<?php
  if(session_status() === PHP_SESSION_NONE) session_start();
  $ulogovan = isset($_SESSION['korisnik']);
?>
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
        <?php if($ulogovan): ?>
        <div class="hero-btns">
            <a href="ternovi.php" class="btn-hero">Zakaži termin</a>
        </div>
        <?php else: ?>
        <div class="hero-btns">
            <a href="registracija.php" class="btn-hero">Registruj se</a>
            <a href="prijava.php" class="btn-hero-outline">Prijavi se</a>
        </div>
        <?php endif; ?>
    </div>
    <a href="#info" class="hero-scroll" aria-label="Skroluj dole">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </a>
</div>

<!-- Info traka -->
<div class="info-section" id="info">
    <div class="info-card">
        <div class="info-svg-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <h3>Radno vreme</h3>
        <p class="info-highlight">09:00 – 17:00</p>
        <p>Ponedeljak – Subota</p>
        <p class="info-closed">Nedeljom zatvoreno</p>
    </div>
    <div class="info-card">
        <div class="info-svg-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/>
            </svg>
        </div>
        <h3>Adresa</h3>
        <p class="info-highlight">Bulevar oslobodjenja 45</p>
        <p>11000 Beograd, Srbija</p>
        <p><a href="kontakt.php" class="info-link">Pogledaj na mapi →</a></p>
    </div>
    <div class="info-card">
        <div class="info-svg-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
            </svg>
        </div>
        <h3>Kontakt</h3>
        <p class="info-highlight">065-676-5532</p>
        <p>Pozovite nas</p>
        <p><a href="kontakt.php" class="info-link">Pošaljite poruku →</a></p>
    </div>
</div>

<!-- O nama -->
<section class="home-about">
    <div class="home-about-inner">
        <div class="home-about-text">
            <span class="ct-eyebrow">Ko smo mi</span>
            <h2 class="home-section-title">Iskustvo koje se oseti</h2>
            <div class="ct-orn" style="justify-content:flex-start; margin-bottom:1.2rem;">
                <span style="width:36px;"></span>
                <svg viewBox="0 0 10 10" style="width:7px;height:7px;color:#d4a828;opacity:.5;flex-shrink:0;"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
                <span style="width:36px;"></span>
            </div>
            <p class="home-about-desc">
                Naš frizerski salon pruža profesionalnu uslugu u opuštenoj i elegantnoj atmosferi.
                Svaki poset je poseban — od konsultacije do finalne obrade, posvećeni smo
                Vašem stilu i zadovoljstvu.
            </p>
            <a href="<?= $ulogovan ? 'ternovi.php' : 'registracija.php' ?>" class="ct-btn" style="display:inline-block; margin-top:1.8rem;">
                <?= $ulogovan ? 'Zakaži termin' : 'Registruj se besplatno' ?>
            </a>
        </div>
        <div class="home-about-img">
            <img src="salon1.jpg" alt="Frizerski salon">
        </div>
    </div>
</section>

<!-- Usluge preview -->
<section class="home-services">
    <div class="home-services-inner">
        <div class="home-services-header">
            <span class="ct-eyebrow">Šta nudimo</span>
            <h2 class="home-section-title">Naše usluge</h2>
            <div class="ct-orn">
                <span></span>
                <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
                <span></span>
            </div>
        </div>
        <div class="home-services-grid">
            <div class="home-srv-card">
                <div class="home-srv-svg">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"/>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"/>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"/>
                    </svg>
                </div>
                <h3>Šišanje</h3>
                <p>Precizni rezovi prilagođeni Vašem licu i stilu.</p>
            </div>
            <div class="home-srv-card">
                <div class="home-srv-svg">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a9 9 0 00-9 9c0 4.17 2.84 7.67 6.69 8.69L12 22l2.31-2.31C18.16 18.67 21 15.17 21 11a9 9 0 00-9-9z"/>
                        <path d="M12 8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                </div>
                <h3>Bojenje</h3>
                <p>Savremene tehnike bojenja i farbanja kose.</p>
            </div>
            <div class="home-srv-card">
                <div class="home-srv-svg">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                    </svg>
                </div>
                <h3>Tretmani</h3>
                <p>Nežna nega i tretmani za zdravu i sjajnu kosu.</p>
            </div>
            <div class="home-srv-card">
                <div class="home-srv-svg">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <h3>Svečane frizure</h3>
                <p>Posebne prilike zaslužuju poseban izgled.</p>
            </div>
        </div>
        <?php if($ulogovan): ?>
        <div style="text-align:center; margin-top:2.8rem;">
            <a href="ternovi.php" class="ct-btn">Zakaži termin</a>
        </div>
        <?php else: ?>
        <div style="text-align:center; margin-top:2.8rem;">
            <a href="registracija.php" class="ct-btn">Registruj se i zakaži</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  });
</script>
</body>
</html>
