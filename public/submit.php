<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$errors = [];
$success = null;

$clientIp = get_client_ip($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    }

    if ($clientIp === '' || !is_ip_allowed($clientIp, $config['allowed_cidrs'] ?? [])) {
        $errors[] = 'Your IP is not in allowed ranges.';
    }

    $window = submission_window_state($config);
    if (($window['enabled'] ?? false) && !($window['is_open'] ?? true)) {
        $errors[] = 'Submission window is closed. Allowed time: ' . ($window['label'] ?? '');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $registerNo = trim((string) ($_POST['register_no'] ?? ''));
    $setNo = trim((string) ($_POST['set_no'] ?? ''));

    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Name is required (max 120 chars).';
    }

    if (!preg_match('/^[A-Za-z0-9]{1,32}$/', $registerNo)) {
        $errors[] = 'Register number must be alphanumeric and max 32 chars.';
    }

    if ($setNo !== '' && mb_strlen($setNo) > 32) {
        $errors[] = 'Set number max length is 32.';
    }

    $upload = $_FILES['submission_file'] ?? [];
    [$uploadOk, $uploadMsg] = validate_upload_file(
        $upload,
        $config['allowed_mime_types'] ?? [],
        (int) ($config['upload_max_bytes'] ?? 0)
    );
    if (!$uploadOk) {
        $errors[] = $uploadMsg;
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, created_at FROM submissions WHERE ip = :ip LIMIT 1');
        $stmt->execute([':ip' => $clientIp]);
        $existingIp = $stmt->fetch();

        if ($existingIp) {
            $errors[] = 'Submission already exists for this IP. ID ' . $existingIp['id'] . ' at ' . $existingIp['created_at'];
        }

        $stmt = $pdo->prepare('SELECT id, created_at FROM submissions WHERE register_no = :reg LIMIT 1');
        $stmt->execute([':reg' => $registerNo]);
        $existingReg = $stmt->fetch();

        if ($existingReg) {
            $errors[] = 'Submission already exists for this register number. ID ' . $existingReg['id'] . ' at ' . $existingReg['created_at'];
        }
    }

    if ($errors === []) {
        $safeOriginal = sanitize_original_filename((string) $upload['name']);
        $ext = strtolower((string) pathinfo($safeOriginal, PATHINFO_EXTENSION));
        $safeReg = sanitize_component($registerNo);
        $safeIp = safe_ip_token($clientIp);
        $stored = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeReg . '_' . $safeIp;
        if ($ext !== '') {
            $stored .= '.' . $ext;
        }

        $targetPath = rtrim($config['upload_dir'], '/') . '/' . $stored;
        if (!move_uploaded_file((string) $upload['tmp_name'], $targetPath)) {
            $errors[] = 'Failed to store uploaded file.';
        } else {
            chmod($targetPath, 0640);

            // Optional ClamAV hook: run scanner on $targetPath before accepting.
            $hash = hash_file('sha256', $targetPath) ?: '';

            $stmt = $pdo->prepare('INSERT INTO submissions
                (name, register_no, set_no, original_filename, stored_filename, file_hash, ip, created_at, status)
                VALUES
                (:name, :reg, :set_no, :orig, :stored, :hash, :ip, :created_at, "submitted")');

            try {
                $stmt->execute([
                    ':name' => $name,
                    ':reg' => $registerNo,
                    ':set_no' => $setNo !== '' ? $setNo : null,
                    ':orig' => $safeOriginal,
                    ':stored' => $stored,
                    ':hash' => $hash,
                    ':ip' => $clientIp,
                    ':created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (PDOException $e) {
                @unlink($targetPath);
                if ($e->getCode() === '23000') {
                    $dupStmt = $pdo->prepare('SELECT id, created_at FROM submissions WHERE ip = :ip OR register_no = :reg ORDER BY id ASC LIMIT 1');
                    $dupStmt->execute([':ip' => $clientIp, ':reg' => $registerNo]);
                    $dup = $dupStmt->fetch();
                    if ($dup) {
                        $errors[] = 'Submission already exists. ID ' . $dup['id'] . ' at ' . $dup['created_at'];
                    } else {
                        $errors[] = 'Submission already exists.';
                    }
                    render('submit_form', [
                        'errors' => $errors,
                        'success' => null,
                        'csrf' => csrf_token(),
                        'clientIp' => $clientIp,
                        'post' => [
                            'name' => (string) ($_POST['name'] ?? ''),
                            'register_no' => (string) ($_POST['register_no'] ?? ''),
                            'set_no' => (string) ($_POST['set_no'] ?? ''),
                        ],
                    ]);
                    exit;
                }
                throw $e;
            }

            $newId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT created_at FROM submissions WHERE id = :id');
            $stmt->execute([':id' => $newId]);
            $createdAt = (string) (($stmt->fetch()['created_at'] ?? ''));

            log_event($config, 'submission_created', [
                'submission_id' => $newId,
                'register_no' => $registerNo,
                'ip' => $clientIp,
            ]);

            $success = ['id' => $newId, 'created_at' => $createdAt];
        }
    }
}

render('submit_form', [
    'errors' => $errors,
    'success' => $success,
    'csrf' => csrf_token(),
    'clientIp' => $clientIp,
    'post' => [
        'name' => (string) ($_POST['name'] ?? ''),
        'register_no' => (string) ($_POST['register_no'] ?? ''),
        'set_no' => (string) ($_POST['set_no'] ?? ''),
    ],
]);
