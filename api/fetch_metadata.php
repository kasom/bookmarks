<?php
// api/fetch_metadata.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$url = trim($_POST['url'] ?? '');
if (!$url) {
    echo json_encode(['error' => 'URL is required']);
    exit;
}

$scheme = parse_url($url, PHP_URL_SCHEME);
if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($scheme ?: ''), ['http', 'https'])) {
    echo json_encode(['error' => 'Invalid URL or unsupported protocol (must be http or https)']);
    exit;
}

$host = parse_url($url, PHP_URL_HOST);
if (!$host) {
    echo json_encode(['error' => 'Could not parse hostname']);
    exit;
}

// SSRF Protection: Resolve hostname to IP and validate it is not private/reserved
$ip = gethostbyname($host);
if (!$ip || $ip === $host) {
    echo json_encode(['error' => 'Could not resolve hostname']);
    exit;
}

if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    echo json_encode(['error' => 'Access to private or reserved IP addresses is forbidden']);
    exit;
}

// Fetch content safely (limit size and timeout)
$ctx = stream_context_create([
    'http' => [
        'timeout' => 3.0, // 3 seconds timeout
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) BookmarksSystem/1.0\r\n",
        'max_redirects' => 3,
        'follow_location' => 1
    ]
]);

// Read up to 512KB of the page to avoid downloading large files (e.g. ISOs, huge PDFs)
$html = @file_get_contents($url, false, $ctx, 0, 524288);
if ($html === false) {
    echo json_encode(['error' => 'Failed to fetch the URL']);
    exit;
}

// Parse Title
$title = '';
if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches)) {
    $title = trim($matches[1]);
}

// Parse Description
$description = '';
if (preg_match('/<meta\s+[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
    $description = trim($matches[1]);
} elseif (preg_match('/<meta\s+[^>]*content=["\'](.*?)["\'][^>]*name=["\']description["\']/is', $html, $matches)) {
    $description = trim($matches[1]);
}

// Clean and decode html entities
$title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$title = preg_replace('/\s+/', ' ', $title);
$title = mb_substr($title, 0, 200, 'UTF-8');

$description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$description = preg_replace('/\s+/', ' ', $description);
$description = mb_substr($description, 0, 500, 'UTF-8');

echo json_encode([
    'success' => true,
    'title' => $title,
    'description' => $description
]);
