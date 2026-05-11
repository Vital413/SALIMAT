<?php
// nurse/dashboard.php - Nurse Main Dashboard & Triage Center
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a nurse
if (!isset($_SESSION['nurse_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: login.php");
    exit();
}

$nurse_id = $_SESSION['nurse_id'];
$success_msg = '';
$error_msg = '';

// --- Auto-Setup: Doctor Orders Table ---
try {
    $checkOrders = $pdo->query("SHOW TABLES LIKE 'doctor_orders'")->rowCount();
    if ($checkOrders == 0) {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS `doctor_orders` (
          `order_id` int(11) NOT NULL AUTO_INCREMENT,
          `doctor_id` int(11) NOT NULL,
          `patient_id` int(11) NOT NULL,
          `order_text` text NOT NULL,
          `status` enum('Pending','Completed') DEFAULT 'Pending',
          `created_at` timestamp DEFAULT current_timestamp(),
          `completed_at` datetime DEFAULT NULL,
          `completed_by_nurse_id` int(11) DEFAULT NULL,
          PRIMARY KEY (`order_id`),
          FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE,
          FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
} catch (PDOException $e) {
    $error_msg = "Database setup error for clinical orders.";
}

// --- Form Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Handle marking a Doctor's Order as Completed & Optional Billing
    if (isset($_POST['complete_order'])) {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $order_fee = filter_input(INPUT_POST, 'order_fee', FILTER_VALIDATE_FLOAT);

        if ($order_id) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE doctor_orders SET status = 'Completed', completed_at = NOW(), completed_by_nurse_id = ? WHERE order_id = ?");
                if ($stmt->execute([$nurse_id, $order_id])) {

                    // Automated Billing if a fee is applied
                    if ($order_fee && $order_fee > 0 && $patient_id) {
                        $billStmt = $pdo->prepare("INSERT INTO billing (patient_id, description, amount, status, due_date) VALUES (?, 'Clinical Order Fulfillment (Nursing)', ?, 'Unpaid', CURDATE())");
                        $billStmt->execute([$patient_id, $order_fee]);
                    }

                    $pdo->commit();
                    $success_msg = "Order marked as completed." . ($order_fee > 0 ? " A service fee of $" . number_format($order_fee, 2) . " has been sent to the Cashier." : "");
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Failed to update order status or process billing.";
            }
        }
    }

    // 2. Handle Nurse Logging In-Person Vitals & Optional Consultation Fee
    if (isset($_POST['log_vitals'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $sys_bp = filter_input(INPUT_POST, 'systolic_bp', FILTER_VALIDATE_INT);
        $dia_bp = filter_input(INPUT_POST, 'diastolic_bp', FILTER_VALIDATE_INT);
        $hr = filter_input(INPUT_POST, 'heart_rate', FILTER_VALIDATE_INT);
        $weight = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
        $sugar = filter_input(INPUT_POST, 'blood_sugar', FILTER_VALIDATE_INT);
        $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));
        $service_fee = filter_input(INPUT_POST, 'service_fee', FILTER_VALIDATE_FLOAT);

        $notes = "[Logged by Nurse] " . $notes;

        if ($pat_id && $sys_bp && $dia_bp) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO vitals (patient_id, systolic_bp, diastolic_bp, heart_rate, weight_kg, blood_sugar_mgdl, symptoms_notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$pat_id, $sys_bp, $dia_bp, $hr, $weight, $sugar, $notes])) {

                    // Automated Billing if a consultation/service fee is added
                    if ($service_fee && $service_fee > 0) {
                        $billStmt = $pdo->prepare("INSERT INTO billing (patient_id, description, amount, status, due_date) VALUES (?, 'Nursing Assessment / Clinic Vitals', ?, 'Unpaid', CURDATE())");
                        $billStmt->execute([$pat_id, $service_fee]);
                    }

                    $pdo->commit();
                    $success_msg = "Clinical vitals logged successfully." . ($service_fee > 0 ? " A fee of $" . number_format($service_fee, 2) . " has been sent to the Cashier." : "");
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Failed to log vitals or process billing fee.";
            }
        } else {
            $error_msg = "Patient selection and Blood Pressure are required.";
        }
    }
}

// --- Fetch Dashboard Data ---
try {
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $vitals_today = $pdo->query("SELECT COUNT(*) FROM vitals WHERE DATE(recorded_at) = CURDATE()")->fetchColumn();
    $pending_orders_count = $pdo->query("SELECT COUNT(*) FROM doctor_orders WHERE status = 'Pending'")->fetchColumn();

    // Fetch Recent Patient Vitals (Triage Queue)
    $vitals_stmt = $pdo->query("
        SELECT v.*, p.first_name, p.last_name, p.patient_id, d.last_name AS doc_last
        FROM vitals v 
        JOIN patients p ON v.patient_id = p.patient_id 
        LEFT JOIN doctors d ON p.doctor_id = d.doctor_id
        ORDER BY v.recorded_at DESC LIMIT 6
    ");
    $recent_vitals = $vitals_stmt->fetchAll();

    // Fetch Pending Doctor Orders
    $orders_stmt = $pdo->query("
        SELECT o.*, d.last_name AS doc_last, p.first_name AS pat_first, p.last_name AS pat_last 
        FROM doctor_orders o
        JOIN doctors d ON o.doctor_id = d.doctor_id
        JOIN patients p ON o.patient_id = p.patient_id
        WHERE o.status = 'Pending'
        ORDER BY o.created_at ASC LIMIT 5
    ");
    $pending_orders = $orders_stmt->fetchAll();

    // Fetch Patients for Vitals Modal
    $patientsList = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading dashboard data.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Dashboard - LuminaCare</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Using the dedicated Teal/Cyan theme for the Nurse module */
            --primary-color: #05828e;
            --secondary-color: #0dcaf0;
            --accent-color: #ff9a9e;
            --text-dark: #17252a;
            --bg-light: #f8fafb;
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
            background-color: rgba(5, 130, 142, 0.08);
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

        .order-card {
            border-left: 4px solid #f59e0b;
            background: #fffcf2;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
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
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            </div>
            <div class="nav-item">
                <a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> Patient Directory</a>
            </div>
            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="bi bi-clipboard-check-fill"></i> Doctor's Orders
                    <?php if ($pending_orders_count > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-auto"><?php echo $pending_orders_count; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-heart-pulse-fill"></i> Log In-Person Vitals</a>
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
                    <h4 class="mb-0 fw-bold text-dark">Welcome, Nurse <?php echo htmlspecialchars($_SESSION['last_name']); ?></h4>
                    <p class="text-muted mb-0 small">Nursing Station Overview</p>
                </div>
            </div>

            <div class="user-profile">
                <button class="btn btn-primary rounded-pill fw-bold shadow-sm d-none d-md-block me-3" data-bs-toggle="modal" data-bs-target="#logVitalsModal">
                    <i class="bi bi-plus-circle me-1"></i> Log Vitals
                </button>
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Quick Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(5, 130, 142, 0.1); color: var(--primary-color);"><i class="bi bi-people"></i></div>
                    <div class="metric-content">
                        <div class="metric-title">Clinic Patients</div>
                        <div class="metric-value"><?php echo $total_patients; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(13, 202, 240, 0.1); color: var(--secondary-color);"><i class="bi bi-clipboard2-pulse"></i></div>
                    <div class="metric-content">
                        <div class="metric-title">Vitals Logged Today</div>
                        <div class="metric-value"><?php echo $vitals_today; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12">
                <div class="metric-card" style="<?php echo $pending_orders_count > 0 ? 'border-left: 4px solid #f59e0b;' : ''; ?>">
                    <div class="metric-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="bi bi-clipboard-check"></i></div>
                    <div class="metric-content">
                        <div class="metric-title">Pending Doctor Orders</div>
                        <div class="metric-value <?php echo $pending_orders_count > 0 ? 'text-warning' : ''; ?>"><?php echo $pending_orders_count; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Triage Queue -->
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-activity text-primary me-2"></i> Recent Vitals (Triage Queue)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-4">Patient Name</th>
                                    <th>Logged At</th>
                                    <th>BP & HR</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_vitals) > 0): ?>
                                    <?php foreach ($recent_vitals as $vital): ?>
                                        <?php $isElevated = ($vital['systolic_bp'] > 130 || $vital['diastolic_bp'] > 85); ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">
                                                <?php echo htmlspecialchars($vital['first_name'] . ' ' . $vital['last_name']); ?><br>
                                                <small class="text-muted fw-normal">Dr. <?php echo htmlspecialchars($vital['doc_last'] ?? 'Unassigned'); ?></small>
                                            </td>
                                            <td class="text-muted small"><?php echo date('M d, h:i A', strtotime($vital['recorded_at'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $isElevated ? 'bg-danger' : 'bg-light text-dark border'; ?> mb-1">
                                                    <?php echo $vital['systolic_bp'] . '/' . $vital['diastolic_bp']; ?> mmHg
                                                </span><br>
                                                <span class="small text-muted"><i class="bi bi-heart-pulse text-danger"></i> <?php echo $vital['heart_rate'] ? $vital['heart_rate'] . ' bpm' : '-'; ?></span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-light border rounded-pill text-primary fw-bold">Review</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-clipboard-x fs-2 d-block mb-2"></i> No vitals logged recently.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Doctor Orders Panel -->
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-file-medical text-warning me-2"></i> Clinical Orders</h6>
                    </div>
                    <div class="card-body bg-light" style="max-height: 400px; overflow-y: auto;">
                        <?php if (count($pending_orders) > 0): ?>
                            <?php foreach ($pending_orders as $order): ?>
                                <div class="order-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <small class="fw-bold text-dark"><i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($order['pat_first'] . ' ' . $order['pat_last']); ?></small>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-2 small text-dark"><?php echo nl2br(htmlspecialchars($order['order_text'])); ?></p>
                                    <div class="mt-2 pt-2 border-top border-warning border-opacity-25">
                                        <small class="text-muted fst-italic mb-2 d-block">From: Dr. <?php echo htmlspecialchars($order['doc_last']); ?></small>
                                        <form method="POST" action="" class="d-flex align-items-center gap-2" id="orderForm<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="patient_id" value="<?php echo $order['patient_id']; ?>">
                                            <input type="hidden" name="complete_order" value="1">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white border-end-0">$</span>
                                                <input type="number" step="0.01" name="order_fee" class="form-control border-start-0 ps-0" placeholder="Fee (Opt)">
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success rounded-pill fw-bold px-3 shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#confirmOrderModal<?php echo $order['order_id']; ?>">
                                                <i class="bi bi-check2-all"></i> Done
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Custom Confirmation Modal for this Order -->
                                <div class="modal fade" id="confirmOrderModal<?php echo $order['order_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                        <div class="modal-content border-0 rounded-4 shadow-lg text-center p-4">
                                            <div class="modal-body p-0">
                                                <i class="bi bi-question-circle text-warning mb-3 d-block" style="font-size: 3rem;"></i>
                                                <h5 class="fw-bold mb-3">Fulfill Order?</h5>
                                                <p class="text-muted small mb-4">Are you sure you want to mark this clinical order as fulfilled? This action cannot be undone.</p>
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" onclick="document.getElementById('orderForm<?php echo $order['order_id']; ?>').submit();">Yes, Done</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-2 d-block mb-2 opacity-50"></i>
                                <p class="small mb-0">No pending orders from doctors. Great job!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal for Logging Vitals (In-Person) -->
    <div class="modal fade" id="logVitalsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clipboard2-pulse text-primary me-2"></i> Log Clinic Vitals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Select Patient *</label>
                            <select name="patient_id" class="form-select bg-light border-0" required>
                                <option value="">-- Search / Choose Patient --</option>
                                <?php foreach ($patientsList as $p): ?>
                                    <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold">Systolic BP *</label>
                                <input type="number" class="form-control bg-light border-0" name="systolic_bp" placeholder="e.g. 120" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold">Diastolic BP *</label>
                                <input type="number" class="form-control bg-light border-0" name="diastolic_bp" placeholder="e.g. 80" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted small fw-bold">Heart Rate</label>
                                <input type="number" class="form-control bg-light border-0" name="heart_rate" placeholder="BPM">
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted small fw-bold">Weight (kg)</label>
                                <input type="number" step="0.1" class="form-control bg-light border-0" name="weight_kg" placeholder="kg">
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted small fw-bold">Blood Sugar</label>
                                <input type="number" class="form-control bg-light border-0" name="blood_sugar" placeholder="mg/dL">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Clinical Notes / Observations</label>
                            <textarea class="form-control bg-light border-0" name="notes" rows="2" placeholder="Any symptoms or notes to pass to the doctor?"></textarea>
                        </div>
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <label class="form-label text-dark small fw-bold"><i class="bi bi-receipt me-1 text-primary"></i> Service / Consultation Fee ($)</label>
                            <input type="number" step="0.01" class="form-control border-0 shadow-sm" name="service_fee" placeholder="0.00 (Optional)">
                            <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">If entered, an invoice will automatically be sent to the Cashier.</small>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="log_vitals" class="btn btn-primary rounded-pill fw-bold py-2">Save Vitals & Process</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle & Custom Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openSidebar');
        const closeBtn = document.getElementById('closeSidebar');

        openBtn.addEventListener('click', () => sidebar.classList.add('show'));
        closeBtn.addEventListener('click', () => sidebar.classList.remove('show'));
    </script>
</body>

</html>