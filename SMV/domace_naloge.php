<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['uporabnik_id']) || !isset($_SESSION['uporabnik_tip'])) {
    header('Location: prijava.php');
    exit;
}

$uporabnik_tip = $_SESSION['uporabnik_tip'];
$uporabnik_id = $_SESSION['uporabnik_id'];
$ime = $_SESSION['ime'];
$priimek = $_SESSION['priimek'];

$napaka = '';
$uspeh = '';

// PROFESOR - Dodaj nalogo
if ($uporabnik_tip === 'profesor' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_nalogo'])) {
    $predmet_id = $_POST['predmet_id'];
    $navodila = $_POST['navodila'];
    $rok = $_POST['rok'];
    
    if (empty($predmet_id) || empty($navodila) || empty($rok)) {
        $napaka = 'Vsa polja so obvezna.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO domača_naloga (predmet_id, navodila, datum_objave, rok) VALUES (:predmet_id, :navodila, NOW(), :rok)");
        $stmt->execute(['predmet_id' => $predmet_id, 'navodila' => $navodila, 'rok' => $rok]);
        $uspeh = 'Naloga dodana!';
    }
}

// PROFESOR - Izbriši nalogo
if ($uporabnik_tip === 'profesor' && isset($_GET['izbrisi'])) {
    $naloga_id = $_GET['izbrisi'];
    $stmt = $pdo->prepare("DELETE FROM oddaja_naloge WHERE naloga_id = :naloga_id");
    $stmt->execute(['naloga_id' => $naloga_id]);
    $stmt = $pdo->prepare("DELETE FROM domača_naloga WHERE naloga_id = :naloga_id");
    $stmt->execute(['naloga_id' => $naloga_id]);
    $uspeh = 'Naloga izbrisana!';
}

// DIJAK - Oddaj nalogo
if ($uporabnik_tip === 'dijak' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oddaj'])) {
    $naloga_id = $_POST['naloga_id'];
    $file = $_FILES['datoteka'];
    
    if ($file['error'] === 0) {
        $stmt = $pdo->prepare("SELECT navodila FROM domača_naloga WHERE naloga_id = :naloga_id");
        $stmt->execute(['naloga_id' => $naloga_id]);
        $naloga = $stmt->fetch();
        
        $naslov = substr($naloga['navodila'], 0, 30);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $novo_ime = $priimek . " " . $ime . " - " . $naslov . "." . $ext;
        
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $pot = $upload_dir . $novo_ime;
        
        $stmt = $pdo->prepare("SELECT oddaja_id, datoteka_link FROM oddaja_naloge WHERE naloga_id = :naloga_id AND id_dijaka = :id_dijaka");
        $stmt->execute(['naloga_id' => $naloga_id, 'id_dijaka' => $uporabnik_id]);
        $obstaja = $stmt->fetch();
        
        if ($obstaja) {
            if (file_exists($obstaja['datoteka_link'])) unlink($obstaja['datoteka_link']);
            move_uploaded_file($file['tmp_name'], $pot);
            $stmt = $pdo->prepare("UPDATE oddaja_naloge SET datum_objave = NOW(), datoteka_link = :pot WHERE oddaja_id = :oddaja_id");
            $stmt->execute(['pot' => $pot, 'oddaja_id' => $obstaja['oddaja_id']]);
            $uspeh = 'Naloga ponovno oddana!';
        } else {
            move_uploaded_file($file['tmp_name'], $pot);
            $stmt = $pdo->prepare("INSERT INTO oddaja_naloge (naloga_id, id_dijaka, datum_objave, datoteka_link) VALUES (:naloga_id, :id_dijaka, NOW(), :pot)");
            $stmt->execute(['naloga_id' => $naloga_id, 'id_dijaka' => $uporabnik_id, 'pot' => $pot]);
            $uspeh = 'Naloga oddana!';
        }
    }
}

// Pridobi podatke
$naloge = [];
$predmeti = [];

if ($uporabnik_tip === 'profesor') {
    $stmt = $pdo->prepare("SELECT DISTINCT p.predmet_id, p.ime_predmeta FROM predmet p JOIN profesor_predmet pp ON p.predmet_id = pp.predmet_id WHERE pp.profesor_id = :profesor_id");
    $stmt->execute(['profesor_id' => $uporabnik_id]);
    $predmeti = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT dn.*, p.ime_predmeta FROM domača_naloga dn JOIN predmet p ON dn.predmet_id = p.predmet_id JOIN profesor_predmet pp ON p.predmet_id = pp.predmet_id WHERE pp.profesor_id = :profesor_id ORDER BY dn.rok DESC");
    $stmt->execute(['profesor_id' => $uporabnik_id]);
    $naloge = $stmt->fetchAll();
    
    foreach ($naloge as &$naloga) {
        $stmt = $pdo->prepare("SELECT on.*, d.ime_dijaka, d.priimek_dijaka FROM oddaja_naloge on JOIN dijaki d ON on.id_dijaka = d.id_dijaka WHERE on.naloga_id = :naloga_id");
        $stmt->execute(['naloga_id' => $naloga['naloga_id']]);
        $naloga['oddaje'] = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->query("SELECT * FROM predmet");
    $predmeti = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT dn.*, p.ime_predmeta FROM domača_naloga dn JOIN predmet p ON dn.predmet_id = p.predmet_id ORDER BY dn.rok DESC");
    $naloge = $stmt->fetchAll();
    
    foreach ($naloge as &$naloga) {
        $stmt = $pdo->prepare("SELECT * FROM oddaja_naloge WHERE naloga_id = :naloga_id AND id_dijaka = :id_dijaka");
        $stmt->execute(['naloga_id' => $naloga['naloga_id'], 'id_dijaka' => $uporabnik_id]);
        $naloga['moja_oddaja'] = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8">
  <title>Domače naloge</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f5f5f5;
    }
    
    .header {
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      color: white;
      padding: 20px;
      text-align: center;
    }
    
    .header h1 {
      margin: 0;
      font-size: 24px;
    }
    
    .nazaj {
      float: left;
      color: white;
      text-decoration: none;
      padding: 5px 15px;
      background: rgba(255,255,255,0.2);
      border-radius: 5px;
    }
    
    .container {
      max-width: 900px;
      margin: 20px auto;
      padding: 20px;
    }
    
    .sporocilo {
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 5px;
    }
    
    .napaka {
      background: #ffcccc;
      color: red;
    }
    
    .uspeh {
      background: #ccffcc;
      color: green;
    }
    
    .box {
      background: white;
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .box h3 {
      margin-top: 0;
      color: #8884FF;
    }
    
    input, select, textarea {
      width: 100%;
      padding: 8px;
      margin: 5px 0 15px 0;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-sizing: border-box;
    }
    
    textarea {
      height: 100px;
      resize: vertical;
    }
    
    button {
      background: #8884FF;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    
    button:hover {
      background: #7374ee;
    }
    
    .naloga {
      background: white;
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 5px;
      border-left: 4px solid #8884FF;
    }
    
    .naloga h4 {
      margin: 0 0 10px 0;
      color: #333;
    }
    
    .info {
      font-size: 13px;
      color: #666;
      margin: 5px 0;
    }
    
    .rok {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: bold;
      margin-top: 5px;
    }
    
    .rok-ok {
      background: #ccffcc;
      color: green;
    }
    
    .rok-ne {
      background: #ffcccc;
      color: red;
    }
    
    .oddaje {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }
    
    .oddaja {
      padding: 8px;
      background: #f9f9f9;
      margin: 5px 0;
      border-radius: 3px;
    }
    
    .gumbi {
      margin-top: 10px;
    }
    
    .gumbi a, .gumbi button {
      display: inline-block;
      padding: 8px 15px;
      margin-right: 5px;
      text-decoration: none;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .btn-izbrisi {
      background: red;
      color: white;
    }
    
    .btn-oddaj {
      background: green;
      color: white;
    }
    
    .status-oddano {
      color: green;
      font-weight: bold;
      margin-top: 10px;
    }
  </style>
</head>
<body>

<div class="header">
  <a href="main page.php" class="nazaj">Nazaj</a>
  <h1>Domače naloge</h1>
</div>

<div class="container">

  <?php if ($napaka): ?>
    <div class="sporocilo napaka"><?php echo $napaka; ?></div>
  <?php endif; ?>

  <?php if ($uspeh): ?>
    <div class="sporocilo uspeh"><?php echo $uspeh; ?></div>
  <?php endif; ?>

  <?php if ($uporabnik_tip === 'profesor'): ?>
    
    <div class="box">
      <h3>Dodaj novo nalogo</h3>
      <form method="POST">
        <label>Predmet:</label>
        <select name="predmet_id" required>
          <option value="">-- Izberi --</option>
          <?php foreach ($predmeti as $p): ?>
            <option value="<?php echo $p['predmet_id']; ?>"><?php echo $p['ime_predmeta']; ?></option>
          <?php endforeach; ?>
        </select>
        
        <label>Navodila:</label>
        <textarea name="navodila" required></textarea>
        
        <label>Rok:</label>
        <input type="datetime-local" name="rok" required>
        
        <button type="submit" name="dodaj_nalogo">Dodaj</button>
      </form>
    </div>

    <h3>Moje naloge:</h3>
    <?php foreach ($naloge as $n): 
      $potekel = strtotime($n['rok']) < time();
    ?>
      <div class="naloga">
        <h4><?php echo $n['ime_predmeta']; ?></h4>
        <div class="info">Objavljeno: <?php echo date('d.m.Y H:i', strtotime($n['datum_objave'])); ?></div>
        <div class="rok <?php echo $potekel ? 'rok-ne' : 'rok-ok'; ?>">
          Rok: <?php echo date('d.m.Y H:i', strtotime($n['rok'])); ?>
        </div>
        <p><?php echo nl2br($n['navodila']); ?></p>
        
        <div class="gumbi">
          <a href="?izbrisi=<?php echo $n['naloga_id']; ?>" class="btn-izbrisi" onclick="return confirm('Izbrisati?')">Izbriši</a>
        </div>
        
        <?php if (!empty($n['oddaje'])): ?>
          <div class="oddaje">
            <strong>Oddaje (<?php echo count($n['oddaje']); ?>):</strong>
            <?php foreach ($n['oddaje'] as $o): ?>
              <div class="oddaja">
                <?php echo $o['ime_dijaka'] . ' ' . $o['priimek_dijaka']; ?> - 
                <?php echo date('d.m.Y H:i', strtotime($o['datum_objave'])); ?> - 
                <a href="<?php echo $o['datoteka_link']; ?>" download>Prenesi</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php else: ?>
    
    <h3>Naloge:</h3>
    <?php foreach ($naloge as $n): 
      $potekel = strtotime($n['rok']) < time();
      $oddano = !empty($n['moja_oddaja']);
    ?>
      <div class="naloga">
        <h4><?php echo $n['ime_predmeta']; ?></h4>
        <div class="info">Objavljeno: <?php echo date('d.m.Y H:i', strtotime($n['datum_objave'])); ?></div>
        <div class="rok <?php echo $potekel ? 'rok-ne' : 'rok-ok'; ?>">
          Rok: <?php echo date('d.m.Y H:i', strtotime($n['rok'])); ?>
        </div>
        <p><?php echo nl2br($n['navodila']); ?></p>
        
        <?php if ($oddano): ?>
          <div class="status-oddano">
            Oddano: <?php echo date('d.m.Y H:i', strtotime($n['moja_oddaja']['datum_objave'])); ?>
          </div>
        <?php endif; ?>
        
        <?php if (!$potekel): ?>
          <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
            <input type="hidden" name="naloga_id" value="<?php echo $n['naloga_id']; ?>">
            <input type="file" name="datoteka" required>
            <button type="submit" name="oddaj" class="btn-oddaj"><?php echo $oddano ? 'Ponovno oddaj' : 'Oddaj'; ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div>

</body>
</html>