<?php
// api/export.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user_id = get_current_user_id();

// 1. Fetch folders
$folders = get_folders($user_id);

// 2. Fetch all bookmarks owned by the user
$bookmarks = get_bookmarks($user_id, 'all');

// Group bookmarks by folder_id
$bookmarks_by_folder = [];
$root_bookmarks = [];

foreach ($bookmarks as $bm) {
    if ($bm['folder_id']) {
        $bookmarks_by_folder[(int)$bm['folder_id']][] = $bm;
    } else {
        $root_bookmarks[] = $bm;
    }
}

// Set download headers for Netscape HTML format
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="bookmarks_export.html"');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
echo "<!-- This is an automatically generated file. -->\n";
echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
echo "<TITLE>Bookmarks</TITLE>\n";
echo "<H1>Bookmarks</H1>\n";
echo "<DL><p>\n";

// Helper function to output a single bookmark in Netscape format
function output_bookmark_item($bm) {
    $url = htmlspecialchars($bm['url'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($bm['title'], ENT_QUOTES, 'UTF-8');
    $date = strtotime($bm['created_at']);
    $private = $bm['visibility'] === 'private' ? '1' : '0';
    $tags = htmlspecialchars($bm['tags'] ?? '', ENT_QUOTES, 'UTF-8');
    
    echo "        <DT><A HREF=\"" . $url . "\" ADD_DATE=\"" . $date . "\" PRIVATE=\"" . $private . "\" TAGS=\"" . $tags . "\">" . $title . "</A>\n";
    if ($bm['description']) {
        $desc = htmlspecialchars($bm['description'], ENT_QUOTES, 'UTF-8');
        echo "        <DD>" . $desc . "\n";
    }
}

// Output folders and their bookmarks
foreach ($folders as $f) {
    $folder_name = htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8');
    echo "    <DT><H3>" . $folder_name . "</H3>\n";
    echo "    <DL><p>\n";
    
    $f_id = (int)$f['id'];
    if (isset($bookmarks_by_folder[$f_id])) {
        foreach ($bookmarks_by_folder[$f_id] as $bm) {
            output_bookmark_item($bm);
        }
    }
    
    echo "    </DL><p>\n";
}

// Output root level bookmarks
foreach ($root_bookmarks as $bm) {
    output_bookmark_item($bm);
}

echo "</DL><p>\n";
