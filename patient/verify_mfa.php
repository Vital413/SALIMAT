<?php
// patient/verify_mfa.php - Multi-Factor Authentication Verification Step
require_once '../config/config.php';

// Redirect if they aren't supposed to be here (no pending login)
if (!isset($_SESSION['pending_patient_id']) || !isset($_SESSION['mfa_otp'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle Resend OTP Request
    if (isset($_POST['resend_otp'])) {
        // Generate a new secure 6-digit OTP
        $new_otp = rand(100000, 999999);
        $_SESSION['mfa_otp'] = $new_otp; // Update session with new OTP

        // Dispatch Email with the new OTP
        $to = $_SESSION['mfa_email'];
        $subject = "Your New LuminaCare Security Code";
        $message = "Hello " . $_SESSION['pending_first_name'] . ",\n\nAs requested, here is your new 6-digit code to complete your login:\n\nSecurity Code: " . $new_otp . "\n\nIf you did not request this, please contact support immediately.\n\n- LuminaCare Security";
        $headers = "From: LuminaCare Security <no-reply@luminacaresec.com>\r\n" . "Reply-To: no-reply@luminacaresec.com";

        // Use @ to suppress the XAMPP localhost SMTP warning if mailserver isn't configured
        @mail($to, $subject, $message, $headers);

        $success = "A new security code has been sent to your email address.";
    }
    // Handle OTP Verification
    elseif (isset($_POST['verify_otp'])) {
        $entered_otp = trim(filter_input(INPUT_POST, 'otp', FILTER_SANITIZE_STRING));

        // Validate the OTP
        if ($entered_otp == $_SESSION['mfa_otp']) {
            // SUCCESS: Elevate pending session to fully authenticated session
            session_regenerate_id(true);

            $_SESSION['patient_id'] = $_SESSION['pending_patient_id'];
            $_SESSION['first_name'] = $_SESSION['pending_first_name'];
            $_SESSION['last_name'] = $_SESSION['pending_last_name'];
            $_SESSION['role'] = 'patient';

            // Clear sensitive MFA temporary data
            unset($_SESSION['pending_patient_id']);
            unset($_SESSION['pending_first_name']);
            unset($_SESSION['pending_last_name']);
            unset($_SESSION['mfa_otp']);
            unset($_SESSION['mfa_email']);

            // Redirect to Dashboard securely
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid security code. Please check your email and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Step Verification - LuminaCare</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2b7a78;
            --secondary-color: #3aafa9;
            --accent-color: #ff9a9e;
            --bg-light: #f8fafb;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Poppins', sans-serif;
        }

        .mfa-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            padding: 50px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(43, 122, 120, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
        }

        .otp-input {
            text-align: center;
            font-size: 2rem;
            letter-spacing: 15px;
            font-weight: bold;
            border-radius: 15px;
            padding: 15px;
            border: 2px solid #e1e5e8;
            background-color: #fcfcfc;
        }

        .otp-input:focus {
            box-shadow: 0 0 0 4px rgba(58, 175, 169, 0.2);
            border-color: var(--secondary-color);
            outline: none;
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50px;
            padding: 15px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-primary-custom:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(43, 122, 120, 0.2);
        }
    </style>
</head>

<body>

    <div class="container d-flex justify-content-center">
        <div class="mfa-container">
            <div class="icon-wrapper">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">Two-Step Verification</h3>
            <p class="text-muted mb-4">To secure your health data, we've sent a 6-digit code to your email <br><strong><?php echo htmlspecialchars($_SESSION['mfa_email']); ?></strong></p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 py-2 text-start">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 py-2 text-start">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="verify_mfa.php">
                <div class="mb-3">
                    <input type="text" class="form-control otp-input" id="otp" name="otp" required maxlength="6" pattern="\d{6}" placeholder="------" autocomplete="one-time-code" autofocus>
                </div>
                <button type="submit" name="verify_otp" class="btn btn-primary-custom fs-5">Verify & Login</button>
            </form>

            <form method="POST" action="verify_mfa.php" class="mt-3">
                <button type="submit" name="resend_otp" class="btn btn-link text-decoration-none p-0 text-muted small">Didn't receive the code? <strong>Resend Email</strong></button>
            </form>

            <div class="mt-4 pt-3 border-top">
                <a href="login.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i> Back to Login</a>
            </div>
        </div>
    </div>

</body>

</html>