<?php

declare(strict_types=1);

namespace SGJobs\Infra\DB\Migrations;

use wpdb;

class Migration005CreateJobAuditTable
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'sg_job_audit';
        $jobs = $this->wpdb->prefix . 'sg_jobs';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            actor VARCHAR(191) NOT NULL,
            action VARCHAR(64) NOT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            CONSTRAINT fk_job_audit_job FOREIGN KEY (job_id) REFERENCES {$jobs}(id) ON DELETE CASCADE
        ) {$charset};";
        dbDelta($sql);
    }
}
