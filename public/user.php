<?php
// public/user.php - User's public profile
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$username = trim($_GET['username'] ?? '');

if (!$username) {
    $pageTitle = 'Public Bookmarks';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="alert alert-info">
        Please provide a username: <code>?username=john</code>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    $pageTitle = 'User Not Found';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> User not found.
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$bookmarks = get_public_bookmarks_by_user($username);
$pageTitle = $username . ' - Public Bookmarks';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h2><i class="bi bi-globe"></i> <?= h($username) ?>'s Public Bookmarks</h2>
    <p class="text-muted"><?= count($bookmarks) ?> public bookmark<?= count($bookmarks) !== 1 ? 's' : '' ?></p>
</div>

<?php if (empty($bookmarks)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No public bookmarks shared yet.
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($bookmarks as $bm): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2">
                        <a href="<?= h($bm['url']) ?>" target="_blank" class="text-decoration-none">
                            <?= h($bm['title']) ?>
                        </a>
                    </h6>
                    <p class="card-text small text-muted mb-2">
                        <i class="bi bi-link-45deg"></i> <?= h(parse_url($bm['url'], PHP_URL_HOST)) ?>
                    </p>
                    <?php if ($bm['description']): ?>
                        <p class="card-text small"><?= h(mb_substr($bm['description'], 0, 100)) ?><?= mb_strlen($bm['description']) > 100 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <?php if ($bm['tags']): ?>
                        <div>
                            <?php foreach (explode(',', $bm['tags']) as $tag_name): ?>
                                <span class="badge bg-secondary"><?= h(trim($tag_name)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/bookmarks/public/bookmark.php?id=<?= $bm['id'] ?>" class="btn btn-sm btn-outline-primary">
                        View Bookmark
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
