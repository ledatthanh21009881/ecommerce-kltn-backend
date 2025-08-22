<?php
// Router file for PHP built-in server

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// For static files in public directory, let the server handle them
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $requestPath)) {
    $filePath = __DIR__ . '/public' . $requestPath;
    if (file_exists($filePath)) {
        return false; // Let PHP built-in server serve the file
    }
}

// For HTML files in public directory
if (preg_match('/\.html$/', $requestPath)) {
    $filePath = __DIR__ . '/public' . $requestPath;
    if (file_exists($filePath)) {
        return false; // Let PHP built-in server serve the file
    }
}

// For PHP files in public directory (like direct-admin-login.php)
if (preg_match('/\.php$/', $requestPath) && $requestPath !== '/index.php') {
    $filePath = __DIR__ . '/public' . $requestPath;
    if (file_exists($filePath)) {
        return false; // Let PHP built-in server serve the file
    }
}

// All other requests (including API routes) go through the main router
$_SERVER['REQUEST_URI'] = $requestUri;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';

// Change working directory to public
chdir(__DIR__ . '/public');

// Include the main index.php
require __DIR__ . '/public/index.php';
