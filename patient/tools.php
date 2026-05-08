<?php
// patient/tools.php - Interactive Pregnancy Tools (Kick Counter & Contraction Timer)
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$success_msg = '';
$error_msg = '';

// Handle Tool Submissions
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
                    $success_msg = "Kick session saved successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to save kick session.";
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
            // Interval can be null for the first contraction
            $interval_minutes = $interval_minutes !== false ? $interval_minutes : null;

            try {
                $stmt = $pdo->prepare("INSERT INTO contraction_logs (patient_id, start_time, duration_seconds, interval_minutes, intensity) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$patient_id, $start_time, $duration_seconds, $interval_minutes, $intensity])) {
                    $success_msg = "Contraction logged successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to log contraction.";
            }
        }
    }
}

// Fetch recent logs for display
try {
    $kick_logs = $pdo->prepare("SELECT * FROM kick_logs WHERE patient_id = ? ORDER BY start_time DESC LIMIT 5");
    $kick_logs->execute([$patient_id]);
    $recent_kicks = $kick_logs->fetchAll();

    $contraction_logs = $pdo->prepare("SELECT * FROM contraction_logs WHERE patient_id = ? ORDER BY start_time DESC LIMIT 5");
    $contraction_logs->execute([$patient_id]);
    $recent_contractions = $contraction_logs->fetchAll();
} catch (PDOException $e) {
    // Silently fail if tables don't exist yet, but in a real scenario we'd log this
    $recent_kicks = [];
    $recent_contractions = [];
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
        /* Shared Sidebar CSS */
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

        /* Tool Specific Styles */
        .tool-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        /* Kick Counter Styles */
        .kick-btn {
            width: 150px;
            height: 150px;
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
            font-size: 3.5rem;
            line-height: 1;
            font-weight: 800;
        }

        /* Contraction Timer Styles */
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
            width: 120px;
            height: 120px;
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

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-heart-pulse-fill me-2"></i>Lumina<span>Care</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
            <div class="nav-item"><a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-clipboard2-pulse"></i> Log Vitals</a></div>
            <div class="nav-item"><a href="tools.php" class="nav-link active"><i class="bi bi-heartbreak-fill"></i> Pregnancy Tools</a></div>
            <div class="nav-item"><a href="messages.php" class="nav-link"><i class="bi bi-chat-dots"></i> Messages</a></div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i> Appointments</a></div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex align-items-center mb-4 gap-3">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold">Interactive Tools</h3>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4">

            <!-- Kick Counter Tool -->
            <div class="col-lg-6">
                <div class="tool-card text-center">
                    <h5 class="fw-bold mb-1"><i class="bi bi-heart-pulse text-danger me-2"></i> Kick Counter</h5>
                    <p class="text-muted small mb-4">Tap the button every time you feel a movement. Aim for 10 kicks within 2 hours.</p>

                    <button id="kickBtn" class="kick-btn">
                        <span id="kickDisplay" class="kick-count-display">0</span>
                        <span class="small fw-normal">Tap to Log</span>
                    </button>

                    <div class="mt-4 d-flex justify-content-center gap-4 text-muted">
                        <div>
                            <small class="d-block text-uppercase fw-bold">Status</small>
                            <span id="kickStatus" class="fw-bold text-dark">Ready</span>
                        </div>
                        <div>
                            <small class="d-block text-uppercase fw-bold">Timer</small>
                            <span id="kickTimerDisplay" class="fw-bold text-dark">00:00</span>
                        </div>
                    </div>

                    <!-- Hidden form to save session -->
                    <form id="kickForm" method="POST" action="" class="mt-4" style="display: none;">
                        <input type="hidden" name="start_time" id="k_start_time">
                        <input type="hidden" name="end_time" id="k_end_time">
                        <input type="hidden" name="total_kicks" id="k_total">
                        <input type="hidden" name="duration_minutes" id="k_duration">
                        <button type="submit" name="save_kick_session" class="btn btn-outline-primary rounded-pill w-100 fw-bold">Save Session to History</button>
                    </form>
                    <button id="resetKickBtn" class="btn btn-link text-muted mt-2 d-none">Reset Counter</button>
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

                    <div id="intensitySelector" class="mt-4 d-none">
                        <p class="mb-2 fw-bold small text-muted text-uppercase">How strong was it?</p>
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

            <!-- History Tables -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold">Recent Kick Sessions</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 text-center">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>Date</th>
                                    <th>Kicks</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_kicks as $k): ?>
                                    <tr>
                                        <td><?php echo date('M d, g:i A', strtotime($k['start_time'])); ?></td>
                                        <td><span class="badge bg-danger rounded-pill"><?php echo $k['total_kicks']; ?></span></td>
                                        <td><?php echo $k['duration_minutes']; ?> min</td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_kicks)) echo '<tr><td colspan="3" class="text-muted py-3 small">No sessions saved yet.</td></tr>'; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold">Recent Contractions</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 text-center">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Intensity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_contractions as $c): ?>
                                    <tr>
                                        <td><?php echo date('M d, g:i A', strtotime($c['start_time'])); ?></td>
                                        <td><strong><?php echo $c['duration_seconds']; ?>s</strong></td>
                                        <td>
                                            <?php
                                            $color = $c['intensity'] == 'Strong' ? 'danger' : ($c['intensity'] == 'Moderate' ? 'warning text-dark' : 'success');
                                            echo "<span class='badge bg-{$color}'>{$c['intensity']}</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_contractions)) echo '<tr><td colspan="3" class="text-muted py-3 small">No contractions logged yet.</td></tr>'; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- JavaScript for Tools Logic -->
    <script>
        // --- Sidebar Toggle ---
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        // --- Kick Counter Logic ---
        let kickCount = 0;
        let kickStartTime = null;
        let kickTimerInterval = null;

        const kickBtn = document.getElementById('kickBtn');
        const kickDisplay = document.getElementById('kickDisplay');
        const kickStatus = document.getElementById('kickStatus');
        const kickTimerDisplay = document.getElementById('kickTimerDisplay');
        const kickForm = document.getElementById('kickForm');
        const resetKickBtn = document.getElementById('resetKickBtn');

        function updateKickTimer() {
            if (!kickStartTime) return;
            const now = new Date();
            const diff = Math.floor((now - kickStartTime) / 1000); // in seconds
            const m = Math.floor(diff / 60).toString().padStart(2, '0');
            const s = (diff % 60).toString().padStart(2, '0');
            kickTimerDisplay.textContent = `${m}:${s}`;
        }

        kickBtn.addEventListener('click', () => {
            if (kickCount === 0) {
                // First kick, start session
                kickStartTime = new Date();
                kickStatus.textContent = "Counting...";
                kickStatus.classList.replace('text-dark', 'text-danger');
                kickTimerInterval = setInterval(updateKickTimer, 1000);

                // Format MySQL datetime
                const tzoffset = kickStartTime.getTimezoneOffset() * 60000;
                const localISOTime = (new Date(kickStartTime - tzoffset)).toISOString().slice(0, 19).replace('T', ' ');
                document.getElementById('k_start_time').value = localISOTime;
                resetKickBtn.classList.remove('d-none');
            }

            kickCount++;
            kickDisplay.textContent = kickCount;

            if (kickCount >= 10) {
                // Goal reached
                clearInterval(kickTimerInterval);
                const endTime = new Date();
                const durationMins = Math.floor((endTime - kickStartTime) / 60000);

                const tzoffset = endTime.getTimezoneOffset() * 60000;
                const localISOEnd = (new Date(endTime - tzoffset)).toISOString().slice(0, 19).replace('T', ' ');

                document.getElementById('k_end_time').value = localISOEnd;
                document.getElementById('k_total').value = kickCount;
                document.getElementById('k_duration').value = durationMins;

                kickStatus.textContent = "Goal Reached!";
                kickStatus.classList.replace('text-danger', 'text-success');
                kickBtn.disabled = true;
                kickBtn.style.opacity = '0.5';
                kickForm.style.display = 'block';
            }
        });

        resetKickBtn.addEventListener('click', () => {
            clearInterval(kickTimerInterval);
            kickCount = 0;
            kickStartTime = null;
            kickDisplay.textContent = '0';
            kickTimerDisplay.textContent = '00:00';
            kickStatus.textContent = "Ready";
            kickStatus.classList.replace('text-success', 'text-dark');
            kickStatus.classList.replace('text-danger', 'text-dark');
            kickBtn.disabled = false;
            kickBtn.style.opacity = '1';
            kickForm.style.display = 'none';
            resetKickBtn.classList.add('d-none');
        });

        // --- Contraction Timer Logic ---
        let conStartTime = null;
        let conTimerInterval = null;
        let lastContractionStart = localStorage.getItem('lastContractionStart') ? new Date(localStorage.getItem('lastContractionStart')) : null;

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

            // Format start time for DB
            const tzoffset = conStartTime.getTimezoneOffset() * 60000;
            const localISOTime = (new Date(conStartTime - tzoffset)).toISOString().slice(0, 19).replace('T', ' ');
            document.getElementById('c_start_time').value = localISOTime;

            // Calculate interval if there was a previous contraction
            if (lastContractionStart) {
                const intervalMins = Math.floor((conStartTime - lastContractionStart) / 60000);
                document.getElementById('c_interval').value = intervalMins;
            }

            // Store current start time for next interval calculation
            localStorage.setItem('lastContractionStart', conStartTime);
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