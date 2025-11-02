<?php
$host = "localhost";
$db   = "smv";
$user = "myuser";
$pass = "mypassword";
$port = "5432";
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
?>