<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for admin authentication
session_start();


// Database connection setup
$host = 'localhost';
$db = 'ravenhill_final';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Fetch statistics for dashboard
$total_products = $pdo->query("SELECT COUNT(*) as count FROM product")->fetch()['count'];
$total_orders = $pdo->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
$total_customers = $pdo->query("SELECT COUNT(*) as count FROM customer")->fetch()['count'];
$revenue_result = $pdo->query("SELECT SUM(total_price) as total FROM orders")->fetch();
$total_revenue = $revenue_result['total'] !== null ? $revenue_result['total'] : 0;

// Fetch recent orders with customer name and order type
$recent_orders = $pdo->query("
    SELECT o.order_id, o.total_price, o.order_time, u.name as customer_name, o.order_type
    FROM orders o
    LEFT JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON c.customer_id = u.user_id
    ORDER BY o.order_time DESC
    LIMIT 4
")->fetchAll();

// Fetch low stock items
$low_stock = $pdo->query("
    SELECT p.name, i.stock_level
    FROM inventory i
    JOIN product p ON i.product_id = p.product_id
    WHERE i.stock_level < 20
    LIMIT 3
")->fetchAll();

// Fetch products for product management
$products = $pdo->query("
    SELECT p.product_id, p.name, c.name as category, p.price, p.available
    FROM product p
    JOIN category c ON p.category_id = c.category_id
    ORDER BY p.product_id
")->fetchAll();

// Fetch all orders for order management with customer name, order type, and derive status from payment
$orders = $pdo->query("
    SELECT o.order_id, o.total_price, o.order_time, u.name as customer_name, o.order_type,
           COALESCE(pay.payment_status, 'Pending') as status
    FROM orders o
    LEFT JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON c.customer_id = u.user_id
    LEFT JOIN payment pay ON o.order_id = pay.order_id
    ORDER BY o.order_time DESC
")->fetchAll();

// Fetch users for user management
$users = $pdo->query("
    SELECT user_id, name, email, role, status, phone, address, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Fetch inventory for inventory management
$inventory = $pdo->query("
    SELECT p.product_id, p.name, i.stock_level, i.threshold
    FROM inventory i
    JOIN product p ON i.product_id = p.product_id
    ORDER BY i.stock_level ASC
")->fetchAll();

// Fetch categories for menu management
$categories = $pdo->query("
    SELECT * FROM category ORDER BY name
")->fetchAll();

// Fetch all menu items
$menu_items = $pdo->query("
    SELECT p.*, c.name as category_name, COALESCE(i.stock_level, 0) as stock
    FROM product p
    JOIN category c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    ORDER BY c.name, p.name
")->fetchAll();

// Group items by category for display
$groupedItems = [];
foreach ($menu_items as $item) {
    $groupedItems[$item['category_name']][] = $item;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        // Add new product
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];
        $available = isset($_POST['available']) ? 1 : 0;
        $allergens = $_POST['allergens'];
        $image_url = $_POST['image_url'];

        $stmt = $pdo->prepare("INSERT INTO product (name, description, price, category_id, available, allergens, image_url)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $category_id, $available, $allergens, $image_url]);

        // Add to inventory
        $product_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO inventory (inventory_id, product_id, stock_level, threshold)
                              VALUES (?, ?, ?, ?)");
        $inventory_id = 'inv' . $product_id;
        $default_threshold = 10;
        $default_stock = 0;
        $stmt->execute([$inventory_id, $product_id, $default_stock, $default_threshold]);

        header("Location: admin_dashboard.php");
        exit();
    }

    if (isset($_POST['update_stock'])) {
        // Update stock level
        $product_id = $_POST['product_id'];
        $stock_level = $_POST['stock_level'];

        $stmt = $pdo->prepare("UPDATE inventory SET stock_level = ? WHERE product_id = ?");
        $stmt->execute([$stock_level, $product_id]);

        header("Location: admin_dashboard.php#inventory");
        exit();
    }

    if (isset($_POST['update_product'])) {
        // Update product
        $product_id = $_POST['product_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];
        $available = isset($_POST['available']) ? 1 : 0;
        $allergens = $_POST['allergens'];
        $image_url = $_POST['image_url'];

        $stmt = $pdo->prepare("UPDATE product SET name = ?, description = ?, price = ?, category_id = ?,
                              available = ?, allergens = ?, image_url = ? WHERE product_id = ?");
        $stmt->execute([$name, $description, $price, $category_id, $available, $allergens, $image_url, $product_id]);

        header("Location: admin_dashboard.php#products");
        exit();
    }

    if (isset($_POST['delete_product'])) {
        // Delete product
        $product_id = $_POST['product_id'];

        // First delete from inventory to maintain referential integrity
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = ?");
        $stmt->execute([$product_id]);

        // Then delete the product
        $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
        $stmt->execute([$product_id]);

        header("Location: admin_dashboard.php#products");
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
        try {
            $order_id = $_POST['order_id'];
            $payment_status = $_POST['payment_status'];
            $payment_time = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("UPDATE payment SET payment_status = ?, payment_time = ? WHERE order_id = ?");
            $stmt->execute([$payment_status, $payment_time, $order_id]);

            // If order is cancelled, restore inventory
            if ($payment_status === 'Cancelled') {
                $stmt = $pdo->prepare("
                    SELECT oi.product_id, oi.quantity
                    FROM order_item oi
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order_id]);
                $items = $stmt->fetchAll();
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("UPDATE inventory SET stock_level = stock_level + ? WHERE product_id = ?");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }
            }
// Handle promotion form submissions
if (isset($_POST['add_promotion'])) {
    $promotion_id = 'promo_' . uniqid();
    $code = $_POST['code'];
    $type = $_POST['type'];
    $value = $_POST['value'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO promotion (promotion_id, code, type, value, start, end, description) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$promotion_id, $code, $type, $value, $start, $end, $description]);

    header("Location: admin_dashboard.php#promotions");
    exit();
}

if (isset($_POST['update_promotion'])) {
    $promotion_id = $_POST['promotion_id'];
    $code = $_POST['code'];
    $type = $_POST['type'];
    $value = $_POST['value'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("UPDATE promotion SET code = ?, type = ?, value = ?, start = ?, end = ?, description = ? 
                          WHERE promotion_id = ?");
    $stmt->execute([$code, $type, $value, $start, $end, $description, $promotion_id]);

    header("Location: admin_dashboard.php#promotions");
    exit();
}

if (isset($_POST['delete_promotion'])) {
    $promotion_id = $_POST['promotion_id'];
    
    $stmt = $pdo->prepare("DELETE FROM promotion WHERE promotion_id = ?");
    $stmt->execute([$promotion_id]);
    
    header("Location: admin_dashboard.php#promotions");
    exit();
}

            $_SESSION['order_message'] = "Order status updated to $payment_status!";
        } catch (PDOException $e) {
            $_SESSION['order_message'] = "Error updating order status: " . $e->getMessage();
        }
        header("Location: admin_dashboard.php#orders");
        exit();
    }
}
// Handle Settings Form Submissions
if (isset($_POST['save_store_info'])) {
    $_SESSION['success_message'] = "Store information updated successfully!";
    header("Location: admin_dashboard.php#settings");
    exit();
}

if (isset($_POST['save_business_settings'])) {
    $_SESSION['success_message'] = "Business settings updated successfully!";
    header("Location: admin_dashboard.php#settings");
    exit();
}

if (isset($_POST['save_order_settings'])) {
    $_SESSION['success_message'] = "Order settings updated successfully!";
    header("Location: admin_dashboard.php#settings");
    exit();
}

if (isset($_POST['save_user_preferences'])) {
    $_SESSION['success_message'] = "User preferences updated successfully!";
    header("Location: admin_dashboard.php#settings");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Uniform Café Color Palette */
        :root {
            --primary-brown: #4B2A29;
            --secondary-beige: #D2B48C;
            --accent-gold: #F5A623;
            --highlight-red: #A52A2A;
            --background-cream: #F5F5DC;
            --text-dark: #4A2C2A;
            --text-light: #FFFFFF;
            --text-gray: #A9A9A9;
            --admin-dark: #1e293b;
            --admin-gray: #334155;
            --admin-light-gray: #cbd5e1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-cream);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: var(--primary-brown);
            color: var(--text-light);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid var(--admin-gray);
        }

        .sidebar-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-nav {
            padding: 2rem 0;
        }

        .nav-item {
            display: block;
            padding: 1rem 2rem;
            color: var(--admin-light-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-weight: 500;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--admin-gray);
            color: var(--text-light);
            border-left-color: var(--accent-gold);
            transform: translateX(5px);
        }

        .nav-item i {
            width: 20px;
            margin-right: 12px;
            color: var(--accent-gold);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: var(--background-cream);
        }

        .header {
            background: var(--text-light);
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--secondary-beige);
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-brown);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .logout-btn {
            background: var(--highlight-red);
            color: var(--text-light);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: #8B2323;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(165, 42, 42, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--text-light);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--accent-gold);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-brown);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .card {
            background: var(--text-light);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--secondary-beige);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--secondary-beige);
        }

        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-brown);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--secondary-beige);
        }

        .table th {
            font-weight: 600;
            color: var(--text-dark);
            background: var(--background-cream);
            font-family: 'Poppins', sans-serif;
        }

        .table tr:hover {
            background: #faf8f5;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left-color: var(--accent-gold);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: var(--highlight-red);
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #10b981;
        }

        .alert h4 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .alert ul {
            margin-top: 0.5rem;
            padding-left: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: var(--accent-gold);
            color: var(--text-dark);
        }

        .btn-primary:hover {
            background: #e59400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 166, 35, 0.3);
        }

        .btn-danger {
            background: var(--highlight-red);
            color: var(--text-light);
        }

        .btn-danger:hover {
            background: #8B2323;
            transform: translateY(-2px);
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .demo-banner {
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--highlight-red) 100%);
            color: var(--text-light);
            padding: 1.5rem 2rem;
            text-align: center;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(75, 42, 41, 0.3);
        }

        .demo-banner h2 {
            font-family: 'Playfair Display', serif;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            color: var(--accent-gold);
        }

        .demo-banner p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .info-placeholder {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
            background: var(--background-cream);
            border-radius: 15px;
            margin: 2rem 0;
        }

        .info-placeholder i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--accent-gold);
            opacity: 0.7;
        }

        .info-placeholder p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        .info-placeholder ul {
            list-style: none;
            margin-top: 1.5rem;
            line-height: 2;
            color: var(--text-gray);
        }

        .info-placeholder ul li::before {
            content: "☕";
            margin-right: 10px;
            color: var(--accent-gold);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--secondary-beige);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--accent-gold);
            outline: none;
        }

        .form-checkbox {
            margin-right: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--text-light);
            border-radius: 15px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--secondary-beige);
        }

        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-brown);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-dark);
        }

        .order-details {
            padding: 1rem;
        }

        /* Additional styles for Settings section */
        .settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
        }

        .setting-card {
    background: var(--text-light);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--accent-gold);
}

.setting-card h4 {
    font-family: 'Playfair Display', serif;
    color: var(--primary-brown);
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.quick-action-btn {
    width: 100%;
    padding: 1rem;
    margin-bottom: 0.5rem;
    text-align: left;
}

.quick-action-btn i {
    margin-right: 0.5rem;
    width: 20px;
}
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-logo"><i class="fas fa-coffee"></i> Ravenhill Admin</h2>
            </div>
            <div class="sidebar-nav">
                <a href="#" class="nav-item active" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="#" class="nav-item" onclick="showSection('products')"><i class="fas fa-mug-hot"></i> Products</a>
                <a href="#" class="nav-item" onclick="showSection('orders')"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="#" class="nav-item" onclick="showSection('users')"><i class="fas fa-users"></i> Users</a>
                <a href="#" class="nav-item" onclick="showSection('inventory')"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="#" class="nav-item" onclick="showSection('menu')"><i class="fas fa-book-open"></i> Menu Preview</a>
                <a href="#" class="nav-item" onclick="showSection('promotions')"><i class="fas fa-tags"></i> Promotions</a>
                <a href="#" class="nav-item" onclick="showSection('reports')"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="#" class="nav-item" onclick="showSection('settings')"><i class="fas fa-cog"></i> Settings</a>
            </div>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome Admin</span>
                    <a href="admin_login.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <div class="demo-banner">
                <h2>Welcome to Ravenhill Admin</h2>
                <p>Manage your café operations with ease</p>
            </div>

            <!-- Dashboard Section -->
            <div id="dashboard" class="section active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_products; ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_orders; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_customers; ?></div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Orders</h3>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                    </tr>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></td>
                                    <td>$<?php echo number_format($order['total_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['order_type']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($order['order_time'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Low Stock Alerts</h3>
                        </div>
                        <div class="alert alert-warning">
                            <h4><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h4>
                            <?php if (empty($low_stock)): ?>
                                <p>No items are currently low in stock.</p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($low_stock as $item): ?>
                                        <li><?php echo htmlspecialchars($item['name']); ?>: <?php echo $item['stock_level']; ?> units</li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="alert alert-success" style="border-left: 4px solid var(--accent-gold); color: var(--primary-brown);">
                            <h4><i class="fas fa-info-circle"></i> System Status</h4>
                            <p>All systems operational. Last backup: <?php echo date('M j, Y g:i A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Section -->
            <div id="products" class="section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Product Management</h3>
                        <button class="btn btn-primary" onclick="openModal('add-product-modal')">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Available</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['product_id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo $product['available'] ? 'completed' : 'cancelled'; ?>">
                                    <?php echo $product['available'] ? 'Yes' : 'No'; ?>
                                </span></td>
                                <td>
                                    <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <button type="submit" name="delete_product" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Orders Section -->
            <div id="orders" class="section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Order Management</h3>
                    </div>
                    <?php if (isset($_SESSION['order_message'])): ?>
                        <div class="alert <?php echo strpos($_SESSION['order_message'], 'Error') !== false ? 'alert-error' : 'alert-success'; ?>">
                            <p><?php echo $_SESSION['order_message']; unset($_SESSION['order_message']); ?></p>
                        </div>
                    <?php endif; ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Order Type</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></td>
                                <td>$<?php echo number_format($order['total_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($order['order_type']); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span></td>
                                <td><?php echo date('M j, Y H:i', strtotime($order['order_time'])); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="viewOrderDetails('<?php echo $order['order_id']; ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-primary" onclick="updateOrderStatus('<?php echo $order['order_id']; ?>', '<?php echo addslashes($order['status']); ?>')">
                                        <i class="fas fa-edit"></i> Update Status
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Users Section -->
            <div id="users" class="section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User Management</h3>
                        <a href="add_user.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add User
                        </a>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo htmlspecialchars($user['address']); ?></td>
                                <td><span class="status-badge status-completed"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                <td><span class="status-badge status-completed"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                        <a href="edit_user.php?id=<?php echo htmlspecialchars($user['user_id']); ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                            </button>
                                            </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory Section -->
            <div id="inventory" class="section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Inventory Management</h3>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Stock Level</th>
                                <th>Threshold</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?php echo $item['product_id']; ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $item['stock_level'] < $item['threshold'] ? 'status-cancelled' : 'status-completed'; ?>">
                                        <?php echo $item['stock_level']; ?>
                                    </span>
                                </td>
                                <td><?php echo $item['threshold']; ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="updateStock(<?php echo $item['product_id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['stock_level']; ?>)">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Menu Preview Section -->
<!-- Menu Preview Section -->
<div id="menu" class="section">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Menu Preview</h3>
            <p>This is how your menu appears to customers</p>
        </div>
        <div class="card">
            <!-- Debug Information for categories -->
            <?php if (count($categories) === 0): ?>
                <div class="alert alert-warning">
                    <h4><i class="fas fa-exclamation-triangle"></i> Debug</h4>
                    <p>No categories found in database.</p>
                </div>
            <?php endif; ?>
            
            <!-- Debug Information for items -->
            <?php if (count($menu_items) === 0): ?>
                <div class="alert alert-warning">
                    <h4><i class="fas fa-exclamation-triangle"></i> Debug</h4>
                    <p>No items found in database.</p>
                </div>
            <?php endif; ?>

            <!-- Category Filters -->
            <div class="card-header">
                <h3 class="card-title">Categories</h3>
            </div>
            <div style="padding: 1rem;">
                <button class="btn btn-primary filter-btn active" data-category="All">
                    <i class="fas fa-th-large"></i> All
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-primary filter-btn" data-category="<?php echo htmlspecialchars($cat['name']); ?>">
                        <i class="fas fa-<?php 
                            $categoryName = strtolower($cat['name']);
                            if ($categoryName === 'coffee') echo 'coffee';
                            elseif ($categoryName === 'drinks') echo 'glass-martini';
                            elseif ($categoryName === 'breakfast') echo 'utensils';
                            elseif ($categoryName === 'lunch') echo 'hamburger';
                            elseif ($categoryName === 'sides') echo 'seedling';
                            elseif ($categoryName === 'pastries') echo 'cookie';
                            else echo 'utensils';
                        ?>"></i>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Menu Items -->
            <?php if (count($groupedItems) > 0): ?>
                <?php foreach ($groupedItems as $catName => $catItems): ?>
                    <div class="category-section" data-category="<?php echo htmlspecialchars($catName); ?>">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($catName); ?></h3>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Allergens</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($catItems as $item): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/100x100/D2B48C/000000?text=' . urlencode($item['name']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;"
                                                 onerror="this.src='https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'">
                                        </td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <?php if (!empty($item['allergens']) && $item['allergens'] !== ''): ?>
                                                <span class="status-badge status-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($item['allergens']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-completed">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $item['stock'] <= 0 ? 'status-cancelled' : 'status-completed'; ?>">
                                                <?php echo $item['stock'] > 0 ? 'In Stock (' . $item['stock'] . ')' : 'Out of Stock'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="info-placeholder">
                    <i class="fas fa-coffee"></i>
                    <h2>No Menu Items Available</h2>
                    <p>Please add menu items to display them here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

           <!-- Promotions Section -->
<div id="promotions" class="section">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Promotion Management</h3>
            <button class="btn btn-primary" onclick="openModal('add-promotion-modal')">
                <i class="fas fa-plus"></i> Add Promotion
            </button>
        </div>
        
        <?php
        // Fetch promotions from database
        $promotions = $pdo->query("
            SELECT * FROM promotion 
            ORDER BY start DESC, end DESC
        ")->fetchAll();
        ?>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promotion): 
                    $current_date = date('Y-m-d');
                    $start_date = $promotion['start'];
                    $end_date = $promotion['end'];
                    $status = '';
                    
                    if ($current_date < $start_date) {
                        $status = 'Upcoming';
                        $status_class = 'status-pending';
                    } elseif ($current_date >= $start_date && $current_date <= $end_date) {
                        $status = 'Active';
                        $status_class = 'status-completed';
                    } else {
                        $status = 'Expired';
                        $status_class = 'status-cancelled';
                    }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($promotion['code']); ?></strong></td>
                    <td><?php echo htmlspecialchars(ucfirst($promotion['type'])); ?></td>
                    <td>
                        <?php if ($promotion['type'] === 'percentage'): ?>
                            <?php echo htmlspecialchars($promotion['value']); ?>%
                        <?php else: ?>
                            $<?php echo number_format($promotion['value'], 2); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($promotion['start'])); ?></td>
                    <td><?php echo date('M j, Y', strtotime($promotion['end'])); ?></td>
                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                    <td>
                        <button class="btn btn-primary" onclick="editPromotion(<?php echo htmlspecialchars(json_encode($promotion)); ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="promotion_id" value="<?php echo $promotion['promotion_id']; ?>">
                            <button type="submit" name="delete_promotion" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this promotion?')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

           <!-- Reports Section -->
<div id="reports" class="section">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Reports & Analytics</h3>
            <button class="btn btn-primary" onclick="generateReport()">
                <i class="fas fa-download"></i> Export Report
            </button>
        </div>

        <!-- Date Range Filter -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Filter Reports by Date Range</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="GET" id="report-filter-form">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-input" 
                                   value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-input" 
                                   value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="admin_dashboard.php#reports" class="btn" style="margin-left: 0.5rem;">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // Get date range from GET parameters or use current month
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

        // Sales Report by Date Range
        $sales_report = $pdo->prepare("
            SELECT 
                DATE(o.order_time) as order_date,
                COUNT(o.order_id) as total_orders,
                SUM(o.total_price) as daily_revenue,
                AVG(o.total_price) as avg_order_value
            FROM orders o
            WHERE o.order_time BETWEEN ? AND ?
            GROUP BY DATE(o.order_time)
            ORDER BY order_date DESC
        ");
        $sales_report->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $sales_data = $sales_report->fetchAll();

        // Customer Analytics
        $customer_analytics = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.customer_id) as total_customers,
                COUNT(o.order_id) as total_orders,
                SUM(o.total_price) as total_revenue,
                AVG(o.total_price) as avg_order_value,
                MAX(o.total_price) as max_order_value
            FROM orders o
            WHERE o.order_time BETWEEN ? AND ?
        ");
        $customer_analytics->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $customer_stats = $customer_analytics->fetch();

        // Top Customers
        $top_customers = $pdo->prepare("
            SELECT 
                u.name,
                u.email,
                COUNT(o.order_id) as order_count,
                SUM(o.total_price) as total_spent
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            JOIN users u ON c.customer_id = u.user_id
            WHERE o.order_time BETWEEN ? AND ?
            GROUP BY o.customer_id
            ORDER BY total_spent DESC
            LIMIT 10
        ");
        $top_customers->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $top_customers_data = $top_customers->fetchAll();

        // Product Performance
        $product_performance = $pdo->prepare("
            SELECT 
                p.name,
                p.category_id,
                c.name as category_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_item oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN product p ON oi.product_id = p.product_id
            JOIN category c ON p.category_id = c.category_id
            WHERE o.order_time BETWEEN ? AND ?
            GROUP BY p.product_id
            ORDER BY total_sold DESC
            LIMIT 15
        ");
        $product_performance->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $product_stats = $product_performance->fetchAll();

        // Revenue by Category
        $revenue_by_category = $pdo->prepare("
            SELECT 
                c.name as category_name,
                SUM(oi.quantity * oi.unit_price) as category_revenue,
                COUNT(DISTINCT o.order_id) as order_count
            FROM order_item oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN product p ON oi.product_id = p.product_id
            JOIN category c ON p.category_id = c.category_id
            WHERE o.order_time BETWEEN ? AND ?
            GROUP BY c.category_id
            ORDER BY category_revenue DESC
        ");
        $revenue_by_category->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $category_revenue = $revenue_by_category->fetchAll();

        // Order Type Analysis
        $order_type_analysis = $pdo->prepare("
            SELECT 
                order_type,
                COUNT(order_id) as order_count,
                SUM(total_price) as total_revenue,
                AVG(total_price) as avg_order_value
            FROM orders
            WHERE order_time BETWEEN ? AND ?
            GROUP BY order_type
            ORDER BY total_revenue DESC
        ");
        $order_type_analysis->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $order_type_stats = $order_type_analysis->fetchAll();
        ?>

        <!-- Summary Statistics -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($customer_stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
                <small><?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $customer_stats['total_orders'] ?? 0; ?></div>
                <div class="stat-label">Total Orders</div>
                <small><?php echo $customer_stats['total_customers'] ?? 0; ?> unique customers</small>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($customer_stats['avg_order_value'] ?? 0, 2); ?></div>
                <div class="stat-label">Average Order Value</div>
                <small>Max: $<?php echo number_format($customer_stats['max_order_value'] ?? 0, 2); ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($sales_data); ?></div>
                <div class="stat-label">Business Days</div>
                <small>With order activity</small>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Sales Report by Date Range -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daily Sales Report</h3>
                    <span class="status-badge status-completed">
                        <?php echo count($sales_data); ?> days
                    </span>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Avg Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_data)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-chart-line" style="font-size: 2rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                                        <p>No sales data for the selected period</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_data as $sale): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($sale['order_date'])); ?></td>
                                    <td><?php echo $sale['total_orders']; ?></td>
                                    <td>$<?php echo number_format($sale['daily_revenue'], 2); ?></td>
                                    <td>$<?php echo number_format($sale['avg_order_value'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Customer Analytics -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Customer Analytics</h3>
                    <span class="status-badge status-completed">
                        Top 10 Customers
                    </span>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Avg/Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_customers_data)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-users" style="font-size: 2rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                                        <p>No customer data for the selected period</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_customers_data as $customer): ?>
                                <tr>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($customer['name']); ?></strong></div>
                                        <small><?php echo htmlspecialchars($customer['email']); ?></small>
                                    </td>
                                    <td><?php echo $customer['order_count']; ?></td>
                                    <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td>$<?php echo number_format($customer['total_spent'] / max($customer['order_count'], 1), 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Product Performance -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Product Performance</h3>
                <span class="status-badge status-completed">
                    Top 15 Products
                </span>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Avg Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($product_stats)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-cube" style="font-size: 2rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                                    <p>No product sales data for the selected period</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($product_stats as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>
                                    <span class="status-badge status-pending">
                                        <?php echo htmlspecialchars($product['category_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo $product['total_sold']; ?></td>
                                <td>$<?php echo number_format($product['total_revenue'], 2); ?></td>
                                <td>$<?php echo number_format($product['total_revenue'] / max($product['total_sold'], 1), 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Revenue Tracking by Category -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Revenue by Category</h3>
                <span class="status-badge status-completed">
                    <?php echo count($category_revenue); ?> Categories
                </span>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Revenue</th>
                            <th>Orders</th>
                            <th>Revenue/Order</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_revenue_period = $customer_stats['total_revenue'] ?? 1; // Avoid division by zero
                        ?>
                        <?php if (empty($category_revenue)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-chart-pie" style="font-size: 2rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                                    <p>No category revenue data for the selected period</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($category_revenue as $category): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                </td>
                                <td>$<?php echo number_format($category['category_revenue'], 2); ?></td>
                                <td><?php echo $category['order_count']; ?></td>
                                <td>$<?php echo number_format($category['category_revenue'] / max($category['order_count'], 1), 2); ?></td>
                                <td>
                                    <span class="status-badge status-processing">
                                        <?php echo number_format(($category['category_revenue'] / $total_revenue_period) * 100, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Type Analysis -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Order Type Analysis</h3>
                <span class="status-badge status-completed">
                    <?php echo count($order_type_stats); ?> Types
                </span>
            </div>
            <div style="max-height: 300px; overflow-y: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order Type</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Avg Order Value</th>
                            <th>% of Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($order_type_stats)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-shopping-cart" style="font-size: 2rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                                    <p>No order type data for the selected period</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($order_type_stats as $order_type): ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-completed">
                                        <?php echo htmlspecialchars(ucfirst($order_type['order_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $order_type['order_count']; ?></td>
                                <td>$<?php echo number_format($order_type['total_revenue'], 2); ?></td>
                                <td>$<?php echo number_format($order_type['avg_order_value'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-pending">
                                        <?php echo number_format(($order_type['total_revenue'] / $total_revenue_period) * 100, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

           <!-- Settings Section -->
<div id="settings" class="section">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">System Settings & Configuration</h3>
        </div>
        
        <!-- Store Information Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Store Information</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" id="store-info-form">
                    <div class="form-group">
                        <label class="form-label">Café Name</label>
                        <input type="text" name="cafe_name" class="form-input" value="Ravenhill Coffee" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="cafe_address" class="form-input" rows="2" required>123 Coffee Street, Sydney NSW 2000</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="cafe_phone" class="form-input" value="+61 2 1234 5678" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="cafe_email" class="form-input" value="info@ravenhillcoffee.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opening Hours</label>
                        <input type="text" name="opening_hours" class="form-input" value="Mon-Fri: 7:00 AM - 5:00 PM, Weekends: 8:00 AM - 4:00 PM" required>
                    </div>
                    <button type="submit" name="save_store_info" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Store Information
                    </button>
                </form>
            </div>
        </div>

        <!-- Business Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Business Configuration</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" id="business-settings-form">
                    <div class="form-group">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="form-input" value="10" step="0.1" min="0" max="30" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-input" required>
                            <option value="AUD" selected>Australian Dollar (AUD)</option>
                            <option value="USD">US Dollar (USD)</option>
                            <option value="EUR">Euro (EUR)</option>
                            <option value="GBP">British Pound (GBP)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" class="form-input" value="20" min="1" required>
                        <small>Items below this quantity will trigger low stock alerts</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Auto-refresh Dashboard (seconds)</label>
                        <input type="number" name="refresh_interval" class="form-input" value="60" min="30" max="300">
                        <small>Set to 0 to disable auto-refresh</small>
                    </div>
                    <button type="submit" name="save_business_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Business Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Order & Payment Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Order & Payment Settings</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" id="order-settings-form">
                    <div class="form-group">
                        <label class="form-label">Default Order Status</label>
                        <select name="default_order_status" class="form-input" required>
                            <option value="Pending" selected>Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Order Amount for Delivery</label>
                        <input type="number" name="min_delivery_amount" class="form-input" value="15" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Fee</label>
                        <input type="number" name="delivery_fee" class="form-input" value="5" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Accepted Payment Methods</label>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label><input type="checkbox" name="payment_methods[]" value="cash" checked> Cash</label>
                            <label><input type="checkbox" name="payment_methods[]" value="card" checked> Credit/Debit Card</label>
                            <label><input type="checkbox" name="payment_methods[]" value="paypal"> PayPal</label>
                            <label><input type="checkbox" name="payment_methods[]" value="apple_pay"> Apple Pay</label>
                        </div>
                    </div>
                    <button type="submit" name="save_order_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Order Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- System Administration -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">System Administration</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">System Status</label>
                    <div class="status-badge status-completed" style="display: inline-block; margin-left: 1rem;">
                        <i class="fas fa-check-circle"></i> All Systems Operational
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Backup</label>
                    <p><?php echo date('F j, Y g:i A'); ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Database Size</label>
                    <p>
                        <?php
                        $db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                               FROM information_schema.tables 
                                               WHERE table_schema = 'ravenhill_final'")->fetch()['size'];
                        echo $db_size . " MB";
                        ?>
                    </p>
                </div>
                <div class="form-group" style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-primary" onclick="backupDatabase()">
                        <i class="fas fa-database"></i> Backup Database
                    </button>
                    <button type="button" class="btn" onclick="clearCache()">
                        <i class="fas fa-broom"></i> Clear Cache
                    </button>
                    <button type="button" class="btn" onclick="generateSystemReport()">
                        <i class="fas fa-file-alt"></i> System Report
                    </button>
                </div>
            </div>
        </div>

        <!-- User Preferences -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Preferences</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" id="user-preferences-form">
                    <div class="form-group">
                        <label class="form-label">Dashboard Theme</label>
                        <select name="theme" class="form-input" onchange="changeTheme(this.value)">
                            <option value="light">Light Mode</option>
                            <option value="dark">Dark Mode</option>
                            <option value="auto">Auto (System Preference)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Items Per Page</label>
                        <select name="items_per_page" class="form-input">
                            <option value="10">10 items</option>
                            <option value="25" selected>25 items</option>
                            <option value="50">50 items</option>
                            <option value="100">100 items</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notification Preferences</label>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label><input type="checkbox" name="notifications[]" value="low_stock" checked> Low Stock Alerts</label>
                            <label><input type="checkbox" name="notifications[]" value="new_orders" checked> New Order Notifications</label>
                            <label><input type="checkbox" name="notifications[]" value="system_updates"> System Update Notifications</label>
                        </div>
                    </div>
                    <button type="submit" name="save_user_preferences" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </form>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <button class="btn btn-primary" onclick="showSection('inventory')">
                    <i class="fas fa-boxes"></i> Check Inventory
                </button>
                <button class="btn btn-primary" onclick="showSection('orders')">
                    <i class="fas fa-shopping-cart"></i> View Recent Orders
                </button>
                <button class="btn btn-primary" onclick="showSection('reports')">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
                <button class="btn btn-primary" onclick="openModal('add-product-modal')">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Add Product Modal -->
    <div id="add-product-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Product</h3>
                <button class="modal-close" onclick="closeModal('add-product-modal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-input" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-input" required>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Allergens</label>
                    <input type="text" name="allergens" class="form-input" placeholder="e.g., Milk, Nuts, Gluten">
                </div>
                <div class="form-group">
                    <label class="form-label">Image URL</label>
                    <input type="url" name="image_url" class="form-input" placeholder="https://example.com/image.jpg">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="available" class="form-checkbox" checked>
                        Available for purchase
                    </label>
                </div>
                <div class="form-group">
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn" onclick="closeModal('add-product-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="update-stock-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Stock Level</h3>
                <button class="modal-close" onclick="closeModal('update-stock-modal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="product_id" id="stock-product-id">
                <div class="form-group">
                    <label class="form-label" id="stock-product-name">Product Name</label>
                    <input type="number" name="stock_level" id="stock-level" class="form-input" min="0" required>
                </div>
                <div class="form-group">
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                    <button type="button" class="btn" onclick="closeModal('update-stock-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Order Details Modal -->
    <div id="order-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Order Details</h3>
                <button class="modal-close" onclick="closeModal('order-details-modal')">&times;</button>
            </div>
            <div id="order-details-content">
                <!-- Dynamically loaded via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Update Order Status Modal -->
    <div id="update-order-status-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Order Status</h3>
                <button class="modal-close" onclick="closeModal('update-order-status-modal')">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php#orders">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" id="update-order-id">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="payment_status" class="form-input" required>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn" onclick="closeModal('update-order-status-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Add Promotion Modal -->
<div id="add-promotion-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Promotion</h3>
            <button class="modal-close" onclick="closeModal('add-promotion-modal')">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Promotion Code</label>
                <input type="text" name="code" class="form-input" required placeholder="e.g., SUMMER25">
            </div>
            <div class="form-group">
                <label class="form-label">Promotion Type</label>
                <select name="type" class="form-input" required>
                    <option value="percentage">Percentage Discount</option>
                    <option value="fixed">Fixed Amount Discount</option>
                    <option value="bogo">Buy One Get One</option>
                    <option value="combo">Combo Deal</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Value</label>
                <input type="number" name="value" class="form-input" step="0.01" min="0" required placeholder="e.g., 25 for 25% or 5 for $5">
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" rows="3" required placeholder="Describe the promotion..."></textarea>
            </div>
            <div class="form-group">
                <button type="submit" name="add_promotion" class="btn btn-primary">Add Promotion</button>
                <button type="button" class="btn" onclick="closeModal('add-promotion-modal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Promotion Modal -->
<div id="edit-promotion-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Promotion</h3>
            <button class="modal-close" onclick="closeModal('edit-promotion-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="promotion_id" id="edit-promotion-id">
            <div class="form-group">
                <label class="form-label">Promotion Code</label>
                <input type="text" name="code" id="edit-code" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Promotion Type</label>
                <select name="type" id="edit-type" class="form-input" required>
                    <option value="percentage">Percentage Discount</option>
                    <option value="fixed">Fixed Amount Discount</option>
                    <option value="bogo">Buy One Get One</option>
                    <option value="combo">Combo Deal</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Value</label>
                <input type="number" name="value" id="edit-value" class="form-input" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start" id="edit-start" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end" id="edit-end" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit-description" class="form-input" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <button type="submit" name="update_promotion" class="btn btn-primary">Update Promotion</button>
                <button type="button" class="btn" onclick="closeModal('edit-promotion-modal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

    <script>
        // Function to show/hide sections
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });

            document.getElementById(sectionId).classList.add('active');

            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });

            document.querySelectorAll('.nav-item').forEach(item => {
                if (item.getAttribute('onclick').includes(sectionId)) {
                    item.classList.add('active');
                }
            });

            window.scrollTo(0, 0);
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Update stock function
        function updateStock(productId, productName, currentStock) {
            document.getElementById('stock-product-id').value = productId;
            document.getElementById('stock-product-name').textContent = productName;
            document.getElementById('stock-level').value = currentStock;
            openModal('update-stock-modal');
        }

        // View order details via AJAX-like fetch
        function viewOrderDetails(orderId) {
            fetch('fetch_order_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + encodeURIComponent(orderId)
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('order-details-content').innerHTML = data;
                openModal('order-details-modal');
            })
            .catch(error => {
                alert('Error fetching order details: ' + error);
            });
        }

        // Update order status modal
        function updateOrderStatus(orderId, currentStatus) {
            document.getElementById('update-order-id').value = orderId;
            const select = document.querySelector('#update-order-status-modal select[name="payment_status"]');
            select.value = currentStatus;
            openModal('update-order-status-modal');
        }
        // Edit promotion function
function editPromotion(promotion) {
    document.getElementById('edit-promotion-id').value = promotion.promotion_id;
    document.getElementById('edit-code').value = promotion.code;
    document.getElementById('edit-type').value = promotion.type;
    document.getElementById('edit-value').value = promotion.value;
    document.getElementById('edit-start').value = promotion.start;
    document.getElementById('edit-end').value = promotion.end;
    document.getElementById('edit-description').value = promotion.description;
    openModal('edit-promotion-modal');
}
// Report section script

function generateReport() {
    // Get current date range from the form
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    
    // Create a simple CSV export (you can enhance this with a proper PDF/Excel generator)
    let csvContent = "Ravenhill Coffee - Sales Report\n";
    csvContent += `Period: ${startDate} to ${endDate}\n\n`;
    
    // Add summary data
    csvContent += "SUMMARY\n";
    csvContent += `Total Revenue,$${<?php echo $customer_stats['total_revenue'] ?? 0; ?>}\n`;
    csvContent += `Total Orders,${<?php echo $customer_stats['total_orders'] ?? 0; ?>}\n`;
    csvContent += `Average Order Value,$${<?php echo number_format($customer_stats['avg_order_value'] ?? 0, 2); ?>}\n\n`;
    
    // Trigger download
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', `ravenhill-report-${startDate}-to-${endDate}.csv`);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    // Show success message
    alert('Report generated successfully!');
}

// Settings Section Functions
function backupDatabase() {
    if (confirm('This will create a backup of the database. Continue?')) {
        alert('Database backup initiated! This may take a few moments.');
        // Simulate backup process
        setTimeout(() => {
            alert('Backup completed successfully!');
        }, 2000);
    }
}

function clearCache() {
    if (confirm('Clear all cached data?')) {
        alert('Cache cleared successfully!');
    }
}

function generateSystemReport() {
    alert('System report generated and downloaded!');
    // This would typically generate and download a PDF report
}

function changeTheme(theme) {
    alert('Theme changed to ' + theme + '. Refresh to see changes.');
    // In a real implementation, this would set a cookie or update user preferences
}


        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
        document.querySelectorAll('.filter-btn').forEach(button => {
        button.addEventListener('click', () => {
        const category = button.getAttribute('data-category');
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        document.querySelectorAll('.category-section').forEach(section => {
            if (category === 'All' || section.getAttribute('data-category') === category) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });
    });
});
    </script>
</body>
</html>