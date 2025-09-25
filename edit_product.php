[file name]: edit_product.php
[file content begin]
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
    die("Database Connection failed: " . $e->getMessage());
}

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No product ID specified.";
    header("Location: admin_dashboard.php#products");
    exit();
}

$product_id = $_GET['id'];

// Fetch product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, i.stock_level, i.threshold 
        FROM product p 
        JOIN category c ON p.category_id = c.category_id 
        LEFT JOIN inventory i ON p.product_id = i.product_id 
        WHERE p.product_id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error_message'] = "Product not found.";
        header("Location: admin_dashboard.php#products");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to fetch product: " . $e->getMessage();
    header("Location: admin_dashboard.php#products");
    exit();
}

// Fetch all categories for the dropdown
try {
    $categories = $pdo->query("SELECT * FROM category ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $error = "Failed to fetch categories: " . $e->getMessage();
}

// Initialize error and success messages
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $category_id = trim($_POST['category_id']);
    $available = isset($_POST['available']) ? 1 : 0;
    $allergens = trim($_POST['allergens'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $stock_level = trim($_POST['stock_level'] ?? 0);
    $threshold = trim($_POST['threshold'] ?? 10);

    // Validate fields
    if (empty($name) || empty($description) || empty($price) || empty($category_id)) {
        $error = "Name, description, price, and category are required.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Price must be a valid positive number.";
    } elseif (!is_numeric($stock_level) || $stock_level < 0) {
        $error = "Stock level must be a valid non-negative number.";
    } elseif (!is_numeric($threshold) || $threshold < 0) {
        $error = "Threshold must be a valid non-negative number.";
    } else {
        try {
            $pdo->beginTransaction();

            // Update product
            $stmt = $pdo->prepare("
                UPDATE product 
                SET name = ?, description = ?, price = ?, category_id = ?, 
                    available = ?, allergens = ?, image_url = ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$name, $description, $price, $category_id, $available, $allergens, $image_url, $product_id]);

            // Update inventory
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET stock_level = ?, threshold = ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$stock_level, $threshold, $product_id]);

            $pdo->commit();

            $success = "Product updated successfully!";
            
            // Refresh product data
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, i.stock_level, i.threshold 
                FROM product p 
                JOIN category c ON p.category_id = c.category_id 
                LEFT JOIN inventory i ON p.product_id = i.product_id 
                WHERE p.product_id = ?
            ");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to update product: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles */
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

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
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

        .btn-secondary {
            background: var(--admin-gray);
            color: var(--text-light);
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .card {
            background: var(--text-light);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--secondary-beige);
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
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-brown);
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

        select.form-input {
            padding: 0.5rem;
        }

        textarea.form-input {
            min-height: 100px;
            resize: vertical;
        }

        .form-checkbox {
            margin-right: 0.5rem;
            width: 18px;
            height: 18px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            font-weight: 500;
            color: var(--text-dark);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid;
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

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 0.5rem;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--secondary-beige);
        }

        .image-preview img {
            width: 100%;
            height: auto;
            display: block;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Edit Product</h1>
            <a href="admin_dashboard.php#products" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>

        <!-- Edit Product Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Product: <?php echo htmlspecialchars($product['name']); ?></h3>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-input" required 
                           value="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price ($)</label>
                    <input type="number" name="price" class="form-input" step="0.01" min="0" required 
                           value="<?php echo htmlspecialchars($product['price']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-input" required>
                        <option value="" disabled>Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" 
                                <?php echo ($category['category_id'] == $product['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Allergens</label>
                    <input type="text" name="allergens" class="form-input" 
                           placeholder="e.g., Milk, Nuts, Gluten"
                           value="<?php echo htmlspecialchars($product['allergens']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Image URL</label>
                    <input type="url" name="image_url" class="form-input" 
                           placeholder="https://example.com/image.jpg"
                           value="<?php echo htmlspecialchars($product['image_url']); ?>"
                           onchange="updateImagePreview(this.value)">
                    <?php if (!empty($product['image_url'])): ?>
                        <div class="image-preview">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="Product image preview" id="image-preview">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stock Level</label>
                    <input type="number" name="stock_level" class="form-input" min="0" 
                           value="<?php echo htmlspecialchars($product['stock_level']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stock Threshold (Low Stock Alert)</label>
                    <input type="number" name="threshold" class="form-input" min="0" 
                           value="<?php echo htmlspecialchars($product['threshold']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="available" class="form-checkbox" 
                            <?php echo $product['available'] ? 'checked' : ''; ?>>
                        Available for purchase
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="update_product" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                    <a href="admin_dashboard.php#products" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateImagePreview(url) {
            const preview = document.getElementById('image-preview');
            if (preview && url) {
                preview.src = url;
            }
        }
        
        // Initialize image preview if it exists
        document.addEventListener('DOMContentLoaded', function() {
            const imageUrlInput = document.querySelector('input[name="image_url"]');
            if (imageUrlInput && imageUrlInput.value) {
                updateImagePreview(imageUrlInput.value);
            }
        });
    </script>
</body>
</html>
[file content end]