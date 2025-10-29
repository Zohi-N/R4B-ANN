<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>E-Dnevnik | E-Ocene</title>
  <style>
    body {
      margin: 0;
      background: #f8f9ff;
      font-family: 'Segoe UI', Arial, sans-serif;
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
    }

    .header h1 {
      font-size: 22px;
      font-weight: bold;
    }

    /* Kontejner za avatar + meni */
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
    }

    /* Meni za odjavo – centriran POD AVATARJEM */
    #odjava-menu {
      display: none;
      position: absolute;
      top: 100%; /* neposredno pod avatarjem */
      left: 50%;
      transform: translateX(-50%); /* centriranje glede na avatar */
      background: white;
      color: #333;
      padding: 8px 16px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      font-size: 14px;
      text-decoration: none;
      text-align: center;
      white-space: nowrap;
      margin-top: 8px; /* majhen razmik */
      z-index: 10;
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

    .vrsta {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 30px;
    }

    .gumb {
      width: calc(33.333% - 20px);
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
      height: 40px;
      background: linear-gradient(135deg, #8884FF, #AB64D6);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      box-shadow: 0 -2px 6px rgba(0,0,0,0.08);
      margin-top: auto;
    }
  </style>
</head>
<body>

  <div class="header">
    <h1>E-Ocene</h1>
    

    <div class="avatar-container">
      <div class="avatar-link" id="avatar">M</div>
      <a href="prijava.php" id="odjava-menu">Odjava</a>
    </div>
  </div>

  <div class="vsebina">
    <div class="pozdrav">Dobrodošli nazaj, Matej!</div>

    <div class="vrsta">
      <a href="ocene.html" class="gumb">Ocene</a>
      <a href="testi.html" class="gumb">Testi</a>
      <a href="izostanki.html" class="gumb">Izostanki</a>
      <a href="domace_naloge.html" class="gumb">Domače naloge</a>
      <a href="projekti.html" class="gumb">Projekti</a>
      <a href="obvestila.html" class="gumb">Obvestila</a>
    </div>
  </div>

  <div class="footer">
    &copy; 2025 E-Ocene. Vse pravice pridržane.
  </div>

  <script>
    document.getElementById('avatar').addEventListener('click', function(e) {
      e.stopPropagation();
      const menu = document.getElementById('odjava-menu');
      if (menu.style.display === 'block') {
        menu.style.display = 'none';
      } else {
        menu.style.display = 'block';
      }
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