<?php
// includes/header.php
if (!isset($pageTitle)) $pageTitle = 'Bookmarks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/bookmarks/css/style.css" rel="stylesheet">
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (!theme) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
        <div class="container">
            <a class="navbar-brand" href="/bookmarks/index.php">
                <i class="bi bi-bookmark-fill"></i> Bookmarks
            </a>
            <div class="d-flex align-items-center">
                <?php if (is_logged_in()): ?>
                    <span class="text-body-secondary me-3"><i class="bi bi-person"></i> <?= h(get_current_username()) ?></span>
                    <a href="/bookmarks/tags.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-tags"></i> Tags</a>
                    <a href="/bookmarks/settings.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-gear"></i> Settings</a>
                    <a href="/bookmarks/public/user.php?username=<?= urlencode(get_current_username()) ?>" class="btn btn-sm btn-outline-secondary me-2" target="_blank">
                        <i class="bi bi-globe"></i> Public
                    </a>
                    <?php if (is_admin()): ?>
                    <a href="/bookmarks/admin.php" class="btn btn-sm btn-outline-warning me-2"><i class="bi bi-shield-lock"></i> Admin</a>
                    <?php endif; ?>
                    <a href="/bookmarks/logout.php" class="btn btn-sm btn-outline-danger me-2">Logout</a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary" id="themeToggleBtn" title="Toggle Light/Dark Mode">
                    <i class="bi bi-sun-fill" id="themeToggleIcon"></i>
                </button>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
