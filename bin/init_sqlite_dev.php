#!/usr/bin/env php
<?php

declare(strict_types=1);

$dbPath = __DIR__ . '/../storage/collector.sqlite';
@mkdir(__DIR__ . '/../storage', 0775, true);

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE IF NOT EXISTS submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    register_no TEXT NOT NULL UNIQUE,
    set_no TEXT NULL,
    original_filename TEXT NOT NULL,
    stored_filename TEXT NOT NULL,
    file_hash TEXT NOT NULL,
    ip TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ("submitted","revoked","deleted"))
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    attempted_at TEXT NOT NULL,
    success INTEGER NOT NULL DEFAULT 0
)');

$hash = '$2y$12$WfeRJifaDC1trpqIOUEbQui2mr6WefRl0rfJbY6rBMYBt15SvFHhC';
$stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :h)
    ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash');
$stmt->execute([':u' => 'admin', ':h' => $hash]);

echo "Initialized SQLite DB at: {$dbPath}\n";
