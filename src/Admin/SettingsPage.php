<?php

declare(strict_types=1);

namespace SGJobs\Admin;

use function sgj_get_normalized_teams;
use function sgj_normalize_teams_data;

class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenu(): void
    {
        add_options_page(
            __('SG Jobs Settings', 'sg-jobs'),
            __('SG Jobs', 'sg-jobs'),
            'manage_options',
            'sg-jobs',
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting('sg_jobs', 'sg_jobs_bexio', ['sanitize_callback' => [$this, 'sanitizeArray']]);
        register_setting('sg_jobs', 'sg_jobs_caldav', ['sanitize_callback' => [$this, 'sanitizeArray']]);
        register_setting('sg_jobs', 'sg_jobs_jwt', ['sanitize_callback' => [$this, 'sanitizeJwt']]);
        register_setting('sg_jobs', 'sg_jobs_teams', ['sanitize_callback' => [$this, 'sanitizeTeams']]);

        add_settings_section('sg_jobs_bexio_section', __('bexio', 'sg-jobs'), function (): void {
            echo '<p>' . esc_html__('Configure the bexio API connection. Delivery notes are fetched live.', 'sg-jobs') . '</p>';
        }, 'sg-jobs');

        add_settings_field('sg_jobs_bexio_base', __('Base URL', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_bexio', []);
            $value = esc_attr($options['base_url'] ?? '');
            echo '<input type="url" name="sg_jobs_bexio[base_url]" value="' . $value . '" class="regular-text" required />';
        }, 'sg-jobs', 'sg_jobs_bexio_section');

        add_settings_field('sg_jobs_bexio_token', __('API Token', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_bexio', []);
            $value = esc_attr($options['token'] ?? '');
            echo '<input type="password" name="sg_jobs_bexio[token]" value="' . $value . '" class="regular-text" required autocomplete="off" />';
        }, 'sg-jobs', 'sg_jobs_bexio_section');

        add_settings_section('sg_jobs_caldav_section', __('CalDAV', 'sg-jobs'), function (): void {
            echo '<p>' . esc_html__('Provide the Nextcloud/SabreDAV credentials used for event creation.', 'sg-jobs') . '</p>';
        }, 'sg-jobs');

        add_settings_field('sg_jobs_caldav_base', __('Base URL', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_caldav', []);
            $value = esc_attr($options['base_url'] ?? '');
            echo '<input type="url" name="sg_jobs_caldav[base_url]" value="' . $value . '" class="regular-text" required />';
        }, 'sg-jobs', 'sg_jobs_caldav_section');

        add_settings_field('sg_jobs_caldav_user', __('Username', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_caldav', []);
            $value = esc_attr($options['username'] ?? '');
            echo '<input type="text" name="sg_jobs_caldav[username]" value="' . $value . '" class="regular-text" required />';
        }, 'sg-jobs', 'sg_jobs_caldav_section');

        add_settings_field('sg_jobs_caldav_pass', __('Password', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_caldav', []);
            $value = esc_attr($options['password'] ?? '');
            echo '<input type="password" name="sg_jobs_caldav[password]" value="' . $value . '" class="regular-text" required autocomplete="off" />';
        }, 'sg-jobs', 'sg_jobs_caldav_section');

        add_settings_section('sg_jobs_jwt_section', __('Magic links', 'sg-jobs'), function (): void {
            echo '<p>' . esc_html__('Magic link JWTs authenticate installers without WordPress accounts.', 'sg-jobs') . '</p>';
        }, 'sg-jobs');

        add_settings_field('sg_jobs_jwt_secret', __('JWT secret', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_jwt', []);
            $value = esc_attr($options['secret'] ?? '');
            echo '<input type="password" name="sg_jobs_jwt[secret]" value="' . $value . '" class="regular-text" required autocomplete="off" />';
        }, 'sg-jobs', 'sg_jobs_jwt_section');

        add_settings_field('sg_jobs_jwt_expire', __('Expiry days', 'sg-jobs'), function (): void {
            $options = get_option('sg_jobs_jwt', []);
            $value = esc_attr($options['expiry_days'] ?? '14');
            echo '<input type="number" min="1" max="60" name="sg_jobs_jwt[expiry_days]" value="' . $value . '" />';
        }, 'sg-jobs', 'sg_jobs_jwt_section');

        add_settings_section('sg_jobs_teams_section', __('Teams', 'sg-jobs'), function (): void {
            echo '<p>' . esc_html__('Define execution and blocker calendars for each team.', 'sg-jobs') . '</p>';
            echo '<p>' . wp_kses(
                __('Verwenden Sie bei Service-Usern (z. B. <code>caldav-sync</code>) die gemounteten Pfade unter <code>/remote.php/dav/calendars/caldav-sync/…</code> und nicht die Owner-Pfade wie <code>/calendars/imhoff/…</code>. Nextcloud blendet die korrekten Pfade im Teilen-Dialog ein.', 'sg-jobs'),
                ['code' => []]
            ) . '</p>';
        }, 'sg-jobs');

        add_settings_field('sg_jobs_teams_table', __('Teams configuration', 'sg-jobs'), [$this, 'renderTeamsTable'], 'sg-jobs', 'sg_jobs_teams_section');
    }

    public function renderPage(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('SG Jobs', 'sg-jobs') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('sg_jobs');
        do_settings_sections('sg-jobs');
        submit_button();
        echo '</form></div>';
    }

    public function renderTeamsTable(): void
    {
        $teams = $this->getNormalizedTeams();
        $rows = max(1, count($teams)) + 1;
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__('Team name', 'sg-jobs') . '</th><th>' . esc_html__('CalDAV principal', 'sg-jobs') . '</th><th>' . esc_html__('Execution calendar path', 'sg-jobs') . '</th><th>' . esc_html__('Blocker calendar path', 'sg-jobs') . '</th></tr></thead>';
        echo '<tbody>';
        for ($i = 0; $i < $rows; $i++) {
            $team = $teams[$i] ?? ['name' => '', 'principal' => '', 'execution' => '', 'blocker' => ''];
            printf(
                '<tr><td><input type="text" name="sg_jobs_teams[%1$d][name]" value="%2$s" /></td><td><input type="text" name="sg_jobs_teams[%1$d][principal]" value="%3$s" /></td><td><input type="text" name="sg_jobs_teams[%1$d][execution]" value="%4$s" /></td><td><input type="text" name="sg_jobs_teams[%1$d][blocker]" value="%5$s" /></td></tr>',
                $i,
                esc_attr($team['name'] ?? ''),
                esc_attr($team['principal'] ?? ''),
                esc_attr($team['execution'] ?? ''),
                esc_attr($team['blocker'] ?? '')
            );
        }
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__('Füllen Sie mehrere Zeilen aus; eine zusätzliche leere Zeile steht immer zur Verfügung. Leere Zeilen werden ignoriert.', 'sg-jobs') . '</p>';
    }

    public function sanitizeArray(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        foreach ($input as $key => $value) {
            $input[$key] = is_string($value) ? sanitize_text_field($value) : $value;
        }

        return $input;
    }

    public function sanitizeJwt(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $secret = sanitize_text_field((string) ($input['secret'] ?? ''));
        $expiry = (int) ($input['expiry_days'] ?? 14);
        if ($secret === '') {
            add_settings_error('sg_jobs_jwt', 'missing_secret', __('Das JWT-Secret darf nicht leer sein.', 'sg-jobs'));
        }
        if ($expiry <= 0) {
            $expiry = 14;
            add_settings_error('sg_jobs_jwt', 'invalid_expiry', __('Expiry days muss größer als 0 sein.', 'sg-jobs'));
        }

        update_option('sg_jobs_jwt_secret', $secret, false);
        update_option('sg_jobs_jwt_expire_days', $expiry, false);

        return [
            'secret' => $secret,
            'expiry_days' => $expiry,
        ];
    }

    public function sanitizeTeams(mixed $input): array
    {
        if (! is_array($input)) {
            add_settings_error('sg_jobs_teams', 'invalid_format', __('Ungültiges Team-Format.', 'sg-jobs'));

            return [];
        }

        $teams = [];
        foreach ($input as $team) {
            if (! is_array($team)) {
                continue;
            }

            $name = sanitize_text_field((string) ($team['name'] ?? ''));
            $principal = sanitize_text_field((string) ($team['principal'] ?? ($team['caldav_principal'] ?? '')));
            $execution = sanitize_text_field((string) ($team['execution'] ?? ($team['execution_path'] ?? ($team['calendar'] ?? ($team['exec'] ?? '')))));
            $blocker = sanitize_text_field((string) ($team['blocker'] ?? ($team['blocker_path'] ?? '')));

            if ($name === '' && $principal === '' && $execution === '' && $blocker === '') {
                continue;
            }

            if ($name === '' || $principal === '' || $execution === '') {
                add_settings_error('sg_jobs_teams', 'missing_fields', __('Team-Zeilen benötigen Name, Principal und Ausführungspfad.', 'sg-jobs'));

                continue;
            }

            $teams[] = [
                'name' => $name,
                'principal' => $principal,
                'execution' => $execution,
                'blocker' => $blocker,
            ];
        }

        $teams = sgj_normalize_teams_data($teams);

        $count = count($teams);
        if ($count > 0) {
            add_settings_error(
                'sg_jobs_teams',
                'teams_saved',
                sprintf(_n('%d Team gespeichert.', '%d Teams gespeichert.', $count, 'sg-jobs'), $count),
                'updated'
            );
        }

        return $teams;
    }

    /**
     * @return array<int, array{name:string,principal:string,execution:string,blocker:string}>
     */
    private function getNormalizedTeams(): array
    {
        return sgj_get_normalized_teams();
    }
}
