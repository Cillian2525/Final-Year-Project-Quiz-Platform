<?php
session_start();
require '../includes/db_connect.php';

// Security: only admins may access quizzes management
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = $_SESSION['admin_quiz_msg'] ?? '';
$messageType = $_SESSION['admin_quiz_msg_type'] ?? '';
unset($_SESSION['admin_quiz_msg'], $_SESSION['admin_quiz_msg_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['quiz_id']) && $_POST['action'] === 'delete') {
    $quiz_id = (int) $_POST['quiz_id'];
    if ($quiz_id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM quizzes WHERE id = ?');
            $stmt->execute([$quiz_id]);
            $_SESSION['admin_quiz_msg'] = $stmt->rowCount() > 0 ? 'Quiz deleted.' : 'Quiz not found.';
            $_SESSION['admin_quiz_msg_type'] = $stmt->rowCount() > 0 ? 'success' : 'warning';
        } catch (PDOException $e) {
            $_SESSION['admin_quiz_msg'] = 'Delete failed. Please try again.';
            $_SESSION['admin_quiz_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['admin_quiz_msg'] = 'Invalid quiz id.';
        $_SESSION['admin_quiz_msg_type'] = 'warning';
    }
    header('Location: manage_quizzes.php');
    exit;
}

// Fetch all quizzes with creator + performance stats
$quizzes = [];
try {
    $stmt = $pdo->query("SELECT q.id, q.topic, q.difficulty, q.created_at,
                                u.username AS creator_name, u.role AS creator_role,
                                COUNT(a.id) AS total_attempts,
                                AVG(a.percentage) AS avg_score
                        FROM quizzes q
                        JOIN users u ON u.id = q.created_by
                        LEFT JOIN quiz_attempts a ON a.quiz_id = q.id
                        GROUP BY q.id, q.topic, q.difficulty, q.created_at, u.username, u.role
                        ORDER BY q.created_at DESC");
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/admin_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboards/admin_dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead">
        <div class="container px-4 px-lg-5 h-100">
            <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white font-weight-bold">Manage Quizzes</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5">View all quizzes and performance statistics</p>
                </div>
            </div>
        </div>
    </header>

    <section class="page-section" id="quizzes">
        <div class="container px-4 px-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mt-0 mb-0">All Quizzes</h2>
                <a href="../dashboards/admin_dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?> mb-4" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($quizzes)): ?>
                <p class="text-muted">No quizzes found.</p>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Topic</th>
                                <th scope="col">Difficulty</th>
                                <th scope="col">Created By</th>
                                <th scope="col">Role</th>
                                <th scope="col">Total Attempts</th>
                                <th scope="col">Average Score</th>
                                <th scope="col">Created</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $q): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($q['topic'] ?? ''); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($q['difficulty'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($q['creator_name'] ?? ''); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($q['creator_role'] ?? ''); ?></td>
                                    <td><?php echo (int)($q['total_attempts'] ?? 0); ?></td>
                                    <td><?php echo ((int)($q['total_attempts'] ?? 0)) > 0 ? number_format((float)($q['avg_score'] ?? 0), 1) . '%' : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($q['created_at'] ?? ''); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="view_quiz.php?quiz_id=<?php echo (int)($q['id'] ?? 0); ?>">View</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this quiz? This will also delete its attempt records.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="quiz_id" value="<?php echo (int)($q['id'] ?? 0); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
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
