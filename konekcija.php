<?php
$url = getenv('MYSQL_URL');
if ($url) {
    $parts = parse_url($url);
    $host = $parts['host'];
    $user = $parts['user'];
    $pass = $parts['pass'] ?? '';
    $db   = ltrim($parts['path'], '/');
    $port = $parts['port'] ?? 3306;
} else {
    $host = getenv('MYSQLHOST')     ?: 'localhost';
    $user = getenv('MYSQLUSER')     ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: '';
    $db   = getenv('MYSQLDATABASE') ?: 'kjfs';
    $port = (int)(getenv('MYSQLPORT') ?: 3306);
}

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die("Neuspešna konekcija na bazu podataka: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>
