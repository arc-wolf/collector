<?php

declare(strict_types=1);

function build_zip_rows(PDO $pdo, ?array $ids, array $filters): array
{
    $sql = 'SELECT id, register_no, ip, created_at, original_filename, stored_filename
            FROM submissions
            WHERE status <> "deleted"';
    $params = [];

    if ($ids !== null && $ids !== []) {
        $placeholders = [];
        foreach (array_values($ids) as $i => $id) {
            $k = ':id' . $i;
            $placeholders[] = $k;
            $params[$k] = (int) $id;
        }
        $sql .= ' AND id IN (' . implode(',', $placeholders) . ')';
    }

    if (!empty($filters['from'])) {
        $sql .= ' AND created_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $sql .= ' AND created_at <= :to';
        $params[':to'] = $filters['to'] . ' 23:59:59';
    }
    if (!empty($filters['ip'])) {
        $sql .= ' AND ip = :ip';
        $params[':ip'] = $filters['ip'];
    }

    $sql .= ' ORDER BY created_at ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function generate_submissions_zip(PDO $pdo, array $config, ?array $ids = null, array $filters = [], ?string $outputPath = null): array
{
    $rows = build_zip_rows($pdo, $ids, $filters);
    if ($rows === []) {
        throw new RuntimeException('No submissions matched ZIP criteria.');
    }

    $zipName = $outputPath ? basename($outputPath) : ('submissions_' . gmdate('Ymd_His') . '.zip');
    $zipPath = $outputPath ?: rtrim($config['temp_dir'], '/') . '/' . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create ZIP file.');
    }

    $csv = [['id', 'register_no', 'ip', 'created_at', 'zip_filename']];

    foreach ($rows as $row) {
        $stored = basename((string) $row['stored_filename']);
        $fullPath = rtrim($config['upload_dir'], '/') . '/' . $stored;
        if (!is_file($fullPath)) {
            continue;
        }

        $ext = strtolower((string) pathinfo((string) $row['original_filename'], PATHINFO_EXTENSION));
        $suffix = $ext !== '' ? '.' . $ext : '';
        $zipFileName = sanitize_component((string) $row['register_no']) . '_' . safe_ip_token((string) $row['ip']) . $suffix;

        $zip->addFile($fullPath, $zipFileName);
        $csv[] = [
            (string) $row['id'],
            (string) $row['register_no'],
            (string) $row['ip'],
            (string) $row['created_at'],
            $zipFileName,
        ];
    }

    if (count($csv) === 1) {
        $zip->close();
        @unlink($zipPath);
        throw new RuntimeException('No physical files available for selected submissions.');
    }

    $zip->addFromString('submissions_index.csv', csv_string($csv));
    $zip->close();

    chmod($zipPath, 0640);

    return [
        'path' => $zipPath,
        'name' => basename($zipPath),
        'count' => count($csv) - 1,
    ];
}

function cleanup_old_zips(array $config): int
{
    $days = (int) ($config['zip_retention_days'] ?? 14);
    if ($days <= 0) {
        return 0;
    }

    $deleted = 0;
    $cutoff = time() - ($days * 86400);
    $dir = rtrim($config['temp_dir'], '/');
    foreach (glob($dir . '/submissions_*.zip') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }

    return $deleted;
}
