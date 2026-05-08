<?php
// patient/appointments.php - View upcoming appointments and request new ones
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$success_msg = '';
$error_msg = '';

// Fetch Patient's assigned doctor
try {
    $docStmt = $pdo->prepare("SELECT doctor_id FROM patients WHERE patient_id = ?");
    $docStmt->execute([$patient_id]);
    $assigned_doctor = $docStmt->fetchColumn();
} catch (PDOException $e) {
    $assigned_doctor = null;
}

// Handle Appointment Scheduling directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_appointment'])) {
    if ($assigned_doctor) {
        $apt_date = $_POST['appointment_date'];
        $reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));

        try {
            // 1. Insert the appointment directly into the appointments table so it shows on the doctor's dashboard
            $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, notes) VALUES (?, ?, ?, 'scheduled', ?)");

            if ($stmt->execute([$patient_id, $assigned_doctor, $apt_date, $reason])) {
                $success_msg = "Your appointment has been successfully scheduled with your provider.";

                // 2. Format a clean notification message for the doctor's inbox
                $msg_body = "🗓️ *New Appointment Scheduled*\nDate: " . date('F j, Y g:i A', strtotime($apt_date)) . "\nReason: " . ($reason ? $reason : "Routine checkup/Follow-up");

                // 3. Send the automated message
                $msgStmt = $pdo->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_body) VALUES (?, 'patient', ?, 'doctor', ?)");
                $msgStmt->execute([$patient_id, $assigned_doctor, $msg_body]);
            } else {
                $error_msg = "Failed to schedule appointment. Please try again.";
            }
        } catch (PDOException $e) {
            $error_msg = "System error occurred while scheduling the appointment.";
        }
    } else {
        $error_msg = "You do not have a provider assigned yet to schedule this appointment.";
    }
}

// Fetch Appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.*, d.first_name as doc_first, d.last_name as doc_last 
        FROM appointments a 
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id 
        WHERE a.patient_id = ? 
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading appointments.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Shared Sidebar CSS - Patient Theme */
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
            <div class="nav-item"><a href="messages.php" class="nav-link"><i class="bi bi-chat-dots"></i> Messages</a></div>
            <div class="nav-item"><a href="appointments.php" class="nav-link active"><i class="bi bi-calendar-check-fill"></i> Appointments</a></div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <h3 class="mb-0 fw-bold">My Appointments</h3>
            </div>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#requestAptModal">
                <i class="bi bi-calendar-plus me-1"></i> Schedule Appointment
            </button>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                            <tr>
                                <th class="ps-4">Date & Time</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Notes/Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($appointments) && count($appointments) > 0): ?>
                                <?php foreach ($appointments as $apt): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold"><?php echo date('F j, Y', strtotime($apt['appointment_date'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($apt['appointment_date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($apt['doc_last']): ?>
                                                Dr. <?php echo htmlspecialchars($apt['doc_last']); ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Pending Assignment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($apt['status'] == 'scheduled') echo '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 rounded-pill">Upcoming</span>';
                                            elseif ($apt['status'] == 'completed') echo '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">Completed</span>';
                                            else echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">Cancelled</span>';
                                            ?>
                                        </td>
                                        <td><?php echo $apt['notes'] ? htmlspecialchars($apt['notes']) : '<span class="text-muted small">No specific instructions</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                        No appointments scheduled yet.<br>Click "Schedule Appointment" to set up a checkup.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal for Requesting Appointment -->
    <div class="modal fade" id="requestAptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Schedule an Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <?php if ($assigned_doctor): ?>
                        <p class="text-muted small mb-4">Select your preferred date and time. This will automatically schedule the appointment with your assigned healthcare provider.</p>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">Date & Time *</label>
                                <input type="datetime-local" class="form-control bg-light border-0" name="appointment_date" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">Reason for Visit (Optional)</label>
                                <textarea class="form-control bg-light border-0" name="reason" rows="3" placeholder="e.g., Doctor requested follow-up, Routine checkup, etc."></textarea>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" name="request_appointment" class="btn btn-primary rounded-pill fw-bold py-2">Schedule Appointment</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-person-x text-danger fs-1 mb-3 d-block"></i>
                            <h6 class="fw-bold">No Provider Assigned</h6>
                            <p class="text-muted small">You cannot schedule an appointment until an administrator assigns a doctor to your account.</p>
                            <button type="button" class="btn btn-secondary rounded-pill mt-2" data-bs-dismiss="modal">Close</button>
                        </div>
                    <?php endif; ?>
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