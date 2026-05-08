<?php
// admin/dashboard.php - System Administrator Dashboard
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Admin Actions (Verify Doctor / Assign Patient)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Action 1: Verify a Doctor
    if (isset($_POST['verify_doctor'])) {
        $doc_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        if ($doc_id) {
            try {
                $stmt = $pdo->prepare("UPDATE doctors SET is_active = 1 WHERE doctor_id = ?");
                if ($stmt->execute([$doc_id])) {
                    $success_msg = "Provider successfully verified and activated.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to verify provider.";
            }
        }
    }

    // Action 2: Assign Doctor to Patient
    if (isset($_POST['assign_doctor'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $doc_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);

        if ($pat_id && $doc_id) {
            try {
                $stmt = $pdo->prepare("UPDATE patients SET doctor_id = ? WHERE patient_id = ?");
                if ($stmt->execute([$doc_id, $pat_id])) {
                    $success_msg = "Patient successfully assigned to the selected provider.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to assign provider.";
            }
        }
    }
}

try {
    // Fetch Quick Stats
    $stats = [
        'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
        'doctors' => $pdo->query("SELECT COUNT(*) FROM doctors WHERE is_active = 1")->fetchColumn(),
        'pending_docs' => $pdo->query("SELECT COUNT(*) FROM doctors WHERE is_active = 0")->fetchColumn()
    ];

    // Fetch Pending Doctors
    $pending_doctors = $pdo->query("SELECT * FROM doctors WHERE is_active = 0 ORDER BY created_at ASC")->fetchAll();

    // Fetch Unassigned Patients
    $unassigned_patients = $pdo->query("SELECT * FROM patients WHERE doctor_id IS NULL ORDER BY created_at ASC")->fetchAll();

    // Fetch Active Doctors for the dropdown menu
    $active_doctors = $pdo->query("SELECT doctor_id, first_name, last_name, specialization FROM doctors WHERE is_active = 1 ORDER BY last_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading dashboard data.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LuminaCare</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #111827;
            /* Slate dark */
            --secondary-color: #374151;
            --accent-color: #ff9a9e;
            --text-dark: #17252a;
            --bg-light: #f3f4f6;
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

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand span {
            color: var(--accent-color);
        }

        .nav-menu {
            padding: 20px 0;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: #d1d5db;
            padding: 12px 25px;
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            text-decoration: none;
        }

        .nav-link i {
            margin-right: 15px;
            font-size: 1.2rem;
            color: #9ca3af;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.05);
            color: white;
            border-left: 4px solid var(--accent-color);
        }

        .logout-wrapper {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content Styling */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: all 0.3s;
        }

        /* Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        /* Dashboard Cards */
        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            background: rgba(17, 24, 39, 0.1);
            color: var(--primary-color);
        }

        .metric-content {
            flex-grow: 1;
        }

        .metric-title {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
            line-height: 1;
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
            <a href="#" class="sidebar-brand"><i class="bi bi-shield-lock-fill me-2"></i>Admin<span>Panel</span></a>
            <button class="btn-close btn-close-white d-lg-none" id="closeSidebar"></button>
        </div>

        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> System Overview</a></div>
            <div class="nav-item"><a href="patients.php" class="nav-link"><i class="bi bi-people"></i> Manage Patients</a></div>
            <div class="nav-item"><a href="providers.php" class="nav-link"><i class="bi bi-hospital"></i> Manage Providers</a></div>
            <div class="nav-item"><a href="logs.php" class="nav-link"><i class="bi bi-database-check"></i> System Logs</a></div>
        </div>

        <div class="logout-wrapper">
            <a href="logout.php" class="btn btn-outline-light w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Secure Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">System Overview</h4>
                    <p class="text-muted mb-0 small">Welcome back, Super Admin.</p>
                </div>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Systems Operational</span>
        </header>

        <!-- Error/Success Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Quick Stats Row -->
        <div class="row g-4 mb-5">
            <div class="col-xl-4 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-people-fill"></i></div>
                    <div class="metric-content">
                        <div class="metric-title">Total Registered Patients</div>
                        <div class="metric-value"><?php echo $stats['patients']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(43, 122, 120, 0.1); color: #2b7a78;"><i class="bi bi-person-badge-fill"></i></div>
                    <div class="metric-content">
                        <div class="metric-title">Active Providers</div>
                        <div class="metric-value"><?php echo $stats['doctors']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12">
                <div class="metric-card" style="<?php echo $stats['pending_docs'] > 0 ? 'border-left: 4px solid #f59e0b;' : ''; ?>">
                    <div class="metric-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="bi bi-clock-history"></i></div>
                    <div class="metric-content">
                        <div class="metric-title">Pending Verifications</div>
                        <div class="metric-value text-warning"><?php echo $stats['pending_docs']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Pending Doctors Table -->
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation text-warning me-2"></i> Pending Provider Verifications</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-4">Provider Info</th>
                                    <th>Specialization</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($pending_doctors) > 0): ?>
                                    <?php foreach ($pending_doctors as $doc): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark">Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($doc['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($doc['specialization']); ?></td>
                                            <td class="text-end pe-4">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                                    <button type="submit" name="verify_doctor" class="btn btn-sm btn-success rounded-pill fw-bold" onclick="return confirm('Approve and activate this provider?');">Verify & Activate</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No pending verifications.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Unassigned Patients Table -->
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus text-primary me-2"></i> Needs Provider Assignment</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-4">Patient Name</th>
                                    <th>Assign To Provider</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($unassigned_patients) > 0): ?>
                                    <?php foreach ($unassigned_patients as $pat): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']); ?></div>
                                                <small class="text-muted">Registered: <?php echo date('M d, Y', strtotime($pat['created_at'])); ?></small>
                                            </td>
                                            <td class="pe-4">
                                                <form method="POST" action="" class="d-flex gap-2">
                                                    <input type="hidden" name="patient_id" value="<?php echo $pat['patient_id']; ?>">
                                                    <select name="doctor_id" class="form-select form-select-sm" required>
                                                        <option value="">-- Select Provider --</option>
                                                        <?php foreach ($active_doctors as $doc): ?>
                                                            <option value="<?php echo $doc['doctor_id']; ?>">Dr. <?php echo htmlspecialchars($doc['last_name']); ?> (<?php echo htmlspecialchars($doc['specialization']); ?>)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="assign_doctor" class="btn btn-sm btn-primary">Assign</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4 text-muted">All patients currently have an assigned provider.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
</body>

</html>