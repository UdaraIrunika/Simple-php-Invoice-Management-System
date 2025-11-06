<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$invoice_id = $_GET['id'] ?? 0;
$invoice = $pdo->prepare("
    SELECT i.*, b.from_date, b.to_date 
    FROM invoice i 
    LEFT JOIN booking b ON i.booking_id = b.booking_id 
    WHERE i.invoice_id = ?
");
$invoice->execute([$invoice_id]);
$invoice = $invoice->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

// Handle print/export
$is_print = isset($_GET['print']);
$is_pdf = isset($_GET['pdf']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $invoice['invoice_number']; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
            }
        }
        .invoice-header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .invoice-title {
            color: #0d6efd;
            font-weight: bold;
            font-size: 2.5rem;
        }
        .total-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 80px;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <!-- Action Buttons -->
        <div class="d-flex justify-content-between mb-4 no-print">
            <a href="invoices.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Invoices
            </a>
            <div>
                <button onclick="window.print()" class="btn btn-outline-primary me-2">
                    <i class="fas fa-print me-1"></i> Print
                </button>
                <a href="generate_pdf.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-success" target="_blank">
                    <i class="fas fa-file-pdf me-1"></i> Export PDF
                </a>
            </div>
        </div>

        <!-- Invoice Content -->
        <div class="card">
            <div class="card-body">
                <!-- Header -->
                <div class="row invoice-header">
                    <div class="col-md-6">
                        <h1 class="brand-logo"><?php echo $settings['company_name'] ?? 'Royal Travel and Tours'; ?></h1>
                        <p class="text-muted mb-0"><?php echo $settings['company_address'] ?? '123 Travel Street, Tourism City'; ?></p>
                        <p class="text-muted mb-0">Phone: <?php echo $settings['company_phone'] ?? '+1 (555) 123-4567'; ?></p>
                        <p class="text-muted">Email: <?php echo $settings['company_email'] ?? 'info@royaltravel.com'; ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h2 class="invoice-title">INVOICE</h2>
                        <p class="mb-1"><strong>Invoice No:</strong> <?php echo $invoice['invoice_number']; ?></p>
                        <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                        <p class="mb-0">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $invoice['payment_status'] == 'paid' ? 'success' : 
                                    ($invoice['payment_status'] == 'overdue' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($invoice['payment_status']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Bill To -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Bill To:</h5>
                        <p class="mb-1"><strong><?php echo $invoice['customer_name']; ?></strong></p>
                        <p class="text-muted mb-0"><?php echo $invoice['customer_email']; ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <?php if ($invoice['from_date']): ?>
                        <h5>Travel Dates:</h5>
                        <p class="mb-0">
                            <?php echo date('M j, Y', strtotime($invoice['from_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($invoice['to_date'])); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Package Details -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Package</th>
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong><?php echo $invoice['package_name']; ?></strong>
                                    <br><small class="text-muted">Travel package booking</small>
                                </td>
                                <td class="text-end">$<?php echo number_format($invoice['package_price'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="row justify-content-end">
                    <div class="col-md-6">
                        <div class="total-section">
                            <div class="row mb-2">
                                <div class="col-6">Subtotal:</div>
                                <div class="col-6 text-end">$<?php echo number_format($invoice['package_price'], 2); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">Tax (<?php echo $invoice['tax']; ?>%):</div>
                                <div class="col-6 text-end">$<?php echo number_format(($invoice['package_price'] * $invoice['tax']) / 100, 2); ?></div>
                            </div>
                            <?php if ($invoice['discount'] > 0): ?>
                            <div class="row mb-2">
                                <div class="col-6">Discount:</div>
                                <div class="col-6 text-end">-$<?php echo number_format($invoice['discount'], 2); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="row mb-0" style="font-size: 1.2rem; font-weight: bold;">
                                <div class="col-6">Total Amount:</div>
                                <div class="col-6 text-end">$<?php echo number_format($invoice['total_amount'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="row mt-5">
                    <div class="col-md-6">
                        <div class="signature-line" style="width: 200px;">
                            <p class="text-center mb-0">Authorized Signature</p>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="text-muted">
                            Thank you for choosing <?php echo $settings['company_name'] ?? 'Royal Travel and Tours'; ?>!<br>
                            For questions, contact us at <?php echo $settings['company_email'] ?? 'info@royaltravel.com'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($is_print): ?>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
    <?php endif; ?>
</body>
</html>