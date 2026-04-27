<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mojstil.css">
    <title>Kontakt – Frizerski salon</title>
</head>
<body class="kontakt-page">

<?php include 'menu.php'; ?>

<?php
  if(session_status() === PHP_SESSION_NONE) session_start();
  $hour   = (int)date('H');
  $day    = (int)date('w');
  $isOpen = ($day >= 1 && $day <= 6 && $hour >= 9 && $hour < 17);
?>

<div class="kp-wrap">

    <!-- Leva kolona -->
    <div class="kp-left">

        <div class="kp-heading">
            <span class="kp-eyebrow">Pronađite nas</span>
            <h1 class="kp-title">Kontakt</h1>
            <div class="kp-title-orn">
                <span class="kp-orn-line"></span>
                <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
                <span class="kp-orn-line"></span>
            </div>
        </div>

        <div class="kp-cards">

            <div class="kp-card">
                <div class="kp-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="kp-card-body">
                    <div class="kp-card-top">
                        <span class="kp-card-label">Radno vreme</span>
                        <?php if($isOpen): ?>
                        <span class="kp-status kp-status--open"><span class="kp-dot"></span>Otvoreno</span>
                        <?php else: ?>
                        <span class="kp-status kp-status--closed"><span class="kp-dot kp-dot--red"></span>Zatvoreno</span>
                        <?php endif; ?>
                    </div>
                    <span class="kp-card-val">09:00 – 17:00</span>
                    <span class="kp-card-sub">Ponedeljak – Subota</span>
                </div>
            </div>

            <div class="kp-card">
                <div class="kp-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/>
                    </svg>
                </div>
                <div class="kp-card-body">
                    <span class="kp-card-label">Adresa</span>
                    <span class="kp-card-val">Bulevar oslobodjenja 45</span>
                    <span class="kp-card-sub">11000 Beograd, Srbija</span>
                </div>
            </div>

            <div class="kp-card">
                <div class="kp-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                </div>
                <div class="kp-card-body">
                    <span class="kp-card-label">Telefon</span>
                    <span class="kp-card-val">065-676-5532</span>
                    <span class="kp-card-sub">Pozovite nas</span>
                </div>
            </div>

        </div>

        <div class="kp-actions">
            <?php if(isset($_SESSION['korisnik'])): ?>
            <a href="ternovi.php" class="kp-btn-gold">Zakaži termin</a>
            <?php else: ?>
            <a href="prijava.php" class="kp-btn-gold">Zakaži termin</a>
            <?php endif; ?>
        </div>

        <div class="kp-social">
            <a href="#" class="kp-social-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="currentColor"/>
                </svg>
                Instagram
            </a>
            <a href="#" class="kp-social-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                </svg>
                Facebook
            </a>
        </div>

    </div>

    <!-- Desna kolona — mapa -->
    <div class="kp-right">
        <iframe
            src="https://maps.google.com/maps?q=Bulevar+oslobodjenja+45,+Beograd,+Srbija&output=embed&z=15"
            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>

</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));
  nav.classList.add('scrolled');
</script>
</body>
</html>
