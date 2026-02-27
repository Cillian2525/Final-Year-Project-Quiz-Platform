<?php
// Start session to check user login status
session_start();
// Load database connection
require '../includes/db_connect.php';
require_once '../includes/llm_service.php';

// Security check: Only allow student users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

// Current logged-in student id
$user_id = (int)$_SESSION['user_id'];

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

// Real stats for logged-in student (prepared statements)
$total_quizzes_taken = 0;
$average_score = 0;
$last_score = null;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_quizzes_taken = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}
try {
    $stmt = $pdo->prepare("SELECT AVG(percentage) FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $avg = $stmt->fetchColumn();
    $average_score = $avg !== null ? round((float)$avg, 1) : 0;
} catch (PDOException $e) {}
try {
    $stmt = $pdo->prepare("SELECT percentage FROM quiz_attempts WHERE user_id = ? ORDER BY attempt_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    $last_score = $row ? (float)$row['percentage'] : null;
} catch (PDOException $e) {}

// Load results history for modal (same logic as results page)
$attempts = [];
try {
    $stmt = $pdo->prepare("SELECT a.id, COALESCE(q.topic, a.topic) AS topic, COALESCE(q.difficulty, a.difficulty) AS difficulty,
                                a.score, a.total_questions, a.percentage, a.attempt_date
                        FROM quiz_attempts a
                        LEFT JOIN quizzes q ON a.quiz_id = q.id
                        WHERE a.user_id = ?
                        ORDER BY a.attempt_date DESC");
    $stmt->execute([$user_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $attempts = [];
}

// Handle starting an adaptive quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_adaptive_quiz'])) {
    $performanceSummary = [
        'average_score' => $average_score,
        'last_score' => $last_score,
    ];

    // Determine topic and difficulty: use last attempted quiz if available, otherwise fall back to first topic
    $adaptiveTopic = null;
    $adaptiveDifficulty = null;
    try {
        $stmt = $pdo->prepare("SELECT topic, difficulty FROM quiz_attempts WHERE user_id = ? ORDER BY attempt_date DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        if ($row) {
            $adaptiveTopic = $row['topic'];
            $adaptiveDifficulty = $row['difficulty'];
        }
    } catch (PDOException $e) {}

    if ($adaptiveTopic === null || $adaptiveDifficulty === null) {
        try {
            $stmt = $pdo->query("SELECT topic, difficulty FROM questions ORDER BY topic, difficulty LIMIT 1");
            $fallback = $stmt->fetch();
            if ($fallback) {
                $adaptiveTopic = $fallback['topic'];
                $adaptiveDifficulty = $fallback['difficulty'];
            }
        } catch (PDOException $e) {}
    }

    if ($adaptiveTopic === null || $adaptiveDifficulty === null) {
        header('Location: student_dashboard.php');
        exit;
    }

    $generated = generateAdaptiveQuestions($adaptiveTopic, $adaptiveDifficulty, $performanceSummary);
    if (empty($generated)) {
        header('Location: student_dashboard.php');
        exit;
    }

    $questionIds = [];
    try {
        $insert = $pdo->prepare(
            "INSERT INTO generated_questions (user_id, topic, difficulty, question_text, option_a, option_b, option_c, option_d, correct_answer)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($generated as $q) {
            $insert->execute([
                $user_id,
                $adaptiveTopic,
                $adaptiveDifficulty,
                $q['question'],
                $q['option_a'],
                $q['option_b'],
                $q['option_c'],
                $q['option_d'],
                $q['correct_answer'],
            ]);
            $questionIds[] = (int)$pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        header('Location: student_dashboard.php');
        exit;
    }

    if (empty($questionIds)) {
        header('Location: student_dashboard.php');
        exit;
    }

    $_SESSION['adaptive_question_ids'] = $questionIds;
    $_SESSION['adaptive_topic'] = $adaptiveTopic;
    $_SESSION['adaptive_difficulty'] = $adaptiveDifficulty;

    header('Location: ../student/adaptive_quiz.php');
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

                <!-- Total quizzes taken -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-journal-text fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $total_quizzes_taken; ?></h3>
                        <p class="text-white-75 mb-0">Quizzes Taken</p>
                    </div>
                </div>
                <!-- Average score -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-graph-up fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $total_quizzes_taken > 0 ? $average_score . '%' : '0'; ?></h3>
                        <p class="text-white-75 mb-0">Average Score</p>
                    </div>
                </div>
                <!-- Last score -->
                <div class="col-lg-4 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-check-circle fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $last_score !== null ? number_format($last_score, 1) . '%' : '—'; ?></h3>
                        <p class="text-white-75 mb-0">Last Score</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick action cards section -->
    <section class="page-section" id="actions">
        <div class="container px-4 px-lg-5">
            <h2 class="text-center mt-0 mb-4">Quick Actions</h2>
            <div class="row gx-4 gx-lg-5">
                <!-- Take Quiz card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column text-center">
                            <i class="bi-book fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Take Quiz</h4>
                            <p class="card-text text-muted">Browse available quizzes</p>
                            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#availableQuizzesModal">
                                Browse quizzes
                            </button>
                        </div>
                    </div>
                </div>
                <!-- View Results card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column text-center">
                            <i class="bi-bar-chart fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">View Results</h4>
                            <p class="card-text text-muted">Check your progress</p>
                            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#resultsModal">
                                My results
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Adaptive Quiz card -->
                <div class="col-lg-12 col-md-12 mb-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column text-center">
                            <i class="bi-stars fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Adaptive Quiz</h4>
                            <p class="card-text text-muted">Get a personalised quiz based on your performance</p>
                            <form method="post" class="mt-3">
                                <input type="hidden" name="start_adaptive_quiz" value="1">
                                <button type="submit" class="btn btn-primary">Take Adaptive Quiz</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Available quizzes modal -->
    <div class="modal fade" id="availableQuizzesModal" tabindex="-1" aria-labelledby="availableQuizzesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="availableQuizzesModalLabel">Available Quizzes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($available_quizzes)): ?>
                        <p class="text-muted text-center mb-0">No quizzes are available yet. Please check back later.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table table-striped align-middle mb-0">
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
            </div>
        </div>
    </div>

    <!-- Results history modal -->
    <div class="modal fade" id="resultsModal" tabindex="-1" aria-labelledby="resultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resultsModalLabel">My Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($attempts)): ?>
                        <p class="text-muted text-center mb-0">You have not taken any quizzes yet.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table table-striped align-middle mb-0">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Load Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Creative theme JavaScript -->
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>





