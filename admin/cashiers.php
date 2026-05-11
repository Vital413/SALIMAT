<?php
// admin/cashiers.php - Manage Cashier & Billing Staff
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle CRUD Operations for Cashiers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add New Cashier
    if (isset($_POST['add_cashier'])) {
        $fname = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
        $lname = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $password = $_POST['password'];

        if (!empty($fname) && !empty($lname) && !empty($email) && !empty($password)) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO cashiers (first_name, last_name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                if ($stmt->execute([$fname, $lname, $email, $phone, $hash])) {
                    $success_msg = "Cashier successfully added to the system.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to add cashier. Ensure the email is not already in use.";
            }
        } else {
            $error_msg = "Please fill in all required fields.";
        }
    }

    // 2. Toggle Status (Active/Suspend)
    elseif (isset($_POST['toggle_status'])) {
        $cashier_id = filter_input(INPUT_POST, 'cashier_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'new_status', FILTER_VALIDATE_INT);

        if ($cashier_id) {
            try {
                $stmt = $pdo->prepare("UPDATE cashiers SET is_active = ? WHERE cashier_id = ?");
                if ($stmt->execute([$new_status, $cashier_id])) {
                    $success_msg = $new_status ? "Cashier account activated." : "Cashier account suspended.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to update status.";
            }
        }
    }

    // 3. Delete Cashier
    elseif (isset($_POST['delete_cashier'])) {
        $cashier_id = filter_input(INPUT_POST, 'cashier_id', FILTER_VALIDATE_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM cashiers WHERE cashier_id = ?");
            if ($stmt->execute([$cashier_id])) {
                $success_msg = "Cashier account permanently deleted.";
            }
        } catch (PDOException $e) {
            $error_msg = "Cannot delete cashier. Ensure all their associated records are cleared first.";
        }
    }

    // 4. Update Cashier Details
    elseif (isset($_POST['update_cashier'])) {
        $cashier_id = filter_input(INPUT_POST, 'cashier_id', FILTER_VALIDATE_INT);
        $fname = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
        $lname = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $new_password = $_POST['new_password'] ?? '';

        try {
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE cashiers SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ? WHERE cashier_id = ?");
                $stmt->execute([$fname, $lname, $email, $phone, $hash, $cashier_id]);
                $success_msg = "Cashier profile and password successfully updated.";
            } else {
                $stmt = $pdo->prepare("UPDATE cashiers SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE cashier_id = ?");
                $stmt->execute([$fname, $lname, $email, $phone, $cashier_id]);
                $success_msg = "Cashier profile successfully updated.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to update cashier. The email might already be registered.";
        }
    }
}

// Auto-Setup & Fetch all Cashiers
try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'cashiers'")->rowCount();

    if ($checkTable == 0) {
        // Auto-create the table if it does not exist
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `cashiers` (
          `cashier_id` int(11) NOT NULL AUTO_INCREMENT,
          `first_name` varchar(50) NOT NULL,
          `last_name` varchar(50) NOT NULL,
          `email` varchar(100) NOT NULL UNIQUE,
          `phone` varchar(20) DEFAULT NULL,
          `password_hash` varchar(255) NOT NULL,
          `is_active` tinyint(1) DEFAULT 1,
          `created_at` timestamp DEFAULT current_timestamp(),
          PRIMARY KEY (`cashier_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($createTableSQL);
        $success_msg = "System Auto-Setup: 'cashiers' table created successfully.";
    }

    // Fetch the data
    $stmt = $pdo->query("SELECT * FROM cashiers ORDER BY created_at DESC");
    $cashiers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading cashiers: " . $e->getMessage();
    $cashiers = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cashiers - Admin</title>
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
            <div class="nav-item"><a href="cashiers.php" class="nav-link active"><i class="bi bi-cash-coin"></i> Manage Cashiers</a></div>

            <div class="nav-section-title">Clinical & Operations</div>
            <div class="nav-item"><a href="records.php" class="nav-link"><i class="bi bi-folder2-open"></i> Medical Records</a></div>
            <div class="nav-item"><a href="tests_results.php" class="nav-link"><i class="bi bi-file-medical"></i> Tests & Results</a></div>
            <div class="nav-item"><a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventory Management</a></div>
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
                <h3 class="mb-0 fw-bold text-dark">Cashiers & Billing Staff</h3>
            </div>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addCashierModal">
                <i class="bi bi-person-plus-fill me-1"></i> Add Cashier
            </button>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Staff Details</th>
                            <th>Role / Dept</th>
                            <th>Contact</th>
                            <th>Account Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashiers as $cashier): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($cashier['first_name'] . ' ' . $cashier['last_name']); ?></div>
                                    <small class="text-muted">ID: #<?php echo str_pad($cashier['cashier_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                </td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill"><i class="bi bi-cash-coin me-1"></i> Billing & Finance</span></td>
                                <td>
                                    <div class="small"><?php echo htmlspecialchars($cashier['email']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($cashier['phone']); ?></div>
                                </td>
                                <td>
                                    <?php if ($cashier['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 rounded-pill">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="cashier_id" value="<?php echo $cashier['cashier_id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $cashier['is_active'] ? '0' : '1'; ?>">
                                        <?php if ($cashier['is_active']): ?>
                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-warning" title="Suspend Account"><i class="bi bi-pause-circle"></i></button>
                                        <?php else: ?>
                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-success" title="Activate Account"><i class="bi bi-play-circle"></i></button>
                                        <?php endif; ?>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editCashierModal<?php echo $cashier['cashier_id']; ?>" title="Edit Info & Password"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this account?');">
                                        <input type="hidden" name="cashier_id" value="<?php echo $cashier['cashier_id']; ?>">
                                        <button type="submit" name="delete_cashier" class="btn btn-sm btn-outline-danger" title="Delete Account"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Cashier Modal -->
                            <div class="modal fade" id="editCashierModal<?php echo $cashier['cashier_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold">Edit Cashier Profile</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <form method="POST" action="">
                                                <input type="hidden" name="cashier_id" value="<?php echo $cashier['cashier_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">First Name</label>
                                                        <input type="text" class="form-control bg-light border-0" name="first_name" value="<?php echo htmlspecialchars($cashier['first_name']); ?>" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Last Name</label>
                                                        <input type="text" class="form-control bg-light border-0" name="last_name" value="<?php echo htmlspecialchars($cashier['last_name']); ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Professional Email</label>
                                                        <input type="email" class="form-control bg-light border-0" name="email" value="<?php echo htmlspecialchars($cashier['email']); ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Phone Number</label>
                                                        <input type="text" class="form-control bg-light border-0" name="phone" value="<?php echo htmlspecialchars($cashier['phone'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-12 mt-4 pt-3 border-top">
                                                        <label class="form-label small fw-bold text-danger"><i class="bi bi-key me-1"></i> Force Password Reset (Optional)</label>
                                                        <input type="text" class="form-control bg-light border-0" name="new_password" placeholder="Enter new password to overwrite">
                                                        <small class="text-muted" style="font-size: 0.75rem;">Leave blank if you do not want to change their password.</small>
                                                    </div>
                                                </div>
                                                <div class="d-grid mt-4">
                                                    <button type="submit" name="update_cashier" class="btn btn-primary rounded-pill py-2 fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        <?php if (empty($cashiers)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-person-x fs-1 d-block mb-3 opacity-25"></i> No billing staff or cashiers registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Cashier Modal -->
    <div class="modal fade" id="addCashierModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Register New Cashier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">First Name *</label>
                                <input type="text" class="form-control bg-light border-0" name="first_name" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Last Name *</label>
                                <input type="text" class="form-control bg-light border-0" name="last_name" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Email Address *</label>
                                <input type="email" class="form-control bg-light border-0" name="email" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Phone Number</label>
                                <input type="text" class="form-control bg-light border-0" name="phone">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Initial Password *</label>
                                <input type="password" class="form-control bg-light border-0" name="password" required>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="add_cashier" class="btn btn-dark rounded-pill py-2 fw-bold">Create Account</button>
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