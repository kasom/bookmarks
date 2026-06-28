<?php
// api/import.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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

if (!isset($_FILES['bookmarks_file']) || $_FILES['bookmarks_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Failed to upload file']);
    exit;
}

$tmp_name = $_FILES['bookmarks_file']['tmp_name'];
$html = file_get_contents($tmp_name);

if ($html === false) {
    echo json_encode(['error' => 'Could not read file']);
    exit;
}

// Split the HTML content by tags to parse element by element
$elements = preg_split('/<(dt|dl)\b/i', $html);

$folder_stack = [];
$current_folder_id = null;
$bookmarks_imported = 0;
$folders_created = 0;

$pdo->beginTransaction();
try {
    foreach ($elements as $el) {
        $el = '<' . trim($el); // Restore starting bracket for search matching
        
        // 1. Handle folder closure depth
        $dl_close_count = substr_count(strtolower($el), '</dl>');
        for ($i = 0; $i < $dl_close_count; $i++) {
            array_pop($folder_stack);
        }
        $current_folder_id = !empty($folder_stack) ? end($folder_stack) : null;

        // 2. Identify and create folders
        if (preg_match('/<H3\b[^>]*>(.*?)<\/H3>/is', $el, $matches)) {
            $folder_name = trim(strip_tags($matches[1]));
            if ($folder_name) {
                // Check if folder exists
                $stmt = $pdo->prepare('SELECT id FROM folders WHERE user_id = ? AND name = ?');
                $stmt->execute([$user_id, $folder_name]);
                $folder = $stmt->fetch();
                if ($folder) {
                    $folder_id = (int)$folder['id'];
                } else {
                    $stmt = $pdo->prepare('INSERT INTO folders (user_id, name) VALUES (?, ?)');
                    $stmt->execute([$user_id, $folder_name]);
                    $folder_id = (int)$pdo->lastInsertId();
                    $folders_created++;
                }
                $folder_stack[] = $folder_id;
                $current_folder_id = $folder_id;
            }
        }

        // 3. Identify and import bookmarks
        if (preg_match('/<A\s+[^>]*HREF=["\'](.*?)["\']/is', $el, $url_matches)) {
            $url = trim($url_matches[1]);
            
            // Extract Title
            $title = '';
            if (preg_match('/<A\b[^>]*>(.*?)<\/A>/is', $el, $title_matches)) {
                $title = trim(strip_tags($title_matches[1]));
            }
            if (!$title) {
                $title = $url;
            }

            // Extract Tags
            $tags = [];
            if (preg_match('/\bTAGS=["\'](.*?)["\']/is', $el, $tags_matches)) {
                $tags = array_map('trim', explode(',', $tags_matches[1]));
                $tags = array_filter($tags);
            }

            // Extract Visibility
            $visibility = 'public';
            if (preg_match('/\bPRIVATE=["\']1["\']/is', $el) || preg_match('/\bPRIVATE\b/is', $el)) {
                $visibility = 'private';
            }

            // Extract Description
            $description = '';
            if (preg_match('/<DD>(.*?)(?:<|$)/is', $el, $desc_matches)) {
                $description = trim(strip_tags($desc_matches[1]));
            }

            // Verify URL
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                // Check duplicate URL
                $stmt = $pdo->prepare('SELECT id FROM bookmarks WHERE user_id = ? AND url = ?');
                $stmt->execute([$user_id, $url]);
                if (!$stmt->fetch()) {
                    // Insert bookmark
                    $stmt = $pdo->prepare('INSERT INTO bookmarks (user_id, folder_id, title, url, description, visibility) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$user_id, $current_folder_id, $title, $url, $description, $visibility]);
                    $bookmark_id = (int)$pdo->lastInsertId();

                    // Insert tags
                    if (!empty($tags)) {
                        $stmt_tag = $pdo->prepare('INSERT INTO bookmark_tags (bookmark_id, tag_name) VALUES (?, ?)');
                        foreach ($tags as $tag) {
                            $stmt_tag->execute([$bookmark_id, $tag]);
                        }
                    }
                    $bookmarks_imported++;
                }
            }
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'bookmarks_count' => $bookmarks_imported,
    'folders_count' => $folders_created
]);
