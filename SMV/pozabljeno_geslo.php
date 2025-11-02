<?php
session_start();
require_once 'database.php';

$napaka = '';
$uspeh = '';
$prikaziFormoGesla = false;
$email_za_reset = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // KORAK 1: Preverjanje e-pošte
    if (isset($_POST['akcija']) && $_POST['akcija'] === 'preveri_email') {
        $email = trim($_POST['email_reset'] ?? '');
        
        if (empty($email)) {
            $napaka = 'Vnesite e-pošto.';
        } else {
            try {
                // Preverimo ali obstaja profesor
                $stmt = $pdo->prepare("SELECT profesor_id FROM profesorji WHERE gmail = :email");
                $stmt->execute(['email' => $email]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($profesor) {
                    $prikaziFormoGesla = true;
                    $email_za_reset = $email;
                    $_SESSION['reset_email'] = $email;
                } else {
                    // Preverimo ali obstaja dijak
                    $stmt = $pdo->prepare("SELECT id_dijaka FROM dijaki WHERE gmail = :email");
                    $stmt->execute(['email' => $email]);
                    $dijak = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($dijak) {
                        $prikaziFormoGesla = true;
                        $email_za_reset = $email;
                        $_SESSION['reset_email'] = $email;
                    } else {
                        $napaka = 'Ta e-pošta ni registrirana v sistemu.';
                    }
                }
            } catch (PDOException $e) {
                $napaka = 'Napaka pri preverjanju. Poskusite ponovno.';
                error_log('Napaka pri preverjanju e-pošte: ' . $e->getMessage());
            }
        }
    }
    
    // KORAK 2: Sprememba gesla
    if (isset($_POST['akcija']) && $_POST['akcija'] === 'sprememba') {
        $email = $_SESSION['reset_email'] ?? '';
        $novo_geslo = $_POST['novo_geslo'] ?? '';
        $ponovljeno_geslo = $_POST['ponovljeno_geslo'] ?? '';
        
        if (empty($email)) {
            $napaka = 'Napaka - e-pošta ni bila najdena. Poskusite ponovno.';
            $prikaziFormoGesla = false;
        } elseif (empty($novo_geslo) || empty($ponovljeno_geslo)) {
            $napaka = 'Vnesite novo geslo.';
            $prikaziFormoGesla = true;
            $email_za_reset = $email;
            $_SESSION['reset_email'] = $email;
        } elseif ($novo_geslo !== $ponovljeno_geslo) {
            $napaka = 'Novi gesli se ne ujemata.';
            $prikaziFormoGesla = true;
            $email_za_reset = $email;
            $_SESSION['reset_email'] = $email;
        } elseif (strlen($novo_geslo) < 10) {
            $napaka = 'Geslo mora imeti najmanj 10 znakov.';
            $prikaziFormoGesla = true;
            $email_za_reset = $email;
            $_SESSION['reset_email'] = $email;
        } else {
            try {
                $hashovano_geslo = password_hash($novo_geslo, PASSWORD_DEFAULT);
                
                // Poskusi update pri profesorjih
                $stmt = $pdo->prepare("UPDATE profesorji SET geslo = :geslo WHERE gmail = :email");
                $stmt->execute(['geslo' => $hashovano_geslo, 'email' => $email]);
                
                $redkov_posodobljenih = $stmt->rowCount();
                
                if ($redkov_posodobljenih === 0) {
                    // Poskusi update pri dijakih
                    $stmt = $pdo->prepare("UPDATE dijaki SET geslo = :geslo WHERE gmail = :email");
                    $stmt->execute(['geslo' => $hashovano_geslo, 'email' => $email]);
                    $redkov_posodobljenih = $stmt->rowCount();
                }
                
                if ($redkov_posodobljenih > 0) {
                    // Čisti sejo
                    unset($_SESSION['reset_email']);
                    
                    // Preusmeri na prijavo s sporočilom
                    $_SESSION['sporocilo_uspeha'] = 'Geslo je bilo uspešno spremenjeno! Prijavite se s novim geslom.';
                    header('Location: PRIJAVA.php?status=ok');
                    exit;
                } else {
                    $napaka = 'Napaka pri spremembi gesla. Poskusite ponovno.';
                    $prikaziFormoGesla = true;
                    $email_za_reset = $email;
                    $_SESSION['reset_email'] = $email;
                }
            } catch (PDOException $e) {
                $napaka = 'Napaka pri spremembi gesla. Poskusite ponovno.';
                error_log('Napaka pri spremembi gesla: ' . $e->getMessage());
                $prikaziFormoGesla = true;
                $email_za_reset = $email;
                $_SESSION['reset_email'] = $email;
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
      text-align: center;
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

  <div class="okno">
    
    <?php if ($prikaziUspehu): ?>
      <div class="velika-uspeh">
        <h3>✓ Geslo uspešno spremenjeno!</h3>
        <p><?php echo htmlspecialchars($_SESSION['sporocilo_uspeha']); ?></p>
      </div>
      <button onclick="window.location.href='PRIJAVA.php'">Pojdi na prijavo</button>
      <?php unset($_SESSION['sporocilo_uspeha']); ?>
    
    <?php elseif ($prikaziFormoGesla): ?>
      <form method="POST" action="">
        <h2>Sprememba gesla</h2>
        
        <?php if ($napaka): ?>
          <div class="napaka"><?php echo htmlspecialchars($napaka); ?></div>
        <?php endif; ?>

        <p>Vnesite novo geslo (najmanj 10 znakov):</p>
        
        <input type="password" name="novo_geslo" placeholder="Novo geslo" required>
        <input type="password" name="ponovljeno_geslo" placeholder="Ponovite novo geslo" required>

        <button type="submit">Spremeni geslo</button>

        <input type="hidden" name="akcija" value="sprememba">
        
        <div class="povratnica">
          <a href="pozabljeno_geslo.php">Nazaj na prejšnji korak</a>
        </div>
      </form>

    <?php else: ?>
      <form method="POST" action="">
        <h2>Pozabljeno geslo</h2>
        
        <?php if ($napaka): ?>
          <div class="napaka"><?php echo htmlspecialchars($napaka); ?></div>
        <?php endif; ?>

        <p>Vnesite e-pošto vašega računa:</p>
        <input type="email" name="email_reset" placeholder="E-pošta" required>

        <button type="submit">Nadaljuj</button>

        <input type="hidden" name="akcija" value="preveri_email">
        
        <div class="povratnica">
          <a href="PRIJAVA.php">Nazaj na prijavo</a>
        </div>
      </form>
    <?php endif; ?>

  </div>

</body>
</html>