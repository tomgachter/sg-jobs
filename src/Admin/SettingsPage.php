<?php

declare(strict_types=1);

namespace SGJobs\Admin;

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
        register_setting('sg_jobs', 'sg_jobs_jwt', ['sanitize_callback' => [$this, 'sanitizeArray']]);
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
        $teams = get_option('sg_jobs_teams', []);
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__('Team name', 'sg-jobs') . '</th><th>' . esc_html__('CalDAV principal', 'sg-jobs') . '</th><th>' . esc_html__('Execution calendar path', 'sg-jobs') . '</th><th>' . esc_html__('Blocker calendar path', 'sg-jobs') . '</th></tr></thead>';
        echo '<tbody>';
        for ($i = 0; $i < max(1, count($teams)); $i++) {
            $team = $teams[$i] ?? [];
            printf(
                '<tr><td><input type="text" name="sg_jobs_teams[%1$d][name]" value="%2$s" /></td><td><input type="text" name="sg_jobs_teams[%1$d][principal]" value="%3$s" /></td><td><input type="text" name="sg_jobs_teams[%1$d][calendar]" value="%4$s" /></td><td><input type="text" name="sg_jobs_teams[%1$d][blocker]" value="%5$s" /></td></tr>',
                $i,
                esc_attr($team['name'] ?? ''),
                esc_attr($team['principal'] ?? ''),
                esc_attr($team['calendar'] ?? ''),
                esc_attr($team['blocker'] ?? '')
            );
        }
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__('Leave blank rows empty to remove teams. CalDAV paths must be full collection URLs.', 'sg-jobs') . '</p>';
    }

    public function sanitizeArray(array $input): array
    {
        foreach ($input as $key => $value) {
            $input[$key] = is_string($value) ? sanitize_text_field($value) : $value;
        }

        return $input;
    }

    public function sanitizeTeams(array $input): array
    {
        $teams = [];
        foreach ($input as $team) {
            if (empty($team['name']) || empty($team['principal']) || empty($team['calendar'])) {
                continue;
            }
            $teams[] = [
                'name' => sanitize_text_field($team['name']),
                'principal' => sanitize_text_field($team['principal']),
                'calendar' => esc_url_raw($team['calendar']),
                'blocker' => esc_url_raw($team['blocker'] ?? ''),
            ];
        }

        return $teams;
    }
}
