<?php
session_start();
require_once 'database.php';

$napaka = '';
$uspeh = '';
$prikaziFormo = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // KORAK 1: Zahteva za ponastavitev gesla
    if (isset($_POST['akcija']) && $_POST['akcija'] === 'zahteva') {
        $email = trim($_POST['email_reset'] ?? '');
        
        if (empty($email)) {
            $napaka = 'Vnesite e-pošt�o.';
        } else {
            try {
                // Preverimo ali obstaja professor
                $stmt = $pdo->prepare("SELECT profesor_id, ime, priimek FROM profesorji WHERE gmail = :email");
                $stmt->execute(['email' => $email]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($profesor) {
                    // Generiraj token
                    $token = bin2hex(random_bytes(32));
                    $expiracija = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Shrani token v bazo (dodaj stolpca reset_token in reset_expiracija v tabelo profesorji)
                    $stmt = $pdo->prepare("UPDATE profesorji SET reset_token = :token, reset_expiracija = :expiracija WHERE gmail = :email");
                    $stmt->execute(['token' => $token, 'expiracija' => $expiracija, 'email' => $email]);
                    
                    $uspeh = 'Navodila za ponastavitev gesla so bila poslana na vašo e-poštÂ.';
                    // V praksi bi tukaj poslal email s povezavo
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_token'] = $token;
                    $prikaziFormo = true;
                } else {
                    // Preverimo ali obstaja dijak
                    $stmt = $pdo->prepare("SELECT id_dijaka, ime_dijaka, priimek_dijaka FROM dijaki WHERE gmail = :email");
                    $stmt->execute(['email' => $email]);
                    $dijak = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($dijak) {
                        $token = bin2hex(random_bytes(32));
                        $expiracija = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        
                        $stmt = $pdo->prepare("UPDATE dijaki SET reset_token = :token, reset_expiracija = :expiracija WHERE gmail = :email");
                        $stmt->execute(['token' => $token, 'expiracija' => $expiracija, 'email' => $email]);
                        
                        $uspeh = 'Navodila za ponastavitev gesla so bila poslana na vašo e-poštÂ.';
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_token'] = $token;
                        $prikaziFormo = true;
                    } else {
                        $napaka = 'Ta e-poštÂ ni registrirana v sistemu.';
                    }
                }
            } catch (PDOException $e) {
                $napaka = 'Napaka pri zahtevi. Poskusite ponovno.';
                error_log('Napaka pri zahtevi za reset: ' . $e->getMessage());
            }
        }
    }
    
    // KORAK 2: Sprememba gesla
    if (isset($_POST['akcija']) && $_POST['akcija'] === 'sprememba') {
        $email = $_SESSION['reset_email'] ?? '';
        $novo_geslo = $_POST['novo_geslo'] ?? '';
        $ponovljeno_geslo = $_POST['ponovljeno_geslo'] ?? '';
        
        if (empty($novo_geslo) || empty($ponovljeno_geslo)) {
            $napaka = 'Vnesite novo geslo.';
            $prikaziFormo = true;
        } elseif ($novo_geslo !== $ponovljeno_geslo) {
            $napaka = 'Gesli se ne ujemata.';
            $prikaziFormo = true;
        } elseif (strlen($novo_geslo) < 6) {
            $napaka = 'Geslo mora imeti najmanj 6 znakov.';
            $prikaziFormo = true;
        } else {
            try {
                $hashovano_geslo = password_hash($novo_geslo, PASSWORD_DEFAULT);
                
                // Poskusi update pri profesorjih
                $stmt = $pdo->prepare("UPDATE profesorji SET geslo = :geslo, reset_token = NULL, reset_expiracija = NULL WHERE gmail = :email");
                $stmt->execute(['geslo' => $hashovano_geslo, 'email' => $email]);
                
                if ($stmt->rowCount() === 0) {
                    // Poskusi update pri dijakih
                    $stmt = $pdo->prepare("UPDATE dijaki SET geslo = :geslo, reset_token = NULL, reset_expiracija = NULL WHERE gmail = :email");
                    $stmt->execute(['geslo' => $hashovano_geslo, 'email' => $email]);
                }
                
                if ($stmt->rowCount() > 0) {
                    // ÄŒisti sejo
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_token']);
                    
                    // Preusmeri na prijavo s sporočilom
                    $_SESSION['sporocilo_uspeha'] = 'Geslo je bilo uspešno spremenjeno! Prijavite se s novim geslom.';
                    header('Location: pozabljeno_geslo.php?status=ok');
                    exit;
                } else {
                    $napaka = 'Napaka pri spremembi gesla. Poskusite ponovno.';
                    $prikaziFormo = true;
                }
            } catch (PDOException $e) {
                $napaka = 'Napaka pri spremembi gesla. Poskusite ponovno.';
                error_log('Napaka pri spremembi gesla: ' . $e->getMessage());
                $prikaziFormo = true;
            }
        }
    }
}

// Preverka ali se prikazuje obvestilo o uspehu
$prikaziUspehu = isset($_GET['status']) && $_GET['status'] === 'ok' && isset($_SESSION['sporocilo_uspeha']);
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pozabljeno geslo | E-Ocene</title>
  <style>
    body {
      margin: 0;
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: Arial, sans-serif;
    }

    .gumb {
      position: absolute;
      top: 30px;
      left: 30px;
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
      width: 400px;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
      text-align: center;
    }

    h2 {
      color: #333;
      margin-bottom: 30px;
    }

    p {
      color: #666;
      font-size: 14px;
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
      margin: 10px 0;
      padding: 10px;
      background: #ffe6e6;
      border-radius: 5px;
      border-left: 4px solid #d32f2f;
    }

    .uspeh {
      color: #388e3c;
      font-size: 14px;
      text-align: left;
      margin: 10px 0;
      padding: 10px;
      background: #e8f5e9;
      border-radius: 5px;
      border-left: 4px solid #388e3c;
    }

    .velika-uspeh {
      background: #e8f5e9;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      text-align: center;
    }

    .velika-uspeh h3 {
      color: #388e3c;
      margin: 0;
    }

    .velika-uspeh p {
      color: #388e3c;
      margin: 10px 0 0 0;
    }

    .povratnica {
      margin-top: 20px;
    }

    .povratnica a {
      color: #4361ee;
      text-decoration: none;
      font-size: 14px;
    }

    .povratnica a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <a href="PRIJAVA.php" class="gumb">← Nazaj na prijavo</a>

  <div class="okno">
    
    <?php if ($prikaziUspehu): ?>
      <div class="velika-uspeh">
        <h3>✓ Geslo uspešno spremenjeno!</h3>
        <p><?php echo htmlspecialchars($_SESSION['sporocilo_uspeha']); ?></p>
      </div>
      <button onclick="window.location.href='PRIJAVA.php'">Pojdi na prijavo</button>
      <?php unset($_SESSION['sporocilo_uspeha']); ?>
    
    <?php elseif ($prikaziFormo): ?>
      <form method="POST" action="">
        <h2>Novo geslo</h2>
        
        <?php if ($napaka): ?>
          <div class="napaka"><?php echo htmlspecialchars($napaka); ?></div>
        <?php endif; ?>

        <p>Vnesite novo geslo:</p>
        <input type="password" name="novo_geslo" placeholder="Novo geslo" required>
        <input type="password" name="ponovljeno_geslo" placeholder="Ponovite geslo" required>

        <button type="submit">Nastavi novo geslo</button>

        <input type="hidden" name="akcija" value="sprememba">
        
        <div class="povratnica">
          <a href="pozabljeno_geslo.php">Nazaj</a>
        </div>
      </form>

    <?php else: ?>
      <form method="POST" action="">
        <h2>Pozabljeno geslo</h2>
        
        <?php if ($napaka): ?>
          <div class="napaka"><?php echo htmlspecialchars($napaka); ?></div>
        <?php endif; ?>

        <?php if ($uspeh): ?>
          <div class="uspeh"><?php echo htmlspecialchars($uspeh); ?></div>
        <?php endif; ?>

        <p>Vnesite e-poštÂo vašega računa in prejmite navodila za ponastavitev gesla:</p>
        <input type="email" name="email_reset" placeholder="E-poštÂa" required>

        <button type="submit">Zahtevaj ponastavitev</button>

        <input type="hidden" name="akcija" value="zahteva">
        
        <div class="povratnica">
          <a href="PRIJAVA.php">← Nazaj na prijavo</a>
        </div>
      </form>
    <?php endif; ?>

  </div>

</body>
</html>