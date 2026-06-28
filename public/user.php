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

$filter = $_GET['filter'] ?? 'all';
$folder_id = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int)$_GET['folder_id'] : null;
$tag = $_GET['tag'] ?? null;
$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

$bookmarks = get_public_bookmarks_by_user($username, $filter, $folder_id, $tag, $search, $sort);
$folders = get_public_folders($username, $filter);
$tags = get_public_tags($username, $filter);

$pageTitle = $username . ' - Public Bookmarks';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h2><i class="bi bi-globe"></i> <?= h($username) ?>'s Public Bookmarks</h2>
    <p class="text-muted"><?= count($bookmarks) ?> public bookmark<?= count($bookmarks) !== 1 ? 's' : '' ?></p>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Filters</h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>" class="list-group-item list-group-item-action <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-grid"></i> All Bookmarks
                </a>
                <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>&filter=public" class="list-group-item list-group-item-action <?= $filter === 'public' ? 'active' : '' ?>">
                    <i class="bi bi-globe"></i> Public
                </a>
                <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>&filter=shared" class="list-group-item list-group-item-action <?= $filter === 'shared' ? 'active' : '' ?>">
                    <i class="bi bi-share"></i> Shared
                </a>
            </div>
        </div>

        <?php if (!empty($folders)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Folders</h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>&filter=<?= urlencode($filter) ?>" class="list-group-item list-group-item-action <?= $folder_id === null ? 'active' : '' ?>">
                    <i class="bi bi-inbox"></i> All Folders
                </a>
                <?php foreach ($folders as $f): ?>
                <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>&filter=<?= urlencode($filter) ?>&folder_id=<?= $f['id'] ?>" class="list-group-item list-group-item-action <?= $folder_id === $f['id'] ? 'active' : '' ?>">
                    <i class="bi bi-folder"></i> <?= h($f['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($tags)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Tags</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1">
                    <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>&filter=<?= urlencode($filter) ?>" class="btn btn-sm btn-outline-secondary <?= !$tag ? 'active' : '' ?>">All</a>
                    <?php foreach ($tags as $t): ?>
                    <a href="/bookmarks/public/user.php?username=<?= urlencode($username) ?>&filter=<?= urlencode($filter) ?>&tag=<?= urlencode($t['tag_name']) ?>" class="btn btn-sm btn-outline-primary <?= $tag === $t['tag_name'] ? 'active' : '' ?>">
                        <?= h($t['tag_name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-9">
        <!-- Search and Sort Controls -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <input type="hidden" name="username" value="<?= h($username) ?>">
                    <?php if (!empty($filter) && $filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?= h($filter) ?>">
                    <?php endif; ?>
                    <?php if ($folder_id !== null): ?>
                        <input type="hidden" name="folder_id" value="<?= h($folder_id) ?>">
                    <?php endif; ?>
                    <?php if ($tag !== null): ?>
                        <input type="hidden" name="tag" value="<?= h($tag) ?>">
                    <?php endif; ?>

                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search public bookmarks..." value="<?= h($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="sort" class="form-select" onchange="this.form.submit()">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Added</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest Added</option>
                            <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                            <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Title (Z-A)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-secondary">Search</button>
                    </div>

                    <?php if ($search !== ''): ?>
                    <div class="col-12 mt-2">
                        <span class="badge bg-secondary p-2">
                            Search: "<?= h($search) ?>" 
                            <a href="user.php?<?= http_build_query(array_filter(['username' => $username, 'filter' => $filter !== 'all' ? $filter : null, 'folder_id' => $folder_id, 'tag' => $tag, 'sort' => $sort])) ?>" class="text-white ms-2 text-decoration-none" title="Clear search">
                                <i class="bi bi-x-circle-fill"></i>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($bookmarks)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No public bookmarks found.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($bookmarks as $bm): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 bookmark-card">
                        <?php 
                        $yt_id = get_youtube_video_id($bm['url']);
                        if ($yt_id): 
                        ?>
                            <div class="thumbnail-wrapper">
                                <img src="https://img.youtube.com/vi/<?= h($yt_id) ?>/mqdefault.jpg" alt="YouTube Thumbnail">
                                <div class="play-overlay youtube-play-btn" data-yt-id="<?= h($yt_id) ?>">
                                    <i class="bi bi-play-fill"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0 d-flex align-items-center">
                                    <img src="https://www.google.com/s2/favicons?sz=32&domain=<?= urlencode(parse_url($bm['url'], PHP_URL_HOST)) ?>" class="favicon-icon" alt="" onerror="this.style.display='none'">
                                    <a href="<?= h($bm['url']) ?>" target="_blank" class="text-decoration-none align-middle text-truncate" style="max-width: 140px;" title="<?= h($bm['title']) ?>">
                                        <?= h($bm['title']) ?>
                                    </a>
                                </h6>
                                <button class="btn-copy-url" data-url="<?= h($bm['url']) ?>" title="Copy URL">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <p class="card-text small text-muted mb-2">
                                <i class="bi bi-link-45deg"></i> <?= h(parse_url($bm['url'], PHP_URL_HOST)) ?>
                            </p>
                            <?php if ($bm['description']): ?>
                                <p class="card-text small"><?= h(mb_substr($bm['description'], 0, 100)) ?><?= mb_strlen($bm['description']) > 100 ? '...' : '' ?></p>
                            <?php endif; ?>
                            <?php if ($bm['tags']): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach (explode(',', $bm['tags']) as $tag_name): ?>
                                        <span class="badge tag-badge"><?= h(trim($tag_name)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <a href="/bookmarks/public/bookmark.php?id=<?= $bm['id'] ?>" class="btn btn-sm btn-outline-primary">
                                View Bookmark
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- YouTube Video Modal -->
<div class="modal fade" id="youtubeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 bg-transparent">
            <div class="modal-header border-0 p-0 mb-2 justify-content-end">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9 shadow-lg rounded overflow-hidden">
                    <iframe src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen id="youtubePlayerIframe"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
