<?php
// admin/patients.php - Manage all patients in the system
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Re-assignment or Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reassign_doctor'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $doc_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);

        // If doc_id is empty string, set it to NULL (unassigned)
        $doc_id = $doc_id ? $doc_id : null;

        try {
            $stmt = $pdo->prepare("UPDATE patients SET doctor_id = ? WHERE patient_id = ?");
            if ($stmt->execute([$doc_id, $pat_id])) {
                $success_msg = "Patient provider assignment updated successfully.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to update assignment.";
        }
    } elseif (isset($_POST['delete_patient'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
            if ($stmt->execute([$pat_id])) {
                $success_msg = "Patient record permanently deleted.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to delete patient. Ensure all related records are cleared.";
        }
    } elseif (isset($_POST['update_patient'])) {
        $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $fname = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
        $lname = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $new_password = $_POST['new_password'] ?? '';

        try {
            if (!empty($new_password)) {
                // Admin provided a new password, hash and update it
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE patients SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ? WHERE patient_id = ?");
                $stmt->execute([$fname, $lname, $email, $phone, $hash, $pat_id]);
                $success_msg = "Patient profile and password successfully updated.";
            } else {
                // Admin left password blank, update info only
                $stmt = $pdo->prepare("UPDATE patients SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE patient_id = ?");
                $stmt->execute([$fname, $lname, $email, $phone, $pat_id]);
                $success_msg = "Patient profile successfully updated.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to update patient. The email might already be registered.";
        }
    }
}

try {
    // Fetch all patients with their assigned doctor's name
    $stmt = $pdo->query("
        SELECT p.*, d.first_name as doc_first, d.last_name as doc_last 
        FROM patients p 
        LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
        ORDER BY p.created_at DESC
    ");
    $patients = $stmt->fetchAll();

    // Fetch active doctors for the re-assignment dropdown
    $active_doctors = $pdo->query("SELECT doctor_id, first_name, last_name, specialization FROM doctors WHERE is_active = 1 ORDER BY last_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading patients.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin</title>
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
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-shield-lock-fill me-2"></i>Admin<span>Panel</span></a>
            <button class="btn-close btn-close-white d-lg-none" id="closeSidebar"></button>
        </div>

        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Overview</a></div>

            <div class="nav-section-title">Users & Staff</div>
            <div class="nav-item"><a href="patients.php" class="nav-link active"><i class="bi bi-people"></i> Manage Patients</a></div>
            <div class="nav-item"><a href="providers.php" class="nav-link"><i class="bi bi-hospital"></i> Manage Providers</a></div>
            <div class="nav-item"><a href="nurses.php" class="nav-link"><i class="bi bi-clipboard2-heart"></i> Manage Nurses</a></div>
            <div class="nav-item"><a href="lab_techs.php" class="nav-link"><i class="bi bi-droplet-half"></i> Manage Lab Techs</a></div>
            <div class="nav-item"><a href="pharmacists.php" class="nav-link"><i class="bi bi-capsule-pill"></i> Manage Pharmacists</a></div>
            <div class="nav-item"><a href="cashiers.php" class="nav-link"><i class="bi bi-cash-coin"></i> Manage Cashiers</a></div>

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

    <main class="main-content">
        <div class="d-flex align-items-center gap-3 mb-4">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold text-dark">Patient Registry</h3>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Patient Details</th>
                            <th>Registered On</th>
                            <th>Assigned Provider</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?php echo str_pad($p['patient_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="" class="d-flex gap-2 align-items-center">
                                        <input type="hidden" name="patient_id" value="<?php echo $p['patient_id']; ?>">
                                        <select name="doctor_id" class="form-select form-select-sm" style="max-width: 200px;">
                                            <option value="">-- Unassigned --</option>
                                            <?php foreach ($active_doctors as $doc): ?>
                                                <option value="<?php echo $doc['doctor_id']; ?>" <?php echo ($p['doctor_id'] == $doc['doctor_id']) ? 'selected' : ''; ?>>
                                                    Dr. <?php echo htmlspecialchars($doc['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="reassign_doctor" class="btn btn-sm btn-outline-primary" title="Update Provider"><i class="bi bi-save"></i></button>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPatientModal<?php echo $p['patient_id']; ?>" title="Edit Patient Info & Password"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this patient? This action cannot be undone.');">
                                        <input type="hidden" name="patient_id" value="<?php echo $p['patient_id']; ?>">
                                        <button type="submit" name="delete_patient" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Patient Modal -->
                            <div class="modal fade" id="editPatientModal<?php echo $p['patient_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold">Edit Patient Profile</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <form method="POST" action="">
                                                <input type="hidden" name="patient_id" value="<?php echo $p['patient_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">First Name</label>
                                                        <input type="text" class="form-control bg-light border-0" name="first_name" value="<?php echo htmlspecialchars($p['first_name']); ?>" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Last Name</label>
                                                        <input type="text" class="form-control bg-light border-0" name="last_name" value="<?php echo htmlspecialchars($p['last_name']); ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Email Address</label>
                                                        <input type="email" class="form-control bg-light border-0" name="email" value="<?php echo htmlspecialchars($p['email']); ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Phone Number</label>
                                                        <input type="text" class="form-control bg-light border-0" name="phone" value="<?php echo htmlspecialchars($p['phone'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-12 mt-4 pt-3 border-top">
                                                        <label class="form-label small fw-bold text-danger"><i class="bi bi-key me-1"></i> Force Password Reset (Optional)</label>
                                                        <input type="text" class="form-control bg-light border-0" name="new_password" placeholder="Enter new password to overwrite">
                                                        <small class="text-muted" style="font-size: 0.75rem;">Leave blank if you do not want to change the patient's password.</small>
                                                    </div>
                                                </div>
                                                <div class="d-grid mt-4">
                                                    <button type="submit" name="update_patient" class="btn btn-primary rounded-pill py-2 fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">No patients registered in the system.</td>
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