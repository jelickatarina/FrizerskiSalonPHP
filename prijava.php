<?php
  session_start();

  $poruka = "";
  $korisnik = "";

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnik = trim($_POST['korisnik'] ?? '');
    $lozinka  = trim($_POST['lozinka']  ?? '');

    if ($korisnik === '') $poruka = "Unesite korisničko ime.";
    elseif ($lozinka === '') $poruka = "Unesite lozinku.";
    else {
      include 'konekcija.php';
      $stmt = $conn->prepare("SELECT Nivo FROM korisnik WHERE KorisnikId=? AND Lozinka=? AND Nivo>0");
      $stmt->bind_param('ss', $korisnik, $lozinka);
      $stmt->execute();
      $result = $stmt->get_result();
      if (!($data = $result->fetch_assoc())) {
        $poruka = "Neispravno korisničko ime ili lozinka.";
      } else {
        $_SESSION['korisnik'] = $korisnik;
        $_SESSION['nivo']     = $data['Nivo'];
        header('Location: index.php');
        exit;
      }
      $stmt->close();
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
    <title>Prijava – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <span class="ct-eyebrow">Dobrodošli nazad</span>
      <h1 class="auth-title">Prijava</h1>
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

    <form action="prijava.php" method="post" class="auth-form">
      <div class="ct-field">
        <label for="korisnik">Korisničko ime</label>
        <input type="text" id="korisnik" name="korisnik"
               value="<?= htmlspecialchars($korisnik) ?>"
               placeholder="Vaše korisničko ime" autocomplete="username">
      </div>
      <div class="ct-field">
        <label for="lozinka">Lozinka</label>
        <input type="password" id="lozinka" name="lozinka"
               placeholder="••••••••" autocomplete="current-password">
      </div>
      <button type="submit" class="auth-btn">Prijavi se</button>
    </form>

    <p class="auth-switch">
      Nemate nalog? <a href="registracija.php">Registrujte se</a>
    </p>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));
  nav.classList.add('scrolled');
</script>
</body>
</html>
