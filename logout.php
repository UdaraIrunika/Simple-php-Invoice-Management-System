<?php
require_once 'config.php';

// Log logout activity (FIXED: uses system_log)
if (isset($_SESSION['username'])) {
    logActivity($pdo, 'logout', 'User logged out');
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>