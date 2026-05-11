<?php
// pharmacy/dashboard.php - Pharmacist Main Dashboard & Fulfillment Queue
require_once '../config/config.php';

if (!isset($_SESSION['pharmacist_id']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: login.php");
    exit();
}

$pharmacist_id = $_SESSION['pharmacist_id'];
$success_msg = '';
$error_msg = '';

// --- Auto-Setup: Ensure Status Fields and Pricing exist ---
try {
    // 1. Update Medications Table
    $pdo->exec("ALTER TABLE medications ADD COLUMN IF NOT EXISTS dispensing_status enum('Pending', 'Dispensed', 'Cancelled') DEFAULT 'Pending'");
    $pdo->exec("ALTER TABLE medications ADD COLUMN IF NOT EXISTS dispensed_at datetime DEFAULT NULL");
    $pdo->exec("ALTER TABLE medications ADD COLUMN IF NOT EXISTS dispensed_by_id int(11) DEFAULT NULL");
    $pdo->exec("ALTER TABLE medications ADD COLUMN IF NOT EXISTS dispensed_qty int(11) DEFAULT NULL");

    // 2. Update Inventory Table for Pricing
    $pdo->exec("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS unit_price decimal(10,2) DEFAULT 0.00");
} catch (PDOException $e) { /* Ignore if already exists */
}

// --- Form Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Fulfill (Dispense) Medication & Auto-Generate Bill
    if (isset($_POST['dispense_med'])) {
        $med_id = filter_input(INPUT_POST, 'med_id', FILTER_VALIDATE_INT);
        $item_id = filter_input(INPUT_POST, 'inventory_item_id', FILTER_VALIDATE_INT);
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $dispense_qty = filter_input(INPUT_POST, 'dispense_qty', FILTER_VALIDATE_INT);

        if ($med_id && $item_id && $patient_id && $dispense_qty && $dispense_qty > 0) {
            try {
                $pdo->beginTransaction();

                // 1. Fetch drug details and check inventory quantity
                $invStmt = $pdo->prepare("SELECT item_name, unit_price, quantity FROM inventory WHERE item_id = ?");
                $invStmt->execute([$item_id]);
                $drug = $invStmt->fetch();

                if ($drug && $drug['quantity'] >= $dispense_qty) {
                    // 2. Update medication status to Dispensed and log quantity
                    $stmt = $pdo->prepare("UPDATE medications SET dispensing_status = 'Dispensed', dispensed_at = NOW(), dispensed_by_id = ?, dispensed_qty = ? WHERE med_id = ?");
                    $stmt->execute([$pharmacist_id, $dispense_qty, $med_id]);

                    // 3. Deduct accurate amount from Inventory
                    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_id = ?");
                    $stmt->execute([$dispense_qty, $item_id]);

                    // 4. AUTO-BILLING: Calculate total cost based on quantity
                    $amount = $drug['unit_price'] * $dispense_qty;
                    $bill_desc = "Pharmacy: " . $drug['item_name'] . " (Qty: " . $dispense_qty . ")";
                    $due_date = date('Y-m-d'); // Due immediately

                    $billStmt = $pdo->prepare("INSERT INTO billing (patient_id, description, amount, status, due_date) VALUES (?, ?, ?, 'Unpaid', ?)");
                    $billStmt->execute([$patient_id, $bill_desc, $amount, $due_date]);

                    $pdo->commit();
                    $success_msg = "Successfully dispensed " . $dispense_qty . " units. A total charge of $" . number_format($amount, 2) . " has been routed to billing.";
                } else {
                    $pdo->rollBack();
                    $error_msg = "Insufficient inventory stock! Only " . ($drug ? $drug['quantity'] : 0) . " units available.";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Failed to dispense medication or generate bill: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please verify all required fields (Quantity must be at least 1).";
        }
    }
}

// --- Fetch Data ---
try {
    // 1. Prescription Queue (Pending Medications)
    $stmt = $pdo->query("
        SELECT m.*, p.first_name AS pat_first, p.last_name AS pat_last, d.last_name AS doc_last 
        FROM medications m 
        JOIN patients p ON m.patient_id = p.patient_id 
        JOIN doctors d ON m.doctor_id = d.doctor_id 
        WHERE m.dispensing_status = 'Pending' AND m.is_active = 1
        ORDER BY m.created_at ASC
    ");
    $queue = $stmt->fetchAll();

    // 2. Counts for stats
    $pending_count = count($queue);
    $dispensed_today = $pdo->query("SELECT COUNT(*) FROM medications WHERE dispensing_status = 'Dispensed' AND DATE(dispensed_at) = CURDATE()")->fetchColumn();

    // 3. Low stock medicines
    $low_stock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE category = 'Medicine' AND quantity <= reorder_level")->fetchColumn();

    // 4. Pharmacy Inventory (Medicines only)
    $inventory = $pdo->query("SELECT * FROM inventory WHERE category = 'Medicine' ORDER BY item_name ASC")->fetchAll();

    // 5. Dispensed History
    $histStmt = $pdo->query("
        SELECT m.*, p.first_name AS pat_first, p.last_name AS pat_last, d.last_name AS doc_last 
        FROM medications m 
        JOIN patients p ON m.patient_id = p.patient_id 
        JOIN doctors d ON m.doctor_id = d.doctor_id 
        WHERE m.dispensing_status = 'Dispensed'
        ORDER BY m.dispensed_at DESC LIMIT 50
    ");
    $history = $histStmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading pharmacy data.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Dashboard - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #059669;
            --secondary-color: #10b981;
            --accent-color: #fbbf24;
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
            background-color: rgba(16, 185, 129, 0.08);
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
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-capsule-pill me-2"></i>Pharmacy</a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu mt-3">
            <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Fulfillment Queue</a>
            <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Medicine Stock</a>
            <div class="mt-4 px-4 small text-muted text-uppercase fw-bold">Account</div>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Settings</a>
        </div>
        <div class="mt-auto p-3 border-top"><a href="../admin/logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold">Fulfillment Center</h4>
                    <p class="text-muted mb-0 small">Welcome back, Ph. <?php echo htmlspecialchars($_SESSION['last_name']); ?></p>
                </div>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Active Dispensing</span>
        </header>

        <?php if ($success_msg): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Pending Fulfillment</small>
                        <h3 class="mb-0 fw-bold"><?php echo $pending_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-all"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Dispensed Today</small>
                        <h3 class="mb-0 fw-bold"><?php echo $dispensed_today; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    <div><small class="text-muted text-uppercase fw-bold">Low Stock Alert</small>
                        <h3 class="mb-0 fw-bold <?php echo $low_stock > 0 ? 'text-danger' : ''; ?>"><?php echo $low_stock; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4 border-bottom-0" id="pharmacyTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                    Pending Queue
                    <?php if ($pending_count > 0): ?><span class="badge bg-warning text-dark ms-1 rounded-pill"><?php echo $pending_count; ?></span><?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                    Dispensed History
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="pharmacyTabsContent">

            <!-- Pending Queue Tab -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Patient</th>
                                    <th>Prescribed Drug</th>
                                    <th>Dosage / Freq</th>
                                    <th>Prescribed By</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_count > 0): ?>
                                    <?php foreach ($queue as $m): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['pat_first'] . ' ' . $m['pat_last']); ?></div>
                                                <small class="text-muted">ID: #<?php echo str_pad($m['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                            </td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($m['medication_name']); ?></td>
                                            <td><?php echo htmlspecialchars($m['dosage']); ?><br><small class="text-muted"><?php echo htmlspecialchars($m['frequency']); ?></small></td>
                                            <td>Dr. <?php echo htmlspecialchars($m['doc_last']); ?></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-success rounded-pill fw-bold px-3" data-bs-toggle="modal" data-bs-target="#dispenseModal<?php echo $m['med_id']; ?>">Fulfill Order</button>
                                            </td>
                                        </tr>
                                        <!-- Dispense Modal -->
                                        <div class="modal fade" id="dispenseModal<?php echo $m['med_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 rounded-4 shadow-lg">
                                                    <div class="modal-header border-bottom-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Dispense & Generate Charge</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body pt-3">
                                                        <div class="p-3 bg-light rounded-3 border mb-3">
                                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($m['medication_name']); ?></h6>
                                                            <p class="small text-muted mb-0">For: <?php echo htmlspecialchars($m['pat_first'] . ' ' . $m['pat_last']); ?></p>
                                                            <small class="text-danger fw-bold">Instructions: <?php echo htmlspecialchars($m['instructions'] ?? 'No special instructions'); ?></small>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="med_id" value="<?php echo $m['med_id']; ?>">
                                                            <input type="hidden" name="patient_id" value="<?php echo $m['patient_id']; ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold text-muted">Select Item from Stock (Pricing Linked) *</label>
                                                                <select name="inventory_item_id" class="form-select bg-light border-0" required>
                                                                    <option value="">-- Match Stock Item --</option>
                                                                    <?php foreach ($inventory as $inv): ?>
                                                                        <option value="<?php echo $inv['item_id']; ?>" <?php echo (stripos($m['medication_name'], $inv['item_name']) !== false) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($inv['item_name']); ?> - $<?php echo number_format($inv['unit_price'], 2); ?> (Stock: <?php echo $inv['quantity']; ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> Selecting an item calculates the bill sent to the cashier.</small>
                                                            </div>

                                                            <!-- New Quantity Input -->
                                                            <div class="mb-4">
                                                                <label class="form-label small fw-bold text-muted">Quantity Dispensed *</label>
                                                                <input type="number" name="dispense_qty" class="form-control bg-light border-0" value="1" min="1" required>
                                                                <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">This amount will be deducted from inventory and multiplied by the unit price for billing.</small>
                                                            </div>

                                                            <button type="submit" name="dispense_med" class="btn btn-success w-100 rounded-pill fw-bold py-2">Confirm, Dispense & Bill</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-check-circle fs-1 d-block mb-2 opacity-50"></i>No pending prescriptions. All caught up!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Patient</th>
                                    <th>Dispensed Drug</th>
                                    <th>Dosage / Freq</th>
                                    <th>Prescribed By</th>
                                    <th>Dispensed On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($history) > 0): ?>
                                    <?php foreach ($history as $h): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($h['pat_first'] . ' ' . $h['pat_last']); ?></div>
                                                <small class="text-muted">ID: #<?php echo str_pad($h['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                            </td>
                                            <td class="fw-bold text-success">
                                                <?php echo htmlspecialchars($h['medication_name']); ?>
                                                <?php if ($h['dispensed_qty']): ?>
                                                    <span class="badge bg-light text-dark border ms-2">Qty: <?php echo $h['dispensed_qty']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($h['dosage']); ?><br><small class="text-muted"><?php echo htmlspecialchars($h['frequency']); ?></small></td>
                                            <td>Dr. <?php echo htmlspecialchars($h['doc_last']); ?></td>
                                            <td class="small text-muted"><?php echo date('M d, Y - h:i A', strtotime($h['dispensed_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-clock-history fs-1 d-block mb-2 opacity-50"></i>No dispensed medications in history.</td>
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
    </script>
</body>

</html>