<?php

declare(strict_types=1);

namespace SGJobs\Ui\Admin;

class SettingsView
{
    public static function renderNotice(string $message, string $type = 'info'): void
    {
        printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($type), esc_html($message));
    }
}
