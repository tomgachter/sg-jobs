<?php

declare(strict_types=1);

namespace SGJobs\Infra\DB\Migrations;

use wpdb;

class Migration003CreateTeamsTable
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'sg_teams';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            caldav_principal VARCHAR(255) NOT NULL,
            team_calendar_path TEXT NOT NULL,
            blocker_calendar_path TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) {$charset};";
        dbDelta($sql);
    }
}
