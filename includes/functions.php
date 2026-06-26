<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

function get_folders(int $user_id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM folders WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_bookmarks(int $user_id, string $filter = 'all', ?int $folder_id = null, ?string $tag = null): array {
    global $pdo;

    $conditions = [];
    $params = [$user_id];

    if ($filter === 'private') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'private';
    } elseif ($filter === 'public') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'public';
    } elseif ($filter === 'shared_with_me') {
        $sql = "SELECT b.*, GROUP_CONCAT(bt.tag_name) as tags
                FROM bookmarks b
                LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
                WHERE b.id IN (SELECT bookmark_id FROM shared_bookmarks WHERE shared_with_user_id = ?)
                GROUP BY b.id
                ORDER BY b.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    if ($folder_id !== null) {
        $conditions[] = 'b.folder_id = ?';
        $params[] = $folder_id;
    }

    if ($tag !== null) {
        $conditions[] = 'b.id IN (SELECT bookmark_id FROM bookmark_tags WHERE tag_name = ?)';
        $params[] = $tag;
    }

    $where = $conditions ? 'WHERE b.user_id = ? AND ' . implode(' AND ', $conditions) : 'WHERE b.user_id = ?';

    $sql = "SELECT b.*, f.name as folder_name, GROUP_CONCAT(bt.tag_name) as tags
            FROM bookmarks b
            LEFT JOIN folders f ON b.folder_id = f.id
            LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
            $where
            GROUP BY b.id
            ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_bookmark(int $id, ?int $user_id = null): ?array {
    global $pdo;

    if ($user_id) {
        $stmt = $pdo->prepare('SELECT b.*, f.name as folder_name, GROUP_CONCAT(bt.tag_name) as tags FROM bookmarks b LEFT JOIN folders f ON b.folder_id = f.id LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id WHERE b.id = ? AND b.user_id = ? GROUP BY b.id');
        $stmt->execute([$id, $user_id]);
    } else {
        $stmt = $pdo->prepare('SELECT b.*, f.name as folder_name, GROUP_CONCAT(bt.tag_name) as tags, u.username FROM bookmarks b LEFT JOIN folders f ON b.folder_id = f.id LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.visibility = ? GROUP BY b.id');
        $stmt->execute([$id, 'public']);
    }

    return $stmt->fetch() ?: null;
}

function get_all_tags(int $user_id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT bt.tag_name, COUNT(*) as count FROM bookmark_tags bt JOIN bookmarks b ON bt.bookmark_id = b.id WHERE b.user_id = ? GROUP BY bt.tag_name ORDER BY bt.tag_name');
    $stmt->execute([$user_id]);
    return $stmt->fetchall();
}

function get_shared_with_me(int $user_id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT b.*, u.username as shared_by FROM shared_bookmarks sb JOIN bookmarks b ON sb.bookmark_id = b.id JOIN users u ON sb.shared_by_user_id = u.id WHERE sb.shared_with_user_id = ? ORDER BY sb.created_at DESC');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_public_bookmarks_by_user(string $username): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT b.*, GROUP_CONCAT(bt.tag_name) as tags FROM bookmarks b LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id JOIN users u ON b.user_id = u.id WHERE u.username = ? AND b.visibility = ? GROUP BY b.id ORDER BY b.created_at DESC');
    $stmt->execute([$username, 'public']);
    return $stmt->fetchAll();
}

function get_bookmark_shares(int $bookmark_id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT sb.*, u.username FROM shared_bookmarks sb JOIN users u ON sb.shared_with_user_id = u.id WHERE sb.bookmark_id = ?');
    $stmt->execute([$bookmark_id]);
    return $stmt->fetchAll();
}

function search_users(string $query): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username LIKE ? LIMIT 20');
    $stmt->execute(["%$query%"]);
    return $stmt->fetchAll();
}

function get_bookmark_count(int $user_id): int {
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?');
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}
