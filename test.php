<?php
try {
    $pdo = new PDO(
        "mysql:host=mysql;port=3306;dbname=ecommerce_db",
        "root",
        "123456"
    );
    echo "✅ Connected OK";
} catch (Exception $e) {
    echo "❌ " . $e->getMessage();
}