#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron: đơn new_request quá SHIPPER_ACCEPT_TIMEOUT_SECONDS không accept → gán shipper khác.
 *
 * Usage:
 *   php scripts/reassign-shipper-accept-timeout.php
 *
 * Env: SHIPPER_ACCEPT_TIMEOUT_SECONDS=120 (default 2 min)
 *      SHIPPER_ACCEPT_TIMEOUT_MAX_ATTEMPTS=5
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

use App\Core\Database;
use App\Domain\Orders\Order;
use App\Services\ShipperAcceptTimeoutService;

$timeoutSeconds = (int) ($_ENV['SHIPPER_ACCEPT_TIMEOUT_SECONDS'] ?? 120);
$maxAttempts = (int) ($_ENV['SHIPPER_ACCEPT_TIMEOUT_MAX_ATTEMPTS'] ?? 5);

if ($timeoutSeconds < 60) {
    fwrite(STDERR, "SHIPPER_ACCEPT_TIMEOUT_SECONDS must be >= 60\n");
    exit(1);
}
if ($maxAttempts < 1) {
    fwrite(STDERR, "SHIPPER_ACCEPT_TIMEOUT_MAX_ATTEMPTS must be >= 1\n");
    exit(1);
}

$pdo = createPdo($root);
$config = require $root . '/app/config/database.php';
$database = new Database($config);
$orderModel = new Order($database);

$service = new ShipperAcceptTimeoutService($pdo, $orderModel, $timeoutSeconds, $maxAttempts);
$stats = $service->processStaleAssignments();

echo sprintf(
    "[%s] Accept timeout reassign (timeout=%ds, max_attempts=%d)\n",
    date('Y-m-d H:i:s'),
    $timeoutSeconds,
    $maxAttempts
);
echo '  scanned: ' . $stats['scanned'] . "\n";
echo '  reassigned: ' . $stats['reassigned'] . "\n";
echo '  no_shipper: ' . $stats['no_shipper'] . "\n";
echo '  max_attempts: ' . $stats['max_attempts'] . "\n";
echo '  skipped: ' . $stats['skipped'] . "\n";
echo '  errors: ' . $stats['errors'] . "\n";

exit($stats['errors'] > 0 ? 1 : 0);

function createPdo(string $root): PDO
{
    $config = require $root . '/app/config/database.php';
    $mysql = $config['connections']['mysql'] ?? [];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $mysql['host'] ?? '127.0.0.1',
        $mysql['port'] ?? '3306',
        $mysql['database'] ?? 'shopswiftv2',
        $mysql['charset'] ?? 'utf8mb4',
    );

    return new PDO(
        $dsn,
        $mysql['username'] ?? 'root',
        $mysql['password'] ?? '',
        $mysql['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
}
