CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    register_no VARCHAR(32) NOT NULL,
    set_no VARCHAR(32) NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_hash CHAR(64) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    status ENUM('submitted','revoked','deleted') NOT NULL DEFAULT 'submitted',
    UNIQUE KEY uq_register_no (register_no),
    UNIQUE KEY uq_ip (ip),
    KEY idx_created_at (created_at),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_ip_attempted (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
