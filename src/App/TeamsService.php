<?php

declare(strict_types=1);

namespace SGJobs\App;

use WP_Error;

class TeamsService
{
    public function listTeams(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'sg_teams ORDER BY name ASC', ARRAY_A);
    }

    public function getTeam(int $teamId): array|WP_Error
    {
        global $wpdb;
        $team = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'sg_teams WHERE id = %d', $teamId), ARRAY_A);
        if (! $team) {
            return new WP_Error('sg_jobs_team_missing', __('Team not found.', 'sg-jobs'));
        }

        return $team;
    }
}
