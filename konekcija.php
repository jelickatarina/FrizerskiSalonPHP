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

// Auto-migrate: add Potvrdjeno column if missing
if (!@$conn->query("SELECT 1 FROM termin WHERE 1=0")) {
    // termin table doesn't exist, skip
} else {
    $colCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='termin' AND COLUMN_NAME='Potvrdjeno'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE termin ADD COLUMN Potvrdjeno TINYINT NOT NULL DEFAULT 1");
        $conn->query("UPDATE termin SET Potvrdjeno=1 WHERE Potvrdjeno IS NULL");
    }
    $colCheck2 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='termin' AND COLUMN_NAME='Otkazano'");
    if (!$colCheck2 || $colCheck2->num_rows === 0) {
        $conn->query("ALTER TABLE termin ADD COLUMN Otkazano TINYINT NOT NULL DEFAULT 0");
    }
}
?>
