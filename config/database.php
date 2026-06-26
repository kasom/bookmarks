<?php
// config/database.php
$ini = parse_ini_file('/etc/bookmarks.ini');

if (!$ini) {
    die('Database config file not found: /etc/bookmarks.ini');
}

$host = $ini['host'];
$dbname = $ini['dbname'];
$username = $ini['username'];
$password = $ini['password'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}