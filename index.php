<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Frizerski salon</title>
</head>
<body class="index-page">

<?php include 'menu.php'; ?>

<!-- Hero -->
<div class="hero-section">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <p class="hero-subtitle">Dobrodošli u</p>
        <h1 class="hero-title">Frizerski salon</h1>
        <p class="hero-tagline">Vaš stil, naša strast</p>
        <?php
          if(session_status() === PHP_SESSION_NONE) session_start();
          if(isset($_SESSION['korisnik'])):
        ?>
        <a href="ternovi.php" class="btn btn-hero">Zakaži termin</a>
        <?php else: ?>
        <a href="registracija.php" class="btn btn-hero">Registruj se</a>
        <?php endif; ?>
    </div>
</div>

<!-- Info kartice -->
<div class="info-section">
    <div class="info-card">
        <div class="info-icon">&#128337;</div>
        <h3>Radno vreme</h3>
        <p>Ponedeljak – Subota</p>
        <p class="info-highlight">09:00 – 17:00</p>
        <p class="info-closed">Nedeljom ne radimo</p>
    </div>
    <div class="info-card">
        <div class="info-icon">&#128205;</div>
        <h3>Lokacija</h3>
        <p>Bulevar oslobodjenja 45</p>
        <p class="info-highlight">11000 Beograd</p>
    </div>
    <div class="info-card">
        <div class="info-icon">&#128222;</div>
        <h3>Kontakt</h3>
        <p>Pozovite nas</p>
        <p class="info-highlight">065-676-5532</p>
    </div>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
