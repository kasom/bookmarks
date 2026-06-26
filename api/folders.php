<?php
// api/folders.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

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
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            echo json_encode(['error' => 'Folder name is required']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO folders (user_id, name) VALUES (?, ?)');
        $stmt->execute([$user_id, $name]);

        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

    } elseif ($action === 'rename') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || !$name) {
            echo json_encode(['error' => 'Folder ID and name are required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE folders SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $id, $user_id]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'Missing folder ID']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);

        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['error' => 'Invalid action']);
    }

} else {
    echo json_encode(['error' => 'Method not allowed']);
}
