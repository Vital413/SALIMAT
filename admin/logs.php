<?php
// admin/logs.php - Comprehensive Audit and System Logs Tracker
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error_msg = '';
$logs = [];

// Helper function to safely fetch logs (ignores if a specific module table hasn't been created yet)
function fetchSystemLogs($pdo, $query, $category)
{
    $tempLogs = [];
    try {
        $stmt = $pdo->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['category'] = $category;
            $tempLogs[] = $row;
        }
    } catch (PDOException $e) {
        // Silently skip if table doesn't exist yet
    }
    return $tempLogs;
}

try {
    // 1. Fetch Registrations (Patients & Staff)
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('New Patient Registered: ', first_name, ' ', last_name) as log_desc FROM patients", 'Registration'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Provider Applied: Dr. ', last_name) as log_desc FROM doctors", 'Registration'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Nurse Registered: ', last_name) as log_desc FROM nurses", 'Registration'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Pharmacist Registered: ', last_name) as log_desc FROM pharmacists", 'Registration'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Lab Tech Registered: ', last_name) as log_desc FROM lab_techs", 'Registration'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Cashier Registered: ', last_name) as log_desc FROM cashiers", 'Registration'));

    // 2. Fetch Clinical Activities
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT recorded_at as log_time, CONCAT('Vitals logged for Patient #', patient_id) as log_desc FROM vitals", 'Clinical'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Lab Test Requested: ', test_name) as log_desc FROM lab_tests", 'Clinical'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Doctor Order created for Patient #', patient_id) as log_desc FROM doctor_orders", 'Clinical'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('New Prescription: ', medication_name) as log_desc FROM medications", 'Clinical'));

    // 3. Fetch Financial Activities
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Invoice generated for Patient #', patient_id, ' ($', amount, ')') as log_desc FROM billing", 'Financial'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Payment of $', amount, ' processed via ', payment_method) as log_desc FROM payments", 'Financial'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Hospital Expense Logged: $', amount) as log_desc FROM expenses", 'Financial'));

    // 4. Fetch General System Activities
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Appointment scheduled for Patient #', patient_id) as log_desc FROM appointments", 'Activity'));
    $logs = array_merge($logs, fetchSystemLogs($pdo, "SELECT created_at as log_time, CONCAT('Smart Alert Triggered: ', alert_type) as log_desc FROM alerts", 'System'));

    // Sort all logs descending by time
    usort($logs, function ($a, $b) {
        return strtotime($b['log_time']) - strtotime($a['log_time']);
    });

    // Limit to latest 100 logs to keep the page fast and responsive
    $logs = array_slice($logs, 0, 100);
} catch (Exception $e) {
    $error_msg = "Failed to compile comprehensive system logs.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #111827;
            --accent-color: #ff9a9e;
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
            flex-shrink: 0;
        }

        .sidebar-brand span {
            color: var(--accent-color);
        }

        .nav-menu {
            padding: 10px 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-menu::-webkit-scrollbar {
            width: 5px;
        }

        .nav-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .nav-item {
            margin-bottom: 2px;
        }

        .nav-link {
            color: #d1d5db;
            padding: 10px 25px;
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .nav-link i {
            margin-right: 15px;
            font-size: 1.1rem;
            color: #9ca3af;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.05);
            color: white;
            border-left: 4px solid var(--accent-color);
        }

        .nav-section-title {
            color: #6b7280;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 25px 5px;
        }

        .logout-wrapper {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
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

        .badge-Clinical {
            background-color: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
            border: 1px solid rgba(13, 202, 240, 0.2);
        }

        .badge-Financial {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.2);
        }

        .badge-Registration {
            background-color: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
            border: 1px solid rgba(111, 66, 193, 0.2);
        }

        .badge-System {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .badge-Activity {
            background-color: rgba(253, 126, 20, 0.1);
            color: #fd7e14;
            border: 1px solid rgba(253, 126, 20, 0.2);
        }
    </style>
</head>

<body>
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-shield-lock-fill me-2"></i>Admin<span>Panel</span></a>
            <button class="btn-close btn-close-white d-lg-none" id="closeSidebar"></button>
        </div>

        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Overview</a></div>

            <div class="nav-section-title">Users & Staff</div>
            <div class="nav-item"><a href="patients.php" class="nav-link"><i class="bi bi-people"></i> Manage Patients</a></div>
            <div class="nav-item"><a href="providers.php" class="nav-link"><i class="bi bi-hospital"></i> Manage Providers</a></div>
            <div class="nav-item"><a href="nurses.php" class="nav-link"><i class="bi bi-clipboard2-heart"></i> Manage Nurses</a></div>
            <div class="nav-item"><a href="lab_techs.php" class="nav-link"><i class="bi bi-droplet-half"></i> Manage Lab Techs</a></div>
            <div class="nav-item"><a href="pharmacists.php" class="nav-link"><i class="bi bi-capsule-pill"></i> Manage Pharmacists</a></div>
            <div class="nav-item"><a href="cashiers.php" class="nav-link"><i class="bi bi-cash-coin"></i> Manage Cashiers</a></div>

            <div class="nav-section-title">Clinical & Operations</div>
            <div class="nav-item"><a href="records.php" class="nav-link"><i class="bi bi-folder2-open"></i> Medical Records</a></div>
            <div class="nav-item"><a href="tests_results.php" class="nav-link"><i class="bi bi-file-medical"></i> Tests & Results</a></div>
            <div class="nav-item"><a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventory Management</a></div>
            <div class="nav-item"><a href="billing.php" class="nav-link"><i class="bi bi-receipt"></i> Billing & Finances</a></div>

            <div class="nav-section-title">System</div>
            <div class="nav-item"><a href="logs.php" class="nav-link active"><i class="bi bi-database-check"></i> System Logs</a></div>
        </div>

        <div class="logout-wrapper">
            <a href="logout.php" class="btn btn-outline-light w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Secure Logout</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h3 class="mb-0 fw-bold text-dark">System Audit Logs</h3>
                    <p class="text-muted mb-0 small">Real-time monitoring of all facility events, cross-department activities, and user registrations.</p>
                </div>
            </div>
            <button class="btn btn-outline-dark rounded-pill px-4 fw-bold shadow-sm" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh Logs
            </button>
        </div>

        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-activity text-primary me-2"></i> Latest 100 System Events</h6>
                <span class="badge bg-light text-dark border shadow-sm">Auto-sorted by Time</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Timestamp</th>
                            <th>Event Category</th>
                            <th>Description / Activity Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted" style="font-family: monospace; font-size: 0.85rem;">
                                    <?php echo date('M d, Y - h:i:s A', strtotime($log['log_time'])); ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($log['category']); ?> px-3 py-1 rounded-pill fw-semibold">
                                        <?php
                                        if ($log['category'] == 'Clinical') echo '<i class="bi bi-clipboard2-pulse me-1"></i> ';
                                        if ($log['category'] == 'Financial') echo '<i class="bi bi-cash-stack me-1"></i> ';
                                        if ($log['category'] == 'Registration') echo '<i class="bi bi-person-plus me-1"></i> ';
                                        if ($log['category'] == 'System') echo '<i class="bi bi-shield-exclamation me-1"></i> ';
                                        if ($log['category'] == 'Activity') echo '<i class="bi bi-calendar-event me-1"></i> ';
                                        echo htmlspecialchars($log['category']);
                                        ?>
                                    </span>
                                </td>
                                <td class="text-dark fw-medium" style="font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($log['log_desc']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-database-x fs-1 d-block mb-3 opacity-25"></i> No system logs available yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
</body>

</html>