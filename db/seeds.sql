INSERT INTO admins (username, password_hash)
VALUES (
    'admin',
    '$2y$12$WfeRJifaDC1trpqIOUEbQui2mr6WefRl0rfJbY6rBMYBt15SvFHhC'
)
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);
