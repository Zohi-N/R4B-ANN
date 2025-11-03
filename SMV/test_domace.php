<?php
// MAKSIMALNI DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();
require_once 'database.php';

// Zapiši vse napake
$debug_info = [];

if (!isset($_SESSION['uporabnik_id']) || $_SESSION['uporabnik_tip'] !== 'dijak') {
    header('Location: prijava.php');
    exit;
}

$dijak_id = $_SESSION['uporabnik_id'];
$ime = $_SESSION['ime'] ?? 'Dijak';
$priimek = $_SESSION['priimek'] ?? '';
$prva_crka = mb_strtoupper(mb_substr($ime, 0, 1, 'UTF-8'), 'UTF-8');

$uspeh = '';
$napaka = '';

// Ustvari mapo
$upload_dir = 'uploads/domace_naloge/';
$debug_info[] = "Upload dir: " . $upload_dir;
$debug_info[] = "Full path: " . __DIR__ . '/' . $upload_dir;

if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        $debug_info[] = "✓ Mapa uspešno ustvarjena";
    } else {
        $debug_info[] = "✗ NAPAKA: Ne morem ustvariti mape!";
    }
} else {
    $debug_info[] = "✓ Mapa že obstaja";
}

// Preveri pravice
if (is_writable($upload_dir)) {
    $debug_info[] = "✓ Mapa je zapisljiva";
} else {
    $debug_info[] = "✗ NAPAKA: Mapa NI zapisljiva!";
}

// ODDAJA NALOGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oddaj_nalogo'])) {
    $debug_info[] = "=== ZAČETEK ODDAJE ===";
    
    $naloga_id = $_POST['naloga_id'] ?? '';
    $naslov_naloge = $_POST['naslov_naloge'] ?? '';
    $potrdi_povozi = isset($_POST['potrdi_povozi']) && $_POST['potrdi_povozi'] === 'da';
    
    $debug_info[] = "Naloga ID: " . $naloga_id;
    $debug_info[] = "Naslov: " . $naslov_naloge;
    $debug_info[] = "Povozi: " . ($potrdi_povozi ? 'DA' : 'NE');
    
    if (empty($naloga_id)) {
        $napaka = 'Manjka ID naloge!';
        $debug_info[] = "✗ Manjka naloga_id";
    } elseif (!isset($_FILES['datoteka'])) {
        $napaka = 'Datoteka ni bila poslana!';
        $debug_info[] = "✗ $_FILES['datoteka'] ne obstaja";
    } elseif ($_FILES['datoteka']['error'] === UPLOAD_ERR_NO_FILE) {
        $napaka = 'Prosim izberite datoteko za oddajo.';
        $debug_info[] = "✗ UPLOAD_ERR_NO_FILE";
    } else {
        try {
            $debug_info[] = "Preverjam obstoječo oddajo...";
            
            // Preveri obstoječo oddajo
            $stmt = $pdo->prepare("SELECT oddaja_id, datoteka_ime FROM oddaja_naloge WHERE naloga_id = ? AND id_dijaka = ?");
            $stmt->execute([$naloga_id, $dijak_id]);
            $obstojeca_oddaja = $stmt->fetch();
            
            if ($obstojeca_oddaja) {
                $debug_info[] = "✓ Našel obstoječo oddajo: " . $obstojeca_oddaja['oddaja_id'];
            } else {
                $debug_info[] = "✓ Nova oddaja (ni obstoječe)";
            }
            
            if ($obstojeca_oddaja && !$potrdi_povozi) {
                $napaka = 'To nalogo ste že oddali! Za ponovno oddajo označite potrditveno polje.';
                $debug_info[] = "✗ Obstaja, ni potrjeno";
            } else {
                $file = $_FILES['datoteka'];
                
                $debug_info[] = "Ime datoteke: " . $file['name'];
                $debug_info[] = "Velikost: " . $file['size'] . " bytes";
                $debug_info[] = "Tip: " . $file['type'];
                $debug_info[] = "Error: " . $file['error'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Napaka pri nalaganju datoteke. Error code: ' . $file['error']);
                }
                
                if ($file['size'] > 10 * 1024 * 1024) {
                    throw new Exception('Datoteka je prevelika. Maksimalna velikost je 10MB.');
                }
                
                $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                $debug_info[] = "Končnica: " . $file_extension;
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception('Nedovoljen tip datoteke. Dovoljeni so: ' . implode(', ', $allowed_extensions));
                }
                
                // Pripravi ime
                $safe_priimek = preg_replace('/[^a-zA-Z0-9]/', '_', $priimek);
                $safe_ime = preg_replace('/[^a-zA-Z0-9]/', '_', $ime);
                $safe_naslov = preg_replace('/[^a-zA-Z0-9]/', '_', $naslov_naloge);
                $safe_naslov = substr($safe_naslov, 0, 50);
                
                $file_name = $safe_priimek . '_' . $safe_ime . '_' . $safe_naslov . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                $debug_info[] = "Končno ime: " . $file_name;
                $debug_info[] = "Pot: " . $file_path;
                
                // Izbriši staro
                if ($obstojeca_oddaja && !empty($obstojeca_oddaja['datoteka_ime'])) {
                    $old_file = $upload_dir . $obstojeca_oddaja['datoteka_ime'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                        $debug_info[] = "✓ Stara datoteka izbrisana";
                    }
                }
                
                // Premakni datoteko
                $debug_info[] = "Premikam datoteko...";
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception('Napaka pri shranjevanju datoteke. Preveri pravice mape!');
                }
                $debug_info[] = "✓ Datoteka shranjena";
                
                // V BAZO
                if ($obstojeca_oddaja) {
                    $debug_info[] = "Posodabljam zapis v bazi...";
                    $stmt = $pdo->prepare("UPDATE oddaja_naloge 
                                          SET datum_objave = NOW(), 
                                              datoteka_link = ?,
                                              datoteka_ime = ?,
                                              status = 'oddano',
                                              komentar_profesorja = NULL,
                                              datum_pregleda = NULL
                                          WHERE oddaja_id = ?");
                    $stmt->execute([$file_path, $file_name, $obstojeca_oddaja['oddaja_id']]);
                    $uspeh = 'Domača naloga je bila ponovno oddana! ✔';
                    $debug_info[] = "✓ UPDATE uspešen";
                } else {
                    $debug_info[] = "Vstavljam v bazo...";
                    $stmt = $pdo->prepare("INSERT INTO oddaja_naloge (naloga_id, id_dijaka, datum_objave, datoteka_link, datoteka_ime, status) 
                                           VALUES (?, ?, NOW(), ?, ?, 'oddano')");
                    $stmt->execute([$naloga_id, $dijak_id, $file_path, $file_name]);
                    $uspeh = 'Domača naloga je bila uspešno oddana! ✔';
                    $debug_info[] = "✓ INSERT uspešen";
                }
            }
        } catch (Exception $e) {
            $napaka = $e->getMessage();
            $debug_info[] = "✗ EXCEPTION: " . $e->getMessage();
        } catch (PDOException $e) {
            $napaka = 'Napaka pri oddaji: ' . $e->getMessage();
            $debug_info[] = "✗ PDO EXCEPTION: " . $e->getMessage();
        }
    }
}

// Pridobi naloge
try {
    $stmt = $pdo->prepare("SELECT dn.naloga_id, dn.naslov, dn.navodila, dn.datum_objave, dn.rok,
                          p.ime_predmeta,
                          od.oddaja_id, od.datum_objave as datum_oddaje, od.datoteka_link, od.datoteka_ime,
                          od.status, od.komentar_profesorja, od.datum_pregleda
                          FROM domaca_naloga dn
                          JOIN predmet p ON dn.predmet_id = p.predmet_id
                          JOIN dijak_predmet dp ON p.predmet_id = dp.predmet_id
                          LEFT JOIN oddaja_naloge od ON dn.naloga_id = od.naloga_id AND od.id_dijaka = ?
                          WHERE dp.id_dijaka = ?
                          ORDER BY 
                            CASE WHEN od.oddaja_id IS NULL THEN 0 ELSE 1 END,
                            dn.rok ASC,
                            dn.datum_objave DESC");
    $stmt->execute([$dijak_id, $dijak_id]);
    $naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info[] = "✓ Pridobil " . count($naloge) . " nalog";
} catch (PDOException $e) {
    $naloge = [];
    $debug_info[] = "✗ SELECT NALOGE ERROR: " . $e->getMessage();
}

$neodane = [];
$oddane_v_pregledu = [];
$pregledane = [];

foreach ($naloge as $naloga) {
    if (!$naloga['oddaja_id']) {
        $neodane[] = $naloga;
    } elseif ($naloga['status'] === 'oddano') {
        $oddane_v_pregledu[] = $naloga;
    } else {
        $pregledane[] = $naloga;
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8">
  <title>Domače naloge DEBUG | E-Ocene</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f5f5f5; }
    .debug-panel {
      position: fixed;
      top: 10px;
      right: 10px;
      width: 400px;
      max-height: 90vh;
      overflow-y: auto;
      background: #1e1e1e;
      color: #0f0;
      padding: 15px;
      border-radius: 8px;
      font-size: 12px;
      font-family: 'Courier New', monospace;
      z-index: 9999;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    }
    .debug-panel h3 {
      color: #ff0;
      margin-bottom: 10px;
      border-bottom: 2px solid #0f0;
      padding-bottom: 5px;
    }
    .debug-panel div {
      margin: 3px 0;
      padding: 3px;
      border-left: 2px solid #0f0;
      padding-left: 8px;
    }
    .debug-panel .error {
      color: #f00;
      border-left-color: #f00;
      font-weight: bold;
    }
    .header {
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      color: white;
      padding: 20px;
      text-align: center;
    }
    .container {
      max-width: 800px;
      margin: 20px auto;
      padding: 20px;
      background: white;
      border-radius: 8px;
    }
    .sporocilo {
      padding: 15px;
      margin: 10px 0;
      border-radius: 5px;
    }
    .uspeh { background: #d4edda; color: #155724; }
    .napaka { background: #f8d7da; color: #721c24; }
    .form-group { margin: 15px 0; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .btn {
      background: #8884FF;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }
    .btn:hover { background: #7774ee; }
  </style>
</head>
<body>

<div class="debug-panel">
  <h3>🐛 DEBUG INFO</h3>
  <?php foreach ($debug_info as $info): ?>
    <div class="<?php echo (strpos($info, '✗') !== false) ? 'error' : ''; ?>">
      <?php echo htmlspecialchars($info); ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="header">
  <h1>DOMAČE NALOGE - DEBUG MODE</h1>
  <p>Dijak: <?php echo htmlspecialchars($ime . ' ' . $priimek); ?></p>
</div>

<div class="container">
  <?php if ($uspeh): ?>
    <div class="sporocilo uspeh"><?php echo htmlspecialchars($uspeh); ?></div>
  <?php endif; ?>

  <?php if ($napaka): ?>
    <div class="sporocilo napaka"><?php echo htmlspecialchars($napaka); ?></div>
  <?php endif; ?>

  <h2>TEST ODDAJA NALOGE</h2>
  
  <?php if (!empty($neodane)): ?>
    <?php $naloga = $neodane[0]; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="naloga_id" value="<?php echo $naloga['naloga_id']; ?>">
      <input type="hidden" name="naslov_naloge" value="<?php echo htmlspecialchars($naloga['naslov']); ?>">
      
      <div class="form-group">
        <label>Naloga: <?php echo htmlspecialchars($naloga['naslov']); ?></label>
        <label>Predmet: <?php echo htmlspecialchars($naloga['ime_predmeta']); ?></label>
      </div>

      <div class="form-group">
        <label>Izberi datoteko:</label>
        <input type="file" name="datoteka" required accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar">
      </div>

      <button type="submit" name="oddaj_nalogo" class="btn">ODDAJ NALOGO</button>
    </form>
  <?php else: ?>
    <p>Ni nalog za oddajo. Profesor mora najprej dodati nalogo!</p>
  <?php endif; ?>

  <hr style="margin: 30px 0;">
  
  <h3>Statistika:</h3>
  <p>📝 Neodanih: <?php echo count($neodane); ?></p>
  <p>⏳ V pregledu: <?php echo count($oddane_v_pregledu); ?></p>
  <p>✓ Pregledanih: <?php echo count($pregledane); ?></p>
</div>

</body>
</html>