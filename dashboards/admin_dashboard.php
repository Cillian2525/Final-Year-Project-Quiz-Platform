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
    <title>Admin Dashboard - IntelliQuiz</title>
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
            <a class="navbar-brand" href="admin_dashboard.php">IntelliQuiz</a>
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
                    <h1 class="text-white fw-bold h2 mb-1">Admin Dashboard</h1>
                    <p class="text-white-75 mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
            </div>

            <section class="py-2" id="stats">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-12 col-lg-10">
                        <div class="card border-0 rounded-4 shadow-sm py-2 px-2 mb-2">
                            <div class="row g-2">
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-people fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$studentCount; ?></h3>
                                        <p class="text-muted mb-0 small">Students</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-person-badge fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$teacherCount; ?></h3>
                                        <p class="text-muted mb-0 small">Teachers</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-lock fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$lockedCount; ?></h3>
                                        <p class="text-muted mb-0 small">Locked</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-people-fill fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$totalUsers; ?></h3>
                                        <p class="text-muted mb-0 small">Total Users</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-journal-text fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$totalQuizzes; ?></h3>
                                        <p class="text-muted mb-0 small">Quizzes</p>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div>
                                        <div class="mb-1"><i class="bi-clipboard-check fs-6 text-primary"></i></div>
                                        <h3 class="h6 mb-0 text-dark"><?php echo (int)$totalAttempts; ?></h3>
                                        <p class="text-muted mb-0 small">Attempts</p>
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
                        <div class="row g-2 g-lg-3">
                            <div class="col-lg-6 col-md-6">
                                <div class="card h-100 border-0 rounded-4 shadow-sm">
                                    <div class="card-body d-flex flex-column text-center p-2">
                                        <i class="bi-people-fill fs-6 text-primary mb-1"></i>
                                        <h4 class="card-title h6 mb-1">Manage Users</h4>
                                        <p class="card-text text-muted small mb-1">Review accounts</p>
                                        <button type="button" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center" data-bs-toggle="modal" data-bs-target="#usersModal">Go to users</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6">
                                <div class="card h-100 border-0 rounded-4 shadow-sm">
                                    <div class="card-body d-flex flex-column text-center p-2">
                                        <i class="bi-journal-text fs-6 text-primary mb-1"></i>
                                        <h4 class="card-title h6 mb-1">Manage Quizzes</h4>
                                        <p class="card-text text-muted small mb-1">Edit quizzes</p>
                                        <a href="../admin/manage_quizzes.php" class="btn btn-sm btn-primary px-3 rounded-pill mt-0 align-self-center">Go to quizzes</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </header>

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
            function cleanupStuckModalBackdrop() {
                var anyShownModal = document.querySelector('.modal.show');
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (!anyShownModal && (backdrops.length > 0 || document.body.classList.contains('modal-open'))) {
                    backdrops.forEach(function (b) { b.parentNode && b.parentNode.removeChild(b); });
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('padding-right');
                }
            }

            if (window.location.search.indexOf('open_users=1') !== -1) {
                var modal = document.getElementById('usersModal');
                if (modal) {
                    cleanupStuckModalBackdrop();
                    var m = new bootstrap.Modal(modal);
                    m.show();
                    history.replaceState({}, '', 'admin_dashboard.php');
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





