<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get dashboard statistics
$total_invoices = $pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn();
$paid_invoices = $pdo->query("SELECT COUNT(*) FROM invoice WHERE payment_status = 'paid'")->fetchColumn();
$pending_invoices = $pdo->query("SELECT COUNT(*) FROM invoice WHERE payment_status = 'pending'")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoice WHERE payment_status = 'paid'")->fetchColumn();

// Get recent invoices
$recent_invoices = $pdo->query("
    SELECT i.*, b.from_date, b.to_date 
    FROM invoice i 
    LEFT JOIN booking b ON i.booking_id = b.booking_id 
    ORDER BY i.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get monthly revenue data for chart
$monthly_revenue = $pdo->query("
    SELECT 
        DATE_FORMAT(invoice_date, '%Y-%m') as month,
        SUM(total_amount) as revenue
    FROM invoice 
    WHERE payment_status = 'paid'
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

// Get today's stats
$today = date('Y-m-d');
$today_invoices = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE DATE(created_at) = ?");
$today_invoices->execute([$today]);
$today_invoices = $today_invoices->fetchColumn();

$today_revenue = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoice WHERE DATE(created_at) = ? AND payment_status = 'paid'");
$today_revenue->execute([$today]);
$today_revenue = $today_revenue->fetchColumn();

// Get top packages
$top_packages = $pdo->query("
    SELECT package_name, COUNT(*) as booking_count, SUM(total_amount) as revenue
    FROM invoice 
    WHERE payment_status = 'paid'
    GROUP BY package_name 
    ORDER BY revenue DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== DASHBOARD ENHANCED STYLES ===== */
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
        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow);
            padding: 1rem 2rem;
            margin: -1rem -1rem 2rem -1rem;
            position: relative;
            z-index: 100;
        }

        .dashboard-header h2 {
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

        /* ===== CHART CONTAINERS ===== */
        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .chart-container:hover {
            box-shadow: var(--shadow-lg);
        }

        .chart-container .card-header {
            background: transparent;
            border: none;
            padding: 0 0 1rem 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .chart-container .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        /* ===== RECENT INVOICES SECTION ===== */
        .recent-invoices {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            height: 100%;
        }

        .recent-invoices .card-header {
            background: transparent;
            border: none;
            padding: 0 0 1rem 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .recent-invoices .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .invoice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: rgba(248, 249, 250, 0.5);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .invoice-item:hover {
            background: rgba(13, 110, 253, 0.05);
            transform: translateX(4px);
        }

        .invoice-item:last-child {
            margin-bottom: 0;
        }

        .invoice-info h6 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .invoice-info small {
            color: #6c757d;
        }

        .invoice-amount {
            font-weight: 700;
            color: #2c3e50;
        }

        /* ===== STATUS BADGES ===== */
        .status-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-paid { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .badge-pending { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
        .badge-overdue { background: linear-gradient(135deg, #dc3545, #e83e8c); color: white; }

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
            
            .dashboard-header {
                padding: 1rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .chart-container {
                margin-bottom: 1rem;
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

        /* ===== QUICK ACTIONS ===== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            background: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            color: inherit;
            text-decoration: none;
        }

        .action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .action-btn h6 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .action-btn p {
            margin: 0;
            color: #6c757d;
            font-size: 0.875rem;
        }

        /* ===== TOP PACKAGES STYLES ===== */
        .package-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: rgba(248, 249, 250, 0.5);
            border-radius: 8px;
            border-left: 4px solid #4facfe;
            transition: all 0.3s ease;
        }

        .package-item:hover {
            background: rgba(79, 172, 254, 0.05);
            transform: translateX(4px);
        }

        .package-info h6 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .package-info small {
            color: #6c757d;
        }

        .package-revenue {
            font-weight: 700;
            color: #2c3e50;
        }

        /* ===== TODAY'S STATS ===== */
        .today-stats {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .today-stats .card-header {
            background: transparent;
            border: none;
            padding: 0 0 1rem 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .today-stats .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .today-stat-item {
            text-align: center;
            padding: 1rem;
        }

        .today-stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .today-stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
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
                            <a href="dashboard.php" class="nav-link active">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="bookings.php" class="nav-link">
                                <i class="fas fa-calendar-check me-2"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="invoices.php" class="nav-link">
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
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="fade-in-up">Dashboard Overview</h2>
                        <div class="user-welcome slide-in-right">
                            <i class="fas fa-user-circle me-2"></i>Welcome, <?php echo $_SESSION['username']; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions fade-in-up">
                    <a href="invoices.php?action=create" class="action-btn">
                        <i class="fas fa-file-invoice"></i>
                        <h6>Create Invoice</h6>
                        <p>Generate new invoice</p>
                    </a>
                    <a href="bookings.php?action=create" class="action-btn">
                        <i class="fas fa-calendar-plus"></i>
                        <h6>New Booking</h6>
                        <p>Create travel booking</p>
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="fas fa-chart-pie"></i>
                        <h6>View Reports</h6>
                        <p>Analytics & insights</p>
                    </a>
                    <a href="invoices.php" class="action-btn">
                        <i class="fas fa-list"></i>
                        <h6>All Invoices</h6>
                        <p>Manage invoices</p>
                    </a>
                </div>

                <!-- Today's Stats -->
                <div class="today-stats fade-in-up">
                    <div class="card-header">
                        <h5 class="card-title"><i class="fas fa-calendar-day me-2"></i>Today's Activity</h5>
                    </div>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="today-stat-item">
                                <div class="today-stat-number"><?php echo $today_invoices; ?></div>
                                <div class="today-stat-label">New Invoices</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="today-stat-item">
                                <div class="today-stat-number">$<?php echo number_format($today_revenue, 2); ?></div>
                                <div class="today-stat-label">Today's Revenue</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="today-stat-item">
                                <div class="today-stat-number"><?php echo count($recent_invoices); ?></div>
                                <div class="today-stat-label">Recent Activities</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4 fade-in-up">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <h4><?php echo $total_invoices; ?></h4>
                                <p>Total Invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h4><?php echo $paid_invoices; ?></h4>
                                <p>Paid Invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h4><?php echo $pending_invoices; ?></h4>
                                <p>Pending Invoices</p>
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

                <div class="row">
                    <!-- Revenue Chart -->
                    <div class="col-md-8">
                        <div class="chart-container">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Monthly Revenue</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Invoices -->
                    <div class="col-md-4">
                        <div class="recent-invoices">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-history me-2"></i>Recent Invoices</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_invoices as $invoice): ?>
                                    <div class="invoice-item">
                                        <div class="invoice-info">
                                            <h6><?php echo $invoice['invoice_number']; ?></h6>
                                            <small><?php echo $invoice['customer_name']; ?></small>
                                        </div>
                                        <div class="invoice-amount">
                                            $<?php echo number_format($invoice['total_amount'], 2); ?>
                                            <br>
                                            <span class="status-badge badge-<?php echo $invoice['payment_status']; ?>">
                                                <?php echo ucfirst($invoice['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Packages & Additional Stats -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-star me-2"></i>Top Performing Packages</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($top_packages as $package): ?>
                                <div class="package-item">
                                    <div class="package-info">
                                        <h6><?php echo $package['package_name']; ?></h6>
                                        <small><?php echo $package['booking_count']; ?> bookings</small>
                                    </div>
                                    <div class="package-revenue">
                                        $<?php echo number_format($package['revenue'], 2); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-pie me-2"></i>Invoice Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        document.addEventListener('DOMContentLoaded', function() {
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M Y', strtotime($item['month'])) . "'"; }, array_reverse($monthly_revenue))); ?>],
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: [<?php echo implode(',', array_map(function($item) { return $item['revenue']; }, array_reverse($monthly_revenue))); ?>],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 },
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });

            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending', 'Overdue'],
                    datasets: [{
                        data: [
                            <?php echo $paid_invoices; ?>,
                            <?php echo $pending_invoices; ?>,
                            <?php echo $total_invoices - $paid_invoices - $pending_invoices; ?>
                        ],
                        backgroundColor: [
                            '#28a745',
                            '#ffc107',
                            '#dc3545'
                        ],
                        borderColor: '#fff',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    cutout: '60%'
                }
            });

            // Add animation delays to elements
            const animatedElements = document.querySelectorAll('.fade-in-up, .slide-in-right');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>