<?php
// lab/inventory.php - Lab Tech specific inventory management
require_once '../config/config.php';

// Secure the page
if (!isset($_SESSION['tech_id']) || $_SESSION['role'] !== 'lab_tech') {
    header("Location: login.php");
    exit();
}

$tech_id = $_SESSION['tech_id'];
$success_msg = '';
$error_msg = '';

// --- Form Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Quick Stock Adjustment (Add/Subtract)
    if (isset($_POST['adjust_stock'])) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $adjustment = filter_input(INPUT_POST, 'adjustment', FILTER_VALIDATE_INT);

        if ($item_id && $adjustment !== false) {
            try {
                // Ensure the item belongs to Lab Supply for security/scope
                $check = $pdo->prepare("SELECT item_id FROM inventory WHERE item_id = ? AND category = 'Lab Supply'");
                $check->execute([$item_id]);

                if ($check->rowCount() > 0) {
                    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_id = ? AND (quantity + ?) >= 0");
                    if ($stmt->execute([$adjustment, $item_id, $adjustment])) {
                        if ($stmt->rowCount() > 0) {
                            $success_msg = "Stock levels adjusted successfully.";
                        } else {
                            $error_msg = "Adjustment failed. Stock cannot drop below zero.";
                        }
                    }
                } else {
                    $error_msg = "Unauthorized access to non-lab inventory.";
                }
            } catch (PDOException $e) {
                $error_msg = "Database error during stock adjustment.";
            }
        }
    }
}

// --- Fetch Data ---
try {
    // 1. Fetch Lab Supplies
    $stmt = $pdo->query("SELECT * FROM inventory WHERE category = 'Lab Supply' ORDER BY item_name ASC");
    $lab_items = $stmt->fetchAll();

    // 2. Count Alerts for sidebar badge
    $inv_stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE category = 'Lab Supply' AND quantity <= reorder_level");
    $lab_inventory_alerts = $inv_stmt->fetchColumn();
} catch (PDOException $e) {
    $error_msg = "Error loading inventory data.";
    $lab_items = [];
    $lab_inventory_alerts = 0;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Inventory - LuminaCare</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #d97706;
            --secondary-color: #f59e0b;
            --accent-color: #fcd34d;
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
            color: var(--text-dark);
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
            background-color: rgba(245, 158, 11, 0.08);
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

        .low-stock-row {
            background-color: rgba(220, 53, 69, 0.03);
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
            <a href="#" class="sidebar-brand"><i class="bi bi-droplet-half me-2"></i>Lab<span>Portal</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>

        <div class="nav-menu">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Diagnostics Board</a>
            </div>
            <div class="nav-item">
                <a href="inventory.php" class="nav-link active">
                    <i class="bi bi-box-seam-fill"></i> Lab Supplies
                    <?php if ($lab_inventory_alerts > 0): ?>
                        <span class="badge bg-info text-dark rounded-pill ms-auto"><?php echo $lab_inventory_alerts; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item">
                <a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Settings</a>
            </div>
        </div>

        <div class="logout-wrapper">
            <a href="../admin/logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">Laboratory Inventory</h4>
                    <p class="text-muted mb-0 small">Manage reagents, diagnostic kits, and medical supplies.</p>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-light text-dark border p-2 rounded-pill"><i class="bi bi-shield-check text-success me-1"></i> Authorized Station Access</span>
            </div>
        </header>

        <!-- Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Inventory List -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">Diagnostics & Supplies Catalog</h6>
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill"><?php echo count($lab_items); ?> Items Listed</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Item Name</th>
                            <th>Current Level</th>
                            <th>Status</th>
                            <th>Minimum Required</th>
                            <th class="text-end pe-4">Stock Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lab_items) > 0): ?>
                            <?php foreach ($lab_items as $item): ?>
                                <?php $isLow = ($item['quantity'] <= $item['reorder_level']); ?>
                                <tr class="<?php echo $isLow ? 'low-stock-row' : ''; ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                        <small class="text-muted">Supplier: <?php echo htmlspecialchars($item['supplier_info'] ?? 'General Procurement'); ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fs-5 fw-bold <?php echo $isLow ? 'text-danger' : 'text-dark'; ?>"><?php echo $item['quantity']; ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['unit']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isLow): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill"><i class="bi bi-arrow-down-circle"></i> Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill"><i class="bi bi-check2"></i> Sufficient</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?php echo $item['reorder_level']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#adjustModal<?php echo $item['item_id']; ?>">
                                            <i class="bi bi-plus-slash-minus me-1"></i> Adjust Stock
                                        </button>
                                    </td>
                                </tr>

                                <!-- Adjust Modal -->
                                <div class="modal fade" id="adjustModal<?php echo $item['item_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-4 shadow-lg">
                                            <div class="modal-header border-bottom-0 pb-0">
                                                <h6 class="modal-title fw-bold">Update Level</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body pt-3">
                                                <p class="small text-muted mb-3">Updating: <strong><?php echo htmlspecialchars($item['item_name']); ?></strong></p>
                                                <form action="" method="POST">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <div class="input-group mb-3 shadow-sm">
                                                        <input type="number" name="adjustment" class="form-control border-0 bg-light" placeholder="e.g. -5 or +10" required>
                                                        <span class="input-group-text border-0 bg-light"><?php echo htmlspecialchars($item['unit']); ?></span>
                                                    </div>
                                                    <button type="submit" name="adjust_stock" class="btn btn-warning w-100 rounded-pill fw-bold" style="background-color: var(--primary-color); border: none; color: white;">Apply Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam fs-2 d-block mb-2 opacity-50"></i>
                                    No lab-specific supplies found. Please contact administration to add items to the 'Lab Supply' category.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 p-4 bg-white rounded-4 border border-warning border-opacity-25 shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-info-circle text-warning me-2"></i> Supply Usage Tip</h6>
                    <p class="small text-muted mb-0">Remember to adjust the stock every time you open a new batch of reagents or use specialized diagnostic cartridges. Maintaining accurate stock levels ensures the clinic never runs out of critical testing materials.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-sm btn-light border rounded-pill px-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Inventory Sheet</button>
                </div>
            </div>
        </div>

    </main>

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