<?php
// Uporabi postgres superuser (privzeto deluje)
$host = "localhost";
$db   = "smv";
$user = "postgres";    
$pass = "1234";             
$port = "5432";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $pdo->exec("SET NAMES 'UTF8'");
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Napaka pri povezavi z bazo podatkov.");
}
?>