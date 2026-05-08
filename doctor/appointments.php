<?php
// doctor/appointments.php - View and manage patient appointments
require_once '../config/config.php';

if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$success_msg = '';
$error_msg = '';

// Handle Status Update or New Appointment Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $apt_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $status = $_POST['status'];

        if ($apt_id && in_array($status, ['scheduled', 'completed', 'cancelled'])) {
            $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND doctor_id = ?");
            if ($stmt->execute([$status, $apt_id, $doctor_id])) {
                $success_msg = "Appointment status updated.";
            }
        }
    } elseif (isset($_POST['create_appointment'])) {
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $apt_date = $_POST['appointment_date'];
        $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));

        if ($patient_id && !empty($apt_date)) {
            $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, notes) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$patient_id, $doctor_id, $apt_date, $notes])) {
                $success_msg = "Appointment scheduled successfully.";
            } else {
                $error_msg = "Failed to schedule appointment.";
            }
        }
    }
}

// Fetch Appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name as pat_first, p.last_name as pat_last 
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.patient_id 
        WHERE a.doctor_id = ? 
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll();

    // Fetch patients for the "Create Appointment" dropdown
    $patStmt = $pdo->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE doctor_id = ? ORDER BY last_name ASC");
    $patStmt->execute([$doctor_id]);
    $assigned_patients = $patStmt->fetchAll();

    // Sidebar unread messages
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
    $msgStmt->execute([$doctor_id]);
    $unread_messages = $msgStmt->fetchColumn();
} catch (PDOException $e) {
    $error_msg = "Error loading appointments.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

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
            <div class="nav-item"><a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> My Patients</a></div>
            <div class="nav-item">
                <a href="messages.php" class="nav-link">
                    <i class="bi bi-chat-dots-fill"></i> Messages
                    <?php if ($unread_messages > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item"><a href="appointments.php" class="nav-link active"><i class="bi bi-calendar-check-fill"></i> Appointments</a></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-gear-fill"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <h3 class="mb-0 fw-bold">Manage Appointments</h3>
            </div>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newAptModal">
                <i class="bi bi-plus-lg me-1"></i> Schedule Patient
            </button>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Patient Name</th>
                            <th>Notes</th>
                            <th>Status / Action</th>
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
                                    <td><?php echo htmlspecialchars($apt['pat_last'] . ', ' . $apt['pat_first']); ?></td>
                                    <td><?php echo $apt['notes'] ? htmlspecialchars($apt['notes']) : '<span class="text-muted small">-</span>'; ?></td>
                                    <td>
                                        <form method="POST" action="" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                            <select name="status" class="form-select form-select-sm" style="width: auto;">
                                                <option value="scheduled" <?php echo $apt['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="completed" <?php echo $apt['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $apt['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-sm btn-outline-secondary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-2 d-block mb-2"></i> No appointments scheduled.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal for New Appointment -->
    <div class="modal fade" id="newAptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Schedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Select Patient *</label>
                            <select name="patient_id" class="form-select bg-light border-0" required>
                                <option value="">-- Choose Patient --</option>
                                <?php foreach ($assigned_patients as $ap): ?>
                                    <option value="<?php echo $ap['patient_id']; ?>"><?php echo htmlspecialchars($ap['last_name'] . ', ' . $ap['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Date & Time *</label>
                            <input type="datetime-local" class="form-control bg-light border-0" name="appointment_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Notes / Reason (Optional)</label>
                            <textarea class="form-control bg-light border-0" name="notes" rows="2" placeholder="e.g., Routine checkup, Follow-up on elevated BP"></textarea>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" name="create_appointment" class="btn btn-primary rounded-pill fw-bold py-2">Schedule</button>
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