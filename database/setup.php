<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\{Container, Database};

// Load environment
$container = new Container(__DIR__ . '/../app/config');
$container->bootEnv(__DIR__ . '/../');

try {
    $database = $container->database();
    $pdo = $database->getConnection();
    
    echo "Connecting to existing ShopSwift database...\n";
    
    // Since the database and tables are already created in XAMPP,
    // we'll just verify the connection and check if we need to seed data
    
    // Check if we have data in the database
    $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'ShopSwift'");
    $result = $stmt->fetch();
    
    if ($result['table_count'] > 0) {
        echo "Database tables found: " . $result['table_count'] . " tables\n";
        
        // Check if we have sample data
        $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
        $userResult = $stmt->fetch();
        
        if ($userResult['user_count'] == 0) {
            echo "No sample data found. Database is ready for seeding.\n";
        } else {
            echo "Sample data already exists: " . $userResult['user_count'] . " users\n";
        }
    } else {
        echo "Warning: No tables found. Please ensure the schema.sql has been executed in XAMPP MySQL.\n";
    }
    
    echo "Database connection verified successfully!\n";
    
    // Insert sample data if needed
    if (isset($argv[1]) && $argv[1] === '--seed') {
        // Check if we already have sample data
        $stmt = $pdo->query("SELECT COUNT(*) as account_count FROM accounts");
        $accountResult = $stmt->fetch();
        
        if ($accountResult['account_count'] == 0) {
            echo "Seeding database with sample data...\n";
            require __DIR__ . '/seeds/sample_data.php';
            echo "Database seeded successfully!\n";
        } else {
            echo "Sample data already exists. Skipping seeding.\n";
        }
    }
    
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check:\n";
    echo "1. XAMPP MySQL service is running\n";
    echo "2. Database 'ShopSwift' exists\n";
    echo "3. Database credentials in .env file are correct\n";
    exit(1);
}
