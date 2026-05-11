<?php
// admin/records.php - Centralized Medical Records Management
require_once '../config/config.php';

// Secure the page
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error_msg = '';
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$view_patient_id = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT);

try {
    if ($view_patient_id) {
        // --- DETAILED RECORD VIEW ---
        
        // 1. Fetch Patient Info & Assigned Doctor
        $stmt = $pdo->prepare("
            SELECT p.*, d.first_name AS doc_first, d.last_name AS doc_last, d.specialization 
            FROM patients p 
            LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
            WHERE p.patient_id = ?
        ");
        $stmt->execute([$view_patient_id]);
        $patient = $stmt->fetch();

        if (!$patient) {
            header("Location: records.php"); // Invalid ID
            exit();
        }

        // 2. Fetch Vitals History
        $vstmt = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY recorded_at DESC");
        $vstmt->execute([$view_patient_id]);
        $vitals = $vstmt->fetchAll();

        // 3. Fetch Clinical Notes
        $nstmt = $pdo->prepare("
            SELECT c.*, d.last_name AS doc_last 
            FROM clinical_notes c 
            JOIN doctors d ON c.doctor_id = d.doctor_id 
            WHERE c.patient_id = ? 
            ORDER BY c.created_at DESC
        ");
        $nstmt->execute([$view_patient_id]);
        $notes = $nstmt->fetchAll();

        // 4. Fetch Medications
        $mstmt = $pdo->prepare("
            SELECT m.*, d.last_name AS doc_last 
            FROM medications m 
            JOIN doctors d ON m.doctor_id = d.doctor_id 
            WHERE m.patient_id = ? 
            ORDER BY m.created_at DESC
        ");
        $mstmt->execute([$view_patient_id]);
        $medications = $mstmt->fetchAll();

    } else {
        // --- DIRECTORY VIEW (Search & List) ---
        $query = "
            SELECT p.patient_id, p.first_name, p.last_name, p.email, d.last_name AS doc_name,
                   (SELECT MAX(recorded_at) FROM vitals WHERE patient_id = p.patient_id) as last_vital_date,
                   (SELECT COUNT(*) FROM clinical_notes WHERE patient_id = p.patient_id) as note_count
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
        $patients_list = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "Database error occurred while fetching medical records.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Admin Panel</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #111827; 
            --secondary-color: #374151;
            --accent-color: #ff9a9e;
            --text-dark: #17252a;
            --bg-light: #f3f4f6;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }

        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); background-color: var(--primary-color); height: 100vh; position: fixed; top: 0; left: 0; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1000; transition: all 0.3s; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 20px; font-size: 1.5rem; font-weight: 800; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .sidebar-brand span { color: var(--accent-color); }
        .nav-menu { padding: 10px 0; flex-grow: 1; overflow-y: auto; }
        .nav-menu::-webkit-scrollbar { width: 5px; }
        .nav-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
        .nav-item { margin-bottom: 2px; }
        .nav-link { color: #d1d5db; padding: 10px 25px; display: flex; align-items: center; font-weight: 500; transition: all 0.3s; border-left: 4px solid transparent; text-decoration: none; font-size: 0.95rem; }
        .nav-link i { margin-right: 15px; font-size: 1.1rem; color: #9ca3af; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.05); color: white; border-left: 4px solid var(--accent-color); }
        .nav-section-title { color: #6b7280; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 15px 25px 5px; }
        .logout-wrapper { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); padding: 30px; transition: all 0.3s; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 15px 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        
        .mobile-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--primary-color); }
        @media (max-width: 991px) { .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } .main-content { margin-left: 0; padding: 15px; } .mobile-toggle { display: block; } }

        /* Custom Tabs */
        .nav-tabs .nav-link { color: var(--secondary-color); font-weight: 600; border: none; border-bottom: 3px solid transparent; padding: 12px 20px; }
        .nav-tabs .nav-link:hover { border-color: #e5e7eb; }
        .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); background: transparent; }
        
        .patient-header { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 25px; display: flex; align-items: center; gap: 20px;}
        .avatar-lg { width: 70px; height: 70px; background: rgba(17, 24, 39, 0.05); color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; flex-shrink: 0; }
        
        .note-card { background: #fdfdfd; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-top: 1px solid #eee; border-right: 1px solid #eee; border-bottom: 1px solid #eee;}
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
            <div class="nav-item"><a href="records.php" class="nav-link active"><i class="bi bi-folder2-open"></i> Medical Records</a></div>
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
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">Electronic Medical Records (EMR)</h4>
                    <p class="text-muted mb-0 small">Secure access to all clinical data and histories.</p>
                </div>
            </div>
        </header>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <?php if ($view_patient_id && isset($patient)): ?>
            
            <!-- DETAILED PATIENT RECORD VIEW -->
            <div class="mb-3">
                <a href="records.php" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Back to Directory</a>
            </div>

            <div class="patient-header">
                <div class="avatar-lg">
                    <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
                </div>
                <div class="flex-grow-1">
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h4>
                    <p class="text-muted mb-2 small">Patient ID: #<?php echo str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT); ?> | DOB: <?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not Provided'; ?></p>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark border"><i class="bi bi-telephone text-muted me-1"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-envelope text-muted me-1"></i> <?php echo htmlspecialchars($patient['email']); ?></span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-droplet-half text-danger me-1"></i> Blood: <?php echo htmlspecialchars($patient['blood_type'] ?? 'Unknown'); ?></span>
                        <?php if ($patient['doc_last']): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-stethoscope me-1"></i> Dr. <?php echo htmlspecialchars($patient['doc_last']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-none d-md-block text-end">
                    <button class="btn btn-dark rounded-pill shadow-sm" onclick="window.print();"><i class="bi bi-printer-fill me-2"></i> Print Record</button>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom pt-3 pb-0">
                    <ul class="nav nav-tabs" id="recordTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview & History</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vitals" type="button" role="tab">Vitals Log (<?php echo count($vitals); ?>)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">Clinical Notes (<?php echo count($notes); ?>)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#meds" type="button" role="tab">Prescriptions (<?php echo count($medications); ?>)</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <div class="tab-content">
                        
                        <!-- Tab 1: Overview -->
                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                            <h6 class="fw-bold text-muted text-uppercase mb-3">Self-Reported Medical History</h6>
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <?php echo $patient['medical_history'] ? nl2br(htmlspecialchars($patient['medical_history'])) : '<em class="text-muted">No specific medical history provided by patient.</em>'; ?>
                            </div>
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase mb-2">Pregnancy Details</h6>
                                    <ul class="list-group list-group-flush rounded-3 border">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Expected Due Date
                                            <span class="fw-bold"><?php echo $patient['expected_due_date'] ? date('F j, Y', strtotime($patient['expected_due_date'])) : 'Not Set'; ?></span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase mb-2">Account Status</h6>
                                    <ul class="list-group list-group-flush rounded-3 border">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Registered On
                                            <span class="fw-bold"><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Vitals -->
                        <div class="tab-pane fade" id="vitals" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light text-muted small text-uppercase">
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Blood Pressure</th>
                                            <th>Heart Rate</th>
                                            <th>Weight</th>
                                            <th>Blood Sugar</th>
                                            <th>Symptoms Noted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($vitals) > 0): ?>
                                            <?php foreach ($vitals as $v): ?>
                                            <?php $isElevatedBP = ($v['systolic_bp'] > 130 || $v['diastolic_bp'] > 85); ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($v['recorded_at'])); ?></div>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($v['recorded_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $isElevatedBP ? 'bg-danger' : 'bg-light text-dark border'; ?>">
                                                        <?php echo $v['systolic_bp'] . '/' . $v['diastolic_bp']; ?> mmHg
                                                    </span>
                                                </td>
                                                <td><?php echo $v['heart_rate'] ? $v['heart_rate'] . ' bpm' : '-'; ?></td>
                                                <td><?php echo $v['weight_kg'] ? $v['weight_kg'] . ' kg' : '-'; ?></td>
                                                <td><?php echo $v['blood_sugar_mgdl'] ? $v['blood_sugar_mgdl'] . ' mg/dL' : '-'; ?></td>
                                                <td><small class="text-muted"><?php echo $v['symptoms_notes'] ? htmlspecialchars($v['symptoms_notes']) : '-'; ?></small></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center py-4 text-muted">No vitals recorded.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 3: Notes -->
                        <div class="tab-pane fade" id="notes" role="tabpanel">
                            <?php if (count($notes) > 0): ?>
                                <div class="row g-3">
                                    <?php foreach ($notes as $note): ?>
                                    <div class="col-md-6">
                                        <div class="note-card h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                                <small class="fw-bold text-dark"><i class="bi bi-person-badge text-primary me-1"></i> Dr. <?php echo htmlspecialchars($note['doc_last']); ?></small>
                                                <small class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('M d, Y', strtotime($note['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($note['note_body'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i> No clinical notes on file for this patient.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 4: Medications -->
                        <div class="tab-pane fade" id="meds" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light text-muted small text-uppercase">
                                        <tr>
                                            <th>Medication Name</th>
                                            <th>Dosage & Freq</th>
                                            <th>Prescribed By</th>
                                            <th>Status</th>
                                            <th>Instructions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($medications) > 0): ?>
                                            <?php foreach ($medications as $med): ?>
                                            <tr>
                                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                                <td><?php echo htmlspecialchars($med['dosage']); ?> <br><small class="text-muted"><?php echo htmlspecialchars($med['frequency']); ?></small></td>
                                                <td>Dr. <?php echo htmlspecialchars($med['doc_last']); ?></td>
                                                <td>
                                                    <?php if ($med['is_active']): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill">Discontinued</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($med['instructions'] ?? '-'); ?></small></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-4 text-muted">No prescriptions on file.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        <?php else: ?>
            
            <!-- DIRECTORY SEARCH & LIST VIEW -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <form method="GET" action="records.php" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="Search by Patient Name, ID, or Email..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Search</button>
                        <?php if(!empty($search_query)): ?>
                            <a href="records.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-folder2-open text-primary me-2"></i> EMR Directory</h6>
                    <span class="badge bg-light text-dark border"><?php echo count($patients_list); ?> Records Found</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                            <tr>
                                <th class="ps-4">Patient Details</th>
                                <th>Primary Provider</th>
                                <th>Clinical Notes</th>
                                <th>Last Vital Update</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($patients_list) > 0): ?>
                                <?php foreach ($patients_list as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?> <small class="text-muted fw-normal ms-1">#<?php echo str_pad($p['patient_id'], 4, '0', STR_PAD_LEFT); ?></small></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($p['doc_name']): ?>
                                            Dr. <?php echo htmlspecialchars($p['doc_name']); ?>
                                        <?php else: ?>
                                            <span class="text-warning small"><i class="bi bi-exclamation-circle"></i> Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-journal-text text-warning me-1"></i> <?php echo $p['note_count']; ?> Notes</span>
                                    </td>
                                    <td>
                                        <?php echo $p['last_vital_date'] ? date('M d, Y', strtotime($p['last_vital_date'])) : '<span class="text-muted small">No vitals logged</span>'; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="records.php?view=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold">View Record</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i> No medical records found matching your search.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php endif; ?>

    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
</body>
</html>