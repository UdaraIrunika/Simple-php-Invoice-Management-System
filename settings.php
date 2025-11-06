<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        // Update company settings
        $settings_to_update = [
            'company_name' => $_POST['company_name'],
            'company_address' => $_POST['company_address'],
            'company_phone' => $_POST['company_phone'],
            'company_email' => $_POST['company_email'],
            'currency' => $_POST['currency'],
            'tax_rate' => $_POST['tax_rate'],
            'invoice_prefix' => $_POST['invoice_prefix'],
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'email_from_name' => $_POST['email_from_name'] ?? '',
            'invoice_footer' => $_POST['invoice_footer'] ?? ''
        ];
        
        foreach ($settings_to_update as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        // Log the action
        logActivity($pdo, 'settings_update', 'System settings updated');
        
        $_SESSION['success'] = "Settings updated successfully!";
        header('Location: settings.php');
        exit;
        
    } elseif ($action === 'create_user') {
        // Create new user
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        // Check if username or email already exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $check_stmt->execute([$username, $email]);
        $exists = $check_stmt->fetchColumn();
        
        if ($exists) {
            $_SESSION['error'] = "Username or email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password, $role]);
            
            // Log the action
            logActivity($pdo, 'user_create', "User {$username} created with role {$role}");
            
            $_SESSION['success'] = "User created successfully!";
        }
        
        header('Location: settings.php#users');
        exit;
        
    } elseif ($action === 'update_user') {
        // Update user
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        // Check if username or email already exists for other users
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
        $check_stmt->execute([$username, $email, $user_id]);
        $exists = $check_stmt->fetchColumn();
        
        if ($exists) {
            $_SESSION['error'] = "Username or email already exists!";
        } else {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE user_id = ?");
                $stmt->execute([$username, $email, $hashed_password, $role, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
                $stmt->execute([$username, $email, $role, $user_id]);
            }
            
            // Log the action
            logActivity($pdo, 'user_update', "User {$username} updated");
            
            $_SESSION['success'] = "User updated successfully!";
        }
        
        header('Location: settings.php#users');
        exit;
        
    } elseif ($action === 'delete_user') {
        // Delete user (cannot delete self or last admin)
        $user_id = $_POST['user_id'];
        $current_user_id = $_SESSION['user_id'];
        
        if ($user_id == $current_user_id) {
            $_SESSION['error'] = "You cannot delete your own account!";
        } else {
            // Check if this is the last admin
            $admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            $user_stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
            $user_stmt->execute([$user_id]);
            $user_role = $user_stmt->fetchColumn();
            
            if ($user_role === 'admin' && $admin_count <= 1) {
                $_SESSION['error'] = "Cannot delete the last admin user!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Log the action
                logActivity($pdo, 'user_delete', "User ID {$user_id} deleted");
                
                $_SESSION['success'] = "User deleted successfully!";
            }
        }
        
        header('Location: settings.php#users');
        exit;
        
    } elseif ($action === 'test_email') {
        // Test email configuration
        $to = $_POST['test_email'];
        $subject = "Test Email from " . ($settings['company_name'] ?? APP_NAME);
        $message = "This is a test email to verify your SMTP configuration is working correctly.";
        $headers = "From: " . ($settings['company_email'] ?? 'noreply@royaltravel.com') . "\r\n";
        
        if (mail($to, $subject, $message, $headers)) {
            $_SESSION['success'] = "Test email sent successfully to {$to}!";
        } else {
            $_SESSION['error'] = "Failed to send test email. Please check your SMTP configuration.";
        }
        
        header('Location: settings.php#email');
        exit;
    }
}

// Get all settings
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$all_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'total_invoices' => $pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn(),
    'total_bookings' => $pdo->query("SELECT COUNT(*) FROM booking")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'database_size' => getDatabaseSize($pdo)
];

// Function to get database size
function getDatabaseSize($pdo) {
    $stmt = $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['size_mb'] ?? '0.00';
}

// Get recent activity logs
$recent_logs = $pdo->query("
    SELECT * FROM system_log 
    ORDER BY performed_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Complete list of all currencies
$currencies = [
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)',
    'JPY' => 'Japanese Yen (¥)',
    'CAD' => 'Canadian Dollar (C$)',
    'AUD' => 'Australian Dollar (A$)',
    'CHF' => 'Swiss Franc (CHF)',
    'CNY' => 'Chinese Yuan (¥)',
    'INR' => 'Indian Rupee (₹)',
    'BRL' => 'Brazilian Real (R$)',
    'RUB' => 'Russian Ruble (₽)',
    'KRW' => 'South Korean Won (₩)',
    'MXN' => 'Mexican Peso ($)',
    'SGD' => 'Singapore Dollar (S$)',
    'HKD' => 'Hong Kong Dollar (HK$)',
    'NZD' => 'New Zealand Dollar (NZ$)',
    'SEK' => 'Swedish Krona (kr)',
    'NOK' => 'Norwegian Krone (kr)',
    'DKK' => 'Danish Krone (kr)',
    'ZAR' => 'South African Rand (R)',
    'TRY' => 'Turkish Lira (₺)',
    'AED' => 'UAE Dirham (د.إ)',
    'SAR' => 'Saudi Riyal (﷼)',
    'THB' => 'Thai Baht (฿)',
    'MYR' => 'Malaysian Ringgit (RM)',
    'IDR' => 'Indonesian Rupiah (Rp)',
    'PHP' => 'Philippine Peso (₱)',
    'VND' => 'Vietnamese Dong (₫)',
    'PLN' => 'Polish Złoty (zł)',
    'CZK' => 'Czech Koruna (Kč)',
    'HUF' => 'Hungarian Forint (Ft)',
    'RON' => 'Romanian Leu (lei)',
    'ILS' => 'Israeli Shekel (₪)',
    'CLP' => 'Chilean Peso ($)',
    'COP' => 'Colombian Peso ($)',
    'ARS' => 'Argentine Peso ($)',
    'PEN' => 'Peruvian Sol (S/)',
    'EGP' => 'Egyptian Pound (£)',
    'NGN' => 'Nigerian Naira (₦)',
    'PKR' => 'Pakistani Rupee (₨)',
    'BDT' => 'Bangladeshi Taka (৳)',
    'LKR' => 'Sri Lankan Rupee (Rs)',
    'KES' => 'Kenyan Shilling (KSh)',
    'UGX' => 'Ugandan Shilling (USh)',
    'TZS' => 'Tanzanian Shilling (TSh)',
    'GHS' => 'Ghanaian Cedi (₵)',
    'MAD' => 'Moroccan Dirham (د.م.)',
    'DZD' => 'Algerian Dinar (د.ج)',
    'QAR' => 'Qatari Riyal (﷼)',
    'KWD' => 'Kuwaiti Dinar (د.ك)',
    'OMR' => 'Omani Rial (﷼)',
    'BHD' => 'Bahraini Dinar (.د.ب)',
    'JOD' => 'Jordanian Dinar (د.ا)',
    'LBP' => 'Lebanese Pound (ل.ل)',
    'ISK' => 'Icelandic Króna (kr)',
    'HRK' => 'Croatian Kuna (kn)',
    'BGN' => 'Bulgarian Lev (лв)',
    'UAH' => 'Ukrainian Hryvnia (₴)',
    'BYN' => 'Belarusian Ruble (Br)',
    'AZN' => 'Azerbaijani Manat (₼)',
    'GEL' => 'Georgian Lari (₾)',
    'AMD' => 'Armenian Dram (֏)',
    'KZT' => 'Kazakhstani Tenge (₸)',
    'UZS' => 'Uzbekistani Som (soʻm)',
    'IRR' => 'Iranian Rial (﷼)',
    'IQD' => 'Iraqi Dinar (ع.د)',
    'AFN' => 'Afghan Afghani (؋)',
    'NPR' => 'Nepalese Rupee (₨)',
    'MMK' => 'Myanmar Kyat (Ks)',
    'KHR' => 'Cambodian Riel (៛)',
    'LAK' => 'Laotian Kip (₭)',
    'MNT' => 'Mongolian Tögrög (₮)',
    'BND' => 'Brunei Dollar (B$)',
    'FJD' => 'Fijian Dollar (FJ$)',
    'PGK' => 'Papua New Guinean Kina (K)',
    'SBD' => 'Solomon Islands Dollar (SI$)',
    'TOP' => 'Tongan Paʻanga (T$)',
    'VUV' => 'Vanuatu Vatu (VT)',
    'WST' => 'Samoan Tālā (T)',
    'XPF' => 'CFP Franc (₣)',
    'XAF' => 'Central African CFA Franc (FCFA)',
    'XOF' => 'West African CFA Franc (CFA)',
    'XCD' => 'East Caribbean Dollar ($)',
    'ANG' => 'Netherlands Antillean Guilder (ƒ)',
    'AWG' => 'Aruban Florin (ƒ)',
    'BBD' => 'Barbadian Dollar ($)',
    'BMD' => 'Bermudian Dollar ($)',
    'BZD' => 'Belize Dollar (BZ$)',
    'CUC' => 'Cuban Convertible Peso ($)',
    'CUP' => 'Cuban Peso ($)',
    'DOP' => 'Dominican Peso ($)',
    'GTQ' => 'Guatemalan Quetzal (Q)',
    'HNL' => 'Honduran Lempira (L)',
    'JMD' => 'Jamaican Dollar (J$)',
    'NIO' => 'Nicaraguan Córdoba (C$)',
    'PYG' => 'Paraguayan Guaraní (₲)',
    'TTD' => 'Trinidad and Tobago Dollar (TT$)',
    'UYU' => 'Uruguayan Peso ($)',
    'VES' => 'Venezuelan Bolívar (Bs.)',
    'BTC' => 'Bitcoin (₿)',
    'ETH' => 'Ethereum (Ξ)',
    'LTC' => 'Litecoin (Ł)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* ===== SETTINGS ENHANCED STYLES ===== */
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

    .admin-badge {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* ===== SETTINGS SECTIONS ===== */
    .settings-section {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .settings-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--primary-light));
    }

    .settings-section:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .settings-section h4 {
        color: #2c3e50;
        font-weight: 700;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f8f9fa;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .settings-section h4 i {
        color: var(--primary);
        font-size: 1.25rem;
    }

    /* ===== NAV TABS ENHANCEMENTS ===== */
    .nav-tabs {
        border-bottom: 3px solid #e9ecef;
        margin-bottom: 2rem;
    }

    .nav-tabs .nav-link {
        border: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        padding: 1rem 1.5rem;
        color: #6c757d;
        font-weight: 500;
        transition: all 0.3s ease;
        background: transparent;
        margin-bottom: -3px;
        position: relative;
    }

    .nav-tabs .nav-link:hover {
        background-color: rgba(13, 110, 253, 0.05);
        color: var(--primary);
        border: none;
    }

    .nav-tabs .nav-link.active {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        box-shadow: var(--shadow-md);
        border: none;
    }

    .nav-tabs .nav-link i {
        margin-right: 0.5rem;
        font-size: 1.1rem;
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

    /* ===== SYSTEM INFO CARDS ===== */
    .system-info-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: white;
        border-left: 4px solid var(--primary);
        transition: all 0.3s ease;
    }

    .system-info-card:hover {
        transform: translateX(5px);
        box-shadow: var(--shadow-lg);
    }

    .system-info-card .card-title {
        color: #6c757d;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .system-info-card .card-text {
        color: #2c3e50;
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
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

    .form-text {
        color: #6c757d;
        font-size: 0.875rem;
    }

    /* ===== BUTTON ENHANCEMENTS ===== */
    .btn {
        border-radius: var(--border-radius);
        font-weight: 500;
        padding: 0.75rem 1.5rem;
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

    .btn-success {
        background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
        border: none;
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

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #e83e8c 100%);
        border: none;
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

    /* ===== BADGE ENHANCEMENTS ===== */
    .badge {
        padding: 0.5rem 0.75rem;
        border-radius: 20px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
    }

    .badge.bg-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)) !important; }
    .badge.bg-success { background: linear-gradient(135deg, var(--success), #20c997) !important; }
    .badge.bg-warning { background: linear-gradient(135deg, var(--warning), #fd7e14) !important; }
    .badge.bg-danger { background: linear-gradient(135deg, var(--danger), #e83e8c) !important; }
    .badge.bg-info { background: linear-gradient(135deg, var(--info), #3dd5f3) !important; }
    .badge.bg-secondary { background: linear-gradient(135deg, #6c757d, #8c9399) !important; }

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

    .alert-warning {
        background: linear-gradient(135deg, #fff3cd 0%, white 100%);
        color: #856404;
        border-left-color: #ffc107;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, white 100%);
        color: #0c5460;
        border-left-color: #0dcaf0;
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

    /* ===== CURRENCY SELECTOR ===== */
    .currency-select {
        height: 200px;
        border: 2px solid #e9ecef;
        border-radius: var(--border-radius);
        padding: 0.5rem;
    }

    .currency-select option {
        padding: 0.5rem;
        border-radius: 4px;
        margin: 2px 0;
        transition: background-color 0.2s ease;
    }

    .currency-select option:hover {
        background-color: var(--primary) !important;
        color: white;
    }

    .currency-select option:checked {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
    }

    /* ===== LOG ENTRY STYLES ===== */
    .log-entry {
        border-left: 4px solid #6c757d;
        padding: 1rem 1.5rem;
        margin-bottom: 1rem;
        background: white;
        border-radius: 0 var(--border-radius) var(--border-radius) 0;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }

    .log-entry:hover {
        transform: translateX(5px);
        box-shadow: var(--shadow-md);
    }

    .log-success { 
        border-left-color: var(--success);
        background: linear-gradient(135deg, #d4edda 0%, white 100%);
    }
    .log-warning { 
        border-left-color: var(--warning);
        background: linear-gradient(135deg, #fff3cd 0%, white 100%);
    }
    .log-danger { 
        border-left-color: var(--danger);
        background: linear-gradient(135deg, #f8d7da 0%, white 100%);
    }
    .log-info { 
        border-left-color: var(--info);
        background: linear-gradient(135deg, #d1ecf1 0%, white 100%);
    }

    .log-action {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .log-details {
        color: #6c757d;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .log-meta {
        color: #adb5bd;
        font-size: 0.75rem;
    }

    /* ===== USER MANAGEMENT STYLES ===== */
    .user-role-badge {
        font-size: 0.7rem;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .current-user-badge {
        background: linear-gradient(135deg, var(--info), #3dd5f3);
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 8px;
        margin-left: 0.5rem;
    }

    /* ===== TEST EMAIL SECTION ===== */
    .test-email-section {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        border: 1px solid #e1f5fe;
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-top: 2rem;
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
        
        .settings-section {
            padding: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
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
        
        .system-info-card .card-text {
            font-size: 1.1rem;
        }
        
        .table-responsive {
            font-size: 0.875rem;
        }
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

    /* ===== CUSTOM SCROLLBAR ===== */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    .currency-select::-webkit-scrollbar {
        width: 8px;
    }

    .currency-select::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .currency-select::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }

    .currency-select::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }

    /* ===== PASSWORD HELP TEXT ===== */
    .password-help {
        transition: all 0.3s ease;
        font-style: italic;
    }

    /* ===== SEARCH INPUT FOR CURRENCIES ===== */
    .currency-search {
        border: 2px solid #e9ecef;
        border-radius: var(--border-radius);
        padding: 0.75rem 1rem;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .currency-search:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
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
                            <a href="reports.php" class="nav-link">
                                <i class="fas fa-chart-bar me-2"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings.php" class="nav-link active">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
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
                    <h2>System Settings</h2>
                    <div class="text-muted">
                        <i class="fas fa-user-shield me-1"></i> Admin Panel
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="company-tab" data-bs-toggle="tab" data-bs-target="#company" type="button" role="tab">
                            <i class="fas fa-building me-1"></i> Company
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab">
                            <i class="fas fa-file-invoice me-1"></i> Invoice
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                            <i class="fas fa-envelope me-1"></i> Email
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                            <i class="fas fa-users me-1"></i> Users
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                            <i class="fas fa-info-circle me-1"></i> System Info
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabsContent">
                    <!-- Company Settings Tab -->
                    <div class="tab-pane fade show active" id="company" role="tabpanel">
                        <div class="settings-section">
                            <h4 class="mb-4"><i class="fas fa-building me-2"></i>Company Information</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" name="company_name" 
                                                   value="<?php echo htmlspecialchars($all_settings['company_name'] ?? 'Royal Travel and Tours'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select currency-select" name="currency" required size="10">
                                                <?php foreach ($currencies as $code => $name): ?>
                                                    <option value="<?php echo $code; ?>" 
                                                        <?php echo ($all_settings['currency'] ?? 'USD') === $code ? 'selected' : ''; ?>>
                                                        <?php echo $name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Scroll to see all available currencies</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Company Address</label>
                                    <textarea class="form-control" name="company_address" rows="3" required><?php echo htmlspecialchars($all_settings['company_address'] ?? '123 Travel Street, Tourism City, TC 12345'); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" name="company_phone" 
                                                   value="<?php echo htmlspecialchars($all_settings['company_phone'] ?? '+1 (555) 123-4567'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="company_email" 
                                                   value="<?php echo htmlspecialchars($all_settings['company_email'] ?? 'info@royaltravel.com'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Company Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Invoice Settings Tab -->
                    <div class="tab-pane fade" id="invoice" role="tabpanel">
                        <div class="settings-section">
                            <h4 class="mb-4"><i class="fas fa-file-invoice me-2"></i>Invoice Settings</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Invoice Prefix</label>
                                            <input type="text" class="form-control" name="invoice_prefix" 
                                                   value="<?php echo htmlspecialchars($all_settings['invoice_prefix'] ?? 'RTT-INV-'); ?>" required>
                                            <div class="form-text">This will be used to generate invoice numbers (e.g., RTT-INV-0001)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Default Tax Rate (%)</label>
                                            <input type="number" class="form-control" name="tax_rate" step="0.01" min="0" max="50" 
                                                   value="<?php echo htmlspecialchars($all_settings['tax_rate'] ?? '10'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Invoice Footer Text</label>
                                    <textarea class="form-control" name="invoice_footer" rows="3"><?php echo htmlspecialchars($all_settings['invoice_footer'] ?? 'Thank you for choosing our services! For questions about this invoice, please contact us.'); ?></textarea>
                                    <div class="form-text">This text will appear at the bottom of all invoices</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Invoice Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Email Settings Tab -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <div class="settings-section">
                            <h4 class="mb-4"><i class="fas fa-envelope me-2"></i>Email Configuration</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($all_settings['smtp_host'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($all_settings['smtp_port'] ?? '587'); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($all_settings['smtp_username'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">SMTP Password</label>
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($all_settings['smtp_password'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="email_from_name" 
                                           value="<?php echo htmlspecialchars($all_settings['email_from_name'] ?? ($all_settings['company_name'] ?? 'Royal Travel and Tours')); ?>">
                                    <div class="form-text">The name that will appear in the "From" field of sent emails</div>
                                </div>
                                
                                <div class="mb-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Email Settings
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Test Email Section -->
                            <div class="border-top pt-4">
                                <h5 class="mb-3">Test Email Configuration</h5>
                                <form method="POST" class="row g-3 align-items-end">
                                    <input type="hidden" name="action" value="test_email">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="col-md-8">
                                        <label class="form-label">Send test email to:</label>
                                        <input type="email" class="form-control" name="test_email" placeholder="Enter email address to test" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-paper-plane me-1"></i> Send Test
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- User Management Tab -->
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <div class="settings-section">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4><i class="fas fa-users me-2"></i>User Management</h4>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                                    <i class="fas fa-plus me-1"></i> Add User
                                </button>
                            </div>

                            <!-- Users Table -->
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                                <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary edit-user" 
                                                            data-user='<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <button class="btn btn-outline-secondary" disabled>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- System Information Tab -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <div class="settings-section">
                            <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i>System Information</h4>
                            
                            <!-- System Stats -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card system-info-card">
                                        <div class="card-body">
                                            <h6 class="card-title">PHP Version</h6>
                                            <p class="card-text h5"><?php echo $system_info['php_version']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card system-info-card">
                                        <div class="card-body">
                                            <h6 class="card-title">MySQL Version</h6>
                                            <p class="card-text h5"><?php echo $system_info['mysql_version']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card system-info-card">
                                        <div class="card-body">
                                            <h6 class="card-title">Database Size</h6>
                                            <p class="card-text h5"><?php echo $system_info['database_size']; ?> MB</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card system-info-card">
                                        <div class="card-body">
                                            <h6 class="card-title">Total Users</h6>
                                            <p class="card-text h5"><?php echo $system_info['total_users']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Stats -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <i class="fas fa-file-invoice fa-2x mb-2"></i>
                                            <h4><?php echo $system_info['total_invoices']; ?></h4>
                                            <p class="mb-0">Total Invoices</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                            <h4><?php echo $system_info['total_bookings']; ?></h4>
                                            <p class="mb-0">Total Bookings</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <i class="fas fa-server fa-2x mb-2"></i>
                                            <h4><?php echo $system_info['server_software']; ?></h4>
                                            <p class="mb-0">Web Server</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_logs)): ?>
                                        <p class="text-muted text-center py-3">No recent activity</p>
                                    <?php else: ?>
                                        <?php foreach ($recent_logs as $log): ?>
                                        <div class="log-entry <?php 
                                            echo strpos($log['action'], 'delete') !== false ? 'log-danger' : 
                                                 (strpos($log['action'], 'create') !== false ? 'log-success' : 
                                                 (strpos($log['action'], 'update') !== false ? 'log-info' : 'log-warning')); ?>">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                                <small class="text-muted"><?php echo date('M j, H:i', strtotime($log['performed_at'])); ?></small>
                                            </div>
                                            <div class="text-muted">
                                                <?php echo htmlspecialchars($log['action_details'] ?? 'No details'); ?>
                                            </div>
                                            <small>By: <?php echo htmlspecialchars($log['performed_by']); ?></small>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="userForm">
                    <input type="hidden" name="action" value="create_user" id="userFormAction">
                    <input type="hidden" name="user_id" id="userId">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="userUsername" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" id="userEmail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" id="userPassword" required>
                            <div class="form-text" id="passwordHelp">Enter a password for the new user</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User management functionality
        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', function() {
                const user = JSON.parse(this.dataset.user);
                
                document.getElementById('userFormAction').value = 'update_user';
                document.getElementById('userId').value = user.user_id;
                document.querySelector('.modal-title').textContent = 'Edit User';
                
                // Populate form fields
                document.getElementById('userUsername').value = user.username;
                document.getElementById('userEmail').value = user.email;
                document.querySelector('select[name="role"]').value = user.role;
                document.getElementById('userPassword').required = false;
                document.getElementById('passwordHelp').textContent = 'Leave blank to keep current password';
                
                // Show modal
                new bootstrap.Modal(document.getElementById('userModal')).show();
            });
        });

        // Reset user form when creating new user
        document.getElementById('userModal').addEventListener('show.bs.modal', function() {
            if (document.getElementById('userFormAction').value === 'create_user') {
                document.getElementById('userForm').reset();
                document.querySelector('.modal-title').textContent = 'Add New User';
                document.getElementById('userPassword').required = true;
                document.getElementById('passwordHelp').textContent = 'Enter a password for the new user';
            }
        });

        // Handle tab navigation from URL hash
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash) {
                const tab = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tab) {
                    new bootstrap.Tab(tab).show();
                }
            }
        });

        // Currency search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const currencySelect = document.querySelector('.currency-select');
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'form-control mb-2';
            searchInput.placeholder = 'Search currencies...';
            
            currencySelect.parentNode.insertBefore(searchInput, currencySelect);
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const options = currencySelect.options;
                
                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    const text = option.text.toLowerCase();
                    option.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            });
        });
    </script>
</body>
</html>