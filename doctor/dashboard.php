<?php
// doctor/dashboard.php - Doctor Main Dashboard
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a doctor
if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];

try {
    // 1. Fetch Total Assigned Patients
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetchColumn();

    // 2. Fetch Unread Messages
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
    $stmt->execute([$doctor_id]);
    $unread_messages = $stmt->fetchColumn();

    // 3. Fetch Active Alerts (if we had automated triggers populating the alerts table)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE doctor_id = ? AND is_resolved = 0");
    $stmt->execute([$doctor_id]);
    $active_alerts = $stmt->fetchColumn();

    // 4. Fetch Recent Patient Vitals
    $stmt = $pdo->prepare("
        SELECT v.*, p.first_name, p.last_name, p.patient_id 
        FROM vitals v 
        JOIN patients p ON v.patient_id = p.patient_id 
        WHERE p.doctor_id = ? 
        ORDER BY v.recorded_at DESC LIMIT 8
    ");
    $stmt->execute([$doctor_id]);
    $recent_vitals = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading dashboard data.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Dashboard - LuminaCare</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Using the professional blue theme for the Doctor module */
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

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background-color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
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

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: var(--text-dark);
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

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Dashboard Cards */
        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
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
        }

        .metric-content {
            flex-grow: 1;
        }

        .metric-title {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
            line-height: 1;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .status-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #d39e00;
        }

        .status-normal {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        /* Mobile Toggler */
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
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            </div>
            <div class="nav-item">
                <a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> My Patients</a>
            </div>
            <div class="nav-item">
                <a href="messages.php" class="nav-link">
                    <i class="bi bi-chat-dots-fill"></i> Messages
                    <?php if ($unread_messages > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check-fill"></i> Appointments</a>
            </div>
            <div class="nav-item">
                <a href="profile.php" class="nav-link"><i class="bi bi-gear-fill"></i> Profile Settings</a>
            </div>
        </div>

        <div class="logout-wrapper">
            <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">Welcome, Dr. <?php echo htmlspecialchars($_SESSION['last_name']); ?></h4>
                    <p class="text-muted mb-0 small">Provider Dashboard Overview</p>
                </div>
            </div>

            <div class="user-profile">
                <span class="d-none d-md-inline text-muted fw-bold">Active Provider</span>
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- Error Alert -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Row -->
        <div class="row g-4 mb-4">
            <!-- Total Patients -->
            <div class="col-xl-4 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(26, 77, 107, 0.1); color: var(--primary-color);">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Assigned Patients</div>
                        <div class="metric-value"><?php echo $total_patients; ?></div>
                    </div>
                </div>
            </div>

            <!-- Unread Messages -->
            <div class="col-xl-4 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(44, 125, 160, 0.1); color: var(--secondary-color);">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Unread Messages</div>
                        <div class="metric-value"><?php echo $unread_messages; ?></div>
                    </div>
                </div>
            </div>

            <!-- Active Alerts -->
            <div class="col-xl-4 col-md-12">
                <div class="metric-card" style="<?php echo $active_alerts > 0 ? 'border-left: 4px solid #dc3545;' : ''; ?>">
                    <div class="metric-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Needs Attention (Alerts)</div>
                        <div class="metric-value text-danger"><?php echo $active_alerts; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Patient Vitals Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Patient Vitals</h5>
                <a href="patients.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">View All Patients</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Patient Name</th>
                            <th>Logged At</th>
                            <th>Blood Pressure</th>
                            <th>Heart Rate</th>
                            <th>Symptoms/Notes</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_vitals) > 0): ?>
                            <?php foreach ($recent_vitals as $vital): ?>
                                <?php
                                // Determine if BP is elevated to highlight it for the doctor
                                $isElevated = ($vital['systolic_bp'] > 130 || $vital['diastolic_bp'] > 85);
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">
                                        <?php echo htmlspecialchars($vital['first_name'] . ' ' . $vital['last_name']); ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date('M d, h:i A', strtotime($vital['recorded_at'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $isElevated ? 'bg-danger' : 'bg-light text-dark border'; ?>">
                                            <?php echo $vital['systolic_bp'] . '/' . $vital['diastolic_bp']; ?> mmHg
                                        </span>
                                    </td>
                                    <td><?php echo $vital['heart_rate'] ? $vital['heart_rate'] . ' bpm' : '-'; ?></td>
                                    <td>
                                        <?php if (!empty($vital['symptoms_notes'])): ?>
                                            <span class="text-truncate d-inline-block" style="max-width: 150px; font-size: 0.85rem;" title="<?php echo htmlspecialchars($vital['symptoms_notes']); ?>">
                                                <?php echo htmlspecialchars($vital['symptoms_notes']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="patient_details.php?id=<?php echo $vital['patient_id']; ?>" class="btn btn-sm btn-light border rounded-pill">View Chart</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-clipboard-x fs-2 d-block mb-2"></i>
                                    No vitals logged by your assigned patients recently.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Bootstrap 5 JS Bundle & Custom Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle Logic for Mobile
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openSidebar');
        const closeBtn = document.getElementById('closeSidebar');

        openBtn.addEventListener('click', () => {
            sidebar.classList.add('show');
        });

        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('show');
        });
    </script>
</body>

</html>