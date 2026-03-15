<?php
session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: dashboards/admin_dashboard.php');
            break;
        case 'teacher':
            header('Location: dashboards/teacher_dashboard.php');
            break;
        case 'student':
            header('Location: dashboards/student_dashboard.php');
            break;
        default:
            header('Location: auth/login.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntelliQuiz</title>
    <link rel="icon" type="image/svg+xml" href="assets/intelliquiz-icon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        header.masthead { position: relative; }
        .landing-title { color: #0b1220 !important; }
        .landing-brand { color: #0b1220 !important; }
        .landing-hero { margin-top: 0; }
        header.masthead {
            background: url("assets/Site Background.png");
            background-size: cover;
            background-position: center;
        }
        header.masthead::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.35);
            pointer-events: none;
        }
        header.masthead > .container { position: relative; z-index: 1; }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="assets/intelliquiz-icon.svg" alt="IntelliQuiz" width="28" height="28" style="display:block;">
                <span class="landing-brand">IntelliQuiz</span>
            </a>
        </div>
    </nav>

    <header class="masthead" style="padding-top: 6rem; padding-bottom: 2rem; min-height: 100vh;">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5 justify-content-center text-center landing-hero">
                <div class="col-12 col-lg-9 col-xl-8">
                    <div class="text-dark small mb-2">Adaptive quizzes for Computer Science learning</div>
                    <h1 class="landing-title fw-bold display-6 mb-2">IntelliQuiz</h1>
                    <p class="text-muted mb-3">
                        A multi-role quiz platform with static quizzes and adaptive difficulty tailored to your needs.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a class="btn btn-primary px-4 rounded-pill" href="auth/login.php">Get Started</a>
                        <a class="btn btn-outline-dark px-4 rounded-pill" href="auth/register.php">Create Account</a>
                    </div>
                </div>
            </div>

            <div class="row gx-4 gx-lg-5 justify-content-center mt-4">
                <div class="col-12 col-lg-10 col-xl-8">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </div>
                                <div class="text-start">
                                    <div class="fw-semibold">What you can do</div>
                                    <div class="text-muted small">Students, teachers, and administrators</div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12 col-md-4">
                                    <div class="border rounded-3 p-2 h-100">
                                        <div class="fw-semibold small">Students</div>
                                        <div class="text-muted small">Take quizzes, view results, practise topics.</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="border rounded-3 p-2 h-100">
                                        <div class="fw-semibold small">Teachers</div>
                                        <div class="text-muted small">Create and manage quizzes, monitor engagement.</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="border rounded-3 p-2 h-100">
                                        <div class="fw-semibold small">Admins</div>
                                        <div class="text-muted small">Manage users and oversee platform activity.</div>
                                    </div>
                                </div>
                            </div>
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





