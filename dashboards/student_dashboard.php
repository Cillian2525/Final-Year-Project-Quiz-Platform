<?php
// Start session to check user login status
session_start();
// Load database connection
require '../includes/db_connect.php';

// Security check: Only allow student users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic HTML page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Quiz System</title>
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
            <a class="navbar-brand" href="student_dashboard.php">Quiz System</a>
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
                    <h1 class="text-white font-weight-bold">Student Dashboard</h1>
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

                <!-- Display available quiz count (placeholder - shows 0) -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-journal-text fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white">0</h3>
                        <p class="text-white-75 mb-0">Available Quizzes</p>
                    </div>
                </div>
                <!-- Display completed quiz count (placeholder - shows 0) -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-check-circle fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white">0</h3>
                        <p class="text-white-75 mb-0">Completed</p>
                    </div>
                </div>
                <!-- Display average score (placeholder - shows 0) -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-graph-up fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white">0</h3>
                        <p class="text-white-75 mb-0">Average Score</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick action cards section -->
    <section class="page-section" id="actions">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5">
                <!-- Take Quiz card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi-book fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Take Quiz</h4>
                            <p class="card-text text-muted">Browse available quizzes</p>
                            <a href="../student/quiz.php" class="btn btn-primary">Browse quizzes</a>
                        </div>
                    </div>
                </div>
                <!-- View Results card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi-bar-chart fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">View Results</h4>
                            <p class="card-text text-muted">Check your progress</p>
                            <a href="#" class="btn btn-primary">My results</a>
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





