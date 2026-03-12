<?php
declare(strict_types=1);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/../storage/logs/php_errors.log');

// CORS Headers - Add before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Credentials: false');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Debug: Log all requests
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
error_log("INDEX.PHP: {$requestMethod} {$requestPath}");

require __DIR__.'/../vendor/autoload.php';

// Load .env before Container so config (app.php) can read $_ENV (e.g. PAYOS_*)
if (file_exists(__DIR__.'/../.env')) {
    \Dotenv\Dotenv::createImmutable(__DIR__.'/../')->safeLoad();
}

use App\Core\Router;
use App\Core\Request;
use App\Core\Response;
use App\Core\Container;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\AuthMiddleware;

$container = new Container(__DIR__.'/../app/config');
$container->bootEnv(__DIR__.'/../');

// Set database instance in container
$container->set('database', $container->database());

set_exception_handler(function(Throwable $e){
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error'=> true, 'message'=> $e->getMessage()]);
});

$router = new Router(new Request(), new Response(), $container);
$router->use(new CorsMiddleware());

require __DIR__.'/../routes/web.php';
require __DIR__.'/../routes/api.php';

$router->dispatch();
