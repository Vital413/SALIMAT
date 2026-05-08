<?php
// admin/logs.php - Audit and System Logs Tracker
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error_msg = '';

// Generate pseudo-logs based on database timestamps to simulate system activity
$logs = [];

try {
    // 1. Fetch Patient Registrations
    $stmt1 = $pdo->query("SELECT created_at as log_time, CONCAT('New Patient Registered: ', first_name, ' ', last_name) as log_desc, 'Registration' as type FROM patients");
    while ($row = $stmt1->fetch()) { $logs[] = $row; }

    // 2. Fetch Provider Registrations
    $stmt2 = $pdo->query("SELECT created_at as log_time, CONCAT('Provider Applied: Dr. ', last_name) as log_desc, 'System' as type FROM doctors");
    while ($row = $stmt2->fetch()) { $logs[] = $row; }

    // 3. Fetch Vitals Logged
    $stmt3 = $pdo->query("SELECT recorded_at as log_time, CONCAT('Vitals logged by Patient #', patient_id) as log_desc, 'Activity' as type FROM vitals");
    while ($row = $stmt3->fetch()) { $logs[] = $row; }

    // Sort logs descending by time
    usort($logs, function($a, $b) {
        return strtotime($b['log_time']) - strtotime($a['log_time']);
    });

    // Limit to latest 50 logs for performance
    $logs = array_slice($logs, 0, 50);

} catch (PDOException $e) {
    $error_msg = "Failed to compile system logs.";
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
        :root { --primary-color: #111827; --accent-color: #ff9a9e; --bg-light: #f3f4f6; --sidebar-width: 250px; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        
        .sidebar { width: var(--sidebar-width); background-color: var(--primary-color); height: 100vh; position: fixed; top: 0; left: 0; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1000; transition: all 0.3s; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 20px; font-size: 1.5rem; font-weight: 800; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand span { color: var(--accent-color); }
        .nav-menu { padding: 20px 0; flex-grow: 1; }
        .nav-link { color: #d1d5db; padding: 12px 25px; display: flex; align-items: center; font-weight: 500; transition: all 0.3s; border-left: 4px solid transparent; text-decoration: none; }
        .nav-link i { margin-right: 15px; font-size: 1.2rem; color: #9ca3af; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.05); color: white; border-left: 4px solid var(--accent-color); }
        .logout-wrapper { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .main-content { margin-left: var(--sidebar-width); padding: 30px; transition: all 0.3s; }
        .mobile-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--primary-color); }
        @media (max-width: 991px) { .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } .main-content { margin-left: 0; padding: 15px; } .mobile-toggle { display: block; } }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-shield-lock-fill me-2"></i>Admin<span>Panel</span></a>
            <button class="btn-close btn-close-white d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> System Overview</a></div>
            <div class="nav-item"><a href="patients.php" class="nav-link"><i class="bi bi-people"></i> Manage Patients</a></div>
            <div class="nav-item"><a href="providers.php" class="nav-link"><i class="bi bi-hospital"></i> Manage Providers</a></div>
            <div class="nav-item"><a href="logs.php" class="nav-link active"><i class="bi bi-database-check"></i> System Logs</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-light w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Secure Logout</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center gap-3 mb-4">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold text-dark">System Audit Logs</h3>
        </div>

        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-muted">Latest 50 System Events</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Timestamp</th>
                            <th>Event Category</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark" style="font-family: monospace; font-size: 0.9rem;">
                                <?php echo date('Y-m-d H:i:s', strtotime($log['log_time'])); ?>
                            </td>
                            <td>
                                <?php 
                                    if ($log['type'] == 'Registration') echo '<span class="badge bg-primary">Registration</span>';
                                    elseif ($log['type'] == 'Activity') echo '<span class="badge bg-success">Activity</span>';
                                    else echo '<span class="badge bg-secondary">System</span>';
                                ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($log['log_desc']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="3" class="text-center py-4">No system logs available.</td></tr>
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