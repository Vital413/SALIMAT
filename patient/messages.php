<?php
// patient/messages.php - Secure Patient-Doctor Chat System
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];

// 1. Fetch assigned doctor info
$docStmt = $pdo->prepare("
    SELECT d.doctor_id, d.first_name, d.last_name 
    FROM patients p 
    LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
    WHERE p.patient_id = ?
");
$docStmt->execute([$patient_id]);
$doctor = $docStmt->fetch();

$doctor_id = $doctor ? $doctor['doctor_id'] : null;

// 2. Handle Message Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $doctor_id) {
    // Basic sanitization
    $message_body = trim(htmlspecialchars($_POST['message_body'], ENT_QUOTES, 'UTF-8'));
    
    if (!empty($message_body)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_body) VALUES (?, 'patient', ?, 'doctor', ?)");
            $stmt->execute([$patient_id, $doctor_id, $message_body]);
            
            // Redirect to prevent form resubmission on refresh
            header("Location: messages.php");
            exit();
        } catch (PDOException $e) {
            $error_msg = "Failed to send message.";
        }
    }
}

// 3. Fetch Chat History
$messages = [];
if ($doctor_id) {
    try {
        $msgStmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND sender_role = 'patient' AND receiver_id = ? AND receiver_role = 'doctor')
               OR (sender_id = ? AND sender_role = 'doctor' AND receiver_id = ? AND receiver_role = 'patient')
            ORDER BY sent_at ASC
        ");
        $msgStmt->execute([$patient_id, $doctor_id, $doctor_id, $patient_id]);
        $messages = $msgStmt->fetchAll();
        
        // Mark doctor's messages as read
        $updateStmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND receiver_role = 'patient' AND sender_id = ? AND sender_role = 'doctor'");
        $updateStmt->execute([$patient_id, $doctor_id]);
        
    } catch (PDOException $e) {
        $error_msg = "Failed to load messages.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Shared Sidebar CSS */
        :root {
            --primary-color: #2b7a78; --secondary-color: #3aafa9; --accent-color: #ff9a9e;
            --text-dark: #17252a; --bg-light: #f4f7f6; --sidebar-width: 250px;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        
        .sidebar { width: var(--sidebar-width); background-color: white; height: 100vh; position: fixed; top: 0; left: 0; box-shadow: 2px 0 15px rgba(0,0,0,0.05); z-index: 1000; display: flex; flex-direction: column; transition: all 0.3s; }
        .sidebar-brand { padding: 20px; font-size: 1.5rem; font-weight: 800; color: var(--primary-color); text-decoration: none; border-bottom: 1px solid #eee; }
        .sidebar-brand span { color: var(--accent-color); }
        .nav-menu { padding: 20px 0; flex-grow: 1; }
        .nav-link { color: var(--text-dark); padding: 12px 25px; display: flex; align-items: center; font-weight: 500; transition: all 0.3s; border-left: 4px solid transparent; text-decoration: none;}
        .nav-link i { margin-right: 15px; font-size: 1.2rem; color: var(--secondary-color); }
        .nav-link:hover, .nav-link.active { background-color: rgba(58, 175, 169, 0.08); color: var(--primary-color); border-left: 4px solid var(--primary-color); }
        .logout-wrapper { padding: 20px; border-top: 1px solid #eee; }
        .main-content { margin-left: var(--sidebar-width); padding: 30px; transition: all 0.3s; height: 100vh; display: flex; flex-direction: column;}
        
        /* Chat Specific CSS */
        .chat-container { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
        .chat-header { padding: 20px 25px; border-bottom: 1px solid #eee; background: white; display: flex; align-items: center; gap: 15px; }
        .chat-box { flex-grow: 1; padding: 25px; background: #f8fafb; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .msg-bubble { max-width: 75%; padding: 12px 18px; border-radius: 20px; position: relative; font-size: 0.95rem; line-height: 1.5; }
        .msg-sent { background: var(--primary-color); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-received { background: white; color: var(--text-dark); border: 1px solid #e1e5e8; align-self: flex-start; border-bottom-left-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .msg-time { font-size: 0.7rem; margin-top: 5px; opacity: 0.7; text-align: right; display: block; }
        .chat-input-area { padding: 20px; background: white; border-top: 1px solid #eee; }
        .chat-input-group { display: flex; gap: 10px; background: #f4f7f6; padding: 8px; border-radius: 50px; border: 1px solid #eee; }
        .chat-input { border: none; background: transparent; flex-grow: 1; padding: 10px 20px; outline: none; }
        .btn-send { background: var(--primary-color); color: white; border: none; border-radius: 50px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .btn-send:hover { background: var(--secondary-color); transform: scale(1.05); }

        .mobile-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--primary-color); }
        
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .mobile-toggle { display: block; }
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
            <div class="nav-item"><a href="messages.php" class="nav-link active"><i class="bi bi-chat-dots-fill"></i> Messages</a></div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i> Appointments</a></div>
            <div class="nav-item mt-4"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
            <div class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Profile Settings</a></div>
        </div>
        <div class="logout-wrapper"><a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex align-items-center mb-3 gap-3 d-lg-none">
            <button class="mobile-toggle" id="openSidebar"><i class="bi bi-list"></i></button>
            <h4 class="mb-0 fw-bold">Messages</h4>
        </div>

        <div class="chat-container">
            <?php if ($doctor_id): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <div style="width: 45px; height: 45px; border-radius: 50%; background: rgba(43, 122, 120, 0.1); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <h5 class="mb-0 fw-bold">Dr. <?php echo htmlspecialchars($doctor['last_name']); ?></h5>
                        <small class="text-success fw-bold"><i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> Active Provider</small>
                    </div>
                </div>

                <!-- Chat Box -->
                <div class="chat-box" id="chatBox">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php 
                                $isSent = ($msg['sender_role'] === 'patient');
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
                            <p>No messages yet. Send a message to start the conversation.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Input Area -->
                <div class="chat-input-area">
                    <form action="messages.php" method="POST">
                        <div class="chat-input-group">
                            <input type="text" name="message_body" class="chat-input" placeholder="Type your message here..." required autocomplete="off">
                            <button type="submit" name="send_message" class="btn-send"><i class="bi bi-send-fill"></i></button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- No Doctor Assigned State -->
                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-5 text-center">
                    <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 fw-bold">No Provider Assigned</h4>
                    <p class="text-muted">You have not been assigned a healthcare provider yet. Once an admin assigns a doctor to your case, you will be able to message them here.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));
        
        // Auto-scroll chat to bottom
        const chatBox = document.getElementById('chatBox');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>
</html>