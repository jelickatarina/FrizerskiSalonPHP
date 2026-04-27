<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Kontakt – Frizerski salon</title>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="kontakt-hero">
    <div class="kontakt-hero-overlay"></div>
    <div class="kontakt-hero-content">
        <p class="kontakt-hero-label">Pronađite nas</p>
        <h1 class="kontakt-hero-title">Kontakt</h1>
    </div>
</div>

<div class="kontakt-body">

    <div class="kontakt-row">
        <div class="kontakt-item">
            <div class="kontakt-icon">&#128337;</div>
            <div class="kontakt-text">
                <span class="kontakt-label">Radno vreme</span>
                <span class="kontakt-value">Pon – Sub &nbsp;|&nbsp; 09:00 – 17:00</span>
                <span class="kontakt-note">Nedeljom ne radimo</span>
            </div>
        </div>

        <div class="kontakt-divider"></div>

        <div class="kontakt-item">
            <div class="kontakt-icon">&#128205;</div>
            <div class="kontakt-text">
                <span class="kontakt-label">Adresa</span>
                <span class="kontakt-value">Bulevar oslobodjenja 45</span>
                <span class="kontakt-note">11000 Beograd</span>
            </div>
        </div>

        <div class="kontakt-divider"></div>

        <div class="kontakt-item">
            <div class="kontakt-icon">&#128222;</div>
            <div class="kontakt-text">
                <span class="kontakt-label">Telefon</span>
                <span class="kontakt-value">065-676-5532</span>
                <span class="kontakt-note">Pozovite nas</span>
            </div>
        </div>
    </div>

    <div class="kontakt-cta">
        <?php
          if(session_status() === PHP_SESSION_NONE) session_start();
          if(isset($_SESSION['korisnik'])):
        ?>
        <a href="ternovi.php" class="btn-hero">Zakaži termin</a>
        <?php else: ?>
        <a href="registracija.php" class="btn-hero">Registruj se</a>
        <?php endif; ?>
    </div>

</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
