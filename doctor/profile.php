<?php
// doctor/profile.php - Edit Doctor Profile Settings
require_once '../config/config.php';

if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $specialization = filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("UPDATE doctors SET phone = ?, specialization = ? WHERE doctor_id = ?");
        if ($stmt->execute([$phone, $specialization, $doctor_id])) {
            $success_msg = "Profile updated successfully.";
        } else {
            $error_msg = "Failed to update profile.";
        }
    } catch (PDOException $e) {
        $error_msg = "Database error occurred.";
    }
}

// Fetch current data
try {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();

    // Sidebar unread messages count
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
    $msgStmt->execute([$doctor_id]);
    $unread_messages = $msgStmt->fetchColumn();
} catch (PDOException $e) {
    $error_msg = "Error loading profile data.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - LuminaCare Provider</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Shared Sidebar CSS - Provider Theme */
        :root {
            --primary-color: #1a4d6b;
            --secondary-color: #2c7da0;
            --accent-color: #ff9a9e;
            --text-dark: #17252a;
            --bg-light: #f4f7f6;
            --sidebar-width: 250px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            overflow-x: hidden;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Poppins', sans-serif;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        .sidebar-brand {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            text-decoration: none;
            border-bottom: 1px solid #eee;
        }

        .sidebar-brand span {
            color: var(--accent-color);
        }

        .nav-menu {
            padding: 20px 0;
            flex-grow: 1;
        }

        .nav-link {
            color: var(--text-dark);
            padding: 12px 25px;
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }

        .nav-link i {
            margin-right: 15px;
            font-size: 1.2rem;
            color: var(--secondary-color);
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(26, 77, 107, 0.08);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }

        .logout-wrapper {
            padding: 20px;
            border-top: 1px solid #eee;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: all 0.3s;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-heart-pulse-fill me-2"></i>Lumina<span>Care</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
            <div class="nav-item"><a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> My Patients</a></div>
            <div class="nav-item">
                <a href="messages.php" class="nav-link">
                    <i class="bi bi-chat-dots-fill"></i> Messages
                    <?php if (isset($unread_messages) && $unread_messages > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check-fill"></i> Appointments</a></div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link active"><i class="bi bi-person-gear-fill"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4 gap-3">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold">Provider Profile Settings</h3>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <form action="profile.php" method="POST">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-4">
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--secondary-color); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold;">
                                <?php echo strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Verified Provider</span>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 text-muted">Professional Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">First Name <i class="bi bi-lock-fill ms-1" title="Contact admin to change"></i></label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($doctor['first_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Last Name <i class="bi bi-lock-fill ms-1" title="Contact admin to change"></i></label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($doctor['last_name']); ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold">Professional Email <i class="bi bi-lock-fill ms-1" title="Contact admin to change"></i></label>
                                <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($doctor['email']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Office Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Specialization</label>
                                <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>" placeholder="e.g., OB/GYN">
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary rounded-pill mt-4 px-4 fw-bold">Save Changes</button>
                    </form>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-light">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i> Account Security</h6>
                    <p class="small text-muted mb-4">Your account is secured and verified by LuminaCare administration. To change your core identity details, email address, or password, please contact the system administrator directly.</p>
                    <a href="messages.php" class="btn btn-outline-primary rounded-pill w-100 fw-bold">Contact Support</a>
                </div>
            </div>
        </div>
    </main>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
</body>

</html>