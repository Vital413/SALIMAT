<?php
// doctor/messages.php - Doctor Chat System
require_once '../config/config.php';

if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$active_patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

// 1. Fetch all assigned patients for the sidebar list
try {
    $stmt = $pdo->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE doctor_id = ? ORDER BY last_name ASC");
    $stmt->execute([$doctor_id]);
    $assigned_patients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error loading patients.");
}

// 2. Handle Message Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_patient_id) {
    if (isset($_POST['send_message'])) {
        $message_body = trim(htmlspecialchars($_POST['message_body'], ENT_QUOTES, 'UTF-8'));
        if (!empty($message_body)) {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_body) VALUES (?, 'doctor', ?, 'patient', ?)");
            $stmt->execute([$doctor_id, $active_patient_id, $message_body]);
            header("Location: messages.php?patient_id=" . $active_patient_id);
            exit();
        }
    } elseif (isset($_POST['request_appointment'])) {
        $msg_body = "Hello. Please request an appointment with me at your earliest convenience so we can discuss your recent health logs and overall progress. Thank you.";
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_body) VALUES (?, 'doctor', ?, 'patient', ?)");
        $stmt->execute([$doctor_id, $active_patient_id, $msg_body]);
        header("Location: messages.php?patient_id=" . $active_patient_id);
        exit();
    }
}

// 3. Fetch Chat History if a patient is selected
$messages = [];
$active_patient = null;

if ($active_patient_id) {
    // Verify patient belongs to doctor
    $checkStmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ? AND doctor_id = ?");
    $checkStmt->execute([$active_patient_id, $doctor_id]);
    $active_patient = $checkStmt->fetch();

    if ($active_patient) {
        $msgStmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND sender_role = 'doctor' AND receiver_id = ? AND receiver_role = 'patient')
               OR (sender_id = ? AND sender_role = 'patient' AND receiver_id = ? AND receiver_role = 'doctor')
            ORDER BY sent_at ASC
        ");
        $msgStmt->execute([$doctor_id, $active_patient_id, $active_patient_id, $doctor_id]);
        $messages = $msgStmt->fetchAll();

        // Mark patient's messages as read
        $updateStmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND receiver_role = 'doctor' AND sender_id = ? AND sender_role = 'patient'");
        $updateStmt->execute([$doctor_id, $active_patient_id]);
    }
}

// Sidebar notification count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'doctor' AND is_read = 0");
$unreadStmt->execute([$doctor_id]);
$unread_messages = $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - LuminaCare Provider</title>
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
            height: 100vh;
            display: flex;
            flex-direction: column;
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
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* Chat Layout */
        .chat-layout {
            display: flex;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            flex-grow: 1;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .chat-sidebar {
            width: 300px;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            background: #fafbfc;
        }

        .chat-list {
            overflow-y: auto;
            flex-grow: 1;
        }

        .patient-chat-link {
            display: block;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.2s;
        }

        .patient-chat-link:hover,
        .patient-chat-link.active {
            background: white;
            border-left: 4px solid var(--primary-color);
        }

        .chat-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-box {
            flex-grow: 1;
            padding: 25px;
            background: #f8fafb;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .msg-bubble {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 20px;
            position: relative;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .msg-sent {
            background: var(--primary-color);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .msg-received {
            background: white;
            color: var(--text-dark);
            border: 1px solid #e1e5e8;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .msg-time {
            font-size: 0.7rem;
            margin-top: 5px;
            opacity: 0.7;
            text-align: right;
            display: block;
        }

        .chat-input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #eee;
        }

        .chat-input-group {
            display: flex;
            gap: 10px;
            background: #f4f7f6;
            padding: 8px;
            border-radius: 50px;
            border: 1px solid #eee;
        }

        .chat-input {
            border: none;
            background: transparent;
            flex-grow: 1;
            padding: 10px 20px;
            outline: none;
        }

        .btn-send {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50px;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .btn-send:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
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

            .chat-sidebar {
                display: none;
            }

            /* Hide list on mobile when chatting */
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
                <a href="messages.php" class="nav-link active">
                    <i class="bi bi-chat-dots-fill"></i> Messages
                    <?php if ($unread_messages > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check-fill"></i> Appointments</a></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-gear-fill"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center mb-3 gap-3 d-lg-none">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h4 class="mb-0 fw-bold">Messages</h4>
        </div>

        <div class="chat-layout">
            <!-- Patient List Sidebar -->
            <div class="chat-sidebar">
                <div class="p-3 border-bottom bg-white fw-bold text-muted text-uppercase" style="font-size: 0.85rem;">Assigned Patients</div>
                <div class="chat-list">
                    <?php foreach ($assigned_patients as $p): ?>
                        <a href="messages.php?patient_id=<?php echo $p['patient_id']; ?>" class="patient-chat-link <?php echo ($active_patient_id == $p['patient_id']) ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 35px; height: 35px; border-radius: 50%; background: var(--secondary-color); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: bold;">
                                    <?php echo strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold fs-6"><?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="chat-main">
                <?php if ($active_patient): ?>
                    <div class="chat-header">
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($active_patient['first_name'] . ' ' . $active_patient['last_name']); ?></h5>
                            <small class="text-muted">Patient ID: #<?php echo str_pad($active_patient_id, 4, '0', STR_PAD_LEFT); ?></small>
                        </div>
                        <form method="POST" action="">
                            <button type="submit" name="request_appointment" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" onclick="return confirm('Send appointment request?');">
                                <i class="bi bi-calendar-plus me-1"></i> Request Appt
                            </button>
                        </form>
                    </div>

                    <div class="chat-box" id="chatBox">
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php
                                $isSent = ($msg['sender_role'] === 'doctor');
                                $bubbleClass = $isSent ? 'msg-sent' : 'msg-received';
                                ?>
                                <div class="msg-bubble <?php echo $bubbleClass; ?>">
                                    <?php echo nl2br(htmlspecialchars($msg['message_body'])); ?>
                                    <span class="msg-time"><?php echo date('M d, h:i A', strtotime($msg['sent_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted mt-5">
                                <i class="bi bi-chat-quote fs-1 mb-2 d-block opacity-50"></i>
                                <p>No messages yet. Start the conversation.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="chat-input-area">
                        <form action="" method="POST">
                            <div class="chat-input-group">
                                <input type="text" name="message_body" class="chat-input" placeholder="Type your message..." required autocomplete="off">
                                <button type="submit" name="send_message" class="btn-send"><i class="bi bi-send-fill"></i></button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 p-5 text-center bg-light">
                        <i class="bi bi-chat-square-text text-muted" style="font-size: 4rem;"></i>
                        <h4 class="mt-3 fw-bold text-muted">Select a Patient</h4>
                        <p class="text-muted">Choose a patient from the list on the left to view history and send messages.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        const chatBox = document.getElementById('chatBox');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>

</html>