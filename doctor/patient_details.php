<?php
// doctor/patient_details.php - Detailed view of a specific patient's chart
require_once '../config/config.php';

if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$patient_id) {
    header("Location: patients.php");
    exit();
}

$success_msg = '';
$error_msg = '';

try {
    // --- Form Handlers ---

    // Handle automated appointment request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_appointment'])) {
        $msg_body = "Hello. Please request an appointment with me at your earliest convenience so we can discuss your recent health logs and overall progress. Thank you.";
        $ins_stmt = $pdo->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_body) VALUES (?, 'doctor', ?, 'patient', ?)");
        if ($ins_stmt->execute([$doctor_id, $patient_id, $msg_body])) {
            $success_msg = "An automated appointment request has been sent to the patient.";
        }
    }

    // Handle adding a Private Clinical Note
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_clinical_note'])) {
        $note_body = trim(filter_input(INPUT_POST, 'note_body', FILTER_SANITIZE_STRING));
        if (!empty($note_body)) {
            $stmt = $pdo->prepare("INSERT INTO clinical_notes (patient_id, doctor_id, note_body) VALUES (?, ?, ?)");
            if ($stmt->execute([$patient_id, $doctor_id, $note_body])) {
                $success_msg = "Clinical note securely added to patient chart.";
            } else {
                $error_msg = "Failed to save clinical note.";
            }
        }
    }

    // Handle prescribing new Medication/Supplement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medication'])) {
        $med_name = trim(filter_input(INPUT_POST, 'medication_name', FILTER_SANITIZE_STRING));
        $dosage = trim(filter_input(INPUT_POST, 'dosage', FILTER_SANITIZE_STRING));
        $freq = trim(filter_input(INPUT_POST, 'frequency', FILTER_SANITIZE_STRING));
        $instructions = trim(filter_input(INPUT_POST, 'instructions', FILTER_SANITIZE_STRING));

        if (!empty($med_name) && !empty($dosage) && !empty($freq)) {
            $stmt = $pdo->prepare("INSERT INTO medications (patient_id, doctor_id, medication_name, dosage, frequency, instructions) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$patient_id, $doctor_id, $med_name, $dosage, $freq, $instructions])) {
                $success_msg = "Medication added and shared with patient's active list.";
            } else {
                $error_msg = "Failed to add medication.";
            }
        }
    }

    // Handle discontinuing a medication
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stop_medication'])) {
        $med_id = filter_input(INPUT_POST, 'med_id', FILTER_VALIDATE_INT);
        if ($med_id) {
            $stmt = $pdo->prepare("UPDATE medications SET is_active = 0 WHERE med_id = ? AND doctor_id = ?");
            if ($stmt->execute([$med_id, $doctor_id])) {
                $success_msg = "Medication marked as discontinued.";
            }
        }
    }

    // --- Fetch Data ---

    // 1. Fetch Patient Info & Verify Assignment
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ? AND doctor_id = ?");
    $stmt->execute([$patient_id, $doctor_id]);
    $patient = $stmt->fetch();

    if (!$patient) {
        die("<div style='padding:50px;text-align:center;font-family:sans-serif;'><h3>Access Denied</h3><p>You do not have permission to view this patient's records.</p><a href='patients.php'>Return to Patient List</a></div>");
    }

    // 2. Fetch Vitals
    $vstmt = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY recorded_at DESC");
    $vstmt->execute([$patient_id]);
    $vitals = $vstmt->fetchAll();

    // 3. Fetch Clinical Notes (Private to doctor)
    $notesStmt = $pdo->prepare("SELECT * FROM clinical_notes WHERE patient_id = ? AND doctor_id = ? ORDER BY created_at DESC");
    $notesStmt->execute([$patient_id, $doctor_id]);
    $clinical_notes = $notesStmt->fetchAll();

    // 4. Fetch Active Medications
    $medsStmt = $pdo->prepare("SELECT * FROM medications WHERE patient_id = ? AND is_active = 1 ORDER BY created_at DESC");
    $medsStmt->execute([$patient_id]);
    $medications = $medsStmt->fetchAll();

    // 5. Sidebar Unread messages
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
    $msgStmt->execute([$doctor_id]);
    $unread_messages = $msgStmt->fetchColumn();
} catch (PDOException $e) {
    die("Database error loading patient details.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Chart - <?php echo htmlspecialchars($patient['last_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- HTML2PDF Library for Exporting Medical Records -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <!-- Include Jitsi External API -->
    <script src="https://meet.ffmuc.net/external_api.js"></script>

    <style>
        :root {
            --primary-color: #1a4d6b;
            --secondary-color: #2c7da0;
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
            background-color: rgba(26, 77, 107, 0.08);
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

        .patient-header-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.02);
            display: flex;
            gap: 30px;
            align-items: center;
            margin-bottom: 30px;
        }

        .patient-avatar-large {
            width: 80px;
            height: 80px;
            background: rgba(26, 77, 107, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            width: 100%;
            border-left: 1px solid #eee;
            padding-left: 30px;
        }

        .info-item label {
            font-size: 0.8rem;
            color: #8a99a0;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
            display: block;
        }

        .info-item span {
            font-weight: 500;
            color: var(--text-dark);
        }

        .private-note-card {
            background: #fffcf2;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        @media (max-width: 768px) {
            .patient-header-card {
                flex-direction: column;
                text-align: center;
            }

            .info-grid {
                border-left: none;
                padding-left: 0;
                border-top: 1px solid #eee;
                padding-top: 20px;
                text-align: left;
            }
        }

        /* Print/PDF specifics hidden from screen */
        .pdf-only-header {
            display: none;
        }
    </style>
</head>

<body>

    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-heart-pulse-fill me-2"></i>Lumina<span>Care</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
            <div class="nav-item"><a href="patients.php" class="nav-link active"><i class="bi bi-people-fill"></i> My Patients</a></div>
            <div class="nav-item">
                <a href="messages.php" class="nav-link">
                    <i class="bi bi-chat-dots-fill"></i> Messages
                    <?php if ($unread_messages > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check-fill"></i> Appointments</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content" id="printableArea">

        <!-- Header visible only in generated PDF -->
        <div class="pdf-only-header mb-4 border-bottom pb-3">
            <h2 class="fw-bold" style="color: #1a4d6b;"><i class="bi bi-heart-pulse-fill me-2"></i>LuminaCare Medical Report</h2>
            <p class="text-muted mb-0">Generated on: <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3" data-html2canvas-ignore="true">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <a href="patients.php" class="btn btn-light rounded-circle shadow-sm me-2"><i class="bi bi-arrow-left"></i></a>
                <h3 class="mb-0 fw-bold">Patient Chart</h3>
            </div>
            <div>
                <!-- Video Call Button added here -->
                <button class="btn btn-success rounded-pill fw-bold px-3 shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#videoCallModal" onclick="startVideoCall()"><i class="bi bi-camera-video-fill me-1"></i> Start Video Call</button>
                <button onclick="generatePDF()" class="btn btn-dark rounded-pill fw-bold px-3 shadow-sm me-2"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Export PDF</button>
                <form method="POST" action="" class="d-inline">
                    <button type="submit" name="request_appointment" class="btn btn-outline-primary rounded-pill fw-bold px-3 shadow-sm" onclick="return confirm('Send appointment request?');"><i class="bi bi-calendar-plus me-1"></i> Request Appt</button>
                </form>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3" data-html2canvas-ignore="true"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3" data-html2canvas-ignore="true"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <!-- Patient Info Header -->
        <div class="patient-header-card">
            <div class="d-flex flex-column align-items-center text-center" style="min-width: 150px;">
                <div class="patient-avatar-large mb-2">
                    <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
                </div>
                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h5>
                <small class="text-muted">ID: #<?php echo str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT); ?></small>

                <a href="messages.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill mt-3 w-100" data-html2canvas-ignore="true"><i class="bi bi-chat-dots me-1"></i> Direct Message</a>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <label>Expected Due Date</label>
                    <span><?php echo $patient['expected_due_date'] ? date('F j, Y', strtotime($patient['expected_due_date'])) : 'Not Set'; ?></span>
                </div>
                <div class="info-item">
                    <label>Date of Birth</label>
                    <span><?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not Set'; ?></span>
                </div>
                <div class="info-item">
                    <label>Blood Type</label>
                    <span><?php echo $patient['blood_type'] ? htmlspecialchars($patient['blood_type']) : 'Unknown'; ?></span>
                </div>
                <div class="info-item">
                    <label>Contact Info</label>
                    <span><?php echo htmlspecialchars($patient['phone'] ?? 'No phone'); ?><br><small class="text-muted"><?php echo htmlspecialchars($patient['email']); ?></small></span>
                </div>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <label>Medical History & Known Allergies</label>
                    <span><?php echo $patient['medical_history'] ? nl2br(htmlspecialchars($patient['medical_history'])) : '<span class="text-muted small">No specific medical history provided.</span>'; ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Private Clinical Notes (Doctor Only) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-lock-fill text-warning me-2"></i> Private Clinical Notes</h6>
                        <span class="badge bg-light text-muted border">Hidden from patient</span>
                    </div>
                    <div class="card-body bg-light">
                        <!-- Add Note Form -->
                        <form method="POST" action="" class="mb-4" data-html2canvas-ignore="true">
                            <textarea name="note_body" class="form-control mb-2" rows="2" placeholder="Write a private observation..." required></textarea>
                            <button type="submit" name="add_clinical_note" class="btn btn-sm btn-primary rounded-pill px-3">Save Note</button>
                        </form>

                        <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach ($clinical_notes as $note): ?>
                                <div class="private-note-card">
                                    <small class="text-muted d-block border-bottom pb-1 mb-2"><i class="bi bi-clock"></i> <?php echo date('M d, Y - g:i A', strtotime($note['created_at'])); ?></small>
                                    <p class="mb-0 text-dark" style="font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($note['note_body'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($clinical_notes)): ?>
                                <div class="text-center text-muted py-3 small">No private clinical notes recorded yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medications & Supplements Prescribed -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-capsule text-success me-2"></i> Prescribed Medications</h6>
                        <button class="btn btn-sm btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#addMedModal" data-html2canvas-ignore="true"><i class="bi bi-plus-lg"></i> Add New</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th data-html2canvas-ignore="true">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medications as $med): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                            <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                                            <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                                            <td data-html2canvas-ignore="true">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="med_id" value="<?php echo $med['med_id']; ?>">
                                                    <button type="submit" name="stop_medication" class="btn btn-sm btn-outline-danger" title="Discontinue" onclick="return confirm('Discontinue this medication?');"><i class="bi bi-stop-circle"></i> Stop</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($medications)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3 small">No active medications prescribed.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vitals History Log -->
        <h5 class="fw-bold mb-3 d-flex align-items-center"><i class="bi bi-clipboard2-pulse text-primary me-2"></i> Comprehensive Vitals Log</h5>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Date Logged</th>
                            <th>Blood Pressure</th>
                            <th>Heart Rate</th>
                            <th>Weight</th>
                            <th>Blood Sugar</th>
                            <th class="pe-4">Patient Symptoms / Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vitals) > 0): ?>
                            <?php foreach ($vitals as $v): ?>
                                <?php $isElevatedBP = ($v['systolic_bp'] > 130 || $v['diastolic_bp'] > 85); ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($v['recorded_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($v['recorded_at'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $isElevatedBP ? 'bg-danger' : 'bg-light text-dark border'; ?> fs-6 py-2 px-3 rounded-pill">
                                            <?php echo $v['systolic_bp'] . '/' . $v['diastolic_bp']; ?> <small class="fw-normal">mmHg</small>
                                        </span>
                                    </td>
                                    <td><?php echo $v['heart_rate'] ? "<strong>{$v['heart_rate']}</strong> <small class='text-muted'>bpm</small>" : '-'; ?></td>
                                    <td><?php echo $v['weight_kg'] ? "<strong>{$v['weight_kg']}</strong> <small class='text-muted'>kg</small>" : '-'; ?></td>
                                    <td><?php echo $v['blood_sugar_mgdl'] ? "<strong>{$v['blood_sugar_mgdl']}</strong> <small class='text-muted'>mg/dL</small>" : '-'; ?></td>
                                    <td class="pe-4">
                                        <?php if (!empty($v['symptoms_notes'])): ?>
                                            <div class="p-2 bg-light rounded text-dark small" style="border-left: 3px solid var(--secondary-color);">
                                                <?php echo nl2br(htmlspecialchars($v['symptoms_notes'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">None logged</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
                                    This patient has not logged any vitals yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal for Adding Medication -->
    <div class="modal fade" id="addMedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-capsule text-success me-2"></i> Prescribe Medication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Medication / Supplement Name *</label>
                            <input type="text" class="form-control bg-light border-0" name="medication_name" required placeholder="e.g., Prenatal Vitamin, Labetalol">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Dosage *</label>
                                <input type="text" class="form-control bg-light border-0" name="dosage" required placeholder="e.g., 200mg">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Frequency *</label>
                                <input type="text" class="form-control bg-light border-0" name="frequency" required placeholder="e.g., Twice daily">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Special Instructions (Optional)</label>
                            <textarea class="form-control bg-light border-0" name="instructions" rows="2" placeholder="e.g., Take with food"></textarea>
                        </div>
                        <button type="submit" name="add_medication" class="btn btn-success w-100 rounded-pill fw-bold py-2 mt-2">Add to Active Medications</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Consultation Modal -->
    <div class="modal fade" id="videoCallModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header bg-dark text-white border-bottom-0 pb-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-camera-video-fill text-success me-2"></i> Telehealth Consultation with Patient</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="endVideoCall()"></button>
                </div>
                <div class="modal-body p-0 bg-black" style="height: 70vh;">
                    <div id="jitsi-container" class="w-100 h-100 d-flex align-items-center justify-content-center text-white">
                        <div class="text-center">
                            <div class="spinner-border text-success mb-3" role="status"></div>
                            <p>Connecting to secure video room...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-dark border-top-0 pt-2 pb-3">
                    <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold" data-bs-dismiss="modal" onclick="endVideoCall()"><i class="bi bi-telephone-x-fill me-2"></i> End Call</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        // Function to Generate PDF Export
        function generatePDF() {
            const element = document.getElementById('printableArea');

            // Show the PDF specific header temporarily
            const header = document.querySelector('.pdf-only-header');
            header.style.display = 'block';

            var opt = {
                margin: [10, 10, 10, 10], // Top, Right, Bottom, Left margins
                filename: 'Medical_Chart_<?php echo preg_replace("/[^A-Za-z0-9]/", "_", $patient['last_name']); ?>.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            // Use html2pdf promise to generate, then hide header again
            html2pdf().set(opt).from(element).save().then(() => {
                header.style.display = 'none';
            });
        }

        // --- Video Consultation Logic (Jitsi Meet API) ---
        let api = null;
        // Generate the EXACT SAME secure room name as the patient's side
        const roomName = "LuminaCare_Consult_P<?php echo $patient_id; ?>_D<?php echo $doctor_id; ?>";
        const doctorName = "Dr. <?php echo addslashes(htmlspecialchars($_SESSION['last_name'])); ?>";

        function startVideoCall() {
            const container = document.getElementById('jitsi-container');
            container.innerHTML = ''; // Clear the loading spinner

            const domain = 'meet.jit.si';
            const options = {
                roomName: roomName,
                width: '100%',
                height: '100%',
                parentNode: container,
                userInfo: {
                    displayName: doctorName
                },
                configOverwrite: {
                    startWithAudioMuted: false,
                    startWithVideoMuted: false,
                    prejoinPageEnabled: false // Skip the prep page and go straight to the room
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                        'fodeviceselection', 'hangup', 'profile', 'chat', 'settings', 'raisehand',
                        'videoquality', 'filmstrip', 'tileview'
                    ],
                }
            };

            api = new JitsiMeetExternalAPI(domain, options);
        }

        function endVideoCall() {
            if (api) {
                api.dispose(); // Destroys the iframe and turns off the camera
                api = null;
            }
            // Reset the container for next time
            document.getElementById('jitsi-container').innerHTML = '<div class="text-center"><div class="spinner-border text-success mb-3" role="status"></div><p>Connecting to secure video room...</p></div>';
        }
    </script>
</body>

</html>