<?php
session_start();
require_once 'database.php';

$napaka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = trim($_POST['ime_reg'] ?? '');
    $priimek = trim($_POST['priimek_reg'] ?? '');
    $email = trim($_POST['email_reg'] ?? '');
    $razred = trim($_POST['razred_reg'] ?? '');
    $geslo = $_POST['geslo_reg'] ?? '';
    $geslo2 = $_POST['geslo2_reg'] ?? '';
    
    if (empty($ime) || empty($priimek) || empty($email) || empty($razred) || empty($geslo)) {
        $napaka = 'Vsa polja so obvezna.';
    } elseif ($geslo !== $geslo2) {
        $napaka = 'Gesli se ne ujemata.';
    } elseif (strlen($geslo) < 10 || !preg_match('/\d/', $geslo) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $geslo)) {
        $napaka = 'Geslo mora imeti vsaj 10 znakov, številko in poseben znak.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $napaka = 'Neveljaven e-poštni naslov.';
    } else {
        try {
            $hashed_geslo = password_hash($geslo, PASSWORD_DEFAULT);
            $razred_lower = strtolower($razred);
            
            // PROFESOR ali ADMIN REGISTRACIJA
            if ($razred_lower === 'profesor' || $razred_lower === 'admin') {
                // Preveri če email končuje na @sola.si
                if (!preg_match('/@sola\.si$/i', $email)) {
                    $napaka = 'Profesor/Admin mora imeti email končnico @sola.si (npr. ime.priimek@sola.si)';
                } else {
                    $stmt = $pdo->prepare("SELECT profesor_id FROM profesorji WHERE gmail = :email");
                    $stmt->execute(['email' => $email]);
                    if ($stmt->fetch()) {
                        $napaka = 'Ta e-poštni naslov je že registriran.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO profesorji (ime, priimek, gmail, geslo) VALUES (:ime, :priimek, :email, :geslo)");
                        $stmt->execute([
                            'ime' => $ime,
                            'priimek' => $priimek,
                            'email' => $email,
                            'geslo' => $hashed_geslo
                        ]);
                        
                        $tip = ($razred_lower === 'admin') ? 'administrator' : 'profesor';
                        $_SESSION['registracija_uspesna'] = "Uspešno ste se registrirali kot {$tip}! Prijavite se.";
                        header('Location: prijava.php');
                        exit;
                    }
                }
                
            // DIJAK REGISTRACIJA
            } elseif (preg_match('/^[REre][1-4][abcABC]$/', $razred)) {
                // Preveri če email končuje na @dijak.si
                if (!preg_match('/@dijak\.si$/i', $email)) {
                    $napaka = 'Dijak mora imeti email končnico @dijak.si (npr. ime.priimek@dijak.si)';
                } else {
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
                            $_SESSION['registracija_uspesna'] = 'Uspešno ste se registrirali kot dijak! Prijavite se.';
                            header('Location: prijava.php');
                            exit;
                        }
                    }
                }
                
            } else {
                $napaka = 'Neveljaven razred. Vnesite veljaven razred (npr. R1A, E4B) ali "profesor" ali "admin".';
            }
            
        } catch (PDOException $e) {
            $napaka = 'Napaka pri registraciji. Poskusite ponovno.';
            error_log($e->getMessage());
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
      max-width: 450px;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
      text-align: center;
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

    .info {
      font-size: 13px;
      color: #666;
      margin-top: 15px;
      padding: 15px;
      background: #f5f5f5;
      border-radius: 8px;
      text-align: left;
      line-height: 1.8;
    }

    .info strong {
      color: #333;
      display: block;
      margin-bottom: 5px;
    }

    .info code {
      background: #e0e0e0;
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Courier New', monospace;
      color: #d32f2f;
      font-size: 12px;
    }

    .email-hint {
      background: #e3f2fd;
      padding: 12px;
      border-radius: 5px;
      margin-top: 12px;
      font-size: 12px;
      color: #1565c0;
      border-left: 4px solid #2196f3;
      text-align: left;
    }
  </style>
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
      
      <div class="email-hint">
        <strong>📧 Pomembno - Email končnice:</strong><br>
        • Dijaki: <code>ime.priimek@dijak.si</code><br>
        • Profesorji: <code>ime.priimek@sola.si</code><br>
        • Admin: <code>ime.priimek@sola.si</code>
      </div>

      <input type="text" name="razred_reg" placeholder="Razred (npr. R1A, E4C) ali 'profesor' ali 'admin'" required value="<?php echo htmlspecialchars($_POST['razred_reg'] ?? ''); ?>">

      <input type="password" name="geslo_reg" placeholder="Geslo (min. 10 znakov)" required>

      <input type="password" name="geslo2_reg" placeholder="Ponovite geslo" required>

      <button type="submit">Registriraj se</button>

      <div class="info">
        <strong>📝 Navodila za razred:</strong>
        • <strong>Dijak:</strong> Vnesite razred <code>R1A</code>, <code>R2A</code>, <code>E4B</code>...<br>
        • <strong>Profesor:</strong> Vnesite <code>profesor</code><br>
        • <strong>Admin:</strong> Vnesite <code>admin</code>
      </div>
    </form>
  </div>

</body>
</html>