<?php

declare(strict_types=1);

function admin_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function admin_logged_in(): bool
{
    return is_array(admin_user());
}

function admin_require_login(): void
{
    if (!admin_logged_in()) {
        header('Location: /admin');
        exit;
    }
}

function admin_is_locked(PDO $pdo, string $ip, array $config): bool
{
    $max = (int) ($config['lockout_max_attempts'] ?? 5);
    $duration = (int) ($config['lockout_duration_seconds'] ?? 900);
    $cutoff = gmdate('Y-m-d H:i:s', time() - $duration);

    $sql = 'SELECT COUNT(*) AS c
            FROM admin_login_attempts
            WHERE ip = :ip AND success = 0 AND attempted_at >= :cutoff';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':cutoff', $cutoff);
    $stmt->execute();

    $count = (int) ($stmt->fetch()['c'] ?? 0);
    return $count >= $max;
}

function admin_record_attempt(PDO $pdo, string $ip, bool $success): void
{
    $stmt = $pdo->prepare('INSERT INTO admin_login_attempts (ip, attempted_at, success) VALUES (:ip, :attempted_at, :success)');
    $stmt->execute([
        ':ip' => $ip,
        ':attempted_at' => gmdate('Y-m-d H:i:s'),
        ':success' => $success ? 1 : 0,
    ]);
}

function admin_login(PDO $pdo, array $config, string $username, string $password, string $ip): bool
{
    if (admin_is_locked($pdo, $ip, $config)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $admin = $stmt->fetch();

    $ok = $admin && password_verify($password, (string) $admin['password_hash']);

    if ($ok) {
        // Prevent session fixation when privileges are granted.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin'] = ['id' => (int) $admin['id'], 'username' => $admin['username']];
    }

    admin_record_attempt($pdo, $ip, (bool) $ok);
    return (bool) $ok;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
