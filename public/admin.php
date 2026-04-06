<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zip.php';

$action = $_GET['action'] ?? '';
$clientIp = get_client_ip($config);

if ($action === 'logout') {
    admin_logout();
    header('Location: /admin');
    exit;
}

if (!admin_logged_in()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $error = 'Invalid CSRF token.';
        } elseif (admin_is_locked($pdo, $clientIp, $config)) {
            $error = 'Too many failed attempts. Try again later.';
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (admin_login($pdo, $config, $username, $password, $clientIp)) {
                log_event($config, 'admin_login_success', ['username' => $username, 'ip' => $clientIp]);
                header('Location: /admin');
                exit;
            }
            log_event($config, 'admin_login_failed', ['username' => $username, 'ip' => $clientIp]);
            $error = 'Invalid credentials.';
        }
    }

    render('admin_login', ['csrf' => csrf_token(), 'error' => $error]);
    exit;
}

if ($action === 'view') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $submission = $stmt->fetch();

    if (!$submission) {
        http_response_code(404);
        exit('Not found');
    }

    render('admin_view', ['submission' => $submission]);
    exit;
}

if ($action === 'download_file') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row || $row['status'] === 'deleted') {
        http_response_code(404);
        exit('Not found');
    }

    $fullPath = rtrim($config['upload_dir'], '/') . '/' . basename((string) $row['stored_filename']);
    if (!is_file($fullPath)) {
        http_response_code(404);
        exit('File missing');
    }

    log_event($config, 'admin_download_file', ['admin' => admin_user()['username'] ?? '', 'submission_id' => $id]);

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Disposition: attachment; filename="' . basename((string) $row['original_filename']) . '"');
    readfile($fullPath);
    exit;
}

if ($action === 'download_zip') {
    $file = basename((string) ($_GET['file'] ?? ''));
    if (!preg_match('/^submissions_\d{8}_\d{6}\.zip$/', $file)) {
        http_response_code(400);
        exit('Invalid ZIP name');
    }

    $fullPath = rtrim($config['temp_dir'], '/') . '/' . $file;
    if (!is_file($fullPath)) {
        http_response_code(404);
        exit('ZIP not found');
    }

    log_event($config, 'admin_download_zip', ['admin' => admin_user()['username'] ?? '', 'zip' => $file]);

    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Disposition: attachment; filename="' . $file . '"');
    readfile($fullPath);
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        if ($postAction === 'toggle_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT status FROM submissions WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $newStatus = $row['status'] === 'revoked' ? 'submitted' : 'revoked';
                if ($row['status'] === 'deleted') {
                    $error = 'Cannot toggle a deleted submission.';
                } else {
                    $u = $pdo->prepare('UPDATE submissions SET status = :s WHERE id = :id');
                    $u->execute([':s' => $newStatus, ':id' => $id]);
                    $message = 'Status updated.';
                    log_event($config, 'admin_toggle_status', ['submission_id' => $id, 'status' => $newStatus]);
                }
            }
        } elseif ($postAction === 'soft_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $deleteFile = isset($_POST['delete_file']);
            $stmt = $pdo->prepare('SELECT stored_filename FROM submissions WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare('UPDATE submissions SET status = "deleted" WHERE id = :id')->execute([':id' => $id]);
                if ($deleteFile) {
                    $fullPath = rtrim($config['upload_dir'], '/') . '/' . basename((string) $row['stored_filename']);
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                $message = 'Submission deleted.';
                log_event($config, 'admin_soft_delete', ['submission_id' => $id, 'physical_delete' => $deleteFile]);
            }
        } elseif ($postAction === 'generate_zip') {
            $ids = array_map('intval', $_POST['selected_ids'] ?? []);
            $ids = array_values(array_filter($ids, fn(int $x): bool => $x > 0));
            if ($ids === []) {
                $error = 'Select at least one submission.';
            } else {
                try {
                    $result = generate_submissions_zip($pdo, $config, $ids, []);
                    $message = 'ZIP generated: ' . $result['name'] . ' (' . $result['count'] . ' files)';
                    log_event($config, 'zip_generated', ['zip' => $result['name'], 'count' => $result['count']]);
                } catch (Throwable $t) {
                    $error = $t->getMessage();
                }
            }
        } elseif ($postAction === 'cleanup_zips') {
            $deleted = cleanup_old_zips($config);
            $message = 'Old ZIP cleanup removed ' . $deleted . ' file(s).';
            log_event($config, 'zip_cleanup', ['deleted' => $deleted]);
        }
    }
}

$filters = [
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'ip' => trim((string) ($_GET['ip'] ?? '')),
    'register_no' => trim((string) ($_GET['register_no'] ?? '')),
];

$where = [];
$params = [];

if ($filters['date_from'] !== '') {
    $where[] = 'created_at >= :from';
    $params[':from'] = $filters['date_from'] . ' 00:00:00';
}
if ($filters['date_to'] !== '') {
    $where[] = 'created_at <= :to';
    $params[':to'] = $filters['date_to'] . ' 23:59:59';
}
if ($filters['ip'] !== '') {
    $where[] = 'ip = :ip';
    $params[':ip'] = $filters['ip'];
}
if ($filters['register_no'] !== '') {
    $where[] = 'register_no = :register_no';
    $params[':register_no'] = $filters['register_no'];
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM submissions' . $whereSql);
$countStmt->execute($params);
$total = (int) (($countStmt->fetch()['c'] ?? 0));
$pages = max(1, (int) ceil($total / $perPage));

$listSql = 'SELECT * FROM submissions' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
$listStmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) {
    $listStmt->bindValue($k, $v);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$submissions = $listStmt->fetchAll();

$zipFiles = [];
foreach (glob(rtrim($config['temp_dir'], '/') . '/submissions_*.zip') ?: [] as $zipPath) {
    $zipFiles[] = [
        'name' => basename($zipPath),
        'size' => filesize($zipPath),
        'mtime' => gmdate('Y-m-d H:i:s', filemtime($zipPath)),
    ];
}
usort($zipFiles, fn(array $a, array $b): int => strcmp($b['name'], $a['name']));

render('admin_dashboard', [
    'csrf' => csrf_token(),
    'message' => $message,
    'error' => $error,
    'filters' => $filters,
    'submissions' => $submissions,
    'page' => $page,
    'pages' => $pages,
    'total' => $total,
    'zipFiles' => $zipFiles,
]);
