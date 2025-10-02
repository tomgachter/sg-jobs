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

require_once __DIR__ . '/vendor/autoload.php';

if (! defined('SGJOBS_PLUGIN_FILE')) {
    define('SGJOBS_PLUGIN_FILE', __FILE__);
}

use SGJobs\Bootstrap;

$bootstrap = Bootstrap::instance();
$bootstrap->init();
