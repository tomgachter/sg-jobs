<?php
/**
 * Plugin Name: SG Jobs
 * Description: Integrates bexio delivery notes with a scheduling board, CalDAV sync and job sheets for installers.
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Version: 0.1.0
 * Author: SG Operations
 * Text Domain: sg-jobs
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    $notice = static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'SG Jobs requires Composer dependencies. Run "composer install --no-dev" in the plugin directory (locally before zipping or via SSH) so "vendor/autoload.php" exists, or upload a release package that already bundles vendor. See the README → "Deployment package" section for detailed steps.',
            'sg-jobs'
        );
        echo '</p></div>';
    };

    if (function_exists('add_action')) {
        add_action('admin_notices', $notice);
        add_action('network_admin_notices', $notice);
    }

    return;
}

require_once $autoload;

if (! defined('SGJOBS_MAIN_FILE')) {
    define('SGJOBS_MAIN_FILE', __FILE__);
}

if (! defined('SGJOBS_VERSION')) {
    define('SGJOBS_VERSION', '0.1.0');
}

if (! defined('SGJOBS_PLUGIN_FILE')) {
    define('SGJOBS_PLUGIN_FILE', SGJOBS_MAIN_FILE);
}

use SGJobs\Bootstrap;

$bootstrap = Bootstrap::instance();
$bootstrap->init();
