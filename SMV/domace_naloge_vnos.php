<?php
session_start();
require_once 'database.php';

// Preveri ali je uporabnik prijavljen kot profesor
if (!isset($_SESSION['uporabnik_id']) || $_SESSION['uporabnik_tip'] !== 'profesor') {
    header('Location: prijava.php');
    exit;
}

$profesor_id = $_SESSION['uporabnik_id'];
$ime = $_SESSION['ime'] ?? 'Profesor';
$priimek = $_SESSION['priimek'] ?? '';
$prva_crka = mb_strtoupper(mb_substr($ime, 0, 1, 'UTF-8'), 'UTF-8');

$uspeh = '';
$napaka = '';

// Dodajanje nove domače naloge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_nalogo'])) {
    $predmet_id = $_POST['predmet_id'] ?? '';
    $naslov = trim($_POST['naslov'] ?? '');
    $navodila = trim($_POST['navodila'] ?? '');
    $rok = $_POST['rok'] ?? '';
    
    if (empty($predmet_id) || empty($naslov) || empty($navodila) || empty($rok)) {
        $napaka = 'Vsa polja so obvezna.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO domača_naloga (predmet_id, naslov, navodila, datum_objave, rok) 
                                   VALUES (:predmet_id, :naslov, :navodila, NOW(), :rok)");
            $stmt->execute([
                'predmet_id' => $predmet_id,
                'naslov' => $naslov,
                'navodila' => $navodila,
                'rok' => $rok
            ]);
            $uspeh = 'Domača naloga je bila uspešno dodana!';
        } catch (PDOException $e) {
            $napaka = 'Napaka pri dodajanju domače naloge.';
            error_log($e->getMessage());
        }
    }
}

// Brisanje domače naloge
if (isset($_GET['izbrisi']) && is_numeric($_GET['izbrisi'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM domača_naloga WHERE naloga_id = :naloga_id");
        $stmt->execute(['naloga_id' => $_GET['izbrisi']]);
        $uspeh = 'Domača naloga je bila uspešno izbrisana!';
    } catch (PDOException $e) {
        $napaka = 'Napaka pri brisanju domače naloge.';
        error_log($e->getMessage());
    }
}

// Pridobi predmete profesorja
try {
    $stmt = $pdo->prepare("SELECT p.predmet_id, p.ime_predmeta 
                          FROM predmet p
                          JOIN profesor_predmet pp ON p.predmet_id = pp.predmet_id
                          WHERE pp.profesor_id = :profesor_id
                          ORDER BY p.ime_predmeta");
    $stmt->execute(['profesor_id' => $profesor_id]);
    $predmeti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $predmeti = [];
    error_log($e->getMessage());
}

// Pridobi domače naloge profesorja
try {
    $stmt = $pdo->prepare("SELECT dn.naloga_id, dn.naslov, dn.navodila, dn.datum_objave, dn.rok, 
                          p.ime_predmeta,
                          COUNT(DISTINCT od.oddaja_id) as st_oddaj,
                          COUNT(DISTINCT dp.id_dijaka) as st_dijakov
                          FROM domača_naloga dn
                          JOIN predmet p ON dn.predmet_id = p.predmet_id
                          JOIN profesor_predmet pp ON p.predmet_id = pp.predmet_id
                          LEFT JOIN oddaja_naloge od ON dn.naloga_id = od.naloga_id
                          LEFT JOIN dijak_predmet dp ON p.predmet_id = dp.predmet_id
                          WHERE pp.profesor_id = :profesor_id
                          GROUP BY dn.naloga_id, dn.naslov, dn.navodila, dn.datum_objave, dn.rok, p.ime_predmeta
                          ORDER BY dn.datum_objave DESC");
    $stmt->execute(['profesor_id' => $profesor_id]);
    $naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $naloge = [];
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Domače naloge - Vnos | E-Ocene</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      margin: 0;
      background: #f8f9ff;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      color: #333;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .header {
      height: 70px;
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 30px;
      color: white;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      position: relative;
      z-index: 100;
    }

    .header h1 {
      font-size: 22px;
      font-weight: bold;
      margin: 0;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .header h1:hover {
      opacity: 0.9;
    }

    .avatar-container {
      position: relative;
      display: inline-block;
    }

    .avatar-link {
      width: 44px;
      height: 44px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #8884FF;
      font-weight: bold;
      font-size: 18px;
      cursor: pointer;
      transition: transform 0.2s;
      user-select: none;
    }

    .avatar-link:hover {
      transform: scale(1.05);
    }

    #odjava-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: white;
      color: #333;
      padding: 10px 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      font-size: 14px;
      text-decoration: none;
      text-align: center;
      white-space: nowrap;
      margin-top: 8px;
      z-index: 1000;
      transition: opacity 0.2s;
      font-weight: 500;
    }

    #odjava-menu:hover {
      background: #f5f5f5;
      color: #8884FF;
    }

    .vsebina {
      padding: 40px;
      flex: 1;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .naslov {
      font-size: 28px;
      margin-bottom: 30px;
      color: #2c2c2c;
      font-weight: 600;
    }

    .sporocilo {
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .uspeh {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid #28a745;
    }

    .napaka {
      background: #f8d7da;
      color: #721c24;
      border-left: 4px solid #dc3545;
    }

    .forma-container {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 40px;
    }

    .forma-container h2 {
      font-size: 20px;
      margin-bottom: 20px;
      color: #444;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #555;
      font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
    }

    .form-group textarea {
      min-height: 120px;
      resize: vertical;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #8884FF;
    }

    .btn {
      padding: 12px 30px;
      background: #8884FF;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.2s;
    }

    .btn:hover {
      background: #7774ee;
    }

    .naloge-seznam {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .naloge-seznam h2 {
      font-size: 20px;
      margin-bottom: 20px;
      color: #444;
    }

    .naloga-card {
      background: #f8f9ff;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 15px;
      border-left: 4px solid #8884FF;
    }

    .naloga-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 10px;
    }

    .naloga-naslov {
      font-size: 18px;
      font-weight: 600;
      color: #333;
    }

    .naloga-predmet {
      display: inline-block;
      background: #8884FF;
      color: white;
      padding: 4px 12px;
      border-radius: 15px;
      font-size: 12px;
      margin-bottom: 8px;
    }

    .naloga-info {
      font-size: 13px;
      color: #666;
      margin: 5px 0;
    }

    .naloga-navodila {
      margin: 10px 0;
      padding: 10px;
      background: white;
      border-radius: 6px;
      font-size: 14px;
      color: #555;
    }

    .naloga-stats {
      display: flex;
      gap: 20px;
      margin-top: 10px;
      font-size: 13px;
    }

    .stat {
      color: #666;
    }

    .stat strong {
      color: #8884FF;
    }

    .btn-izbrisi {
      background: #dc3545;
      color: white;
      padding: 6px 15px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 13px;
      transition: background 0.2s;
    }

    .btn-izbrisi:hover {
      background: #c82333;
    }

    .prazen {
      text-align: center;
      padding: 40px;
      color: #999;
      font-size: 15px;
    }

    .footer {
      height: 50px;
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      box-shadow: 0 -2px 6px rgba(0,0,0,0.08);
      margin-top: auto;
    }

    @media (max-width: 768px) {
      .vsebina {
        padding: 20px;
      }

      .header {
        padding: 0 20px;
      }

      .header h1 {
        font-size: 18px;
      }

      .naloga-header {
        flex-direction: column;
      }

      .naloga-stats {
        flex-direction: column;
        gap: 5px;
      }
    }
  </style>
</head>
<body>

  <div class="header">
    <h1 onclick="window.location.href='main_page.php'" style="cursor: pointer;">E-Ocene</h1>
    
    <div class="avatar-container">
      <div class="avatar-link" id="avatar"><?php echo htmlspecialchars($prva_crka); ?></div>
      <a href="odjava.php" id="odjava-menu">Odjava</a>
    </div>
  </div>

  <div class="vsebina">
    <div class="naslov">Domače naloge - Upravljanje</div>

    <?php if ($uspeh): ?>
      <div class="sporocilo uspeh"><?php echo htmlspecialchars($uspeh); ?></div>
    <?php endif; ?>

    <?php if ($napaka): ?>
      <div class="sporocilo napaka"><?php echo htmlspecialchars($napaka); ?></div>
    <?php endif; ?>

    <!-- Forma za dodajanje nove domače naloge -->
    <div class="forma-container">
      <h2>Dodaj novo domačo nalogo</h2>
      <form method="POST" action="">
        <div class="form-group">
          <label>Predmet:</label>
          <select name="predmet_id" required>
            <option value="">-- Izberi predmet --</option>
            <?php foreach ($predmeti as $predmet): ?>
              <option value="<?php echo $predmet['predmet_id']; ?>">
                <?php echo htmlspecialchars($predmet['ime_predmeta']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Naslov naloge:</label>
          <input type="text" name="naslov" required placeholder="npr. Domača naloga 5">
        </div>

        <div class="form-group">
          <label>Navodila:</label>
          <textarea name="navodila" required placeholder="Vnesite navodila za domačo nalogo..."></textarea>
        </div>

        <div class="form-group">
          <label>Rok za oddajo:</label>
          <input type="datetime-local" name="rok" required>
        </div>

        <button type="submit" name="dodaj_nalogo" class="btn">Dodaj domačo nalogo</button>
      </form>
    </div>

    <!-- Seznam obstoječih domačih nalog -->
    <div class="naloge-seznam">
      <h2>Vse domače naloge</h2>

      <?php if (empty($naloge)): ?>
        <div class="prazen">Še nimate dodanih domačih nalog.</div>
      <?php else: ?>
        <?php foreach ($naloge as $naloga): ?>
          <div class="naloga-card">
            <span class="naloga-predmet"><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></span>
            <div class="naloga-header">
              <div>
                <div class="naloga-naslov"><?php echo htmlspecialchars($naloga['naslov']); ?></div>
                <div class="naloga-info">
                  Objavljeno: <?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?>
                </div>
                <div class="naloga-info">
                  Rok: <?php echo date('d.m.Y H:i', strtotime($naloga['rok'])); ?>
                </div>
              </div>
              <a href="?izbrisi=<?php echo $naloga['naloga_id']; ?>" 
                 class="btn-izbrisi" 
                 onclick="return confirm('Ali ste prepričani, da želite izbrisati to nalogo?')">
                Izbriši
              </a>
            </div>
            
            <div class="naloga-navodila">
              <?php echo nl2br(htmlspecialchars($naloga['navodila'])); ?>
            </div>

            <div class="naloga-stats">
              <div class="stat">
                Oddalo: <strong><?php echo $naloga['st_oddaj']; ?></strong> / <?php echo $naloga['st_dijakov']; ?> dijakov
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="footer">
    &copy; 2025 E-Ocene. Vse pravice pridržane.
  </div>

  <script>
    // Toggle odjava menu
    document.getElementById('avatar').addEventListener('click', function(e) {
      e.stopPropagation();
      const menu = document.getElementById('odjava-menu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    });

    // Zapri menu ko klikneš izven
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('odjava-menu');
      const container = document.querySelector('.avatar-container');
      if (!container.contains(event.target)) {
        menu.style.display = 'none';
      }
    });
  </script>

</body>
</html>