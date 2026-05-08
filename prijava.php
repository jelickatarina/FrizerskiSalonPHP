<?php
require_once 'sesija.php';

  $poruka     = "";
  $korisnik   = "";
  $deaktiviran = false;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnik = trim($_POST['korisnik'] ?? '');
    $lozinka  = trim($_POST['lozinka']  ?? '');

    if ($korisnik === '') $poruka = "Unesite korisničko ime.";
    elseif ($lozinka === '') $poruka = "Unesite lozinku.";
    else {
      include 'konekcija.php';
      $stmt = $conn->prepare("SELECT Nivo, Ime, Prezime FROM korisnik WHERE KorisnikId=? AND Lozinka=?");
      $stmt->bind_param('ss', $korisnik, $lozinka);
      $stmt->execute();
      $result = $stmt->get_result();
      if (!($data = $result->fetch_assoc())) {
        $poruka = "Neispravno korisničko ime ili lozinka.";
      } elseif ((int)$data['Nivo'] === 0) {
        $deaktiviran = true;
      } else {
        $_SESSION['korisnik'] = $korisnik;
        $_SESSION['nivo']     = $data['Nivo'];
        $_SESSION['ime']      = $data['Ime'];
        $_SESSION['prezime']  = $data['Prezime'];
        header('Location: termini.php');
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
    <?php if (!empty($_GET['deaktiviran']) || $deaktiviran): ?>
    <div class="ct-alert ct-alert--warn" style="margin-bottom:.8rem;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
      Vaš nalog je deaktiviran.
    </div>
    <?php endif; ?>

    <form action="prijava.php" method="post" class="auth-form">
      <div class="ct-field">
        <label for="korisnik">Korisničko ime</label>
        <input type="text" id="korisnik" name="korisnik"
               value="<?= htmlspecialchars($korisnik) ?>" autocomplete="username">
      </div>
      <div class="ct-field">
        <label for="lozinka">Lozinka</label>
        <input type="password" id="lozinka" name="lozinka" autocomplete="current-password">
        <a href="zaboravljena_lozinka.php" class="ct-forgot">Zaboravili ste lozinku?</a>
      </div>
      <button type="submit" class="auth-btn">Prijavi se</button>
    </form>

    <p class="auth-switch">
      Nemate nalog? <a href="registracija.php">Registrujte se</a>
    </p>

  </div>
</div>

<?php include 'footer.php'; ?>

<?php if (!empty($_GET['deaktiviran']) || $deaktiviran): ?>
<div class="modal fade" id="deaktiviranModal" tabindex="-1" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1a1510;border:1px solid rgba(196,167,108,.25);border-radius:10px;color:rgba(255,255,255,.85);">
      <div class="modal-body" style="padding:2rem 1.8rem 1.4rem;text-align:center;">
        <svg viewBox="0 0 24 24" fill="none" stroke="#c4a76c" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="44" height="44" style="margin-bottom:1rem;opacity:.9;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="#c4a76c"/></svg>
        <h5 style="font-family:'Cormorant Garamond',serif;font-size:1.35rem;font-weight:600;margin-bottom:.7rem;color:#fff;">Nalog je deaktiviran</h5>
        <p style="font-family:'Jost',sans-serif;font-size:.88rem;color:rgba(255,255,255,.6);margin-bottom:1.4rem;line-height:1.6;">
          Vaš nalog je trenutno deaktiviran.<br>
          Pozovite nas kako bismo aktivirali Vaš nalog i ponovo Vam omogućili pristup.
        </p>
        <button type="button" class="auth-btn" style="width:100%;" data-bs-dismiss="modal">U redu</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var m = new bootstrap.Modal(document.getElementById('deaktiviranModal'));
    m.show();
  });
</script>
<?php endif; ?>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  if (nav) window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));
</script>
</body>
</html>
