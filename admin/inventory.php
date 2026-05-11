<?php
// admin/inventory.php - Manage Hospital/Clinic Inventory
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle CRUD Operations for Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add New Item
    if (isset($_POST['add_item'])) {
        $item_name = trim(filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING));
        $category = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING));
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $unit = trim(filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING));
        $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);
        $supplier_info = trim(filter_input(INPUT_POST, 'supplier_info', FILTER_SANITIZE_STRING));

        if (!empty($item_name) && $quantity !== false && $reorder_level !== false) {
            try {
                $stmt = $pdo->prepare("INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, supplier_info) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$item_name, $category, $quantity, $unit, $reorder_level, $supplier_info])) {
                    $success_msg = "New item added to inventory successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to add item to inventory.";
            }
        } else {
            $error_msg = "Please fill in all required fields with valid data.";
        }
    }

    // 2. Update Existing Item
    elseif (isset($_POST['update_item'])) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $item_name = trim(filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING));
        $category = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING));
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $unit = trim(filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING));
        $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);
        $supplier_info = trim(filter_input(INPUT_POST, 'supplier_info', FILTER_SANITIZE_STRING));

        if ($item_id && !empty($item_name) && $quantity !== false && $reorder_level !== false) {
            try {
                $stmt = $pdo->prepare("UPDATE inventory SET item_name = ?, category = ?, quantity = ?, unit = ?, reorder_level = ?, supplier_info = ? WHERE item_id = ?");
                if ($stmt->execute([$item_name, $category, $quantity, $unit, $reorder_level, $supplier_info, $item_id])) {
                    $success_msg = "Inventory item updated successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to update inventory item.";
            }
        }
    }

    // 3. Delete Item
    elseif (isset($_POST['delete_item'])) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE item_id = ?");
            if ($stmt->execute([$item_id])) {
                $success_msg = "Item permanently deleted from inventory.";
            }
        } catch (PDOException $e) {
            $error_msg = "Cannot delete this item.";
        }
    }

    // 4. Quick Stock Adjustment (Add/Subtract)
    elseif (isset($_POST['adjust_stock'])) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $adjustment = filter_input(INPUT_POST, 'adjustment', FILTER_VALIDATE_INT);

        if ($item_id && $adjustment !== false) {
            try {
                // Determine if adding or subtracting based on sign
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_id = ? AND (quantity + ?) >= 0");
                if ($stmt->execute([$adjustment, $item_id, $adjustment])) {
                    if ($stmt->rowCount() > 0) {
                        $success_msg = "Stock levels adjusted successfully.";
                    } else {
                        $error_msg = "Adjustment failed. Stock cannot drop below zero.";
                    }
                }
            } catch (PDOException $e) {
                $error_msg = "Database error during stock adjustment.";
            }
        }
    }
}

// Auto-Setup & Fetch Data
try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'inventory'")->rowCount();

    if ($checkTable == 0) {
        // Auto-create the table if it does not exist
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `inventory` (
          `item_id` int(11) NOT NULL AUTO_INCREMENT,
          `item_name` varchar(100) NOT NULL,
          `category` varchar(50) NOT NULL,
          `quantity` int(11) NOT NULL DEFAULT 0,
          `unit` varchar(20) NOT NULL,
          `reorder_level` int(11) NOT NULL DEFAULT 10,
          `supplier_info` varchar(255) DEFAULT NULL,
          `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($createTableSQL);
        $success_msg = "System Auto-Setup: 'inventory' table created successfully.";

        // Insert some dummy data for initial setup
        $pdo->exec("INSERT INTO `inventory` (`item_name`, `category`, `quantity`, `unit`, `reorder_level`, `supplier_info`) VALUES 
            ('Paracetamol 500mg', 'Medicine', 150, 'Boxes', 50, 'PharmaCorp Inc.'),
            ('Sterile Syringes (5ml)', 'Lab Supply', 500, 'Pieces', 100, 'MedEquip Logistics'),
            ('Ultrasound Gel', 'Equipment', 12, 'Bottles', 15, 'HealthTech Providers'),
            ('Prenatal Vitamins', 'Medicine', 300, 'Bottles', 50, 'Maternal Care Ltd.')
        ");
    }

    // Fetch all inventory items
    $stmt = $pdo->query("SELECT * FROM inventory ORDER BY item_name ASC");
    $inventory_items = $stmt->fetchAll();

    // Calculate Summary Stats
    $total_items = count($inventory_items);
    $low_stock_count = 0;
    foreach ($inventory_items as $item) {
        if ($item['quantity'] <= $item['reorder_level']) {
            $low_stock_count++;
        }
    }
} catch (PDOException $e) {
    $error_msg = "Error loading inventory data: " . $e->getMessage();
    $inventory_items = [];
    $total_items = 0;
    $low_stock_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Admin</title>
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

        .low-stock-row {
            background-color: rgba(220, 53, 69, 0.05);
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
            <div class="nav-item"><a href="inventory.php" class="nav-link active"><i class="bi bi-box-seam"></i> Inventory Management</a></div>
            <div class="nav-item"><a href="billing.php" class="nav-link"><i class="bi bi-receipt"></i> Billing & Finances</a></div>

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
                <h3 class="mb-0 fw-bold text-dark">Central Inventory</h3>
            </div>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="bi bi-plus-circle-fill me-1"></i> Add New Item
            </button>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-box2-fill"></i></div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 small fw-bold">Total Unique Items</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $total_items; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card <?php echo $low_stock_count > 0 ? 'border-danger border border-2 border-opacity-50' : ''; ?>">
                    <div class="metric-icon <?php echo $low_stock_count > 0 ? 'bg-danger text-danger' : 'bg-success text-success'; ?> bg-opacity-10">
                        <i class="bi <?php echo $low_stock_count > 0 ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'; ?>"></i>
                    </div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 small fw-bold">Low Stock Alerts</h6>
                        <h3 class="mb-0 fw-bold <?php echo $low_stock_count > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $low_stock_count; ?> Items</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Item Name / Category</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Supplier Info</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): ?>
                            <?php $is_low_stock = ($item['quantity'] <= $item['reorder_level']); ?>

                            <tr class="<?php echo $is_low_stock ? 'low-stock-row' : ''; ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-0 mt-1"><?php echo htmlspecialchars($item['category']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="mb-0 fw-bold <?php echo $is_low_stock ? 'text-danger' : 'text-dark'; ?>">
                                            <?php echo $item['quantity']; ?>
                                        </h5>
                                        <span class="small text-muted"><?php echo htmlspecialchars($item['unit']); ?></span>
                                    </div>
                                    <?php if ($is_low_stock): ?>
                                        <small class="text-danger fw-bold"><i class="bi bi-arrow-down-circle"></i> Low Stock</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?php echo $item['reorder_level']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($item['supplier_info'] ?? 'N/A'); ?></small></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#adjustStockModal<?php echo $item['item_id']; ?>" title="Quick Add/Remove Stock"><i class="bi bi-plus-slash-minus"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editItemModal<?php echo $item['item_id']; ?>" title="Edit Details"><i class="bi bi-pencil-square"></i></button>
                                        <form method="POST" action="" onsubmit="return confirm('Permanently delete this item from inventory?');">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger" title="Delete Item"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Adjust Stock Modal -->
                            <div class="modal fade" id="adjustStockModal<?php echo $item['item_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h6 class="modal-title fw-bold text-success"><i class="bi bi-arrow-left-right me-1"></i> Adjust Stock</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <p class="small text-muted mb-3">Adjust stock for: <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>Current: <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></p>
                                            <form method="POST" action="">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <div class="input-group mb-3">
                                                    <input type="number" name="adjustment" class="form-control bg-light" placeholder="e.g. +50 or -10" required>
                                                    <span class="input-group-text"><?php echo htmlspecialchars($item['unit']); ?></span>
                                                </div>
                                                <button type="submit" name="adjust_stock" class="btn btn-success w-100 rounded-pill fw-bold">Apply Adjustment</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Item Modal -->
                            <div class="modal fade" id="editItemModal<?php echo $item['item_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold">Edit Inventory Item</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <form method="POST" action="">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Item Name *</label>
                                                        <input type="text" class="form-control bg-light border-0" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Category *</label>
                                                        <select name="category" class="form-select bg-light border-0" required>
                                                            <option value="Medicine" <?php echo ($item['category'] == 'Medicine') ? 'selected' : ''; ?>>Medicine / Pharmacy</option>
                                                            <option value="Lab Supply" <?php echo ($item['category'] == 'Lab Supply') ? 'selected' : ''; ?>>Lab Supply</option>
                                                            <option value="Equipment" <?php echo ($item['category'] == 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                                                            <option value="General" <?php echo ($item['category'] == 'General') ? 'selected' : ''; ?>>General Consumable</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Unit of Measurement *</label>
                                                        <input type="text" class="form-control bg-light border-0" name="unit" value="<?php echo htmlspecialchars($item['unit']); ?>" placeholder="e.g. Boxes, Vials, Pieces" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Absolute Quantity *</label>
                                                        <input type="number" class="form-control bg-light border-0" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Low Stock Threshold *</label>
                                                        <input type="number" class="form-control bg-light border-0" name="reorder_level" value="<?php echo $item['reorder_level']; ?>" min="0" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Supplier Information</label>
                                                        <input type="text" class="form-control bg-light border-0" name="supplier_info" value="<?php echo htmlspecialchars($item['supplier_info']); ?>" placeholder="Vendor name, contact, etc.">
                                                    </div>
                                                </div>
                                                <div class="d-grid mt-4">
                                                    <button type="submit" name="update_item" class="btn btn-primary rounded-pill py-2 fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        <?php if (empty($inventory_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-box2 fs-1 d-block mb-3 opacity-25"></i> No items found in the inventory.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Add to Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Item Name *</label>
                                <input type="text" class="form-control bg-light border-0" name="item_name" placeholder="e.g. Ibuprofen 500mg, Surgical Masks" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Category *</label>
                                <select name="category" class="form-select bg-light border-0" required>
                                    <option value="Medicine">Medicine / Pharmacy</option>
                                    <option value="Lab Supply">Lab Supply</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="General">General Consumable</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Unit of Measurement *</label>
                                <input type="text" class="form-control bg-light border-0" name="unit" placeholder="e.g. Boxes, Vials, Packs" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Initial Quantity *</label>
                                <input type="number" class="form-control bg-light border-0" name="quantity" value="0" min="0" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Low Stock Threshold *</label>
                                <input type="number" class="form-control bg-light border-0" name="reorder_level" value="10" min="0" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Supplier Information</label>
                                <input type="text" class="form-control bg-light border-0" name="supplier_info" placeholder="Vendor name, contact, etc.">
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="add_item" class="btn btn-dark rounded-pill py-2 fw-bold">Save New Item</button>
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