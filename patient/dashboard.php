<?php
// patient/dashboard.php - Patient Main Dashboard with Data Visualization & Timeline
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$success_msg = '';
$error_msg = '';

// 1. Fetch assigned doctor info FIRST (Needed for Smart Alerts & Display)
try {
    $docStmt = $pdo->prepare("SELECT p.expected_due_date, d.doctor_id, d.first_name, d.last_name, d.specialization, d.email AS doc_email FROM patients p LEFT JOIN doctors d ON p.doctor_id = d.doctor_id WHERE p.patient_id = ?");
    $docStmt->execute([$patient_id]);
    $patient_info = $docStmt->fetch();

    if ($patient_info && $patient_info['doctor_id']) {
        $assigned_doctor = [
            'doctor_id' => $patient_info['doctor_id'],
            'first_name' => $patient_info['first_name'],
            'last_name' => $patient_info['last_name'],
            'specialization' => $patient_info['specialization'],
            'email' => $patient_info['doc_email']
        ];
    } else {
        $assigned_doctor = null;
    }
} catch (PDOException $e) {
    $assigned_doctor = null;
    $patient_info = ['expected_due_date' => null];
}

// 2. Handle Vital Logging Form Submission & Smart Alerts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_vitals'])) {
    $sys_bp = filter_input(INPUT_POST, 'systolic_bp', FILTER_SANITIZE_NUMBER_INT);
    $dia_bp = filter_input(INPUT_POST, 'diastolic_bp', FILTER_SANITIZE_NUMBER_INT);
    $heart_rate = filter_input(INPUT_POST, 'heart_rate', FILTER_SANITIZE_NUMBER_INT);
    $weight = filter_input(INPUT_POST, 'weight_kg', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $blood_sugar = filter_input(INPUT_POST, 'blood_sugar_mgdl', FILTER_SANITIZE_NUMBER_INT);
    $symptoms = trim(filter_input(INPUT_POST, 'symptoms_notes', FILTER_SANITIZE_STRING));

    if (empty($sys_bp) || empty($dia_bp) || empty($weight)) {
        $error_msg = "Blood Pressure and Weight are required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vitals (patient_id, systolic_bp, diastolic_bp, heart_rate, weight_kg, blood_sugar_mgdl, symptoms_notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$patient_id, $sys_bp, $dia_bp, $heart_rate, $weight, $blood_sugar, $symptoms])) {

                $vital_id = $pdo->lastInsertId(); // Get the ID of the vital just logged
                $success_msg = "Vitals logged successfully!";

                // --- SMART ALERTS & NOTIFICATION SYSTEM ---
                // Trigger condition: High Blood Pressure (Pre-eclampsia warning)
                if ($sys_bp >= 140 || $dia_bp >= 90) {
                    if ($assigned_doctor && $assigned_doctor['doctor_id']) {

                        // Log alert into the database for the Doctor's Dashboard
                        $alert_msg = "Critical Blood Pressure logged: {$sys_bp}/{$dia_bp} mmHg. Immediate review recommended.";
                        $astmt = $pdo->prepare("INSERT INTO alerts (patient_id, doctor_id, vital_id, alert_type, alert_message) VALUES (?, ?, ?, 'High Blood Pressure', ?)");
                        $astmt->execute([$patient_id, $assigned_doctor['doctor_id'], $vital_id, $alert_msg]);

                        // Send Email Notification to the Doctor
                        $to = $assigned_doctor['email'];
                        $subject = "URGENT: Maternal Health Alert - LuminaCare";
                        $message = "Dr. " . $assigned_doctor['last_name'] . ",\n\nYour patient, " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . ", just logged a critical blood pressure reading of {$sys_bp}/{$dia_bp} mmHg.\n\nPlease log in to the LuminaCare Provider Portal immediately to review their chart and initiate a telehealth consultation if necessary.\n\n- LuminaCare Automated System";
                        $headers = "From: emergency@luminacare.com\r\n" . "Reply-To: no-reply@luminacare.com\r\n" . "X-Priority: 1 (Highest)";

                        @mail($to, $subject, $message, $headers);

                        // Alter the patient's success message to a critical warning
                        $success_msg = '';
                        $error_msg = "<strong>Elevated Reading Detected!</strong> Your blood pressure is high ({$sys_bp}/{$dia_bp}). An urgent alert has been automatically sent to Dr. {$assigned_doctor['last_name']}. If you experience severe headaches, vision changes, or right-sided stomach pain, please go to the nearest emergency room immediately.";
                    } else {
                        // High BP, but no doctor assigned
                        $success_msg = '';
                        $error_msg = "<strong>Warning!</strong> Your blood pressure is elevated ({$sys_bp}/{$dia_bp}), but you do not have an assigned provider yet. Please contact a local clinic or emergency room if you feel unwell.";
                    }
                }
            } else {
                $error_msg = "Failed to log vitals. Please try again.";
            }
        } catch (PDOException $e) {
            $error_msg = "Database error occurred while logging vitals.";
        }
    }
}

// 3. Fetch Latest Vitals for Dashboard Cards & Charts & Calculate Timeline
try {
    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY recorded_at ASC LIMIT 10");
    $stmt->execute([$patient_id]);
    $chart_vitals = $stmt->fetchAll();

    // Sort descending for the table and latest card
    $recent_vitals = array_reverse($chart_vitals);
    $latest_vital = $recent_vitals[0] ?? null;

    // Prepare JSON data for Chart.js
    $chart_dates = [];
    $chart_sys = [];
    $chart_dia = [];

    foreach ($chart_vitals as $v) {
        $chart_dates[] = date('M d', strtotime($v['recorded_at']));
        $chart_sys[] = $v['systolic_bp'];
        $chart_dia[] = $v['diastolic_bp'];
    }

    // --- PREGNANCY TIMELINE CALCULATION LOGIC ---
    $due_date = $patient_info['expected_due_date'] ?? null;
    $weeks_pregnant = 0;
    $days_pregnant = 0;
    $progress_percent = 0;
    $milestone_text = "Set your due date in Profile Settings to see your pregnancy timeline.";
    $trimester = 0;

    if ($due_date) {
        // Average pregnancy is 280 days (40 weeks) from last menstrual period
        $conception_date = date('Y-m-d', strtotime($due_date . ' - 280 days'));
        $today = date('Y-m-d');

        $datetime1 = new DateTime($conception_date);
        $datetime2 = new DateTime($today);

        if ($datetime2 >= $datetime1) {
            $interval = $datetime1->diff($datetime2);
            $total_days = $interval->format('%a');

            if ($total_days <= 280) {
                $weeks_pregnant = floor($total_days / 7);
                $days_pregnant = $total_days % 7;
                $progress_percent = min(100, ($total_days / 280) * 100);

                if ($weeks_pregnant < 13) $trimester = 1;
                elseif ($weeks_pregnant < 27) $trimester = 2;
                else $trimester = 3;

                // Fun Baby Size Milestones
                $milestones = [
                    4 => "Your baby is the size of a poppy seed.",
                    8 => "Your baby is the size of a raspberry.",
                    12 => "Your baby is the size of a plum. First trimester almost done!",
                    16 => "Your baby is the size of an avocado.",
                    20 => "Halfway there! Your baby is the size of a banana.",
                    24 => "Your baby is the size of a cantaloupe.",
                    28 => "Third trimester begins! Baby is the size of an eggplant.",
                    32 => "Your baby is the size of a squash.",
                    36 => "Almost time! Your baby is the size of a papaya.",
                    40 => "You made it! Your baby is the size of a small pumpkin."
                ];

                // Find closest milestone milestone
                $closest_week = floor($weeks_pregnant / 4) * 4;
                if ($closest_week < 4) $closest_week = 4;

                if (isset($milestones[$closest_week])) {
                    $milestone_text = "Week $weeks_pregnant: " . $milestones[$closest_week];
                } else {
                    $milestone_text = "Week $weeks_pregnant: Your baby is growing beautifully!";
                }
            } else {
                $progress_percent = 100;
                $weeks_pregnant = 40;
                $milestone_text = "You have reached or passed your expected due date!";
                $trimester = 3;
            }
        }
    }
} catch (PDOException $e) {
    $error_msg = "Error loading dashboard data.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LuminaCare Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Include Jitsi External API -->
    <script src="https://meet.ffmuc.net/external_api.js"></script>

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

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        /* Timeline specific CSS */
        .timeline-card {
            background: linear-gradient(135deg, #ffffff 0%, #fef6f7 100%);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
            border: 1px solid rgba(255, 154, 158, 0.2);
        }

        .progress-container {
            width: 100%;
            background-color: #e9ecef;
            border-radius: 50px;
            height: 12px;
            margin: 20px 0;
            position: relative;
            overflow: visible;
        }

        .progress-bar-custom {
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
            height: 100%;
            border-radius: 50px;
            transition: width 1s ease-in-out;
            position: relative;
        }

        .progress-marker {
            position: absolute;
            top: -8px;
            right: -15px;
            width: 28px;
            height: 28px;
            background: white;
            border: 4px solid var(--accent-color);
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            z-index: 2;
        }

        .trimester-labels {
            display: flex;
            justify-content: space-between;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .metric-title {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .status-normal {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .status-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #d39e00;
        }

        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
        }

        .doctor-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(43, 122, 120, 0.2);
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
            <div class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
            <div class="nav-item"><a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-clipboard2-pulse"></i> Log Vitals</a></div>
            <div class="nav-item"><a href="tools.php" class="nav-link"><i class="bi bi-heartbreak"></i> Pregnancy Tools</a></div>
            <div class="nav-item"><a href="messages.php" class="nav-link"><i class="bi bi-chat-dots"></i> Messages</a></div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i> Appointments</a></div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">Hello, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h4>
                    <p class="text-muted mb-0 small">Here is your daily health overview.</p>
                </div>
            </div>
            <button class="btn btn-primary rounded-pill px-4 d-none d-md-block" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-plus-lg me-2"></i>Log Vitals</button>
        </header>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <!-- PREGNANCY TIMELINE WIDGET -->
        <div class="timeline-card">
            <div class="row align-items-center">
                <div class="col-md-3 text-center text-md-start mb-3 mb-md-0 border-md-end">
                    <h2 class="fw-bold mb-0" style="color: var(--accent-color); font-family: 'Poppins', sans-serif;">
                        <?php echo $weeks_pregnant ? $weeks_pregnant . '<span class="fs-5 text-dark">w</span> ' . $days_pregnant . '<span class="fs-5 text-dark">d</span>' : '--'; ?>
                    </h2>
                    <span class="text-muted small fw-bold text-uppercase">Current Gestation</span>
                </div>
                <div class="col-md-9 ps-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold mb-0 text-dark">Pregnancy Milestone</h6>
                        <?php if ($trimester): ?>
                            <span class="badge bg-white text-primary border shadow-sm">Trimester <?php echo $trimester; ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-2"><i class="bi bi-stars text-warning me-1"></i> <?php echo $milestone_text; ?></p>

                    <div class="progress-container">
                        <div class="progress-bar-custom" style="width: <?php echo $progress_percent; ?>%;">
                            <?php if ($progress_percent > 0): ?><div class="progress-marker"></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="trimester-labels">
                        <span>1st Tri</span>
                        <span>2nd Tri (Wk 14)</span>
                        <span>3rd Tri (Wk 28)</span>
                        <span>Due Date</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(255, 107, 114, 0.1); color: #ff6b72;"><i class="bi bi-heart-pulse-fill"></i></div>
                    <div class="metric-title">Blood Pressure</div>
                    <?php if ($latest_vital && $latest_vital['systolic_bp']): ?>
                        <div class="metric-value"><?php echo $latest_vital['systolic_bp'] . '/' . $latest_vital['diastolic_bp']; ?> <span class="fs-6 text-muted fw-normal">mmHg</span></div>
                        <?php if ($latest_vital['systolic_bp'] < 120 && $latest_vital['diastolic_bp'] < 80) echo '<span class="status-badge status-normal"><i class="bi bi-check-circle me-1"></i> Normal</span>';
                        else echo '<span class="status-badge status-warning"><i class="bi bi-exclamation-circle me-1"></i> Elevated</span>'; ?>
                    <?php else: ?><div class="metric-value text-muted">--/--</div><?php endif; ?>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(43, 122, 120, 0.1); color: var(--primary-color);"><i class="bi bi-person-standing"></i></div>
                    <div class="metric-title">Current Weight</div>
                    <?php if ($latest_vital && $latest_vital['weight_kg']): ?>
                        <div class="metric-value"><?php echo number_format($latest_vital['weight_kg'], 1); ?> <span class="fs-6 text-muted fw-normal">kg</span></div>
                    <?php else: ?><div class="metric-value text-muted">--</div><?php endif; ?>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(255, 193, 7, 0.1); color: #d39e00;"><i class="bi bi-activity"></i></div>
                    <div class="metric-title">Heart Rate</div>
                    <?php if ($latest_vital && $latest_vital['heart_rate']): ?>
                        <div class="metric-value"><?php echo $latest_vital['heart_rate']; ?> <span class="fs-6 text-muted fw-normal">bpm</span></div>
                    <?php else: ?><div class="metric-value text-muted">--</div><?php endif; ?>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="doctor-card h-100 d-flex flex-column justify-content-center relative overflow-hidden">
                    <i class="bi bi-stethoscope position-absolute text-white opacity-25" style="font-size: 6rem; right: -10px; bottom: -20px;"></i>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 1px;">Assigned Provider</h6>
                    <?php if ($assigned_doctor && $assigned_doctor['first_name']): ?>
                        <h4 class="fw-bold mb-0">Dr. <?php echo htmlspecialchars($assigned_doctor['last_name']); ?></h4>
                        <p class="mb-3 opacity-75 small"><?php echo htmlspecialchars($assigned_doctor['specialization']); ?></p>
                        <div class="d-flex flex-column gap-2 w-75 z-1">
                            <a href="messages.php" class="btn btn-light btn-sm rounded-pill fw-bold text-primary"><i class="bi bi-chat-dots me-1"></i> Message</a>
                            <!-- NEW: Video Consultation Button -->
                            <button class="btn btn-success btn-sm rounded-pill fw-bold text-white shadow" data-bs-toggle="modal" data-bs-target="#videoCallModal" onclick="startVideoCall()"><i class="bi bi-camera-video-fill me-1"></i> Telehealth Call</button>
                        </div>
                    <?php else: ?>
                        <h5 class="fw-bold mb-0">Pending Assignment</h5>
                        <p class="mb-0 opacity-75 small mt-2">An admin will assign a doctor to you shortly.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Data Visualization Chart -->
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i> Blood Pressure Trends</h5>
                <span class="badge bg-light text-dark border">Last 10 Readings</span>
            </div>
            <canvas id="bpChart" height="80"></canvas>
        </div>

        <!-- Recent History Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Vitals History</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Blood Pressure</th>
                            <th>Heart Rate</th>
                            <th>Weight</th>
                            <th>Symptoms Logged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_vitals) > 0): ?>
                            <?php foreach (array_slice($recent_vitals, 0, 5) as $vital): ?>
                                <tr>
                                    <td class="ps-4 text-muted">
                                        <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($vital['recorded_at'])); ?></div><small><?php echo date('h:i A', strtotime($vital['recorded_at'])); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $vital['systolic_bp'] . '/' . $vital['diastolic_bp']; ?> mmHg</span></td>
                                    <td><?php echo $vital['heart_rate'] ? $vital['heart_rate'] . ' bpm' : '-'; ?></td>
                                    <td><?php echo $vital['weight_kg'] ? $vital['weight_kg'] . ' kg' : '-'; ?></td>
                                    <td><?php echo !empty($vital['symptoms_notes']) ? '<i class="bi bi-info-circle text-primary" title="Note Added"></i>' : '<span class="text-muted small">None</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No vitals logged yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Log Vitals Modal -->
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

    <!-- Video Consultation Modal -->
    <div class="modal fade" id="videoCallModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header bg-dark text-white border-bottom-0 pb-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-camera-video-fill text-success me-2"></i> Telehealth Consultation</h5>
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
        // Sidebar Toggle
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        // Initialize Chart.js safely using PHP data injection
        const chartDates = <?php echo json_encode($chart_dates); ?>;
        const sysData = <?php echo json_encode($chart_sys); ?>;
        const diaData = <?php echo json_encode($chart_dia); ?>;

        if (chartDates.length > 0) {
            const ctx = document.getElementById('bpChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [{
                            label: 'Systolic BP',
                            data: sysData,
                            borderColor: '#ff6b72',
                            backgroundColor: 'rgba(255, 107, 114, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Diastolic BP',
                            data: diaData,
                            borderColor: '#3aafa9',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            suggestedMin: 60,
                            suggestedMax: 140
                        }
                    }
                }
            });
        } else {
            // Hide chart canvas and show empty state if no data
            document.getElementById('bpChart').outerHTML = '<div class="text-center text-muted py-4">Not enough data to display chart. Log your vitals first!</div>';
        }

        // --- Video Consultation Logic (Jitsi Meet API) ---
        let api = null;
        // Generate a unique, secure room name based on the patient and doctor IDs
        const roomName = "LuminaCare_Consult_P<?php echo $patient_id; ?>_D<?php echo $assigned_doctor['doctor_id'] ?? '0'; ?>";
        const patientName = "<?php echo addslashes(htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])); ?>";

        function startVideoCall() {
            const container = document.getElementById('jitsi-container');
            container.innerHTML = ''; // Clear the loading spinner

            const domain = 'meet.ffmuc.net'; // Using the no-login-required Jitsi community server
            const options = {
                roomName: roomName,
                width: '100%',
                height: '100%',
                parentNode: container,
                userInfo: {
                    displayName: patientName
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