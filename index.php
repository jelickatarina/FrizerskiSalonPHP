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
        <div class="hero-btns">
            <a href="registracija.php" class="btn btn-hero">Registruj se</a>
            <a href="prijava.php" class="btn btn-hero-outline">Prijavi se</a>
        </div>
        <?php endif; ?>
    </div>
</div>


<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  });
</script>
</body>
</html>
