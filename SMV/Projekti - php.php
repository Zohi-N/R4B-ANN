<?php
session_start();

// --- Povezava z bazo ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "solski_portal";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Povezava z bazo ni uspela: " . $conn->connect_error);
}

// --- Preveri uporabnika in vlogo (za demo) ---
if (!isset($_SESSION['user'])) {
    // Za demo, nastavimo privzeto uporabnika
    $_SESSION['user'] = [
        'ime' => 'Janez Novak',
        'vloga' => 'ucenec' // ali 'ucitelj' ali 'administrator'
    ];
}

$user = $_SESSION['user'];
$role = $user['vloga'];

// --- Naloži projekte ---
$projekti_sql = "SELECT * FROM projekti ORDER BY rok_oddaje ASC";
$projekti_result = $conn->query($projekti_sql);

// --- Naloži feedback za učence in učitelje ---
$feedback_sql = "SELECT f.*, p.naslov AS projekt_naslov, u.ime AS ucenec_ime
                 FROM feedback f
                 JOIN projekti p ON f.projekt_id = p.id
                 JOIN users u ON f.user_id = u.id";
$feedback_result = $conn->query($feedback_sql);
?>

<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Šolski Projekti</title>
  <style>
    body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height:1.6; background:#f9f9f9; color:#333; margin:0; padding:20px;}
    header {background:#2c3e50; color:white; padding:20px; text-align:center; border-radius:8px; margin-bottom:30px;}
    h1,h2,h3{color:#2c3e50;}
    .container{max-width:900px; margin:0 auto; background:white; padding:25px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
    table{width:100%; border-collapse:collapse; margin:15px 0;}
    th,td{padding:12px; text-align:left; border-bottom:1px solid #ddd;}
    th{background:#3498db; color:white;}
    tr:hover{background:#f1f9ff;}
    .btn{display:inline-block; background:#3498db; color:white; padding:8px 16px; text-decoration:none; border-radius:4px; font-weight:bold;}
    .btn:hover{background:#2980b9;}
    .feedback{background:#e8f4f8; padding:15px; border-left:4px solid #3498db; margin:15px 0;}
    footer{text-align:center; margin-top:40px; color:#777; font-size:0.9em;}
  </style>
</head>
<body>

<header>
  <h1>🎓 Portal za šolske projekte</h1>
  <p>Prijavljen kot: <strong><?php echo $user['ime']; ?> (<?php echo ucfirst($role); ?>)</strong></p>
</header>

<div class="container">

<?php if($role == 'administrator'): ?>
  <div class="section">
    <h2>🛠 Administrator - upravljanje projektov</h2>
    <p>Dodaj, uredi ali odstrani projekte.</p>
    <a href="#" class="btn">Dodaj projekt</a>
  </div>
<?php endif; ?>

<?php if($role == 'ucitelj'): ?>
  <div class="section">
    <h2>📝 Učitelj - povratna informacija</h2>
    <p>Dodaj ocene in komentarje učencem:</p>
    <?php if($feedback_result->num_rows > 0): ?>
      <?php while($fb = $feedback_result->fetch_assoc()): ?>
        <div class="feedback">
          <strong>Učenec:</strong> <?php echo $fb['ucenec_ime']; ?><br>
          <strong>Projekt:</strong> <?php echo $fb['projekt_naslov']; ?><br>
          <strong>Ocena:</strong> <?php echo $fb['ocena']; ?><br>
          <strong>Komentar:</strong> <?php echo $fb['komentar']; ?>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>Ni še nobenega feedbacka.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if($role == 'ucenec'): ?>
  <div class="section">
    <h2>📋 Trenutni projekti</h2>
    <table>
      <thead>
        <tr>
          <th>Naslov</th>
          <th>Opis</th>
          <th>Rok oddaje</th>
        </tr>
      </thead>
      <tbody>
        <?php if($projekti_result->num_rows > 0): ?>
          <?php while($projekt = $projekti_result->fetch_assoc()): ?>
          <tr>
            <td><?php echo $projekt['naslov']; ?></td>
            <td><?php echo $projekt['opis']; ?></td>
            <td><?php echo date("d. F Y", strtotime($projekt['rok_oddaje'])); ?></td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="3">Ni še nobenih projektov.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="section">
      <h2>📤 Oddaja projekta</h2>
      <p>Oddaj projekt prek uradnega Google Obrazca:</p>
      <a href="https://forms.gle/tvoja-povezava" target="_blank" class="btn">Oddaj projekt tukaj</a>
    </div>
<?php endif; ?>

</div>

<footer>
  <p>© 2025 Spletna učilnica – Portal za projekte </p>
</footer>

</body>
</html>

<?php $conn->close(); ?>
