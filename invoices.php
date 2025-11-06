<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle invoice actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        // Handle create/update invoice
        $invoice_data = [
            'booking_id' => $_POST['booking_id'] ?: null,
            'invoice_date' => $_POST['invoice_date'],
            'customer_name' => $_POST['customer_name'],
            'customer_email' => $_POST['customer_email'],
            'package_name' => $_POST['package_name'],
            'package_price' => $_POST['package_price'],
            'tax' => $_POST['tax'],
            'discount' => $_POST['discount'],
            'total_amount' => $_POST['total_amount'],
            'payment_status' => $_POST['payment_status']
        ];
        
        if ($action === 'create') {
            // Generate invoice number
            $prefix = $settings['invoice_prefix'] ?? 'RTT-INV-';
            $last_id = $pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn();
            $invoice_number = $prefix . str_pad($last_id + 1, 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO invoice (invoice_number, booking_id, invoice_date, customer_name, customer_email, package_name, package_price, tax, discount, total_amount, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoice_number,
                $invoice_data['booking_id'],
                $invoice_data['invoice_date'],
                $invoice_data['customer_name'],
                $invoice_data['customer_email'],
                $invoice_data['package_name'],
                $invoice_data['package_price'],
                $invoice_data['tax'],
                $invoice_data['discount'],
                $invoice_data['total_amount'],
                $invoice_data['payment_status']
            ]);
            
            $invoice_id = $pdo->lastInsertId();
            
            // Log the action (FIXED: uses system_log)
            logActivity($pdo, 'invoice_create', "Invoice {$invoice_number} created");
            
            $_SESSION['success'] = "Invoice created successfully!";
        } else {
            $invoice_id = $_POST['invoice_id'];
            $stmt = $pdo->prepare("
                UPDATE invoice SET 
                booking_id = ?, invoice_date = ?, customer_name = ?, customer_email = ?, package_name = ?, package_price = ?, tax = ?, discount = ?, total_amount = ?, payment_status = ?
                WHERE invoice_id = ?
            ");
            $stmt->execute([
                $invoice_data['booking_id'],
                $invoice_data['invoice_date'],
                $invoice_data['customer_name'],
                $invoice_data['customer_email'],
                $invoice_data['package_name'],
                $invoice_data['package_price'],
                $invoice_data['tax'],
                $invoice_data['discount'],
                $invoice_data['total_amount'],
                $invoice_data['payment_status'],
                $invoice_id
            ]);
            
            // Log the action (FIXED: uses system_log)
            logActivity($pdo, 'invoice_update', "Invoice ID {$invoice_id} updated");
            
            $_SESSION['success'] = "Invoice updated successfully!";
        }
        
        header('Location: invoices.php');
        exit;
    } elseif ($action === 'delete') {
        $invoice_id = $_POST['invoice_id'];
        
        // Get invoice number before deletion for logging
        $invoice_stmt = $pdo->prepare("SELECT invoice_number FROM invoice WHERE invoice_id = ?");
        $invoice_stmt->execute([$invoice_id]);
        $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice) {
            // Log before deletion (FIXED: uses system_log)
            logActivity($pdo, 'invoice_delete', "Invoice {$invoice['invoice_number']} deleted");
            
            $stmt = $pdo->prepare("DELETE FROM invoice WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            
            $_SESSION['success'] = "Invoice deleted successfully!";
        }
        
        header('Location: invoices.php');
        exit;
    }
}

// Get all invoices with optional filters
$where_conditions = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(customer_name LIKE ? OR customer_email LIKE ? OR package_name LIKE ?)";
    $search_term = "%{$_GET['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "payment_status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['from_date']) && !empty($_GET['from_date'])) {
    $where_conditions[] = "invoice_date >= ?";
    $params[] = $_GET['from_date'];
}

if (isset($_GET['to_date']) && !empty($_GET['to_date'])) {
    $where_conditions[] = "invoice_date <= ?";
    $params[] = $_GET['to_date'];
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$invoices = $pdo->prepare("
    SELECT i.*, b.from_date, b.to_date 
    FROM invoice i 
    LEFT JOIN booking b ON i.booking_id = b.booking_id 
    $where_sql 
    ORDER BY i.created_at DESC
");
$invoices->execute($params);
$invoices = $invoices->fetchAll(PDO::FETCH_ASSOC);

// Get bookings for dropdown
$bookings = $pdo->query("SELECT * FROM booking WHERE status = 'confirmed'")->fetchAll(PDO::FETCH_ASSOC);

// Calculate additional stats for the dashboard
$total_revenue = array_sum(array_column($invoices, 'total_amount'));
$paid_invoices_count = count(array_filter($invoices, function($inv) { return $inv['payment_status'] == 'paid'; }));
$pending_invoices_count = count(array_filter($invoices, function($inv) { return $inv['payment_status'] == 'pending'; }));
$overdue_invoices_count = count(array_filter($invoices, function($inv) { return $inv['payment_status'] == 'overdue'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ===== INVOICES ENHANCED STYLES ===== */
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --primary-light: #3d8bfd;
            --success: #198754;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 280px;
            --header-height: 80px;
            --border-radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        /* ===== SIDEBAR ENHANCEMENTS ===== */
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            box-shadow: var(--shadow-lg);
            border: none;
            position: fixed;
            width: var(--sidebar-width);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.05"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            margin: 4px 15px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link.active {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0.1) 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border-left: 4px solid #00f2fe;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1em;
            transition: transform 0.3s ease;
        }

        .sidebar .nav-link:hover i {
            transform: scale(1.1);
        }

        .sidebar .nav-link.active i {
            color: #00f2fe;
        }

        .sidebar .nav-link.text-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .sidebar .nav-link.text-danger:hover {
            background: rgba(220, 53, 69, 0.3);
            border-color: rgba(220, 53, 69, 0.5);
        }

        /* ===== MAIN CONTENT AREA ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            position: relative;
        }

        .main-content::before {
            content: '';
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: var(--sidebar-width);
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.02"><defs><pattern id="dots" width="10" height="10" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r="1" fill="%23000"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
            pointer-events: none;
        }

        /* ===== HEADER ENHANCEMENTS ===== */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow);
            padding: 1rem 2rem;
            margin: -1rem -1rem 2rem -1rem;
            position: relative;
            z-index: 100;
        }

        .page-header h2 {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin: 0;
        }

        .user-welcome {
            color: #2c3e50;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.8);
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* ===== STATISTICS CARDS ===== */
        .stat-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            background: white;
            height: 100%;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.bg-primary { background: var(--gradient-primary) !important; }
        .stat-card.bg-success { background: var(--gradient-success) !important; }
        .stat-card.bg-warning { background: var(--gradient-warning) !important; }
        .stat-card.bg-info { background: var(--gradient-info) !important; }
        .stat-card.bg-danger { background: var(--gradient-danger) !important; }

        .stat-card .card-body {
            position: relative;
            z-index: 2;
            padding: 1.5rem;
        }

        .stat-card h4 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }

        .stat-card p {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin: 0;
        }

        .stat-card .icon-container {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .stat-card .icon-container i {
            font-size: 1.5rem;
            color: white;
        }

        /* ===== FILTER CARD ===== */
        .filter-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .filter-card:hover {
            box-shadow: var(--shadow-lg);
        }

        /* ===== TABLE ENHANCEMENTS ===== */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .table-container:hover {
            box-shadow: var(--shadow-lg);
        }

        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
            margin: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1rem;
            border-color: var(--gray-200);
            vertical-align: middle;
            transition: all 0.3s ease;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
            transform: scale(1.01);
        }

        /* ===== STATUS BADGES ===== */
        .status-badge {
            font-size: 0.7rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        .badge-paid { 
            background: linear-gradient(135deg, #28a745, #20c997) !important; 
            color: white; 
        }
        .badge-pending { 
            background: linear-gradient(135deg, #ffc107, #fd7e14) !important; 
            color: white; 
        }
        .badge-overdue { 
            background: linear-gradient(135deg, #dc3545, #e83e8c) !important; 
            color: white; 
        }

        /* ===== BUTTON ENHANCEMENTS ===== */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .btn-group .btn {
            border-radius: 0;
        }

        .btn-group .btn:first-child {
            border-top-left-radius: var(--border-radius);
            border-bottom-left-radius: var(--border-radius);
        }

        .btn-group .btn:last-child {
            border-top-right-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }

        /* ===== MODAL ENHANCEMENTS ===== */
        .modal-content {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1.5rem;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }

        /* ===== FORM ENHANCEMENTS ===== */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
            background: white;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        /* ===== ALERT ENHANCEMENTS ===== */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, white 100%);
            color: #155724;
            border-left-color: #28a745;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .slide-in-right {
            animation: slideInRight 0.6s ease-out;
        }

        /* ===== BRAND STYLING ===== */
        .brand-logo {
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn-group .btn {
                border-radius: var(--border-radius) !important;
            }
        }

        @media (max-width: 576px) {
            .stat-card h4 {
                font-size: 1.5rem;
            }
            
            .stat-card .icon-container {
                width: 50px;
                height: 50px;
                top: 1rem;
                right: 1rem;
            }
            
            .stat-card .icon-container i {
                font-size: 1.25rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h5 {
            margin-bottom: 0.5rem;
            color: #495057;
        }

        /* ===== INVOICE ITEM STYLES ===== */
        .invoice-number {
            font-weight: 700;
            color: #2c3e50;
        }

        .customer-info {
            line-height: 1.4;
        }

        .customer-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .customer-email {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .amount {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .booking-badge {
            background: rgba(13, 110, 253, 0.1);
            color: var(--primary);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="d-flex flex-column p-3">
                    <div class="brand-logo"><?php echo APP_NAME; ?></div>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="bookings.php" class="nav-link">
                                <i class="fas fa-calendar-check me-2"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="invoices.php" class="nav-link active">
                                <i class="fas fa-file-invoice me-2"></i> Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link">
                                <i class="fas fa-chart-bar me-2"></i> Reports
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a href="settings.php" class="nav-link">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="fade-in-up">Invoice Management</h2>
                        <div class="user-welcome slide-in-right">
                            <i class="fas fa-user-circle me-2"></i>Welcome, <?php echo $_SESSION['username']; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4 fade-in-up">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <h4><?php echo count($invoices); ?></h4>
                                <p>Total Invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h4><?php echo $paid_invoices_count; ?></h4>
                                <p>Paid</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h4><?php echo $pending_invoices_count; ?></h4>
                                <p>Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h4><?php echo $overdue_invoices_count; ?></h4>
                                <p>Overdue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <h4>$<?php echo number_format($total_revenue, 2); ?></h4>
                                <p>Total Revenue</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show fade-in-up" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 fade-in-up">
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                            <i class="fas fa-plus me-2"></i> Create New Invoice
                        </button>
                    </div>
                    <div class="text-muted">
                        <i class="fas fa-filter me-2"></i>Advanced Filtering
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="filter-card fade-in-up">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Customer, package, email..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo ($_GET['status'] ?? '') == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="overdue" <?php echo ($_GET['status'] ?? '') == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date" value="<?php echo $_GET['from_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date" value="<?php echo $_GET['to_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-2"></i> Filter
                            </button>
                            <a href="invoices.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Invoices Table -->
                <div class="table-container fade-in-up">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Package</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="fas fa-file-invoice"></i>
                                                <h5>No Invoices Found</h5>
                                                <p>Create your first invoice to get started</p>
                                                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                                                    <i class="fas fa-plus me-2"></i> Create Invoice
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <div class="invoice-number"><?php echo $invoice['invoice_number']; ?></div>
                                            <?php if ($invoice['booking_id']): ?>
                                                <div class="booking-badge">Booking #<?php echo $invoice['booking_id']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="customer-info">
                                                <div class="customer-name"><?php echo $invoice['customer_name']; ?></div>
                                                <div class="customer-email"><?php echo $invoice['customer_email']; ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo $invoice['package_name']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td class="text-end amount">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge badge-<?php echo $invoice['payment_status']; ?>">
                                                <?php echo ucfirst($invoice['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-outline-primary" title="View Invoice">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-outline-secondary edit-invoice" 
                                                        data-invoice='<?php echo htmlspecialchars(json_encode($invoice), ENT_QUOTES, 'UTF-8'); ?>'
                                                        title="Edit Invoice">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="invoice_id" value="<?php echo $invoice['invoice_id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete Invoice">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div class="modal fade" id="invoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="invoiceForm">
                    <input type="hidden" name="action" value="create" id="formAction">
                    <input type="hidden" name="invoice_id" id="invoiceId">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Booking</label>
                                    <select class="form-select" name="booking_id" id="bookingSelect">
                                        <option value="">Select Booking</option>
                                        <?php foreach ($bookings as $booking): ?>
                                        <option value="<?php echo $booking['booking_id']; ?>" 
                                                data-customer="<?php echo $booking['user_email']; ?>"
                                                data-package="<?php echo $booking['package_name']; ?>">
                                            <?php echo $booking['package_name'] . ' - ' . $booking['user_email']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Invoice Date</label>
                                    <input type="date" class="form-control" name="invoice_date" id="invoiceDate" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" class="form-control" name="customer_name" id="customerName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Customer Email</label>
                                    <input type="email" class="form-control" name="customer_email" id="customerEmail" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Package Name</label>
                                    <input type="text" class="form-control" name="package_name" id="packageName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Package Price ($)</label>
                                    <input type="number" class="form-control" name="package_price" id="packagePrice" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tax (%)</label>
                                    <input type="number" class="form-control" name="tax" id="tax" step="0.01" value="<?php echo $settings['tax_rate'] ?? 10; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Discount ($)</label>
                                    <input type="number" class="form-control" name="discount" id="discount" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Total Amount ($)</label>
                                    <input type="number" class="form-control" name="total_amount" id="totalAmount" step="0.01" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" name="payment_status" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-populate from booking
        document.getElementById('bookingSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('customerEmail').value = selectedOption.dataset.customer;
                document.getElementById('packageName').value = selectedOption.dataset.package;
                // Set customer name from email (you can modify this logic)
                const email = selectedOption.dataset.customer;
                const name = email.split('@')[0].replace(/[.-]/g, ' ');
                document.getElementById('customerName').value = name.charAt(0).toUpperCase() + name.slice(1);
                // Set a default package price
                document.getElementById('packagePrice').value = '1000.00';
                calculateTotal();
            }
        });

        // Calculate total amount
        function calculateTotal() {
            const packagePrice = parseFloat(document.getElementById('packagePrice').value) || 0;
            const taxRate = parseFloat(document.getElementById('tax').value) || 0;
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            
            const taxAmount = (packagePrice * taxRate) / 100;
            const total = packagePrice + taxAmount - discount;
            
            document.getElementById('totalAmount').value = total.toFixed(2);
        }

        // Attach event listeners for calculation
        ['packagePrice', 'tax', 'discount'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateTotal);
        });

        // Set today's date as default
        document.getElementById('invoiceDate').valueAsDate = new Date();

        // Edit invoice functionality
        document.querySelectorAll('.edit-invoice').forEach(button => {
            button.addEventListener('click', function() {
                const invoice = JSON.parse(this.dataset.invoice);
                
                document.getElementById('formAction').value = 'update';
                document.getElementById('invoiceId').value = invoice.invoice_id;
                document.querySelector('.modal-title').textContent = 'Edit Invoice';
                
                // Populate form fields
                document.getElementById('bookingSelect').value = invoice.booking_id || '';
                document.getElementById('invoiceDate').value = invoice.invoice_date;
                document.getElementById('customerName').value = invoice.customer_name;
                document.getElementById('customerEmail').value = invoice.customer_email;
                document.getElementById('packageName').value = invoice.package_name;
                document.getElementById('packagePrice').value = invoice.package_price;
                document.getElementById('tax').value = invoice.tax;
                document.getElementById('discount').value = invoice.discount;
                document.getElementById('totalAmount').value = invoice.total_amount;
                document.querySelector('select[name="payment_status"]').value = invoice.payment_status;
                
                // Show modal
                new bootstrap.Modal(document.getElementById('invoiceModal')).show();
            });
        });

        // Reset form when creating new invoice
        document.getElementById('invoiceModal').addEventListener('show.bs.modal', function() {
            if (document.getElementById('formAction').value === 'create') {
                document.getElementById('invoiceForm').reset();
                document.getElementById('invoiceDate').valueAsDate = new Date();
                document.getElementById('tax').value = '<?php echo $settings['tax_rate'] ?? 10; ?>';
                calculateTotal();
            }
        });

        // Initial calculation
        calculateTotal();

        // Add animation delays to elements
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.fade-in-up, .slide-in-right');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>