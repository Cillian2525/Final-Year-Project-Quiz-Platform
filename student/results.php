<?php
session_start();
require '../includes/db_connect.php';

// Only students can view results
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch all attempts for logged-in student, JOIN with quizzes for topic + difficulty
$attempts = [];
try {
    $stmt = $pdo->prepare("SELECT a.id, COALESCE(q.topic, a.topic) AS topic, COALESCE(q.difficulty, a.difficulty) AS difficulty,
                                   a.score, a.total_questions, a.percentage, a.attempt_date
                        FROM quiz_attempts a
                        LEFT JOIN quizzes q ON a.quiz_id = q.id
                        WHERE a.user_id = ?
                        ORDER BY a.attempt_date DESC");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $attempts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - Quiz System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/student_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead">
        <div class="container px-4 px-lg-5 h-100">
            <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white font-weight-bold">My Results</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5">Your quiz attempt history</p>
                </div>
            </div>
        </div>
    </header>

    <section class="page-section" id="results">
        <div class="container px-4 px-lg-5">
            <?php if (empty($attempts)): ?>
                <p class="text-muted text-center">You have not taken any quizzes yet.</p>
                <div class="text-center">
                    <a href="../dashboards/student_dashboard.php#available-quizzes" class="btn btn-primary">Browse quizzes</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Topic</th>
                                <th scope="col">Difficulty</th>
                                <th scope="col">Score</th>
                                <th scope="col">Percentage</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['topic'] ?? ''); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($a['difficulty'] ?? ''); ?></td>
                                    <td><?php echo (int)$a['score']; ?> / <?php echo (int)$a['total_questions']; ?></td>
                                    <td><?php echo number_format((float)($a['percentage'] ?? 0), 1); ?>%</td>
                                    <td><?php echo htmlspecialchars($a['attempt_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="mt-3">
                <a href="../dashboards/student_dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>
