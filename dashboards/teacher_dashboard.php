<?php
// Start session to check user login status
session_start();
// Load database connection
require '../includes/db_connect.php';

// Security check: Only allow teacher users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch quizzes with performance stats (total attempts, avg score) - LEFT JOIN so zero-attempt quizzes show
$my_quizzes = [];
try {
    $stmt = $pdo->prepare("SELECT q.id, q.topic, q.difficulty, q.created_at,
                                COUNT(a.id) AS total_attempts,
                                AVG(a.percentage) AS avg_score
                        FROM quizzes q
                        LEFT JOIN quiz_attempts a ON a.quiz_id = q.id
                        WHERE q.created_by = ?
                        GROUP BY q.id, q.topic, q.difficulty, q.created_at
                        ORDER BY q.created_at DESC");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $my_quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $my_quizzes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic HTML page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Quiz System</title>
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
            <a class="navbar-brand" href="teacher_dashboard.php">Quiz System</a>
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
                    <h1 class="text-white font-weight-bold">Teacher Dashboard</h1>
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

                <!-- Display quiz count (placeholder - shows 0) -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-journal-text fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white">0</h3>
                        <p class="text-white-75 mb-0">My Quizzes</p>
                    </div>
                </div>
                <!-- Display student count (placeholder - shows 0) -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-people fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white">0</h3>
                        <p class="text-white-75 mb-0">Students Enrolled</p>
                    </div>
                </div>
                <!-- Display submission count (placeholder - shows 0) -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-clipboard-check fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white">0</h3>
                        <p class="text-white-75 mb-0">Submissions</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick action cards section -->
    <section class="page-section" id="actions">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5">
                <!-- Create Quiz card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi-plus-circle fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Create Quiz</h4>
                            <p class="card-text text-muted">Start a new quiz and share it with your students</p>
                            <a href="../teacher/create_quiz.php" class="btn btn-primary">Create quiz</a>
                        </div>
                    </div>
                </div>
                <!-- Manage Quizzes card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi-list-ul fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Manage Quizzes</h4>
                            <p class="card-text text-muted">View and edit quizzes</p>
                            <a href="#" class="btn btn-primary">View quizzes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- My Quizzes with performance stats -->
    <section class="page-section" id="my-quizzes">
        <div class="container px-4 px-lg-5">
            <h2 class="text-center mt-0 mb-4">Quiz Performance</h2>
            <?php if (empty($my_quizzes)): ?>
                <p class="text-muted text-center">You have not created any quizzes yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Topic</th>
                                <th scope="col">Difficulty</th>
                                <th scope="col">Total Attempts</th>
                                <th scope="col">Average Score</th>
                                <th scope="col">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_quizzes as $quiz): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($quiz['topic'] ?? ''); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($quiz['difficulty'] ?? ''); ?></td>
                                    <td><?php echo (int)($quiz['total_attempts'] ?? 0); ?></td>
                                    <td><?php echo ((int)($quiz['total_attempts'] ?? 0)) > 0 ? number_format((float)($quiz['avg_score'] ?? 0), 1) . '%' : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($quiz['created_at'] ?? ''); ?></td>
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





