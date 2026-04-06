<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

require_once __DIR__ . '/helpers.php';

$configPath = getenv('APP_CONFIG') ?: (__DIR__ . '/../config.php');
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Missing config.php. Copy config.example.php to a secure path and set APP_CONFIG.');
}

$config = require $configPath;
if (!is_array($config)) {
    http_response_code(500);
    exit('Invalid config format.');
}

$required = ['DB_DSN', 'DB_USER', 'DB_PASS', 'upload_dir', 'temp_dir'];
foreach ($required as $k) {
    if (!array_key_exists($k, $config)) {
        http_response_code(500);
        exit('Missing required config key: ' . e($k));
    }
}

if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0750, true);
}
if (!is_dir($config['temp_dir'])) {
    mkdir($config['temp_dir'], 0750, true);
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

$pdo = new PDO(
    $config['DB_DSN'],
    $config['DB_USER'],
    $config['DB_PASS'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    if (!isset($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/../templates/' . $template . '.php';
}
