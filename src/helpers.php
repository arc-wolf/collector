<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_component(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]/', '_', $value) ?? '';
    $value = trim($value, '._-');
    return $value !== '' ? $value : 'x';
}

function sanitize_original_filename(string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $safeBase = sanitize_component($base);
    return $ext !== '' ? ($safeBase . '.' . $ext) : $safeBase;
}

function safe_ip_token(string $ip): string
{
    return sanitize_component(str_replace([':', '.'], '_', $ip));
}

function cidr_match(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return false;
    }

    [$subnet, $maskBits] = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $maskBits = (int) $maskBits;
    $maxBits = strlen($ipBin) * 8;
    if ($maskBits < 0 || $maskBits > $maxBits) {
        return false;
    }

    $bytes = intdiv($maskBits, 8);
    $remainder = $maskBits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }

    if ($remainder === 0) {
        return true;
    }

    $mask = (~(0xFF >> $remainder)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
}

function is_ip_allowed(string $ip, array $allowedCidrs): bool
{
    foreach ($allowedCidrs as $cidr) {
        if (cidr_match($ip, (string) $cidr)) {
            return true;
        }
    }
    return false;
}

function get_client_ip(array $config): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        return '';
    }

    if (!empty($config['trusted_proxy']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $trustedProxies = $config['trusted_proxy_ips'] ?? [];
        if (in_array($remote, $trustedProxies, true)) {
            $parts = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
            $candidate = $parts[0] ?? '';
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    return $remote;
}

function log_event(array $config, string $event, array $meta = []): void
{
    $logFile = $config['log_file'] ?? (__DIR__ . '/../logs/app.log');
    $payload = [
        'ts' => gmdate('Y-m-d\TH:i:s\Z'),
        'event' => $event,
        'meta' => $meta,
    ];

    $line = json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function validate_upload_file(array $file, array $allowedMimeTypes, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    $size = (int) ($file['size'] ?? 0);
    if (!is_uploaded_file($tmp)) {
        return [false, 'Invalid upload source.'];
    }

    if ($size <= 0 || $size > $maxBytes) {
        return [false, 'File size exceeds allowed limit.'];
    }

    $original = (string) ($file['name'] ?? '');
    $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
    if ($ext === '' || !isset($allowedMimeTypes[$ext])) {
        return [false, 'File type is not allowed.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mime === false || $mime !== $allowedMimeTypes[$ext]) {
        return [false, 'MIME validation failed.'];
    }

    return [true, 'ok'];
}

function csv_string(array $rows): string
{
    $fp = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    rewind($fp);
    $csv = stream_get_contents($fp) ?: '';
    fclose($fp);
    return $csv;
}

function submission_window_state(array $config): array
{
    $enabled = (bool) ($config['submission_window_enabled'] ?? false);
    $start = (string) ($config['submission_window_start'] ?? '');
    $duration = (int) ($config['submission_window_duration_minutes'] ?? 0);
    $tzName = (string) ($config['submission_window_timezone'] ?? 'UTC');

    if (!$enabled || $start === '' || $duration <= 0) {
        return ['enabled' => false, 'is_open' => true, 'label' => ''];
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
        return ['enabled' => true, 'is_open' => false, 'label' => 'Invalid submission window config'];
    }

    try {
        $tz = new DateTimeZone($tzName);
    } catch (Throwable $e) {
        return ['enabled' => true, 'is_open' => false, 'label' => 'Invalid submission window timezone'];
    }

    $now = new DateTimeImmutable('now', $tz);
    [$h, $m] = array_map('intval', explode(':', $start, 2));
    $windowStart = $now->setTime($h, $m, 0);
    $windowEnd = $windowStart->modify('+' . $duration . ' minutes');
    $isOpen = $now >= $windowStart && $now < $windowEnd;

    $label = $windowStart->format('H:i') . ' to ' . $windowEnd->format('H:i') . ' ' . $tzName;

    return [
        'enabled' => true,
        'is_open' => $isOpen,
        'label' => $label,
    ];
}
