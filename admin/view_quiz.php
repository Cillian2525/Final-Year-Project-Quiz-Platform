<?php
session_start();
require '../includes/db_connect.php';

// Security: only admins may view quizzes from this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;
if ($quiz_id <= 0) {
    header('Location: manage_quizzes.php');
    exit;
}

$quiz = null;
try {
    $stmt = $pdo->prepare("SELECT q.id, q.topic, q.difficulty, q.created_at, u.username AS creator_name
                           FROM quizzes q
                           JOIN users u ON u.id = q.created_by
                           WHERE q.id = ?");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quiz = null;
}

if (!$quiz) {
    header('Location: manage_quizzes.php');
    exit;
}

$questions = [];
$total_questions = 0;
try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE topic = ? AND difficulty = ?');
    $countStmt->execute([(string)$quiz['topic'], (string)$quiz['difficulty']]);
    $total_questions = (int) $countStmt->fetchColumn();

    $qStmt = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer
                            FROM questions
                            WHERE topic = ? AND difficulty = ?
                            ORDER BY id DESC
                            LIMIT 50");
    $qStmt->execute([(string)$quiz['topic'], (string)$quiz['difficulty']]);
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $questions = [];
    $total_questions = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quiz - Quiz System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/admin_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="manage_quizzes.php">Manage Quizzes</a>
                <a class="nav-link" href="../dashboards/admin_dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead">
        <div class="container px-4 px-lg-5 h-100">
            <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white font-weight-bold">View Quiz</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5">Quiz details and matching question bank</p>
                </div>
            </div>
        </div>
    </header>

    <section class="page-section" id="quiz-details">
        <div class="container px-4 px-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mt-0 mb-0">Quiz #<?php echo (int)$quiz['id']; ?></h2>
                <a href="manage_quizzes.php" class="btn btn-outline-secondary">Back</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>Topic:</strong> <?php echo htmlspecialchars($quiz['topic'] ?? ''); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Difficulty:</strong> <span class="text-capitalize"><?php echo htmlspecialchars($quiz['difficulty'] ?? ''); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Created By:</strong> <?php echo htmlspecialchars($quiz['creator_name'] ?? ''); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Created At:</strong> <?php echo htmlspecialchars($quiz['created_at'] ?? ''); ?>
                        </div>
                        <div class="col-12">
                            <strong>Question Bank Matches:</strong> <?php echo (int)$total_questions; ?>
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="h5 mb-3">Questions (showing up to 50)</h3>

            <?php if (empty($questions)): ?>
                <div class="alert alert-warning" role="alert">
                    No questions found for this topic and difficulty.
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Question</th>
                                <th scope="col">Correct</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $q): ?>
                                <tr>
                                    <td><?php echo (int)($q['id'] ?? 0); ?></td>
                                    <td>
                                        <div class="fw-semibold mb-1"><?php echo htmlspecialchars($q['question_text'] ?? ''); ?></div>
                                        <div class="small text-muted">
                                            A. <?php echo htmlspecialchars($q['option_a'] ?? ''); ?><br>
                                            B. <?php echo htmlspecialchars($q['option_b'] ?? ''); ?><br>
                                            C. <?php echo htmlspecialchars($q['option_c'] ?? ''); ?><br>
                                            D. <?php echo htmlspecialchars($q['option_d'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($q['correct_answer'] ?? ''); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>
