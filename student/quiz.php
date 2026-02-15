<?php
session_start();
require '../includes/db_connect.php';

// Only students can take the quiz
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

// Must have topic and difficulty from teacher (create quiz)
if (empty($_SESSION['quiz_topic']) || empty($_SESSION['quiz_difficulty'])) {
    header('Location: ../dashboards/student_dashboard.php');
    exit;
}

$topic = $_SESSION['quiz_topic'];
$difficulty = $_SESSION['quiz_difficulty'];

$show_result = false;
$score = 0;
$total_questions = 0;
$percentage = 0;
$error_message = '';

// Handle quiz submission: compare answers, calculate score, store attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['quiz_correct_answers'])) {
    $correct_answers = $_SESSION['quiz_correct_answers'];
    $question_ids = $_SESSION['quiz_question_ids'];
    $total_questions = count($correct_answers);
    $score = 0;
    foreach ($correct_answers as $i => $correct) {
        $qid = $question_ids[$i] ?? 0;
        $submitted = isset($_POST['answers'][$qid]) ? trim($_POST['answers'][$qid]) : '';
        if ($submitted === $correct) {
            $score++;
        }
    }
    $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;

    // Time taken in seconds (from when quiz was loaded)
    $time_taken = isset($_SESSION['quiz_start_time']) ? (time() - (int)$_SESSION['quiz_start_time']) : null;

    // Insert attempt into quiz_attempts (prepared statement)
    $ins = "INSERT INTO quiz_attempts (user_id, topic, difficulty, score, total_questions, percentage, time_taken) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($ins);
    $stmt->execute([
        (int)$_SESSION['user_id'],
        $topic,
        $difficulty,
        $score,
        $total_questions,
        $percentage,
        $time_taken
    ]);

    // Clear quiz session so refresh doesn't resubmit
    unset($_SESSION['quiz_topic'], $_SESSION['quiz_difficulty'], $_SESSION['quiz_question_ids'], $_SESSION['quiz_correct_answers'], $_SESSION['quiz_start_time']);
    $show_result = true;
}

// If showing result, skip fetch and form
if ($show_result) {
    // Result view is rendered below
} else {
// Fetch 5 random questions for this topic + difficulty (prepared statement)
$sql = "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer 
        FROM questions WHERE topic = ? AND difficulty = ? ORDER BY RAND() LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$topic, $difficulty]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Need at least 5 questions to run quiz
if (count($questions) < 5) {
    $error_message = 'Not enough questions for this topic and difficulty.';
    $questions = [];
} else {
    // Store question IDs and correct answers in session for marking later
    $_SESSION['quiz_question_ids'] = array_column($questions, 'id');
    $_SESSION['quiz_correct_answers'] = array_column($questions, 'correct_answer');
    $_SESSION['quiz_start_time'] = time(); // for time_taken when attempt is saved
}
} // end else (not showing result)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Quiz System</title>
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
                    <h1 class="text-white font-weight-bold">Quiz</h1>
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
                        <h4 class="card-title mb-3">Quiz complete</h4>
                        <p class="mb-1">You scored <strong><?php echo (int)$score; ?></strong> out of <strong><?php echo (int)$total_questions; ?></strong>.</p>
                        <p class="mb-2">Percentage: <strong><?php echo number_format($percentage, 1); ?>%</strong></p>
                        <p class="text-muted small mb-3">Your attempt has been recorded.</p>
                        <a href="../dashboards/student_dashboard.php" class="btn btn-primary">Back to dashboard</a>
                    </div>
                </div>
            <?php elseif (!empty($error_message)): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($error_message); ?></div>
                <a href="../dashboards/student_dashboard.php" class="btn btn-primary">Back to dashboard</a>
            <?php else: ?>
                <form method="post" action="quiz.php">
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
                    <button type="submit" class="btn btn-primary">Submit quiz</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>
