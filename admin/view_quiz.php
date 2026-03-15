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
    <title>View Quiz - IntelliQuiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        header.masthead { position: relative; }
        header.masthead::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            pointer-events: none;
        }
        header.masthead > .container { position: relative; z-index: 1; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/admin_dashboard.php">IntelliQuiz</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="manage_quizzes.php">Manage Quizzes</a>
                <a class="nav-link" href="../dashboards/admin_dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead" style="padding-top: 5rem; padding-bottom: .75rem; min-height: 0;">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="text-white fw-bold h3 mb-1">View Quiz</h1>
                    <p class="text-white-75 mb-2 small">Quiz details and matching question bank</p>
                </div>
            </div>

            <div class="row gx-4 gx-lg-5 justify-content-center" id="quiz-details">
                <div class="col-12 col-lg-10">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-white-75 small">Quiz #<?php echo (int)$quiz['id']; ?></div>
                        <a href="manage_quizzes.php" class="btn btn-sm btn-primary px-3 rounded-pill">Back</a>
                    </div>

                    <div class="card border-0 rounded-4 shadow-sm mb-2">
                        <div class="card-body p-2">
                            <div class="row g-1">
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

                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center px-1 pt-1 mb-2">
                                <div class="small text-muted">Questions (showing up to 50)</div>
                            </div>

                            <?php if (empty($questions)): ?>
                                <div class="alert alert-warning mb-0" role="alert">
                                    No questions found for this topic and difficulty.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 55vh; overflow-y: auto;">
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
                    </div>
                </div>
            </div>
        </div>
    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>
