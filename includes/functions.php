<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

function get_folders(int $user_id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM folders WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_bookmarks(int $user_id, string $filter = 'all', ?int $folder_id = null, ?string $tag = null, ?string $search = null, string $sort = 'newest'): array {
    global $pdo;

    $conditions = [];
    $params = [];

    if ($filter === 'private') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'private';
        $conditions[] = 'b.user_id = ?';
        $params[] = $user_id;
    } elseif ($filter === 'public') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'public';
        $conditions[] = 'b.user_id = ?';
        $params[] = $user_id;
    } elseif ($filter === 'shared_with_me') {
        $conditions[] = 'b.id IN (SELECT bookmark_id FROM shared_bookmarks WHERE shared_with_user_id = ?)';
        $params[] = $user_id;
    } else {
        // 'all' filter: bookmarks owned by the user
        $conditions[] = 'b.user_id = ?';
        $params[] = $user_id;
    }

    if ($folder_id !== null) {
        $conditions[] = 'b.folder_id = ?';
        $params[] = $folder_id;
    }

    if ($tag !== null) {
        $conditions[] = 'b.id IN (SELECT bookmark_id FROM bookmark_tags WHERE tag_name = ?)';
        $params[] = $tag;
    }

    if ($search !== null && $search !== '') {
        $conditions[] = '(b.title LIKE ? OR b.url LIKE ? OR b.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $orderBy = 'b.created_at DESC';
    if ($sort === 'oldest') {
        $orderBy = 'b.created_at ASC';
    } elseif ($sort === 'title_asc') {
        $orderBy = 'b.title ASC';
    } elseif ($sort === 'title_desc') {
        $orderBy = 'b.title DESC';
    }

    $sql = "SELECT b.*, f.name as folder_name, GROUP_CONCAT(DISTINCT bt.tag_name) as tags, u.username as shared_by
            FROM bookmarks b
            LEFT JOIN folders f ON b.folder_id = f.id
            LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
            LEFT JOIN shared_bookmarks sb ON b.id = sb.bookmark_id AND sb.shared_with_user_id = ?
            LEFT JOIN users u ON sb.shared_by_user_id = u.id
            $where
            GROUP BY b.id
            ORDER BY $orderBy";

    array_unshift($params, $user_id);

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

function get_public_bookmarks_by_user(string $username, string $filter = 'all', ?int $folder_id = null, ?string $tag = null, ?string $search = null, string $sort = 'newest'): array {
    global $pdo;

    $conditions = [];
    $params = [];

    $conditions[] = 'u.username = ?';
    $params[] = $username;

    if ($filter === 'public') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'public';
    } elseif ($filter === 'shared') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'shared';
    } else {
        $conditions[] = 'b.visibility IN (?, ?)';
        $params[] = 'public';
        $params[] = 'shared';
    }

    if ($folder_id !== null) {
        $conditions[] = 'b.folder_id = ?';
        $params[] = $folder_id;
    }

    if ($tag !== null) {
        $conditions[] = 'b.id IN (SELECT bookmark_id FROM bookmark_tags WHERE tag_name = ?)';
        $params[] = $tag;
    }

    if ($search !== null && $search !== '') {
        $conditions[] = '(b.title LIKE ? OR b.url LIKE ? OR b.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $where = implode(' AND ', $conditions);

    $orderBy = 'b.created_at DESC';
    if ($sort === 'oldest') {
        $orderBy = 'b.created_at ASC';
    } elseif ($sort === 'title_asc') {
        $orderBy = 'b.title ASC';
    } elseif ($sort === 'title_desc') {
        $orderBy = 'b.title DESC';
    }

    $sql = "SELECT b.*, f.name as folder_name, GROUP_CONCAT(DISTINCT bt.tag_name) as tags
            FROM bookmarks b
            LEFT JOIN folders f ON b.folder_id = f.id
            LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
            JOIN users u ON b.user_id = u.id
            WHERE $where
            GROUP BY b.id
            ORDER BY $orderBy";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_public_folders(string $username, string $filter = 'all'): array {
    global $pdo;

    $conditions = ['u.username = ?'];
    $params = [$username];

    if ($filter === 'public') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'public';
    } elseif ($filter === 'shared') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'shared';
    } else {
        $conditions[] = 'b.visibility IN (?, ?)';
        $params[] = 'public';
        $params[] = 'shared';
    }

    $where = implode(' AND ', $conditions);

    $stmt = $pdo->prepare("SELECT DISTINCT f.* FROM folders f JOIN users u ON f.user_id = u.id JOIN bookmarks b ON b.folder_id = f.id AND $where ORDER BY f.name");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_public_tags(string $username, string $filter = 'all'): array {
    global $pdo;

    $conditions = ['u.username = ?'];
    $params = [$username];

    if ($filter === 'public') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'public';
    } elseif ($filter === 'shared') {
        $conditions[] = 'b.visibility = ?';
        $params[] = 'shared';
    } else {
        $conditions[] = 'b.visibility IN (?, ?)';
        $params[] = 'public';
        $params[] = 'shared';
    }

    $where = implode(' AND ', $conditions);

    $stmt = $pdo->prepare("SELECT bt.tag_name, COUNT(*) as count FROM bookmark_tags bt JOIN bookmarks b ON bt.bookmark_id = b.id JOIN users u ON b.user_id = u.id WHERE $where GROUP BY bt.tag_name ORDER BY bt.tag_name");
    $stmt->execute($params);
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

function get_youtube_video_id(string $url): ?string {
    $parsed = parse_url($url);
    if (!$parsed) return null;
    
    $host = strtolower($parsed['host'] ?? '');
    
    if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'])) {
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            if (isset($query['v'])) {
                return $query['v'];
            }
        }
        // Handle path format like /embed/VIDEO_ID or /v/VIDEO_ID
        $path_parts = explode('/', trim($parsed['path'] ?? '', '/'));
        if (count($path_parts) >= 2 && in_array($path_parts[0], ['embed', 'v'])) {
            return $path_parts[1];
        }
    } elseif ($host === 'youtu.be') {
        return ltrim($parsed['path'] ?? '', '/');
    }
    
    return null;
}
