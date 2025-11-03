<?php
session_start();
require_once 'database.php';

$napaka = '';
$uspeh = '';

// Prikaži sporočilo o uspešni registraciji
if (isset($_SESSION['registracija_uspesna'])) {
    $uspeh = $_SESSION['registracija_uspesna'];
    unset($_SESSION['registracija_uspesna']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email_prijava'] ?? '');
    $geslo = $_POST['geslo_prijava'] ?? '';
    
    if (empty($email) || empty($geslo)) {
        $napaka = 'Vnesite e-pošto in geslo.';
    } else {
        try {
            // Preveri ali je profesor
            $stmt = $pdo->prepare("SELECT profesor_id, ime, priimek, geslo FROM profesorji WHERE gmail = :email");
            $stmt->execute(['email' => $email]);
            $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($profesor && password_verify($geslo, $profesor['geslo'])) {
                // Prijava profesorja
                $_SESSION['uporabnik_id'] = $profesor['profesor_id'];
                $_SESSION['uporabnik_tip'] = 'profesor';
                $_SESSION['ime'] = $profesor['ime'];
                $_SESSION['priimek'] = $profesor['priimek'];
                $_SESSION['email'] = $email;
                
                // Preveri če je admin
                if (strpos($email, 'admin@') !== false || $email === 'admin@sola.si') {
                    $_SESSION['uporabnik_tip'] = 'admin';
                }
                
                header('Location: main_page.php');
                exit;
            }
            
            // Preveri ali je dijak
            $stmt = $pdo->prepare("SELECT id_dijaka, ime_dijaka, priimek_dijaka, geslo, razred_id, razred FROM dijaki WHERE gmail = :email");
            $stmt->execute(['email' => $email]);
            $dijak = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dijak && password_verify($geslo, $dijak['geslo'])) {
                // Prijava dijaka
                $_SESSION['uporabnik_id'] = $dijak['id_dijaka'];
                $_SESSION['uporabnik_tip'] = 'dijak';
                $_SESSION['ime'] = $dijak['ime_dijaka'];
                $_SESSION['priimek'] = $dijak['priimek_dijaka'];
                $_SESSION['razred_id'] = $dijak['razred_id'];
                $_SESSION['razred'] = $dijak['razred'];
                $_SESSION['email'] = $email;
                
                header('Location: main_page.php');
                exit;
            }
            
            // Če ni najden noben uporabnik
            $napaka = 'Napačna e-pošta ali geslo.';
            
        } catch (PDOException $e) {
            $napaka = 'Napaka pri prijavi. Poskusite ponovno.';
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
  <title>Prijava | E-Ocene</title>
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
      max-width: 400px;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
      text-align: center;
    }

    h2 {
      color: #333;
      margin-bottom: 30px;
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
      margin-bottom: 15px;
      padding: 12px;
      background: #ffe6e6;
      border-radius: 5px;
      border-left: 4px solid #d32f2f;
    }

    .uspeh {
      color: #2e7d32;
      font-size: 14px;
      text-align: center;
      margin-bottom: 15px;
      padding: 12px;
      background: #e8f5e9;
      border-radius: 5px;
      border-left: 4px solid #4caf50;
      line-height: 1.6;
    }

    .ste_pozabiligeslo {
      margin-top: 20px;
    }

    .ste_pozabiligeslo a {
      color: #4361ee;
      text-decoration: none;
      font-size: 14px;
    }

    .ste_pozabiligeslo a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <a href="REGISTRACIJA.php" class="gumb">Registracija</a>

  <div class="okno">
    <form method="POST" action="">
      <h2>Prijava</h2>
      
      <?php if ($uspeh): ?>
        <div class="uspeh"><?php echo $uspeh; ?></div>
      <?php endif; ?>

      <?php if ($napaka): ?>
        <div class="napaka"><?php echo htmlspecialchars($napaka); ?></div>
      <?php endif; ?>

      <input type="email" name="email_prijava" placeholder="E-pošta" required value="<?php echo htmlspecialchars($_POST['email_prijava'] ?? ''); ?>">
      <input type="password" name="geslo_prijava" placeholder="Geslo" required>

      <button type="submit">Prijavi se</button>

      <div class="ste_pozabiligeslo">
        <a href="pozabljeno_geslo.php">Ste pozabili geslo?</a> 
      </div>
    </form>
  </div>

</body>
</html>