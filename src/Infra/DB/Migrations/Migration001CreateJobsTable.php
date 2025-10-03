<?php

declare(strict_types=1);

namespace SGJobs\Infra\DB\Migrations;

use wpdb;

class Migration001CreateJobsTable
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'sg_jobs';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            delivery_note_id BIGINT UNSIGNED NOT NULL,
            delivery_note_nr VARCHAR(64) NOT NULL,
            sales_order_nr VARCHAR(64) NULL,
            team_id BIGINT UNSIGNED NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            tz VARCHAR(64) NOT NULL DEFAULT 'Europe/Zurich',
            location_city VARCHAR(128) NOT NULL,
            customer_name VARCHAR(191) NOT NULL,
            address_line TEXT NOT NULL,
            phones_json LONGTEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            caldav_event_uid VARCHAR(255) NULL,
            job_token_hash VARCHAR(64) NULL,
            public_job_url TEXT NOT NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY delivery_note_nr (delivery_note_nr),
            KEY caldav_event_uid (caldav_event_uid)
        ) {$charset};";
        dbDelta($sql);
    }
}
