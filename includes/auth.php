<?php
// includes/auth.php
session_start();

require_once __DIR__ . '/../config/database.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function get_current_user_id(): ?int {
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

function get_current_username(): ?string {
    return is_logged_in() ? ($_SESSION['username'] ?? null) : null;
}

function is_admin(): bool {
    return is_logged_in() && (int)($_SESSION['is_admin'] ?? 0) === 1;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /bookmarks/login.php');
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: /bookmarks/index.php');
        exit;
    }
}

function login(string $username, string $password): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, password_hash, approved, disabled, is_admin FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    if ((int)$user['disabled'] === 1) {
        return ['success' => false, 'error' => 'Your account has been disabled.'];
    }

    if ((int)$user['approved'] !== 1) {
        return ['success' => false, 'error' => 'Your account is pending approval. Please wait.'];
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = (int)$user['is_admin'];
    return ['success' => true];
}

function logout(): void {
    session_destroy();
    header('Location: /bookmarks/login.php');
    exit;
}

function register(string $username, string $email, string $password): array {
    global $pdo;
    $errors = [];

    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be 3-50 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if (preg_match('/[<>"\'&]/', $username)) {
        $errors[] = 'Username contains invalid characters.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'errors' => ['Username or email already exists.']];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, approved) VALUES (?, ?, ?, 0)');
    $stmt->execute([$username, $email, $hash]);
    return ['success' => true];
}

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
