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

  $formStatus = '';
  $formErrors = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kontakt_submit'])) {
    $ime     = trim($_POST['ime'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $sadrzaj = trim($_POST['sadrzaj'] ?? '');

    if ($ime === '')     $formErrors[] = 'Ime je obavezno.';
    if ($email === '')   $formErrors[] = 'Email je obavezan.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $formErrors[] = 'Email adresa nije ispravna.';
    if ($sadrzaj === '') $formErrors[] = 'Poruka ne može biti prazna.';

    if (empty($formErrors)) {
      include_once 'konekcija.php';
      $stmt = $conn->prepare(
        "INSERT INTO poruka (Ime, Email, Telefon, Sadrzaj) VALUES (?, ?, ?, ?)"
      );
      $telefonVal = $telefon !== '' ? $telefon : null;
      $stmt->bind_param('ssss', $ime, $email, $telefonVal, $sadrzaj);
      if ($stmt->execute()) {
        $formStatus = 'ok';
      } else {
        $formErrors[] = 'Greška pri čuvanju poruke. Pokušajte ponovo.';
      }
      $stmt->close();
    }
  }
?>

<div class="ct-page">
  <div class="ct-wrap">

    <header class="ct-header">
      <span class="ct-eyebrow">Pronađite nas</span>
      <h1 class="ct-title">Kontakt</h1>
      <div class="ct-orn">
        <span></span>
        <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
        <span></span>
      </div>
    </header>

    <div class="ct-info">

      <div class="ct-row">
        <div class="ct-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <div class="ct-row-body">
          <span class="ct-row-label">Radno vreme</span>
          <span class="ct-row-val">09:00 – 17:00</span>
          <span class="ct-row-sub">Ponedeljak – Subota</span>
        </div>
        <div class="ct-row-aside">
          <?php if($isOpen): ?>
          <span class="ct-badge ct-badge--open"><span class="ct-dot"></span>Otvoreno</span>
          <?php else: ?>
          <span class="ct-badge ct-badge--closed"><span class="ct-dot ct-dot--off"></span>Zatvoreno</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="ct-row">
        <div class="ct-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/>
          </svg>
        </div>
        <div class="ct-row-body">
          <span class="ct-row-label">Adresa</span>
          <span class="ct-row-val">Bulevar oslobodjenja 45</span>
          <span class="ct-row-sub">11000 Beograd, Srbija</span>
        </div>
      </div>

      <div class="ct-row">
        <div class="ct-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
          </svg>
        </div>
        <div class="ct-row-body">
          <span class="ct-row-label">Telefon</span>
          <span class="ct-row-val">065-676-5532</span>
          <span class="ct-row-sub">Pozovite nas</span>
        </div>
      </div>

    </div>

    <div class="ct-map">
      <iframe
        src="https://maps.google.com/maps?q=Bulevar+oslobodjenja+45,+Beograd,+Srbija&output=embed&z=15"
        allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>

    <section class="ct-form-section">
      <div class="ct-form-header">
        <span class="ct-eyebrow">Pošaljite nam poruku</span>
        <h2 class="ct-form-title">Kontaktirajte nas</h2>
      </div>

      <?php if ($formStatus === 'ok'): ?>
      <div class="ct-alert ct-alert--ok">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Poruka je uspešno poslata. Javićemo vam se uskoro!
      </div>
      <?php endif; ?>

      <?php if (!empty($formErrors)): ?>
      <div class="ct-alert ct-alert--err">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
        </svg>
        <ul>
          <?php foreach ($formErrors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($formStatus !== 'ok'): ?>
      <form class="ct-form" method="POST" action="kontakt.php#poruka" novalidate>
        <div class="ct-form-row2">
          <div class="ct-field">
            <label for="cf-ime">Ime i prezime <span class="ct-req">*</span></label>
            <input type="text" id="cf-ime" name="ime"
                   value="<?= htmlspecialchars($_POST['ime'] ?? '') ?>"
                   placeholder="Npr. Marija Petrović" autocomplete="name">
          </div>
          <div class="ct-field">
            <label for="cf-email">Email adresa <span class="ct-req">*</span></label>
            <input type="email" id="cf-email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="vas@email.com" autocomplete="email">
          </div>
        </div>
        <div class="ct-field">
          <label for="cf-tel">Telefon <span class="ct-opt">(opciono)</span></label>
          <input type="text" id="cf-tel" name="telefon"
                 value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>"
                 placeholder="06x-xxx-xxxx" autocomplete="tel">
        </div>
        <div class="ct-field">
          <label for="cf-msg">Poruka <span class="ct-req">*</span></label>
          <textarea id="cf-msg" name="sadrzaj" rows="4"
                    placeholder="Napišite nam…"><?= htmlspecialchars($_POST['sadrzaj'] ?? '') ?></textarea>
        </div>
        <button type="submit" name="kontakt_submit" class="ct-submit">Pošalji poruku</button>
      </form>
      <?php endif; ?>
    </section>

    <footer class="ct-footer">
      <a href="<?= isset($_SESSION['korisnik']) ? 'ternovi.php' : 'prijava.php' ?>" class="ct-btn">Zakaži termin</a>
      <div class="ct-social">
        <a href="#" class="ct-social-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="currentColor"/>
          </svg>
          Instagram
        </a>
        <a href="#" class="ct-social-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
          </svg>
          Facebook
        </a>
      </div>
    </footer>

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
