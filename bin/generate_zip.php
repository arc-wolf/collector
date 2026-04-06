#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/zip.php';

$options = getopt('', ['from::', 'to::', 'ip::', 'output::']);

$filters = [
    'from' => $options['from'] ?? '',
    'to' => $options['to'] ?? '',
    'ip' => $options['ip'] ?? '',
];

if ($filters['from'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
    fwrite(STDERR, "Invalid --from format. Use YYYY-MM-DD\n");
    exit(2);
}
if ($filters['to'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
    fwrite(STDERR, "Invalid --to format. Use YYYY-MM-DD\n");
    exit(2);
}
if ($filters['ip'] !== '' && !filter_var($filters['ip'], FILTER_VALIDATE_IP)) {
    fwrite(STDERR, "Invalid --ip value\n");
    exit(2);
}

$output = $options['output'] ?? null;
if ($output !== null) {
    $dir = dirname($output);
    if (!is_dir($dir) || !is_writable($dir)) {
        fwrite(STDERR, "Output directory is not writable: {$dir}\n");
        exit(3);
    }
}

try {
    $result = generate_submissions_zip($pdo, $config, null, $filters, $output ?: null);
    log_event($config, 'zip_generated_cli', ['zip' => $result['name'], 'count' => $result['count'], 'filters' => $filters]);
    fwrite(STDOUT, $result['path'] . PHP_EOL);
    exit(0);
} catch (Throwable $t) {
    fwrite(STDERR, 'ZIP generation failed: ' . $t->getMessage() . PHP_EOL);
    exit(1);
}
