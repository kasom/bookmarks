<?php
// api/share.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'share') {
        $bookmark_id = (int)($_POST['bookmark_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');

        if (!$bookmark_id || !$username) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM bookmarks WHERE id = ? AND user_id = ?');
        $stmt->execute([$bookmark_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Bookmark not found']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $target = $stmt->fetch();
        if (!$target) {
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        if ((int)$target['id'] === $user_id) {
            echo json_encode(['error' => 'Cannot share with yourself']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO shared_bookmarks (bookmark_id, shared_by_user_id, shared_with_user_id) VALUES (?, ?, ?)');
        $stmt->execute([$bookmark_id, $user_id, (int)$target['id']]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'unshare') {
        $bookmark_id = (int)($_POST['bookmark_id'] ?? 0);
        $user_id_target = (int)($_POST['user_id'] ?? 0);

        if (!$bookmark_id || !$user_id_target) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM shared_bookmarks WHERE bookmark_id = ? AND shared_with_user_id = ? AND shared_by_user_id = ?');
        $stmt->execute([$bookmark_id, $user_id_target, $user_id]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'get_shares') {
        $bookmark_id = (int)($_POST['bookmark_id'] ?? 0);

        // Verify the caller owns this bookmark or is an admin
        $stmt = $pdo->prepare('SELECT id FROM bookmarks WHERE id = ? AND user_id = ?');
        $stmt->execute([$bookmark_id, $user_id]);
        if (!$stmt->fetch() && !is_admin()) {
            echo json_encode(['error' => 'Bookmark not found']);
            exit;
        }

        $shares = get_bookmark_shares($bookmark_id);
        echo json_encode(['success' => true, 'shares' => $shares]);

    } elseif ($action === 'search_users') {
        $query = trim($_POST['query'] ?? '');
        $users = search_users($query);
        echo json_encode(['success' => true, 'users' => $users]);

    } else {
        echo json_encode(['error' => 'Invalid action']);
    }

} else {
    echo json_encode(['error' => 'Method not allowed']);
}
