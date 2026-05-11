<?php
// nurse/patients.php - Patient Directory for Nursing Staff
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a nurse
if (!isset($_SESSION['nurse_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: login.php");
    exit();
}

$nurse_id = $_SESSION['nurse_id'];
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$error_msg = '';

try {
    // Fetch Pending Doctor Orders for sidebar badge
    $pending_orders_count = $pdo->query("SELECT COUNT(*) FROM doctor_orders WHERE status = 'Pending'")->fetchColumn();

    // Fetch Patients (All clinic patients, since nurses triage everyone)
    $query = "
        SELECT p.*, d.first_name AS doc_first, d.last_name AS doc_last,
        (SELECT recorded_at FROM vitals v WHERE v.patient_id = p.patient_id ORDER BY recorded_at DESC LIMIT 1) as last_vital_date
        FROM patients p 
        LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
    ";

    $params = [];
    if (!empty($search_query)) {
        $query .= " WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR p.email LIKE ? OR p.patient_id = ?";
        $search_param = "%{$search_query}%";
        $params = [$search_param, $search_param, $search_param, $search_query];
    }

    $query .= " ORDER BY p.last_name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();

    // Fetch Patients for Vitals Modal
    $patientsList = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading patient directory.";
    $patients = [];
    $patientsList = [];
}

// Handle Nurse Logging In-Person Vitals (Modal Submission)
$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_vitals'])) {
    $pat_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $sys_bp = filter_input(INPUT_POST, 'systolic_bp', FILTER_VALIDATE_INT);
    $dia_bp = filter_input(INPUT_POST, 'diastolic_bp', FILTER_VALIDATE_INT);
    $hr = filter_input(INPUT_POST, 'heart_rate', FILTER_VALIDATE_INT);
    $weight = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
    $sugar = filter_input(INPUT_POST, 'blood_sugar', FILTER_VALIDATE_INT);
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));

    $notes = "[Logged by Nurse] " . $notes;

    if ($pat_id && $sys_bp && $dia_bp) {
        try {
            $insert_stmt = $pdo->prepare("INSERT INTO vitals (patient_id, systolic_bp, diastolic_bp, heart_rate, weight_kg, blood_sugar_mgdl, symptoms_notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($insert_stmt->execute([$pat_id, $sys_bp, $dia_bp, $hr, $weight, $sugar, $notes])) {
                $success_msg = "Clinical vitals logged successfully for the patient.";
                // Refresh data to show new vital date
                header("Location: patients.php?success=1");
                exit();
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to log vitals.";
        }
    } else {
        $error_msg = "Patient selection and Blood Pressure are required.";
    }
}
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_msg = "Clinical vitals logged successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Directory - LuminaCare Nurse</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Nurse Theme Colors */
            --primary-color: #05828e;
            --secondary-color: #0dcaf0;
            --accent-color: #ff9a9e;
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
            color: var(--accent-color);
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
            background-color: rgba(5, 130, 142, 0.08);
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
            <a href="#" class="sidebar-brand"><i class="bi bi-heart-pulse-fill me-2"></i>Lumina<span>Care</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>

        <div class="nav-menu">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            </div>
            <div class="nav-item">
                <a href="patients.php" class="nav-link active"><i class="bi bi-people-fill"></i> Patient Directory</a>
            </div>
            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="bi bi-clipboard-check-fill"></i> Doctor's Orders
                    <?php if ($pending_orders_count > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-auto"><?php echo $pending_orders_count; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-heart-pulse-fill"></i> Log In-Person Vitals</a>
            </div>
        </div>

        <div class="logout-wrapper">
            <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <h3 class="mb-0 fw-bold text-dark">Patient Directory</h3>
            </div>

            <form method="GET" action="patients.php" class="d-flex gap-2">
                <div class="input-group" style="max-width: 350px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0 bg-white" placeholder="Search by name, ID, or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="background-color: var(--primary-color); border: none;">Search</button>
                <?php if (!empty($search_query)): ?>
                    <a href="patients.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Patient Name</th>
                            <th>Contact Info</th>
                            <th>Expected Due Date</th>
                            <th>Assigned Provider</th>
                            <th>Last Vitals Log</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($patients) > 0): ?>
                            <?php foreach ($patients as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; color: var(--primary-color) !important;">
                                                <?php echo strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?></div>
                                                <small class="text-muted">ID: #<?php echo str_pad($p['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small"><i class="bi bi-telephone text-muted me-1"></i> <?php echo htmlspecialchars($p['phone'] ?? 'N/A'); ?></div>
                                        <div class="small"><i class="bi bi-envelope text-muted me-1"></i> <?php echo htmlspecialchars($p['email']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($p['expected_due_date']): ?>
                                            <?php echo date('M d, Y', strtotime($p['expected_due_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['doc_last']): ?>
                                            <span class="badge bg-light text-dark border">Dr. <?php echo htmlspecialchars($p['doc_last']); ?></span>
                                        <?php else: ?>
                                            <span class="text-warning small fst-italic"><i class="bi bi-exclamation-circle"></i> Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['last_vital_date']): ?>
                                            <small class="text-muted"><?php echo date('M d, Y - h:i A', strtotime($p['last_vital_date'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> No history</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#logVitalsModal" onclick="document.getElementById('patientSelect').value = '<?php echo $p['patient_id']; ?>'">Log Vitals</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-search fs-2 d-block mb-2"></i>
                                    No patients found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal for Logging Vitals (In-Person) -->
    <div class="modal fade" id="logVitalsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clipboard2-pulse text-primary me-2"></i> Log Clinic Vitals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Select Patient *</label>
                            <select name="patient_id" id="patientSelect" class="form-select bg-light border-0" required>
                                <option value="">-- Search / Choose Patient --</option>
                                <?php foreach ($patientsList as $pl): ?>
                                    <option value="<?php echo $pl['patient_id']; ?>"><?php echo htmlspecialchars($pl['last_name'] . ', ' . $pl['first_name']); ?> (ID: #<?php echo str_pad($pl['patient_id'], 4, '0', STR_PAD_LEFT); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold">Systolic BP *</label>
                                <input type="number" class="form-control bg-light border-0" name="systolic_bp" placeholder="e.g. 120" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold">Diastolic BP *</label>
                                <input type="number" class="form-control bg-light border-0" name="diastolic_bp" placeholder="e.g. 80" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted small fw-bold">Heart Rate</label>
                                <input type="number" class="form-control bg-light border-0" name="heart_rate" placeholder="BPM">
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted small fw-bold">Weight (kg)</label>
                                <input type="number" step="0.1" class="form-control bg-light border-0" name="weight_kg" placeholder="kg">
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted small fw-bold">Blood Sugar</label>
                                <input type="number" class="form-control bg-light border-0" name="blood_sugar" placeholder="mg/dL">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Clinical Notes / Observations</label>
                            <textarea class="form-control bg-light border-0" name="notes" rows="2" placeholder="Any symptoms or notes to pass to the doctor?"></textarea>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="log_vitals" class="btn btn-primary rounded-pill fw-bold py-2" style="background-color: var(--primary-color); border: none;">Save to Patient Chart</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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