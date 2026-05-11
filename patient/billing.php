<?php
// patient/billing.php - Patient Billing & Invoice History
require_once '../config/config.php';

if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$error_msg = '';

try {
    // 1. Fetch all invoices for this patient
    $stmt = $pdo->prepare("
        SELECT * FROM billing 
        WHERE patient_id = ? 
        ORDER BY CASE WHEN status != 'Paid' THEN 0 ELSE 1 END, due_date ASC, created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $invoices = $stmt->fetchAll();

    // 2. Calculate Financial Summaries
    $unpaid_balance = 0;
    $total_paid = 0;

    foreach ($invoices as $inv) {
        $balance = $inv['amount'] - $inv['amount_paid'];
        if ($inv['status'] !== 'Paid') {
            $unpaid_balance += $balance;
        }
        $total_paid += $inv['amount_paid'];
    }

    // 3. Fetch Unread Messages Count (for sidebar badge)
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_role = 'patient' AND is_read = 0");
    $msgStmt->execute([$patient_id]);
    $unread_msgs = $msgStmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $error_msg = "Error loading billing records.";
    $invoices = [];
    $unpaid_balance = 0;
    $total_paid = 0;
    $unread_msgs = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Invoices - LuminaCare Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- HTML2PDF Library for Invoice Printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
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
                <a href="billing.php" class="nav-link active">
                    <i class="bi bi-receipt-cutoff"></i> Billing & Invoices
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
                <h3 class="mb-0 fw-bold">My Billing & Invoices</h3>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Financial Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="metric-card" style="<?php echo $unpaid_balance > 0 ? 'border-left: 4px solid #ef4444;' : ''; ?>">
                    <div class="metric-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><i class="bi bi-exclamation-octagon"></i></div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 small fw-bold">Outstanding Balance</h6>
                        <h3 class="mb-0 fw-bold <?php echo $unpaid_balance > 0 ? 'text-danger' : 'text-dark'; ?>">$<?php echo number_format($unpaid_balance, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(43, 122, 120, 0.1); color: var(--primary-color);"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 small fw-bold">Total Amount Paid</h6>
                        <h3 class="mb-0 fw-bold text-success">$<?php echo number_format($total_paid, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-receipt text-primary me-2"></i> Invoice History</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Invoice Details</th>
                            <th>Description</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Status & Balance</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invoices) > 0): ?>
                            <?php foreach ($invoices as $inv): ?>
                                <?php
                                $balance = $inv['amount'] - $inv['amount_paid'];

                                $statusClass = 'warning text-dark';
                                if ($inv['status'] == 'Paid') $statusClass = 'success';
                                if ($inv['status'] == 'Overdue') $statusClass = 'danger';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark">INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?></div>
                                        <small class="text-muted">Issued: <?php echo date('M d, Y', strtotime($inv['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($inv['description']); ?>">
                                            <?php echo htmlspecialchars($inv['description']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">$<?php echo number_format($inv['amount'], 2); ?></td>
                                    <td class="text-success">$<?php echo number_format($inv['amount_paid'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusClass; ?> bg-opacity-10 text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?> border-opacity-25 px-2 py-1 rounded-pill mb-1">
                                            <?php echo htmlspecialchars($inv['status']); ?>
                                        </span><br>
                                        <?php if ($balance > 0): ?>
                                            <h6 class="mb-0 fw-bold text-danger mt-1">$<?php echo number_format($balance, 2); ?> Due</h6>
                                        <?php else: ?>
                                            <small class="text-muted">Settled</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary rounded-pill fw-bold px-3" onclick="viewInvoice({
                                            id: '<?php echo $inv['invoice_id']; ?>',
                                            patient: '<?php echo addslashes($_SESSION['first_name'] . " " . $_SESSION['last_name']); ?>',
                                            description: '<?php echo addslashes($inv['description']); ?>',
                                            amount: '<?php echo number_format($inv['amount'], 2); ?>',
                                            paid: '<?php echo number_format($inv['amount_paid'], 2); ?>',
                                            balance: '<?php echo number_format($balance, 2); ?>',
                                            status: '<?php echo $inv['status']; ?>',
                                            date: '<?php echo date('M d, Y', strtotime($inv['created_at'])); ?>'
                                        })">
                                            <i class="bi bi-eye me-1"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm mb-3" style="width: 80px; height: 80px;">
                                        <i class="bi bi-receipt fs-1 text-muted opacity-50"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark">No Invoices Found</h5>
                                    <p class="text-muted">You have no billing records on file at this time.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($unpaid_balance > 0): ?>
            <div class="alert alert-info border-0 shadow-sm rounded-4 mt-4 d-flex align-items-center gap-3 p-4">
                <i class="bi bi-info-circle-fill fs-2 text-primary"></i>
                <div>
                    <h6 class="fw-bold mb-1">Payment Instructions</h6>
                    <p class="mb-0 small">To settle an outstanding balance, please present your Invoice ID (e.g., INV-00001) to the clinic's Cashier Desk. They accept Cash, Point-of-Sale (POS) Card transfers, and approved Insurance policies.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- View Invoice Modal -->
    <div class="modal fade" id="viewInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i> Invoice Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-white">
                    <div id="invoicePreviewContent">
                        <!-- Invoice HTML injected here -->
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Close Preview</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="downloadInvoicePDF()"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Vitals Modal (Submits back to dashboard for central processing) -->
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
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        let currentInvoiceData = null; // Store data for PDF generation

        /**
         * Previews the invoice inside the modal
         */
        function viewInvoice(data) {
            currentInvoiceData = data;
            const statusColor = data.status === 'Paid' ? '#10b981' : (data.status === 'Overdue' ? '#ef4444' : '#f59e0b');

            const htmlContent = `
                <div id="pdfPrintArea" style="padding: 40px; font-family: 'Helvetica', 'Arial', sans-serif; color: #374151; background: white;">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h1 style="color: #2b7a78; margin-bottom: 5px; font-weight: 800;">LuminaCare</h1>
                        <p style="font-size: 1.1rem; color: #6b7280; margin-top: 0;">Maternal Health & Clinical Services</p>
                        <div style="background: ${statusColor}; color: white; display: inline-block; padding: 5px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; margin-top: 10px; text-transform: uppercase;">INVOICE - ${data.status}</div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px;">
                        <div>
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Billed To</p>
                            <p style="font-size: 1.2rem; font-weight: 700; margin-top: 0;">${data.patient}</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Invoice Details</p>
                            <p style="margin: 0;"><strong>INV:</strong> #${data.id.padStart(5, '0')}</p>
                            <p style="margin: 0;"><strong>Date:</strong> ${data.date}</p>
                        </div>
                    </div>

                    <div style="margin-bottom: 40px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid #f3f4f6;">
                                    <th style="text-align: left; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">DESCRIPTION OF SERVICES</th>
                                    <th style="text-align: right; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 20px 0; font-weight: 500;">${data.description}</td>
                                    <td style="padding: 20px 0; text-align: right; font-weight: 700; font-size: 1.1rem;">$${data.amount}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="background: #f9fafb; padding: 25px; border-radius: 12px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #6b7280; font-weight: 600;">Total Amount:</span>
                            <span style="font-weight: 700;">$${data.amount}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #10b981; font-weight: 600;">Amount Paid:</span>
                            <span style="font-weight: 700; color: #10b981;">-$${data.paid}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
                            <span style="color: ${statusColor}; font-weight: 700; font-size: 1.1rem;">Balance Due:</span>
                            <span style="font-weight: 800; color: ${statusColor}; font-size: 1.1rem;">$${data.balance}</span>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 60px; font-size: 0.8rem; color: #9ca3af; border-top: 1px dashed #e5e7eb; padding-top: 20px;">
                        <p>Thank you for trusting LuminaCare with your health journey.</p>
                        <p style="margin-top: 5px;">Present this invoice ID at the Cashier Desk to process payments.</p>
                    </div>
                </div>
            `;

            document.getElementById('invoicePreviewContent').innerHTML = htmlContent;

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('viewInvoiceModal'));
            modal.show();
        }

        /**
         * Generates the PDF from the active preview modal
         */
        function downloadInvoicePDF() {
            if (!currentInvoiceData) return;

            const element = document.getElementById('pdfPrintArea');
            const opt = {
                margin: 0,
                filename: `LuminaCare_Invoice_${currentInvoiceData.id.padStart(5, '0')}.pdf`,
                image: {
                    type: 'jpeg',
                    quality: 1
                },
                html2canvas: {
                    scale: 3,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'in',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>

</html>