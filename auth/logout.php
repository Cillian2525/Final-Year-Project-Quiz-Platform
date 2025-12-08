<?php
// Start session to access user data
session_start();
// Destroy session to log user out
session_destroy();
// Redirect to login page
header('Location: login.php');
exit;
?>





