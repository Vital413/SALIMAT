<?php
// cashier/dashboard.php - Comprehensive Financial Management
require_once '../config/config.php';

if (!isset($_SESSION['cashier_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: login.php");
    exit();
}

$cashier_id = $_SESSION['cashier_id'];
$success_msg = '';
$error_msg = '';
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';

// --- Auto-Setup: Financial Tables ---
try {
    // 1. Update Billing table for partial payments
    $pdo->exec("ALTER TABLE billing ADD COLUMN IF NOT EXISTS amount_paid decimal(10,2) DEFAULT 0.00");

    // 2. Create Payments table (Transaction Log)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
      `payment_id` int(11) NOT NULL AUTO_INCREMENT,
      `invoice_id` int(11) NOT NULL,
      `amount` decimal(10,2) NOT NULL,
      `payment_method` varchar(50) DEFAULT 'Cash',
      `cashier_id` int(11) NOT NULL,
      `status` enum('Success', 'Declined') DEFAULT 'Success',
      `created_at` timestamp DEFAULT current_timestamp(),
      PRIMARY KEY (`payment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Create Expenses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expenses` (
      `expense_id` int(11) NOT NULL AUTO_INCREMENT,
      `description` varchar(255) NOT NULL,
      `amount` decimal(10,2) NOT NULL,
      `category` varchar(50) DEFAULT 'General',
      `cashier_id` int(11) NOT NULL,
      `created_at` timestamp DEFAULT current_timestamp(),
      PRIMARY KEY (`expense_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) { /* Silently check */
}

// --- Form Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Process Advanced Payment
    if (isset($_POST['process_payment'])) {
        $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
        $amount_tendering = filter_input(INPUT_POST, 'amount_tendering', FILTER_VALIDATE_FLOAT);
        $method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
        $action = $_POST['payment_action']; // 'Success' or 'Declined'

        if ($invoice_id && $amount_tendering !== false) {
            try {
                $pdo->beginTransaction();

                // Log the payment attempt
                $payStmt = $pdo->prepare("INSERT INTO payments (invoice_id, amount, payment_method, cashier_id, status) VALUES (?, ?, ?, ?, ?)");
                $payStmt->execute([$invoice_id, $amount_tendering, $method, $cashier_id, $action]);

                if ($action === 'Success') {
                    // Update the invoice
                    $invStmt = $pdo->prepare("SELECT amount, amount_paid FROM billing WHERE invoice_id = ?");
                    $invStmt->execute([$invoice_id]);
                    $invoice = $invStmt->fetch();

                    $new_total_paid = $invoice['amount_paid'] + $amount_tendering;
                    $status = ($new_total_paid >= $invoice['amount']) ? 'Paid' : 'Unpaid';

                    $updStmt = $pdo->prepare("UPDATE billing SET amount_paid = ?, status = ?, updated_at = NOW() WHERE invoice_id = ?");
                    $updStmt->execute([$new_total_paid, $status, $invoice_id]);

                    $success_msg = "Payment of $" . number_format($amount_tendering, 2) . " processed successfully.";
                } else {
                    $error_msg = "Payment transaction was marked as DECLINED.";
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Financial processing error.";
            }
        }
    }

    // 2. Log Clinic Expense
    if (isset($_POST['log_expense'])) {
        $desc = trim(filter_input(INPUT_POST, 'expense_desc', FILTER_SANITIZE_STRING));
        $amt = filter_input(INPUT_POST, 'expense_amount', FILTER_VALIDATE_FLOAT);
        $cat = filter_input(INPUT_POST, 'expense_category', FILTER_SANITIZE_STRING);

        if (!empty($desc) && $amt > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, category, cashier_id) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$desc, $amt, $cat, $cashier_id])) {
                    $success_msg = "Expense logged successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to log expense.";
            }
        }
    }
}

// --- Fetch Data ---
try {
    // 1. Fetch All Invoices (Paid, Unpaid, Partial) with Search Logic
    $query = "
        SELECT b.*, p.first_name, p.last_name, p.phone, p.email
        FROM billing b 
        JOIN patients p ON b.patient_id = p.patient_id 
        WHERE 1=1
    ";

    $params = [];
    if (!empty($search_query)) {
        $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.patient_id = ?)";
        $search_param = "%{$search_query}%";
        $params = [$search_param, $search_param, $search_param, $search_param, $search_query];
    }

    // Prioritize unpaid/overdue first, then by date
    $query .= " ORDER BY CASE WHEN b.status != 'Paid' THEN 0 ELSE 1 END, b.due_date ASC, b.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_invoices = $stmt->fetchAll();

    // Count pending vs all for the badge
    $pending_count = 0;
    foreach ($all_invoices as $inv) {
        if ($inv['status'] != 'Paid') $pending_count++;
    }

    // 2. Daily Summary Stats
    $collected_today = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Success' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $expenses_today = $pdo->query("SELECT SUM(amount) FROM expenses WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $unpaid_balance = $pdo->query("SELECT SUM(amount - amount_paid) FROM billing WHERE status != 'Paid'")->fetchColumn() ?: 0;

    // 3. Payment Method Summary for Reconciliation
    $method_summary = $pdo->query("SELECT payment_method, SUM(amount) as total FROM payments WHERE status = 'Success' AND DATE(created_at) = CURDATE() GROUP BY payment_method")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading financial records.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- HTML2PDF Library for Invoice Printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #818cf8;
            --accent-color: #f43f5e;
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
            background-color: rgba(79, 70, 229, 0.05);
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
            <a href="#" class="sidebar-brand"><i class="bi bi-cash-stack me-2"></i>LuminaPay</a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu mt-3">
            <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Billing Desk</a>
            <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-cart-dash"></i> Log Expense</a>
            <a href="history.php" class="nav-link"><i class="bi bi-clock-history"></i> Transaction Logs</a>
            <div class="mt-4 px-4 small text-muted text-uppercase fw-bold">Account</div>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Settings</a>
        </div>
        <div class="mt-auto p-3 border-top"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold">Financial Center</h4>
                    <p class="text-muted mb-0 small">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-danger rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-dash-circle me-1"></i> Log Expense</button>
            </div>
        </header>

        <?php if ($success_msg): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Gross Today</small>
                        <h3 class="mb-0 fw-bold">$<?php echo number_format($collected_today, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-cart-x"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Expenses Today</small>
                        <h3 class="mb-0 fw-bold text-danger">$<?php echo number_format($expenses_today, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card" style="border-left: 4px solid var(--primary-color);">
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-piggy-bank"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Net Cash Flow</small>
                        <h3 class="mb-0 fw-bold text-primary">$<?php echo number_format($collected_today - $expenses_today, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Billing Queue -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="fw-bold"><i class="bi bi-receipt text-primary me-2"></i> Patient Bills & Invoices</span>

                            <!-- Search Form -->
                            <form method="GET" action="dashboard.php" class="d-flex gap-2 m-0" style="max-width: 350px;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="Search ID, Name, Phone..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">Search</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Patient</th>
                                    <th>Description</th>
                                    <th>Total Due</th>
                                    <th>Status / Balance</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_invoices) > 0): ?>
                                    <?php foreach ($all_invoices as $inv): ?>
                                        <?php $balance = $inv['amount'] - $inv['amount_paid']; ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></div>
                                                <small class="text-muted">ID: #<?php echo str_pad($inv['patient_id'], 4, '0', STR_PAD_LEFT); ?> | <?php echo htmlspecialchars($inv['phone'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($inv['description']); ?>">
                                                    <?php echo htmlspecialchars($inv['description']); ?>
                                                </div>
                                                <small class="text-muted d-block">INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?></small>
                                            </td>
                                            <td>$<?php echo number_format($inv['amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = ($inv['status'] == 'Paid') ? 'success' : (($inv['status'] == 'Overdue') ? 'danger' : 'warning text-dark');
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?> bg-opacity-10 text-<?php echo $badgeClass; ?> px-2 py-1 rounded-pill mb-1 border border-<?php echo $badgeClass; ?> border-opacity-25">
                                                    <?php echo $inv['status']; ?>
                                                </span>
                                                <br>
                                                <h6 class="mb-0 fw-bold text-<?php echo ($balance > 0) ? 'danger' : 'success'; ?>">$<?php echo number_format($balance, 2); ?></h6>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-flex flex-column align-items-end gap-1">
                                                    <button class="btn btn-sm btn-outline-dark rounded-pill fw-bold" onclick="generateInvoice({
                                                    id: '<?php echo $inv['invoice_id']; ?>',
                                                    patient: '<?php echo addslashes($inv['first_name'] . " " . $inv['last_name']); ?>',
                                                    description: '<?php echo addslashes($inv['description']); ?>',
                                                    amount: '<?php echo number_format($inv['amount'], 2); ?>',
                                                    paid: '<?php echo number_format($inv['amount_paid'], 2); ?>',
                                                    balance: '<?php echo number_format($balance, 2); ?>',
                                                    status: '<?php echo $inv['status']; ?>',
                                                    date: '<?php echo date('M d, Y', strtotime($inv['created_at'])); ?>'
                                                })"><i class="bi bi-printer me-1"></i> Print</button>

                                                    <?php if ($balance > 0): ?>
                                                        <button class="btn btn-sm btn-primary rounded-pill fw-bold w-100" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $inv['invoice_id']; ?>">Receive Payment</button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Advanced Payment Modal -->
                                        <?php if ($balance > 0): ?>
                                            <div class="modal fade" id="payModal<?php echo $inv['invoice_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 rounded-4 shadow-lg">
                                                        <div class="modal-header border-bottom-0 pb-0">
                                                            <h5 class="modal-title fw-bold">Settle Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-3">
                                                            <div class="p-3 bg-light rounded-4 mb-3 text-center border">
                                                                <small class="text-muted text-uppercase fw-bold">Outstanding Balance</small>
                                                                <h2 class="fw-bold text-primary mb-0">$<?php echo number_format($balance, 2); ?></h2>
                                                            </div>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="invoice_id" value="<?php echo $inv['invoice_id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold">Enter Exact Amount Received ($) *</label>
                                                                    <input type="number" step="0.01" name="amount_tendering" class="form-control form-control-lg bg-light border-0" value="<?php echo $balance; ?>" max="<?php echo $balance; ?>" required>
                                                                </div>
                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-bold">Payment Method</label>
                                                                        <select name="payment_method" class="form-select bg-light border-0">
                                                                            <option value="Cash">Cash</option>
                                                                            <option value="POS/Card">POS / Card</option>
                                                                            <option value="Transfer">Bank Transfer</option>
                                                                            <option value="Insurance">Insurance</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-bold">Action</label>
                                                                        <select name="payment_action" class="form-select bg-light border-0">
                                                                            <option value="Success">Authorize Success</option>
                                                                            <option value="Declined">Mark Declined</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <button type="submit" name="process_payment" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow">Finalize Transaction</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                            <?php echo !empty($search_query) ? "No invoices found for this search." : "No collections in the system."; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Summary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom py-3 fw-bold">Daily Method Summary</div>
                    <div class="card-body">
                        <?php if ($method_summary): ?>
                            <?php foreach ($method_summary as $m): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded-3">
                                    <span class="fw-bold text-dark"><i class="bi bi-wallet2 me-2"></i> <?php echo htmlspecialchars($m['payment_method']); ?></span>
                                    <span class="badge bg-white text-dark border shadow-sm">$<?php echo number_format($m['total'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted"><i class="bi bi-info-circle mb-2 d-block"></i> No payments collected today yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Expense Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Log Hospital Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="mb-3"><label class="form-label small fw-bold">Description *</label><input type="text" name="expense_desc" class="form-control bg-light border-0" placeholder="e.g. Utility Bill, Stationery, Repairs" required></div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label small fw-bold">Amount ($) *</label><input type="number" step="0.01" name="expense_amount" class="form-control bg-light border-0" required></div>
                            <div class="col-6"><label class="form-label small fw-bold">Category</label><select name="expense_category" class="form-select bg-light border-0">
                                    <option value="General">General</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Supplies">Supplies</option>
                                </select></div>
                        </div>
                        <button type="submit" name="log_expense" class="btn btn-danger w-100 rounded-pill py-2 fw-bold">Process Outgoing Funds</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        /**
         * Generates an official invoice as a PDF using html2pdf.js
         */
        function generateInvoice(data) {
            const statusColor = data.status === 'Paid' ? '#10b981' : (data.status === 'Overdue' ? '#ef4444' : '#f59e0b');
            const element = document.createElement('div');

            element.innerHTML = `
                <div style="padding: 50px; font-family: 'Helvetica', 'Arial', sans-serif; color: #374151;">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h1 style="color: #4f46e5; margin-bottom: 5px; font-weight: 800;">LuminaCare</h1>
                        <p style="font-size: 1.1rem; color: #6b7280; margin-top: 0;">Maternal Health & Clinical Services</p>
                        <div style="background: ${statusColor}; color: white; display: inline-block; padding: 5px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; margin-top: 10px; text-transform: uppercase;">INVOICE - ${data.status}</div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px;">
                        <div>
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Billed To</p>
                            <p style="font-size: 1.2rem; font-weight: 700; margin-top: 0;">${data.patient}</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Invoice Details</p>
                            <p style="margin: 0;"><strong>INV:</strong> #${data.id.padStart(5, '0')}</p>
                            <p style="margin: 0;"><strong>Date:</strong> ${data.date}</p>
                        </div>
                    </div>

                    <div style="margin-bottom: 40px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid #f3f4f6;">
                                    <th style="text-align: left; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">DESCRIPTION OF SERVICES</th>
                                    <th style="text-align: right; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 20px 0; font-weight: 500;">${data.description}</td>
                                    <td style="padding: 20px 0; text-align: right; font-weight: 700; font-size: 1.1rem;">$${data.amount}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="background: #f9fafb; padding: 25px; border-radius: 12px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #6b7280; font-weight: 600;">Total Amount:</span>
                            <span style="font-weight: 700;">$${data.amount}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #10b981; font-weight: 600;">Amount Paid:</span>
                            <span style="font-weight: 700; color: #10b981;">-$${data.paid}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                            <span style="color: ${statusColor}; font-weight: 700; font-size: 1.1rem;">Balance Due:</span>
                            <span style="font-weight: 800; color: ${statusColor}; font-size: 1.1rem;">$${data.balance}</span>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 60px; font-size: 0.8rem; color: #9ca3af; border-top: 1px dashed #e5e7eb; padding-top: 20px;">
                        <p>Thank you for trusting LuminaCare with your health journey.</p>
                        <p style="margin-top: 5px;">This is a computer-generated invoice and does not require a physical stamp.</p>
                    </div>
                </div>
            `;

            const opt = {
                margin: 0,
                filename: `LuminaCare_Invoice_${data.id.padStart(5, '0')}.pdf`,
                image: {
                    type: 'jpeg',
                    quality: 1
                },
                html2canvas: {
                    scale: 3,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'in',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>

</html>