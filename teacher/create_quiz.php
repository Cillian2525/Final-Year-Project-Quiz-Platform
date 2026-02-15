<?php
session_start();
require '../includes/db_connect.php';

// Only teachers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit;
}

// Handle form submit: store topic + difficulty in session and redirect to student quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = trim($_POST['topic'] ?? '');
    $difficulty = $_POST['difficulty'] ?? '';

    $valid_difficulties = ['easy', 'medium', 'hard'];
    if ($topic !== '' && in_array($difficulty, $valid_difficulties, true)) {
        $_SESSION['quiz_topic'] = $topic;
        $_SESSION['quiz_difficulty'] = $difficulty;
        header('Location: ../student/quiz.php');
        exit;
    }
}

// Load distinct topics from questions table for dropdown
$topics = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT topic FROM questions ORDER BY topic");
    $topics = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Leave topics empty if table missing or error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Quiz System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/teacher_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead">
        <div class="container px-4 px-lg-5 h-100">
            <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white font-weight-bold">Create Quiz</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5">Choose topic and difficulty. Students will get 5 random questions.</p>
                </div>
            </div>
        </div>
    </header>

    <section class="page-section" id="create-quiz">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body p-4">
                            <form method="post" action="">
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
                                <button type="submit" class="btn btn-primary">Create quiz</button>
                                <a href="../dashboards/teacher_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>
