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

$adaptive_topics = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT topic FROM questions ORDER BY topic");
    $adaptive_topics = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $adaptive_topics = [];
}

$default_adaptive_topic = null;
try {
    $stmt = $pdo->prepare("SELECT topic FROM quiz_attempts WHERE user_id = ? ORDER BY attempt_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['topic']) && trim((string)$row['topic']) !== '') {
        $default_adaptive_topic = (string)$row['topic'];
    }
} catch (PDOException $e) {
    $default_adaptive_topic = null;
}

if ($default_adaptive_topic === null && !empty($adaptive_topics)) {
    $default_adaptive_topic = (string)$adaptive_topics[0];
}

$suggested_adaptive_difficulty = 'medium';
if ($default_adaptive_topic !== null) {
    $topicAverage = null;
    $topicLast = null;
    try {
        $stmt = $pdo->prepare("SELECT AVG(percentage) FROM quiz_attempts WHERE user_id = ? AND topic = ?");
        $stmt->execute([$user_id, $default_adaptive_topic]);
        $avg = $stmt->fetchColumn();
        $topicAverage = $avg !== null ? (float)$avg : null;
    } catch (PDOException $e) {
        $topicAverage = null;
    }

    try {
        $stmt = $pdo->prepare("SELECT percentage FROM quiz_attempts WHERE user_id = ? AND topic = ? ORDER BY attempt_date DESC LIMIT 1");
        $stmt->execute([$user_id, $default_adaptive_topic]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $topicLast = $row ? (float)$row['percentage'] : null;
    } catch (PDOException $e) {
        $topicLast = null;
    }

    $scoreSignal = $topicLast !== null ? $topicLast : $topicAverage;
    if ($scoreSignal !== null) {
        if ($scoreSignal >= 80) {
            $suggested_adaptive_difficulty = 'hard';
        } elseif ($scoreSignal < 50) {
            $suggested_adaptive_difficulty = 'easy';
        } else {
            $suggested_adaptive_difficulty = 'medium';
        }
    }
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

// Flash message for adaptive fallback
$adaptive_fallback_msg = $_SESSION['adaptive_fallback_msg'] ?? '';
unset($_SESSION['adaptive_fallback_msg']);

// Handle starting an adaptive quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_adaptive_quiz'])) {
    // Prevent duplicate generation on refresh: if we already have pending adaptive questions, redirect to quiz
    if (!empty($_SESSION['adaptive_question_ids']) && count($_SESSION['adaptive_question_ids']) === 5) {
        header('Location: ../student/adaptive_quiz.php');
        exit;
    }

    $performanceSummary = [
        'average_score' => $average_score,
        'last_score' => $last_score,
    ];

    // choose topic: optionally from POST (Adaptive button next to a quiz), if not use last attempted topic
    $adaptiveTopic = trim((string)($_POST['adaptive_topic'] ?? ''));
    if ($adaptiveTopic === '' || strlen($adaptiveTopic) > 100) {
        $adaptiveTopic = null;
    }

    if ($adaptiveTopic === null) {
        try {
            $stmt = $pdo->prepare("SELECT topic FROM quiz_attempts WHERE user_id = ? ORDER BY attempt_date DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();
            if ($row && isset($row['topic'])) {
                $adaptiveTopic = (string)$row['topic'];
            }
        } catch (PDOException $e) {}
    }

    if ($adaptiveTopic === null) {
        try {
            $stmt = $pdo->query("SELECT topic FROM questions ORDER BY topic LIMIT 1");
            $fallback = $stmt->fetch();
            if ($fallback && isset($fallback['topic'])) {
                $adaptiveTopic = (string)$fallback['topic'];
            }
        } catch (PDOException $e) {}
    }

    // Choose difficulty based on performance for this topic (withsimple thresholds)
    $adaptiveDifficulty = 'medium';
    $topicAverage = null;
    $topicLast = null;
    try {
        $stmt = $pdo->prepare("SELECT AVG(percentage) FROM quiz_attempts WHERE user_id = ? AND topic = ?");
        $stmt->execute([$user_id, $adaptiveTopic]);
        $avg = $stmt->fetchColumn();
        $topicAverage = $avg !== null ? (float)$avg : null;
    } catch (PDOException $e) {}
    try {
        $stmt = $pdo->prepare("SELECT percentage FROM quiz_attempts WHERE user_id = ? AND topic = ? ORDER BY attempt_date DESC LIMIT 1");
        $stmt->execute([$user_id, $adaptiveTopic]);
        $row = $stmt->fetch();
        $topicLast = $row ? (float)$row['percentage'] : null;
    } catch (PDOException $e) {}

    $scoreSignal = $topicLast !== null ? $topicLast : $topicAverage;
    if ($scoreSignal !== null) {
        if ($scoreSignal >= 80) {
            $adaptiveDifficulty = 'hard';
        } elseif ($scoreSignal < 50) {
            $adaptiveDifficulty = 'easy';
        } else {
            $adaptiveDifficulty = 'medium';
        }
    }

    $masteryWindowAttempts = 20;
    $masteryThresholdCorrect = 3;
    $masteredQuestionStems = [];
    $adaptiveQuizId = null;
    try {
        $stmt = $pdo->query("SELECT id FROM quizzes WHERE topic = 'Adaptive' LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            $adaptiveQuizId = (int)$row['id'];
        }
    } catch (PDOException $e) {
        $adaptiveQuizId = null;
    }

    if ($adaptiveQuizId !== null) {
        $recentAttemptIds = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND topic = ? ORDER BY attempt_date DESC LIMIT {$masteryWindowAttempts}"
            );
            $stmt->execute([$user_id, $adaptiveQuizId, $adaptiveTopic]);
            $recentAttemptIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            $recentAttemptIds = [];
        }

        if (!empty($recentAttemptIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($recentAttemptIds), '?'));
                $sql =
                    "SELECT gqa.question_hash, COUNT(*) AS correct_count "
                    . "FROM generated_question_attempts gqa "
                    . "WHERE gqa.user_id = ? AND gqa.is_correct = 1 AND gqa.attempt_id IN ($placeholders) "
                    . "GROUP BY gqa.question_hash "
                    . "HAVING COUNT(*) >= ? "
                    . "ORDER BY correct_count DESC";
                $params = array_merge([$user_id], $recentAttemptIds, [$masteryThresholdCorrect]);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $masteredHashes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($masteredHashes)) {
                    $hashPlaceholders = implode(',', array_fill(0, count($masteredHashes), '?'));
                    $sql2 =
                        "SELECT DISTINCT gq.question_text "
                        . "FROM generated_question_attempts gqa "
                        . "JOIN generated_questions gq ON gq.id = gqa.generated_question_id "
                        . "WHERE gqa.user_id = ? AND gqa.question_hash IN ($hashPlaceholders) "
                        . "ORDER BY gq.created_at DESC LIMIT 10";
                    $stmt2 = $pdo->prepare($sql2);
                    $stmt2->execute(array_merge([$user_id], $masteredHashes));
                    $masteredQuestionStems = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                }
            } catch (PDOException $e) {
                $masteredQuestionStems = [];
            }
        }
    }

    $performanceSummary['mastered_question_stems'] = $masteredQuestionStems;

    if ($adaptiveTopic === null || $adaptiveDifficulty === null) {
        header('Location: student_dashboard.php');
        exit;
    }

    $generated = generateAdaptiveQuestions($adaptiveTopic, $adaptiveDifficulty, $performanceSummary);

    // API failed: fallback to static quiz if one exists for this topic/difficulty
    if (empty($generated)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE topic = ? AND difficulty = ? AND topic != 'Adaptive' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$adaptiveTopic, $adaptiveDifficulty]);
            $staticQuiz = $stmt->fetch();
            if ($staticQuiz) {
                header('Location: ../student/quiz.php?quiz_id=' . (int)$staticQuiz['id']);
                exit;
            }
        } catch (PDOException $e) {}
        $_SESSION['adaptive_fallback_msg'] = 'Adaptive quiz unavailable. Please try a static quiz from Browse quizzes.';
        header('Location: student_dashboard.php');
        exit;
    }

    // Validate structure before saving: ensure each question has required fields
    $validKeys = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];
    foreach ($generated as $q) {
        if (!is_array($q)) {
            header('Location: student_dashboard.php');
            exit;
        }
        foreach ($validKeys as $k) {
            if (!isset($q[$k]) || !is_string($q[$k]) || trim($q[$k]) === '') {
                header('Location: student_dashboard.php');
                exit;
            }
        }
        if (!in_array(strtoupper(trim($q['correct_answer'])), ['A', 'B', 'C', 'D'], true)) {
            header('Location: student_dashboard.php');
            exit;
        }
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
    <title>Student Dashboard - IntelliQuiz</title>
    <!-- Load Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Load Creative Bootstrap theme CSS -->
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">

    <!-- Modern font for a cleaner dashboard look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* modern font stack */
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }

        /* dark overlay on the masthead background image */
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
            <a class="navbar-brand" href="student_dashboard.php">IntelliQuiz</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Hero section with background image (masthead) -->
    <header class="masthead" style="padding-top: 5rem; padding-bottom: 0.75rem; min-height: 0;">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white fw-bold h2 mb-1">Student Dashboard</h1>
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
            </div>
        </div>

    <!-- Statistics section with blue background -->
    <section class="py-2" id="stats">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 justify-content-center">

                <div class="col-12 col-lg-10">
                    <div class="card border-0 rounded-4 shadow-sm py-2 px-2 mb-2" style="backdrop-filter: none;">

                        <div class="row g-0">

                <!-- Total quizzes taken -->
                <div class="col-4 text-center">
                    <div>
                        <div class="mb-1"><i class="bi-journal-text fs-6 text-primary"></i></div>
                        <h3 class="h6 mb-0 text-dark"><?php echo $total_quizzes_taken; ?></h3>
                        <p class="text-muted mb-0 small">Taken</p>
                    </div>
                </div>
                <!-- Average score -->
                <div class="col-4 text-center">
                    <div>
                        <div class="mb-1"><i class="bi-graph-up fs-6 text-primary"></i></div>
                        <h3 class="h6 mb-0 text-dark"><?php echo $total_quizzes_taken > 0 ? $average_score . '%' : '0'; ?></h3>
                        <p class="text-muted mb-0 small">Average</p>
                    </div>
                </div>
                <!-- Last score -->
                <div class="col-4 text-center">
                    <div>
                        <div class="mb-1"><i class="bi-check-circle fs-6 text-primary"></i></div>
                        <h3 class="h6 mb-0 text-dark"><?php echo $last_score !== null ? number_format($last_score, 1) . '%' : '—'; ?></h3>
                        <p class="text-muted mb-0 small">Last</p>
                    </div>
                </div>

                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Quick action cards section -->
    <section class="py-2" id="actions">
        <div class="container px-4 px-lg-5">
            <?php if (!empty($adaptive_fallback_msg)): ?>
                <div class="alert alert-warning mb-4" role="alert"><?php echo htmlspecialchars($adaptive_fallback_msg); ?></div>
            <?php endif; ?>
            <div class="row gx-4 gx-lg-5 justify-content-center">
                <div class="col-12 col-lg-10">
                    <div class="row g-2 g-lg-3">
                <!-- Take Quiz card -->
                <div class="col-lg-6 col-md-6">
                    <div class="card h-100 border-0 rounded-4 shadow-sm">
                        <div class="card-body d-flex flex-column text-center p-2">
                            <i class="bi-book fs-6 text-primary mb-1"></i>
                            <h4 class="card-title h6 mb-1">Take Quiz</h4>
                            <p class="card-text text-muted small mb-1">Browse quizzes</p>
                            <button type="button" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center" data-bs-toggle="modal" data-bs-target="#availableQuizzesModal">
                                Browse quizzes
                            </button>
                        </div>
                    </div>
                </div>
                <!-- View Results card -->
                <div class="col-lg-6 col-md-6">
                    <div class="card h-100 border-0 rounded-4 shadow-sm">
                        <div class="card-body d-flex flex-column text-center p-2">
                            <i class="bi-bar-chart fs-6 text-primary mb-1"></i>
                            <h4 class="card-title h6 mb-1">View Results</h4>
                            <p class="card-text text-muted small mb-1">Your attempts</p>
                            <button type="button" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center" data-bs-toggle="modal" data-bs-target="#resultsModal">
                                My results
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Adaptive Quiz card -->
                <div class="col-12 col-lg-9 mx-auto">
                    <div class="card h-100 border-0 rounded-4 shadow-sm">
                        <div class="card-body d-flex flex-column text-center p-2">
                            <i class="bi-stars fs-6 text-primary mb-1"></i>
                            <h4 class="card-title h6 mb-1">Adaptive Quiz</h4>
                            <p class="card-text text-muted small mb-1">Personalised quiz</p>
                            <p class="mb-0 small">
                                Current difficulty:
                                <span class="badge bg-secondary text-capitalize"><?php echo htmlspecialchars($suggested_adaptive_difficulty); ?></span>
                            </p>
                            <form method="post" class="mt-0">
                                <input type="hidden" name="start_adaptive_quiz" value="1">
                                <div class="row g-2 justify-content-center">
                                    <div class="col-12 col-md-5">
                                        <select name="adaptive_topic" class="form-select form-select-sm">
                                            <option value="">Auto (recommended)</option>
                                            <?php foreach ($adaptive_topics as $t): ?>
                                                <option value="<?php echo htmlspecialchars((string)$t); ?>"><?php echo htmlspecialchars((string)$t); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill">Take Adaptive Quiz</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    </header>

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
        })();
    </script>
</body>
</html>




