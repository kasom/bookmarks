<?php
// logout.php
require_once __DIR__ . '/includes/auth.php';
session_destroy();
header('Location: /bookmarks/login.php');
exit;
