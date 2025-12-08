<?php
// Start session to check if user is logged in
session_start();

// This is the main entry point - redirects users to the right page
// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // User is logged in - send them to their dashboard based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: dashboards/admin_dashboard.php');
            break;
        case 'teacher':
            header('Location: dashboards/teacher_dashboard.php');
            break;
        case 'student':
            header('Location: dashboards/student_dashboard.php');
            break;
        default:
            header('Location: auth/login.php');
    }
    exit;
} else {
    // User is not logged in - send them to login page
    header('Location: auth/login.php');
    exit;
}
?>





