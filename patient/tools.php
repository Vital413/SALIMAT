<?php
// patient/tools.php - Interactive Pregnancy Tools (Cycle-Isolated)
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$success_msg = '';
$error_msg = '';

// --- Calculate Filter for Current Pregnancy Cycle ---
try {
    $patStmt = $pdo->prepare("SELECT expected_due_date FROM patients WHERE patient_id = ?");
    $patStmt->execute([$patient_id]);
    $due_date = $patStmt->fetchColumn();

    $conception_date_str = '1970-01-01 00:00:00'; // Default fallback
    if ($due_date) {
        // Find start of current pregnancy to isolate logs
        $conception_date_str = date('Y-m-d 00:00:00', strtotime($due_date . ' - 280 days'));
    }
} catch (PDOException $e) {
    $conception_date_str = '1970-01-01 00:00:00';
}

// --- Handle Tool Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Save Kick Session
    if (isset($_POST['save_kick_session'])) {
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $total_kicks = filter_input(INPUT_POST, 'total_kicks', FILTER_VALIDATE_INT);
        $duration_minutes = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
        $session_date = date('Y-m-d');

        if ($total_kicks > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO kick_logs (patient_id, session_date, start_time, end_time, total_kicks, duration_minutes) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$patient_id, $session_date, $start_time, $end_time, $total_kicks, $duration_minutes])) {
                    $success_msg = "Kick session saved successfully to your current pregnancy history.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to save kick session. Please try again.";
            }
        }
    }

    // 2. Save Contraction Log
    if (isset($_POST['save_contraction'])) {
        $start_time = $_POST['contraction_start'];
        $duration_seconds = filter_input(INPUT_POST, 'contraction_duration', FILTER_VALIDATE_INT);
        $interval_minutes = filter_input(INPUT_POST, 'contraction_interval', FILTER_VALIDATE_INT);
        $intensity = filter_input(INPUT_POST, 'intensity', FILTER_SANITIZE_STRING);

        if ($duration_seconds > 0) {
            $interval_minutes = ($interval_minutes !== false && $interval_minutes !== null && $interval_minutes !== '') ? $interval_minutes : null;

            try {
                $stmt = $pdo->prepare("INSERT INTO contraction_logs (patient_id, start_time, duration_seconds, interval_minutes, intensity) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$patient_id, $start_time, $duration_seconds, $interval_minutes, $intensity])) {
                    $success_msg = "Contraction logged successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to log contraction. Please try again.";
            }
        }
    }
}

// --- Fetch Data for UI (Filtered by Cycle) ---
try {
    // Recent Kick Logs - Filtered
    $kick_logs = $pdo->prepare("SELECT * FROM kick_logs WHERE patient_id = ? AND start_time >= ? ORDER BY start_time DESC LIMIT 5");
    $kick_logs->execute([$patient_id, $conception_date_str]);
    $recent_kicks = $kick_logs->fetchAll();

    // Recent Contraction Logs - Filtered
    $contraction_logs = $pdo->prepare("SELECT * FROM contraction_logs WHERE patient_id = ? AND start_time >= ? ORDER BY start_time DESC LIMIT 5");
    $contraction_logs->execute([$patient_id, $conception_date_str]);
    $recent_contractions = $contraction_logs->fetchAll();

    // Active Medications & Supplements (Independent of cycle, managed by doctor)
    $medStmt = $pdo->prepare("SELECT * FROM medications WHERE patient_id = ? AND is_active = 1 ORDER BY created_at DESC");
    $medStmt->execute([$patient_id]);
    $medications = $medStmt->fetchAll();

    // Sidebar Badges
    $billStmt = $pdo->prepare("SELECT SUM(amount - amount_paid) FROM billing WHERE patient_id = ? AND status != 'Paid'");
    $billStmt->execute([$patient_id]);
    $unpaid_balance = $billStmt->fetchColumn() ?: 0;

    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'patient' AND is_read = 0");
    $msgStmt->execute([$patient_id]);
    $unread_msgs = $msgStmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $recent_kicks = [];
    $recent_contractions = [];
    $medications = [];
    $unpaid_balance = 0;
    $unread_msgs = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pregnancy Tools - LuminaCare</title>
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

        .tool-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .kick-btn {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-color), #ff6b72);
            color: white;
            border: none;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 10px 20px rgba(255, 107, 114, 0.3);
            transition: transform 0.1s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .kick-btn:active {
            transform: scale(0.95);
        }

        .kick-count-display {
            font-size: 3.8rem;
            line-height: 1;
            font-weight: 800;
        }

        .timer-display {
            font-size: 4rem;
            font-weight: 800;
            font-family: monospace;
            color: var(--primary-color);
            text-align: center;
            line-height: 1;
            margin: 20px 0;
        }

        .timer-btn {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: none;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            text-align: center;
        }

        .btn-start-timer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .btn-stop-timer {
            background: linear-gradient(135deg, #dc3545, #c82333);
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

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Clinical Data</small></div>
            <div class="nav-item"><a href="tools.php" class="nav-link active"><i class="bi bi-heartbreak"></i> Tools & Medications</a></div>
            <div class="nav-item"><a href="lab_results.php" class="nav-link"><i class="bi bi-file-medical"></i> Lab Results</a></div>

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
                <h3 class="mb-0 fw-bold">Tools & Medications</h3>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <!-- Active Medications Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 py-3 pb-0 d-flex align-items-center">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(58, 175, 169, 0.1); color: var(--primary-color);" class="d-flex align-items-center justify-content-center fs-4 me-3">
                            <i class="bi bi-capsule"></i>
                        </div>
                        <h5 class="mb-0 fw-bold">Active Medications & Supplements</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($medications as $med): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="p-3 bg-light rounded-4 h-100 border border-success border-opacity-10 shadow-sm">
                                        <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($med['medication_name']); ?></h6>
                                        <div class="small text-muted mb-2 d-flex gap-2">
                                            <span class="badge bg-white text-dark border"><i class="bi bi-prescription2 me-1 text-primary"></i> <?php echo htmlspecialchars($med['dosage']); ?></span>
                                            <span class="badge bg-white text-dark border"><i class="bi bi-clock-history me-1 text-warning"></i> <?php echo htmlspecialchars($med['frequency']); ?></span>
                                        </div>
                                        <p class="small mb-0 mt-2 text-dark"><i class="bi bi-info-circle me-1 text-muted"></i> <?php echo htmlspecialchars($med['instructions'] ?? 'No special instructions.'); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($medications)): ?>
                                <div class="col-12 text-center text-muted py-3 small">
                                    <i class="bi bi-check-circle fs-3 d-block mb-2 text-success opacity-50"></i>
                                    No active medications or supplements currently prescribed by your provider.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- Kick Counter Tool -->
            <div class="col-lg-6">
                <div class="tool-card text-center position-relative">
                    <h5 class="fw-bold mb-1"><i class="bi bi-heart-pulse text-danger me-2"></i> Kick Counter</h5>
                    <p class="text-muted small mb-4">Tap the button every time you feel a movement. Aim for 10 kicks within 2 hours.</p>

                    <button id="kickBtn" class="kick-btn">
                        <span id="kickDisplay" class="kick-count-display">0</span>
                        <span id="kickLabel" class="small fw-normal mt-1">Tap to Start</span>
                    </button>

                    <div class="mt-4 d-flex justify-content-center gap-4 text-muted border-top pt-3">
                        <div>
                            <small class="d-block text-uppercase fw-bold">Status</small>
                            <span id="kickStatus" class="fw-bold text-dark">Ready</span>
                        </div>
                        <div>
                            <small class="d-block text-uppercase fw-bold">Session Timer</small>
                            <span id="kickTimerDisplay" class="fw-bold text-dark">00:00</span>
                        </div>
                    </div>

                    <form id="kickForm" method="POST" action="" class="mt-4" style="display: none;">
                        <input type="hidden" name="start_time" id="k_start_time">
                        <input type="hidden" name="end_time" id="k_end_time">
                        <input type="hidden" name="total_kicks" id="k_total">
                        <input type="hidden" name="duration_minutes" id="k_duration">
                        <button type="submit" name="save_kick_session" class="btn btn-outline-danger rounded-pill w-100 fw-bold mb-2">End & Save Session</button>
                    </form>

                    <button id="resetKickBtn" class="btn btn-link text-muted mt-2 d-none text-decoration-none small">Cancel Session</button>
                </div>
            </div>

            <!-- Contraction Timer Tool -->
            <div class="col-lg-6">
                <div class="tool-card text-center">
                    <h5 class="fw-bold mb-1"><i class="bi bi-stopwatch text-primary me-2"></i> Contraction Timer</h5>
                    <p class="text-muted small mb-4">Time how long your contractions last to track active labor progression.</p>

                    <div id="contractionTimerDisplay" class="timer-display">00:00</div>

                    <button id="startContractionBtn" class="timer-btn btn-start-timer">Start<br>Contraction</button>
                    <button id="stopContractionBtn" class="timer-btn btn-stop-timer">Stop<br>Timer</button>

                    <div id="intensitySelector" class="mt-4 d-none p-3 bg-light rounded-4 border">
                        <p class="mb-2 fw-bold small text-dark text-uppercase">How strong was the contraction?</p>
                        <form id="contractionForm" method="POST" action="">
                            <input type="hidden" name="contraction_start" id="c_start_time">
                            <input type="hidden" name="contraction_duration" id="c_duration">
                            <input type="hidden" name="contraction_interval" id="c_interval" value="">

                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="intensity" id="int_mild" value="Mild" checked>
                                <label class="btn btn-outline-success" for="int_mild">Mild</label>

                                <input type="radio" class="btn-check" name="intensity" id="int_mod" value="Moderate">
                                <label class="btn btn-outline-warning" for="int_mod">Moderate</label>

                                <input type="radio" class="btn-check" name="intensity" id="int_strong" value="Strong">
                                <label class="btn btn-outline-danger" for="int_strong">Strong</label>
                            </div>
                            <button type="submit" name="save_contraction" class="btn btn-primary w-100 rounded-pill fw-bold">Save Contraction Log</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- History Tables (Filtered by Current Cycle) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-danger me-2"></i> Kicks (Current Cycle)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 text-center">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th>Date</th>
                                    <th>Total Kicks</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_kicks as $k): ?>
                                    <tr>
                                        <td class="text-muted">
                                            <div class="fw-bold text-dark"><?php echo date('M d', strtotime($k['start_time'])); ?></div><small><?php echo date('g:i A', strtotime($k['start_time'])); ?></small>
                                        </td>
                                        <td><span class="badge bg-danger rounded-pill px-3 py-2" style="font-size: 0.9rem;"><?php echo $k['total_kicks']; ?></span></td>
                                        <td class="fw-bold"><?php echo $k['duration_minutes']; ?> <small class="text-muted fw-normal">min</small></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_kicks)) echo '<tr><td colspan="3" class="text-muted py-4 small">No sessions saved yet in this pregnancy cycle.</td></tr>'; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-stopwatch text-primary me-2"></i> Contractions (Current Cycle)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 text-center">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Intensity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_contractions as $c): ?>
                                    <tr>
                                        <td class="text-muted">
                                            <div class="fw-bold text-dark"><?php echo date('M d', strtotime($c['start_time'])); ?></div><small><?php echo date('g:i A', strtotime($c['start_time'])); ?></small>
                                        </td>
                                        <td><strong><?php echo $c['duration_seconds']; ?>s</strong></td>
                                        <td>
                                            <?php
                                            $color = $c['intensity'] == 'Strong' ? 'danger' : ($c['intensity'] == 'Moderate' ? 'warning text-dark' : 'success');
                                            echo "<span class='badge bg-{$color} bg-opacity-10 text-{$color} border border-{$color} border-opacity-25 px-2 py-1 rounded-pill'>{$c['intensity']}</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_contractions)) echo '<tr><td colspan="3" class="text-muted py-4 small">No contractions logged yet in this pregnancy cycle.</td></tr>'; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- JavaScript for Tools Logic -->
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        function toMySqlLocalString(date) {
            const tzoffset = date.getTimezoneOffset() * 60000;
            return (new Date(date - tzoffset)).toISOString().slice(0, 19).replace('T', ' ');
        }

        // --- Kick Counter Logic ---
        let kickCount = 0;
        let kickStartTime = null;
        let kickTimerInterval = null;

        const kickBtn = document.getElementById('kickBtn');
        const kickDisplay = document.getElementById('kickDisplay');
        const kickLabel = document.getElementById('kickLabel');
        const kickStatus = document.getElementById('kickStatus');
        const kickTimerDisplay = document.getElementById('kickTimerDisplay');
        const kickForm = document.getElementById('kickForm');
        const resetKickBtn = document.getElementById('resetKickBtn');

        function updateKickTimer() {
            if (!kickStartTime) return;
            const now = new Date();
            const diff = Math.floor((now - kickStartTime) / 1000);
            const m = Math.floor(diff / 60).toString().padStart(2, '0');
            const s = (diff % 60).toString().padStart(2, '0');
            kickTimerDisplay.textContent = `${m}:${s}`;
        }

        function prepareKickForm() {
            const endTime = new Date();
            const durationMins = Math.floor((endTime - kickStartTime) / 60000);

            document.getElementById('k_end_time').value = toMySqlLocalString(endTime);
            document.getElementById('k_total').value = kickCount;
            document.getElementById('k_duration').value = durationMins;
        }

        kickBtn.addEventListener('click', () => {
            if (kickCount === 0) {
                kickStartTime = new Date();
                kickStatus.textContent = "Counting active...";
                kickStatus.classList.replace('text-dark', 'text-danger');
                kickTimerInterval = setInterval(updateKickTimer, 1000);

                document.getElementById('k_start_time').value = toMySqlLocalString(kickStartTime);
                resetKickBtn.classList.remove('d-none');
                kickLabel.textContent = "Tap to Log";
                kickForm.style.display = 'block';
            }

            if (kickCount < 10) {
                kickCount++;
                kickDisplay.textContent = kickCount;
                prepareKickForm();
            }

            if (kickCount >= 10) {
                clearInterval(kickTimerInterval);
                kickStatus.textContent = "Goal Reached! (10 Kicks)";
                kickStatus.classList.replace('text-danger', 'text-success');
                kickBtn.disabled = true;
                kickBtn.style.opacity = '0.5';
                kickLabel.textContent = "Session Complete";
            }
        });

        kickForm.addEventListener('submit', (e) => {
            if (kickCount === 0) {
                e.preventDefault();
                alert("Please log at least one kick before saving.");
            }
        });

        resetKickBtn.addEventListener('click', () => {
            clearInterval(kickTimerInterval);
            kickCount = 0;
            kickStartTime = null;
            kickDisplay.textContent = '0';
            kickLabel.textContent = "Tap to Start";
            kickTimerDisplay.textContent = '00:00';
            kickStatus.textContent = "Ready";
            kickStatus.classList.remove('text-success', 'text-danger');
            kickStatus.classList.add('text-dark');
            kickBtn.disabled = false;
            kickBtn.style.opacity = '1';
            kickForm.style.display = 'none';
            resetKickBtn.classList.add('d-none');
        });

        // --- Contraction Timer Logic ---
        let conStartTime = null;
        let conTimerInterval = null;
        let lastContractionStart = localStorage.getItem('lastContractionStart') ? new Date(localStorage.getItem('lastContractionStart')) : null;

        if (lastContractionStart) {
            const hoursSinceLast = (new Date() - lastContractionStart) / (1000 * 60 * 60);
            if (hoursSinceLast > 24) {
                localStorage.removeItem('lastContractionStart');
                lastContractionStart = null;
            }
        }

        const startConBtn = document.getElementById('startContractionBtn');
        const stopConBtn = document.getElementById('stopContractionBtn');
        const conDisplay = document.getElementById('contractionTimerDisplay');
        const intensitySelector = document.getElementById('intensitySelector');

        function updateConTimer() {
            if (!conStartTime) return;
            const now = new Date();
            const diff = Math.floor((now - conStartTime) / 1000);
            const m = Math.floor(diff / 60).toString().padStart(2, '0');
            const s = (diff % 60).toString().padStart(2, '0');
            conDisplay.textContent = `${m}:${s}`;
        }

        startConBtn.addEventListener('click', () => {
            conStartTime = new Date();
            startConBtn.style.display = 'none';
            stopConBtn.style.display = 'flex';
            intensitySelector.classList.add('d-none');

            conTimerInterval = setInterval(updateConTimer, 1000);

            document.getElementById('c_start_time').value = toMySqlLocalString(conStartTime);

            if (lastContractionStart) {
                const intervalMins = Math.floor((conStartTime - lastContractionStart) / 60000);
                document.getElementById('c_interval').value = intervalMins;
            } else {
                document.getElementById('c_interval').value = '';
            }

            localStorage.setItem('lastContractionStart', conStartTime.toString());
        });

        stopConBtn.addEventListener('click', () => {
            clearInterval(conTimerInterval);
            const endTime = new Date();
            const durationSecs = Math.floor((endTime - conStartTime) / 1000);

            document.getElementById('c_duration').value = durationSecs;

            stopConBtn.style.display = 'none';
            startConBtn.style.display = 'flex';
            startConBtn.innerHTML = 'Log Another<br>Contraction';

            intensitySelector.classList.remove('d-none');
        });
    </script>
</body>

</html>