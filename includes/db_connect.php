<?php
// Database connection settings
$host = getenv('MYSQLHOST');      // Database server address
$port = getenv('MYSQLPORT');      // Database port
$db   = getenv('MYSQLDATABASE');  // Database name
$user = getenv('MYSQLUSER');      // Database username
$pass = getenv('MYSQLPASSWORD');  // Database password
$charset = 'utf8mb4';             // Character encoding

// Create Data Source Name (DSN) string for PDO connection
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// PDO connection options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

// Try to connect to database
try {
    // Create PDO connection object - this is used by all pages to access the database
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // If connection fails, stop script and show error
    die("Connection failed: " . $e->getMessage());
}
?>