<?php
// cashier/history.php - Detailed Transaction Audit Logs
require_once '../config/config.php';

if (!isset($_SESSION['cashier_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: login.php");
    exit();
}

$error_msg = '';

try {
    // Fetch detailed payment history with cashier and patient info
    $stmt = $pdo->query("
        SELECT 
            p.*, 
            pat.first_name as pat_fname, pat.last_name as pat_lname,
            cas.last_name as cashier_name,
            b.description as invoice_desc
        FROM payments p
        JOIN billing b ON p.invoice_id = b.invoice_id
        JOIN patients pat ON b.patient_id = pat.patient_id
        JOIN cashiers cas ON p.cashier_id = cas.cashier_id
        ORDER BY p.created_at DESC
    ");
    $transactions = $stmt->fetchAll();

    // Fetch expense history
    $stmt2 = $pdo->query("
        SELECT e.*, cas.last_name as cashier_name
        FROM expenses e
        JOIN cashiers cas ON e.cashier_id = cas.cashier_id
        ORDER BY e.created_at DESC
    ");
    $expenses = $stmt2->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading transaction history.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - LuminaCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- PDF Generation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #818cf8;
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

        .nav-link {
            color: #17252a;
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
            background-color: rgba(79, 70, 229, 0.05);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 600;
            border: none;
            border-bottom: 3px solid transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background: transparent;
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
        }
    </style>
</head>

<body>
    <nav class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center pe-3">
            <a href="#" class="sidebar-brand"><i class="bi bi-cash-stack me-2"></i>LuminaPay</a>
            <button class="btn-close d-lg-none" id="closeSidebar"></button>
        </div>
        <div class="nav-menu mt-3">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Billing Desk</a>
            <a href="history.php" class="nav-link active"><i class="bi bi-clock-history"></i> Transaction Logs</a>
            <a href="profile.php" class="nav-link"><i class="bi bi-person-gear"></i> Settings</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="d-flex align-items-center gap-3 mb-4">
            <button class="btn btn-light d-lg-none" id="openSidebar"><i class="bi bi-list"></i></button>
            <h3 class="fw-bold mb-0">Financial Audit Logs</h3>
        </div>

        <ul class="nav nav-tabs mb-4" id="historyTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#inflow">Revenue (Inflow)</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#outflow">Expenses (Outflow)</button></li>
        </ul>

        <div class="tab-content">
            <!-- Revenue History -->
            <div class="tab-pane fade show active" id="inflow">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Timestamp</th>
                                    <th>Patient</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Cashier</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($t['pat_fname'] . ' ' . $t['pat_lname']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['invoice_desc']); ?></small>
                                        </td>
                                        <td class="fw-bold text-dark">$<?php echo number_format($t['amount'], 2); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo $t['payment_method']; ?></span></td>
                                        <td><small class="text-muted">Staff: <?php echo htmlspecialchars($t['cashier_name']); ?></small></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($t['status'] == 'Success') ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo ($t['status'] == 'Success') ? 'success' : 'danger'; ?> px-2 py-1 rounded-pill">
                                                <?php echo $t['status']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($t['status'] == 'Success'): ?>
                                                <button class="btn btn-sm btn-outline-primary rounded-pill fw-bold" onclick="generateReceipt({
                                                id: '<?php echo $t['payment_id']; ?>',
                                                patient: '<?php echo addslashes($t['pat_fname'] . " " . $t['pat_lname']); ?>',
                                                description: '<?php echo addslashes($t['invoice_desc']); ?>',
                                                amount: '<?php echo number_format($t['amount'], 2); ?>',
                                                method: '<?php echo $t['payment_method']; ?>',
                                                cashier: '<?php echo addslashes($t['cashier_name']); ?>',
                                                date: '<?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>'
                                            })">
                                                    <i class="bi bi-printer me-1"></i> Print
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Expense History -->
            <div class="tab-pane fade" id="outflow">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Timestamp</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Cashier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $e): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?php echo date('M d, Y h:i A', strtotime($e['created_at'])); ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($e['description']); ?></td>
                                        <td><span class="badge bg-info bg-opacity-10 text-info"><?php echo $e['category']; ?></span></td>
                                        <td class="text-danger fw-bold">-$<?php echo number_format($e['amount'], 2); ?></td>
                                        <td><small><?php echo htmlspecialchars($e['cashier_name']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('openSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('closeSidebar')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        /**
         * Generates a professional receipt as a PDF using html2pdf.js
         */
        function generateReceipt(data) {
            const element = document.createElement('div');
            element.innerHTML = `
                <div style="padding: 50px; font-family: 'Helvetica', 'Arial', sans-serif; color: #374151;">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h1 style="color: #4f46e5; margin-bottom: 5px; font-weight: 800;">LuminaCare</h1>
                        <p style="font-size: 1.1rem; color: #6b7280; margin-top: 0;">Maternal Health & Clinical Services</p>
                        <div style="background: #4f46e5; color: white; display: inline-block; padding: 5px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; margin-top: 10px; text-transform: uppercase;">Official Receipt</div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px;">
                        <div>
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Billed To</p>
                            <p style="font-size: 1.2rem; font-weight: 700; margin-top: 0;">${data.patient}</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Receipt Details</p>
                            <p style="margin: 0;"><strong>ID:</strong> PAY-${data.id.padStart(5, '0')}</p>
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

                    <div style="background: #f9fafb; padding: 25px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Payment Method</p>
                            <p style="font-weight: 600; margin: 0; font-size: 1.1rem;">${data.method}</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 0.85rem; color: #4f46e5; margin-bottom: 4px; font-weight: 700;">TOTAL PAID</p>
                            <p style="font-size: 2rem; font-weight: 800; color: #111827; margin: 0;">$${data.amount}</p>
                        </div>
                    </div>

                    <div style="margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end;">
                        <div style="font-size: 0.85rem; color: #6b7280;">
                            <p style="margin-bottom: 2px;"><strong>Cashier:</strong> ${data.cashier}</p>
                            <p style="margin: 0;">LuminaCare Secure Billing Terminal</p>
                        </div>
                        <div style="text-align: center; border-top: 1px solid #d1d5db; width: 200px; padding-top: 10px;">
                            <p style="font-size: 0.75rem; color: #9ca3af; margin: 0; text-transform: uppercase;">Authorized Signature</p>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 60px; font-size: 0.8rem; color: #9ca3af; border-top: 1px dashed #e5e7eb; pt: 20px;">
                        <p>Thank you for trusting LuminaCare with your health journey.</p>
                        <p style="margin-top: 5px;">This is a computer-generated receipt and does not require a physical stamp.</p>
                    </div>
                </div>
            `;

            const opt = {
                margin: 0,
                filename: `LuminaCare_Receipt_${data.id}.pdf`,
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

            // Start generation
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>

</html>