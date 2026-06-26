<?php
// admin.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$msg = '';
$csrf = generate_csrf_token();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $post_action = $_POST['action'] ?? '';
    $post_id = (int)($_POST['id'] ?? 0);

    if ($post_action === 'approve') {
        $pdo->prepare('UPDATE users SET approved = 1 WHERE id = ?')->execute([$post_id]);
        $msg = 'User approved.';
    } elseif ($post_action === 'reject') {
        $pdo->prepare('DELETE FROM users WHERE id = ? AND approved = 0')->execute([$post_id]);
        $msg = 'Registration rejected.';
    } elseif ($post_action === 'toggle_admin') {
        $new_val = (int)($_POST['set_admin'] ?? 0);
        $pdo->prepare('UPDATE users SET is_admin = ? WHERE id = ? AND id != ?')->execute([$new_val, $post_id, get_current_user_id()]);
        $msg = $new_val ? 'User promoted to admin.' : 'Admin rights removed.';
    } elseif ($post_action === 'toggle_disabled') {
        $new_val = (int)($_POST['set_disabled'] ?? 0);
        $pdo->prepare('UPDATE users SET disabled = ? WHERE id = ? AND id != ?')->execute([$new_val, $post_id, get_current_user_id()]);
        $msg = $new_val ? 'User disabled.' : 'User re-enabled.';
    } elseif ($post_action === 'delete_user') {
        $pdo->beginTransaction();
        try {
            // Verify user exists and is not the current admin
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND id != ? FOR UPDATE');
            $stmt->execute([$post_id, get_current_user_id()]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                $msg = 'User not found.';
            } else {
                // Delete all related data explicitly before deleting the user
                $pdo->prepare('DELETE FROM shared_bookmarks WHERE shared_by_user_id = ? OR shared_with_user_id = ?')->execute([$post_id, $post_id]);
                $pdo->prepare('DELETE FROM bookmark_tags WHERE bookmark_id IN (SELECT id FROM bookmarks WHERE user_id = ?)')->execute([$post_id]);
                $pdo->prepare('DELETE FROM bookmarks WHERE user_id = ?')->execute([$post_id]);
                $pdo->prepare('DELETE FROM folders WHERE user_id = ?')->execute([$post_id]);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$post_id]);
                $pdo->commit();
                $msg = 'User and all their data deleted.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Failed to delete user. Please try again.';
        }
    } elseif ($post_action === 'delete_bookmark') {
        $pdo->prepare('DELETE FROM bookmarks WHERE id = ?')->execute([$post_id]);
        $msg = 'Bookmark deleted.';
    }
}

// Fetch data
$pending = $pdo->query('SELECT * FROM users WHERE approved = 0 ORDER BY created_at DESC')->fetchAll();
$all_users = $pdo->query('SELECT * FROM users ORDER BY is_admin DESC, approved DESC, created_at DESC')->fetchAll();
$stmt = $pdo->query('SELECT b.*, u.username FROM bookmarks b JOIN users u ON b.user_id = u.id WHERE b.visibility = "public" ORDER BY b.created_at DESC');
$public_bookmarks = $stmt->fetchAll();

$pageTitle = 'Admin Panel';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?= h($msg) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-shield-lock"></i> Admin Panel</h2>
    <a href="/bookmarks/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Pending Registrations (<?= count($pending) ?>)</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Registered</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($pending)): ?>
                <tr><td colspan="5" class="text-center text-muted">No pending registrations.</td></tr>
                <?php else: ?>
                <?php foreach ($pending as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= h($u['username']) ?></td>
                    <td><?= h($u['email']) ?></td>
                    <td><?= h($u['created_at']) ?></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject and delete this registration?')">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-people"></i> All Users (<?= count($all_users) ?>)</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($all_users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td>
                        <?= h($u['username']) ?>
                        <?php if ((int)$u['is_admin']): ?><span class="badge bg-danger ms-1">Admin</span><?php endif; ?>
                    </td>
                    <td><?= h($u['email']) ?></td>
                    <td>
                        <?php if ((int)$u['disabled']): ?>
                            <span class="badge bg-secondary">Disabled</span>
                        <?php elseif ((int)$u['approved']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$u['id'] !== get_current_user_id()): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="toggle_admin">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="set_admin" value="<?= (int)$u['is_admin'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning"><?= (int)$u['is_admin'] ? 'Demote' : 'Make Admin' ?></button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="toggle_disabled">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="set_disabled" value="<?= (int)$u['disabled'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= (int)$u['disabled'] ? 'success' : 'danger' ?>"><?= (int)$u['disabled'] ? 'Enable' : 'Disable' ?></button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete user \"<?= h($u['username']) ?>\" and all their data?')">Delete</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-globe"></i> Public Bookmarks (<?= count($public_bookmarks) ?>)</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Title</th><th>URL</th><th>Owner</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($public_bookmarks)): ?>
                <tr><td colspan="6" class="text-center text-muted">No public bookmarks.</td></tr>
                <?php else: ?>
                <?php foreach ($public_bookmarks as $bm): ?>
                <tr>
                    <td><?= $bm['id'] ?></td>
                    <td><a href="<?= h($bm['url']) ?>" target="_blank"><?= h(mb_substr($bm['title'], 0, 50)) ?></a></td>
                    <td class="text-muted small"><?= h(parse_url($bm['url'], PHP_URL_HOST)) ?></td>
                    <td><?= h($bm['username']) ?></td>
                    <td><?= h($bm['created_at']) ?></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete_bookmark">
                            <input type="hidden" name="id" value="<?= $bm['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this public bookmark?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
