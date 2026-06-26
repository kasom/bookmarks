<?php
// includes/auth.php

// Secure session configuration (must be before session_start())
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);

session_start();

require_once __DIR__ . '/../config/database.php';

// Rate limit config
define('RATE_LOGIN_MAX_ATTEMPTS', 5);
define('RATE_LOGIN_LOCK_MINUTES', 15);
define('RATE_REGISTER_MAX_ATTEMPTS', 3);
define('RATE_REGISTER_LOCK_MINUTES', 60);

function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function check_rate_limit(string $endpoint, string $identifier = ''): array {
    global $pdo;
    $ip = get_client_ip();

    $stmt = $pdo->prepare('SELECT attempts, locked_until FROM rate_limits WHERE endpoint = ? AND ip_address = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$endpoint, $ip]);
    $record = $stmt->fetch();

    if ($record) {
        $locked_until = $record['locked_until'];
        if ($locked_until && $locked_until > date('Y-m-d H:i:s')) {
            $remaining = (strtotime($locked_until) - time()) / 60;
            return ['locked' => true, 'remaining_minutes' => ceil($remaining)];
        }
        if ($locked_until || (int)$record['attempts'] >= ($endpoint === 'login' ? RATE_LOGIN_MAX_ATTEMPTS : RATE_REGISTER_MAX_ATTEMPTS)) {
            // Reset after lock period expired
            $pdo->prepare('DELETE FROM rate_limits WHERE endpoint = ? AND ip_address = ?')->execute([$endpoint, $ip]);
        }
    }

    return ['locked' => false];
}

function record_rate_attempt(string $endpoint, string $identifier = ''): void {
    global $pdo;
    $ip = get_client_ip();
    $max_attempts = $endpoint === 'login' ? RATE_LOGIN_MAX_ATTEMPTS : RATE_REGISTER_MAX_ATTEMPTS;
    $lock_minutes = $endpoint === 'login' ? RATE_LOGIN_LOCK_MINUTES : RATE_REGISTER_LOCK_MINUTES;

    $stmt = $pdo->prepare('SELECT id, attempts FROM rate_limits WHERE endpoint = ? AND ip_address = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$endpoint, $ip]);
    $record = $stmt->fetch();

    if ($record) {
        $new_attempts = (int)$record['attempts'] + 1;
        $locked_until = $new_attempts >= $max_attempts ? date('Y-m-d H:i:s', strtotime("+$lock_minutes minutes")) : null;
        $pdo->prepare('UPDATE rate_limits SET attempts = ?, last_attempt = NOW(), locked_until = ? WHERE id = ?')->execute([$new_attempts, $locked_until, $record['id']]);
    } else {
        $locked_until = null;
        $pdo->prepare('INSERT INTO rate_limits (endpoint, ip_address, identifier, attempts, last_attempt) VALUES (?, ?, ?, 1, NOW())')->execute([$endpoint, $ip, $identifier]);
    }
}

function clear_rate_limit(string $endpoint): void {
    global $pdo;
    $ip = get_client_ip();
    $pdo->prepare('DELETE FROM rate_limits WHERE endpoint = ? AND ip_address = ?')->execute([$endpoint, $ip]);
}

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

    // Check rate limit
    $rate = check_rate_limit('login', $username);
    if ($rate['locked']) {
        return ['success' => false, 'error' => 'Too many failed attempts. Try again in ' . $rate['remaining_minutes'] . ' minute(s).'];
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, approved, disabled, is_admin FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_rate_attempt('login', $username);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    if ((int)$user['disabled'] === 1) {
        return ['success' => false, 'error' => 'Your account has been disabled.'];
    }

    if ((int)$user['approved'] !== 1) {
        return ['success' => false, 'error' => 'Your account is pending approval. Please wait.'];
    }

    // Prevent session fixation
    session_regenerate_id(true);

    clear_rate_limit('login');
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

    // Check rate limit
    $rate = check_rate_limit('register');
    if ($rate['locked']) {
        return ['success' => false, 'errors' => ['Too many registration attempts. Try again in ' . $rate['remaining_minutes'] . ' minute(s).']];
    }

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
        record_rate_attempt('register');
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