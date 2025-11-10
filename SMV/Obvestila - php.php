<?php
// CONFIG: podatki za povezavo z bazo
$host = "localhost";
$db   = "ucilnica";
$user = "root";
$pass = "";

// Povezava z bazo
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Povezava z bazo ni uspela: " . $conn->connect_error);
}

// ROLE: administrator / ucitelj / ucenec
// V realnem sistemu bi to prišlo iz prijave uporabnika
$role = "ucitelj"; // primer, spremeni po želji

// Poizvedba glede na vlogo
$sql = "SELECT * FROM obvestila";

// Če je učenec, pokažemo samo pomembna in obična obvestila, brez administrativnih opomb
if ($role == "ucenec") {
    $sql .= " WHERE 1"; // trenutno vsi vidijo vsa obvestila
}

// Administrator vidi vse, učitelj lahko dodaja svoja obvestila
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Obvestila učilnice</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      margin: 0;
      padding: 20px;
      color: #333;
    }
    h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
    .obvestila { max-width: 800px; margin: 0 auto; }
    .obvestilo { background: white; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .pomembno { background-color: #fff8e1; border-left: 4px solid #ffc107; }
    .naslov { font-size: 1.3em; font-weight: bold; margin-bottom: 6px; color: #1a237e; }
    .pomembno .naslov { color: #d32f2f; }
    .meta { font-size: 0.9em; color: #666; margin-bottom: 8px; }
    .opis { line-height: 1.5; }
    .ikona { display: inline-block; margin-right: 6px; color: #d32f2f; font-weight: bold; }
    .dodaj-obvestilo { margin-bottom: 20px; }
    .dodaj-obvestilo form { display: flex; flex-direction: column; gap: 10px; }
  </style>
</head>
<body>

  <h1>Obvestila učilnice</h1>

  <div class="obvestila">

    <?php
    // Če je administrator ali učitelj, omogočimo dodajanje novega obvestila
    if ($role == "administrator" || $role == "ucitelj") {
        echo '<div class="dodaj-obvestilo">
                <h2>Dodaj novo obvestilo</h2>
                <form method="post">
                    <input type="text" name="naslov" placeholder="Naslov" required>
                    <input type="text" name="avtor" placeholder="Avtor" required>
                    <input type="date" name="datum" required>
                    <textarea name="opis" placeholder="Opis obvestila" required></textarea>
                    <label><input type="checkbox" name="pomembno"> Pomembno</label>
                    <button type="submit" name="dodaj">Dodaj obvestilo</button>
                </form>
              </div>';
    }

    // Dodajanje obvestila v bazo
    if (isset($_POST['dodaj'])) {
        $naslov = $conn->real_escape_string($_POST['naslov']);
        $avtor = $conn->real_escape_string($_POST['avtor']);
        $datum = $_POST['datum'];
        $opis = $conn->real_escape_string($_POST['opis']);
        $pomembno = isset($_POST['pomembno']) ? 1 : 0;

        $conn->query("INSERT INTO obvestila (naslov, avtor, datum, opis, pomembno) VALUES ('$naslov','$avtor','$datum','$opis','$pomembno')");
        echo "<p style='color:green;'>Obvestilo dodano!</p>";
        // Osveži stran, da se prikaže novo obvestilo
        echo "<meta http-equiv='refresh' content='0'>";
    }

    // Prikaz obvestil
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $class = $row['pomembno'] ? "obvestilo pomembno" : "obvestilo";
            $ikona = $row['pomembno'] ? "❗" : "";
            echo "<div class='$class'>
                    <div class='naslov'><span class='ikona'>$ikona</span> {$row['naslov']}</div>
                    <div class='meta'>Datum: {$row['datum']} | Avtor: {$row['avtor']}</div>
                    <div class='opis'>{$row['opis']}</div>
                  </div>";
        }
    } else {
        echo "<p>Trenutno ni obvestil.</p>";
    }

    $conn->close();
    ?>

  </div>

</body>
</html>
