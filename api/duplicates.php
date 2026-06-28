<?php
// api/duplicates.php
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

if ($action === 'list') {
    // Fetch all duplicates for the current user
    $sql = "SELECT b.*, f.name as folder_name, GROUP_CONCAT(DISTINCT bt.tag_name) as tags 
            FROM bookmarks b 
            LEFT JOIN folders f ON b.folder_id = f.id 
            LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
            WHERE b.user_id = ? AND b.url IN (
                SELECT url FROM bookmarks WHERE user_id = ? GROUP BY url HAVING COUNT(*) > 1
            )
            GROUP BY b.id
            ORDER BY b.url, b.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $bookmarks = $stmt->fetchAll();

    // Group bookmarks by URL
    $grouped = [];
    foreach ($bookmarks as $bm) {
        $grouped[$bm['url']][] = $bm;
    }

    echo json_encode(['success' => true, 'duplicates' => $grouped]);

} elseif ($action === 'delete_bulk') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        // Fallback for form-data serialized lists
        $ids = json_decode($ids, true) ?: [];
    }
    
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);

    if (empty($ids)) {
        echo json_encode(['error' => 'No bookmark IDs provided']);
        exit;
    }

    // Delete bookmarks ensuring they belong to the current user
    $in_clause = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM bookmarks WHERE user_id = ? AND id IN ($in_clause)";
    
    $params = array_merge([$user_id], $ids);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'deleted_count' => $stmt->rowCount()]);

} else {
    echo json_encode(['error' => 'Invalid action']);
}
