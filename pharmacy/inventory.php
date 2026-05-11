<?php
// pharmacy/inventory.php - Pharmacist Medicine Stock Management
require_once '../config/config.php';

// Secure the page
if (!isset($_SESSION['pharmacist_id']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: login.php");
    exit();
}

$pharmacist_id = $_SESSION['pharmacist_id'];
$success_msg = '';
$error_msg = '';

// --- Auto-Setup: Ensure unit_price column exists (Integrity check) ---
try {
    $pdo->exec("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS unit_price decimal(10,2) DEFAULT 0.00");
} catch (PDOException $e) { /* Ignore */
}

// --- Form Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add New Medicine
    if (isset($_POST['add_medicine'])) {
        $name = trim(filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING));
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $unit = trim(filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING));
        $price = filter_input(INPUT_POST, 'unit_price', FILTER_VALIDATE_FLOAT);
        $reorder = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);
        $supplier = trim(filter_input(INPUT_POST, 'supplier_info', FILTER_SANITIZE_STRING));

        if (!empty($name) && $qty !== false && $price !== false) {
            try {
                $stmt = $pdo->prepare("INSERT INTO inventory (item_name, category, quantity, unit, unit_price, reorder_level, supplier_info) VALUES (?, 'Medicine', ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $qty, $unit, $price, $reorder, $supplier])) {
                    $success_msg = "New medicine added to stock.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to add medicine.";
            }
        }
    }

    // 2. Quick Stock Adjustment
    if (isset($_POST['adjust_stock'])) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $adjustment = filter_input(INPUT_POST, 'adjustment', FILTER_VALIDATE_INT);

        if ($item_id && $adjustment !== false) {
            try {
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_id = ? AND category = 'Medicine' AND (quantity + ?) >= 0");
                if ($stmt->execute([$adjustment, $item_id, $adjustment])) {
                    if ($stmt->rowCount() > 0) {
                        $success_msg = "Stock levels updated.";
                    } else {
                        $error_msg = "Adjustment failed. Quantity cannot be negative.";
                    }
                }
            } catch (PDOException $e) {
                $error_msg = "Database error.";
            }
        }
    }

    // 3. Update Price & Details
    if (isset($_POST['update_details'])) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $price = filter_input(INPUT_POST, 'unit_price', FILTER_VALIDATE_FLOAT);
        $reorder = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);

        if ($item_id && $price !== false) {
            try {
                $stmt = $pdo->prepare("UPDATE inventory SET unit_price = ?, reorder_level = ? WHERE item_id = ? AND category = 'Medicine'");
                $stmt->execute([$price, $reorder, $item_id]);
                $success_msg = "Medicine details updated.";
            } catch (PDOException $e) {
                $error_msg = "Update failed.";
            }
        }
    }
}

// --- Fetch Data ---
try {
    // 1. Fetch Medicines
    $stmt = $pdo->query("SELECT * FROM inventory WHERE category = 'Medicine' ORDER BY item_name ASC");
    $medicines = $stmt->fetchAll();

    // 2. Stats
    $low_stock_count = 0;
    foreach ($medicines as $m) if ($m['quantity'] <= $m['reorder_level']) $low_stock_count++;
} catch (PDOException $e) {
    $error_msg = "Error loading inventory.";
    $medicines = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Stock - LuminaCare Pharmacy</title>
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

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .low-stock-row {
            background-color: rgba(220, 53, 69, 0.03);
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
            <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Fulfillment Queue</a>
            <a href="inventory.php" class="nav-link active"><i class="bi bi-box-seam-fill"></i> Medicine Stock</a>
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
                    <h4 class="mb-0 fw-bold">Medicine Inventory</h4>
                    <p class="text-muted mb-0 small">Manage pricing and availability of pharmaceuticals.</p>
                </div>
            </div>
            <button class="btn btn-success rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addMedModal"><i class="bi bi-plus-lg me-2"></i>Add New Drug</button>
        </header>

        <?php if ($success_msg): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?></div><?php endif; ?>

        <?php if ($low_stock_count > 0): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3">
                <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
                <div>
                    <h6 class="mb-0 fw-bold">Low Stock Warning</h6>
                    <p class="mb-0 small">You have <strong><?php echo $low_stock_count; ?></strong> items below reorder level. Please initiate restock requests.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Medicine Name</th>
                            <th>Current Stock</th>
                            <th>Unit Price</th>
                            <th>Min. Level</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($medicines) > 0): ?>
                            <?php foreach ($medicines as $m): ?>
                                <?php $isLow = ($m['quantity'] <= $m['reorder_level']); ?>
                                <tr class="<?php echo $isLow ? 'low-stock-row' : ''; ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['item_name']); ?></div>
                                        <small class="text-muted">Unit: <?php echo htmlspecialchars($m['unit']); ?></small>
                                    </td>
                                    <td>
                                        <span class="fs-5 fw-bold <?php echo $isLow ? 'text-danger' : 'text-dark'; ?>"><?php echo $m['quantity']; ?></span>
                                        <?php if ($isLow): ?><span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.6rem;">LOW</span><?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-success">$<?php echo number_format($m['unit_price'], 2); ?></td>
                                    <td class="text-muted"><?php echo $m['reorder_level']; ?></td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjustModal<?php echo $m['item_id']; ?>"><i class="bi bi-plus-slash-minus"></i> Adjust</button>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $m['item_id']; ?>"><i class="bi bi-pencil"></i> Edit</button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Adjust Modal -->
                                <div class="modal fade" id="adjustModal<?php echo $m['item_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-4 shadow-lg">
                                            <div class="modal-header border-bottom-0 pb-0">
                                                <h6>Quick Stock Adjust</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-muted">Item: <strong><?php echo htmlspecialchars($m['item_name']); ?></strong></p>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="item_id" value="<?php echo $m['item_id']; ?>">
                                                    <input type="number" name="adjustment" class="form-control mb-3" placeholder="e.g. +50 or -10" required>
                                                    <button type="submit" name="adjust_stock" class="btn btn-primary w-100 rounded-pill fw-bold">Update Quantity</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Details Modal -->
                                <div class="modal fade" id="editModal<?php echo $m['item_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-4 shadow-lg">
                                            <div class="modal-header border-bottom-0 pb-0">
                                                <h5 class="fw-bold">Update Medicine Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body pt-3">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="item_id" value="<?php echo $m['item_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Unit Price ($) *</label>
                                                        <input type="number" step="0.01" name="unit_price" class="form-control bg-light" value="<?php echo $m['unit_price']; ?>" required>
                                                        <small class="text-muted">This price will be used for automated patient billing.</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Min. Reorder Level *</label>
                                                        <input type="number" name="reorder_level" class="form-control bg-light" value="<?php echo $m['reorder_level']; ?>" required>
                                                    </div>
                                                    <button type="submit" name="update_details" class="btn btn-primary w-100 rounded-pill fw-bold py-2">Save Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-box-seam fs-1 d-block mb-2 opacity-50"></i>No medicines found in stock.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Medicine Modal -->
    <div class="modal fade" id="addMedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="fw-bold">New Inventory Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label small fw-bold">Medicine Name *</label><input type="text" name="item_name" class="form-control" placeholder="e.g. Labetalol 200mg" required></div>
                            <div class="col-6"><label class="form-label small fw-bold">Initial Qty *</label><input type="number" name="quantity" class="form-control" value="0" required></div>
                            <div class="col-6"><label class="form-label small fw-bold">Unit *</label><input type="text" name="unit" class="form-control" placeholder="Tabs/Vials" required></div>
                            <div class="col-6"><label class="form-label small fw-bold">Price per Unit ($) *</label><input type="number" step="0.01" name="unit_price" class="form-control" required></div>
                            <div class="col-6"><label class="form-label small fw-bold">Min Level *</label><input type="number" name="reorder_level" class="form-control" value="10" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Supplier</label><input type="text" name="supplier_info" class="form-control"></div>
                        </div>
                        <button type="submit" name="add_medicine" class="btn btn-primary w-100 rounded-pill fw-bold py-2 mt-4">Save Entry</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>