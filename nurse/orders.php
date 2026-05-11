<?php
// nurse/orders.php - View and manage Doctor's Orders/Clinical Instructions
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a nurse
if (!isset($_SESSION['nurse_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: login.php");
    exit();
}

$nurse_id = $_SESSION['nurse_id'];
$success_msg = '';
$error_msg = '';

// --- Form Handlers ---

// 1. Handle marking a Doctor's Order as Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    if ($order_id) {
        try {
            $stmt = $pdo->prepare("UPDATE doctor_orders SET status = 'Completed', completed_at = NOW(), completed_by_nurse_id = ? WHERE order_id = ?");
            if ($stmt->execute([$nurse_id, $order_id])) {
                $success_msg = "Order successfully marked as completed.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to update order status.";
        }
    }
}

// 2. Handle Nurse Logging In-Person Vitals (Modal Submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_vitals'])) {
    $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $sys_bp = filter_input(INPUT_POST, 'systolic_bp', FILTER_VALIDATE_INT);
    $dia_bp = filter_input(INPUT_POST, 'diastolic_bp', FILTER_VALIDATE_INT);
    $hr = filter_input(INPUT_POST, 'heart_rate', FILTER_VALIDATE_INT);
    $weight = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
    $sugar = filter_input(INPUT_POST, 'blood_sugar', FILTER_VALIDATE_INT);
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));

    $notes = "[Logged by Nurse] " . $notes;

    if ($pat_id && $sys_bp && $dia_bp) {
        try {
            $insert_stmt = $pdo->prepare("INSERT INTO vitals (patient_id, systolic_bp, diastolic_bp, heart_rate, weight_kg, blood_sugar_mgdl, symptoms_notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($insert_stmt->execute([$pat_id, $sys_bp, $dia_bp, $hr, $weight, $sugar, $notes])) {
                $success_msg = "Clinical vitals logged successfully for the patient.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to log vitals.";
        }
    } else {
        $error_msg = "Patient selection and Blood Pressure are required.";
    }
}

// --- Fetch Data ---
try {
    // Fetch Pending Doctor Orders
    $pending_stmt = $pdo->query("
        SELECT o.*, d.last_name AS doc_last, p.first_name AS pat_first, p.last_name AS pat_last 
        FROM doctor_orders o
        JOIN doctors d ON o.doctor_id = d.doctor_id
        JOIN patients p ON o.patient_id = p.patient_id
        WHERE o.status = 'Pending'
        ORDER BY o.created_at ASC
    ");
    $pending_orders = $pending_stmt->fetchAll();
    $pending_orders_count = count($pending_orders);

    // Fetch Completed Orders (Last 50 for history)
    $completed_stmt = $pdo->query("
        SELECT o.*, d.last_name AS doc_last, p.first_name AS pat_first, p.last_name AS pat_last, n.last_name AS nurse_last
        FROM doctor_orders o
        JOIN doctors d ON o.doctor_id = d.doctor_id
        JOIN patients p ON o.patient_id = p.patient_id
        LEFT JOIN nurses n ON o.completed_by_nurse_id = n.nurse_id
        WHERE o.status = 'Completed'
        ORDER BY o.completed_at DESC LIMIT 50
    ");
    $completed_orders = $completed_stmt->fetchAll();

    // Fetch Patients for Vitals Modal
    $patientsList = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading clinical orders.";
    $pending_orders = [];
    $completed_orders = [];
    $pending_orders_count = 0;
    $patientsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Orders - LuminaCare Nurse</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Nurse Theme Colors */
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

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        /* Order Cards */
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
        }

        .order-card.pending {
            border-left: 4px solid #f59e0b;
        }

        .order-card.completed {
            border-left: 4px solid #10b981;
            opacity: 0.85;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .order-body {
            flex-grow: 1;
            color: var(--text-dark);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed #eee;
            padding-top: 15px;
            margin-top: auto;
        }

        /* Custom Tabs */
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 600;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 25px;
        }

        .nav-tabs .nav-link:hover {
            border-color: #e9ecef;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 3px solid var(--primary-color);
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
                <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            </div>
            <div class="nav-item">
                <a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> Patient Directory</a>
            </div>
            <div class="nav-item">
                <a href="orders.php" class="nav-link active">
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
        <div class="d-flex align-items-center gap-3 mb-4">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <div>
                <h3 class="mb-0 fw-bold text-dark">Clinical Orders</h3>
                <p class="text-muted mb-0 small">Manage and fulfill instructions assigned by healthcare providers.</p>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4 border-bottom-0" id="ordersTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                    Pending Action
                    <?php if ($pending_orders_count > 0): ?><span class="badge bg-warning text-dark ms-1 rounded-pill"><?php echo $pending_orders_count; ?></span><?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                    Completed History
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="ordersTabContent">

            <!-- Pending Orders Tab -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <div class="row g-4">
                    <?php if (count($pending_orders) > 0): ?>
                        <?php foreach ($pending_orders as $order): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="order-card pending">
                                    <div class="order-header">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-fill text-muted me-1"></i> <?php echo htmlspecialchars($order['pat_first'] . ' ' . $order['pat_last']); ?></h6>
                                            <small class="text-muted">ID: #<?php echo str_pad($order['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                        </div>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 rounded-pill"><i class="bi bi-clock"></i> Pending</span>
                                    </div>

                                    <div class="order-body">
                                        <strong class="small text-uppercase text-muted d-block mb-1">Instructions:</strong>
                                        <?php echo nl2br(htmlspecialchars($order['order_text'])); ?>
                                    </div>

                                    <div class="order-footer">
                                        <div class="small text-muted">
                                            <i class="bi bi-stethoscope text-primary"></i> Dr. <?php echo htmlspecialchars($order['doc_last']); ?><br>
                                            <span style="font-size: 0.75rem;"><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></span>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" name="complete_order" class="btn btn-success rounded-pill fw-bold px-3 shadow-sm" onclick="return confirm('Mark this order as fulfilled?');">
                                                <i class="bi bi-check2-all me-1"></i> Mark Done
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5 text-muted bg-white rounded-4 border border-light shadow-sm">
                                <i class="bi bi-check-circle fs-1 d-block mb-3 text-success opacity-50"></i>
                                <h5 class="fw-bold">All caught up!</h5>
                                <p>There are no pending doctor orders at this time.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Orders Tab -->
            <div class="tab-pane fade" id="completed" role="tabpanel">
                <div class="row g-4">
                    <?php if (count($completed_orders) > 0): ?>
                        <?php foreach ($completed_orders as $order): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="order-card completed">
                                    <div class="order-header">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-fill text-muted me-1"></i> <?php echo htmlspecialchars($order['pat_first'] . ' ' . $order['pat_last']); ?></h6>
                                            <small class="text-muted">ID: #<?php echo str_pad($order['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                        </div>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill"><i class="bi bi-check-all"></i> Done</span>
                                    </div>

                                    <div class="order-body text-muted">
                                        <?php echo nl2br(htmlspecialchars($order['order_text'])); ?>
                                    </div>

                                    <div class="order-footer bg-light p-2 rounded border border-light mt-2">
                                        <div class="w-100 text-center small text-muted">
                                            Completed by <strong>Nurse <?php echo htmlspecialchars($order['nurse_last'] ?? 'Unknown'); ?></strong><br>
                                            <span style="font-size: 0.75rem;"><?php echo date('M d, Y - h:i A', strtotime($order['completed_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5 text-muted bg-white rounded-4 border border-light shadow-sm">
                                <i class="bi bi-clock-history fs-1 d-block mb-3 opacity-25"></i>
                                <p>No completed orders in the history log yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                <?php foreach ($patientsList as $pl): ?>
                                    <option value="<?php echo $pl['patient_id']; ?>"><?php echo htmlspecialchars($pl['last_name'] . ', ' . $pl['first_name']); ?> (ID: #<?php echo str_pad($pl['patient_id'], 4, '0', STR_PAD_LEFT); ?>)</option>
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
                        <div class="d-grid mt-4">
                            <button type="submit" name="log_vitals" class="btn btn-primary rounded-pill fw-bold py-2" style="background-color: var(--primary-color); border: none;">Save to Patient Chart</button>
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