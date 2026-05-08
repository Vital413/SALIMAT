<?php
// admin/login.php - Administrator Login Page
require_once '../config/config.php';

// Redirect if already logged in as admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim(filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_STRING)); // Can be email or username
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        try {
            // Fetch admin from the database (checking both username and email)
            $stmt = $pdo->prepare("SELECT admin_id, username, password_hash FROM admins WHERE email = ? OR username = ?");
            $stmt->execute([$identifier, $identifier]);
            $admin = $stmt->fetch();

            // Verify if admin exists and password is correct
            if ($admin && password_verify($password, $admin['password_hash'])) {

                // Prevent Session Fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['role'] = 'admin';

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Generic error message for security
                $error = "Invalid credentials. Access denied.";
            }
        } catch (PDOException $e) {
            $error = "Database error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Login - LuminaCare</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2b7a78;
            --accent-color: #ff9a9e;
            --text-dark: #17252a;
            --bg-light: #f3f4f6;
            --admin-theme: #111827;
            /* Dark slate for Admin */
            --admin-theme-light: #374151;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .brand-logo {
            font-family: 'Poppins', sans-serif;
        }

        .auth-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            margin: 40px auto;
        }

        .auth-sidebar {
            background: linear-gradient(135deg, var(--admin-theme) 0%, var(--admin-theme-light) 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .auth-sidebar::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
        }

        .brand-logo span {
            color: var(--accent-color);
        }

        .auth-form-wrapper {
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(55, 65, 81, 0.2);
            border-color: var(--admin-theme-light);
        }

        .form-label {
            font-weight: 600;
            color: var(--admin-theme);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .btn-admin-custom {
            background-color: var(--admin-theme);
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-admin-custom:hover {
            background-color: black;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(17, 24, 39, 0.3);
        }

        .security-badge {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            font-size: 0.85rem;
            width: fit-content;
        }

        @media (max-width: 768px) {
            .auth-sidebar {
                padding: 40px 30px;
                text-align: center;
                min-height: 300px;
            }

            .security-badge {
                margin: 0 auto;
            }

            .auth-form-wrapper {
                padding: 40px 30px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="auth-container row g-0">

            <!-- Left Sidebar (Branding) -->
            <div class="col-md-5 auth-sidebar">
                <div>
                    <a href="../index.php" class="brand-logo"><i class="bi bi-heart-pulse-fill me-2"></i>Lumina<span>Care</span></a>
                    <h2 class="mt-5 fw-bold">System Administration</h2>
                    <p class="opacity-75 mt-3 fs-5">Secure control panel for managing platform users, verifying healthcare providers, and monitoring system health.</p>
                </div>

                <div class="mt-5 pt-5 border-top border-light border-opacity-25 z-1">
                    <div class="security-badge">
                        <i class="bi bi-shield-lock-fill me-2 text-warning"></i> Restricted Access Area
                    </div>
                </div>
            </div>

            <!-- Right Side (Form) -->
            <div class="col-md-7 auth-form-wrapper">
                <div class="mb-4 text-center text-md-start">
                    <h3 class="fw-bold" style="color: var(--admin-theme);">Admin Login</h3>
                    <p class="text-muted">Enter your administrative credentials to proceed.</p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="identifier" class="form-label">Username or Email</label>
                        <input type="text" class="form-control" id="identifier" name="identifier" required
                            value="<?php echo isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : ''; ?>">
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label mb-0">Password</label>
                        </div>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-admin-custom fs-5 mt-2"><i class="bi bi-box-arrow-in-right me-2"></i> Authenticate</button>

                    <div class="text-center mt-4">
                        <a href="../index.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i> Return to Homepage</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>