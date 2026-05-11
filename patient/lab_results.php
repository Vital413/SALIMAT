<?php
// patient/lab_results.php - View Laboratory Test Status and Results
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$error_msg = '';

try {
    // 1. Fetch all lab tests for this patient
    $stmt = $pdo->prepare("
        SELECT t.*, d.last_name AS doc_last 
        FROM lab_tests t
        LEFT JOIN doctors d ON t.doctor_id = d.doctor_id
        WHERE t.patient_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $lab_results = $stmt->fetchAll();

    // 2. Fetch Unpaid Billing Balance (for sidebar badge)
    $billStmt = $pdo->prepare("SELECT SUM(amount - amount_paid) FROM billing WHERE patient_id = ? AND status != 'Paid'");
    $billStmt->execute([$patient_id]);
    $unpaid_balance = $billStmt->fetchColumn() ?: 0;

    // 3. Fetch Unread Messages Count (for sidebar badge)
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'patient' AND is_read = 0");
    $msgStmt->execute([$patient_id]);
    $unread_msgs = $msgStmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $error_msg = "Error loading laboratory records.";
    $lab_results = [];
    $unpaid_balance = 0;
    $unread_msgs = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Results - LuminaCare Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2b7a78;
            --secondary-color: #3aafa9;
            --accent-color: #ff9a9e;
            --text-dark: #17252a;
            --bg-light: #f4f7f6;
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
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #e1e5e8;
            border-radius: 10px;
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
            background-color: rgba(58, 175, 169, 0.08);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }

        .logout-wrapper {
            padding: 20px;
            border-top: 1px solid #eee;
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

        .result-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
            margin-bottom: 15px;
            transition: transform 0.2s ease;
        }

        .result-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .result-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .status-completed {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #d39e00;
        }

        .status-progress {
            background: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
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
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
            <div class="nav-item"><a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-clipboard2-pulse"></i> Log Vitals</a></div>

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Clinical Data</small></div>
            <div class="nav-item"><a href="tools.php" class="nav-link"><i class="bi bi-heartbreak"></i> Tools & Medications</a></div>
            <div class="nav-item"><a href="lab_results.php" class="nav-link active"><i class="bi bi-file-medical-fill"></i> Lab Results</a></div>

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Manage</small></div>
            <div class="nav-item">
                <a href="billing.php" class="nav-link">
                    <i class="bi bi-receipt"></i> Billing & Invoices
                    <?php if ($unpaid_balance > 0): ?><span class="badge bg-danger rounded-pill ms-auto">!</span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="messages.php" class="nav-link">
                    <i class="bi bi-chat-dots"></i> Messages
                    <?php if ($unread_msgs > 0): ?><span class="badge bg-primary rounded-pill ms-auto"><?php echo $unread_msgs; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i> Appointments</a></div>

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <h3 class="mb-0 fw-bold">My Laboratory Results</h3>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-12">
                <?php if (count($lab_results) > 0): ?>
                    <?php foreach ($lab_results as $test): ?>
                        <?php
                        $status_class = 'status-pending';
                        $icon = 'bi-hourglass-split';
                        if ($test['status'] == 'Completed') {
                            $status_class = 'status-completed';
                            $icon = 'bi-check2-all';
                        } elseif ($test['status'] == 'In Progress') {
                            $status_class = 'status-progress';
                            $icon = 'bi-gear-fill';
                        } elseif ($test['status'] == 'Cancelled') {
                            $status_class = 'status-cancelled';
                            $icon = 'bi-x-circle';
                        }
                        ?>
                        <div class="result-card d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="result-icon <?php echo $status_class; ?>">
                                    <i class="bi <?php echo $icon; ?>"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($test['test_name']); ?></h5>
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar-event me-1"></i> Requested: <?php echo date('M d, Y', strtotime($test['created_at'])); ?>
                                        <?php if ($test['doc_last']): ?>
                                            &nbsp;|&nbsp; <i class="bi bi-person me-1"></i> Dr. <?php echo htmlspecialchars($test['doc_last']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                                <span class="badge <?php echo $status_class; ?> px-3 py-2 rounded-pill border border-opacity-25" style="border-color: inherit;">
                                    <?php echo htmlspecialchars($test['status']); ?>
                                </span>

                                <?php if ($test['status'] == 'Completed'): ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#viewResultModal<?php echo $test['test_id']; ?>">
                                        View Report
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light border rounded-pill px-4 fw-bold text-muted" disabled>
                                        Pending...
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- View Result Modal (Only generated for completed tests) -->
                        <?php if ($test['status'] == 'Completed'): ?>
                            <div class="modal fade" id="viewResultModal<?php echo $test['test_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-medical-fill text-success me-2"></i> Official Lab Report</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-3">
                                            <div class="p-3 bg-light rounded-3 border mb-3">
                                                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($test['test_name']); ?></h6>
                                                <div class="d-flex justify-content-between small text-muted mt-2">
                                                    <span><i class="bi bi-calendar-check me-1"></i> Completed: <?php echo date('M d, Y', strtotime($test['updated_at'])); ?></span>
                                                    <span>Ref: #<?php echo str_pad($test['test_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <h6 class="fw-bold text-uppercase text-muted small mb-2">Diagnostic Findings & Notes</h6>
                                                <div class="p-3 bg-white border rounded-3 text-dark" style="font-size: 0.95rem; line-height: 1.6;">
                                                    <?php echo nl2br(htmlspecialchars($test['result_notes'] ?? 'No additional notes provided.')); ?>
                                                </div>
                                            </div>

                                            <div class="alert alert-info border-0 shadow-sm py-2 px-3 small d-flex gap-2 align-items-center mb-0">
                                                <i class="bi bi-info-circle-fill fs-5"></i>
                                                <div>Please discuss these results with your healthcare provider during your next consultation.</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top-0 pt-0">
                                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-clipboard2-x fs-1 text-muted opacity-50"></i>
                        </div>
                        <h5 class="fw-bold text-dark">No Laboratory Records Found</h5>
                        <p class="text-muted">You do not have any requested or completed lab tests at this time.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Log Vitals Modal (Submits back to dashboard for central processing) -->
    <div class="modal fade" id="logVitalsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Log Daily Vitals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="dashboard.php" method="POST">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Systolic BP (mmHg) *</label><input type="number" class="form-control bg-light border-0" name="systolic_bp" required></div>
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Diastolic BP (mmHg) *</label><input type="number" class="form-control bg-light border-0" name="diastolic_bp" required></div>
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Heart Rate (bpm)</label><input type="number" class="form-control bg-light border-0" name="heart_rate"></div>
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Weight (kg) *</label><input type="number" step="0.1" class="form-control bg-light border-0" name="weight_kg" required></div>
                            <div class="col-12"><label class="form-label small fw-bold text-muted">Blood Sugar (mg/dL)</label><input type="number" class="form-control bg-light border-0" name="blood_sugar_mgdl"></div>
                            <div class="col-12"><label class="form-label small fw-bold text-muted">Symptoms or Notes</label><textarea class="form-control bg-light border-0" name="symptoms_notes" rows="3"></textarea></div>
                        </div>
                        <div class="mt-4 d-grid"><button type="submit" name="log_vitals" class="btn btn-primary rounded-pill py-2 fw-bold">Save Vitals</button></div>
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