<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connection setup (identical to admin_dashboard.php)
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

// Handle login form submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $password = $_POST['password'];

    // Validate user_id format (must start with 'ADM' followed by numbers)
    if (!preg_match('/^ADM\d+$/', $user_id)) {
        $login_error = "User ID must start with 'ADM' followed by numbers.";
    } else {
        // Fetch user by user_id
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) { // Assumes password is hashed in DB
            if ($user['role'] === 'admin') {
                $_SESSION['admin_id'] = $user['user_id'];
                $_SESSION['admin_name'] = $user['name'];
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $login_error = "You do not have admin privileges.";
            }
        } else {
            $login_error = "Invalid User ID or password.";
        }
    }
}

// Note: This assumes an admin user already exists in the `users` table with user_id starting with 'ADM' (e.g., 'ADM001'),
// role='admin', and a hashed password. If the `password` column doesn't exist, you must add it:
// ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL;
// The password must be hashed in the user management section (e.g., via add_user.php or edit_user.php) using password_hash.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Consistent with admin_dashboard.php styling */
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-container {
            background: var(--text-light);
            padding: 3rem 4rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--secondary-beige);
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .login-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
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
            width: 100%;
            justify-content: center;
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

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--highlight-red);
            text-align: center;
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 0.5rem;
            color: var(--text-gray);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .forgot-password:hover {
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2 class="login-logo"><i class="fas fa-coffee"></i> Ravenhill Admin</h2>
            <h3 class="login-title">Admin Login</h3>
        </div>

        <?php if ($login_error): ?>
            <div class="alert">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">User ID</label>
                <input type="text" name="user_id" class="form-input" required placeholder="e.g., ADM001">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required placeholder="Enter your password">
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>
</body>
</html>