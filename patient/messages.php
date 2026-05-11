<?php
// patient/messages.php - Secure Patient-Doctor Chat System with Media Uploads & Edit/Soft Delete Features
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$error_msg = '';
$success_msg = '';

// --- Auto-Setup: Ensure message table has attachment, edit, and soft delete columns ---
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_path varchar(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_type varchar(50) DEFAULT NULL");
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_edited tinyint(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_deleted tinyint(1) DEFAULT 0");
} catch (PDOException $e) { /* Ignore if columns already exist */
}

// 1. Fetch assigned doctor info
try {
    $docStmt = $pdo->prepare("
        SELECT d.doctor_id, d.first_name, d.last_name 
        FROM patients p 
        LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
        WHERE p.patient_id = ?
    ");
    $docStmt->execute([$patient_id]);
    $doctor = $docStmt->fetch();

    $doctor_id = $doctor ? $doctor['doctor_id'] : null;

    // Fetch Unpaid Billing Balance (for sidebar badge)
    $billStmt = $pdo->prepare("SELECT SUM(amount - amount_paid) FROM billing WHERE patient_id = ? AND status != 'Paid'");
    $billStmt->execute([$patient_id]);
    $unpaid_balance = $billStmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $doctor_id = null;
    $unpaid_balance = 0;
}

// 2. Handle Message Actions (Send, Edit, Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor_id) {

    // --- SEND MESSAGE ---
    if (isset($_POST['send_message'])) {
        $message_body = trim(htmlspecialchars($_POST['message_body'] ?? '', ENT_QUOTES, 'UTF-8'));
        $attachment_path = null;
        $attachment_type = null;

        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/messages/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        // Process Audio Blob (Base64 from JS MediaRecorder)
        if (!empty($_POST['audio_data'])) {
            $audio_parts = explode(',', $_POST['audio_data']);
            if (count($audio_parts) == 2) {
                $audio_data = base64_decode($audio_parts[1]);
                $file_name = 'audio_' . time() . '_' . uniqid() . '.webm';
                $filepath = $upload_dir . $file_name;
                if (file_put_contents($filepath, $audio_data)) {
                    $attachment_path = $filepath;
                    $attachment_type = 'audio/webm';
                }
            }
        }

        // Process File Uploads (Images, PDFs, etc.)
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK && empty($_POST['audio_data'])) {
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $clean_name = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['attachment']['name']));
            $file_name = time() . '_' . $clean_name;
            $filepath = $upload_dir . $file_name;
            $file_type = $_FILES['attachment']['type'];

            if (move_uploaded_file($file_tmp, $filepath)) {
                $attachment_path = $filepath;
                $attachment_type = $file_type;
            }
        }

        // Insert if there's either text OR an attachment
        if (!empty($message_body) || $attachment_path) {
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message_body, attachment_path, attachment_type) VALUES (?, 'patient', ?, 'doctor', ?, ?, ?)");
                $stmt->execute([$patient_id, $doctor_id, $message_body, $attachment_path, $attachment_type]);

                header("Location: messages.php");
                exit();
            } catch (PDOException $e) {
                $error_msg = "Failed to send message.";
            }
        }
    }

    // --- EDIT MESSAGE (Within 10 Minutes, and not deleted) ---
    elseif (isset($_POST['edit_message'])) {
        $msg_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
        $new_body = trim(htmlspecialchars($_POST['edit_message_body'] ?? '', ENT_QUOTES, 'UTF-8'));

        if ($msg_id && !empty($new_body)) {
            try {
                // Ensure message belongs to patient, is not deleted, and within 10-minute window
                $stmt = $pdo->prepare("UPDATE messages SET message_body = ?, is_edited = 1 WHERE message_id = ? AND sender_id = ? AND sender_role = 'patient' AND is_deleted = 0 AND sent_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
                $stmt->execute([$new_body, $msg_id, $patient_id]);

                if ($stmt->rowCount() > 0) {
                    $success_msg = "Message edited successfully.";
                } else {
                    $error_msg = "Cannot edit message. Either it was already deleted or the 10-minute modification window has expired.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to edit message.";
            }
        }
    }

    // --- SOFT DELETE MESSAGE (Within 24 Hours) ---
    elseif (isset($_POST['soft_delete_message'])) {
        $msg_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

        if ($msg_id) {
            try {
                // Only mark as deleted if within 24 hours and not already deleted
                $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE message_id = ? AND sender_id = ? AND sender_role = 'patient' AND is_deleted = 0 AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $stmt->execute([$msg_id, $patient_id]);

                if ($stmt->rowCount() > 0) {
                    $success_msg = "Message deleted successfully. It will be hidden from both sides.";
                } else {
                    $error_msg = "Cannot delete message. The 24-hour deletion window has expired or the message was already deleted.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to delete message.";
            }
        }
    }
}

// 3. Fetch Chat History (excluding messages where both sender and receiver are not relevant, but we include deleted ones to show placeholder)
$messages = [];
$unread_msgs = 0;

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
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Chat Specific CSS */
        .chat-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .chat-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            background: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-box {
            flex-grow: 1;
            padding: 25px;
            padding-bottom: 40px;
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
            padding-right: 25px;
        }

        .msg-received {
            background: white;
            color: var(--text-dark);
            border: 1px solid #e1e5e8;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .msg-deleted {
            background: #e9ecef;
            color: #6c757d;
            align-self: center;
            font-size: 0.85rem;
            font-style: italic;
            max-width: 50%;
            text-align: center;
            border-radius: 20px;
            padding: 8px 15px;
        }

        /* Actions Dropdown for Sent Messages */
        .msg-actions-btn {
            position: absolute;
            top: 10px;
            right: 8px;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }

        .msg-actions-btn:hover {
            color: white;
        }

        .msg-time {
            font-size: 0.7rem;
            margin-top: 5px;
            opacity: 0.7;
            text-align: right;
            display: block;
        }

        .chat-attachment-img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 12px;
            margin-bottom: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .chat-audio-player {
            max-width: 250px;
            height: 40px;
            margin-bottom: 5px;
        }

        .chat-file-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .msg-received .chat-file-link {
            background: #f1f5f9;
            border-color: #e5e7eb;
        }

        .chat-input-area {
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            flex-direction: column;
        }

        .preview-area {
            padding: 15px 20px 0 20px;
            display: none;
        }

        .preview-content-box {
            background: #f8fafb;
            border: 1px solid #e1e5e8;
            border-radius: 8px;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 15px 20px;
        }

        .chat-input-wrapper {
            display: flex;
            flex-grow: 1;
            align-items: center;
            background: #f4f7f6;
            border-radius: 50px;
            padding: 5px 15px;
            border: 1px solid #eee;
        }

        .chat-input {
            border: none;
            background: transparent;
            flex-grow: 1;
            padding: 8px 10px;
            outline: none;
        }

        .btn-action {
            background: transparent;
            color: #6c757d;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.2s;
        }

        .btn-action:hover {
            background: #e1e5e8;
            color: var(--primary-color);
        }

        .btn-record {
            background: #e1e5e8;
        }

        .btn-recording {
            background: #dc3545 !important;
            color: white !important;
            animation: pulse 1.5s infinite;
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
            flex-shrink: 0;
        }

        .btn-send:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
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
            <div class="nav-item"><a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#logVitalsModal"><i class="bi bi-clipboard2-pulse"></i> Log Vitals</a></div>

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Clinical Data</small></div>
            <div class="nav-item"><a href="tools.php" class="nav-link"><i class="bi bi-heartbreak"></i> Tools & Medications</a></div>
            <div class="nav-item"><a href="lab_results.php" class="nav-link"><i class="bi bi-file-medical"></i> Lab Results</a></div>

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Manage</small></div>
            <div class="nav-item">
                <a href="billing.php" class="nav-link">
                    <i class="bi bi-receipt"></i> Billing & Invoices
                    <?php if ($unpaid_balance > 0): ?><span class="badge bg-danger rounded-pill ms-auto">!</span><?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="messages.php" class="nav-link active">
                    <i class="bi bi-chat-dots-fill"></i> Messages
                </a>
            </div>
            <div class="nav-item"><a href="appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i> Appointments</a></div>

            <div class="nav-item mt-3 mb-1"><small class="text-muted px-4 fw-bold text-uppercase" style="font-size: 0.75rem;">Account</small></div>
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

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

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
                        <?php
                        $current_time = time();
                        foreach ($messages as $msg):
                            $isSent = ($msg['sender_role'] === 'patient');
                            $isDeleted = ($msg['is_deleted'] == 1);

                            // If message is deleted, show a simple placeholder (no actions, no attachments)
                            if ($isDeleted):
                        ?>
                                <div class="msg-bubble msg-deleted text-muted">
                                    <i class="bi bi-trash3 me-1"></i> This message was deleted
                                    <div class="msg-time mt-1">
                                        <?php echo date('M d, h:i A', strtotime($msg['sent_at'])); ?>
                                    </div>
                                </div>
                            <?php
                                continue;
                            endif;

                            $bubbleClass = $isSent ? 'msg-sent' : 'msg-received';
                            // Calculate time difference for edit/delete logic (only for non-deleted sent messages)
                            $sent_time = strtotime($msg['sent_at']);
                            $diff_minutes = floor(($current_time - $sent_time) / 60);
                            $can_edit = ($isSent && $diff_minutes <= 10);
                            $can_delete = ($isSent && $diff_minutes <= 1440); // 24 hours = 1440 minutes
                            ?>
                            <div class="msg-bubble <?php echo $bubbleClass; ?>">

                                <!-- Edit & Delete Dropdown Menu for Sender (only if not deleted) -->
                                <?php if ($can_edit || $can_delete): ?>
                                    <div class="dropdown position-absolute" style="top: 10px; right: 10px;">
                                        <button class="msg-actions-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-display="static">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width: 120px;">
                                            <?php if ($can_edit): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item small" onclick="openEditModal(this)" data-msg-id="<?php echo $msg['message_id']; ?>" data-msg-body="<?php echo htmlspecialchars($msg['message_body'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <i class="bi bi-pencil me-2 text-primary"></i> Edit Message
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item small text-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-msg-id="<?php echo $msg['message_id']; ?>">
                                                        <i class="bi bi-trash me-2"></i> Delete Message
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- Attachment Rendering -->
                                <?php if (!empty($msg['attachment_path'])): ?>
                                    <?php if (strpos($msg['attachment_type'], 'image/') === 0): ?>
                                        <img src="<?php echo htmlspecialchars($msg['attachment_path']); ?>" alt="Image Attachment" class="chat-attachment-img">
                                    <?php elseif (strpos($msg['attachment_type'], 'audio/') === 0): ?>
                                        <audio controls class="chat-audio-player">
                                            <source src="<?php echo htmlspecialchars($msg['attachment_path']); ?>" type="<?php echo htmlspecialchars($msg['attachment_type']); ?>">
                                            Browser doesn't support audio.
                                        </audio>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($msg['attachment_path']); ?>" target="_blank" class="chat-file-link">
                                            <i class="bi bi-file-earmark-text fs-5"></i> View Attached File
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Text Rendering -->
                                <?php if (!empty($msg['message_body'])): ?>
                                    <div><?php echo nl2br(htmlspecialchars($msg['message_body'])); ?></div>
                                <?php endif; ?>

                                <div class="msg-time mt-1">
                                    <?php if ($msg['is_edited']): ?>
                                        <span class="me-1 opacity-75 fst-italic" style="font-size: 0.65rem;"><i class="bi bi-pencil-square"></i> Edited</span>
                                    <?php endif; ?>
                                    <span><?php echo date('M d, h:i A', strtotime($msg['sent_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted mt-5">
                            <i class="bi bi-chat-quote fs-1 mb-2 d-block opacity-50"></i>
                            <p>No messages yet. Send a message to start the conversation.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Input Area with Attachments and Voice Note -->
                <div class="chat-input-area">
                    <form action="messages.php" method="POST" enctype="multipart/form-data" id="chatForm">

                        <!-- Preview Area for Files/Audio -->
                        <div class="preview-area" id="previewArea">
                            <div class="preview-content-box">
                                <div id="previewContent" class="d-flex align-items-center text-truncate small fw-bold text-dark"></div>
                                <button type="button" class="btn-close" onclick="clearPreview()"></button>
                            </div>
                        </div>

                        <div class="chat-input-group">
                            <!-- Hidden inputs for media -->
                            <input type="file" name="attachment" id="fileInput" class="d-none" accept="image/*,audio/*,video/*,.pdf,.doc,.docx" onchange="handleFileSelect(event)">
                            <input type="hidden" name="audio_data" id="audioData">

                            <!-- Attachment Button -->
                            <button type="button" class="btn-action" title="Attach File/Image" onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-paperclip"></i>
                            </button>

                            <!-- Text Input -->
                            <div class="chat-input-wrapper">
                                <input type="text" name="message_body" id="messageInput" class="chat-input" placeholder="Type a message..." autocomplete="off">

                                <!-- Audio Record Button -->
                                <button type="button" id="recordBtn" class="btn-action" title="Record Voice Note">
                                    <i class="bi bi-mic-fill"></i>
                                </button>
                            </div>

                            <button type="submit" name="send_message" id="sendBtn" class="btn-send"><i class="bi bi-send-fill"></i></button>
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

    <!-- Edit Message Modal -->
    <div class="modal fade" id="editMessageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="messages.php" method="POST">
                        <input type="hidden" name="message_id" id="edit_message_id">
                        <div class="mb-3">
                            <textarea class="form-control bg-light border-0" name="edit_message_body" id="edit_message_body" rows="4" required></textarea>
                            <small class="text-muted mt-2 d-block"><i class="bi bi-info-circle text-warning"></i> Messages can only be edited within 10 minutes of being sent.</small>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="edit_message" class="btn btn-primary rounded-pill py-2 fw-bold">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal (Custom Popup) -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <p>Are you sure you want to delete this message?</p>
                    <p class="text-muted small mb-0">Once deleted, it will be hidden from both you and your doctor. You cannot undo this action.</p>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-3">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <form action="messages.php" method="POST" id="deleteMessageForm">
                        <input type="hidden" name="message_id" id="delete_message_id">
                        <button type="submit" name="soft_delete_message" class="btn btn-danger rounded-pill px-4 fw-bold">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        // Auto-scroll chat to bottom
        const chatBox = document.getElementById('chatBox');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

        // Open Edit Modal Data Injection
        function openEditModal(btn) {
            const id = btn.getAttribute('data-msg-id');
            const body = btn.getAttribute('data-msg-body');
            document.getElementById('edit_message_id').value = id;
            document.getElementById('edit_message_body').value = body;
            new bootstrap.Modal(document.getElementById('editMessageModal')).show();
        }

        // Delete Confirmation: populate hidden field and show modal
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const msgId = button.getAttribute('data-msg-id');
                const deleteMessageId = document.getElementById('delete_message_id');
                if (deleteMessageId) deleteMessageId.value = msgId;
            });
        }

        // --- WhatsApp-Style Media & Voice Note Logic ---
        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;
        let recordingInterval;
        let recordingSeconds = 0;

        const recordBtn = document.getElementById('recordBtn');
        const messageInput = document.getElementById('messageInput');
        const previewArea = document.getElementById('previewArea');
        const previewContent = document.getElementById('previewContent');
        const audioDataInput = document.getElementById('audioData');
        const fileInput = document.getElementById('fileInput');
        const chatForm = document.getElementById('chatForm');

        // Prevent submitting completely empty messages
        chatForm.addEventListener('submit', (e) => {
            if (!messageInput.value.trim() && !fileInput.value && !audioDataInput.value) {
                e.preventDefault();
            }
        });

        // 1. Handle Voice Recording
        recordBtn.addEventListener('click', async () => {
            if (!isRecording) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        audio: true
                    });
                    mediaRecorder = new MediaRecorder(stream);

                    mediaRecorder.ondataavailable = e => {
                        audioChunks.push(e.data);
                    };

                    mediaRecorder.onstop = () => {
                        const audioBlob = new Blob(audioChunks, {
                            type: 'audio/webm'
                        });
                        audioChunks = [];

                        // Convert to Base64 for easy PHP processing
                        const reader = new FileReader();
                        reader.readAsDataURL(audioBlob);
                        reader.onloadend = () => {
                            audioDataInput.value = reader.result;
                            showAudioPreview(reader.result);
                        };

                        // Release microphone
                        stream.getTracks().forEach(track => track.stop());
                    };

                    mediaRecorder.start();
                    isRecording = true;

                    // Update UI to Recording state
                    recordBtn.classList.add('btn-recording');
                    recordBtn.innerHTML = '<i class="bi bi-stop-fill"></i>';
                    messageInput.disabled = true;

                    recordingSeconds = 0;
                    messageInput.placeholder = "Recording: 00:00 (Click stop to attach)";

                    recordingInterval = setInterval(() => {
                        recordingSeconds++;
                        const m = Math.floor(recordingSeconds / 60).toString().padStart(2, '0');
                        const s = (recordingSeconds % 60).toString().padStart(2, '0');
                        messageInput.placeholder = `Recording: ${m}:${s} (Click stop to attach)`;
                    }, 1000);

                } catch (err) {
                    alert("Microphone access denied. Please allow microphone permissions to record voice notes.");
                }
            } else {
                // Stop Recording
                mediaRecorder.stop();
                isRecording = false;
                clearInterval(recordingInterval);

                // Reset UI
                recordBtn.classList.remove('btn-recording');
                recordBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
                messageInput.disabled = false;
                messageInput.placeholder = "Add a caption to your voice note...";
            }
        });

        // 2. Handle File/Image Attachments
        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                // Clear any existing audio if a file is selected
                audioDataInput.value = '';

                let iconHtml = '<i class="bi bi-file-earmark-fill text-primary fs-3 me-2"></i>';

                // If it's an image, create a temporary preview thumbnail
                if (file.type.startsWith('image/')) {
                    const url = URL.createObjectURL(file);
                    iconHtml = `<img src="${url}" style="height: 40px; width: 40px; object-fit: cover; border-radius: 6px;" class="me-3">`;
                }

                previewContent.innerHTML = `${iconHtml} <span>${file.name}</span>`;
                previewArea.style.display = 'block';
                messageInput.placeholder = "Add a caption...";
                messageInput.focus();
            }
        }

        // 3. Render Audio Preview
        function showAudioPreview(src) {
            // Clear any existing file if audio is recorded
            fileInput.value = '';

            previewContent.innerHTML = `<audio controls src="${src}" class="w-100" style="height: 40px; max-width: 300px;"></audio>`;
            previewArea.style.display = 'block';
            messageInput.focus();
        }

        // 4. Clear Preview/Attachments
        function clearPreview() {
            previewArea.style.display = 'none';
            previewContent.innerHTML = '';
            fileInput.value = '';
            audioDataInput.value = '';
            messageInput.placeholder = "Type a message...";
        }
    </script>
</body>

</html>