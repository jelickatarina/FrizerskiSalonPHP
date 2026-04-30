<?php
$conn = new mysqli(
    getenv('MYSQLHOST')     ?: 'localhost',
    getenv('MYSQLUSER')     ?: 'root',
    getenv('MYSQLPASSWORD') ?: '',
    getenv('MYSQLDATABASE') ?: 'kjfs',
    (int)(getenv('MYSQLPORT') ?: 3306)
);
if ($conn->connect_error) {
    die("Neuspešna konekcija na bazu podataka: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>