<?php
// admin/tests_results.php - Manage Laboratory Tests and Medical Results
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle CRUD Operations for Lab Tests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add New Lab Test Request
    if (isset($_POST['add_test'])) {
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT) ?: null;
        $tech_id = filter_input(INPUT_POST, 'tech_id', FILTER_VALIDATE_INT) ?: null;
        $test_name = trim(filter_input(INPUT_POST, 'test_name', FILTER_SANITIZE_STRING));
        $test_description = trim(filter_input(INPUT_POST, 'test_description', FILTER_SANITIZE_STRING));

        if ($patient_id && !empty($test_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO lab_tests (patient_id, doctor_id, tech_id, test_name, test_description, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                if ($stmt->execute([$patient_id, $doctor_id, $tech_id, $test_name, $test_description])) {
                    $success_msg = "New lab test request created successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to create lab test request. Ensure the patient exists.";
            }
        } else {
            $error_msg = "Patient selection and Test Name are required.";
        }
    }

    // 2. Update Test Status and Results
    elseif (isset($_POST['update_test'])) {
        $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
        $tech_id = filter_input(INPUT_POST, 'tech_id', FILTER_VALIDATE_INT) ?: null;
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        $result_notes = trim(filter_input(INPUT_POST, 'result_notes', FILTER_SANITIZE_STRING));

        if ($test_id && $status) {
            try {
                $stmt = $pdo->prepare("UPDATE lab_tests SET tech_id = ?, status = ?, result_notes = ? WHERE test_id = ?");
                if ($stmt->execute([$tech_id, $status, $result_notes, $test_id])) {
                    $success_msg = "Test record updated successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to update test record.";
            }
        }
    }

    // 3. Delete Test Request
    elseif (isset($_POST['delete_test'])) {
        $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM lab_tests WHERE test_id = ?");
            if ($stmt->execute([$test_id])) {
                $success_msg = "Lab test record permanently deleted.";
            }
        } catch (PDOException $e) {
            $error_msg = "Cannot delete test record at this time.";
        }
    }
}

// Auto-Setup & Fetch Data
try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'lab_tests'")->rowCount();

    if ($checkTable == 0) {
        // Auto-create the table if it does not exist
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `lab_tests` (
          `test_id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) NOT NULL,
          `doctor_id` int(11) DEFAULT NULL,
          `tech_id` int(11) DEFAULT NULL,
          `test_name` varchar(100) NOT NULL,
          `test_description` text DEFAULT NULL,
          `status` enum('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
          `result_notes` text DEFAULT NULL,
          `created_at` timestamp DEFAULT current_timestamp(),
          `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`test_id`),
          FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($createTableSQL);
        $success_msg = "System Auto-Setup: 'lab_tests' table created successfully.";
    }

    // Fetch all Lab Tests with joins
    $stmt = $pdo->query("
        SELECT t.*, 
               p.first_name AS pat_first, p.last_name AS pat_last,
               d.last_name AS doc_last,
               l.first_name AS tech_first, l.last_name AS tech_last
        FROM lab_tests t
        JOIN patients p ON t.patient_id = p.patient_id
        LEFT JOIN doctors d ON t.doctor_id = d.doctor_id
        LEFT JOIN lab_techs l ON t.tech_id = l.tech_id
        ORDER BY t.created_at DESC
    ");
    $lab_tests = $stmt->fetchAll();

    // Fetch lists for dropdowns
    $patientsList = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name ASC")->fetchAll();
    $doctorsList = $pdo->query("SELECT doctor_id, last_name, specialization FROM doctors WHERE is_active = 1 ORDER BY last_name ASC")->fetchAll();

    // Safe fetch for lab techs in case the table is empty/missing
    $techsCheck = $pdo->query("SHOW TABLES LIKE 'lab_techs'")->rowCount();
    $techsList = [];
    if ($techsCheck > 0) {
        $techsList = $pdo->query("SELECT tech_id, first_name, last_name FROM lab_techs WHERE is_active = 1 ORDER BY last_name ASC")->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "Error loading laboratory data: " . $e->getMessage();
    $lab_tests = [];
    $patientsList = [];
    $doctorsList = [];
    $techsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tests & Results - Admin</title>
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
            <div class="nav-item"><a href="cashiers.php" class="nav-link"><i class="bi bi-cash-coin"></i> Manage Cashiers</a></div>

            <div class="nav-section-title">Clinical & Operations</div>
            <div class="nav-item"><a href="records.php" class="nav-link"><i class="bi bi-folder2-open"></i> Medical Records</a></div>
            <div class="nav-item"><a href="tests_results.php" class="nav-link active"><i class="bi bi-file-medical"></i> Tests & Results</a></div>
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
                <h3 class="mb-0 fw-bold text-dark">Laboratory Tests & Results</h3>
            </div>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addTestModal">
                <i class="bi bi-plus-circle-fill me-1"></i> New Lab Request
            </button>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Request Info</th>
                            <th>Patient</th>
                            <th>Assigned Tech</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($test['test_name']); ?></div>
                                    <small class="text-muted">Req: <?php echo date('M d, Y', strtotime($test['created_at'])); ?></small>
                                    <?php if ($test['doc_last']): ?>
                                        <div class="small text-muted fst-italic">By: Dr. <?php echo htmlspecialchars($test['doc_last']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($test['pat_last'] . ', ' . $test['pat_first']); ?></div>
                                    <small class="text-muted">ID: #<?php echo str_pad($test['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                </td>
                                <td>
                                    <?php if ($test['tech_last']): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($test['tech_first'] . ' ' . $test['tech_last']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning small"><i class="bi bi-exclamation-circle"></i> Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    if ($test['status'] == 'Completed') $statusClass = 'success';
                                    if ($test['status'] == 'In Progress') $statusClass = 'primary';
                                    if ($test['status'] == 'Pending') $statusClass = 'warning text-dark';
                                    if ($test['status'] == 'Cancelled') $statusClass = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?> bg-opacity-10 text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?> border-opacity-25 px-2 py-1 rounded-pill">
                                        <?php echo htmlspecialchars($test['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTestModal<?php echo $test['test_id']; ?>" title="Update Status & Results"><i class="bi bi-pencil-square"></i> Update</button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Permanently delete this test record?');">
                                        <input type="hidden" name="test_id" value="<?php echo $test['test_id']; ?>">
                                        <button type="submit" name="delete_test" class="btn btn-sm btn-outline-danger" title="Delete Record"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Test Modal -->
                            <div class="modal fade" id="editTestModal<?php echo $test['test_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold">Update Test Record</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <div class="mb-3 p-3 bg-light rounded-3 border">
                                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($test['test_name']); ?></h6>
                                                <p class="small text-muted mb-0"><?php echo htmlspecialchars($test['test_description'] ?? 'No description provided.'); ?></p>
                                            </div>

                                            <form method="POST" action="">
                                                <input type="hidden" name="test_id" value="<?php echo $test['test_id']; ?>">

                                                <div class="row g-3">
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Assign Lab Tech</label>
                                                        <select name="tech_id" class="form-select bg-light border-0">
                                                            <option value="">-- Unassigned --</option>
                                                            <?php foreach ($techsList as $tech): ?>
                                                                <option value="<?php echo $tech['tech_id']; ?>" <?php echo ($test['tech_id'] == $tech['tech_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label small fw-bold text-muted">Current Status</label>
                                                        <select name="status" class="form-select bg-light border-0">
                                                            <option value="Pending" <?php echo ($test['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="In Progress" <?php echo ($test['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="Completed" <?php echo ($test['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="Cancelled" <?php echo ($test['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-bold text-muted">Result Notes / Findings</label>
                                                        <textarea class="form-control bg-light border-0" name="result_notes" rows="4" placeholder="Enter findings, measurements, or diagnostic results here..."><?php echo htmlspecialchars($test['result_notes'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="d-grid mt-4">
                                                    <button type="submit" name="update_test" class="btn btn-primary rounded-pill py-2 fw-bold">Save Updates</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        <?php if (empty($lab_tests)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-clipboard2-x fs-1 d-block mb-3 opacity-25"></i> No lab tests have been requested yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Test Modal -->
    <div class="modal fade" id="addTestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Create New Lab Request</h5>
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
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Requesting Doctor</label>
                                <select name="doctor_id" class="form-select bg-light border-0">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($doctorsList as $d): ?>
                                        <option value="<?php echo $d['doctor_id']; ?>">Dr. <?php echo htmlspecialchars($d['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted">Assign Lab Tech</label>
                                <select name="tech_id" class="form-select bg-light border-0">
                                    <option value="">-- Optional --</option>
                                    <?php foreach ($techsList as $tech): ?>
                                        <option value="<?php echo $tech['tech_id']; ?>"><?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Test Name *</label>
                                <input type="text" class="form-control bg-light border-0" name="test_name" placeholder="e.g., Complete Blood Count, Urinalysis" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Test Description / Instructions</label>
                                <textarea class="form-control bg-light border-0" name="test_description" rows="2" placeholder="Any specific requirements..."></textarea>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="add_test" class="btn btn-dark rounded-pill py-2 fw-bold">Submit Request</button>
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