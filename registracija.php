<?php
  session_start();

  $poruka  = "";
  $korisnik = $lozinka = $ime = $prezime = $datumr = $email = $telefon = "";

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnik = trim($_POST['korisnik'] ?? '');
    $lozinka  = trim($_POST['lozinka']  ?? '');
    $ime      = trim($_POST['ime']      ?? '');
    $prezime  = trim($_POST['prezime']  ?? '');
    $datumr   = trim($_POST['datumr']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefon  = trim($_POST['telefon']  ?? '');

    if ($korisnik === '')     $poruka = "Unesite korisničko ime.";
    elseif ($lozinka === '')  $poruka = "Unesite lozinku.";
    elseif ($ime === '')      $poruka = "Unesite ime.";
    elseif ($prezime === '')  $poruka = "Unesite prezime.";
    elseif ($datumr === '')   $poruka = "Unesite datum rođenja.";
    elseif ($email === '')    $poruka = "Unesite email.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $poruka = "Email adresa nije ispravna.";
    elseif ($telefon === '')  $poruka = "Unesite telefon.";
    else {
      include 'konekcija.php';
      $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM korisnik WHERE KorisnikId=?");
      $stmt->bind_param('s', $korisnik);
      $stmt->execute();
      $data = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if ($data['c'] > 0) {
        $poruka = "Korisnik „{$korisnik}" je već registrovan.";
      } else {
        $stmt = $conn->prepare(
          "INSERT INTO korisnik (KorisnikId, Lozinka, Ime, Prezime, DatumRodjenja, Telefon, Email, Nivo)
           VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('sssssss', $korisnik, $lozinka, $ime, $prezime, $datumr, $telefon, $email);
        $stmt->execute();
        $stmt->close();
        $_SESSION['korisnik'] = $korisnik;
        $_SESSION['nivo']     = '1';
        header('Location: index.php');
        exit;
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mojstil.css">
    <title>Registracija – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card auth-card--wide">

    <div class="auth-header">
      <span class="ct-eyebrow">Novi nalog</span>
      <h1 class="auth-title">Registracija</h1>
      <div class="ct-orn">
        <span></span>
        <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
        <span></span>
      </div>
    </div>

    <?php if ($poruka !== ''): ?>
    <div class="ct-alert ct-alert--err">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
      </svg>
      <?= htmlspecialchars($poruka) ?>
    </div>
    <?php endif; ?>

    <form method="post" class="auth-form">
      <div class="auth-grid">
        <div class="ct-field">
          <label for="korisnik">Korisničko ime <span class="ct-req">*</span></label>
          <input type="text" id="korisnik" name="korisnik"
                 value="<?= htmlspecialchars($korisnik) ?>"
                 placeholder="Izaberite korisničko ime" autocomplete="username">
        </div>
        <div class="ct-field">
          <label for="lozinka">Lozinka <span class="ct-req">*</span></label>
          <input type="password" id="lozinka" name="lozinka"
                 placeholder="••••••••" autocomplete="new-password">
        </div>
        <div class="ct-field">
          <label for="ime">Ime <span class="ct-req">*</span></label>
          <input type="text" id="ime" name="ime"
                 value="<?= htmlspecialchars($ime) ?>"
                 placeholder="Vaše ime" autocomplete="given-name">
        </div>
        <div class="ct-field">
          <label for="prezime">Prezime <span class="ct-req">*</span></label>
          <input type="text" id="prezime" name="prezime"
                 value="<?= htmlspecialchars($prezime) ?>"
                 placeholder="Vaše prezime" autocomplete="family-name">
        </div>
        <div class="ct-field">
          <label for="email">Email <span class="ct-req">*</span></label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($email) ?>"
                 placeholder="vas@email.com" autocomplete="email">
        </div>
        <div class="ct-field">
          <label for="telefon">Telefon <span class="ct-req">*</span></label>
          <input type="text" id="telefon" name="telefon"
                 value="<?= htmlspecialchars($telefon) ?>"
                 placeholder="06x-xxx-xxxx" autocomplete="tel">
        </div>
        <div class="ct-field auth-grid--full">
          <label for="datumr">Datum rođenja <span class="ct-req">*</span></label>
          <input type="date" id="datumr" name="datumr"
                 value="<?= htmlspecialchars($datumr) ?>" autocomplete="bday">
        </div>
      </div>
      <button type="submit" class="auth-btn">Registruj se</button>
    </form>

    <p class="auth-switch">
      Već imate nalog? <a href="prijava.php">Prijavite se</a>
    </p>

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
