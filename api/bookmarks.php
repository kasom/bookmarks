<?php
// api/bookmarks.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user_id = get_current_user_id();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $url = trim($_POST['url'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;
        $visibility = in_array($_POST['visibility'] ?? '', ['private', 'public']) ? $_POST['visibility'] : 'private';
        $tags = array_map('trim', explode(',', $_POST['tags'] ?? ''));
        $tags = array_filter($tags);

        if (!$url || !$title) {
            echo json_encode(['error' => 'URL and title are required']);
            exit;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($scheme ?: ''), ['http', 'https'])) {
            echo json_encode(['error' => 'Invalid URL or unsupported protocol (must be http or https)']);
            exit;
        }


        if ($folder_id) {
            $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
            $stmt->execute([$folder_id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Invalid folder']);
                exit;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO bookmarks (user_id, folder_id, title, url, description, visibility) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user_id, $folder_id, $title, $url, $description, $visibility]);
        $bookmark_id = (int)$pdo->lastInsertId();

        if (!empty($tags)) {
            $stmt = $pdo->prepare('INSERT INTO bookmark_tags (bookmark_id, tag_name) VALUES (?, ?)');
            foreach ($tags as $tag) {
                $stmt->execute([$bookmark_id, $tag]);
            }
        }

        echo json_encode(['success' => true, 'id' => $bookmark_id]);

    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $url = trim($_POST['url'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;
        $visibility = in_array($_POST['visibility'] ?? '', ['private', 'public']) ? $_POST['visibility'] : 'private';
        $tags = array_map('trim', explode(',', $_POST['tags'] ?? ''));
        $tags = array_filter($tags);

        if (!$id || !$url || !$title) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($scheme ?: ''), ['http', 'https'])) {
            echo json_encode(['error' => 'Invalid URL or unsupported protocol (must be http or https)']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM bookmarks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Bookmark not found']);
            exit;
        }

        // If updated to private but has active shares, keep/change visibility to 'shared'
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM shared_bookmarks WHERE bookmark_id = ?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0 && $visibility === 'private') {
            $visibility = 'shared';
        }

        if ($folder_id) {
            $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
            $stmt->execute([$folder_id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Invalid folder']);
                exit;
            }
        }

        $stmt = $pdo->prepare('UPDATE bookmarks SET title = ?, url = ?, description = ?, folder_id = ?, visibility = ? WHERE id = ?');
        $stmt->execute([$title, $url, $description, $folder_id, $visibility, $id]);

        $pdo->prepare('DELETE FROM bookmark_tags WHERE bookmark_id = ?')->execute([$id]);

        if (!empty($tags)) {
            $stmt = $pdo->prepare('INSERT INTO bookmark_tags (bookmark_id, tag_name) VALUES (?, ?)');
            foreach ($tags as $tag) {
                $stmt->execute([$id, $tag]);
            }
        }

        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'Missing bookmark ID']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM bookmarks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'get') {
        $id = (int)($_POST['id'] ?? 0);
        $bm = get_bookmark($id, $user_id);
        if ($bm) {
            echo json_encode(['success' => true, 'bookmark' => $bm]);
        } else {
            echo json_encode(['error' => 'Bookmark not found']);
        }

    } else {
        echo json_encode(['error' => 'Invalid action']);
    }

} else {
    echo json_encode(['error' => 'Method not allowed']);
}
