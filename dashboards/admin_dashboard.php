<?php
// Start session to check user login status
session_start();
// Load database connection
require '../includes/db_connect.php';

// Security check: Only allow admin users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize count variables
$studentCount = 0;
$teacherCount = 0;
$lockedCount = 0;
$totalUsers = 0;
$totalQuizzes = 0;
$totalAttempts = 0;

// Get statistics from database (real values)
try {
    $studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $teacherCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $lockedCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'locked'")->fetchColumn();
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalQuizzes = (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
    $totalAttempts = (int) $pdo->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn();
} catch (PDOException $e) {
    // If database query fails, counts stay at zero
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic HTML page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quiz System</title>
    <!-- Load Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Load Creative Bootstrap theme CSS -->
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Top navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="admin_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Hero section with background image (masthead) -->
    <header class="masthead">
        <div class="container px-4 px-lg-5 h-100">
            <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white font-weight-bold">Admin Dashboard</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <a class="btn btn-primary btn-xl" href="#stats">View Stats</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Statistics section with blue background -->
    <section class="page-section bg-primary" id="stats">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5">

                <!-- Display student count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-people fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $studentCount; ?></h3>
                        <p class="text-white-75 mb-0">Students</p>
                    </div>
                </div>
                <!-- Display teacher count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-person-badge fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $teacherCount; ?></h3>
                        <p class="text-white-75 mb-0">Teachers</p>
                    </div>
                </div>
                <!-- Display locked account count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-lock fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $lockedCount; ?></h3>
                        <p class="text-white-75 mb-0">Locked Accounts</p>
                    </div>
                </div>
                <!-- Display total user count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-people-fill fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $totalUsers; ?></h3>
                        <p class="text-white-75 mb-0">Total Users</p>
                    </div>
                </div>
                <!-- Display total quizzes -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-journal-text fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $totalQuizzes; ?></h3>
                        <p class="text-white-75 mb-0">Total Quizzes</p>
                    </div>
                </div>
                <!-- Display total attempts -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-clipboard-check fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $totalAttempts; ?></h3>
                        <p class="text-white-75 mb-0">Total Attempts</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick action cards section -->
    <section class="page-section" id="actions">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5">
                <!-- Manage Users card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi-people-fill fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Manage Users</h4>
                            <p class="card-text text-muted">Review and manage user accounts</p>
                            <a href="#" class="btn btn-primary">Go to users</a>
                        </div>
                    </div>
                </div>
                <!-- Manage Quizzes card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi-journal-text fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Manage Quizzes</h4>
                            <p class="card-text text-muted">Create and manage quizzes</p>
                            <a href="#" class="btn btn-primary">Go to quizzes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Load Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Creative theme JavaScript -->
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>





