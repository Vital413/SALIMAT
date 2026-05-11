<?php
// doctor/records.php - Electronic Medical Records (EMR) Directory
require_once '../config/config.php';

if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$error_msg = '';

try {
    // Build query to fetch patients assigned to this doctor, along with record stats
    $query = "
        SELECT p.patient_id, p.first_name, p.last_name, p.email, p.phone, p.expected_due_date,
               (SELECT MAX(recorded_at) FROM vitals v WHERE v.patient_id = p.patient_id) as last_vital_date,
               (SELECT COUNT(*) FROM lab_tests l WHERE l.patient_id = p.patient_id AND l.status = 'Completed') as completed_labs,
               (SELECT COUNT(*) FROM clinical_notes c WHERE c.patient_id = p.patient_id AND c.doctor_id = ?) as note_count
        FROM patients p 
        WHERE p.doctor_id = ?
    ";

    $params = [$doctor_id, $doctor_id];

    // Append search filtering if provided
    if (!empty($search_query)) {
        $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id = ?)";
        $search_param = "%{$search_query}%";
        array_push($params, $search_param, $search_param, $search_query);
    }

    $query .= " ORDER BY p.last_name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();

    // Sidebar unread messages count
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
    $msgStmt->execute([$doctor_id]);
    $unread_messages = $msgStmt->fetchColumn();
} catch (PDOException $e) {
    $error_msg = "Error loading medical records directory.";
    $patients = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMR Directory - LuminaCare Provider</title>
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

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-heart-pulse-fill me-2"></i>Lumina<span>Care</span></a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <a href="patients.php" class="nav-link"><i class="bi bi-people-fill"></i> My Patients</a>
            <a href="messages.php" class="nav-link">
                <i class="bi bi-chat-dots-fill"></i> Messages
                <?php if ($unread_messages > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span><?php endif; ?>
            </a>
            <a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check-fill"></i> Appointments</a>

            <div class="mt-4 px-4 small text-muted text-uppercase fw-bold">Clinical Logs</div>
            <a href="records.php" class="nav-link active"><i class="bi bi-folder-check"></i> EMR Directory</a>

            <div class="mt-4 px-4 small text-muted text-uppercase fw-bold">Account</div>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Settings</a>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h3 class="mb-0 fw-bold">EMR Directory</h3>
                    <p class="text-muted mb-0 small">Access and review all medical records for your assigned patients.</p>
                </div>
            </div>

            <!-- Search Form -->
            <form method="GET" action="records.php" class="d-flex gap-2">
                <div class="input-group shadow-sm" style="max-width: 350px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0 bg-white" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <button type="submit" class="btn btn-primary shadow-sm" style="background-color: var(--primary-color); border: none;">Search</button>
                <?php if (!empty($search_query)): ?>
                    <a href="records.php" class="btn btn-outline-secondary shadow-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-4">Patient Info</th>
                            <th>EDD (Due Date)</th>
                            <th>Clinical Notes</th>
                            <th>Labs Completed</th>
                            <th>Last Vitals Update</th>
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
                                        <?php if ($p['expected_due_date']): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill border border-primary border-opacity-25">
                                                <?php echo date('M d, Y', strtotime($p['expected_due_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-journal-text text-warning me-1"></i> <?php echo $p['note_count']; ?> Notes</span>
                                    </td>
                                    <td>
                                        <?php if ($p['completed_labs'] > 0): ?>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-file-medical text-success me-1"></i> <?php echo $p['completed_labs']; ?> Reports</span>
                                        <?php else: ?>
                                            <span class="text-muted small">0 Reports</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['last_vital_date']): ?>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($p['last_vital_date'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> No history</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="patient_details.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">Open Full Chart</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x fs-2 d-block mb-2"></i>
                                    No patient records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
    </script>
</body>

</html>