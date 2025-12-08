<?php
// Start PHP session to track logged-in users
session_start();
// Load database connection file
require '../includes/db_connect.php';

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ../dashboards/admin_dashboard.php');
            break;
        case 'teacher':
            header('Location: ../dashboards/teacher_dashboard.php');
            break;
        case 'student':
            header('Location: ../dashboards/student_dashboard.php');
            break;
    }
    exit;
}

// Variable to store error messages
$error = '';

// Process form when user submits login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get email and password from form
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check if both fields are filled
    if (!empty($email) && !empty($password)) {
        try {
            // Search for user in database by email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Check if account is locked
                if ($user['status'] == 'locked') {
                    $error = "Your account is locked. Please contact an administrator.";
                } elseif ($password === $user['password']) {
                    // Password matches - login successful
                    // Store user info in session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect user to their dashboard based on role
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: ../dashboards/admin_dashboard.php');
                            exit;
                        case 'teacher':
                            header('Location: ../dashboards/teacher_dashboard.php');
                            exit;
                        case 'student':
                            header('Location: ../dashboards/student_dashboard.php');
                            exit;
                        default:
                            $error = "Invalid user role.";
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic HTML page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Quiz System</title>
    <!-- Load Bootstrap CSS for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load Bootstrap Icons for icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Load Creative Bootstrap theme CSS -->
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Main content section -->
    <section class="page-section">
        <div class="container px-4 px-lg-5">
            <!-- Page header -->
            <div class="row gx-4 gx-lg-5 justify-content-center">
                <div class="col-lg-8 col-xl-6 text-center">
                    <h2 class="mt-0">Quiz System</h2>
                    <hr class="divider" />
                    <p class="text-muted mb-5">Please log in to continue</p>
                </div>
            </div>
            <!-- Login form card -->
            <div class="row gx-4 gx-lg-5 justify-content-center">
                <div class="col-lg-6 col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <!-- Display error message if login fails -->
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Login form - sends data to this same page -->
                            <form method="POST" action="login.php">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email address</label>
                                    <input type="email" class="form-control" id="email" name="email" required autofocus>
                                </div>
                                <div class="mb-4">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </form>

                            <!-- Link to registration page -->
                            <p class="text-center mt-3 mb-0">
                                Don't have an account? <a href="register.php">Register</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Load Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Creative theme JavaScript -->
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>





