#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Xóa ảnh Cloudinary chỉ trong folder shipper-delivery-proofs/order_{id}
 * (pickup / delivery / arrival proof trên bảng order_delivery_proofs).
 *
 * Giữ ảnh cho KEEP_ORDERS đơn mới nhất; xóa proof của đơn khác khi đủ MIN_AGE_MINUTES.
 *
 * Usage:
 *   php scripts/cleanup-shipper-delivery-cloudinary.php --dry-run
 *   php scripts/cleanup-shipper-delivery-cloudinary.php
 *
 * Env (optional): SHIPPER_PROOF_CLEANUP_KEEP_ORDERS=5, SHIPPER_PROOF_CLEANUP_MIN_AGE_MINUTES=5
 */

const SHIPPER_PROOF_FOLDER_PREFIX = 'shipper-delivery-proofs/order_';

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

use App\Services\CloudinaryService;

$options = parseCliOptions($argv);
$keepOrders = (int) ($options['keep'] ?? (int) ($_ENV['SHIPPER_PROOF_CLEANUP_KEEP_ORDERS'] ?? 5));
$minAgeMinutes = (int) ($options['minutes'] ?? (int) ($_ENV['SHIPPER_PROOF_CLEANUP_MIN_AGE_MINUTES'] ?? 5));
$dryRun = (bool) ($options['dry-run'] ?? false);

if ($keepOrders < 1) {
    fwrite(STDERR, "KEEP_ORDERS must be >= 1\n");
    exit(1);
}
if ($minAgeMinutes < 1) {
    fwrite(STDERR, "MIN_AGE_MINUTES must be >= 1\n");
    exit(1);
}

$pdo = createPdo($root);
$cloudinary = new CloudinaryService();

$keepOrderIds = fetchKeepOrderIds($pdo, $keepOrders);
$rows = fetchDeletionCandidates($pdo, $keepOrderIds, $minAgeMinutes);

$stats = [
    'keep_orders' => $keepOrderIds,
    'candidates' => count($rows),
    'deleted_cloudinary' => 0,
    'cleared_db' => 0,
    'skipped' => 0,
    'errors' => 0,
];

echo sprintf(
    "[%s] Shipper delivery proof cleanup (keep=%d orders, age>=%d min, dry-run=%s)\n",
    date('Y-m-d H:i:s'),
    $keepOrders,
    $minAgeMinutes,
    $dryRun ? 'yes' : 'no',
);
echo 'Keeping order_id: ' . (empty($keepOrderIds) ? '(none)' : implode(', ', $keepOrderIds)) . "\n";

foreach ($rows as $row) {
    $proofId = (int) $row['proof_id'];
    $orderId = (int) $row['order_id'];
    $photoUrl = trim((string) $row['photo_url']);
    $proofType = (string) $row['proof_type'];

    $publicId = shipperProofPublicIdFromUrl($photoUrl);
    if ($publicId === null) {
        echo "  SKIP proof_id={$proofId} order={$orderId} (not a shipper Cloudinary URL)\n";
        $stats['skipped']++;
        continue;
    }

    echo "  " . ($dryRun ? 'WOULD DELETE' : 'DELETE')
        . " proof_id={$proofId} order={$orderId} type={$proofType} public_id={$publicId}\n";

    if ($dryRun) {
        continue;
    }

    $deleteResult = $cloudinary->delete($publicId, 'image');
    if (empty($deleteResult['success'])) {
        $err = $deleteResult['error'] ?? 'unknown';
        echo "    Cloudinary error: {$err}\n";
        $stats['errors']++;
        continue;
    }

    $stats['deleted_cloudinary']++;
    clearProofPhotoUrl($pdo, $proofId);
    $stats['cleared_db']++;
}

echo "Done. candidates={$stats['candidates']} deleted={$stats['deleted_cloudinary']} "
    . "db_cleared={$stats['cleared_db']} skipped={$stats['skipped']} errors={$stats['errors']}\n";

exit($stats['errors'] > 0 ? 1 : 0);

// --- helpers ---

function parseCliOptions(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $out['dry-run'] = true;
            continue;
        }
        if (preg_match('/^--keep=(\d+)$/', $arg, $m)) {
            $out['keep'] = (int) $m[1];
            continue;
        }
        if (preg_match('/^--minutes=(\d+)$/', $arg, $m)) {
            $out['minutes'] = (int) $m[1];
            continue;
        }
    }
    return $out;
}

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

/** @return int[] */
function fetchKeepOrderIds(PDO $pdo, int $limit): array
{
    $sql = "
        SELECT order_id
        FROM order_delivery_proofs
        WHERE photo_url LIKE '%res.cloudinary.com%'
          AND photo_url LIKE '%shipper-delivery-proofs/order_%'
          AND proof_type IN ('pickup_photo', 'delivery_photo', 'arrival_photo')
          AND photo_url IS NOT NULL
          AND TRIM(photo_url) <> ''
        GROUP BY order_id
        ORDER BY MAX(captured_at) DESC
        LIMIT " . (int) $limit;

    $ids = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ids[] = (int) $row['order_id'];
    }
    return $ids;
}

/**
 * @param int[] $keepOrderIds
 * @return array<int, array<string, mixed>>
 */
function fetchDeletionCandidates(PDO $pdo, array $keepOrderIds, int $minAgeMinutes): array
{
    $params = [$minAgeMinutes];

    $sql = "
        SELECT proof_id, order_id, proof_type, photo_url, captured_at
        FROM order_delivery_proofs
        WHERE photo_url LIKE '%res.cloudinary.com%'
          AND photo_url LIKE '%shipper-delivery-proofs/order_%'
          AND proof_type IN ('pickup_photo', 'delivery_photo', 'arrival_photo')
          AND photo_url IS NOT NULL
          AND TRIM(photo_url) <> ''
          AND captured_at < (NOW() - INTERVAL ? MINUTE)
    ";

    if (!empty($keepOrderIds)) {
        $sql .= ' AND order_id NOT IN (' . implode(',', array_fill(0, count($keepOrderIds), '?')) . ')';
        $params = array_merge($params, $keepOrderIds);
    }

    $sql .= ' ORDER BY captured_at ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function shipperProofPublicIdFromUrl(string $url): ?string
{
    if (!str_contains($url, 'res.cloudinary.com')) {
        return null;
    }
    if (!str_contains($url, SHIPPER_PROOF_FOLDER_PREFIX)) {
        return null;
    }

    if (!preg_match('#' . preg_quote(SHIPPER_PROOF_FOLDER_PREFIX, '#') . '\d+/[^?\s#]+#', $url, $m)) {
        return null;
    }

    $segment = $m[0];
    $publicId = preg_replace('/\.[a-z0-9]+$/i', '', $segment);
    if ($publicId === null || $publicId === '') {
        return null;
    }

    if (!preg_match('#^shipper-delivery-proofs/order_\d+/.+#', $publicId)) {
        return null;
    }

    return $publicId;
}

function clearProofPhotoUrl(PDO $pdo, int $proofId): void
{
    $stmt = $pdo->prepare('UPDATE order_delivery_proofs SET photo_url = NULL WHERE proof_id = ?');
    $stmt->execute([$proofId]);
}
