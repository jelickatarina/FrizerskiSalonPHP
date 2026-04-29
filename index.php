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
        <div class="info-icon">🕐</div>
        <h3>Radno vreme</h3>
        <p class="info-highlight">09:00 – 17:00</p>
        <p>Ponedeljak – Subota</p>
        <p class="info-closed">Nedeljom zatvoreno</p>
    </div>
    <div class="info-card">
        <div class="info-icon">📍</div>
        <h3>Adresa</h3>
        <p class="info-highlight">Bulevar oslobodjenja 45</p>
        <p>11000 Beograd, Srbija</p>
        <p><a href="kontakt.php" class="info-link">Pogledaj na mapi</a></p>
    </div>
    <div class="info-card">
        <div class="info-icon">📞</div>
        <h3>Kontakt</h3>
        <p class="info-highlight">065-676-5532</p>
        <p>Pozovite nas</p>
        <p><a href="kontakt.php" class="info-link">Pošaljite poruku</a></p>
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
                <div class="home-srv-icon">✂</div>
                <h3>Šišanje</h3>
                <p>Precizni rezovi prilagođeni Vašem licu i stilu.</p>
            </div>
            <div class="home-srv-card">
                <div class="home-srv-icon">🎨</div>
                <h3>Bojenje</h3>
                <p>Savremene tehnike bojenja i farbanja kose.</p>
            </div>
            <div class="home-srv-card">
                <div class="home-srv-icon">💆</div>
                <h3>Tretmani</h3>
                <p>Nežna nega i tretmani za zdravu i sjajnu kosu.</p>
            </div>
            <div class="home-srv-card">
                <div class="home-srv-icon">💍</div>
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
