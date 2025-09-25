<?php
// fetch_order_details.php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];

    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.total_price, o.order_time, o.order_type, o.order_address,
               u.name as customer_name, s.name as staff_name, c.name as cashier_name,
               p.code as promotion_code, pay.payment_status, pay.method as payment_method
        FROM orders o
        LEFT JOIN customer cu ON o.customer_id = cu.customer_id
        LEFT JOIN users u ON cu.customer_id = u.user_id
        LEFT JOIN staff st ON o.staff_id = st.staff_id
        LEFT JOIN users s ON st.staff_id = s.user_id
        LEFT JOIN cashier ca ON o.cashier_id = ca.cashier_id
        LEFT JOIN users c ON ca.cashier_id = c.user_id
        LEFT JOIN promotion p ON o.promotion_id = p.promotion_id
        LEFT JOIN payment pay ON o.order_id = pay.order_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.order_item_id, p.name, oi.quantity, oi.unit_price, oi.customisations
        FROM order_item oi
        JOIN product p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    // Fetch loyalty points
    $stmt = $pdo->prepare("
        SELECT points, description, created_at
        FROM loyalty_program
        WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    $loyalty = $stmt->fetch();

    // Fetch notifications
    $stmt = $pdo->prepare("
        SELECT notif_type, content, sent_time, is_read
        FROM notification
        WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    $notifications = $stmt->fetchAll();

    // Output order details
    echo '<div class="order-details">';
    echo '<h4>Order #' . htmlspecialchars($order['order_id']) . '</h4>';
    echo '<p><strong>Customer:</strong> ' . htmlspecialchars($order['customer_name'] ?: 'Guest') . '</p>';
    echo '<p><strong>Total:</strong> $' . number_format($order['total_price'], 2) . '</p>';
    echo '<p><strong>Order Type:</strong> ' . htmlspecialchars($order['order_type']) . '</p>';
    if ($order['order_address']) {
        echo '<p><strong>Address:</strong> ' . htmlspecialchars($order['order_address']) . '</p>';
    }
    echo '<p><strong>Status:</strong> ' . htmlspecialchars($order['payment_status']) . '</p>';
    echo '<p><strong>Date:</strong> ' . date('M j, Y H:i', strtotime($order['order_time'])) . '</p>';
    if ($order['staff_name']) {
        echo '<p><strong>Staff:</strong> ' . htmlspecialchars($order['staff_name']) . '</p>';
    }
    if ($order['cashier_name']) {
        echo '<p><strong>Cashier:</strong> ' . htmlspecialchars($order['cashier_name']) . '</p>';
    }
    if ($order['promotion_code']) {
        echo '<p><strong>Promotion:</strong> ' . htmlspecialchars($order['promotion_code']) . '</p>';
    }
    if ($order['payment_method']) {
        echo '<p><strong>Payment Method:</strong> ' . htmlspecialchars($order['payment_method']) . '</p>';
    }
    if ($loyalty) {
        echo '<p><strong>Loyalty Points Earned:</strong> ' . $loyalty['points'] . ' (' . htmlspecialchars($loyalty['description']) . ') on ' . date('M j, Y H:i', strtotime($loyalty['created_at'])) . '</p>';
    }
    echo '<h4>Order Items</h4>';
    echo '<table class="table">';
    echo '<thead><tr><th>Item</th><th>Quantity</th><th>Unit Price</th><th>Customizations</th></tr></thead>';
    echo '<tbody>';
    foreach ($items as $item) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['name']) . '</td>';
        echo '<td>' . $item['quantity'] . '</td>';
        echo '<td>$' . number_format($item['unit_price'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($item['customisations'] ?: 'None') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    if (!empty($notifications)) {
        echo '<h4>Notifications</h4>';
        echo '<ul>';
        foreach ($notifications as $notif) {
            echo '<li><strong>' . htmlspecialchars($notif['notif_type']) . '</strong>: ' . htmlspecialchars($notif['content']) . ' (Sent: ' . date('M j, Y H:i', strtotime($notif['sent_time'])) . ', Read: ' . ($notif['is_read'] ? 'Yes' : 'No') . ')</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}
?>