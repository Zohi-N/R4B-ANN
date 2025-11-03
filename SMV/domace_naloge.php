<?php
session_start();
require_once 'database.php';

// Preveri ali je uporabnik prijavljen kot dijak
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

// Oddaja domače naloge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oddaj_nalogo'])) {
    $naloga_id = $_POST['naloga_id'] ?? '';
    $datoteka_link = trim($_POST['datoteka_link'] ?? '');
    
    if (empty($naloga_id) || empty($datoteka_link)) {
        $napaka = 'Vsa polja so obvezna.';
    } else {
        try {
            // Preveri ali je dijak že oddal to nalogo
            $stmt = $pdo->prepare("SELECT oddaja_id FROM oddaja_naloge WHERE naloga_id = :naloga_id AND id_dijaka = :id_dijaka");
            $stmt->execute([
                'naloga_id' => $naloga_id,
                'id_dijaka' => $dijak_id
            ]);
            
            if ($stmt->fetch()) {
                $napaka = 'To nalogo ste že oddali!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO oddaja_naloge (naloga_id, id_dijaka, datum_objave, datoteka_link, status) 
                                       VALUES (:naloga_id, :id_dijaka, NOW(), :datoteka_link, 'oddano')");
                $stmt->execute([
                    'naloga_id' => $naloga_id,
                    'id_dijaka' => $dijak_id,
                    'datoteka_link' => $datoteka_link
                ]);
                $uspeh = 'Domača naloga je bila uspešno oddana! ✓';
            }
        } catch (PDOException $e) {
            $napaka = 'Napaka pri oddaji domače naloge.';
            error_log($e->getMessage());
        }
    }
}

// Pridobi domače naloge za dijaka (glede na njegove predmete)
try {
    $stmt = $pdo->prepare("SELECT dn.naloga_id, dn.naslov, dn.navodila, dn.datum_objave, dn.rok,
                          p.ime_predmeta,
                          od.oddaja_id, od.datum_objave as datum_oddaje, od.datoteka_link,
                          od.status, od.komentar_profesorja, od.datum_pregleda
                          FROM domača_naloga dn
                          JOIN predmet p ON dn.predmet_id = p.predmet_id
                          JOIN dijak_predmet dp ON p.predmet_id = dp.predmet_id
                          LEFT JOIN oddaja_naloge od ON dn.naloga_id = od.naloga_id AND od.id_dijaka = :dijak_id
                          WHERE dp.id_dijaka = :dijak_id
                          ORDER BY 
                            CASE WHEN od.oddaja_id IS NULL THEN 0 ELSE 1 END,
                            dn.rok ASC,
                            dn.datum_objave DESC");
    $stmt->execute(['dijak_id' => $dijak_id]);
    $naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $naloge = [];
    error_log($e->getMessage());
}

// Ločimo naloge na neodane, oddane (v pregledu) in pregledane
$neodane = [];
$oddane_v_pregledu = [];
$pregledane = [];

foreach ($naloge as $naloga) {
    if (!$naloga['oddaja_id']) {
        // Neodane
        $neodane[] = $naloga;
    } elseif ($naloga['status'] === 'oddano') {
        // Oddane, čakajo na pregled
        $oddane_v_pregledu[] = $naloga;
    } else {
        // Pregledane (odobrene ali zavrnjene)
        $pregledane[] = $naloga;
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Domače naloge | E-Ocene</title>
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
      z-index: 100;
    }

    .header h1 {
      font-size: 22px;
      font-weight: bold;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .header h1:hover {
      opacity: 0.9;
    }

    .avatar-container {
      position: relative;
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
      margin-top: 8px;
      z-index: 1000;
      font-weight: 500;
    }

    #odjava-menu:hover {
      background: #f5f5f5;
      color: #8884FF;
    }

    .vsebina {
      padding: 40px;
      flex: 1;
      max-width: 1200px;
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

    .sekcija {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }

    .sekcija h2 {
      font-size: 22px;
      margin-bottom: 20px;
      color: #444;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .badge {
      background: #dc3545;
      color: white;
      padding: 4px 12px;
      border-radius: 15px;
      font-size: 14px;
      font-weight: 600;
    }

    .badge.zelena {
      background: #28a745;
    }

    .badge.rumena {
      background: #ffc107;
      color: #856404;
    }

    .naloga-card {
      background: #f8f9ff;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 15px;
      border-left: 4px solid #8884FF;
    }

    .naloga-card.oddana {
      border-left-color: #ffc107;
    }

    .naloga-card.pregledano {
      border-left-color: #28a745;
      opacity: 0.85;
    }

    .naloga-card.zavrnjeno {
      border-left-color: #dc3545;
    }

    .naloga-card.zamujeno {
      border-left-color: #dc3545;
      background: #fff5f5;
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

    .naloga-naslov {
      font-size: 18px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }

    .naloga-info {
      font-size: 13px;
      color: #666;
      margin: 5px 0;
    }

    .naloga-info.zamujeno {
      color: #dc3545;
      font-weight: 600;
    }

    .naloga-navodila {
      margin: 10px 0;
      padding: 10px;
      background: white;
      border-radius: 6px;
      font-size: 14px;
      color: #555;
    }

    .status-badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: 600;
      margin-top: 8px;
    }

    .status-oddano {
      background: #ffc107;
      color: #856404;
    }

    .status-pregledano {
      background: #28a745;
      color: white;
    }

    .status-zavrnjeno {
      background: #dc3545;
      color: white;
    }

    .oddaja-forma {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #ddd;
    }

    .oddaja-forma input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      margin-bottom: 10px;
    }

    .oddaja-forma input:focus {
      outline: none;
      border-color: #8884FF;
    }

    .btn {
      padding: 10px 25px;
      background: #8884FF;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.2s;
    }

    .btn:hover {
      background: #7774ee;
    }

    .oddaja-info {
      margin-top: 10px;
      padding: 10px;
      background: #fff3cd;
      border-radius: 6px;
      font-size: 13px;
      color: #856404;
    }

    .oddaja-info.uspesno {
      background: #d4edda;
      color: #155724;
    }

    .oddaja-info a {
      color: inherit;
      font-weight: 600;
      word-break: break-all;
    }

    .komentar-profesorja {
      margin-top: 10px;
      padding: 12px;
      background: white;
      border-radius: 6px;
      border-left: 3px solid #8884FF;
      font-size: 14px;
    }

    .komentar-profesorja strong {
      color: #8884FF;
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

      .naslov {
        font-size: 22px;
      }
    }
  </style>
</head>
<body>

  <div class="header">
    <h1 onclick="window.location.href='main_page.php'">E-Ocene</h1>
    
    <div class="avatar-container">
      <div class="avatar-link" id="avatar"><?php echo htmlspecialchars($prva_crka); ?></div>
      <a href="odjava.php" id="odjava-menu">Odjava</a>
    </div>
  </div>

  <div class="vsebina">
    <div class="naslov">Domače naloge</div>

    <?php if ($uspeh): ?>
      <div class="sporocilo uspeh"><?php echo htmlspecialchars($uspeh); ?></div>
    <?php endif; ?>

    <?php if ($napaka): ?>
      <div class="sporocilo napaka"><?php echo htmlspecialchars($napaka); ?></div>
    <?php endif; ?>

    <!-- Neodane naloge -->
    <div class="sekcija">
      <h2>
        📝 Neodane naloge 
        <?php if (!empty($neodane)): ?>
          <span class="badge"><?php echo count($neodane); ?></span>
        <?php endif; ?>
      </h2>

      <?php if (empty($neodane)): ?>
        <div class="prazen">🎉 Trenutno nimate nobene neodane domače naloge!</div>
      <?php else: ?>
        <?php foreach ($neodane as $naloga): 
          $je_zamujeno = strtotime($naloga['rok']) < time();
        ?>
          <div class="naloga-card <?php echo $je_zamujeno ? 'zamujeno' : ''; ?>">
            <span class="naloga-predmet"><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></span>
            
            <div class="naloga-naslov"><?php echo htmlspecialchars($naloga['naslov']); ?></div>
            
            <div class="naloga-info">
              Objavljeno: <?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?>
            </div>
            <div class="naloga-info <?php echo $je_zamujeno ? 'zamujeno' : ''; ?>">
              Rok: <?php echo date('d.m.Y H:i', strtotime($naloga['rok'])); ?>
              <?php if ($je_zamujeno): ?>
                ⚠️ ZAMUJENO
              <?php endif; ?>
            </div>
            
            <div class="naloga-navodila">
              <strong>Navodila:</strong><br>
              <?php echo nl2br(htmlspecialchars($naloga['navodila'])); ?>
            </div>

            <form method="POST" action="" class="oddaja-forma">
              <input type="hidden" name="naloga_id" value="<?php echo $naloga['naloga_id']; ?>">
              <input type="url" name="datoteka_link" placeholder="Povezava do oddaje (npr. Google Drive link)" required>
              <button type="submit" name="oddaj_nalogo" class="btn">Oddaj nalogo</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Oddane naloge - čakajo na pregled -->
    <div class="sekcija">
      <h2>
        ⏳ V pregledu 
        <?php if (!empty($oddane_v_pregledu)): ?>
          <span class="badge rumena"><?php echo count($oddane_v_pregledu); ?></span>
        <?php endif; ?>
      </h2>

      <?php if (empty($oddane_v_pregledu)): ?>
        <div class="prazen">Ni nalog v pregledu.</div>
      <?php else: ?>
        <?php foreach ($oddane_v_pregledu as $naloga): ?>
          <div class="naloga-card oddana">
            <span class="naloga-predmet"><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></span>
            
            <div class="naloga-naslov"><?php echo htmlspecialchars($naloga['naslov']); ?></div>
            
            <div class="naloga-info">
              Rok je bil: <?php echo date('d.m.Y H:i', strtotime($naloga['rok'])); ?>
            </div>
            
            <span class="status-badge status-oddano">⏳ Čaka na pregled</span>

            <div class="oddaja-info">
              ✅ <strong>Oddano:</strong> <?php echo date('d.m.Y H:i', strtotime($naloga['datum_oddaje'])); ?><br>
              📎 <strong>Povezava:</strong> <a href="<?php echo htmlspecialchars($naloga['datoteka_link']); ?>" target="_blank">
                <?php echo htmlspecialchars($naloga['datoteka_link']); ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pregledane naloge -->
    <div class="sekcija">
      <h2>
        ✓ Pregledane naloge 
        <?php if (!empty($pregledane)): ?>
          <span class="badge zelena"><?php echo count($pregledane); ?></span>
        <?php endif; ?>
      </h2>

      <?php if (empty($pregledane)): ?>
        <div class="prazen">Ni še nobene pregledane naloge.</div>
      <?php else: ?>
        <?php foreach ($pregledane as $naloga): ?>
          <div class="naloga-card <?php echo $naloga['status']; ?>">
            <span class="naloga-predmet"><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></span>
            
            <div class="naloga-naslov"><?php echo htmlspecialchars($naloga['naslov']); ?></div>
            
            <div class="naloga-info">
              Rok je bil: <?php echo date('d.m.Y H:i', strtotime($naloga['rok'])); ?>
            </div>
            
            <?php if ($naloga['status'] === 'pregledano'): ?>
              <span class="status-badge status-pregledano">✓ Odobreno</span>
            <?php else: ?>
              <span class="status-badge status-zavrnjeno">✗ Zavrnjeno</span>
            <?php endif; ?>

            <div class="oddaja-info <?php echo $naloga['status'] === 'pregledano' ? 'uspesno' : ''; ?>">
              <strong>Oddano:</strong> <?php echo date('d.m.Y H:i', strtotime($naloga['datum_oddaje'])); ?><br>
              <strong>Pregledano:</strong> <?php echo $naloga['datum_pregleda'] ? date('d.m.Y H:i', strtotime($naloga['datum_pregleda'])) : '/'; ?><br>
              📎 <strong>Povezava:</strong> <a href="<?php echo htmlspecialchars($naloga['datoteka_link']); ?>" target="_blank">
                <?php echo htmlspecialchars($naloga['datoteka_link']); ?>
              </a>
            </div>

            <?php if ($naloga['komentar_profesorja']): ?>
              <div class="komentar-profesorja">
                <strong>💬 Komentar profesorja:</strong><br>
                <?php echo nl2br(htmlspecialchars($naloga['komentar_profesorja'])); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="footer">
    &copy; 2025 E-Ocene. Vse pravice pridržane.
  </div>

  <script>
    document.getElementById('avatar').addEventListener('click', function(e) {
      e.stopPropagation();
      const menu = document.getElementById('odjava-menu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    });

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