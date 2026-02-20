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

// Load all available quizzes for students to browse
$available_quizzes = [];
try {
    $stmt = $pdo->query("SELECT q.id, q.topic, q.difficulty, q.created_at, u.username AS teacher_name
                         FROM quizzes q
                         JOIN users u ON q.created_by = u.id
                         ORDER BY q.created_at DESC");
    $available_quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $available_quizzes = [];
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
                            <a href="#available-quizzes" class="btn btn-primary">Browse quizzes</a>
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

    <!-- Available quizzes for students -->
    <section class="page-section" id="available-quizzes">
        <div class="container px-4 px-lg-5">
            <h2 class="text-center mt-0 mb-4">Available Quizzes</h2>
            <?php if (empty($available_quizzes)): ?>
                <p class="text-muted text-center">No quizzes are available yet. Please check back later.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Topic</th>
                                <th scope="col">Difficulty</th>
                                <th scope="col">Teacher</th>
                                <th scope="col">Created</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_quizzes as $quiz): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($quiz['topic']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($quiz['difficulty']); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['created_at']); ?></td>
                                    <td>
                                        <a href="../student/quiz.php?quiz_id=<?php echo (int)$quiz['id']; ?>" class="btn btn-sm btn-primary">
                                            Start Quiz
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Load Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Creative theme JavaScript -->
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>





