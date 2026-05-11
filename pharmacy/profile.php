<?php
// pharmacy/profile.php - Edit Pharmacist Profile Settings
require_once '../config/config.php';

// Secure the page: Check if user is logged in and is a pharmacist
if (!isset($_SESSION['pharmacist_id']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: login.php");
    exit();
}

$pharmacist_id = $_SESSION['pharmacist_id'];
$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("UPDATE pharmacists SET phone = ? WHERE pharmacist_id = ?");
        if ($stmt->execute([$phone, $pharmacist_id])) {
            $success_msg = "Profile updated successfully.";
        } else {
            $error_msg = "Failed to update profile.";
        }
    } catch (PDOException $e) {
        $error_msg = "Database error occurred.";
    }
}

// Fetch current data
try {
    $stmt = $pdo->prepare("SELECT * FROM pharmacists WHERE pharmacist_id = ?");
    $stmt->execute([$pharmacist_id]);
    $pharmacist = $stmt->fetch();

    // Fetch Low Stock alerts for sidebar badge
    $low_stock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE category = 'Medicine' AND quantity <= reorder_level")->fetchColumn();
} catch (PDOException $e) {
    $error_msg = "Error loading profile data.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - LuminaCare Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #059669;
            --secondary-color: #10b981;
            --accent-color: #fbbf24;
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
            background-color: rgba(16, 185, 129, 0.08);
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
            <a href="#" class="sidebar-brand"><i class="bi bi-capsule-pill me-2"></i>Pharmacy</a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Fulfillment Queue</a>
            <a href="inventory.php" class="nav-link">
                <i class="bi bi-box-seam-fill"></i> Medicine Stock
                <?php if (isset($low_stock) && $low_stock > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;"><?php echo $low_stock; ?></span>
                <?php endif; ?>
            </a>
            <div class="mt-4 px-4 small text-muted text-uppercase fw-bold">Account</div>
            <a href="profile.php" class="nav-link active"><i class="bi bi-person-gear-fill"></i> Settings</a>
        </div>
        <div class="logout-wrapper">
            <a href="../admin/logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4 gap-3">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="mb-0 fw-bold">Pharmacist Profile Settings</h3>
        </div>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <form action="profile.php" method="POST">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-4">
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--secondary-color); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold;">
                                <?php echo strtoupper(substr($pharmacist['first_name'], 0, 1) . substr($pharmacist['last_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Ph. <?php echo htmlspecialchars($pharmacist['first_name'] . ' ' . $pharmacist['last_name']); ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill"><i class="bi bi-shield-check me-1"></i> Verified Pharmacist</span>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 text-muted">Staff Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">First Name <i class="bi bi-lock-fill ms-1" title="Contact admin to change"></i></label>
                                <input type="text" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($pharmacist['first_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Last Name <i class="bi bi-lock-fill ms-1" title="Contact admin to change"></i></label>
                                <input type="text" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($pharmacist['last_name']); ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold">Professional Email <i class="bi bi-lock-fill ms-1" title="Contact admin to change"></i></label>
                                <input type="email" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($pharmacist['email']); ?>" readonly>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold">Contact Phone Number</label>
                                <input type="text" name="phone" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($pharmacist['phone'] ?? ''); ?>" placeholder="e.g. +1 234 567 8900">
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-success rounded-pill mt-4 px-4 fw-bold" style="background-color: var(--primary-color); border: none; color: white;">Save Changes</button>
                    </form>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-info-circle text-success me-2"></i> Account Support</h6>
                    <p class="small text-muted mb-4">Core staff details are locked to ensure the integrity of prescriptions and medication records. If you need to update your registered name or email, please submit a formal request to the System Administrator.</p>
                    <div class="p-3 bg-light rounded-3 border border-dashed text-center">
                        <small class="text-muted d-block mb-1">Administrative Contact</small>
                        <strong class="text-dark">admin@luminacare.local</strong>
                    </div>
                </div>
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