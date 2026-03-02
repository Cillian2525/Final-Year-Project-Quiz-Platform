<?php
session_start();
require '../includes/db_connect.php';

// Security: only students may take adaptive quizzes
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$question_ids = $_SESSION['adaptive_question_ids'] ?? [];
$topic = $_SESSION['adaptive_topic'] ?? '';
$difficulty = $_SESSION['adaptive_difficulty'] ?? '';

if (empty($question_ids) || $topic === '' || !in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
    header('Location: ../dashboards/student_dashboard.php');
    exit;
}

$show_result = false;
$score = 0;
$total_questions = 0;
$percentage = 0;
$save_ok = true;

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['adaptive_correct_answers'])) {
    $correct_answers = $_SESSION['adaptive_correct_answers'];
    $ids = $_SESSION['adaptive_question_ids'];
    $total_questions = count($correct_answers);
    $score = 0;
    $valid_choices = ['A', 'B', 'C', 'D'];

    foreach ($correct_answers as $i => $correct) {
        $qid = (int)($ids[$i] ?? 0);
        $submitted = isset($_POST['answers'][$qid]) ? strtoupper(trim((string)$_POST['answers'][$qid])) : '';
        if (in_array($submitted, $valid_choices, true) && $submitted === $correct) {
            $score++;
        }
    }
    $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;
    $time_taken = isset($_SESSION['adaptive_start_time']) ? (time() - (int)$_SESSION['adaptive_start_time']) : null;

    // Get or create adaptive quiz (quiz_id required for quiz_attempts)
    $adaptive_quiz_id = null;
    try {
        $stmt = $pdo->query("SELECT id FROM quizzes WHERE topic = 'Adaptive' LIMIT 1");
        $row = $stmt->fetch();
        if ($row) {
            $adaptive_quiz_id = (int)$row['id'];
        } else {
            // Choose a valid creator id for the Adaptive quiz row
            $creator_id = $user_id;
            try {
                $uStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                $adminRow = $uStmt->fetch();
                if ($adminRow && isset($adminRow['id'])) {
                    $creator_id = (int)$adminRow['id'];
                }
            } catch (PDOException $eInner) {
                // Ignore and fall back to current user as creator
            }
            $insertQuiz = $pdo->prepare("INSERT INTO quizzes (topic, difficulty, created_by) VALUES ('Adaptive', 'medium', ?)");
            $insertQuiz->execute([$creator_id]);
            $adaptive_quiz_id = (int)$pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        $save_ok = false;
        error_log('Adaptive quiz - failed to get/create quizzes row: ' . $e->getMessage());
    }

    if ($adaptive_quiz_id && $save_ok) {
        try {
            $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, topic, difficulty, score, total_questions, percentage, time_taken) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $adaptive_quiz_id, $topic, $difficulty, $score, $total_questions, $percentage, $time_taken]);
            $attempt_id = (int)$pdo->lastInsertId();

            // Link generated_questions to this attempt
            $update = $pdo->prepare("UPDATE generated_questions SET attempt_id = ? WHERE id = ? AND user_id = ?");
            foreach ($ids as $gq_id) {
                $update->execute([$attempt_id, (int)$gq_id, $user_id]);
            }
        } catch (PDOException $e) {
            $save_ok = false;
            error_log('Adaptive quiz - failed to save attempt or link generated_questions: ' . $e->getMessage());
        }
    }

    unset($_SESSION['adaptive_question_ids'], $_SESSION['adaptive_topic'], $_SESSION['adaptive_difficulty'], $_SESSION['adaptive_correct_answers'], $_SESSION['adaptive_start_time']);
    $show_result = true;
}

// Load generated questions from database
$questions = [];
if (!$show_result) {
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer FROM generated_questions WHERE id IN ($placeholders) AND user_id = ?");
    $stmt->execute(array_merge(array_map('intval', $question_ids), [$user_id]));
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($questions) !== 5) {
        unset($_SESSION['adaptive_question_ids'], $_SESSION['adaptive_topic'], $_SESSION['adaptive_difficulty']);
        header('Location: ../dashboards/student_dashboard.php');
        exit;
    }

    $_SESSION['adaptive_correct_answers'] = array_column($questions, 'correct_answer');
    $_SESSION['adaptive_start_time'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adaptive Quiz - Quiz System</title>
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
                    <h1 class="text-white font-weight-bold">Adaptive Quiz</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5"><?php echo htmlspecialchars($topic); ?> &ndash; <?php echo htmlspecialchars($difficulty); ?></p>
                </div>
            </div>
        </div>
    </header>
    <section class="page-section" id="quiz">
        <div class="container px-4 px-lg-5">
            <?php if ($show_result): ?>
                <div class="card mx-auto" style="max-width: 28rem;">
                    <div class="card-body text-center py-5">
                        <?php if ($save_ok): ?>
                            <div class="alert alert-success mb-4" role="alert">
                                Your attempt has been recorded.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-4" role="alert">
                                Your result could not be saved, but here is your score.
                            </div>
                        <?php endif; ?>
                        <h4 class="card-title mb-3">Quiz complete</h4>
                        <p class="mb-1">You scored <strong><?php echo (int)$score; ?></strong> out of <strong><?php echo (int)$total_questions; ?></strong>.</p>
                        <p class="mb-2">Percentage: <strong><?php echo number_format($percentage, 1); ?>%</strong></p>
                        <a href="../dashboards/student_dashboard.php" class="btn btn-primary mt-3">Back to dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="adaptive_quiz.php" id="quizForm">
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Question <?php echo $i + 1; ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($q['question_text']); ?></p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" id="q<?php echo $q['id']; ?>_a" value="A" required>
                                    <label class="form-check-label" for="q<?php echo $q['id']; ?>_a">A. <?php echo htmlspecialchars($q['option_a']); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" id="q<?php echo $q['id']; ?>_b" value="B">
                                    <label class="form-check-label" for="q<?php echo $q['id']; ?>_b">B. <?php echo htmlspecialchars($q['option_b']); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" id="q<?php echo $q['id']; ?>_c" value="C">
                                    <label class="form-check-label" for="q<?php echo $q['id']; ?>_c">C. <?php echo htmlspecialchars($q['option_c']); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" id="q<?php echo $q['id']; ?>_d" value="D">
                                    <label class="form-check-label" for="q<?php echo $q['id']; ?>_d">D. <?php echo htmlspecialchars($q['option_d']); ?></label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary" id="quizSubmitBtn">
                        Submit quiz
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
    <script>
        (function () {
            const form = document.getElementById('quizForm');
            const submitBtn = document.getElementById('quizSubmitBtn');
            if (form && submitBtn) {
                form.addEventListener('submit', function () {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
                });
            }
        })();
    </script>
</body>
</html>
