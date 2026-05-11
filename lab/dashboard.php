<?php
// lab/dashboard.php - Lab Tech Main Dashboard & Test Manager with Automated Billing
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a lab tech
if (!isset($_SESSION['tech_id']) || $_SESSION['role'] !== 'lab_tech') {
    header("Location: login.php");
    exit();
}

$tech_id = $_SESSION['tech_id'];
$success_msg = '';
$error_msg = '';

// --- Auto-Setup: Ensure Lab Tests table exists and has cost tracking ---
try {
    $checkTests = $pdo->query("SHOW TABLES LIKE 'lab_tests'")->rowCount();
    if ($checkTests == 0) {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS `lab_tests` (
          `test_id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) NOT NULL,
          `doctor_id` int(11) DEFAULT NULL,
          `tech_id` int(11) DEFAULT NULL,
          `test_name` varchar(100) NOT NULL,
          `test_description` text DEFAULT NULL,
          `status` enum('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
          `result_notes` text DEFAULT NULL,
          `result_reference` varchar(255) DEFAULT NULL,
          `service_cost` decimal(10,2) DEFAULT 0.00,
          `created_at` timestamp DEFAULT current_timestamp(),
          `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`test_id`),
          FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } else {
        // Ensure service_cost column exists in case of previous versions
        $pdo->exec("ALTER TABLE lab_tests ADD COLUMN IF NOT EXISTS `service_cost` decimal(10,2) DEFAULT 0.00");
    }
} catch (PDOException $e) {
    $error_msg = "Database setup error.";
}

// --- Form Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Pick up a test (Self-assign)
    if (isset($_POST['pickup_test'])) {
        $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
        if ($test_id) {
            try {
                $stmt = $pdo->prepare("UPDATE lab_tests SET tech_id = ?, status = 'In Progress' WHERE test_id = ? AND status = 'Pending'");
                if ($stmt->execute([$tech_id, $test_id])) {
                    $success_msg = "Test assignment accepted. Status updated to 'In Progress'.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to assign test.";
            }
        }
    }

    // 2. Update Test Results & Trigger Automated Billing
    if (isset($_POST['update_test'])) {
        $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        $result_notes = trim(filter_input(INPUT_POST, 'result_notes', FILTER_SANITIZE_STRING));
        $test_cost = filter_input(INPUT_POST, 'test_cost', FILTER_VALIDATE_FLOAT) ?: 0.00;

        if ($test_id && $status) {
            try {
                $pdo->beginTransaction();

                // Update the lab test record including the final service cost
                $stmt = $pdo->prepare("UPDATE lab_tests SET status = ?, result_notes = ?, service_cost = ? WHERE test_id = ? AND tech_id = ?");
                $stmt->execute([$status, $result_notes, $test_cost, $test_id, $tech_id]);

                // AUTOMATED BILLING: If test is completed and has a cost, create a billing record for the cashier
                if ($status === 'Completed' && $test_cost > 0) {
                    // Fetch patient_id and test name for the invoice
                    $infoStmt = $pdo->prepare("SELECT patient_id, test_name FROM lab_tests WHERE test_id = ?");
                    $infoStmt->execute([$test_id]);
                    $test_info = $infoStmt->fetch();

                    if ($test_info) {
                        $bill_desc = "Laboratory Service: " . $test_info['test_name'];
                        $due_date = date('Y-m-d'); // Due immediately

                        // Insert into central billing table
                        // The Cashier uses the patient_id to retrieve all 'Unpaid' records like this one
                        $billStmt = $pdo->prepare("INSERT INTO billing (patient_id, description, amount, status, due_date) VALUES (?, ?, ?, 'Unpaid', ?)");
                        $billStmt->execute([$test_info['patient_id'], $bill_desc, $test_cost, $due_date]);
                    }
                }

                $pdo->commit();
                $success_msg = "Test results saved. " . ($status === 'Completed' ? "Service cost of $" . number_format($test_cost, 2) . " sent to Billing." : "");
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Failed to save results and generate billing.";
            }
        }
    }
}

// --- Fetch Dashboard Data ---
try {
    $unassigned_count = $pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status = 'Pending' AND tech_id IS NULL")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lab_tests WHERE tech_id = ? AND status = 'In Progress'");
    $stmt->execute([$tech_id]);
    $my_progress_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lab_tests WHERE tech_id = ? AND status = 'Completed' AND DATE(updated_at) = CURDATE()");
    $stmt->execute([$tech_id]);
    $my_completed_today = $stmt->fetchColumn();

    // Fetch Active Queue
    $queue_stmt = $pdo->prepare("
        SELECT t.*, p.first_name AS pat_first, p.last_name AS pat_last, d.last_name AS doc_last
        FROM lab_tests t
        JOIN patients p ON t.patient_id = p.patient_id
        LEFT JOIN doctors d ON t.doctor_id = d.doctor_id
        WHERE (t.status = 'Pending') OR (t.tech_id = ? AND t.status = 'In Progress')
        ORDER BY t.created_at ASC
    ");
    $queue_stmt->execute([$tech_id]);
    $active_queue = $queue_stmt->fetchAll();

    // Fetch My Completed History
    $history_stmt = $pdo->prepare("
        SELECT t.*, p.first_name AS pat_first, p.last_name AS pat_last
        FROM lab_tests t
        JOIN patients p ON t.patient_id = p.patient_id
        WHERE t.tech_id = ? AND t.status = 'Completed'
        ORDER BY t.updated_at DESC LIMIT 20
    ");
    $history_stmt->execute([$tech_id]);
    $completed_history = $history_stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading diagnostic data.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Dashboard - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #d97706;
            --secondary-color: #f59e0b;
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
            background-color: rgba(245, 158, 11, 0.08);
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

        .nav-tabs {
            border-bottom: 1px solid #eee;
            padding: 0 15px;
            background: white;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 600;
            padding: 15px 20px;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: transparent;
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
            <a href="#" class="sidebar-brand"><i class="bi bi-droplet-half me-2"></i>Lab<span>Portal</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu mt-3">
            <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Diagnostics Board</a>
            <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Lab Supplies</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Settings</a>
        </div>
        <div class="mt-auto p-3 border-top"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold">Diagnostics Center</h4>
                    <p class="text-muted mb-0 small">Welcome back, Tech. <?php echo htmlspecialchars($_SESSION['last_name']); ?></p>
                </div>
            </div>
            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-microscope me-1"></i> Lab Active</span>
        </header>

        <?php if ($success_msg): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-hourglass-split"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Pending Tests</small>
                        <h3 class="mb-0 fw-bold"><?php echo $unassigned_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-gear-fill"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">In Progress</small>
                        <h3 class="mb-0 fw-bold"><?php echo $my_progress_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-all"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Done Today</small>
                        <h3 class="mb-0 fw-bold"><?php echo $my_completed_today; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <ul class="nav nav-tabs" id="labTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#activeQueue" type="button" role="tab">Active Queue</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completedHistory" type="button" role="tab">Completed History</button>
                </li>
            </ul>

            <div class="tab-content" id="labTabsContent">
                <div class="tab-pane fade show active" id="activeQueue" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Patient</th>
                                    <th>Test Name</th>
                                    <th>Status</th>
                                    <th>Requested By</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($active_queue) > 0): ?>
                                    <?php foreach ($active_queue as $t): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($t['pat_first'] . ' ' . $t['pat_last']); ?></div>
                                                <small class="text-muted">ID: #<?php echo str_pad($t['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                            </td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($t['test_name']); ?></td>
                                            <td>
                                                <?php if ($t['status'] == 'Pending'): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">Unassigned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 rounded-pill">My Assignment</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>Dr. <?php echo htmlspecialchars($t['doc_last'] ?? 'Clinic'); ?></td>
                                            <td class="text-end pe-4">
                                                <?php if ($t['status'] == 'Pending'): ?>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="test_id" value="<?php echo $t['test_id']; ?>">
                                                        <button type="submit" name="pickup_test" class="btn btn-sm btn-outline-danger rounded-pill fw-bold">Accept</button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $t['test_id']; ?>">Complete Test</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <div class="modal fade" id="updateModal<?php echo $t['test_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 rounded-4 shadow-lg">
                                                    <div class="modal-header border-bottom-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Finalize Test Results</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body pt-3">
                                                        <div class="p-3 bg-light rounded-3 border mb-3">
                                                            <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($t['test_name']); ?></h6>
                                                            <p class="small text-muted mb-0">For: <?php echo htmlspecialchars($t['pat_first'] . ' ' . $t['pat_last']); ?></p>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="test_id" value="<?php echo $t['test_id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold text-muted">Test Status</label>
                                                                <select name="status" class="form-select bg-light border-0" required id="statusSelect<?php echo $t['test_id']; ?>" onchange="togglePrice(this, <?php echo $t['test_id']; ?>)">
                                                                    <option value="In Progress" selected>Keep In Progress</option>
                                                                    <option value="Completed">Mark as Completed & Generate Invoice</option>
                                                                    <option value="Cancelled">Cancel Test</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3" id="costField<?php echo $t['test_id']; ?>" style="display: none;">
                                                                <label class="form-label small fw-bold text-primary">Service Price for Cashier ($) *</label>
                                                                <input type="number" step="0.01" name="test_cost" class="form-control bg-light border-primary border-opacity-25" placeholder="Enter amount to be charged by cashier">
                                                                <small class="text-muted mt-1 d-block" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> This price will be automatically fetched by the cashier when they enter the patient's ID.</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold text-muted">Diagnostic Findings / Notes</label>
                                                                <textarea name="result_notes" class="form-control bg-light border-0" rows="4" placeholder="Enter test results here..." required><?php echo htmlspecialchars($t['result_notes'] ?? ''); ?></textarea>
                                                            </div>
                                                            <button type="submit" name="update_test" class="btn btn-warning w-100 rounded-pill fw-bold py-2 shadow-sm" style="background-color: var(--primary-color); border: none; color: white;">Finalize & Bill Patient</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No pending tests in the laboratory.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="completedHistory" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Patient</th>
                                    <th>Test Name</th>
                                    <th>Billed Amount</th>
                                    <th>Completed On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($completed_history) > 0): ?>
                                    <?php foreach ($completed_history as $hist): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($hist['pat_first'] . ' ' . $hist['pat_last']); ?></div>
                                            </td>
                                            <td class="fw-bold text-success"><?php echo htmlspecialchars($hist['test_name']); ?></td>
                                            <td><span class="badge bg-light text-dark border">$<?php echo number_format($hist['service_cost'], 2); ?></span></td>
                                            <td class="small text-muted"><?php echo date('M d, Y - h:i A', strtotime($hist['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">You haven't completed any tests today yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        function togglePrice(select, id) {
            const field = document.getElementById('costField' + id);
            field.style.display = (select.value === 'Completed') ? 'block' : 'none';
            const input = field.querySelector('input');
            if (select.value === 'Completed') input.setAttribute('required', 'required');
            else input.removeAttribute('required');
        }
    </script>
</body>

</html>