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

// Function to generate a unique user ID
function generateUserId($pdo, $role) {
    $prefix = '';
    switch ($role) {
        case 'admin':
            $prefix = 'ADM';
            break;
        case 'cashier':
            $prefix = 'CSH';
            break;
        case 'staff':
            $prefix = 'STF';
            break;
        case 'customer':
            $prefix = 'CUS';
            break;
        default:
            $prefix = 'USR';
    }
    
    // Generate a random number
    $random = mt_rand(1000, 9999);
    $user_id = $prefix . $random;
    
    // Check if the ID already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->fetch()['count'] > 0) {
        // If exists, generate a new one recursively
        return generateUserId($pdo, $role);
    }
    
    return $user_id;
}

// Initialize error and success messages
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);
    $created_at = date('Y-m-d'); // Current date

    // Role-specific fields
    $access_granted_on = ($role === 'admin') ? date('Y-m-d') : null;
    $cashier_code = ($role === 'cashier') ? trim($_POST['cashier_code'] ?? '') : null;
    $shift = ($role === 'cashier') ? trim($_POST['shift'] ?? '') : null;
    $staff_number = ($role === 'staff') ? trim($_POST['staff_number'] ?? '') : null;
    $position = ($role === 'staff') ? trim($_POST['position'] ?? '') : null;
    $loyalty_points = ($role === 'customer') ? (int)($_POST['loyalty_points'] ?? 0) : null;

    // Validate common fields
    if (empty($name) || empty($email) || empty($password) || empty($role) || empty($status)) {
        $error = "All common fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!empty($phone) && !preg_match('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/', $phone)) {
        $error = "Invalid phone number format.";
    } else {
        // Validate role-specific fields
        if ($role === 'cashier' && (empty($cashier_code) || empty($shift))) {
            $error = "Cashier code and shift are required for cashier role.";
        } elseif ($role === 'staff' && (empty($staff_number) || empty($position))) {
            $error = "Staff number and position are required for staff role.";
        } else {
            try {
                $pdo->beginTransaction();

                // Check for duplicate email
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()['count'] > 0) {
                    $error = "Email already exists.";
                } else {
                    // Generate a unique user ID
                    $user_id = generateUserId($pdo, $role);
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert into users table with the generated user_id
                    $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, phone, address, created_at, role, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $name, $email, $hashed_password, $phone, $address, $created_at, $role, $status]);

                    // Insert into role-specific table with correct foreign key column name
                    if ($role === 'admin') {
                        $stmt = $pdo->prepare("INSERT INTO admin (admin_id, access_granted_on) VALUES (?, ?)");
                        $stmt->execute([$user_id, $access_granted_on]);
                    } elseif ($role === 'cashier') {
                        $stmt = $pdo->prepare("INSERT INTO cashier (cashier_id, cashier_code, shift) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $cashier_code, $shift]);
                    } elseif ($role === 'staff') {
                        $stmt = $pdo->prepare("INSERT INTO staff (staff_id, staff_number, position) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $staff_number, $position]);
                    } elseif ($role === 'customer') {
                        $stmt = $pdo->prepare("INSERT INTO customer (customer_id, loyalty_points) VALUES (?, ?)");
                        $stmt->execute([$user_id, $loyalty_points]);
                    }

                    $pdo->commit();

                    // Set success message and redirect
                    $_SESSION['success_message'] = "User added successfully!";
                    header("Location: admin_dashboard.php#users");
                    exit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to add user: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles remain unchanged */
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

        .role-specific {
            display: none;
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
            <h1>Add New User</h1>
            <a href="admin_dashboard.php#users" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Add User Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Create User</h3>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-input" placeholder="e.g., +1234567890 or (123) 456-7890" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="role" class="form-input" required>
                        <option value="" disabled selected>Select Role</option>
                        <option value="admin" <?php echo isset($_POST['role']) && $_POST['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="cashier" <?php echo isset($_POST['role']) && $_POST['role'] == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                        <option value="staff" <?php echo isset($_POST['role']) && $_POST['role'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="customer" <?php echo isset($_POST['role']) && $_POST['role'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input" required>
                        <option value="active" <?php echo isset($_POST['status']) && $_POST['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo isset($_POST['status']) && $_POST['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Role-specific fields -->
                <div id="admin-fields" class="role-specific">
                    <!-- For admin, access_granted_on is automatically set to current date, no input needed -->
                </div>
                <div id="cashier-fields" class="role-specific">
                    <div class="form-group">
                        <label class="form-label">Cashier Code</label>
                        <input type="text" name="cashier_code" class="form-input" value="<?php echo isset($_POST['cashier_code']) ? htmlspecialchars($_POST['cashier_code']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Shift</label>
                        <input type="text" name="shift" class="form-input" value="<?php echo isset($_POST['shift']) ? htmlspecialchars($_POST['shift']) : ''; ?>">
                    </div>
                </div>
                <div id="staff-fields" class="role-specific">
                    <div class="form-group">
                        <label class="form-label">Staff Number</label>
                        <input type="text" name="staff_number" class="form-input" value="<?php echo isset($_POST['staff_number']) ? htmlspecialchars($_POST['staff_number']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-input" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                    </div>
                </div>
                <div id="customer-fields" class="role-specific">
                    <div class="form-group">
                        <label class="form-label">Loyalty Points</label>
                        <input type="number" name="loyalty_points" class="form-input" min="0" value="<?php echo isset($_POST['loyalty_points']) ? htmlspecialchars($_POST['loyalty_points']) : '0'; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    <button type="button" class="btn" onclick="window.location.href='admin_dashboard.php#users'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const fields = ['admin-fields', 'cashier-fields', 'staff-fields', 'customer-fields'];
            fields.forEach(field => {
                document.getElementById(field).style.display = 'none';
            });
            if (role) {
                document.getElementById(role + '-fields').style.display = 'block';
            }
        });
        
        // Trigger change event on page load to show appropriate fields
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            if (roleSelect.value) {
                roleSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>