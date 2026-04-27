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
<body class="kontakt-page">

<?php include 'menu.php'; ?>

<div class="k-wrap">

    <!-- Leva strana -->
    <div class="k-left">
        <div class="k-left-inner">
            <span class="k-eyebrow">Frizerski salon</span>
            <h1 class="k-title">Posetite<br>nas</h1>
            <div class="k-line"></div>
            <p class="k-tagline">Uvek smo tu za vas —<br>zakažite ili nas nazovite.</p>
            <?php
              if(session_status() === PHP_SESSION_NONE) session_start();
              if(isset($_SESSION['korisnik'])):
            ?>
            <a href="ternovi.php" class="k-btn">Zakaži termin</a>
            <?php else: ?>
            <a href="prijava.php" class="k-btn">Prijavi se</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Desna strana -->
    <div class="k-right">

        <p class="k-right-eyebrow">Informacije</p>

        <div class="k-info-item">
            <div class="k-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="k-info-body">
                <span class="k-info-label">Radno vreme</span>
                <span class="k-info-value">Pon – Sub</span>
                <span class="k-info-time">09:00 – 17:00</span>
                <span class="k-info-sub">Nedeljom zatvoreno</span>
            </div>
        </div>

        <div class="k-info-item">
            <div class="k-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                    <circle cx="12" cy="9" r="2.5"/>
                </svg>
            </div>
            <div class="k-info-body">
                <span class="k-info-label">Adresa</span>
                <span class="k-info-value">Bulevar oslobodjenja 45</span>
                <span class="k-info-time">11000 Beograd</span>
                <span class="k-info-sub">Srbija</span>
            </div>
        </div>

        <div class="k-info-item">
            <div class="k-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
            </div>
            <div class="k-info-body">
                <span class="k-info-label">Telefon</span>
                <span class="k-info-value">065-676-5532</span>
                <span class="k-info-sub">Pozovite nas</span>
            </div>
        </div>

        <div class="k-right-deco">Frizerski<br>salon</div>

    </div>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
