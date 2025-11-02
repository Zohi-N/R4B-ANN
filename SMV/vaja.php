
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Hitra Diagnoza Baze</h2>";

// Tvoje nastavitve
$host = "localhost";
$db   = "mydb";
$user = "myuser";
$pass = "mypassword";
$port = "5432";

echo "<strong>Uporabljam nastavitve:</strong><br>";
echo "Host: $host<br>";
echo "Database: $db<br>";
echo "User: $user<br>";
echo "Port: $port<br><hr>";

// Test 1: Ali obstaja pdo_pgsql
if (!extension_loaded('pdo_pgsql')) {
    die("❌ PROBLEM: PDO PostgreSQL driver ni nameščen!<br>Namesti: sudo apt install php-pgsql");
}
echo "✅ PDO PostgreSQL driver je nameščen<br><br>";

// Test 2: Povezava BREZ options
echo "<strong>Test povezave BREZ options:</strong><br>";
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass);
    echo "✅ Povezava brez options DELUJE!<br><br>";
} catch (PDOException $e) {
    echo "❌ Napaka: " . $e->getMessage() . "<br><br>";
    die("USTAVI SE TUKAJ - problem je v osnovni povezavi!");
}

// Test 3: Povezava Z options
echo "<strong>Test povezave Z options:</strong><br>";
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;options='--client_encoding=UTF8'";
    $pdo2 = new PDO($dsn, $user, $pass);
    echo "✅ Povezava z options DELUJE!<br><br>";
} catch (PDOException $e) {
    echo "❌ Napaka z options: " . $e->getMessage() . "<br><br>";
    echo "Uporabi verzijo BREZ options!<br>";
}

// Test 4: Preveri tabele
echo "<strong>Preverjam tabele:</strong><br>";
try {
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "✅ Najdenih " . count($tables) . " tabel:<br>";
        foreach ($tables as $t) {
            echo "- $t<br>";
        }
    } else {
        echo "⚠️ Baza obstaja ampak je prazna!<br>";
    }
} catch (PDOException $e) {
    echo "❌ " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>ZAKLJUČEK:</h3>";
echo "Če vidiš ta tekst, database.php bi moral delovati!<br>";
echo "Kopiraj spodnjo kodo v svoj database.php:<br><br>";

echo "<textarea rows='20' cols='80' readonly>";
echo '<?php
$host = "localhost";
$db   = "mydb";
$user = "myuser";
$pass = "mypassword";
$port = "5432";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $pdo->exec("SET NAMES \'UTF8\'");
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    die("Napaka pri povezavi z bazo podatkov.");
}
?>';
echo "</textarea>";
?>