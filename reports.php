<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Date range defaults (last 30 days)
$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');

// Get filter parameters
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;
$report_type = $_GET['report_type'] ?? 'financial_summary';

// Validate dates
if ($start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Generate reports based on type
$reports = [];
$chart_data = [];

switch ($report_type) {
    case 'financial_summary':
        $reports = generateFinancialSummary($pdo, $start_date, $end_date);
        $chart_data = generateFinancialChartData($pdo, $start_date, $end_date);
        break;
    case 'invoice_analysis':
        $reports = generateInvoiceAnalysis($pdo, $start_date, $end_date);
        $chart_data = generateInvoiceChartData($pdo, $start_date, $end_date);
        break;
    case 'customer_reports':
        $reports = generateCustomerReports($pdo, $start_date, $end_date);
        $chart_data = generateCustomerChartData($pdo, $start_date, $end_date);
        break;
    case 'package_performance':
        $reports = generatePackagePerformance($pdo, $start_date, $end_date);
        $chart_data = generatePackageChartData($pdo, $start_date, $end_date);
        break;
}

// Report generation functions
function generateFinancialSummary($pdo, $start_date, $end_date) {
    $reports = [];
    
    // Total Revenue
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ? AND payment_status = 'paid'
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];
    
    // Total Invoices
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_invoices 
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['total_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_invoices'];
    
    // Paid Invoices
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as paid_invoices 
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ? AND payment_status = 'paid'
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['paid_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC)['paid_invoices'];
    
    // Pending Invoices
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_invoices 
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ? AND payment_status = 'pending'
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['pending_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_invoices'];
    
    // Average Invoice Value
    $reports['avg_invoice_value'] = $reports['total_invoices'] > 0 ? 
        $reports['total_revenue'] / $reports['total_invoices'] : 0;
    
    // Revenue by Payment Status
    $stmt = $pdo->prepare("
        SELECT 
            payment_status,
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as amount
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY payment_status
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['revenue_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Revenue Trend
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            COALESCE(SUM(total_amount), 0) as revenue,
            COUNT(*) as invoice_count
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ? AND payment_status = 'paid'
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['monthly_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $reports;
}

function generateFinancialChartData($pdo, $start_date, $end_date) {
    $chart_data = [];
    
    // Revenue by status for pie chart
    $stmt = $pdo->prepare("
        SELECT 
            payment_status,
            COALESCE(SUM(total_amount), 0) as amount
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY payment_status
    ");
    $stmt->execute([$start_date, $end_date]);
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data['status_labels'] = [];
    $chart_data['status_data'] = [];
    $chart_data['status_colors'] = ['#28a745', '#ffc107', '#dc3545'];
    
    foreach ($status_data as $item) {
        $chart_data['status_labels'][] = ucfirst($item['payment_status']);
        $chart_data['status_data'][] = floatval($item['amount']);
    }
    
    // Monthly trend for line chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(invoice_date, '%b %Y') as month_name,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ? AND payment_status = 'paid'
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m'), month_name
        ORDER BY MIN(invoice_date)
    ");
    $stmt->execute([$start_date, $end_date]);
    $trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data['trend_labels'] = array_column($trend_data, 'month_name');
    $chart_data['trend_data'] = array_map('floatval', array_column($trend_data, 'revenue'));
    
    return $chart_data;
}

function generateInvoiceAnalysis($pdo, $start_date, $end_date) {
    $reports = [];
    
    // Invoice status distribution
    $stmt = $pdo->prepare("
        SELECT 
            payment_status,
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total_amount
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY payment_status
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top invoices by amount
    $stmt = $pdo->prepare("
        SELECT 
            invoice_number,
            customer_name,
            package_name,
            total_amount,
            invoice_date,
            payment_status
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        ORDER BY total_amount DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['top_invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Invoice age analysis
    $stmt = $pdo->prepare("
        SELECT 
            payment_status,
            AVG(DATEDIFF(CURDATE(), invoice_date)) as avg_age_days,
            COUNT(*) as count
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY payment_status
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['invoice_age'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $reports;
}

function generateInvoiceChartData($pdo, $start_date, $end_date) {
    $chart_data = [];
    
    // Status distribution for doughnut chart
    $stmt = $pdo->prepare("
        SELECT 
            payment_status,
            COUNT(*) as count
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY payment_status
    ");
    $stmt->execute([$start_date, $end_date]);
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data['invoice_status_labels'] = [];
    $chart_data['invoice_status_data'] = [];
    $chart_data['invoice_status_colors'] = ['#28a745', '#ffc107', '#dc3545'];
    
    foreach ($status_data as $item) {
        $chart_data['invoice_status_labels'][] = ucfirst($item['payment_status']);
        $chart_data['invoice_status_data'][] = intval($item['count']);
    }
    
    return $chart_data;
}

function generateCustomerReports($pdo, $start_date, $end_date) {
    $reports = [];
    
    // Top customers by spending
    $stmt = $pdo->prepare("
        SELECT 
            customer_name,
            customer_email,
            COUNT(*) as invoice_count,
            COALESCE(SUM(total_amount), 0) as total_spent,
            AVG(total_amount) as avg_invoice_value
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY customer_email, customer_name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // New customers per period
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(MIN(invoice_date), '%Y-%m') as first_purchase_month,
            COUNT(DISTINCT customer_email) as new_customers
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
        ORDER BY first_purchase_month
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['new_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $reports;
}

function generateCustomerChartData($pdo, $start_date, $end_date) {
    $chart_data = [];
    
    // Customer spending distribution
    $stmt = $pdo->prepare("
        SELECT 
            customer_name,
            COALESCE(SUM(total_amount), 0) as total_spent
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY customer_email, customer_name
        ORDER BY total_spent DESC
        LIMIT 8
    ");
    $stmt->execute([$start_date, $end_date]);
    $customer_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data['customer_labels'] = array_column($customer_data, 'customer_name');
    $chart_data['customer_data'] = array_map('floatval', array_column($customer_data, 'total_spent'));
    
    return $chart_data;
}

function generatePackagePerformance($pdo, $start_date, $end_date) {
    $reports = [];
    
    // Package performance
    $stmt = $pdo->prepare("
        SELECT 
            package_name,
            COUNT(*) as bookings_count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            AVG(total_amount) as avg_revenue_per_booking
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY package_name
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['package_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Package popularity over time
    $stmt = $pdo->prepare("
        SELECT 
            package_name,
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            COUNT(*) as bookings_count
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY package_name, DATE_FORMAT(invoice_date, '%Y-%m')
        ORDER BY month, bookings_count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['package_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $reports;
}

function generatePackageChartData($pdo, $start_date, $end_date) {
    $chart_data = [];
    
    // Package revenue distribution
    $stmt = $pdo->prepare("
        SELECT 
            package_name,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM invoice 
        WHERE invoice_date BETWEEN ? AND ?
        GROUP BY package_name
        ORDER BY total_revenue DESC
        LIMIT 8
    ");
    $stmt->execute([$start_date, $end_date]);
    $package_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data['package_labels'] = array_column($package_data, 'package_name');
    $chart_data['package_data'] = array_map('floatval', array_column($package_data, 'total_revenue'));
    
    return $chart_data;
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($report_type) {
        case 'financial_summary':
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Revenue', '$' . number_format($reports['total_revenue'], 2)]);
            fputcsv($output, ['Total Invoices', $reports['total_invoices']]);
            fputcsv($output, ['Paid Invoices', $reports['paid_invoices']]);
            fputcsv($output, ['Pending Invoices', $reports['pending_invoices']]);
            fputcsv($output, ['Average Invoice Value', '$' . number_format($reports['avg_invoice_value'], 2)]);
            break;
            
        case 'invoice_analysis':
            fputcsv($output, ['Status', 'Count', 'Total Amount']);
            foreach ($reports['status_distribution'] as $row) {
                fputcsv($output, [
                    ucfirst($row['payment_status']),
                    $row['count'],
                    '$' . number_format($row['total_amount'], 2)
                ]);
            }
            break;
            
        case 'customer_reports':
            fputcsv($output, ['Customer', 'Email', 'Invoices', 'Total Spent', 'Avg Invoice']);
            foreach ($reports['top_customers'] as $row) {
                fputcsv($output, [
                    $row['customer_name'],
                    $row['customer_email'],
                    $row['invoice_count'],
                    '$' . number_format($row['total_spent'], 2),
                    '$' . number_format($row['avg_invoice_value'], 2)
                ]);
            }
            break;
            
        case 'package_performance':
            fputcsv($output, ['Package', 'Bookings', 'Total Revenue', 'Avg Revenue']);
            foreach ($reports['package_performance'] as $row) {
                fputcsv($output, [
                    $row['package_name'],
                    $row['bookings_count'],
                    '$' . number_format($row['total_revenue'], 2),
                    '$' . number_format($row['avg_revenue_per_booking'], 2)
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    /* ===== REPORTS ENHANCED STYLES ===== */
    :root {
        --primary: #0d6efd;
        --primary-dark: #0b5ed7;
        --primary-light: #3d8bfd;
        --success: #198754;
        --warning: #ffc107;
        --info: #0dcaf0;
        --danger: #dc3545;
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
        --gradient-purple: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --gradient-teal: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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

    /* ===== REPORT STATS CARDS ===== */
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
    .stat-card.bg-purple { background: var(--gradient-purple) !important; }
    .stat-card.bg-teal { background: var(--gradient-teal) !important; }

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

    /* ===== REPORT SECTIONS ===== */
    .report-section {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .report-section:hover {
        box-shadow: var(--shadow-lg);
    }

    .report-section .card-header {
        background: transparent;
        border: none;
        padding: 0 0 1rem 0;
        margin-bottom: 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .report-section .card-title {
        color: #2c3e50;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
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
        position: relative;
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
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* ===== TAB NAVIGATION ===== */
    .nav-tabs {
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 1.5rem;
    }

    .nav-tabs .nav-link {
        border: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        padding: 1rem 1.5rem;
        color: #6c757d;
        font-weight: 500;
        transition: all 0.3s ease;
        background: transparent;
        margin-bottom: -2px;
    }

    .nav-tabs .nav-link:hover {
        background-color: rgba(13, 110, 253, 0.05);
        color: var(--primary);
        border: none;
    }

    .nav-tabs .nav-link.active {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        box-shadow: var(--shadow-md);
        border: none;
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

    .btn-success {
        background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
        border: none;
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

    .btn-outline-secondary {
        border: 2px solid #6c757d;
        color: #6c757d;
        background: transparent;
    }

    .btn-outline-secondary:hover {
        background: #6c757d;
        border-color: #6c757d;
        color: white;
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

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, white 100%);
        color: #721c24;
        border-left-color: #dc3545;
    }

    /* ===== TABLE ENHANCEMENTS ===== */
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

    /* ===== METRIC CARDS ===== */
    .metric-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        border-left: 4px solid var(--primary);
    }

    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }

    .metric-card .metric-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .metric-card .metric-label {
        color: #6c757d;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .metric-card .metric-change {
        font-size: 0.8rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }

    .metric-change.positive {
        color: var(--success);
    }

    .metric-change.negative {
        color: var(--danger);
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

    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
        }
    }

    .pulse {
        animation: pulse 2s infinite;
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

    /* ===== EXPORT SECTION ===== */
    .export-section {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        border: 1px solid #e1f5fe;
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .export-section .card-header {
        background: transparent;
        border: none;
        padding: 0 0 1rem 0;
        margin-bottom: 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .export-section .card-title {
        color: #2c3e50;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
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
        
        .chart-container {
            margin-bottom: 1rem;
        }
        
        .nav-tabs .nav-link {
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
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
        
        .metric-card .metric-value {
            font-size: 2rem;
        }
        
        .table-responsive {
            font-size: 0.875rem;
        }
    }

    /* ===== CUSTOM CHART STYLES ===== */
    .chart-wrapper {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .chart-legend {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }

    /* ===== KPI INDICATORS ===== */
    .kpi-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.8);
        border-radius: var(--border-radius);
        border-left: 4px solid var(--success);
    }

    .kpi-indicator.warning {
        border-left-color: var(--warning);
    }

    .kpi-indicator.danger {
        border-left-color: var(--danger);
    }

    .kpi-value {
        font-weight: 700;
        font-size: 1.2rem;
        color: #2c3e50;
    }

    .kpi-label {
        color: #6c757d;
        font-size: 0.875rem;
    }

    /* ===== LOADING STATES ===== */
    .loading {
        position: relative;
        overflow: hidden;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% { left: -100%; }
        100% { left: 100%; }
    }

    /* ===== REPORT SUMMARY ===== */
    .report-summary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 1.5rem;
    }

    .report-summary h4 {
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .summary-stat {
        text-align: center;
    }

    .summary-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .summary-label {
        font-size: 0.875rem;
        opacity: 0.9;
    }

    /* ===== COMPARISON CARDS ===== */
    .comparison-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        border-top: 4px solid var(--primary);
    }

    .comparison-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }

    .comparison-card .current-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .comparison-card .previous-value {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .comparison-card .change-indicator {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 20px;
    }

    .change-positive {
        background: rgba(40, 167, 69, 0.1);
        color: var(--success);
    }

    .change-negative {
        background: rgba(220, 53, 69, 0.1);
        color: var(--danger);
    }

    /* ===== DATA GRID ===== */
    .data-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .data-item {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }

    .data-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .data-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        font-size: 1.25rem;
    }

    .data-content {
        flex: 1;
    }

    .data-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .data-label {
        color: #6c757d;
        font-size: 0.875rem;
    }
</style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="d-flex flex-column p-3">
                    <h4 class="text-center mb-4"><?php echo APP_NAME; ?></h4>
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
                            <a href="invoices.php" class="nav-link">
                                <i class="fas fa-file-invoice me-2"></i> Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link active">
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
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Analytics & Reports</h2>
                    <div>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-select" name="report_type">
                                    <option value="financial_summary" <?php echo $report_type === 'financial_summary' ? 'selected' : ''; ?>>Financial Summary</option>
                                    <option value="invoice_analysis" <?php echo $report_type === 'invoice_analysis' ? 'selected' : ''; ?>>Invoice Analysis</option>
                                    <option value="customer_reports" <?php echo $report_type === 'customer_reports' ? 'selected' : ''; ?>>Customer Reports</option>
                                    <option value="package_performance" <?php echo $report_type === 'package_performance' ? 'selected' : ''; ?>>Package Performance</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Generate Report</button>
                                    <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <?php if ($report_type === 'financial_summary'): ?>
                    <!-- Financial Summary Report -->
                    <div class="report-section">
                        <h4 class="mb-4">Financial Summary (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h4>
                        
                        <!-- Key Metrics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stat-card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4>$<?php echo number_format($reports['total_revenue'], 2); ?></h4>
                                                <p>Total Revenue</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-dollar-sign fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4><?php echo $reports['total_invoices']; ?></h4>
                                                <p>Total Invoices</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-file-invoice fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4><?php echo $reports['paid_invoices']; ?></h4>
                                                <p>Paid Invoices</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-check-circle fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4>$<?php echo number_format($reports['avg_invoice_value'], 2); ?></h4>
                                                <p>Avg Invoice Value</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-calculator fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Revenue by Payment Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="revenueStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Monthly Revenue Trend</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="revenueTrendChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($report_type === 'invoice_analysis'): ?>
                    <!-- Invoice Analysis Report -->
                    <div class="report-section">
                        <h4 class="mb-4">Invoice Analysis (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Invoice Status Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="invoiceStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Top 10 Invoices by Amount</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Invoice #</th>
                                                        <th>Customer</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reports['top_invoices'] as $invoice): ?>
                                                    <tr>
                                                        <td><?php echo $invoice['invoice_number']; ?></td>
                                                        <td><?php echo $invoice['customer_name']; ?></td>
                                                        <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $invoice['payment_status'] == 'paid' ? 'success' : ($invoice['payment_status'] == 'overdue' ? 'danger' : 'warning'); ?>">
                                                                <?php echo ucfirst($invoice['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($report_type === 'customer_reports'): ?>
                    <!-- Customer Reports -->
                    <div class="report-section">
                        <h4 class="mb-4">Customer Reports (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Top Customers by Spending</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="customerSpendingChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Customer Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Customer</th>
                                                        <th>Invoices</th>
                                                        <th>Total Spent</th>
                                                        <th>Avg Invoice</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reports['top_customers'] as $customer): ?>
                                                    <tr>
                                                        <td>
                                                            <div><?php echo $customer['customer_name']; ?></div>
                                                            <small class="text-muted"><?php echo $customer['customer_email']; ?></small>
                                                        </td>
                                                        <td><?php echo $customer['invoice_count']; ?></td>
                                                        <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                                        <td>$<?php echo number_format($customer['avg_invoice_value'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($report_type === 'package_performance'): ?>
                    <!-- Package Performance Report -->
                    <div class="report-section">
                        <h4 class="mb-4">Package Performance (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Package Revenue Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="packageRevenueChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Package Performance Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Package</th>
                                                        <th>Bookings</th>
                                                        <th>Revenue</th>
                                                        <th>Avg/Booking</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reports['package_performance'] as $package): ?>
                                                    <tr>
                                                        <td><?php echo $package['package_name']; ?></td>
                                                        <td><?php echo $package['bookings_count']; ?></td>
                                                        <td>$<?php echo number_format($package['total_revenue'], 2); ?></td>
                                                        <td>$<?php echo number_format($package['avg_revenue_per_booking'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts based on report type
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type === 'financial_summary'): ?>
                // Revenue by Status Pie Chart
                const revenueStatusCtx = document.getElementById('revenueStatusChart').getContext('2d');
                new Chart(revenueStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($chart_data['status_labels']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($chart_data['status_data']); ?>,
                            backgroundColor: <?php echo json_encode($chart_data['status_colors']); ?>,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Revenue Trend Line Chart
                const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
                new Chart(revenueTrendCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chart_data['trend_labels']); ?>,
                        datasets: [{
                            label: 'Monthly Revenue',
                            data: <?php echo json_encode($chart_data['trend_data']); ?>,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });

            <?php elseif ($report_type === 'invoice_analysis'): ?>
                // Invoice Status Doughnut Chart
                const invoiceStatusCtx = document.getElementById('invoiceStatusChart').getContext('2d');
                new Chart(invoiceStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($chart_data['invoice_status_labels']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($chart_data['invoice_status_data']); ?>,
                            backgroundColor: <?php echo json_encode($chart_data['invoice_status_colors']); ?>,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

            <?php elseif ($report_type === 'customer_reports'): ?>
                // Customer Spending Bar Chart
                const customerSpendingCtx = document.getElementById('customerSpendingChart').getContext('2d');
                new Chart(customerSpendingCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_data['customer_labels']); ?>,
                        datasets: [{
                            label: 'Total Spent ($)',
                            data: <?php echo json_encode($chart_data['customer_data']); ?>,
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });

            <?php elseif ($report_type === 'package_performance'): ?>
                // Package Revenue Bar Chart
                const packageRevenueCtx = document.getElementById('packageRevenueChart').getContext('2d');
                new Chart(packageRevenueCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_data['package_labels']); ?>,
                        datasets: [{
                            label: 'Revenue ($)',
                            data: <?php echo json_encode($chart_data['package_data']); ?>,
                            backgroundColor: '#6f42c1',
                            borderColor: '#59359a',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>