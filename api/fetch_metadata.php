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

function resolve_relative_url(string $base_url, string $relative_url): string {
    if (parse_url($relative_url, PHP_URL_SCHEME) != '') {
        return $relative_url;
    }

    $parts = parse_url($base_url);
    $scheme = $parts['scheme'] ?? 'http';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    if (substr($relative_url, 0, 2) === '//') {
        return $scheme . ':' . $relative_url;
    }

    if (substr($relative_url, 0, 1) === '/') {
        return $scheme . '://' . $host . $port . $relative_url;
    }

    $path = $parts['path'] ?? '/';
    $dir = dirname($path);
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }
    return $scheme . '://' . $host . $port . '/' . ltrim($dir . '/' . $relative_url, '/');
}

function fetch_metadata_safely(string $url): array {
    $redirect_count = 0;
    $max_redirects = 3;
    $current_url = $url;

    while ($redirect_count <= $max_redirects) {
        $parts = parse_url($current_url);
        if (!$parts || !isset($parts['host']) || !isset($parts['scheme'])) {
            return ['success' => false, 'error' => 'Invalid URL structure'];
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'])) {
            return ['success' => false, 'error' => 'Unsupported URL scheme (must be http or https)'];
        }

        $host = $parts['host'];
        $ip = gethostbyname($host);
        if (!$ip || $ip === $host) {
            return ['success' => false, 'error' => 'Could not resolve hostname'];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['success' => false, 'error' => 'Access to private or reserved IP addresses is forbidden'];
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        
        $ip_url = $scheme . '://' . $ip . $port . $path . $query . $fragment;

        $ctx_options = [
            'http' => [
                'timeout' => 3.0,
                'header' => "User-Agent: facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_voiced.html)\r\nHost: " . $host . "\r\nConnection: close\r\n",
                'follow_location' => 0,
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
        $html = @file_get_contents($ip_url, false, $ctx, 0, 524288);
        if ($html === false) {
            return ['success' => false, 'error' => 'Failed to fetch the URL'];
        }

        // Check status code and Location header for manual redirect handling
        $redirect_url = null;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^Location:\s*(.*)$/i', $header, $matches)) {
                    $redirect_url = trim($matches[1]);
                    break;
                }
            }
            
            $is_redirect = false;
            if (isset($http_response_header[0]) && preg_match('/HTTP\/\d\.\d\s+3\d\d/', $http_response_header[0])) {
                $is_redirect = true;
            }

            if ($is_redirect && $redirect_url) {
                $current_url = resolve_relative_url($current_url, $redirect_url);
                $redirect_count++;
                continue;
            }
        }

        return ['success' => true, 'html' => $html];
    }

    return ['success' => false, 'error' => 'Too many redirects'];
}

$fetch_res = fetch_metadata_safely($url);
if (!$fetch_res['success']) {
    echo json_encode(['error' => $fetch_res['error']]);
    exit;
}
$html = $fetch_res['html'];

// Parse Title
$title = '';
if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches)) {
    $title = trim($matches[1]);
}
if (!$title && preg_match('/<meta\s+[^>]*property=["\']og:title["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
    $title = trim($matches[1]);
} elseif (!$title && preg_match('/<meta\s+[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:title["\']/is', $html, $matches)) {
    $title = trim($matches[1]);
}

// Parse Description
$description = '';
if (preg_match('/<meta\s+[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
    $description = trim($matches[1]);
} elseif (preg_match('/<meta\s+[^>]*content=["\'](.*?)["\'][^>]*name=["\']description["\']/is', $html, $matches)) {
    $description = trim($matches[1]);
}

// Open Graph fallback for description
if (!$description && preg_match('/<meta\s+[^>]*property=["\']og:description["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
    $description = trim($matches[1]);
} elseif (!$description && preg_match('/<meta\s+[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:description["\']/is', $html, $matches)) {
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
