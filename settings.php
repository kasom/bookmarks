<?php
// settings.php
require_once __DIR__ . '/includes/auth.php';

require_login();
$user_id = get_current_user_id();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $error = 'Password must contain an uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new)) {
        $error = 'Password must contain a lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new)) {
        $error = 'Password must contain a number.';
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current, $user['password_hash'])) {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user_id]);
            $msg = 'Password changed successfully.';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

$pageTitle = 'Settings';
$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-gear"></i> Settings</h2>
            <a href="/bookmarks/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?= h($error) ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header"><h5 class="mb-0">Change Password</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <div class="card shadow mt-4">
            <div class="card-header"><h5 class="mb-0">Data Portability</h5></div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>Export Bookmarks</h6>
                    <p class="text-muted small">Export all your private and public bookmarks into standard Netscape HTML format. This file can be imported directly into Chrome, Firefox, Safari, or back here.</p>
                    <a href="/bookmarks/api/export.php" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Export HTML File
                    </a>
                </div>
                <hr>
                <div>
                    <h6>Import Bookmarks</h6>
                    <p class="text-muted small">Import your bookmarks from Chrome, Firefox, or other bookmark managers. Select your HTML file below.</p>
                    <form id="importBookmarksForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <div class="mb-3">
                            <input type="file" name="bookmarks_file" id="bookmarks_file" class="form-control" accept=".html,.htm" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary" id="btnImportSubmit">
                            <i class="bi bi-upload"></i> Import Bookmarks
                        </button>
                    </form>
                    <div id="importStatus" class="mt-3 d-none"></div>
                </div>
            </div>
        </div>

        <div class="card shadow mt-4">
            <div class="card-header"><h5 class="mb-0">Maintenance & Cleanup</h5></div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>Duplicate Bookmark Finder</h6>
                    <p class="text-muted small">Scan your library for bookmarks with identical URLs. You can choose to keep the oldest or newest entries, or select specific ones to delete.</p>
                    <a href="/bookmarks/duplicates.php" class="btn btn-outline-danger">
                        <i class="bi bi-clouds-fill"></i> Find and Clean Duplicates
                    </a>
                </div>
                <hr>
                <div>
                    <h6>Broken Link Checker</h6>
                    <p class="text-muted small">Scan your bookmark library for dead or non-functional links. Identify server error pages, connection timeouts, or DNS resolution failures.</p>
                    <a href="/bookmarks/check_links.php" class="btn btn-outline-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> Find and Clean Broken Links
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var importForm = document.getElementById('importBookmarksForm');
    if (importForm) {
        importForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var statusDiv = document.getElementById('importStatus');
            var btnSubmit = document.getElementById('btnImportSubmit');
            var originalBtnHtml = btnSubmit.innerHTML;

            statusDiv.className = 'alert alert-info mt-3';
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing, please wait...';
            statusDiv.classList.remove('d-none');
            btnSubmit.disabled = true;

            var fd = new FormData(this);
            fetch('/bookmarks/api/import.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalBtnHtml;

                    if (data.success) {
                        statusDiv.className = 'alert alert-success mt-3';
                        statusDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> Successfully imported <strong>' + data.bookmarks_count + '</strong> bookmarks and created <strong>' + data.folders_count + '</strong> folders.';
                        importForm.reset();
                    } else {
                        statusDiv.className = 'alert alert-danger mt-3';
                        statusDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.error || 'Import failed.');
                    }
                })
                .catch(function () {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalBtnHtml;
                    statusDiv.className = 'alert alert-danger mt-3';
                    statusDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Network request failed.';
                });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
