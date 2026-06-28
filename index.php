<?php
// index.php - Dashboard
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user_id = get_current_user_id();

$filter = $_GET['filter'] ?? 'all';
$folder_id = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int)$_GET['folder_id'] : null;
$tag = $_GET['tag'] ?? null;
$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

$bookmarks = get_bookmarks($user_id, $filter, $folder_id, $tag, $search, $sort);
$folders = get_folders($user_id);
$tags = get_all_tags($user_id);
$shared = get_shared_with_me($user_id);
$count = get_bookmark_count($user_id);

$pageTitle = 'My Bookmarks';
$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Bookmarks (<?= $count ?>)</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookmarkModal">
        <i class="bi bi-plus-lg"></i> Add Bookmark
    </button>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Filters</h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="/bookmarks/index.php" class="list-group-item list-group-item-action <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-grid"></i> All Bookmarks
                </a>
                <a href="/bookmarks/index.php?filter=private" class="list-group-item list-group-item-action <?= $filter === 'private' ? 'active' : '' ?>">
                    <i class="bi bi-lock"></i> Private
                </a>
                <a href="/bookmarks/index.php?filter=public" class="list-group-item list-group-item-action <?= $filter === 'public' ? 'active' : '' ?>">
                    <i class="bi bi-globe"></i> Public
                </a>
                <a href="/bookmarks/index.php?filter=shared_with_me" class="list-group-item list-group-item-action <?= $filter === 'shared_with_me' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Shared with Me
                </a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Folders</h6>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addFolderModal">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
            <div class="list-group list-group-flush">
                <a href="/bookmarks/index.php?folder_id=" class="list-group-item list-group-item-action <?= $folder_id === null ? 'active' : '' ?>">
                    <i class="bi bi-inbox"></i> All Folders
                </a>
                <?php foreach ($folders as $f): ?>
                <a href="/bookmarks/index.php?folder_id=<?= $f['id'] ?>" class="list-group-item list-group-item-action <?= $folder_id === $f['id'] ? 'active' : '' ?>">
                    <i class="bi bi-folder"></i> <?= h($f['name']) ?>
                    <span class="float-end">
                        <button class="btn btn-sm btn-link p-0 edit-folder me-2" data-id="<?= $f['id'] ?>" data-name="<?= h($f['name']) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-link p-0 text-danger delete-folder" data-id="<?= $f['id'] ?>" data-name="<?= h($f['name']) ?>">×</button>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($tags)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Tags</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1">
                    <a href="/bookmarks/index.php" class="btn btn-sm btn-outline-secondary <?= !$tag ? 'active' : '' ?>">All</a>
                    <?php foreach ($tags as $t): ?>
                    <a href="/bookmarks/index.php?tag=<?= urlencode($t['tag_name']) ?>" class="btn btn-sm btn-outline-primary <?= $tag === $t['tag_name'] ? 'active' : '' ?>">
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
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by title, description or URL..." value="<?= h($search) ?>">
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
                            <a href="index.php?<?= http_build_query(array_filter(['filter' => $filter !== 'all' ? $filter : null, 'folder_id' => $folder_id, 'tag' => $tag, 'sort' => $sort])) ?>" class="text-white ms-2 text-decoration-none" title="Clear search">
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
                <i class="bi bi-info-circle"></i> No bookmarks found. Add one to get started!
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
                                <div class="d-flex align-items-center">
                                    <button class="btn-copy-url me-1" data-url="<?= h($bm['url']) ?>" title="Copy URL">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <?php if ((int)$bm['user_id'] === $user_id): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link p-0 text-body-secondary" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item edit-bookmark" href="#" data-id="<?= $bm['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a></li>
                                            <li><a class="dropdown-item share-bookmark" href="#" data-id="<?= $bm['id'] ?>">
                                                <i class="bi bi-share"></i> Share
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger delete-bookmark" href="#" data-id="<?= $bm['id'] ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="card-text small text-muted mb-2">
                                <i class="bi bi-link-45deg"></i> <?= h(parse_url($bm['url'], PHP_URL_HOST)) ?>
                            </p>
                            <?php if (!empty($bm['shared_by'])): ?>
                                <p class="card-text small text-warning mb-2">
                                    <i class="bi bi-person-fill"></i> Shared by <?= h($bm['shared_by']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($bm['description']): ?>
                                <p class="card-text small"><?= h(mb_substr($bm['description'], 0, 100)) ?><?= mb_strlen($bm['description']) > 100 ? '...' : '' ?></p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    <?php if ($bm['tags']): ?>
                                        <?php foreach (explode(',', $bm['tags']) as $tag_name): ?>
                                            <span class="badge bg-secondary"><?= h(trim($tag_name)) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $bm['visibility'] === 'public' ? 'bg-success' : ($bm['visibility'] === 'shared' ? 'bg-warning' : 'bg-secondary') ?>">
                                    <?= ucfirst($bm['visibility']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Bookmark Modal -->
<div class="modal fade" id="addBookmarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Bookmark</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBookmarkForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="mb-3">
                        <label class="form-label">URL *</label>
                        <div class="input-group">
                            <input type="url" name="url" class="form-control" required placeholder="https://example.com">
                            <button class="btn btn-outline-secondary btn-fetch-metadata" type="button" title="Auto-fetch Title & Description">
                                <i class="bi bi-arrow-clockwise"></i> Fetch
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Bookmark title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Folder</label>
                        <select name="folder_id" class="form-select">
                            <option value="">No folder</option>
                            <?php foreach ($folders as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= h($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="tag1, tag2, tag3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" class="form-select">
                            <option value="private">Private</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bookmark</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bookmark Modal -->
<div class="modal fade" id="editBookmarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bookmark</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBookmarkForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">URL *</label>
                        <div class="input-group">
                            <input type="url" name="url" id="edit_url" class="form-control" required>
                            <button class="btn btn-outline-secondary btn-fetch-metadata" type="button" title="Auto-fetch Title & Description">
                                <i class="bi bi-arrow-clockwise"></i> Fetch
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Folder</label>
                        <select name="folder_id" id="edit_folder_id" class="form-select">
                            <option value="">No folder</option>
                            <?php foreach ($folders as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= h($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" id="edit_tags" class="form-control" placeholder="tag1, tag2, tag3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" id="edit_visibility" class="form-select">
                            <option value="private">Private</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rename Folder Modal -->
<div class="modal fade" id="renameFolderModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rename Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="renameFolderForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="id" id="rename_folder_id">
                    <input type="text" name="name" id="rename_folder_name" class="form-control" required placeholder="Folder name">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Folder Modal -->
<div class="modal fade" id="addFolderModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addFolderForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="text" name="name" class="form-control" required placeholder="Folder name">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Bookmark</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="shareForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="bookmark_id" id="share_bookmark_id">
                    <div class="mb-3">
                        <label class="form-label">Share with (username)</label>
                        <input type="text" name="username" id="share_username" class="form-control" required placeholder="Enter username">
                        <div id="share_suggestions" class="mt-2"></div>
                    </div>
                    <div id="share_existing"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Share</button>
                </div>
            </form>
        </div>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
