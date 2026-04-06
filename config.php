<?php
return [
    'DB_DSN' => 'sqlite:/home/arc/Desktop/o_o/collector/storage/collector.sqlite',
    'DB_USER' => '',
    'DB_PASS' => '',

    'allowed_cidrs' => [
        '192.168.204.0/24',
    ],

    'trusted_proxy' => false,
    'trusted_proxy_ips' => ['127.0.0.1'],

    'upload_max_bytes' => 20 * 1024 * 1024,
    'allowed_mime_types' => [
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    'upload_dir' => '/home/arc/Desktop/o_o/collector/storage/uploads',
    'temp_dir' => '/home/arc/Desktop/o_o/collector/storage/tmp',

    'admin_password_hash' => '',

    'zip_retention_days' => 14,
    'lockout_max_attempts' => 5,
    'lockout_duration_seconds' => 900,

    // Daily submission window.
    'submission_window_enabled' => true,
    'submission_window_start' => '12:00',
    'submission_window_duration_minutes' => 15,
    'submission_window_timezone' => 'Asia/Kolkata',

    'site_base_url' => 'http://127.0.0.1:8080',
    'log_file' => '/home/arc/Desktop/o_o/collector/logs/app.log',
];
