php<?php
require_once 'database.php';

// Nastavi isto geslo za VSE profesorje in dijake
$geslo = "geslo12345!";
$hash = password_hash($geslo, PASSWORD_DEFAULT);

try {
    // Posodobi vse profesorje
    $stmt = $pdo->prepare("UPDATE profesorji SET geslo = :geslo");
    $stmt->execute(['geslo' => $hash]);
    echo "Posodobljeno " . $stmt->rowCount() . " profesorjev\n";
    
    // Posodobi vse dijake
    $stmt = $pdo->prepare("UPDATE dijaki SET geslo = :geslo");
    $stmt->execute(['geslo' => $hash]);
    echo "Posodobljeno " . $stmt->rowCount() . " dijakov\n";
    
    echo "\nVsi uporabniki imajo zdaj geslo: $geslo\n";
    
} catch (PDOException $e) {
    echo "Napaka: " . $e->getMessage();
}
?>