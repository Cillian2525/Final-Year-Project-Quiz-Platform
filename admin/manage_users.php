<?php
session_start();
require '../includes/db_connect.php';

// Security: only admins may manage users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle lock/unlock toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $targetId = (int) $_POST['user_id'];
    $action = $_POST['action'] === 'lock' ? 'locked' : 'active';
    $currentAdminId = (int) $_SESSION['user_id'];

    // Cannot change own account
    if ($targetId === $currentAdminId) {
        $message = 'You cannot lock or unlock your own account.';
        $messageType = 'warning';
    } else {
        try {
            if ($action === 'locked') {
                // Ensure we don't lock the last admin
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$targetId]);
                $target = $stmt->fetch();
                if ($target && $target['role'] === 'admin') {
                    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
                    if ($count <= 1) {
                        $message = 'Cannot lock the last active admin.';
                        $messageType = 'warning';
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = ?");
                        $stmt->execute([$targetId]);
                        $message = 'User locked.';
                        $messageType = 'success';
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = ?");
                    $stmt->execute([$targetId]);
                    $message = 'User locked.';
                    $messageType = 'success';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$targetId]);
                $message = 'User unlocked.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Update failed. Please try again.';
            $messageType = 'danger';
        }
    }
}

// Fetch all users
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY role, username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Quiz System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="../dashboards/admin_dashboard.php">Quiz System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboards/admin_dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <header class="masthead">
        <div class="container px-4 px-lg-5 h-100">
            <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-8 align-self-end">
                    <h1 class="text-white font-weight-bold">Manage Users</h1>
                    <hr class="divider" />
                </div>
                <div class="col-lg-8 align-self-baseline">
                    <p class="text-white-75 mb-5">Review and lock/unlock user accounts</p>
                </div>
            </div>
        </div>
    </header>

    <section class="page-section" id="users">
        <div class="container px-4 px-lg-5">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?> mb-4" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mt-0 mb-0">All Users</h2>
                <a href="../dashboards/admin_dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
            </div>

            <?php if (empty($users)): ?>
                <p class="text-muted">No users found.</p>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
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
                            <?php foreach ($users as $u): ?>
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
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-creative@gh-pages/js/scripts.js"></script>
</body>
</html>
