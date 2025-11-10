<?php
// db.php - povezava z bazo
$host = "localhost";
$db = "ucilnica";
$user = "root";
$pass = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Napaka pri povezavi: " . $e->getMessage());
}

// ---- Funkcija za pridobitev izostankov glede na uporabnika ----
function getIzostanki($conn, $userRole, $username = '') {
    if ($userRole == 'administrator') {
        $stmt = $conn->prepare("SELECT * FROM izostanki ORDER BY datum DESC");
    } elseif ($userRole == 'ucitelj') {
        $stmt = $conn->prepare("SELECT * FROM izostanki WHERE ucitelj = :ucitelj ORDER BY datum DESC");
        $stmt->bindParam(':ucitelj', $username);
    } else { // učenec
        $stmt = $conn->prepare("SELECT * FROM izostanki WHERE 1 ORDER BY datum DESC"); 
        // Tu lahko dodamo filtriranje glede na učenčevo ID če imamo ločeno tabelo
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Nastavimo uporabniško vlogo (za test) ----
$userRole = 'administrator'; // administrator, ucitelj, ucenec
$username = 'Janez Novak';    // če je učitelj, filtriramo po njegovem imenu

$izostanki = getIzostanki($conn, $userRole, $username);

// ---- Funkcija za povzetek ----
function getSummary($izostanki) {
    $skupaj = count($izostanki);
    $opravičeni = 0;
    $neopravičeni = 0;
    foreach($izostanki as $i) {
        if($i['vrsta_izostanka'] == 'opravičen') $opravičeni++;
        if($i['vrsta_izostanka'] == 'neopravičen') $neopravičeni++;
    }
    return "Skupaj izostankov: $skupaj ($opravičeni opravičenih, $neopravičeni neopravičenih)";
}
?>

<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Pregled izostankov</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; color: #333; }
        h1 { text-align: center; }
        .summary { background-color: #e0f7fa; padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #0080f7; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .opravičen { color: green; font-weight: bold; }
        .neopravičen { color: red; font-weight: bold; }
        .napovedan { color: orange; font-weight: bold; }
        .potrjeno { color: green; }
        .nepotrjeno { color: red; }
    </style>
</head>
<body>

<h1>📅 Pregled izostankov (<?php echo ucfirst($userRole); ?>)</h1>

<div class="summary">
    <?php echo getSummary($izostanki); ?>
</div>

<table>
    <thead>
        <tr>
            <th>Datum</th>
            <th>Predmet / ura</th>
            <th>Vrsta izostanka</th>
            <th>Razlog</th>
            <th>Učitelj</th>
            <th>Status potrditve</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($izostanki as $row): ?>
        <tr>
            <td><?php echo $row['datum']; ?></td>
            <td><?php echo $row['predmet'] . ' ' . $row['ura']; ?></td>
            <td class="<?php echo $row['vrsta_izostanka']; ?>"><?php echo $row['vrsta_izostanka']; ?></td>
            <td><?php echo $row['razlog']; ?></td>
            <td><?php echo $row['ucitelj']; ?></td>
            <td class="<?php echo ($row['status_potrditve']=='potrjeno')?'potrjeno':'nepotrjeno'; ?>"><?php echo ucfirst($row['status_potrditve']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
