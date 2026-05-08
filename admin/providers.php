<?php
// admin/providers.php - Manage healthcare providers
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Provider Status Toggling or Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $doc_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'new_status', FILTER_VALIDATE_INT);

        if ($doc_id) {
            try {
                $stmt = $pdo->prepare("UPDATE doctors SET is_active = ? WHERE doctor_id = ?");
                if ($stmt->execute([$new_status, $doc_id])) {
                    $success_msg = $new_status ? "Provider account activated." : "Provider account suspended.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to update status.";
            }
        }
    } elseif (isset($_POST['delete_provider'])) {
        $doc_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE doctor_id = ?");
            if ($stmt->execute([$doc_id])) {
                $success_msg = "Provider account permanently deleted.";
            }
        } catch (PDOException $e) {
            $error_msg = "Cannot delete provider. Please re-assign all their patients first.";
        }
    } elseif (isset($_POST['update_provider'])) {
        $doc_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $fname = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
        $lname = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $specialization = trim(filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING));
        $new_password = $_POST['new_password'] ?? '';

        try {
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE doctors SET first_name = ?, last_name = ?, email = ?, phone = ?, specialization = ?, password_hash = ? WHERE doctor_id = ?");
                $stmt->execute([$fname, $lname, $email, $phone, $specialization, $hash, $doc_id]);
                $success_msg = "Provider profile and password successfully updated.";
            } else {
                $stmt = $pdo->prepare("UPDATE doctors SET first_name = ?, last_name = ?, email = ?, phone = ?, specialization = ? WHERE doctor_id = ?");
                $stmt->execute([$fname, $lname, $email, $phone, $specialization, $doc_id]);
                $success_msg = "Provider profile successfully updated.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to update provider. The email might already be registered.";
        }
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM doctors ORDER BY created_at DESC");
    $providers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading providers.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Providers - Admin</title>
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
        }

        .sidebar-brand span {
            color: var(--accent-color);
        }

        .nav-menu {
            padding: 20px 0;
            flex-grow: 1;
        }

        .nav-link {
            color: #d1d5db;
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
            color: #9ca3af;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.05);
            color: white;
            border-left: 4px solid var(--accent-color);
        }

        .logout-wrapper {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
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
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-shield-lock-fill me-2"></i>Admin<span>Panel</span></a>
            <button class="btn-close btn-close-white d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> System Overview</a></div>
            <div class="nav-item"><a href="patients.php" class="nav-link"><i class="bi bi-people"></i> Manage Patients</a></div>
            <div class="nav-item"><a href="providers.php" class="nav-link active"><i class="bi bi-hospital"></i> Manage Providers</a></div>
            <div class="nav-item"><a href="logs.php" class="nav-link"><i class="bi bi-database-check"></i> System Logs</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-light w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Secure Logout</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center gap-3 mb-4">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold text-dark">Provider Directory</h3>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Provider Details</th>
                            <th>Specialization</th>
                            <th>Contact</th>
                            <th>Account Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $doc): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></div>
                                    <small class="text-muted">ID: #<?php echo str_pad($doc['doctor_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($doc['specialization']); ?></td>
                                <td>
                                    <div class="small"><?php echo htmlspecialchars($doc['email']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($doc['phone']); ?></div>
                                </td>
                                <td>
                                    <?php if ($doc['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 rounded-pill">Suspended / Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $doc['is_active'] ? '0' : '1'; ?>">
                                        <?php if ($doc['is_active']): ?>
                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-warning" title="Suspend Provider"><i class="bi bi-pause-circle"></i></button>
                                        <?php else: ?>
                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-success" title="Activate Provider"><i class="bi bi-play-circle"></i></button>
                                        <?php endif; ?>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProviderModal<?php echo $doc['doctor_id']; ?>" title="Edit Provider Info & Password"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this provider? Ensure their patients are reassigned first.');">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                        <button type="submit" name="delete_provider" class="btn btn-sm btn-outline-danger" title="Delete Account"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Provider Modal -->
                            <div class="modal fade" id="editProviderModal<?php echo $doc['doctor_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold">Edit Provider Profile</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <form method="POST" action="">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">First Name</label>
                                                        <input type="text" class="form-control bg-light border-0" name="first_name" value="<?php echo htmlspecialchars($doc['first_name']); ?>" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Last Name</label>
                                                        <input type="text" class="form-control bg-light border-0" name="last_name" value="<?php echo htmlspecialchars($doc['last_name']); ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Professional Email</label>
                                                        <input type="email" class="form-control bg-light border-0" name="email" value="<?php echo htmlspecialchars($doc['email']); ?>" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Phone Number</label>
                                                        <input type="text" class="form-control bg-light border-0" name="phone" value="<?php echo htmlspecialchars($doc['phone'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Specialization</label>
                                                        <input type="text" class="form-control bg-light border-0" name="specialization" value="<?php echo htmlspecialchars($doc['specialization'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-12 mt-4 pt-3 border-top">
                                                        <label class="form-label small fw-bold text-danger"><i class="bi bi-key me-1"></i> Force Password Reset (Optional)</label>
                                                        <input type="text" class="form-control bg-light border-0" name="new_password" placeholder="Enter new password to overwrite">
                                                        <small class="text-muted" style="font-size: 0.75rem;">Leave blank if you do not want to change the provider's password.</small>
                                                    </div>
                                                </div>
                                                <div class="d-grid mt-4">
                                                    <button type="submit" name="update_provider" class="btn btn-primary rounded-pill py-2 fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        <?php if (empty($providers)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">No providers registered in the system.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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