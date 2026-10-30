<?php

declare(strict_types=1);

// Create the first Collector administrator without shipping a known password.
// Run with APP_CONFIG pointing to the protected config file and
// COLLECTOR_ADMIN_PASSWORD set in the process environment.

require_once __DIR__ . '/../src/bootstrap.php';

$username = trim((string) (getenv('COLLECTOR_ADMIN_USERNAME') ?: 'admin'));
$password = (string) (getenv('COLLECTOR_ADMIN_PASSWORD') ?: '');

if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
    fwrite(STDERR, "Username must be 3-64 characters: letters, numbers, dot, underscore or hyphen.\n");
    exit(2);
}

if (strlen($password) < 14) {
    fwrite(STDERR, "Set COLLECTOR_ADMIN_PASSWORD to a unique password of at least 14 characters.\n");
    exit(2);
}

$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username LIMIT 1');
$stmt->execute([':username' => $username]);
if ($stmt->fetch()) {
    fwrite(STDERR, "An administrator with that username already exists; refusing to overwrite it.\n");
    exit(3);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "Could not hash administrator password.\n");
    exit(4);
}

$stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
$stmt->execute([':username' => $username, ':password_hash' => $hash]);

fwrite(STDOUT, "Administrator account created: {$username}\n");
