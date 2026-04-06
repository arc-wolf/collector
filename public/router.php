<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/submit') {
    require __DIR__ . '/submit.php';
    return true;
}
if ($path === '/admin') {
    require __DIR__ . '/admin.php';
    return true;
}
if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}
$file = __DIR__ . $path;
if (is_file($file)) {
    return false;
}
http_response_code(404);
echo 'Not Found';
