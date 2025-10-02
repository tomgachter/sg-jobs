<?php

declare(strict_types=1);

namespace SGJobs\Infra\DB\Migrations;

use wpdb;

class Migration004CreateUsersTeamsTable
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'sg_users_teams';
        $teams = $this->wpdb->prefix . 'sg_teams';
        $users = $this->wpdb->users;
        $sql = "CREATE TABLE {$table} (
            user_id BIGINT UNSIGNED NOT NULL,
            team_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, team_id),
            KEY team_id (team_id),
            CONSTRAINT fk_users_teams_user FOREIGN KEY (user_id) REFERENCES {$users}(ID) ON DELETE CASCADE,
            CONSTRAINT fk_users_teams_team FOREIGN KEY (team_id) REFERENCES {$teams}(id) ON DELETE CASCADE
        ) {$charset};";
        dbDelta($sql);
    }
}
