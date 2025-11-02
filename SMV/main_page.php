<?php
session_start();

// Preveri ali je uporabnik prijavljen
if (!isset($_SESSION['uporabnik_id']) || !isset($_SESSION['uporabnik_tip'])) {
    header('Location: prijava.php');
    exit;
}

// Pridobi podatke uporabnika
$ime = $_SESSION['ime'] ?? 'Uporabnik';
$priimek = $_SESSION['priimek'] ?? '';
$uporabnik_tip = $_SESSION['uporabnik_tip'];

// Prva črka imena za avatar
$prva_crka = mb_strtoupper(mb_substr($ime, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>E-Dnevnik | E-Ocene</title>
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
      text-align: center;
      flex: 1;
    }

    .pozdrav {
      font-size: 26px;
      margin-bottom: 50px;
      color: #2c2c2c;
      font-weight: 600;
    }

    .pozdrav .ime-uporabnika {
      color: #8884FF;
    }

    .vrsta {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 30px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .gumb {
      width: calc(33.333% - 20px);
      min-width: 250px;
      max-width: 350px;
      height: 170px;
      background: white;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      color: #444;
      font-weight: bold;
      font-size: 19px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .gumb:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.12);
      color: #8884FF;
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

    @media (max-width: 900px) {
      .gumb {
        width: calc(50% - 15px);
      }
    }

    @media (max-width: 600px) {
      .gumb {
        width: 100%;
      }
      
      .vsebina {
        padding: 20px;
      }
      
      .pozdrav {
        font-size: 20px;
        margin-bottom: 30px;
      }

      .header {
        padding: 0 20px;
      }

      .header h1 {
        font-size: 18px;
      }
    }
  </style>
</head>
<body>

  <div class="header">
    <h1>E-Ocene</h1>
    
    <div class="avatar-container">
      <div class="avatar-link" id="avatar"><?php echo htmlspecialchars($prva_crka); ?></div>
      <a href="odjava.php" id="odjava-menu">Odjava</a>
    </div>
  </div>

  <div class="vsebina">
    <div class="pozdrav">
      Dobrodošli nazaj, <span class="ime-uporabnika"><?php echo htmlspecialchars($ime); ?></span>!
    </div>

    <div class="vrsta">
      <?php if ($uporabnik_tip === 'dijak'): ?>
        <!-- Dijak gumbi -->
        <a href="ocene.php" class="gumb">Ocene</a>
        <a href="testi.php" class="gumb">Testi</a>
        <a href="izostanki.php" class="gumb">Izostanki</a>
        <a href="domace_naloge.php" class="gumb">Domače naloge</a>
        <a href="projekti.php" class="gumb">Projekti</a>
        <a href="obvestila.php" class="gumb">Obvestila</a>

      <?php elseif ($uporabnik_tip === 'profesor'): ?>
        <!-- Profesor gumbi -->
        <a href="ocene_vnos.php" class="gumb">Vnos ocen</a>
        <a href="dijaki_pregled.php" class="gumb">Dijaki</a>
        <a href="domace_naloge_vnos.php" class="gumb">Domače naloge</a>
        <a href="razredi.php" class="gumb">Razredi</a>
        <a href="predmeti.php" class="gumb">Predmeti</a>
        <a href="obvestila_vnos.php" class="gumb">Obvestila</a>

      <?php else: ?>
        <p style="color: red;">Napaka: Neznan tip uporabnika.</p>
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