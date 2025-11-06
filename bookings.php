<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        // Handle create/update booking
        $booking_data = [
            'user_email' => $_POST['user_email'],
            'package_id' => $_POST['package_id'],
            'package_name' => $_POST['package_name'],
            'from_date' => $_POST['from_date'],
            'to_date' => $_POST['to_date'],
            'status' => $_POST['status']
        ];
        
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO booking (user_email, package_id, package_name, from_date, to_date, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $booking_data['user_email'],
                $booking_data['package_id'],
                $booking_data['package_name'],
                $booking_data['from_date'],
                $booking_data['to_date'],
                $booking_data['status']
            ]);
            
            $booking_id = $pdo->lastInsertId();
            
            // Log the action
            logActivity($pdo, 'booking_create', "Booking #{$booking_id} created for {$booking_data['user_email']}");
            
            $_SESSION['success'] = "Booking created successfully!";
        } else {
            $booking_id = $_POST['booking_id'];
            $stmt = $pdo->prepare("
                UPDATE booking SET 
                user_email = ?, package_id = ?, package_name = ?, from_date = ?, to_date = ?, status = ?
                WHERE booking_id = ?
            ");
            $stmt->execute([
                $booking_data['user_email'],
                $booking_data['package_id'],
                $booking_data['package_name'],
                $booking_data['from_date'],
                $booking_data['to_date'],
                $booking_data['status'],
                $booking_id
            ]);
            
            // Log the action
            logActivity($pdo, 'booking_update', "Booking #{$booking_id} updated");
            
            $_SESSION['success'] = "Booking updated successfully!";
        }
        
        header('Location: bookings.php');
        exit;
    } elseif ($action === 'delete') {
        $booking_id = $_POST['booking_id'];
        
        // Check if booking has invoices
        $invoice_check = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE booking_id = ?");
        $invoice_check->execute([$booking_id]);
        $invoice_count = $invoice_check->fetchColumn();
        
        if ($invoice_count > 0) {
            $_SESSION['error'] = "Cannot delete booking. There are invoices associated with this booking.";
        } else {
            // Log before deletion
            logActivity($pdo, 'booking_delete', "Booking #{$booking_id} deleted");
            
            $stmt = $pdo->prepare("DELETE FROM booking WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            
            $_SESSION['success'] = "Booking deleted successfully!";
        }
        
        header('Location: bookings.php');
        exit;
    } elseif ($action === 'create_invoice') {
        // Create invoice from booking
        $booking_id = $_POST['booking_id'];
        
        // Get booking details
        $booking_stmt = $pdo->prepare("SELECT * FROM booking WHERE booking_id = ?");
        $booking_stmt->execute([$booking_id]);
        $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            // Generate invoice number
            $prefix = $settings['invoice_prefix'] ?? 'RTT-INV-';
            $last_id = $pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn();
            $invoice_number = $prefix . str_pad($last_id + 1, 4, '0', STR_PAD_LEFT);
            
            // Calculate package price based on package type
            $package_price = calculatePackagePrice($booking['package_name'], $booking['from_date'], $booking['to_date']);
            $tax_rate = $settings['tax_rate'] ?? 10;
            $tax_amount = ($package_price * $tax_rate) / 100;
            $total_amount = $package_price + $tax_amount;
            
            // Extract customer name from email
            $customer_name = ucwords(str_replace(['.', '-', '_'], ' ', explode('@', $booking['user_email'])[0]));
            
            // Create invoice
            $stmt = $pdo->prepare("
                INSERT INTO invoice (invoice_number, booking_id, invoice_date, customer_name, customer_email, package_name, package_price, tax, discount, total_amount, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoice_number,
                $booking_id,
                date('Y-m-d'),
                $customer_name,
                $booking['user_email'],
                $booking['package_name'],
                $package_price,
                $tax_rate,
                0,
                $total_amount,
                'pending'
            ]);
            
            $invoice_id = $pdo->lastInsertId();
            
            // Log the action
            logActivity($pdo, 'invoice_from_booking', "Invoice {$invoice_number} created from booking #{$booking_id}");
            
            $_SESSION['success'] = "Invoice created from booking successfully!";
            header('Location: invoice_view.php?id=' . $invoice_id);
            exit;
        }
    }
}

// Get all bookings with optional filters
$where_conditions = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(user_email LIKE ? OR package_name LIKE ?)";
    $search_term = "%{$_GET['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['from_date']) && !empty($_GET['from_date'])) {
    $where_conditions[] = "from_date >= ?";
    $params[] = $_GET['from_date'];
}

if (isset($_GET['to_date']) && !empty($_GET['to_date'])) {
    $where_conditions[] = "to_date <= ?";
    $params[] = $_GET['to_date'];
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$bookings = $pdo->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM invoice i WHERE i.booking_id = b.booking_id) as invoice_count
    FROM booking b 
    $where_sql 
    ORDER BY b.created_at DESC
");
$bookings->execute($params);
$bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

// Calculate booking statistics
$total_bookings = count($bookings);
$confirmed_bookings = count(array_filter($bookings, function($booking) { return $booking['status'] == 'confirmed'; }));
$pending_bookings = count(array_filter($bookings, function($booking) { return $booking['status'] == 'pending'; }));
$cancelled_bookings = count(array_filter($bookings, function($booking) { return $booking['status'] == 'cancelled'; }));
$bookings_with_invoices = count(array_filter($bookings, function($booking) { return $booking['invoice_count'] > 0; }));

// Common travel packages
$common_packages = [
    ['id' => 1, 'name' => 'Bali Paradise Tour', 'base_price' => 1200],
    ['id' => 2, 'name' => 'European Adventure', 'base_price' => 2500],
    ['id' => 3, 'name' => 'Thailand Explorer', 'base_price' => 900],
    ['id' => 4, 'name' => 'Japan Cultural Journey', 'base_price' => 1800],
    ['id' => 5, 'name' => 'Australian Outback', 'base_price' => 2200],
    ['id' => 6, 'name' => 'Caribbean Cruise', 'base_price' => 1500],
    ['id' => 7, 'name' => 'African Safari', 'base_price' => 3000],
    ['id' => 8, 'name' => 'USA West Coast', 'base_price' => 2000]
];

// Function to calculate package price based on duration
function calculatePackagePrice($package_name, $from_date, $to_date) {
    $base_prices = [
        'Bali Paradise Tour' => 1200,
        'European Adventure' => 2500,
        'Thailand Explorer' => 900,
        'Japan Cultural Journey' => 1800,
        'Australian Outback' => 2200,
        'Caribbean Cruise' => 1500,
        'African Safari' => 3000,
        'USA West Coast' => 2000
    ];
    
    $base_price = $base_prices[$package_name] ?? 1000;
    
    // Calculate duration in days
    $start = new DateTime($from_date);
    $end = new DateTime($to_date);
    $duration = $start->diff($end)->days;
    
    // Adjust price based on duration (minimum 7 days)
    $duration = max($duration, 7);
    $price_per_day = $base_price / 7;
    $total_price = $price_per_day * $duration;
    
    return round($total_price, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ===== BOOKINGS ENHANCED STYLES ===== */
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

        /* ===== BOOKING CARDS ===== */
        .booking-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            background: white;
            height: 100%;
            position: relative;
        }

        .booking-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .booking-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .booking-card .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .booking-card .card-body {
            padding: 1.5rem;
        }

        .booking-card .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .booking-card .card-text {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .booking-card .card-footer {
            background: rgba(248, 249, 250, 0.8);
            border: none;
            padding: 1rem 1.5rem;
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

        .badge-confirmed { 
            background: linear-gradient(135deg, #28a745, #20c997) !important; 
            color: white; 
        }
        .badge-pending { 
            background: linear-gradient(135deg, #ffc107, #fd7e14) !important; 
            color: white; 
        }
        .badge-cancelled { 
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

        .btn-outline-success {
            border: 2px solid var(--success);
            color: var(--success);
            background: transparent;
        }

        .btn-outline-success:hover {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
            background: transparent;
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
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

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, white 100%);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, white 100%);
            color: #0c5460;
            border-left-color: #0dcaf0;
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

        /* ===== PRICE ESTIMATE STYLES ===== */
        .price-estimate {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #e1f5fe;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
        }

        .price-estimate i {
            color: #4facfe;
        }

        .estimated-price {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.2rem;
        }

        .duration-days {
            color: #6c757d;
            font-weight: 500;
        }

        /* ===== BOOKING INFO STYLES ===== */
        .booking-info {
            line-height: 1.6;
        }

        .booking-email {
            font-weight: 600;
            color: #2c3e50;
        }

        .booking-dates {
            color: #6c757d;
        }

        .invoice-count {
            background: rgba(13, 110, 253, 0.1);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
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
                            <a href="bookings.php" class="nav-link active">
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
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="fade-in-up">Booking Management</h2>
                        <div class="user-welcome slide-in-right">
                            <i class="fas fa-user-circle me-2"></i>Welcome, <?php echo $_SESSION['username']; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4 fade-in-up">
                    <div class="col-md-2">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h4><?php echo $total_bookings; ?></h4>
                                <p>Total Bookings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h4><?php echo $confirmed_bookings; ?></h4>
                                <p>Confirmed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h4><?php echo $pending_bookings; ?></h4>
                                <p>Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h4><?php echo $cancelled_bookings; ?></h4>
                                <p>Cancelled</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <h4><?php echo $bookings_with_invoices; ?></h4>
                                <p>With Invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card bg-dark text-white">
                            <div class="card-body">
                                <div class="icon-container">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <h4><?php echo $total_bookings > 0 ? round(($confirmed_bookings / $total_bookings) * 100) : 0; ?>%</h4>
                                <p>Success Rate</p>
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

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show fade-in-up" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 fade-in-up">
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal">
                            <i class="fas fa-plus me-2"></i> Create New Booking
                        </button>
                    </div>
                    <div class="text-muted">
                        <i class="fas fa-filter me-2"></i>Advanced Filtering
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="filter-card fade-in-up">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Customer email, package name..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo ($_GET['status'] ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2 w-100">
                                <i class="fas fa-search me-2"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="bookings.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Bookings Grid -->
                <div class="row fade-in-up">
                    <?php if (empty($bookings)): ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h5>No Bookings Found</h5>
                                <p>Create your first booking to get started</p>
                                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#bookingModal">
                                    <i class="fas fa-plus me-2"></i> Create Booking
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card booking-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Booking #<?php echo $booking['booking_id']; ?></h6>
                                    <span class="status-badge badge-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo $booking['package_name']; ?></h6>
                                    <div class="booking-info">
                                        <div class="booking-email">
                                            <i class="fas fa-user me-2"></i><?php echo $booking['user_email']; ?>
                                        </div>
                                        <div class="booking-dates mt-2">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('M j, Y', strtotime($booking['from_date'])); ?> - 
                                            <?php echo date('M j, Y', strtotime($booking['to_date'])); ?>
                                        </div>
                                        <div class="mt-2">
                                            <span class="invoice-count">
                                                <i class="fas fa-file-invoice me-1"></i>
                                                <?php echo $booking['invoice_count']; ?> invoice(s)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="btn-group w-100">
                                        <button class="btn btn-outline-primary btn-sm edit-booking" 
                                                data-booking='<?php echo htmlspecialchars(json_encode($booking), ENT_QUOTES, 'UTF-8'); ?>'
                                                title="Edit Booking">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($booking['status'] === 'confirmed' && $booking['invoice_count'] == 0): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="create_invoice">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="Create Invoice">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled title="Create Invoice">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($booking['invoice_count'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Booking">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled title="Cannot delete - has invoices">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="action" value="create" id="formAction">
                    <input type="hidden" name="booking_id" id="bookingId">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Customer Email</label>
                                    <input type="email" class="form-control" name="user_email" id="userEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Package</label>
                                    <select class="form-select" name="package_name" id="packageName" required>
                                        <option value="">Select Package</option>
                                        <?php foreach ($common_packages as $package): ?>
                                        <option value="<?php echo $package['name']; ?>" data-price="<?php echo $package['base_price']; ?>">
                                            <?php echo $package['name']; ?> ($<?php echo number_format($package['base_price'], 2); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="Custom">Custom Package</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Package ID</label>
                                    <input type="number" class="form-control" name="package_id" id="packageId" required min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="from_date" id="fromDate" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="to_date" id="toDate" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Price Estimate -->
                        <div class="price-estimate" id="priceEstimate" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Price Estimate:</strong>
                                </div>
                                <div class="text-end">
                                    <span class="estimated-price">$<span id="estimatedPrice">0.00</span></span>
                                    <br>
                                    <small class="duration-days">for <span id="durationDays">0</span> days</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate duration and price estimate
        function calculatePriceEstimate() {
            const packageSelect = document.getElementById('packageName');
            const fromDate = document.getElementById('fromDate');
            const toDate = document.getElementById('toDate');
            const priceEstimate = document.getElementById('priceEstimate');
            const estimatedPrice = document.getElementById('estimatedPrice');
            const durationDays = document.getElementById('durationDays');
            
            if (packageSelect.value && fromDate.value && toDate.value) {
                const selectedOption = packageSelect.options[packageSelect.selectedIndex];
                const basePrice = selectedOption.dataset.price || 1000;
                
                // Calculate duration
                const start = new Date(fromDate.value);
                const end = new Date(toDate.value);
                const duration = Math.max(Math.ceil((end - start) / (1000 * 60 * 60 * 24)), 7);
                
                // Calculate price
                const pricePerDay = basePrice / 7;
                const totalPrice = (pricePerDay * duration).toFixed(2);
                
                estimatedPrice.textContent = totalPrice;
                durationDays.textContent = duration;
                priceEstimate.style.display = 'block';
            } else {
                priceEstimate.style.display = 'none';
            }
        }

        // Attach event listeners
        ['packageName', 'fromDate', 'toDate'].forEach(id => {
            document.getElementById(id).addEventListener('change', calculatePriceEstimate);
        });

        // Set default dates
        const today = new Date();
        const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
        
        document.getElementById('fromDate').valueAsDate = today;
        document.getElementById('toDate').valueAsDate = nextWeek;

        // Auto-generate package ID based on selection
        document.getElementById('packageName').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const packageText = selectedOption.text;
            
            // Extract package ID from common packages or generate one
            if (packageText.includes('Bali')) {
                document.getElementById('packageId').value = 1;
            } else if (packageText.includes('European')) {
                document.getElementById('packageId').value = 2;
            } else if (packageText.includes('Thailand')) {
                document.getElementById('packageId').value = 3;
            } else if (packageText.includes('Japan')) {
                document.getElementById('packageId').value = 4;
            } else if (packageText.includes('Australian')) {
                document.getElementById('packageId').value = 5;
            } else if (packageText.includes('Caribbean')) {
                document.getElementById('packageId').value = 6;
            } else if (packageText.includes('African')) {
                document.getElementById('packageId').value = 7;
            } else if (packageText.includes('USA')) {
                document.getElementById('packageId').value = 8;
            } else {
                // For custom packages, use a higher number
                document.getElementById('packageId').value = 9;
            }
            
            calculatePriceEstimate();
        });

        // Edit booking functionality
        document.querySelectorAll('.edit-booking').forEach(button => {
            button.addEventListener('click', function() {
                const booking = JSON.parse(this.dataset.booking);
                
                document.getElementById('formAction').value = 'update';
                document.getElementById('bookingId').value = booking.booking_id;
                document.querySelector('.modal-title').textContent = 'Edit Booking';
                
                // Populate form fields
                document.getElementById('userEmail').value = booking.user_email;
                document.getElementById('packageName').value = booking.package_name;
                document.getElementById('packageId').value = booking.package_id;
                document.querySelector('select[name="status"]').value = booking.status;
                document.getElementById('fromDate').value = booking.from_date;
                document.getElementById('toDate').value = booking.to_date;
                
                // Show modal
                new bootstrap.Modal(document.getElementById('bookingModal')).show();
                
                // Calculate price estimate
                setTimeout(calculatePriceEstimate, 100);
            });
        });

        // Reset form when creating new booking
        document.getElementById('bookingModal').addEventListener('show.bs.modal', function() {
            if (document.getElementById('formAction').value === 'create') {
                document.getElementById('bookingForm').reset();
                document.getElementById('fromDate').valueAsDate = today;
                document.getElementById('toDate').valueAsDate = nextWeek;
                document.querySelector('select[name="status"]').value = 'pending';
                calculatePriceEstimate();
            }
        });

        // Initial calculation
        calculatePriceEstimate();

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