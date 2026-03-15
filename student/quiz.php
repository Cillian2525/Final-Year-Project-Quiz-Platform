<?php
session_start();
require '../includes/db_connect.php';

// Security: only students may take quizzes
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

// Quiz must be started with a valid quiz_id (from GET on first load, POST on submit)
$valid_difficulties = ['easy', 'medium', 'hard'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
} else {
    $quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
}
if ($quiz_id <= 0) {
    header('Location: ../dashboards/student_dashboard.php');
    exit;
}

// Fetch quiz record from quizzes table
try {
    $stmt = $pdo->prepare("SELECT topic, difficulty FROM quizzes WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch();
} catch (PDOException $e) {
    $quiz = false;
}

if (!$quiz) {
    header('Location: ../dashboards/student_dashboard.php');
    exit;
}

$topic = trim((string)$quiz['topic']);
$difficulty = $quiz['difficulty'];
if ($topic === '' || strlen($topic) > 100 || !in_array($difficulty, $valid_difficulties, true)) {
    header('Location: ../dashboards/student_dashboard.php');
    exit;
}

$show_result = false;
$score = 0;
$total_questions = 0;
$percentage = 0;
$error_message = '';
$save_ok = true;

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['quiz_correct_answers'])) {
    $correct_answers = $_SESSION['quiz_correct_answers'];
    $question_ids = $_SESSION['quiz_question_ids'];
    $total_questions = count($correct_answers);
    $score = 0;
    $valid_choices = ['A', 'B', 'C', 'D'];
    foreach ($correct_answers as $i => $correct) {
        $qid = (int)($question_ids[$i] ?? 0);
        $submitted = isset($_POST['answers'][$qid]) ? strtoupper(trim((string)$_POST['answers'][$qid])) : '';
        if (in_array($submitted, $valid_choices, true) && $submitted === $correct) {
            $score++;
        }
    }
    $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;
    $time_taken = isset($_SESSION['quiz_start_time']) ? (time() - (int)$_SESSION['quiz_start_time']) : null;

    $save_ok = false;
    try {
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, topic, difficulty, score, total_questions, percentage, time_taken) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([(int)$_SESSION['user_id'], $quiz_id, $topic, $difficulty, $score, $total_questions, $percentage, $time_taken]);
        $save_ok = true;
    } catch (PDOException $e) {}

    unset($_SESSION['quiz_question_ids'], $_SESSION['quiz_correct_answers'], $_SESSION['quiz_start_time']);
    $show_result = true;
}

if (!$show_result) {
    $sql = "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer FROM questions WHERE topic = ? AND difficulty = ? ORDER BY RAND() LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$topic, $difficulty]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($questions) < 5) {
        $error_message = 'Not enough questions for this topic and difficulty.';
        $questions = [];
    } else {
        $_SESSION['quiz_question_ids'] = array_column($questions, 'id');
        $_SESSION['quiz_correct_answers'] = array_column($questions, 'correct_answer');
        $_SESSION['quiz_start_time'] = time();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - IntelliQuiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/student_dashboard.php">IntelliQuiz</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <header class="masthead" style="padding-top: 5rem; padding-bottom: 1rem; min-height: 0;">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="text-white fw-bold h3 mb-1">Quiz</h1>
                    <p class="text-white-75 mb-2 small"><?php echo htmlspecialchars($topic); ?> &ndash; <?php echo htmlspecialchars($difficulty); ?></p>
                </div>
            </div>

            <div class="row gx-4 gx-lg-5 justify-content-center" id="quiz">
                <div class="col-12 col-lg-8 col-xl-7">
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
            <?php elseif (!empty($error_message)): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($error_message); ?></div>
                <a href="../dashboards/student_dashboard.php" class="btn btn-primary">Back to dashboard</a>
            <?php else: ?>
                <form method="post" action="quiz.php" id="quizForm">
                    <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz_id; ?>">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-white-75 small" id="quizProgress"></div>
                        <div class="text-white-75 small" id="quizStepHint"></div>
                    </div>

                    <div class="alert alert-warning py-2 d-none" id="quizStepAlert" role="alert"></div>
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="card mb-2 quiz-step" data-step="<?php echo (int)$i; ?>">
                            <div class="card-body py-2">
                                <h5 class="card-title h6 mb-2">Question <?php echo $i + 1; ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($q['question_text']); ?></p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" id="q<?php echo $q['id']; ?>_a" value="A">
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

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-light" id="quizBackBtn">Back</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light" id="quizNextBtn">Next</button>
                            <button type="submit" class="btn btn-primary d-none" id="quizSubmitBtn">Submit quiz</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
    <script>
        (function () {
            const form = document.getElementById('quizForm');
            const submitBtn = document.getElementById('quizSubmitBtn');
            const backBtn = document.getElementById('quizBackBtn');
            const nextBtn = document.getElementById('quizNextBtn');
            const progressEl = document.getElementById('quizProgress');
            const hintEl = document.getElementById('quizStepHint');
            const alertEl = document.getElementById('quizStepAlert');
            const steps = Array.from(document.querySelectorAll('.quiz-step'));
            let currentStep = 0;

            function hideAlert() {
                if (!alertEl) return;
                alertEl.classList.add('d-none');
                alertEl.textContent = '';
            }

            function showAlert(msg) {
                if (!alertEl) return;
                alertEl.textContent = msg;
                alertEl.classList.remove('d-none');
            }

            function setRequiredForStep(stepEl) {
                steps.forEach(s => {
                    const radios = s.querySelectorAll('input[type="radio"]');
                    radios.forEach(r => r.required = false);
                });
                if (!stepEl) return;
                const radios = stepEl.querySelectorAll('input[type="radio"]');
                if (radios.length > 0) {
                    radios[0].required = true;
                }
            }

            function isStepAnswered(stepEl) {
                if (!stepEl) return false;
                const radios = stepEl.querySelectorAll('input[type="radio"]');
                return Array.from(radios).some(r => r.checked);
            }

            function render() {
                if (!steps.length) return;
                hideAlert();
                steps.forEach((s, idx) => {
                    s.classList.toggle('d-none', idx !== currentStep);
                });

                const total = steps.length;
                if (progressEl) progressEl.textContent = `Question ${currentStep + 1} of ${total}`;
                if (hintEl) hintEl.textContent = 'Select an answer, then click Next';

                if (backBtn) backBtn.disabled = currentStep === 0;
                const isLast = currentStep === total - 1;
                if (nextBtn) nextBtn.classList.toggle('d-none', isLast);
                if (submitBtn) submitBtn.classList.toggle('d-none', !isLast);

                setRequiredForStep(steps[currentStep]);
            }

            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    if (currentStep > 0) {
                        currentStep--;
                        render();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    const stepEl = steps[currentStep];
                    if (!isStepAnswered(stepEl)) {
                        showAlert('Please select an answer before continuing.');
                        return;
                    }
                    if (currentStep < steps.length - 1) {
                        currentStep++;
                        render();
                    }
                });
            }

            if (form && submitBtn) {
                form.addEventListener('submit', function () {
                    const unanswered = steps.find(s => !isStepAnswered(s));
                    if (unanswered) {
                        const idx = steps.indexOf(unanswered);
                        if (idx >= 0) {
                            currentStep = idx;
                            render();
                        }
                        showAlert('Please answer all questions before submitting.');
                        return;
                    }
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
                });
            }

            render();
        })();
    </script>
</body>
</html>
