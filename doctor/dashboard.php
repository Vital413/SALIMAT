<?php
// doctor/dashboard.php - Doctor Main Dashboard & Clinical Control Center
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a doctor
if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$success_msg = '';
$error_msg = '';

// --- Form Handlers for New Integrated Features ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Issue Clinical Order to Nurses
    if (isset($_POST['issue_order'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $order_text = trim(filter_input(INPUT_POST, 'order_text', FILTER_SANITIZE_STRING));

        if ($pat_id && !empty($order_text)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO doctor_orders (doctor_id, patient_id, order_text, status) VALUES (?, ?, ?, 'Pending')");
                if ($stmt->execute([$doctor_id, $pat_id, $order_text])) {
                    $success_msg = "Instruction successfully sent to the nursing station.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to send clinical order.";
            }
        }
    }

    // 2. Request Laboratory Test
    if (isset($_POST['request_lab'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $test_name = trim(filter_input(INPUT_POST, 'test_name', FILTER_SANITIZE_STRING));
        $test_desc = trim(filter_input(INPUT_POST, 'test_description', FILTER_SANITIZE_STRING));

        if ($pat_id && !empty($test_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO lab_tests (patient_id, doctor_id, test_name, test_description, status) VALUES (?, ?, ?, ?, 'Pending')");
                if ($stmt->execute([$pat_id, $doctor_id, $test_name, $test_desc])) {
                    $success_msg = "Lab request submitted to the diagnostic center.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to submit lab request.";
            }
        }
    }

    // 3. Resolve Alert
    if (isset($_POST['resolve_alert'])) {
        $alert_id = filter_input(INPUT_POST, 'alert_id', FILTER_VALIDATE_INT);
        if ($alert_id) {
            try {
                $stmt = $pdo->prepare("UPDATE alerts SET is_resolved = 1 WHERE alert_id = ? AND doctor_id = ?");
                $stmt->execute([$alert_id, $doctor_id]);
                $success_msg = "Health alert marked as reviewed/resolved.";
            } catch (PDOException $e) {
                $error_msg = "Action failed.";
            }
        }
    }
}

// --- Fetch Dashboard Data ---
try {
    // 1. Core Stats
    $total_patients = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE doctor_id = ?");
    $total_patients->execute([$doctor_id]);
    $pat_count = $total_patients->fetchColumn();

    $unread_msg = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
    $unread_msg->execute([$doctor_id]);
    $msg_count = $unread_msg->fetchColumn();

    $active_alrt = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE doctor_id = ? AND is_resolved = 0");
    $active_alrt->execute([$doctor_id]);
    $alert_count = $active_alrt->fetchColumn();

    $pending_lb = $pdo->prepare("SELECT COUNT(*) FROM lab_tests WHERE doctor_id = ? AND status != 'Completed'");
    $pending_lb->execute([$doctor_id]);
    $lab_count = $pending_lb->fetchColumn();

    // 2. Recent Patient Vitals (The Triage Queue)
    $stmt = $pdo->prepare("
        SELECT v.*, p.first_name, p.last_name, p.patient_id 
        FROM vitals v 
        JOIN patients p ON v.patient_id = p.patient_id 
        WHERE p.doctor_id = ? 
        ORDER BY v.recorded_at DESC LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $recent_vitals = $stmt->fetchAll();

    // 3. Active Alerts to Review
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.last_name 
        FROM alerts a
        JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.doctor_id = ? AND a.is_resolved = 0
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $current_alerts = $stmt->fetchAll();

    // 4. Patient List for Modals
    $stmt = $pdo->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE doctor_id = ? ORDER BY last_name ASC");
    $stmt->execute([$doctor_id]);
    $my_patients = $stmt->fetchAll();
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
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

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: all 0.3s;
        }

        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid rgba(0, 0, 0, 0.02);
            height: 100%;
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
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

    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand">Lumina<span>Care</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> My Patients</a>
            <a href="messages.php" class="nav-link"><i class="bi bi-chat-dots-fill"></i> Messages</a>
            <a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check-fill"></i> Appointments</a>
            <div class="mt-4 px-4 small text-muted text-uppercase fw-bold">Clinical Logs</div>
            <a href="records.php" class="nav-link"><i class="bi bi-folder-check"></i> EMR Directory</a>
        </div>
        <div class="mt-auto p-3 border-top"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold">Clinical Center</h4>
                    <p class="text-muted mb-0 small">Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['last_name']); ?></p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary rounded-pill fw-bold px-3 d-none d-md-block" data-bs-toggle="modal" data-bs-target="#orderModal"><i class="bi bi-clipboard-plus me-1"></i> Nurse Order</button>
                <button class="btn btn-outline-primary rounded-pill fw-bold px-3 d-none d-md-block" data-bs-toggle="modal" data-bs-target="#labModal"><i class="bi bi-microscope me-1"></i> Request Lab</button>
            </div>
        </header>

        <?php if ($success_msg): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">My Patients</small>
                        <h3 class="mb-0 fw-bold"><?php echo $pat_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-bell-fill"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Active Alerts</small>
                        <h3 class="mb-0 fw-bold <?php echo $alert_count > 0 ? 'text-danger' : ''; ?>"><?php echo $alert_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-microscope"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Pending Labs</small>
                        <h3 class="mb-0 fw-bold"><?php echo $lab_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-chat-dots"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">New Messages</small>
                        <h3 class="mb-0 fw-bold"><?php echo $msg_count; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Vitals Feed -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom py-3 fw-bold"><i class="bi bi-activity text-primary me-2"></i> Recent Vitals Feed</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Patient</th>
                                    <th>Logged At</th>
                                    <th>BP Reading</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_vitals as $v): ?>
                                    <?php $isHigh = ($v['systolic_bp'] >= 140 || $v['diastolic_bp'] >= 90); ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($v['first_name'] . ' ' . $v['last_name']); ?></div>
                                        </td>
                                        <td class="small text-muted"><?php echo date('M d, g:i A', strtotime($v['recorded_at'])); ?></td>
                                        <td><span class="badge <?php echo $isHigh ? 'bg-danger' : 'bg-light text-dark border'; ?> fs-6"><?php echo $v['systolic_bp']; ?>/<?php echo $v['diastolic_bp']; ?></span></td>
                                        <td><?php echo $isHigh ? '<span class="text-danger fw-bold small">CRITICAL</span>' : '<span class="text-success small">Normal</span>'; ?></td>
                                        <td class="text-end pe-4"><a href="patient_details.php?id=<?php echo $v['patient_id']; ?>" class="btn btn-sm btn-light border rounded-pill">View Chart</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Health Alerts Panel -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom py-3 fw-bold"><i class="bi bi-exclamation-triangle text-danger me-2"></i> Active Alerts</div>
                    <div class="card-body bg-light">
                        <?php if ($current_alerts): ?>
                            <?php foreach ($current_alerts as $a): ?>
                                <div class="bg-white p-3 rounded-3 border-start border-danger border-4 shadow-sm mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="fw-bold text-dark"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></small>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($a['created_at'])); ?></small>
                                    </div>
                                    <p class="small text-muted mb-2"><?php echo htmlspecialchars($a['alert_message']); ?></p>
                                    <form method="POST" action="" class="text-end">
                                        <input type="hidden" name="alert_id" value="<?php echo $a['alert_id']; ?>">
                                        <button type="submit" name="resolve_alert" class="btn btn-sm btn-outline-success rounded-pill px-3" style="font-size: 0.7rem;">Mark as Reviewed</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted opacity-50"><i class="bi bi-shield-check fs-1 d-block"></i>
                                <p class="small">No unreviewed alerts.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Nurse Order Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">New Nursing Instruction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Select Patient</label>
                            <select name="patient_id" class="form-select bg-light border-0" required>
                                <?php foreach ($my_patients as $p): ?>
                                    <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Instruction / Clinical Order</label>
                            <textarea name="order_text" class="form-control bg-light border-0" rows="3" placeholder="e.g. Administer 500mg Paracetamol, Setup immediate checkup..." required></textarea>
                        </div>
                        <button type="submit" name="issue_order" class="btn btn-primary w-100 rounded-pill fw-bold py-2">Dispatch Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Lab Request Modal -->
    <div class="modal fade" id="labModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">New Diagnostic Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Select Patient</label>
                            <select name="patient_id" class="form-select bg-light border-0" required>
                                <?php foreach ($my_patients as $p): ?>
                                    <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Test Name</label>
                            <input type="text" name="test_name" class="form-control bg-light border-0" placeholder="e.g. CBC, Urinalysis, Blood Sugar" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Notes for Technician</label>
                            <textarea name="test_description" class="form-control bg-light border-0" rows="2"></textarea>
                        </div>
                        <button type="submit" name="request_lab" class="btn btn-outline-primary w-100 rounded-pill fw-bold py-2">Submit Lab Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
</body>

</html>