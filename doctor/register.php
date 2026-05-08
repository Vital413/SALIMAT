<?php
// doctor/register.php - Doctor Registration Page
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and gather inputs
    $firstName = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $lastName = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $specialization = trim(filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Basic Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            // Check if email already exists in doctors table
            $stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "A provider account with this email already exists.";
            } else {
                // Hash the password securely
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Set default specialization if empty
                $specialization = !empty($specialization) ? $specialization : 'Obstetrician/Gynecologist';

                // Insert new doctor into the database (is_active defaults to 0)
                $insertStmt = $pdo->prepare("
                    INSERT INTO doctors (first_name, last_name, email, phone, password_hash, specialization) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($insertStmt->execute([$firstName, $lastName, $email, $phone, $passwordHash, $specialization])) {
                    $success = "Registration successful! Your account is pending admin verification. Redirecting to login...";
                    // Redirect to login page after 3 seconds
                    header("refresh:3;url=login.php");
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error occurred. Please contact system admin.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Registration - LuminaCare</title>

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
            --text-dark: #17252a;
            --bg-light: #f8fafb;
            --doctor-theme: #1a4d6b; /* Slightly different blue tone for providers */
            --doctor-theme-light: #2c7da0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        h1, h2, h3, h4, h5, h6, .brand-logo {
            font-family: 'Poppins', sans-serif;
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
            background: linear-gradient(135deg, var(--doctor-theme) 0%, var(--doctor-theme-light) 100%);
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
            padding: 50px 60px;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #e1e5e8;
            background-color: #fcfcfc;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(44, 125, 160, 0.2);
            border-color: var(--doctor-theme-light);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .btn-primary-custom {
            background-color: var(--doctor-theme);
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-primary-custom:hover {
            background-color: var(--doctor-theme-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(26, 77, 107, 0.2);
        }

        .form-text {
            font-size: 0.8rem;
            color: #8a99a0;
        }

        @media (max-width: 768px) {
            .auth-sidebar {
                padding: 40px 30px;
                text-align: center;
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
                    <h2 class="mt-5 fw-bold">Join the Provider Network</h2>
                    <p class="opacity-75 mt-3 fs-5">Empower your practice with remote maternal health monitoring. Stay connected with your patients and receive critical alerts in real-time.</p>
                </div>
                
                <div class="mt-5 pt-5 border-top border-light border-opacity-25 z-1">
                    <p class="mb-2">Already a verified provider?</p>
                    <a href="login.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">Log In Here</a>
                </div>
            </div>

            <!-- Right Side (Form) -->
            <div class="col-md-7 auth-form-wrapper">
                <div class="mb-4">
                    <h3 class="fw-bold text-dark">Provider Enrollment</h3>
                    <p class="text-muted">Register your medical credentials. <br><span class="text-primary small fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Admin verification required before access.</span></p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-3">
                        <i class="bi bi-shield-check me-2"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" novalidate>
                    <div class="row g-3">
                        <!-- First Name -->
                        <div class="col-sm-6">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required 
                                value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                        
                        <!-- Last Name -->
                        <div class="col-sm-6">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>

                        <!-- Email Address -->
                        <div class="col-12">
                            <label for="email" class="form-label">Professional Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <!-- Phone Number -->
                        <div class="col-sm-6">
                            <label for="phone" class="form-label">Office Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g., +1 234 567 8900"
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>

                        <!-- Specialization -->
                        <div class="col-sm-6">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" placeholder="e.g., OB/GYN"
                                value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                        </div>

                        <!-- Password -->
                        <div class="col-sm-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Must be at least 8 characters.</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-sm-6">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="mt-4 pt-2">
                        <button type="submit" class="btn btn-primary-custom fs-5">Submit Application</button>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted">By applying, you agree to our <a href="#" class="text-decoration-none">Provider Terms of Service</a> and <a href="#" class="text-decoration-none">HIPAA Guidelines</a>.</small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>