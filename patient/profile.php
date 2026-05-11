<?php
// patient/profile.php - Edit Patient Profile Settings & Pregnancy Cycle
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$success_msg = '';
$error_msg = '';

// Handle Personal Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $dob = $_POST['date_of_birth'];
    $blood_type = filter_input(INPUT_POST, 'blood_type', FILTER_SANITIZE_STRING);
    $medical_history = filter_input(INPUT_POST, 'medical_history', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("UPDATE patients SET phone = ?, date_of_birth = ?, blood_type = ?, medical_history = ? WHERE patient_id = ?");
        if ($stmt->execute([$phone, $dob, $blood_type, $medical_history, $patient_id])) {
            $success_msg = "Personal profile updated successfully.";
        } else {
            $error_msg = "Failed to update profile.";
        }
    } catch (PDOException $e) {
        $error_msg = "Database error occurred.";
    }
}

// Handle New Pregnancy Journey Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_new_pregnancy'])) {
    $new_due_date = trim($_POST['expected_due_date']);

    if (!empty($new_due_date)) {
        try {
            $stmt = $pdo->prepare("UPDATE patients SET expected_due_date = ? WHERE patient_id = ?");
            if ($stmt->execute([$new_due_date, $patient_id])) {
                $success_msg = "New pregnancy journey started! Your dashboard timeline has been successfully reset to focus on your current cycle.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to start new pregnancy journey.";
        }
    } else {
        $error_msg = "Please provide a valid Expected Due Date.";
    }
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Shared Sidebar CSS */
        :root {
            --primary-color: #2b7a78;
            --secondary-color: #3aafa9;
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
            background-color: rgba(58, 175, 169, 0.08);
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
            <div class="nav-item"><a href="messages.php" class="nav-link"><i class="bi bi-chat-dots"></i> Messages</a></div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i> Appointments</a></div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link active"><i class="bi bi-person-gear-fill"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4 gap-3">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold">Account & Medical Profile</h3>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4">
            <!-- Personal Profile Card -->
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                    <form action="profile.php" method="POST">
                        <h5 class="fw-bold mb-4"><i class="bi bi-person-lines-fill text-primary me-2"></i> Personal & Medical Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">First Name <i class="bi bi-lock-fill ms-1" title="Locked Field"></i></label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($patient['first_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Last Name <i class="bi bi-lock-fill ms-1" title="Locked Field"></i></label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($patient['last_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Blood Type</label>
                                <select name="blood_type" class="form-select">
                                    <option value="">Select...</option>
                                    <?php $types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                    foreach ($types as $type) {
                                        $selected = ($patient['blood_type'] == $type) ? 'selected' : '';
                                        echo "<option value=\"$type\" $selected>$type</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label text-muted small fw-bold">Brief Medical History / Known Allergies</label>
                                <textarea name="medical_history" class="form-control" rows="4" placeholder="List any chronic conditions or allergies here..."><?php echo htmlspecialchars($patient['medical_history'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary rounded-pill mt-4 px-4 fw-bold">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Pregnancy Cycle Management Card -->
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #fef6f7 100%);">
                    <h5 class="fw-bold mb-3" style="color: var(--accent-color);"><i class="bi bi-arrow-repeat me-2"></i> Pregnancy Cycle</h5>
                    <p class="small text-muted mb-4">
                        Registering a new pregnancy or updating your due date will automatically <strong>reset your active dashboard timeline</strong>.
                        Don't worry, your past vitals and records are safely archived in your full medical history.
                    </p>

                    <div class="p-3 bg-white rounded-3 border mb-4 text-center">
                        <small class="text-uppercase fw-bold text-muted d-block mb-1">Current Expected Due Date</small>
                        <h4 class="mb-0 fw-bold text-dark">
                            <?php echo $patient['expected_due_date'] ? date('F j, Y', strtotime($patient['expected_due_date'])) : 'Not Set'; ?>
                        </h4>
                    </div>

                    <form action="profile.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Enter New Due Date *</label>
                            <input type="date" name="expected_due_date" class="form-control border-danger border-opacity-25" required>
                        </div>
                        <button type="submit" name="start_new_pregnancy" class="btn btn-danger w-100 rounded-pill fw-bold shadow-sm" onclick="return confirm('Are you sure you want to reset your pregnancy timeline to a new cycle?');">
                            <i class="bi bi-stars me-1"></i> Start New Journey
                        </button>
                    </form>
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