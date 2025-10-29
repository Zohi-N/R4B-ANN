<?php
session_start();
require_once 'database.php';

$napaka = '';
$uspeh = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = trim($_POST['ime_reg'] ?? '');
    $priimek = trim($_POST['priimek_reg'] ?? '');
    $email = trim($_POST['email_reg'] ?? '');
    $razred = trim($_POST['razred_reg'] ?? '');
    $geslo = $_POST['geslo_reg'] ?? '';
    $geslo2 = $_POST['geslo2_reg'] ?? '';
    
    // Validacija
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
            // Hash gesla
            $hashed_geslo = password_hash($geslo, PASSWORD_DEFAULT);
            
            // Preveri tip uporabnika glede na razred
            $razred_lower = strtolower($razred);
            
            if ($razred_lower === 'profesor') {
                // REGISTRACIJA PROFESORJA
                
                // Preveri, če email že obstaja
                $stmt = $pdo->prepare("SELECT profesor_id FROM profesorji WHERE gmail = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $napaka = 'Ta e-poštni naslov je že registriran.';
                } else {
                    // Vstavi profesorja
                    $stmt = $pdo->prepare("INSERT INTO profesorji (ime, priimek, gmail, geslo) VALUES (:ime, :priimek, :email, :geslo)");
                    $stmt->execute([
                        'ime' => $ime,
                        'priimek' => $priimek,
                        'email' => $email,
                        'geslo' => $hashed_geslo
                    ]);
                    $uspeh = 'Uspešno ste se registrirali kot profesor!';
                }
                
            } elseif ($razred_lower === 'admin') {
                // REGISTRACIJA ADMINA (lahko tudi v posebno tabelo ali z flag-om)
                
                // Preveri, če email že obstaja
                $stmt = $pdo->prepare("SELECT profesor_id FROM profesorji WHERE gmail = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $napaka = 'Ta e-poštni naslov je že registriran.';
                } else {
                    // Vstavi admina kot profesorja (lahko dodaš flag za razlikovanje)
                    $stmt = $pdo->prepare("INSERT INTO profesorji (ime, priimek, gmail, geslo) VALUES (:ime, :priimek, :email, :geslo)");
                    $stmt->execute([
                        'ime' => $ime,
                        'priimek' => $priimek,
                        'email' => $email,
                        'geslo' => $hashed_geslo
                    ]);
                    $uspeh = 'Uspešno ste se registrirali kot administrator!';
                }
                
            } elseif (preg_match('/^[REre][1-4][abcABC]$/', $razred)) {
                // REGISTRACIJA DIJAKA (format: R1A, E4B, itd.)
                
                // Preveri, če email že obstaja
                $stmt = $pdo->prepare("SELECT id_dijaka FROM dijaki WHERE gmail = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $napaka = 'Ta e-poštni naslov je že registriran.';
                } else {
                    // Poskusi najti razred v bazi
                    $razred_upper = strtoupper($razred);
                    $stmt = $pdo->prepare("SELECT razred_id FROM razred WHERE UPPER(oznaka) = :oznaka");
                    $stmt->execute(['oznaka' => $razred_upper]);
                    $razred_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$razred_data) {
                        $napaka = "Razred {$razred} ne obstaja v sistemu. Kontaktirajte administratorja.";
                    } else {
                        // Generiraj ID dijaka (ali uporabi AUTO_INCREMENT če imaš SERIAL)
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(id_dijaka), 0) + 1 as next_id FROM dijaki");
                        $stmt->execute();
                        $next_id = $stmt->fetch(PDO::FETCH_ASSOC)['next_id'];
                        
                        // Vstavi dijaka
                        $stmt = $pdo->prepare("INSERT INTO dijaki (id_dijaka, ime_dijaka, priimek_dijaka, razred, gmail, geslo, razred_id) 
                                               VALUES (:id, :ime, :priimek, :razred, :email, :geslo, :razred_id)");
                        $stmt->execute([
                            'id' => $next_id,
                            'ime' => $ime,
                            'priimek' => $priimek,
                            'razred' => $razred_upper,
                            'email' => $email,
                            'geslo' => $hashed_geslo,
                            'razred_id' => $razred_data['razred_id']
                        ]);
                        $uspeh = 'Uspešno ste se registrirali kot dijak!';
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

    .okno {
      background: white;
      width: 100%;
      max-width: 400px;
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

    .uspeh {
      color: #2e7d32;
      font-size: 14px;
      text-align: center;
      margin-top: 10px;
      padding: 10px;
      background: #e6ffe6;
      border-radius: 5px;
      border-left: 4px solid #2e7d32;
    }

    .info {
      font-size: 12px;
      color: #666;
      margin-top: 15px;
      padding: 10px;
      background: #f5f5f5;
      border-radius: 5px;
      text-align: left;
    }

    .info strong {
      color: #333;
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

      <?php if ($uspeh): ?>
        <div class="uspeh"><?php echo htmlspecialchars($uspeh); ?> <a href="prijava.php">Prijavi se</a></div>
      <?php endif; ?>

      <input type="text" name="ime_reg" placeholder="Ime" required value="<?php echo htmlspecialchars($_POST['ime_reg'] ?? ''); ?>">

      <input type="text" name="priimek_reg" placeholder="Priimek" required value="<?php echo htmlspecialchars($_POST['priimek_reg'] ?? ''); ?>">

      <input type="email" name="email_reg" placeholder="E-pošta" required value="<?php echo htmlspecialchars($_POST['email_reg'] ?? ''); ?>">

      <input type="text" name="razred_reg" placeholder="Razred (npr. R1A, E4C) ali 'profesor' ali 'admin'" required value="<?php echo htmlspecialchars($_POST['razred_reg'] ?? ''); ?>">

      <input type="password" name="geslo_reg" placeholder="Geslo (min. 10 znakov)" required>

      <input type="password" name="geslo2_reg" placeholder="Ponovite geslo" required>

      <button type="submit">Registriraj se</button>

      <div class="info">
        <strong>Navodila:</strong><br>
        • <strong>Dijak:</strong> Vnesite razred (npr. R1A, E4B)<br>
        • <strong>Profesor:</strong> Vnesite "profesor"<br>
        • <strong>Admin:</strong> Vnesite "admin"
      </div>
    </form>
  </div>

</body>
</html>