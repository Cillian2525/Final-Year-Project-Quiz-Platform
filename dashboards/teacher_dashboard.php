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

// Flash messages from create_quiz flow
$quiz_create_success = $_SESSION['quiz_create_success'] ?? '';
$quiz_create_error = $_SESSION['quiz_create_error'] ?? '';
unset($_SESSION['quiz_create_success'], $_SESSION['quiz_create_error']);

// Fetch distinct topics for Create Quiz modal dropdown
$topics = [];
try {
    $topicStmt = $pdo->query("SELECT DISTINCT topic FROM questions ORDER BY topic");
    $topics = $topicStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $topics = [];
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

// Teacher dashboard stats
$my_quiz_count = count($my_quizzes);
$total_submissions = 0;
foreach ($my_quizzes as $q) {
    $total_submissions += (int)($q['total_attempts'] ?? 0);
}

$students_enrolled = 0;
try {
    $s = $pdo->prepare(
        "SELECT COUNT(DISTINCT a.user_id)
         FROM quiz_attempts a
         INNER JOIN quizzes q ON q.id = a.quiz_id
         WHERE q.created_by = ?"
    );
    $s->execute([(int)$_SESSION['user_id']]);
    $students_enrolled = (int)$s->fetchColumn();
} catch (PDOException $e) {
    $students_enrolled = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic HTML page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - IntelliQuiz</title>
    <!-- Load Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Load Creative Bootstrap theme CSS -->
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
    <!-- Top navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2" href="teacher_dashboard.php">
                <img src="../assets/intelliquiz-icon.svg" alt="IntelliQuiz" width="28" height="28" style="display:block;">
                <span>IntelliQuiz</span>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Hero section with background image (masthead) -->
    <header class="masthead" style="padding-top: 5rem; padding-bottom: 1rem; min-height: 0;">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 align-items-center justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="text-white fw-bold h2 mb-1">Teacher Dashboard</h1>
                    <p class="text-white-75 mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
            </div>

            <section class="py-2" id="stats">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-12 col-lg-10">
                        <div class="card border-0 rounded-4 shadow-sm py-2 px-2 mb-2">
                            <div class="row g-0">
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-journal-text fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$my_quiz_count; ?></h3>
                                        <p class="text-muted mb-0 small">My Quizzes</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-people fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$students_enrolled; ?></h3>
                                        <p class="text-muted mb-0 small">Students</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-clipboard-check fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$total_submissions; ?></h3>
                                        <p class="text-muted mb-0 small">Submissions</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-2" id="actions">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-12 col-lg-10">
                        <?php if (!empty($quiz_create_success)): ?>
                            <div class="alert alert-success mb-2" role="alert">
                                <?php echo $quiz_create_success; ?>
                            </div>
                        <?php endif; ?>
                        <div class="row g-2 g-lg-3">
                            <div class="col-lg-6 col-md-6">
                                <div class="card h-100 border-0 rounded-4 shadow-sm">
                                    <div class="card-body d-flex flex-column text-center p-2">
                                        <i class="bi-plus-circle fs-6 text-primary mb-1"></i>
                                        <h4 class="card-title h6 mb-1">Create Quiz</h4>
                                        <p class="card-text text-muted small mb-1">Start a new quiz</p>
                                        <button type="button" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center" data-bs-toggle="modal" data-bs-target="#createQuizModal">
                                            Create quiz
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6">
                                <div class="card h-100 border-0 rounded-4 shadow-sm">
                                    <div class="card-body d-flex flex-column text-center p-2">
                                        <i class="bi-list-ul fs-6 text-primary mb-1"></i>
                                        <h4 class="card-title h6 mb-1">Manage Quizzes</h4>
                                        <p class="card-text text-muted small mb-1">View and edit</p>
                                        <a href="../teacher/manage_quizzes.php" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center">View quizzes</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-9 mx-auto">
                                <div class="card h-100 border-0 rounded-4 shadow-sm">
                                    <div class="card-body d-flex flex-column text-center p-2">
                                        <i class="bi-graph-up fs-6 text-primary mb-1"></i>
                                        <h4 class="card-title h6 mb-1">Quiz Performance</h4>
                                        <p class="card-text text-muted small mb-1">Attempts and averages</p>
                                        <button type="button" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center" data-bs-toggle="modal" data-bs-target="#performanceModal">
                                            View performance
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </header>

    <!-- Create Quiz modal -->
    <div class="modal fade" id="createQuizModal" tabindex="-1" aria-labelledby="createQuizModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createQuizModalLabel">Create Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($quiz_create_error)): ?>
                        <div class="alert alert-danger mb-3" role="alert">
                            <?php echo htmlspecialchars($quiz_create_error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="../teacher/create_quiz.php" id="createQuizForm">
                        <div class="mb-3">
                            <label for="topic" class="form-label">Topic</label>
                            <select class="form-select" id="topic" name="topic" required>
                                <option value="">-- Select topic --</option>
                                <?php foreach ($topics as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                <option value="">-- Select difficulty --</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="createQuizSubmitBtn">Create quiz</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance modal -->
    <div class="modal fade" id="performanceModal" tabindex="-1" aria-labelledby="performanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="performanceModalLabel">Quiz Performance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($my_quizzes)): ?>
                        <p class="text-muted text-center mb-0">You have not created any quizzes yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
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
            </div>
        </div>
    </div>

    <!-- Load Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Creative theme JavaScript -->
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
    <script>
        (function () {
            function cleanupStuckModalBackdrop() {
                var anyShownModal = document.querySelector('.modal.show');
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (!anyShownModal && (backdrops.length > 0 || document.body.classList.contains('modal-open'))) {
                    backdrops.forEach(function (b) { b.parentNode && b.parentNode.removeChild(b); });
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('padding-right');
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupStuckModalBackdrop);
            } else {
                cleanupStuckModalBackdrop();
            }

            const form = document.getElementById('createQuizForm');
            const submitBtn = document.getElementById('createQuizSubmitBtn');
            if (form && submitBtn) {
                form.addEventListener('submit', function () {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creating...';
                });
            }
        })();
    </script>
</body>
</html>





