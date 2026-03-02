<?php
// Start session to check user login status
session_start();
// Load database connection
require '../includes/db_connect.php';

// Security check: Only allow admin users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize count variables
$studentCount = 0;
$teacherCount = 0;
$lockedCount = 0;
$totalUsers = 0;
$totalQuizzes = 0;
$totalAttempts = 0;

// Get statistics from database (real values)
try {
    $studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $teacherCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $lockedCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'locked'")->fetchColumn();
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalQuizzes = (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
    $totalAttempts = (int) $pdo->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn();
} catch (PDOException $e) {
    // If database query fails, counts stay at zero
}

// Handle lock/unlock from Manage Users modal (POST then redirect to avoid resubmit)
$usersModalMessage = '';
$usersModalMessageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $targetId = (int) $_POST['user_id'];
    $action = $_POST['action'] === 'lock' ? 'locked' : 'active';
    $currentAdminId = (int) $_SESSION['user_id'];

    if ($targetId === $currentAdminId) {
        $usersModalMessage = 'You cannot lock or unlock your own account.';
        $usersModalMessageType = 'warning';
    } else {
        try {
            if ($action === 'locked') {
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$targetId]);
                $target = $stmt->fetch();
                if ($target && $target['role'] === 'admin') {
                    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
                    if ($count <= 1) {
                        $usersModalMessage = 'Cannot lock the last active admin.';
                        $usersModalMessageType = 'warning';
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = ?");
                        $stmt->execute([$targetId]);
                        $usersModalMessage = 'User locked.';
                        $usersModalMessageType = 'success';
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = ?");
                    $stmt->execute([$targetId]);
                    $usersModalMessage = 'User locked.';
                    $usersModalMessageType = 'success';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$targetId]);
                $usersModalMessage = 'User unlocked.';
                $usersModalMessageType = 'success';
            }
        } catch (PDOException $e) {
            $usersModalMessage = 'Update failed. Please try again.';
            $usersModalMessageType = 'danger';
        }
    }
    $_SESSION['admin_users_modal_msg'] = $usersModalMessage;
    $_SESSION['admin_users_modal_type'] = $usersModalMessageType;
    header('Location: admin_dashboard.php?open_users=1');
    exit;
}

// Flash message for users modal (after redirect)
if (isset($_SESSION['admin_users_modal_msg'])) {
    $usersModalMessage = $_SESSION['admin_users_modal_msg'];
    $usersModalMessageType = $_SESSION['admin_users_modal_type'] ?? 'info';
    unset($_SESSION['admin_users_modal_msg'], $_SESSION['admin_users_modal_type']);
}

// Fetch all users for Manage Users modal
$adminUsersList = [];
try {
    $stmt = $pdo->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY role, username");
    $adminUsersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $adminUsersList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic HTML page setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quiz System</title>
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
            <a class="navbar-brand" href="admin_dashboard.php">Quiz System</a>
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
                    <h1 class="text-white font-weight-bold">Admin Dashboard</h1>
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

                <!-- Display student count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-people fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $studentCount; ?></h3>
                        <p class="text-white-75 mb-0">Students</p>
                    </div>
                </div>
                <!-- Display teacher count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-person-badge fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $teacherCount; ?></h3>
                        <p class="text-white-75 mb-0">Teachers</p>
                    </div>
                </div>
                <!-- Display locked account count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-lock fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $lockedCount; ?></h3>
                        <p class="text-white-75 mb-0">Locked Accounts</p>
                    </div>
                </div>
                <!-- Display total user count -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-people-fill fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $totalUsers; ?></h3>
                        <p class="text-white-75 mb-0">Total Users</p>
                    </div>
                </div>
                <!-- Display total quizzes -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-journal-text fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $totalQuizzes; ?></h3>
                        <p class="text-white-75 mb-0">Total Quizzes</p>
                    </div>
                </div>
                <!-- Display total attempts -->
                <div class="col-lg-3 col-md-6 text-center">
                    <div class="mt-5">
                        <div class="mb-2"><i class="bi-clipboard-check fs-1 text-primary"></i></div>
                        <h3 class="h4 mb-2 text-white"><?php echo $totalAttempts; ?></h3>
                        <p class="text-white-75 mb-0">Total Attempts</p>
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
                <!-- Manage Users card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column text-center">
                            <i class="bi-people-fill fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Manage Users</h4>
                            <p class="card-text text-muted">Review and manage user accounts</p>
                            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#usersModal">Go to users</button>
                        </div>
                    </div>
                </div>
                <!-- Manage Quizzes card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column text-center">
                            <i class="bi-journal-text fs-1 text-primary mb-3"></i>
                            <h4 class="card-title">Manage Quizzes</h4>
                            <p class="card-text text-muted">Create and manage quizzes</p>
                            <a href="../admin/manage_quizzes.php" class="btn btn-primary mt-3">Go to quizzes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Manage Users modal -->
    <div class="modal fade" id="usersModal" tabindex="-1" aria-labelledby="usersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="usersModalLabel">Manage Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($usersModalMessage): ?>
                        <div class="alert alert-<?php echo $usersModalMessageType === 'success' ? 'success' : ($usersModalMessageType === 'warning' ? 'warning' : 'danger'); ?> mb-3" role="alert">
                            <?php echo htmlspecialchars($usersModalMessage); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($adminUsersList)): ?>
                        <p class="text-muted mb-0">No users found.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Username</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Created</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminUsersList as $u): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td class="text-capitalize"><?php echo htmlspecialchars($u['role']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($u['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                                            <td>
                                                <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                                    <span class="text-muted small">(you)</span>
                                                <?php elseif ($u['status'] === 'active'): ?>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Lock this user? They will not be able to log in.');">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                        <input type="hidden" name="action" value="lock">
                                                        <button type="submit" class="btn btn-sm btn-warning">Lock</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                        <input type="hidden" name="action" value="unlock">
                                                        <button type="submit" class="btn btn-sm btn-success">Unlock</button>
                                                    </form>
                                                <?php endif; ?>
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

    <!-- Load Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Creative theme JavaScript -->
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
    <script>
        (function() {
            if (window.location.search.indexOf('open_users=1') !== -1) {
                var modal = document.getElementById('usersModal');
                if (modal) {
                    var m = new bootstrap.Modal(modal);
                    m.show();
                    history.replaceState({}, '', 'admin_dashboard.php');
                }
            }
        })();
    </script>
</body>
</html>





