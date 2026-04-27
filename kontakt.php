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

<div class="kp-bg">
    <div class="kp-overlay"></div>

    <div class="kp-content">

        <div class="kp-header">
            <div class="kp-header-line"></div>
            <h1 class="kp-title">Kontakt</h1>
            <div class="kp-header-line"></div>
        </div>

        <div class="kp-grid">

            <div class="kp-card">
                <svg class="kp-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <div class="kp-card-body">
                    <span class="kp-card-label">Radno vreme</span>
                    <span class="kp-card-big">09:00 – 17:00</span>
                    <span class="kp-card-note">Pon – Sub &nbsp;·&nbsp; Ned zatvoreno</span>
                </div>
            </div>

            <div class="kp-card kp-card--mid">
                <svg class="kp-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                    <circle cx="12" cy="9" r="2.5"/>
                </svg>
                <div class="kp-card-body">
                    <span class="kp-card-label">Adresa</span>
                    <span class="kp-card-big">Bulevar oslobodjenja 45</span>
                    <span class="kp-card-note">11000 Beograd, Srbija</span>
                </div>
            </div>

            <div class="kp-card">
                <svg class="kp-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
                <div class="kp-card-body">
                    <span class="kp-card-label">Telefon</span>
                    <span class="kp-card-big">065-676-5532</span>
                    <span class="kp-card-note">Pozovite nas</span>
                </div>
            </div>

        </div>

        <?php
          if(session_status() === PHP_SESSION_NONE) session_start();
          if(isset($_SESSION['korisnik'])): ?>
        <div class="kp-footer">
            <a href="ternovi.php" class="kp-btn">Zakaži termin</a>
        </div>
        <?php endif; ?>

        <div class="kp-map">
            <iframe
                src="https://maps.google.com/maps?q=Bulevar+oslobodjenja+45,+Beograd,+Srbija&output=embed&z=15"
                allowfullscreen
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>

    </div>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  });
  if (window.scrollY > 10) nav.classList.add('scrolled');
</script>
</body>
</html>
