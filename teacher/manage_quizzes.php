<?php
session_start();
require '../includes/db_connect.php';

// Security: only teachers may manage quizzes
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit;
}

$teacher_id = (int) $_SESSION['user_id'];

$message = $_SESSION['teacher_quiz_msg'] ?? '';
$messageType = $_SESSION['teacher_quiz_msg_type'] ?? '';
unset($_SESSION['teacher_quiz_msg'], $_SESSION['teacher_quiz_msg_type']);

// Handle delete (POST then redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['quiz_id']) && $_POST['action'] === 'delete') {
    $quiz_id = (int) $_POST['quiz_id'];
    if ($quiz_id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM quizzes WHERE id = ? AND created_by = ?');
            $stmt->execute([$quiz_id, $teacher_id]);
            $_SESSION['teacher_quiz_msg'] = $stmt->rowCount() > 0 ? 'Quiz deleted.' : 'Quiz not found.';
            $_SESSION['teacher_quiz_msg_type'] = $stmt->rowCount() > 0 ? 'success' : 'warning';
        } catch (PDOException $e) {
            $_SESSION['teacher_quiz_msg'] = 'Delete failed. Please try again.';
            $_SESSION['teacher_quiz_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['teacher_quiz_msg'] = 'Invalid quiz id.';
        $_SESSION['teacher_quiz_msg_type'] = 'warning';
    }

    header('Location: manage_quizzes.php');
    exit;
}

// Fetch this teacher's quizzes with performance stats
$quizzes = [];
try {
    $stmt = $pdo->prepare("SELECT q.id, q.topic, q.difficulty, q.created_at,
                                COUNT(a.id) AS total_attempts,
                                AVG(a.percentage) AS avg_score
                        FROM quizzes q
                        LEFT JOIN quiz_attempts a ON a.quiz_id = q.id
                        WHERE q.created_by = ?
                        GROUP BY q.id, q.topic, q.difficulty, q.created_at
                        ORDER BY q.created_at DESC");
    $stmt->execute([$teacher_id]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quizzes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes - Quiz System</title>
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
            <a class="navbar-brand" href="../dashboards/teacher_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboards/teacher_dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead" style="padding-top: 5rem; padding-bottom: 1rem; min-height: 0;">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="text-white fw-bold h3 mb-1">Manage Quizzes</h1>
                    <p class="text-white-75 mb-2 small">View your quizzes and performance</p>
                </div>
            </div>

            <div class="row gx-4 gx-lg-5 justify-content-center" id="quizzes">
                <div class="col-12 col-lg-10">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-white-75 small">My quizzes</div>
                        <a href="../dashboards/teacher_dashboard.php" class="btn btn-sm btn-primary px-3 rounded-pill">Back</a>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?> mb-2" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-2">
                            <?php if (empty($quizzes)): ?>
                                <p class="text-muted text-center mb-0">You have not created any quizzes yet.</p>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                                    <table class="table table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Topic</th>
                                                <th scope="col">Difficulty</th>
                                                <th scope="col">Attempts</th>
                                                <th scope="col">Avg</th>
                                                <th scope="col">Created</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($quizzes as $q): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($q['topic'] ?? ''); ?></td>
                                                    <td class="text-capitalize"><?php echo htmlspecialchars($q['difficulty'] ?? ''); ?></td>
                                                    <td><?php echo (int)($q['total_attempts'] ?? 0); ?></td>
                                                    <td><?php echo ((int)($q['total_attempts'] ?? 0)) > 0 ? number_format((float)($q['avg_score'] ?? 0), 1) . '%' : '—'; ?></td>
                                                    <td><?php echo htmlspecialchars($q['created_at'] ?? ''); ?></td>
                                                    <td>
                                                        <a class="btn btn-sm btn-primary px-3 rounded-pill" href="view_quiz.php?quiz_id=<?php echo (int)($q['id'] ?? 0); ?>">View</a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this quiz? This will also delete its attempt records.');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="quiz_id" value="<?php echo (int)($q['id'] ?? 0); ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger px-3 rounded-pill">Delete</button>
                                                        </form>
                                                    </td>
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
