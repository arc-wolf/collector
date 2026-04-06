<?php
return [
    'DB_DSN' => 'mysql:host=127.0.0.1;dbname=collector;charset=utf8mb4',
    'DB_USER' => 'collector_user',
    'DB_PASS' => 'change_me',

    // Allowed student client networks.
    'allowed_cidrs' => [
        '192.168.1.0/24',
        '10.0.0.0/8',
    ],

    // Keep false unless behind a trusted reverse proxy.
    'trusted_proxy' => false,
    'trusted_proxy_ips' => ['127.0.0.1'],

    'upload_max_bytes' => 20 * 1024 * 1024,

    // Extension => MIME type (validated using finfo).
    'allowed_mime_types' => [
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    // Absolute paths outside webroot.
    'upload_dir' => '/srv/collector/uploads',
    'temp_dir' => '/srv/collector/tmp',

    // Optional fallback if admins table is unavailable.
    'admin_password_hash' => '',

    'zip_retention_days' => 14,
    'lockout_max_attempts' => 5,
    'lockout_duration_seconds' => 900,

    // Optional daily submission window.
    'submission_window_enabled' => false,
    'submission_window_start' => '12:00',
    'submission_window_duration_minutes' => 15,
    'submission_window_timezone' => 'UTC',

    'site_base_url' => 'http://collector.local',

    // App log (append-only), writable by Apache user.
    'log_file' => '/var/log/collector/app.log',
];
