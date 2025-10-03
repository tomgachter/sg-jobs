<?php

declare(strict_types=1);

namespace SGJobs\Infra\DB\Migrations;

use wpdb;

class Migration002CreateJobPositionsTable
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'sg_job_positions';
        $jobs = $this->wpdb->prefix . 'sg_jobs';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            bexio_position_id BIGINT UNSIGNED NOT NULL,
            article_no VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            qty DECIMAL(10,2) NOT NULL DEFAULT 0,
            unit VARCHAR(16) NOT NULL,
            work_type VARCHAR(16) NOT NULL DEFAULT 'unknown',
            sort INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            CONSTRAINT fk_job_positions_job FOREIGN KEY (job_id) REFERENCES {$jobs}(id) ON DELETE CASCADE
        ) {$charset};";
        dbDelta($sql);
    }
}
