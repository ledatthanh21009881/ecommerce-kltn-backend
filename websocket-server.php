<?php

require_once 'vendor/autoload.php';

use App\Services\WebSocketService;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Start WebSocket server
$webSocketService = new WebSocketService();
$webSocketService->startServer(8080);
