<?php
session_start();
require_once 'database.php';

$napaka = '';
$vsi_predmeti = [];

// Pridobi vse razpoložljive predmete za prikaz v formi
try {
    $stmt = $pdo->query("SELECT predmet_id, ime_predmeta FROM predmet ORDER BY ime_predmeta");
    $vsi_predmeti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = trim($_POST['ime_reg'] ?? '');
    $priimek = trim($_POST['priimek_reg'] ?? '');
    $email = trim($_POST['email_reg'] ?? '');
    $razred = trim($_POST['razred_reg'] ?? '');
    $geslo = $_POST['geslo_reg'] ?? '';
    $geslo2 = $_POST['geslo2_reg'] ?? '';
    $izbrani_predmeti = $_POST['predmeti'] ?? [];
    
    if (empty($ime) || empty($priimek) || empty($email) || empty($razred) || empty($geslo)) {
        $napaka = 'Vsa polja so obvezna.';
    } elseif ($geslo !== $geslo2) {
        $napaka = 'Gesli se ne ujemata.';
    } elseif (strlen($geslo) < 10 || !preg_match('/\d/', $geslo) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $geslo)) {
        $napaka = 'Geslo mora imeti vsaj 10 znakov, številko in poseben znak.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $napaka = 'Neveljaven e-poštni naslov.';
    } else {
        $email_parts = explode('@', $email);
        $domena = strtolower($email_parts[1] ?? '');
        
        $dovoljene_domene = ['dijak.si', 'sola.si'];
        if (!in_array($domena, $dovoljene_domene)) {
            $napaka = 'E-poštni naslov mora biti @dijak.si ali @sola.si';
        } else {
            try {
                $hashed_geslo = password_hash($geslo, PASSWORD_DEFAULT);
                $razred_lower = strtolower($razred);
                
                if ($razred_lower === 'profesor') {
                    // PREVERI ČE JE IZBRAL VSAJ EN PREDMET
                    if (empty($izbrani_predmeti)) {
                        $napaka = 'Profesor mora izbrati vsaj en predmet, ki ga uči.';
                    } else {
                        $stmt = $pdo->prepare("SELECT profesor_id FROM profesorji WHERE gmail = :email");
                        $stmt->execute(['email' => $email]);
                        if ($stmt->fetch()) {
                            $napaka = 'Ta e-poštni naslov je že registriran.';
                        } else {
                            $pdo->beginTransaction();
                            
                            try {
                                $stmt = $pdo->prepare("INSERT INTO profesorji (ime, priimek, gmail, geslo) VALUES (:ime, :priimek, :email, :geslo)");
                                $stmt->execute([
                                    'ime' => $ime,
                                    'priimek' => $priimek,
                                    'email' => $email,
                                    'geslo' => $hashed_geslo
                                ]);
                                
                                $novi_profesor_id = $pdo->lastInsertId();
                                
                                // DODAJ IZBRANE PREDMETE
                                $insert_predmet = $pdo->prepare("INSERT INTO profesor_predmet (profesor_id, predmet_id) VALUES (:profesor_id, :predmet_id)");
                                
                                foreach ($izbrani_predmeti as $predmet_id) {
                                    $insert_predmet->execute([
                                        'profesor_id' => $novi_profesor_id,
                                        'predmet_id' => (int)$predmet_id
                                    ]);
                                }
                                
                                // PRIDOBI IMENA IZBRANIH PREDMETOV
                                $placeholders = implode(',', array_fill(0, count($izbrani_predmeti), '?'));
                                $stmt = $pdo->prepare("SELECT ime_predmeta FROM predmet WHERE predmet_id IN ($placeholders)");
                                $stmt->execute($izbrani_predmeti);
                                $predmeti_imena = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                $pdo->commit();
                                
                                $seznam_predmetov = implode(', ', $predmeti_imena);
                                $st_predmetov = count($predmeti_imena);
                                $_SESSION['registracija_uspesna'] = 'Uspešno ste se registrirali kot profesor!<br>Izbrani predmeti (' . $st_predmetov . '): <strong>' . $seznam_predmetov . '</strong><br>Prijavite se.';
                                header('Location: prijava.php');
                                exit;
                                
                            } catch (Exception $e) {
                                $pdo->rollBack();
                                throw $e;
                            }
                        }
                    }
                    
                } elseif (preg_match('/^[REre][1-4][abcABC]$/', $razred)) {
                    $stmt = $pdo->prepare("SELECT id_dijaka FROM dijaki WHERE gmail = :email");
                    $stmt->execute(['email' => $email]);
                    if ($stmt->fetch()) {
                        $napaka = 'Ta e-poštni naslov je že registriran.';
                    } else {
                        $razred_upper = strtoupper($razred);
                        $stmt = $pdo->prepare("SELECT razred_id FROM razred WHERE UPPER(oznaka) = :oznaka");
                        $stmt->execute(['oznaka' => $razred_upper]);
                        $razred_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$razred_data) {
                            $napaka = "Razred {$razred} ne obstaja v sistemu. Kontaktirajte administratorja.";
                        } else {
                            // DOLOČI PREDMETE GLEDE NA RAZRED
                            $predmeti_po_razredu = [
                                'R1A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Računalništvo', 'Fizika'],
                                'R2A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Računalništvo', 'Zgodovina', 'Kemija'],
                                'R3A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Računalništvo', 'Geografija', 'Biologija'],
                                'R4A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Računalništvo', 'Fizika', 'Zgodovina'],
                                'E1A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Fizika', 'Računalništvo'],
                                'E2A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Fizika', 'Kemija', 'Računalništvo'],
                                'E3A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Fizika', 'Geografija', 'Računalništvo'],
                                'E4A' => ['Matematika', 'Slovenščina', 'Angleščina', 'Fizika', 'Zgodovina', 'Računalništvo']
                            ];
                            
                            $predmeti_za_razred = $predmeti_po_razredu[$razred_upper] ?? [];
                            
                            if (empty($predmeti_za_razred)) {
                                $napaka = "Razred {$razred_upper} nima definiranih predmetov.";
                            } else {
                                $pdo->beginTransaction();
                                
                                try {
                                    $stmt = $pdo->prepare("INSERT INTO dijaki (ime_dijaka, priimek_dijaka, razred, gmail, geslo, razred_id) 
                                                           VALUES (:ime, :priimek, :razred, :email, :geslo, :razred_id)");
                                    $stmt->execute([
                                        'ime' => $ime,
                                        'priimek' => $priimek,
                                        'razred' => $razred_upper,
                                        'email' => $email,
                                        'geslo' => $hashed_geslo,
                                        'razred_id' => $razred_data['razred_id']
                                    ]);
                                    
                                    $novi_dijak_id = $pdo->lastInsertId();
                                    
                                    // Pridobi ID-je predmetov
                                    $placeholders = implode(',', array_fill(0, count($predmeti_za_razred), '?'));
                                    $stmt = $pdo->prepare("SELECT predmet_id, ime_predmeta FROM predmet WHERE ime_predmeta IN ($placeholders)");
                                    $stmt->execute($predmeti_za_razred);
                                    $predmeti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    $insert_predmet = $pdo->prepare("INSERT INTO dijak_predmet (id_dijaka, predmet_id) VALUES (:dijak_id, :predmet_id)");
                                    
                                    foreach ($predmeti as $predmet) {
                                        $insert_predmet->execute([
                                            'dijak_id' => $novi_dijak_id,
                                            'predmet_id' => $predmet['predmet_id']
                                        ]);
                                    }
                                    
                                    $pdo->commit();
                                    
                                    $seznam_predmetov = implode(', ', array_column($predmeti, 'ime_predmeta'));
                                    $st_predmetov = count($predmeti);
                                    $_SESSION['registracija_uspesna'] = 'Uspešno ste se registrirali kot dijak!<br>Razred <strong>' . $razred_upper . '</strong> ima ' . $st_predmetov . ' predmetov:<br><strong>' . $seznam_predmetov . '</strong><br>Prijavite se.';
                                    header('Location: prijava.php');
                                    exit;
                                    
                                } catch (Exception $e) {
                                    $pdo->rollBack();
                                    throw $e;
                                }
                            }
                        }
                    }
                    
                } else {
                    $napaka = 'Neveljaven razred. Vnesite veljaven razred (npr. R1A, E4A) ali "profesor".';
                }
                
            } catch (PDOException $e) {
                $napaka = 'Napaka pri registraciji. Poskusite ponovno.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registracija | E-Ocene</title>
  <style>
    body {
      margin: 0;
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: Arial, sans-serif;
      padding: 20px;
    }

    .gumb {
      position: absolute;
      top: 30px;
      right: 30px;
      background: white;
      color: #4361ee;
      padding: 10px 20px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: bold;
    }

    .gumb:hover {
      background: #f0f0f0;
    }

    .okno {
      background: white;
      width: 100%;
      max-width: 500px;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
      text-align: center;
      max-height: 90vh;
      overflow-y: auto;
    }

    h2 {
      color: #333;
      margin-bottom: 20px;
    }

    input {
      width: 100%;
      padding: 12px;
      margin: 8px 0;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-sizing: border-box;
      font-size: 14px;
    }

    input:focus {
      outline: none;
      border-color: #4361ee;
    }

    button {
      width: 100%;
      padding: 12px;
      background: #4361ee;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      margin-top: 10px;
      cursor: pointer;
      font-weight: bold;
    }

    button:hover {
      background: #3651d4;
    }

    .napaka {
      color: #d32f2f;
      font-size: 14px;
      text-align: left;
      margin-top: 10px;
      padding: 10px;
      background: #ffe6e6;
      border-radius: 5px;
      border-left: 4px solid #d32f2f;
    }

    .predmeti-box {
      display: none;
      background: #f9f9f9;
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
      border: 1px solid #ddd;
      text-align: left;
    }

    .predmeti-box.active {
      display: block;
    }

    .predmeti-box h3 {
      margin-top: 0;
      color: #333;
      font-size: 16px;
      margin-bottom: 15px;
      text-align: center;
    }

    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
    }

    .checkbox-item input[type="checkbox"] {
      width: auto;
      margin: 0 8px 0 0;
      cursor: pointer;
    }

    .checkbox-item label {
      cursor: pointer;
      font-size: 14px;
      color: #333;
    }

    .info-text {
      font-size: 12px;
      color: #666;
      margin-top: 10px;
      text-align: center;
      font-style: italic;
    }
  </style>
  <script>
    function proveriRazred() {
      const razredInput = document.getElementById('razred_reg');
      const predmetiBox = document.getElementById('predmeti-box');
      const razred = razredInput.value.toLowerCase().trim();
      
      if (razred === 'profesor') {
        predmetiBox.classList.add('active');
      } else {
        predmetiBox.classList.remove('active');
        // Odstrani vse označene checkboxe
        const checkboxes = document.querySelectorAll('.predmeti-box input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
      }
    }
  </script>
</head>
<body>

  <a href="prijava.php" class="gumb">Nazaj na prijavo</a>

  <div class="okno">
    <form method="POST" action="">
      <h2>Registracija</h2>

      <?php if ($napaka): ?>
        <div class="napaka"><?php echo htmlspecialchars($napaka); ?></div>
      <?php endif; ?>

      <input type="text" name="ime_reg" placeholder="Ime" required value="<?php echo htmlspecialchars($_POST['ime_reg'] ?? ''); ?>">

      <input type="text" name="priimek_reg" placeholder="Priimek" required value="<?php echo htmlspecialchars($_POST['priimek_reg'] ?? ''); ?>">

      <input type="email" name="email_reg" placeholder="E-pošta" required value="<?php echo htmlspecialchars($_POST['email_reg'] ?? ''); ?>">

      <input 
        type="text" 
        id="razred_reg"
        name="razred_reg" 
        placeholder="Razred (R1A-R4A, E1A-E4A ali profesor)" 
        required 
        value="<?php echo htmlspecialchars($_POST['razred_reg'] ?? ''); ?>"
        onkeyup="proveriRazred()"
        onchange="proveriRazred()">

      
      <div id="predmeti-box" class="predmeti-box <?php echo (strtolower($_POST['razred_reg'] ?? '') === 'profesor') ? 'active' : ''; ?>">
        <h3> Izberite predmete, ki jih učite</h3>
        <div class="checkbox-group">
          <?php foreach ($vsi_predmeti as $predmet): ?>
            <div class="checkbox-item">
              <input 
                type="checkbox" 
                id="predmet_<?php echo $predmet['predmet_id']; ?>" 
                name="predmeti[]" 
                value="<?php echo $predmet['predmet_id']; ?>"
                <?php echo (in_array($predmet['predmet_id'], $_POST['predmeti'] ?? [])) ? 'checked' : ''; ?>>
              <label for="predmet_<?php echo $predmet['predmet_id']; ?>">
                <?php echo htmlspecialchars($predmet['ime_predmeta']); ?>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="info-text">*Izberite vsaj en predmet</p>
      </div>

      <input type="password" name="geslo_reg" placeholder="Geslo (min. 10 znakov)" required>

      <input type="password" name="geslo2_reg" placeholder="Ponovite geslo" required>

      <button type="submit">Registriraj se</button>
    </form>
  </div>

</body>
</html>