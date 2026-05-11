<?php
// pharmacy/register.php - Pharmacist Registration
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $lname = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($fname) || empty($lname) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT pharmacist_id FROM pharmacists WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "An account with this email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO pharmacists (first_name, last_name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 0)");
                if ($stmt->execute([$fname, $lname, $email, $phone, $hash])) {
                    $success = "Registration successful! Pending admin approval. Redirecting to login...";
                    header("refresh:3;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $error = "Database error. Setup might be required.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Registration - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --pharmacy-theme: #059669;
            --pharmacy-theme-light: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafb;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="auth-container row g-0">
            <div class="col-md-5 auth-sidebar">
                <div>
                    <h2 class="fw-bold"><i class="bi bi-plus-circle me-2"></i>Join the Pharmacy Team</h2>
                    <p class="opacity-75 mt-3">Provide essential medication management and pharmaceutical care to expectant mothers.</p>
                </div>
                <div class="mt-5 pt-5 border-top border-light border-opacity-25">
                    <p class="mb-2">Already registered?</p>
                    <a href="login.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">Log In Here</a>
                </div>
            </div>
            <div class="col-md-7 p-5">
                <h3 class="fw-bold mb-1">Pharmacist Registration</h3>
                <p class="text-muted small mb-4">Credentials will be verified by system admin before access is granted.</p>
                <?php if (!empty($error)): ?><div class="alert alert-danger border-0 rounded-3"><?php echo $error; ?></div><?php endif; ?>
                <?php if (!empty($success)): ?><div class="alert alert-success border-0 rounded-3"><?php echo $success; ?></div><?php endif; ?>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn btn-custom mt-4 fs-5">Submit Registration</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>