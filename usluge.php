<?php
  session_start();
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'1'))
    header('Location: nemaovlascenje.html');
  include 'konekcija.php';
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
        <?php if($_SESSION['nivo']>='2') { ?>
        <div class="pg-action" style="margin-bottom:2.5rem;margin-top:0;">
            <a class="ct-btn" href="uslnova.php">+ Nova usluga</a>
        </div>
        <?php } ?>
        <div class="cards-grid">
<?php
  $sql = "select * from usluga order by UslugaId";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc()) {
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
        </div>
    </div>
</div>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
