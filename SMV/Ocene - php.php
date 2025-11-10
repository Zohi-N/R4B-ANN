<?php
session_start();

// 🔹 POVEZAVA Z BAZO
$conn = new mysqli("localhost", "root", "", "ucilnica");
if ($conn->connect_error) die("Napaka: " . $conn->connect_error);

// 🔹 ODJAVA
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: ucilnica.php");
    exit;
}

// 🔹 PRIJAVA
if (isset($_POST["login"])) {
    $u = $_POST["uporabnik"];
    $g = $_POST["geslo"];
    $r = $conn->query("SELECT * FROM uporabniki WHERE uporabnik='$u' AND geslo='$g'");
    if ($r->num_rows == 1) {
        $vr = $r->fetch_assoc();
        $_SESSION["uporabnik"] = $u;
        $_SESSION["vloga"] = $vr["vloga"];
    } else {
        $napaka = "Napačno uporabniško ime ali geslo!";
    }
}

// 🔹 DODAJ UPORABNIKA (administrator)
if (isset($_POST["dodaj_uporabnika"]) && $_SESSION["vloga"] == "admin") {
    $u = $_POST["uporabnik"];
    $g = $_POST["geslo"];
    $v = $_POST["vloga"];
    $conn->query("INSERT INTO uporabniki (uporabnik, geslo, vloga) VALUES ('$u','$g','$v')");
}

// 🔹 DODAJ OCENO (učitelj)
if (isset($_POST["dodaj_oceno"]) && $_SESSION["vloga"] == "ucitelj") {
    $uc = $_POST["ucenec"];
    $pr = $_POST["predmet"];
    $oc = $_POST["ocena"];
    $conn->query("INSERT INTO ocene (ucenec, predmet, ocena) VALUES ('$uc','$pr','$oc')");
}

// 🔹 HTML ZAČETEK
?>
<!DOCTYPE html>
<html lang="sl">
<head>
<meta charset="UTF-8">
<title>Spletna učilnica</title>
<style>
body { font-family: Segoe UI, sans-serif; background:#f5f8fb; padding:20px; color:#2c3e50; }
.container { max-width:800px; margin:auto; background:white; padding:30px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
h1 { text-align:center; color:#2980b9; }
form { margin:20px 0; }
input, select { padding:8px; margin:5px 0; width:100%; }
input[type=submit] { background:#3498db; color:white; border:none; border-radius:5px; cursor:pointer; }
input[type=submit]:hover { background:#2980b9; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { border:1px solid #ccc; padding:8px; text-align:center; }
th { background:#3498db; color:white; }
a { color:#2980b9; text-decoration:none; }
a:hover { text-decoration:underline; }
.note { color:red; text-align:center; }
</style>
</head>
<body>
<div class="container">

<?php
// 🔹 NI PRIJAVLJEN
if (!isset($_SESSION["vloga"])) {
?>
    <h1>Prijava v spletno učilnico</h1>
    <?php if (isset($napaka)) echo "<p class='note'>$napaka</p>"; ?>
    <form method="post">
        <label>Uporabniško ime:</label>
        <input type="text" name="uporabnik" required>
        <label>Geslo:</label>
        <input type="password" name="geslo" required>
        <input type="submit" name="login" value="Prijava">
    </form>

    <p style="text-align:center; color:#777;">Primeri uporabnikov:<br>
    admin / admin • učitelj / ucitelj • janez / 123</p>

<?php
// 🔹 ADMIN – lahko dodaja uporabnike
} elseif ($_SESSION["vloga"] == "admin") {
?>
    <h1>👨‍💻 Administrator</h1>
    <p>Prijavljen kot: <b><?= $_SESSION["uporabnik"] ?></b></p>
    <a href="?logout=1">Odjava</a>

    <h2>Dodaj uporabnika</h2>
    <form method="post">
        <input type="text" name="uporabnik" placeholder="Uporabniško ime" required>
        <input type="text" name="geslo" placeholder="Geslo" required>
        <select name="vloga">
            <option value="ucenec">Učenec</option>
            <option value="ucitelj">Učitelj</option>
            <option value="admin">Administrator</option>
        </select>
        <input type="submit" name="dodaj_uporabnika" value="Dodaj uporabnika">
    </form>

<?php
// 🔹 UČITELJ – dodaja ocene
} elseif ($_SESSION["vloga"] == "ucitelj") {
?>
    <h1>🧑‍🏫 Učitelj</h1>
    <p>Prijavljen kot: <b><?= $_SESSION["uporabnik"] ?></b></p>
    <a href="?logout=1">Odjava</a>

    <h2>Dodaj oceno</h2>
    <form method="post">
        <input type="text" name="ucenec" placeholder="Učenec (ime)" required>
        <input type="text" name="predmet" placeholder="Predmet" required>
        <input type="number" name="ocena" min="1" max="5" required>
        <input type="submit" name="dodaj_oceno" value="Shrani oceno">
    </form>

    <h2>Vse ocene</h2>
    <table>
        <tr><th>Učenec</th><th>Predmet</th><th>Ocena</th></tr>
        <?php
        $r = $conn->query("SELECT * FROM ocene ORDER BY ucenec");
        while ($d = $r->fetch_assoc()) {
            echo "<tr><td>{$d['ucenec']}</td><td>{$d['predmet']}</td><td>{$d['ocena']}</td></tr>";
        }
        ?>
    </table>

<?php
// 🔹 UČENEC – samo gleda ocene
} elseif ($_SESSION["vloga"] == "ucenec") {
?>
    <h1>👨‍🎓 Učenec</h1>
    <p>Prijavljen kot: <b><?= $_SESSION["uporabnik"] ?></b></p>
    <a href="?logout=1">Odjava</a>

    <h2>Moje ocene</h2>
    <table>
        <tr><th>Predmet</th><th>Ocena</th></tr>
        <?php
        $u = $_SESSION["uporabnik"];
        $r = $conn->query("SELECT predmet, ocena FROM ocene WHERE ucenec='$u'");
        if ($r->num_rows == 0) echo "<tr><td colspan='2'>Ni še ocen.</td></tr>";
        while ($d = $r->fetch_assoc()) {
            echo "<tr><td>{$d['predmet']}</td><td>{$d['ocena']}</td></tr>";
        }
        ?>
    </table>

<?php
}
?>

</div>
</body>
</html>
