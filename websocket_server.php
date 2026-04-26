<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Services\WebSocketService;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create WebSocket service
$webSocketService = new WebSocketService();

// Start server
echo "Starting WebSocket server on port 8080...\n";
$server = IoServer::factory(
    new HttpServer(
        new WsServer($webSocketService)
    ),
    8080
);

echo "WebSocket server is running on ws://localhost:8080\n";

$server->loop->addPeriodicTimer(1.0, function () use ($webSocketService) {
    $webSocketService->drainAdminNotificationQueue();
});

$server->run();
