<?php
// api/tags.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

require_login();
$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'rename') {
    $old_tag = trim($_POST['old_tag'] ?? '');
    $new_tag = trim($_POST['new_tag'] ?? '');
    
    if (!$old_tag || !$new_tag) {
        echo json_encode(['error' => 'Old and new tag names are required']);
        exit;
    }

    if ($old_tag === $new_tag) {
        echo json_encode(['success' => true]);
        exit;
    }

    // Get all bookmark IDs owned by the user that have $old_tag
    $stmt = $pdo->prepare('SELECT bt.id, bt.bookmark_id FROM bookmark_tags bt JOIN bookmarks b ON bt.bookmark_id = b.id WHERE b.user_id = ? AND bt.tag_name = ?');
    $stmt->execute([$user_id, $old_tag]);
    $tags_to_update = $stmt->fetchAll();

    $pdo->beginTransaction();
    try {
        foreach ($tags_to_update as $tag_row) {
            $bt_id = (int)$tag_row['id'];
            $bookmark_id = (int)$tag_row['bookmark_id'];
            
            // Check duplicate tag entries
            $check = $pdo->prepare('SELECT id FROM bookmark_tags WHERE bookmark_id = ? AND tag_name = ?');
            $check->execute([$bookmark_id, $new_tag]);
            if ($check->fetch()) {
                // If it already has the new tag, delete the old tag entry to avoid duplicates
                $pdo->prepare('DELETE FROM bookmark_tags WHERE id = ?')->execute([$bt_id]);
            } else {
                // Update old tag to new tag
                $pdo->prepare('UPDATE bookmark_tags SET tag_name = ? WHERE id = ?')->execute([$new_tag, $bt_id]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to rename tag: ' . $e->getMessage()]);
    }

} elseif ($action === 'delete') {
    $tag_name = trim($_POST['tag_name'] ?? '');
    if (!$tag_name) {
        echo json_encode(['error' => 'Tag name is required']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE bt FROM bookmark_tags bt JOIN bookmarks b ON bt.bookmark_id = b.id WHERE b.user_id = ? AND bt.tag_name = ?');
    $stmt->execute([$user_id, $tag_name]);
    
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['error' => 'Invalid action']);
}
