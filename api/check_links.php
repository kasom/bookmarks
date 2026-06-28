<?php
// api/check_links.php
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
    // Fetch all user bookmarks
    $stmt = $pdo->prepare('SELECT id, title, url FROM bookmarks WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    $bookmarks = $stmt->fetchAll();
    echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);

} elseif ($action === 'check') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Verify ownership
    $stmt = $pdo->prepare('SELECT url FROM bookmarks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user_id]);
    $bookmark = $stmt->fetch();
    
    if (!$bookmark) {
        echo json_encode(['error' => 'Bookmark not found']);
        exit;
    }

    $res = check_url_status($bookmark['url']);
    echo json_encode(array_merge(['success' => true], $res));

} else {
    echo json_encode(['error' => 'Invalid action']);
}

function check_url_status(string $url): array {
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host']) || !isset($parts['scheme'])) {
        return ['status' => 'broken', 'status_code' => 0, 'error' => 'Invalid URL structure'];
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'])) {
        return ['status' => 'broken', 'status_code' => 0, 'error' => 'Unsupported URL scheme'];
    }

    $host = $parts['host'];
    $ip = gethostbyname($host);
    if (!$ip || $ip === $host) {
        return ['status' => 'broken', 'status_code' => 0, 'error' => 'Could not resolve hostname'];
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['status' => 'broken', 'status_code' => 0, 'error' => 'Access to private or reserved IP addresses is forbidden'];
    }

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    
    $ip_url = $scheme . '://' . $ip . $port . $path . $query . $fragment;

    // 1. Try HEAD request first
    $res = run_http_request($ip_url, $host, $scheme, 'HEAD');
    
    // 2. Fallback to GET if HEAD is forbidden/blocked (some sites reject HEAD)
    if ($res['status'] === 'broken' && in_array($res['status_code'], [400, 403, 405])) {
        $res = run_http_request($ip_url, $host, $scheme, 'GET');
    }

    return $res;
}

function run_http_request(string $ip_url, string $host, string $scheme, string $method): array {
    $ctx_options = [
        'http' => [
            'method' => $method,
            'timeout' => 3.0,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nHost: " . $host . "\r\nConnection: close\r\n",
            'follow_location' => 0, // Manual check (Redirects (3xx) are OK as they mean URL exists)
            'ignore_errors' => true
        ]
    ];

    if ($scheme === 'https') {
        $ctx_options['ssl'] = [
            'peer_name' => $host,
            'verify_peer' => true,
            'verify_peer_name' => true
        ];
    }

    $ctx = stream_context_create($ctx_options);
    
    // Read 0-1 byte only to save download bandwidth
    $html = @file_get_contents($ip_url, false, $ctx, 0, 1);

    if (!isset($http_response_header) || empty($http_response_header)) {
        return ['status' => 'broken', 'status_code' => 0, 'error' => 'Connection failed or timeout'];
    }

    $status_line = $http_response_header[0];
    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/i', $status_line, $matches)) {
        $code = (int)$matches[1];
        
        // 2xx and 3xx are considered functional
        if ($code >= 200 && $code < 400) {
            return ['status' => 'ok', 'status_code' => $code, 'error' => ''];
        } else {
            return ['status' => 'broken', 'status_code' => $code, 'error' => 'HTTP ' . $code];
        }
    }

    return ['status' => 'broken', 'status_code' => 0, 'error' => 'Invalid server response'];
}
