<?php
// admin/billing.php - Manage Patient Billing and Finances
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle CRUD Operations for Billing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add New Invoice
    if (isset($_POST['add_invoice'])) {
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $due_date = trim(filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING));
        $status = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING));

        if ($patient_id && !empty($description) && $amount !== false && !empty($due_date)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO billing (patient_id, description, amount, status, due_date) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$patient_id, $description, $amount, $status, $due_date])) {
                    $success_msg = "New invoice generated successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to generate invoice. Ensure the selected patient is valid.";
            }
        } else {
            $error_msg = "Please fill in all required fields with valid data.";
        }
    }

    // 2. Update Invoice Status
    elseif (isset($_POST['update_status'])) {
        $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
        $status = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING));

        if ($invoice_id && in_array($status, ['Unpaid', 'Paid', 'Overdue'])) {
            try {
                $stmt = $pdo->prepare("UPDATE billing SET status = ? WHERE invoice_id = ?");
                if ($stmt->execute([$status, $invoice_id])) {
                    $success_msg = "Invoice status updated successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to update invoice status.";
            }
        }
    }

    // 3. Delete Invoice
    elseif (isset($_POST['delete_invoice'])) {
        $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM billing WHERE invoice_id = ?");
            if ($stmt->execute([$invoice_id])) {
                $success_msg = "Invoice permanently deleted from the system.";
            }
        } catch (PDOException $e) {
            $error_msg = "Cannot delete this invoice.";
        }
    }
}

// Auto-Setup & Fetch Data
try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'billing'")->rowCount();

    if ($checkTable == 0) {
        // Auto-create the table if it does not exist
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `billing` (
          `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) NOT NULL,
          `description` varchar(255) NOT NULL,
          `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
          `status` enum('Unpaid', 'Paid', 'Overdue') DEFAULT 'Unpaid',
          `due_date` date NOT NULL,
          `created_at` timestamp DEFAULT current_timestamp(),
          `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`invoice_id`),
          FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($createTableSQL);
        $success_msg = "System Auto-Setup: 'billing' table created successfully.";
    }

    // Automatically update overdue invoices
    $pdo->exec("UPDATE billing SET status = 'Overdue' WHERE status = 'Unpaid' AND due_date < CURDATE()");

    // Fetch all invoices
    $stmt = $pdo->query("
        SELECT b.*, p.first_name, p.last_name 
        FROM billing b 
        JOIN patients p ON b.patient_id = p.patient_id 
        ORDER BY b.created_at DESC
    ");
    $invoices = $stmt->fetchAll();

    // Fetch patients for the dropdown
    $patientsList = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name ASC")->fetchAll();

    // Calculate Summary Stats
    $total_revenue = 0;
    $pending_revenue = 0;

    foreach ($invoices as $inv) {
        if ($inv['status'] === 'Paid') {
            $total_revenue += $inv['amount'];
        } else {
            $pending_revenue += $inv['amount'];
        }
    }
} catch (PDOException $e) {
    $error_msg = "Error loading billing data: " . $e->getMessage();
    $invoices = [];
    $patientsList = [];
    $total_revenue = 0;
    $pending_revenue = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Finances - Admin</title>
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

        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 20px;
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
            background: rgba(17, 24, 39, 0.1);
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
            <div class="nav-item"><a href="billing.php" class="nav-link active"><i class="bi bi-receipt"></i> Billing & Finances</a></div>

            <div class="nav-section-title">System</div>
            <div class="nav-item"><a href="logs.php" class="nav-link"><i class="bi bi-database-check"></i> System Logs</a></div>
        </div>

        <div class="logout-wrapper">
            <a href="logout.php" class="btn btn-outline-light w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Secure Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <h3 class="mb-0 fw-bold text-dark">Billing & Finances</h3>
            </div>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                <i class="bi bi-receipt me-1"></i> Generate Invoice
            </button>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 small fw-bold">Total Revenue (Paid)</h6>
                        <h3 class="mb-0 fw-bold text-success">$<?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 small fw-bold">Pending / Overdue Collections</h6>
                        <h3 class="mb-0 fw-bold text-warning">$<?php echo number_format($pending_revenue, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Invoice ID / Date</th>
                            <th>Patient Name</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status / Due Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?></div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($inv['last_name'] . ', ' . $inv['first_name']); ?></div>
                                    <small class="text-muted">ID: #<?php echo str_pad($inv['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($inv['description']); ?>">
                                        <?php echo htmlspecialchars($inv['description']); ?>
                                    </span>
                                </td>
                                <td>
                                    <h6 class="mb-0 fw-bold">$<?php echo number_format($inv['amount'], 2); ?></h6>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'warning text-dark';
                                    if ($inv['status'] == 'Paid') $statusClass = 'success';
                                    if ($inv['status'] == 'Overdue') $statusClass = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?> bg-opacity-10 text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?> border-opacity-25 px-2 py-1 rounded-pill mb-1">
                                        <?php echo htmlspecialchars($inv['status']); ?>
                                    </span><br>
                                    <small class="text-muted">Due: <?php echo date('M d, Y', strtotime($inv['due_date'])); ?></small>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStatusModal<?php echo $inv['invoice_id']; ?>" title="Update Payment Status"><i class="bi bi-pencil-square"></i></button>
                                        <form method="POST" action="" onsubmit="return confirm('Permanently delete this invoice?');">
                                            <input type="hidden" name="invoice_id" value="<?php echo $inv['invoice_id']; ?>">
                                            <button type="submit" name="delete_invoice" class="btn btn-sm btn-outline-danger" title="Delete Invoice"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit Status Modal -->
                            <div class="modal fade" id="editStatusModal<?php echo $inv['invoice_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h6 class="modal-title fw-bold">Update Status</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <p class="small text-muted mb-3">Update payment status for <strong>INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?></strong></p>
                                            <form method="POST" action="">
                                                <input type="hidden" name="invoice_id" value="<?php echo $inv['invoice_id']; ?>">
                                                <div class="mb-3">
                                                    <select name="status" class="form-select bg-light border-0" required>
                                                        <option value="Unpaid" <?php echo ($inv['status'] == 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                                        <option value="Paid" <?php echo ($inv['status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                                        <option value="Overdue" <?php echo ($inv['status'] == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                                                    </select>
                                                </div>
                                                <button type="submit" name="update_status" class="btn btn-primary w-100 rounded-pill fw-bold">Save Status</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-1 d-block mb-3 opacity-25"></i> No invoices have been generated yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Invoice Modal -->
    <div class="modal fade" id="addInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Generate New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Select Patient *</label>
                                <select name="patient_id" class="form-select bg-light border-0" required>
                                    <option value="">-- Choose Patient --</option>
                                    <?php foreach ($patientsList as $p): ?>
                                        <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Billing Description / Services *</label>
                                <input type="text" class="form-control bg-light border-0" name="description" placeholder="e.g. Ultrasound Scan, Consultation Fee, Lab Tests" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Total Amount ($) *</label>
                                <input type="number" step="0.01" class="form-control bg-light border-0" name="amount" placeholder="0.00" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Payment Status</label>
                                <select name="status" class="form-select bg-light border-0" required>
                                    <option value="Unpaid" selected>Unpaid</option>
                                    <option value="Paid">Paid</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Due Date *</label>
                                <input type="date" class="form-control bg-light border-0" name="due_date" required>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="add_invoice" class="btn btn-dark rounded-pill py-2 fw-bold">Generate Invoice</button>
                        </div>
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