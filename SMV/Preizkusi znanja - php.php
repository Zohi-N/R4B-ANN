<?php
session_start();

// -------------------------
// Nastavitve baze podatkov
// -------------------------
$host = "localhost";
$dbname = "ucilnica";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Napaka pri povezavi z bazo: " . $e->getMessage());
}

// -------------------------
// Simulacija prijave
// -------------------------
// Vloga: admin, ucitelj, ucenec
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'ucenec'; // spremeni za test: 'admin' ali 'ucitelj'
}
$role = $_SESSION['role'];

function getTests($pdo, $tip) {
    $stmt = $pdo->prepare("SELECT * FROM testi WHERE tip=:tip ORDER BY datum ASC");
    $stmt->execute(['tip'=>$tip]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Razpored testov – Spletna učilnica</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fa; margin: 0; padding: 20px; color: #333; }
.container { max-width: 900px; margin: 0 auto; background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1 { text-align: center; color: #2c3e50; margin-bottom: 10px; }
.subtitle { text-align: center; color: #555; margin-bottom: 25px; font-size: 1.05em; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 30px; }
th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; }
th { background-color: #3498db; color: white; font-weight: bold; }
tr:hover { background-color: #f9fbfd; }
.section-title { color: #2c3e50; margin-top: 30px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #3498db; }
@media (max-width: 600px) {
  table, thead, tbody, th, td, tr { display: block; }
  th { display: none; }
  td { border: none; position: relative; padding-left: 50%; text-align: right; }
  td::before { content: attr(data-label); position: absolute; left: 15px; width: 45%; font-weight: bold; color: #2c3e50; }
  tr { margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; }
}
</style>
</head>
<body>
<div class="container">
<h1>🧠 Testi in preverjanja znanja</h1>
<p class="subtitle">Dobrodošli, tvoja vloga je: <strong><?php echo ucfirst($role); ?></strong></p>

<?php if($role=='admin' || $role=='ucitelj'): ?>
<h2 class="section-title">Koledar prihajajočih testov</h2>
<table>
<thead>
<tr><th>Datum</th><th>Predmet</th><th>Vrsta testa</th><th>Učitelj</th><th>Opombe</th></tr>
</thead>
<tbody>
<?php foreach(getTests($pdo,'prihodnji') as $t): ?>
<tr>
<td data-label="Datum"><?php echo $t['datum']; ?></td>
<td data-label="Predmet"><?php echo $t['predmet']; ?></td>
<td data-label="Vrsta testa"><?php echo $t['vrsta']; ?></td>
<td data-label="Učitelj"><?php echo $t['ucitelj']; ?></td>
<td data-label="Opombe"><?php echo $t['opombe']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<h2 class="section-title">Rezultati preteklih preverjanj</h2>
<table>
<thead>
<tr><th>Datum</th><th>Predmet</th><th>Vrsta testa</th><th>Ocena / Točke</th>
<?php if($role=='admin'||$role=='ucitelj') echo '<th>Učenec</th>'; ?>
<th>Povratna informacija</th></tr>
</thead>
<tbody>
<?php foreach(getTests($pdo,'pretekli') as $t): ?>
<tr>
<td data-label="Datum"><?php echo $t['datum']; ?></td>
<td data-label="Predmet"><?php echo $t['predmet']; ?></td>
<td data-label="Vrsta testa"><?php echo $t['vrsta']; ?></td>
<td data-label="Ocena / Točke"><?php echo $t['ocena']; ?></td>
<?php if($role=='admin'||$role=='ucitelj') echo '<td data-label="Učenec">'.$t['ucenec'].'</td>'; ?>
<td data-label="Povratna informacija"><?php echo $t['feedback']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php if($role=='admin'): ?>
<h2 class="section-title">Arhiv preteklih testov</h2>
<table>
<thead>
<tr><th>Datum</th><th>Predmet</th><th>Vrsta testa</th><th>Učitelj</th><th>Opombe</th></tr>
</thead>
<tbody>
<?php foreach(getTests($pdo,'arhiv') as $t): ?>
<tr>
<td data-label="Datum"><?php echo $t['datum']; ?></td>
<td data-label="Predmet"><?php echo $t['predmet']; ?></td>
<td data-label="Vrsta testa"><?php echo $t['vrsta']; ?></td>
<td data-label="Učitelj"><?php echo $t['ucitelj']; ?></td>
<td data-label="Opombe"><?php echo $t['opombe']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

</div>
</body>
</html>
