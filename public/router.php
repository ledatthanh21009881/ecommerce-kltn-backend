<?php
// Router for PHP built-in server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Debug: Log the request
error_log("ROUTER: Request URI: " . $uri);

// If the URI is a file or directory, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    error_log("ROUTER: Serving file: " . $uri);
    return false;
}

// For all API requests, serve index.php
if (strpos($uri, '/api/') === 0) {
    error_log("ROUTER: API request, serving index.php");
    require_once __DIR__ . '/index.php';
    return true;
}

// For root requests, serve index.php
if ($uri === '/' || $uri === '') {
    error_log("ROUTER: Root request, serving index.php");
    require_once __DIR__ . '/index.php';
    return true;
}

// For all other requests, serve index.php
error_log("ROUTER: Other request, serving index.php");
require_once __DIR__ . '/index.php';
return true;
