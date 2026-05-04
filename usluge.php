<?php
require_once 'sesija.php';
if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'1'))
  header('Location: nemaovlascenje.html');
include 'konekcija.php';

$q       = trim($_GET['q'] ?? '');
$aktivan = $_GET['aktivan'] ?? '';
$wh = "where 1=1";
if($aktivan === '1') $wh .= " and Aktivna=1";
if($aktivan === '0') $wh .= " and Aktivna=0";
if($q !== '') {
  $esc = $conn->real_escape_string($q);
  $wh .= " and (UslugaId like '%$esc%' or Opis like '%$esc%')";
}
$result = $conn->query("select * from usluga $wh order by UslugaId");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mojstil.css">
    <title>Usluge</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="kp-page">
    <header class="kp-header">
        <p class="ct-eyebrow">Frizerski salon</p>
        <h1 class="kp-title">Usluge</h1>
        <div class="ct-orn">
            <span></span>
            <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="2"/></svg>
            <span></span>
        </div>
    </header>
    <div class="pg-wrap">
        <div class="pg-toolbar">
            <form method="get" class="search-form">
                <input class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Pretraži usluge...">
                <select class="filter-select" name="aktivan">
                    <option value="" <?= $aktivan===''?'selected':'' ?>>Sve usluge</option>
                    <option value="1" <?= $aktivan==='1'?'selected':'' ?>>Aktivne</option>
                    <option value="0" <?= $aktivan==='0'?'selected':'' ?>>Neaktivne</option>
                </select>
                <button class="search-btn" type="submit">Traži</button>
            </form>
            <?php if($_SESSION['nivo']>='2') { ?>
            <a class="ct-btn" href="uslnova.php">+ Nova usluga</a>
            <?php } ?>
        </div>
        <?php if($q !== '') { ?>
        <p class="search-info">Rezultati za: <strong><?= htmlspecialchars($q) ?></strong>
            — <a href="usluge.php">Poništi pretragu</a></p>
        <?php } ?>
        <div class="cards-grid">
<?php
$rows = 0;
while($data=$result->fetch_assoc()) {
  $rows++;
  $aktivna = $data['Aktivna'] == 1;
?>
            <div class="srv-card <?= $aktivna ? '' : 'srv-card--inactive' ?>">
                <div class="srv-card-top">
                    <h3 class="srv-card-name"><?= htmlspecialchars($data['UslugaId']) ?></h3>
                    <span class="srv-badge <?= $aktivna ? 'srv-badge--on' : 'srv-badge--off' ?>">
                        <?= $aktivna ? 'Aktivna' : 'Neaktivna' ?>
                    </span>
                </div>
                <p class="srv-card-opis"><?= htmlspecialchars($data['Opis']) ?></p>
                <div class="srv-card-meta">
                    <div class="srv-meta-item">
                        <span class="srv-meta-label">Cena</span>
                        <span class="srv-meta-val"><?= $data['Cena'] ?> RSD</span>
                    </div>
                    <div class="srv-meta-divider"></div>
                    <div class="srv-meta-item">
                        <span class="srv-meta-label">Trajanje</span>
                        <span class="srv-meta-val"><?= $data['Trajanje'] ?> min</span>
                    </div>
                </div>
                <?php if($_SESSION['nivo']>='2') { ?>
                <a class="srv-card-edit" href="uslizmeni.php?p=<?= urlencode($data['UslugaId']) ?>">Izmeni</a>
                <?php } ?>
            </div>
<?php } ?>
<?php if($rows===0) { ?>
        <p class="search-info">Nema usluga<?= $q!==''?' za ovu pretragu':'' ?>.</p>
<?php } ?>
        </div>
    </div>
</div>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
