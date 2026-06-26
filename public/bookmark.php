<?php
// public/bookmark.php - Single public bookmark view
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$bm = get_bookmark($id);

$pageTitle = 'Public Bookmark';
require_once __DIR__ . '/../includes/header.php';

if (!$bm) {
    ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> Bookmark not found or not public.
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $bm['title'] . ' - Public Bookmark';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-body p-5 text-center">
                <span class="badge bg-success mb-3"><i class="bi bi-globe"></i> Public Bookmark</span>
                <h2 class="mb-3">
                    <a href="<?= h($bm['url']) ?>" target="_blank" class="text-decoration-none">
                        <?= h($bm['title']) ?>
                    </a>
                </h2>
                <p class="text-muted mb-3">
                    <i class="bi bi-link-45deg"></i> <?= h($bm['url']) ?>
                </p>
                <?php if ($bm['description']): ?>
                    <p class="mb-4"><?= h($bm['description']) ?></p>
                <?php endif; ?>
                <?php if ($bm['tags']): ?>
                    <div class="mb-3">
                        <?php foreach (explode(',', $bm['tags']) as $tag_name): ?>
                            <span class="badge bg-secondary"><?= h(trim($tag_name)) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="text-muted small">
                    Shared by <a href="/bookmarks/public/user.php?username=<?= urlencode($bm['username']) ?>"><?= h($bm['username']) ?></a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
