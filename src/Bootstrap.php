<?php

declare(strict_types=1);

namespace SGJobs;

use SGJobs\Admin\SettingsPage;
use SGJobs\App\JobsService;
use SGJobs\App\Sync\BexioPaymentSync;
use SGJobs\Http\Api\HealthController;
use SGJobs\Http\Api\JobsController;
use SGJobs\Http\Api\MagicLinkController;
use SGJobs\Ui\Board\BoardController;
use SGJobs\Ui\JobSheet\JobSheetController;
use WP_Error;

class Bootstrap
{
    private static ?self $instance = null;

    private SettingsPage $settingsPage;

    private function __construct()
    {
        $this->settingsPage = new SettingsPage();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
        $pluginFile = defined('SGJOBS_MAIN_FILE')
            ? SGJOBS_MAIN_FILE
            : (defined('SGJOBS_PLUGIN_FILE') ? SGJOBS_PLUGIN_FILE : __FILE__);

        if (! defined('SGJOBS_PLUGIN_FILE')) {
            define('SGJOBS_PLUGIN_FILE', $pluginFile);
        }

        register_activation_hook($pluginFile, [$this, 'onActivate']);
        register_deactivation_hook($pluginFile, [$this, 'onDeactivate']);
    }

    public function onPluginsLoaded(): void
    {
        $this->settingsPage->register();
        (new JobsController())->registerRoutes();
        (new MagicLinkController())->registerRoutes();
        (new HealthController())->registerRoutes();
        (new BoardController())->register();
        (new JobSheetController())->register();

        add_action('sg_jobs_bexio_payment_sync', [$this, 'handlePaymentSync']);
        if (! wp_next_scheduled('sg_jobs_bexio_payment_sync')) {
            wp_schedule_event(time() + 60, 'fifteen_minutes', 'sg_jobs_bexio_payment_sync');
        }

        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['fifteen_minutes'] = [
                'interval' => 15 * 60,
                'display' => __('Every 15 Minutes', 'sg-jobs'),
            ];

            return $schedules;
        });
    }

    public function onActivate(): void
    {
        $migrations = glob(__DIR__ . '/Infra/DB/Migrations/*.php') ?: [];
        sort($migrations);
        global $wpdb;
        foreach ($migrations as $migration) {
            require_once $migration;
            $className = $this->resolveMigrationClass($migration);
            if ($className && class_exists($className)) {
                $migrationInstance = new $className($wpdb);
                $migrationInstance->up();
            }
        }

        $role = get_role('administrator');
        if ($role && ! $role->has_cap('sgjobs_manage')) {
            $role->add_cap('sgjobs_manage');
        }

        $this->migrateTeamsOption();
        $this->migrateJwtOption();

        (new JobSheetController())->registerRewrites();
        flush_rewrite_rules(false);
    }

    public function onDeactivate(): void
    {
        wp_clear_scheduled_hook('sg_jobs_bexio_payment_sync');
    }

    public function handlePaymentSync(): void
    {
        $service = new JobsService();
        $sync = new BexioPaymentSync($service);
        $result = $sync->syncPaidInvoices();
        if ($result instanceof WP_Error) {
            error_log('[SG Jobs] Payment sync failed: ' . $result->get_error_message());
        }
    }

    private function resolveMigrationClass(string $path): ?string
    {
        $file = pathinfo($path, PATHINFO_FILENAME);
        $normalized = str_replace(['_', '-'], ' ', $file);
        $class = 'SGJobs\\Infra\\DB\\Migrations\\Migration' . str_replace(' ', '', ucwords($normalized));

        return $class;
    }

    private function migrateTeamsOption(): void
    {
        $value = get_option('sg_jobs_teams');
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                update_option('sg_jobs_teams', $decoded, false);
            }
        }
    }

    private function migrateJwtOption(): void
    {
        $options = get_option('sg_jobs_jwt', []);
        if (! is_array($options)) {
            return;
        }

        if (isset($options['secret'])) {
            update_option('sg_jobs_jwt_secret', (string) $options['secret'], false);
        }

        if (isset($options['expiry_days'])) {
            $expiry = (int) $options['expiry_days'];
            if ($expiry > 0) {
                update_option('sg_jobs_jwt_expire_days', $expiry, false);
            }
        }
    }
}
