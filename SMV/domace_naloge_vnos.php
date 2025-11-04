<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'database.php';

if (!isset($_SESSION['uporabnik_id']) || $_SESSION['uporabnik_tip'] !== 'profesor') {
    header('Location: prijava.php');
    exit;
}

$profesor_id = $_SESSION['uporabnik_id'];
$ime = $_SESSION['ime'] ?? 'Profesor';
$prva_crka = mb_strtoupper(mb_substr($ime, 0, 1, 'UTF-8'), 'UTF-8');

$uspeh = '';
$napaka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oceni_oddajo'])) {
    $oddaja_id = $_POST['oddaja_id'] ?? '';
    $status = $_POST['oceni_oddajo'];
    $komentar = trim($_POST['komentar'] ?? '');
    
    if (empty($oddaja_id) || empty($status)) {
        $napaka = 'Status je obvezen.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE oddaja_naloge 
                                   SET status = :status, 
                                       komentar_profesorja = :komentar,
                                       datum_pregleda = NOW()
                                   WHERE oddaja_id = :oddaja_id");
            $stmt->execute([
                'status' => $status,
                'komentar' => $komentar,
                'oddaja_id' => $oddaja_id
            ]);
            $uspeh = 'Oddaja je bila uspešno ocenjena!';
        } catch (PDOException $e) {
            $napaka = 'Napaka pri ocenjevanju oddaje: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_nalogo'])) {
    $predmet_id = $_POST['predmet_id'] ?? '';
    $naslov = trim($_POST['naslov'] ?? '');
    $navodila = trim($_POST['navodila'] ?? '');
    $rok = $_POST['rok'] ?? '';
    
    if (empty($predmet_id) || empty($naslov) || empty($navodila) || empty($rok)) {
        $napaka = 'Vsa polja so obvezna.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM profesor_predmet 
                                   WHERE profesor_id = :profesor_id AND predmet_id = :predmet_id");
            $stmt->execute(['profesor_id' => $profesor_id, 'predmet_id' => $predmet_id]);
            
            if ($stmt->fetchColumn() == 0) {
                $napaka = 'Ne učite tega predmeta!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO domaca_naloga (predmet_id, naslov, navodila, datum_objave, rok) 
                                       VALUES (:predmet_id, :naslov, :navodila, NOW(), :rok)");
                $stmt->execute([
                    'predmet_id' => $predmet_id,
                    'naslov' => $naslov,
                    'navodila' => $navodila,
                    'rok' => $rok
                ]);
                $uspeh = 'Domača naloga je bila uspešno dodana!';
            }
        } catch (PDOException $e) {
            $napaka = 'Napaka pri dodajanju domače naloge: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

if (isset($_GET['izbrisi']) && is_numeric($_GET['izbrisi'])) {
    $naloga_id = $_GET['izbrisi'];
    
    try {
        $stmt = $pdo->prepare("SELECT dn.naloga_id 
                              FROM domaca_naloga dn
                              JOIN profesor_predmet pp ON dn.predmet_id = pp.predmet_id
                              WHERE dn.naloga_id = :naloga_id AND pp.profesor_id = :profesor_id");
        $stmt->execute(['naloga_id' => $naloga_id, 'profesor_id' => $profesor_id]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("SELECT datoteka_link FROM oddaja_naloge WHERE naloga_id = :naloga_id");
            $stmt->execute(['naloga_id' => $naloga_id]);
            $datoteke = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($datoteke as $datoteka) {
                if (file_exists($datoteka)) {
                    unlink($datoteka);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM domaca_naloga WHERE naloga_id = :naloga_id");
            $stmt->execute(['naloga_id' => $naloga_id]);
            
            $uspeh = 'Domača naloga je bila uspešno izbrisana!';
        } else {
            $napaka = 'Nimate pravice izbrisati te naloge!';
        }
    } catch (PDOException $e) {
        $napaka = 'Napaka pri brisanju domače naloge: ' . $e->getMessage();
        error_log($e->getMessage());
    }
}

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

try {
    $stmt = $pdo->prepare("SELECT dn.naloga_id, dn.naslov, dn.navodila, dn.datum_objave, dn.rok,
                          p.ime_predmeta,
                          COUNT(DISTINCT od.oddaja_id) as st_oddaj,
                          COUNT(DISTINCT CASE WHEN od.status = 'oddano' THEN od.oddaja_id END) as st_v_pregledu
                          FROM domaca_naloga dn
                          JOIN predmet p ON dn.predmet_id = p.predmet_id
                          JOIN profesor_predmet pp ON p.predmet_id = pp.predmet_id
                          LEFT JOIN oddaja_naloge od ON dn.naloga_id = od.naloga_id
                          WHERE pp.profesor_id = :profesor_id
                          GROUP BY dn.naloga_id, dn.naslov, dn.navodila, dn.datum_objave, dn.rok, p.ime_predmeta
                          ORDER BY dn.datum_objave DESC");
    $stmt->execute(['profesor_id' => $profesor_id]);
    $vse_naloge = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vse_naloge = [];
    $napaka = 'Napaka pri pridobivanju nalog: ' . $e->getMessage();
    error_log($e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT od.oddaja_id, od.datum_objave, od.datoteka_link, od.datoteka_ime, od.status, 
                          od.komentar_profesorja, od.datum_pregleda,
                          dn.naslov, dn.rok,
                          p.ime_predmeta,
                          d.ime_dijaka, d.priimek_dijaka, d.gmail as dijak_email,
                          r.oznaka as razred
                          FROM oddaja_naloge od
                          JOIN domaca_naloga dn ON od.naloga_id = dn.naloga_id
                          JOIN predmet p ON dn.predmet_id = p.predmet_id
                          JOIN profesor_predmet pp ON p.predmet_id = pp.predmet_id
                          JOIN dijaki d ON od.id_dijaka = d.id_dijaka
                          JOIN razred r ON d.razred_id = r.razred_id
                          WHERE pp.profesor_id = :profesor_id
                          ORDER BY 
                            CASE 
                              WHEN od.status = 'oddano' THEN 1
                              WHEN od.status = 'pregledano' THEN 2
                              ELSE 3
                            END,
                            od.datum_objave DESC");
    $stmt->execute(['profesor_id' => $profesor_id]);
    $oddaje = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $oddaje = [];
    error_log($e->getMessage());
}

$oddaje_za_pregled = [];
$pregledane = [];
foreach ($oddaje as $oddaja) {
    if ($oddaja['status'] === 'oddano') {
        $oddaje_za_pregled[] = $oddaja;
    } else {
        $pregledane[] = $oddaja;
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Domače naloge - Upravljanje | E-Ocene</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      margin: 0; background: #f8f9ff;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      color: #333; min-height: 100vh; display: flex; flex-direction: column;
    }
    .header {
      height: 70px; background: linear-gradient(135deg, #8884FF, #AB64D6);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 30px; color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.1); z-index: 100;
    }
    .header h1 { font-size: 22px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
    .header h1:hover { opacity: 0.9; }
    .avatar-container { position: relative; }
    .avatar-link {
      width: 44px; height: 44px; background: white; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: #8884FF; font-weight: bold; font-size: 18px;
      cursor: pointer; transition: transform 0.2s;
    }
    .avatar-link:hover { transform: scale(1.05); }
    #odjava-menu {
      display: none; position: absolute; top: 100%; left: 50%;
      transform: translateX(-50%); background: white; color: #333;
      padding: 10px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      font-size: 14px; text-decoration: none; margin-top: 8px; z-index: 1000; font-weight: 500;
    }
    #odjava-menu:hover { background: #f5f5f5; color: #8884FF; }
    .vsebina { padding: 40px; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; }
    .naslov { font-size: 28px; margin-bottom: 30px; color: #2c2c2c; font-weight: 600; }
    .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
    .tab {
      padding: 12px 24px; background: none; border: none; font-size: 16px;
      font-weight: 500; color: #666; cursor: pointer; border-bottom: 3px solid transparent;
      transition: all 0.2s;
    }
    .tab:hover { color: #8884FF; }
    .tab.active { color: #8884FF; border-bottom-color: #8884FF; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .sporocilo { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .uspeh { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
    .napaka { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .sekcija {
      background: white; padding: 30px; border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 30px;
    }
    .sekcija h2 {
      font-size: 20px; margin-bottom: 20px; color: #444;
      display: flex; align-items: center; gap: 10px;
    }
    .badge {
      background: #dc3545; color: white; padding: 4px 12px;
      border-radius: 15px; font-size: 14px; font-weight: 600;
    }
    .badge.zelena { background: #28a745; }
    .badge.modra { background: #007bff; }
    .form-group { margin-bottom: 20px; }
    .form-group label {
      display: block; margin-bottom: 8px; font-weight: 500;
      color: #555; font-size: 14px;
    }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 12px; border: 1px solid #ddd;
      border-radius: 8px; font-size: 14px; font-family: inherit;
    }
    .form-group textarea { min-height: 120px; resize: vertical; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      outline: none; border-color: #8884FF;
    }
    .btn {
      padding: 12px 30px; background: #8884FF; color: white;
      border: none; border-radius: 8px; font-size: 15px;
      cursor: pointer; font-weight: 600; transition: background 0.2s;
    }
    .btn:hover { background: #7774ee; }
    .btn-odobri { background: #28a745; padding: 8px 20px; font-size: 14px; }
    .btn-odobri:hover { background: #218838; }
    .btn-zavrni { background: #dc3545; padding: 8px 20px; font-size: 14px; }
    .btn-zavrni:hover { background: #c82333; }
    .btn-prenesi {
      background: #007bff; padding: 8px 20px; font-size: 14px;
      text-decoration: none; display: inline-block; margin-top: 10px; color: white;
    }
    .btn-prenesi:hover { background: #0056b3; }
    .btn-izbrisi { background: #dc3545; padding: 8px 16px; font-size: 13px; }
    .btn-izbrisi:hover { background: #c82333; }
    .oddaja-card {
      background: #f8f9ff; padding: 20px; border-radius: 10px;
      margin-bottom: 15px; border-left: 4px solid #8884FF;
    }
    .oddaja-card.pregledano { border-left-color: #28a745; opacity: 0.8; }
    .oddaja-card.zavrnjeno { border-left-color: #dc3545; opacity: 0.8; }
    .naloga-card {
      background: #f8f9ff; padding: 20px; border-radius: 10px;
      margin-bottom: 15px; border-left: 4px solid #8884FF; position: relative;
    }
    .oddaja-header {
      display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;
    }
    .dijak-info { font-size: 16px; font-weight: 600; color: #333; }
    .dijak-razred {
      display: inline-block; background: #8884FF; color: white;
      padding: 3px 10px; border-radius: 12px; font-size: 12px; margin-left: 8px;
    }
    .naloga-info { font-size: 13px; color: #666; margin: 5px 0; }
    .status-badge {
      display: inline-block; padding: 5px 12px; border-radius: 15px;
      font-size: 12px; font-weight: 600;
    }
    .status-oddano { background: #ffc107; color: #856404; }
    .status-pregledano { background: #28a745; color: white; }
    .status-zavrnjeno { background: #dc3545; color: white; }
    .oceni-forma { margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; }
    .oceni-forma textarea {
      width: 100%; padding: 10px; border: 1px solid #ddd;
      border-radius: 6px; font-size: 14px; margin-bottom: 10px; min-height: 80px;
    }
    .oceni-forma .gumbi { display: flex; gap: 10px; }
    .datoteka-info {
      margin-top: 10px; padding: 12px; background: white;
      border-radius: 6px; font-size: 13px; border: 1px solid #ddd;
    }
    .datoteka-info strong { color: #8884FF; }
    .komentar-box {
      background: #fff3cd; padding: 10px; border-radius: 6px;
      margin-top: 10px; font-size: 13px; border-left: 3px solid #ffc107;
    }
    .prazen { text-align: center; padding: 40px; color: #999; font-size: 15px; }
    .naloga-statistika { display: flex; gap: 20px; margin-top: 10px; font-size: 13px; }
    .stat-item { display: flex; align-items: center; gap: 5px; color: #666; }
    .footer {
      height: 50px; background: linear-gradient(135deg, #8884FF, #AB64D6);
      color: white; display: flex; align-items: center; justify-content: center;
      font-size: 14px; box-shadow: 0 -2px 6px rgba(0,0,0,0.08);
    }
    @media (max-width: 768px) {
      .vsebina { padding: 20px; }
      .header { padding: 0 20px; }
      .tabs { overflow-x: auto; }
      .oceni-forma .gumbi { flex-direction: column; }
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
    <div class="naslov">Domače naloge - Upravljanje</div>

    <?php if ($uspeh): ?>
      <div class="sporocilo uspeh"><?php echo htmlspecialchars($uspeh); ?></div>
    <?php endif; ?>

    <?php if ($napaka): ?>
      <div class="sporocilo napaka"><?php echo htmlspecialchars($napaka); ?></div>
    <?php endif; ?>

    <div class="tabs">
      <button class="tab" onclick="switchTab('dodaj')">Dodaj nalogo</button>
      <button class="tab active" onclick="switchTab('upravljanje')">Vse naloge (<?php echo count($vse_naloge); ?>)</button>
      <button class="tab" onclick="switchTab('pregled')">Za pregled <?php if(!empty($oddaje_za_pregled)): ?><span class="badge"><?php echo count($oddaje_za_pregled); ?></span><?php endif; ?></button>
      <button class="tab" onclick="switchTab('pregledane')">Pregledane (<?php echo count($pregledane); ?>)</button>
    </div>

    <div id="tab-dodaj" class="tab-content">
      <div class="sekcija">
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
    </div>

    <div id="tab-upravljanje" class="tab-content active">
      <div class="sekcija">
        <h2>Vse domače naloge</h2>
        <?php if (empty($vse_naloge)): ?>
          <div class="prazen">Še niste dodali nobene domače naloge.</div>
        <?php else: ?>
          <?php foreach ($vse_naloge as $naloga): ?>
            <div class="naloga-card">
              <div class="oddaja-header">
                <div style="flex: 1;">
                  <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                    <span class="dijak-razred"><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></span>
                    <strong style="font-size: 16px;"><?php echo htmlspecialchars($naloga['naslov']); ?></strong>
                  </div>
                  <div class="naloga-info">
                    <strong>Objavljeno:</strong> <?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?> | 
                    <strong>Rok:</strong> <?php echo date('d.m.Y H:i', strtotime($naloga['rok'])); ?>
                    <?php if (strtotime($naloga['rok']) < time()): ?>
                      <span style="color: #dc3545; font-weight: 600;"> (ROK POTEKEL)</span>
                    <?php endif; ?>
                  </div>
                  <div class="naloga-statistika">
                    <div class="stat-item"><strong><?php echo $naloga['st_oddaj']; ?></strong> oddaj skupaj</div>
                    <?php if ($naloga['st_v_pregledu'] > 0): ?>
                      <div class="stat-item" style="color: #ffc107;"> <strong><?php echo $naloga['st_v_pregledu']; ?></strong> čaka na pregled</div>
                    <?php endif; ?>
                  </div>
                </div>
                <button onclick="if(confirm('Ali ste prepričani, da želite izbrisati to nalogo? To bo izbrisalo tudi vse oddaje!')) { window.location.href='?izbrisi=<?php echo $naloga['naloga_id']; ?>'; }" class="btn btn-izbrisi">🗑️ Izbriši</button>
              </div>
              <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 6px; font-size: 13px;">
                <strong>Navodila:</strong><br><?php echo nl2br(htmlspecialchars($naloga['navodila'])); ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div id="tab-pregled" class="tab-content">
      <div class="sekcija">
        <h2>Oddaje za pregled</h2>
        <?php if (empty($oddaje_za_pregled)): ?>
          <div class="prazen"> Ni novih oddaj za pregled.</div>
        <?php else: ?>
          <?php foreach ($oddaje_za_pregled as $oddaja): ?>
            <div class="oddaja-card">
              <div class="oddaja-header">
                <div>
                  <div class="dijak-info">
                    <?php echo htmlspecialchars($oddaja['ime_dijaka'] . ' ' . $oddaja['priimek_dijaka']); ?>
                    <span class="dijak-razred"><?php echo htmlspecialchars($oddaja['razred']); ?></span>
                  </div>
                  <div class="naloga-info">
                    <strong>Predmet:</strong> <?php echo htmlspecialchars($oddaja['ime_predmeta']); ?> | 
                    <strong>Naloga:</strong> <?php echo htmlspecialchars($oddaja['naslov']); ?>
                  </div>
                  <div class="naloga-info">
                    <strong>Oddano:</strong> <?php echo date('d.m.Y H:i', strtotime($oddaja['datum_objave'])); ?> | 
                    <strong>Rok je bil:</strong> <?php echo date('d.m.Y H:i', strtotime($oddaja['rok'])); ?>
                  </div>
                </div>
                <span class="status-badge status-oddano">Oddano</span>
              </div>
              <div class="datoteka-info">
                <strong>Oddana datoteka:</strong> <?php echo htmlspecialchars($oddaja['datoteka_ime']); ?><br>
                <a href="<?php echo htmlspecialchars($oddaja['datoteka_link']); ?>" download class="btn btn-prenesi">⬇️ Prenesi datoteko</a>
              </div>
              <form method="POST" class="oceni-forma">
                <input type="hidden" name="oddaja_id" value="<?php echo $oddaja['oddaja_id']; ?>">
                <textarea name="komentar" placeholder="Komentar (opcijsko)"></textarea>
                <div class="gumbi">
                  <button type="submit" name="oceni_oddajo" value="pregledano" class="btn btn-odobri">✓ Odobri</button>
                  <button type="submit" name="oceni_oddajo" value="zavrnjeno" class="btn btn-zavrni">✗ Zavrni</button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div id="tab-pregledane" class="tab-content">
      <div class="sekcija">
        <h2>Pregledane oddaje</h2>
        <?php if (empty($pregledane)): ?>
          <div class="prazen">Še niste pregledali nobene oddaje.</div>
        <?php else: ?>
          <?php foreach ($pregledane as $oddaja): ?>
            <div class="oddaja-card <?php echo $oddaja['status']; ?>">
              <div class="oddaja-header">
                <div>
                  <div class="dijak-info">
                    <?php echo htmlspecialchars($oddaja['ime_dijaka'] . ' ' . $oddaja['priimek_dijaka']); ?>
                    <span class="dijak-razred"><?php echo htmlspecialchars($oddaja['razred']); ?></span>
                  </div>
                  <div class="naloga-info">
                    <strong>Predmet:</strong> <?php echo htmlspecialchars($oddaja['ime_predmeta']); ?> | 
                    <strong>Naloga:</strong> <?php echo htmlspecialchars($oddaja['naslov']); ?>
                  </div>
                  <div class="naloga-info">
                    <strong>Pregledano:</strong> <?php echo $oddaja['datum_pregleda'] ? date('d.m.Y H:i', strtotime($oddaja['datum_pregleda'])) : '/'; ?>
                  </div>
                </div>
                <span class="status-badge status-<?php echo $oddaja['status']; ?>">
                  <?php echo $oddaja['status'] === 'pregledano' ? 'Odobreno' : 'Zavrnjeno'; ?>
                </span>
              </div>
              <div class="datoteka-info">
                <strong> Oddana datoteka:</strong> <?php echo htmlspecialchars($oddaja['datoteka_ime']); ?><br>
                <a href="<?php echo htmlspecialchars($oddaja['datoteka_link']); ?>" download class="btn btn-prenesi">⬇ Prenesi datoteko</a>
                 </div>
              <?php if ($oddaja['komentar_profesorja']): ?>
                <div class="komentar-box">
                  <strong>Vaš komentar:</strong><br>
                  <?php echo nl2br(htmlspecialchars($oddaja['komentar_profesorja'])); ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <div class="footer">&copy; 2025 E-Ocene. Vse pravice pridržane.</div>

  <script>
    function switchTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
      });
      document.getElementById('tab-' + tabName).classList.add('active');
      event.target.classList.add('active');
    }

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