<?php
// pharmacy/login.php - Pharmacist Login Page
require_once '../config/config.php';

// Redirect if already logged in as pharmacist
if (isset($_SESSION['role']) && $_SESSION['role'] === 'pharmacist') {
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
            // Fetch pharmacist from database
            $stmt = $pdo->prepare("SELECT pharmacist_id, first_name, last_name, password_hash, is_active FROM pharmacists WHERE email = ?");
            $stmt->execute([$email]);
            $pharmacist = $stmt->fetch();

            if ($pharmacist && password_verify($password, $pharmacist['password_hash'])) {
                if ($pharmacist['is_active'] == 1) {
                    session_regenerate_id(true);
                    $_SESSION['pharmacist_id'] = $pharmacist['pharmacist_id'];
                    $_SESSION['first_name'] = $pharmacist['first_name'];
                    $_SESSION['last_name'] = $pharmacist['last_name'];
                    $_SESSION['role'] = 'pharmacist';
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Your account is pending admin verification or has been suspended.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Login - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --pharmacy-theme: #059669;
            --pharmacy-theme-light: #10b981;
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
            max-width: 900px;
            margin: 40px auto;
        }

        .auth-sidebar {
            background: linear-gradient(135deg, var(--pharmacy-theme) 0%, var(--pharmacy-theme-light) 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-custom {
            background-color: var(--pharmacy-theme);
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-custom:hover {
            background-color: #047857;
            color: white;
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
                    <h2 class="fw-bold"><i class="bi bi-capsule-pill me-2"></i>Pharmacy Portal</h2>
                    <p class="opacity-75 mt-3">Log in to manage prescriptions, fulfill medication orders, and track pharmacy inventory.</p>
                </div>
                <div class="mt-5 pt-5 border-top border-light border-opacity-25">
                    <p class="mb-2">New pharmacist?</p>
                    <a href="register.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">Register Here</a>
                </div>
            </div>
            <div class="col-md-7 p-5">
                <h3 class="fw-bold mb-4">Pharmacist Login</h3>
                <?php if (!empty($error)): ?><div class="alert alert-danger border-0 rounded-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?></div><?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3"><label class="form-label">Professional Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <button type="submit" class="btn btn-custom fs-5">Access Portal</button>
                    <div class="text-center mt-4"><a href="../index.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Home</a></div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>