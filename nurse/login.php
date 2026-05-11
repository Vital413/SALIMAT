<?php
// nurse/login.php - Nurse Login Page
require_once '../config/config.php';

// Redirect if already logged in as nurse
if (isset($_SESSION['role']) && $_SESSION['role'] === 'nurse') {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            // Fetch nurse from the database
            $stmt = $pdo->prepare("SELECT nurse_id, first_name, last_name, password_hash, is_active FROM nurses WHERE email = ?");
            $stmt->execute([$email]);
            $nurse = $stmt->fetch();

            // Verify if nurse exists and password is correct
            if ($nurse && password_verify($password, $nurse['password_hash'])) {

                // CRITICAL SECURITY CHECK: Ensure the admin has activated this nurse
                if ($nurse['is_active'] == 1) {
                    // Prevent Session Fixation
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['nurse_id'] = $nurse['nurse_id'];
                    $_SESSION['first_name'] = $nurse['first_name'];
                    $_SESSION['last_name'] = $nurse['last_name'];
                    $_SESSION['role'] = 'nurse';

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Your account has been suspended or is pending verification. Please contact administration.";
                }
            } else {
                // Generic error message for security
                $error = "Invalid email or password.";
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
    <title>Nursing Portal Login - LuminaCare</title>

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
            --bg-light: #f8fafb;
            --nurse-theme: #05828e;
            /* Deep Teal/Cyan for Nursing */
            --nurse-theme-light: #0dcaf0;
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
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            margin: 40px auto;
        }

        .auth-sidebar {
            background: linear-gradient(135deg, var(--nurse-theme) 0%, var(--nurse-theme-light) 100%);
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
            background: rgba(255, 255, 255, 0.1);
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
            border: 1px solid #e1e5e8;
            background-color: #fcfcfc;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(13, 202, 240, 0.2);
            border-color: var(--nurse-theme-light);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .btn-custom {
            background-color: var(--nurse-theme);
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-custom:hover {
            background-color: #046b75;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(5, 130, 142, 0.2);
        }

        @media (max-width: 768px) {
            .auth-sidebar {
                padding: 40px 30px;
                text-align: center;
                min-height: 300px;
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
                    <h2 class="mt-5 fw-bold">Nursing Portal</h2>
                    <p class="opacity-75 mt-3 fs-5">Log in to manage patient care, record clinical vitals, and assist healthcare providers.</p>
                </div>

                <div class="mt-5 pt-5 border-top border-light border-opacity-25 z-1">
                    <p class="mb-2">New to the team?</p>
                    <a href="register.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">Register Here</a>
                </div>
            </div>

            <!-- Right Side (Form) -->
            <div class="col-md-7 auth-form-wrapper">
                <div class="mb-4 text-center text-md-start">
                    <h3 class="fw-bold text-dark">Nurse Login</h3>
                    <p class="text-muted">Enter your registered credentials to access your dashboard.</p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Professional Email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label mb-0">Password</label>
                            <a href="#" class="text-decoration-none small" style="color: var(--nurse-theme);">Forgot Password?</a>
                        </div>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-custom fs-5 mt-2">Access Portal</button>

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