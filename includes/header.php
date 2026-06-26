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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/bookmarks/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/bookmarks/index.php">
                <i class="bi bi-bookmark-fill"></i> Bookmarks
            </a>
            <?php if (is_logged_in()): ?>
                <div class="d-flex align-items-center">
                    <span class="text-light me-3"><i class="bi bi-person"></i> <?= h(get_current_username()) ?></span>
                    <a href="/bookmarks/settings.php" class="btn btn-sm btn-outline-light me-2"><i class="bi bi-gear"></i> Settings</a>
                    <a href="/bookmarks/public/user.php?username=<?= urlencode(get_current_username()) ?>" class="btn btn-sm btn-outline-light me-2" target="_blank">
                        <i class="bi bi-globe"></i> Public
                    </a>
                    <?php if (is_admin()): ?>
                    <a href="/bookmarks/admin.php" class="btn btn-sm btn-outline-warning me-2"><i class="bi bi-shield-lock"></i> Admin</a>
                    <?php endif; ?>
                    <a href="/bookmarks/logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container mt-4">
