<?php
declare(strict_types=1);

// Sample data seeding for ShopSwift database
if (!isset($pdo)) {
    throw new Exception('Database connection not available');
}

echo "Inserting sample data...\n";

// 1. Insert roles
$roles = [
    ['admin'],
    ['customer'],
    ['shipper'],
    ['staff']
];

$roleStmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES (?)");
foreach ($roles as $role) {
    $roleStmt->execute($role);
}

// 2. Insert accounts
$accounts = [
    ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'local'],
    ['customer1', password_hash('password123', PASSWORD_DEFAULT), 'local'],
    ['customer2', password_hash('password123', PASSWORD_DEFAULT), 'local'],
    ['shipper1', password_hash('password123', PASSWORD_DEFAULT), 'local'],
    ['shipper2', password_hash('password123', PASSWORD_DEFAULT), 'local'],
];

$accountStmt = $pdo->prepare("
    INSERT INTO accounts (account_name, password, account_type, created_at) 
    VALUES (?, ?, ?, NOW())
");

foreach ($accounts as $account) {
    $accountStmt->execute($account);
}

// 3. Insert users
$users = [
    [1, 'Admin', 'User', 'admin@shopswift.com', '+84987654321', 'other', null],
    [2, 'Nguyen Van', 'Nam', 'nam.nguyen@gmail.com', '+84912345678', 'male', '1990-05-15'],
    [3, 'Tran Thi', 'Lan', 'lan.tran@gmail.com', '+84923456789', 'female', '1992-08-20'],
    [4, 'Le Van', 'Shipper', 'shipper1@shopswift.com', '+84934567890', 'male', '1988-12-10'],
    [5, 'Pham Thi', 'Delivery', 'shipper2@shopswift.com', '+84945678901', 'female', '1991-03-25'],
];

$userStmt = $pdo->prepare("
    INSERT INTO users (account_id, first_name, last_name, email, phone, gender, birthdate) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

foreach ($users as $user) {
    $userStmt->execute($user);
}

// 4. Assign roles to users
$userRoles = [
    [1, 1], // Admin user -> admin role
    [2, 2], // Customer 1 -> customer role
    [3, 2], // Customer 2 -> customer role
    [4, 3], // Shipper 1 -> shipper role
    [5, 3], // Shipper 2 -> shipper role
];

$userRoleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
foreach ($userRoles as $userRole) {
    $userRoleStmt->execute($userRole);
}

// 5. Insert customers
$customers = [
    [2, 0, 0], // customer1
    [3, 150, 3], // customer2 with some loyalty points and orders
];

$customerStmt = $pdo->prepare("
    INSERT INTO customers (user_id, loyalty_points, total_orders) 
    VALUES (?, ?, ?)
");

foreach ($customers as $customer) {
    $customerStmt->execute($customer);
}

// 6. Insert shippers
$shippers = [
    [4, 'Honda Wave RSX', 4.8, 95.5, 150],
    [5, 'Yamaha Sirius', 4.6, 92.3, 89],
];

$shipperStmt = $pdo->prepare("
    INSERT INTO shippers (user_id, vehicle_info, rating, on_time_delivery_pct, total_delivered) 
    VALUES (?, ?, ?, ?, ?)
");

foreach ($shippers as $shipper) {
    $shipperStmt->execute($shipper);
}

// 7. Insert addresses
$addresses = [
    [2, 'Nguyen Van Nam', '+84912345678', '123 Nguyen Trai, District 1', 'Phuong Ben Nghe', 'District 1', 'Ho Chi Minh City', '70000', 1],
    [3, 'Tran Thi Lan', '+84923456789', '456 Le Loi, Ba Dinh', 'Phuong Ngoc Khanh', 'Ba Dinh', 'Hanoi', '10000', 1],
    [2, 'Nguyen Van Nam', '+84912345678', '789 Office Building, District 3', 'Phuong 1', 'District 3', 'Ho Chi Minh City', '70000', 0],
];

$addressStmt = $pdo->prepare("
    INSERT INTO addresses (user_id, receiver_name, phone, address_line, ward, district, city, postal_code, is_default) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($addresses as $address) {
    $addressStmt->execute($address);
}

// 8. Insert categories
$categories = [
    ['Electronics', 'electronics', null, '/images/categories/electronics.jpg'],
    ['Smartphones', 'smartphones', 1, '/images/categories/smartphones.jpg'],
    ['Laptops', 'laptops', 1, '/images/categories/laptops.jpg'],
    ['Fashion', 'fashion', null, '/images/categories/fashion.jpg'],
    ['Men Fashion', 'men-fashion', 4, '/images/categories/men.jpg'],
    ['Women Fashion', 'women-fashion', 4, '/images/categories/women.jpg'],
    ['Home & Living', 'home-living', null, '/images/categories/home.jpg'],
    ['Books', 'books', null, '/images/categories/books.jpg'],
    ['Sports', 'sports', null, '/images/categories/sports.jpg'],
    ['Beauty', 'beauty', null, '/images/categories/beauty.jpg'],
];

$categoryStmt = $pdo->prepare("
    INSERT INTO categories (name, slug, parent_id, image_url) 
    VALUES (?, ?, ?, ?)
");

foreach ($categories as $category) {
    $categoryStmt->execute($category);
}

// 9. Insert products
$products = [
    ['iPhone 15 Pro Max 256GB', 'iphone-15-pro-max-256gb', 'Latest iPhone with advanced camera system and titanium design', 'IP15PM256', 29990000, 27990000, 2, 50, 10],
    ['Samsung Galaxy S24 Ultra 512GB', 'samsung-galaxy-s24-ultra-512gb', 'Samsung flagship smartphone with S Pen and AI features', 'SGS24U512', 26990000, 24990000, 2, 30, 5],
    ['MacBook Pro 16-inch M3 Pro', 'macbook-pro-16-m3-pro', 'Professional laptop with M3 Pro chip for demanding workflows', 'MBP16M3P', 59990000, null, 3, 15, 3],
    ['Dell XPS 13 Plus', 'dell-xps-13-plus', 'Ultra-thin laptop with stunning display and performance', 'DXPS13P', 35990000, 32990000, 3, 25, 5],
    ['Nike Air Max 270 React', 'nike-air-max-270-react', 'Comfortable running shoes with React foam technology', 'NAM270R', 3290000, 2890000, 9, 100, 20],
    ['Adidas Ultraboost 22', 'adidas-ultraboost-22', 'Premium running shoes with Boost midsole', 'AUB22', 4590000, 3990000, 9, 80, 15],
    ['Uniqlo Airism T-Shirt', 'uniqlo-airism-tshirt', 'Moisture-wicking and quick-dry basic t-shirt', 'UQATS', 299000, 199000, 5, 200, 50],
    ['Zara Slim Fit Jeans', 'zara-slim-fit-jeans', 'Modern slim fit jeans with stretch comfort', 'ZSFJ', 899000, 699000, 5, 150, 30],
];

$productStmt = $pdo->prepare("
    INSERT INTO products (name, slug, description, sku, price, sale_price, category_id, stock_quantity, min_stock_level) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($products as $product) {
    $productStmt->execute($product);
}

echo "Sample data inserted successfully!\n";
echo "\n=== LOGIN CREDENTIALS ===\n";
echo "Admin: admin / admin123\n";
echo "Customer 1: customer1 / password123 (email: nam.nguyen@gmail.com)\n";
echo "Customer 2: customer2 / password123 (email: lan.tran@gmail.com)\n";
echo "Shipper 1: shipper1 / password123\n";
echo "Shipper 2: shipper2 / password123\n";
echo "==========================\n";
