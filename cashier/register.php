<?php
// cashier/register.php - Cashier & Billing Staff Registration
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $lastName = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            // Check if email already exists in cashiers table
            $stmt = $pdo->prepare("SELECT cashier_id FROM cashiers WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = "An account with this email already exists.";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Insert new cashier (is_active defaults to 0 for admin approval)
                $insertStmt = $pdo->prepare("
                    INSERT INTO cashiers (first_name, last_name, email, phone, password_hash, is_active) 
                    VALUES (?, ?, ?, ?, ?, 0)
                ");

                if ($insertStmt->execute([$firstName, $lastName, $email, $phone, $passwordHash])) {
                    $success = "Registration successful! Your account is pending admin verification. Redirecting to login...";
                    header("refresh:3;url=login.php");
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        } catch (PDOException $e) {
            // Setup table if missing
            $pdo->exec("CREATE TABLE IF NOT EXISTS `cashiers` (
                `cashier_id` int(11) NOT NULL AUTO_INCREMENT,
                `first_name` varchar(50) NOT NULL,
                `last_name` varchar(50) NOT NULL,
                `email` varchar(100) NOT NULL UNIQUE,
                `phone` varchar(20) DEFAULT NULL,
                `password_hash` varchar(255) NOT NULL,
                `is_active` tinyint(1) DEFAULT 0,
                `created_at` timestamp DEFAULT current_timestamp(),
                PRIMARY KEY (`cashier_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $error = "Database setup was incomplete. Please try submitting your registration again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Registration - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --cashier-theme: #4f46e5;
            --cashier-theme-light: #818cf8;
            --bg-light: #f8fafb;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .auth-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            margin: 40px auto;
        }

        .auth-sidebar {
            background: linear-gradient(135deg, var(--cashier-theme) 0%, var(--cashier-theme-light) 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-custom {
            background-color: var(--cashier-theme);
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-custom:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {

            .auth-sidebar,
            .auth-form-wrapper {
                padding: 40px 30px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="auth-container row g-0">
            <div class="col-md-5 auth-sidebar">
                <div>
                    <h2 class="fw-bold"><i class="bi bi-cash-coin me-2"></i>Billing Registration</h2>
                    <p class="opacity-75 mt-3">Join the financial team to streamline patient payments and maintain accurate hospital records.</p>
                </div>
                <div class="mt-5 pt-5 border-top border-light border-opacity-25">
                    <p class="mb-2">Already have an account?</p>
                    <a href="login.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">Log In Here</a>
                </div>
            </div>
            <div class="col-md-7 p-5">
                <h3 class="fw-bold mb-1">Create Staff Account</h3>
                <p class="text-muted small mb-4">Registration requires administrative approval before dashboard access is granted.</p>
                <?php if ($error): ?><div class="alert alert-danger border-0 rounded-3"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success border-0 rounded-3"><?php echo $success; ?></div><?php endif; ?>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label small fw-bold">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label small fw-bold">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">Phone</label><input type="text" name="phone" class="form-control"></div>
                        <div class="col-6"><label class="form-label small fw-bold">Password</label><input type="password" name="password" class="form-control" required></div>
                        <div class="col-6"><label class="form-label small fw-bold">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn btn-custom mt-4 fs-5">Submit Registration</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>