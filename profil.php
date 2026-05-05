<?php
require_once 'sesija.php';
requireNivo('1');

$korisnik = $_SESSION['korisnik'];
include 'konekcija.php';

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka   = "";
    $ime      = trim($_POST['ime']      ?? '');
    $prezime  = trim($_POST['prezime']  ?? '');
    $datumr   = trim($_POST['datumr']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefon  = trim($_POST['telefon']  ?? '');
    $lozinka  = trim($_POST['lozinka']  ?? '');
    $lozinka2 = trim($_POST['lozinka2'] ?? '');

    if (strlen($ime) === 0)     $poruka = "Unesite ime.";
    elseif (strlen($prezime) === 0)  $poruka = "Unesite prezime.";
    elseif (strlen($datumr) === 0)   $poruka = "Unesite datum rođenja.";
    elseif (strlen($email) === 0)    $poruka = "Unesite email.";
    elseif (strlen($telefon) === 0)  $poruka = "Unesite telefon.";
    elseif ($lozinka !== '' && $lozinka !== $lozinka2) $poruka = "Lozinke se ne podudaraju.";
    else {
        if ($lozinka !== '') {
            $upd = $conn->prepare(
                "UPDATE korisnik SET Ime=?, Prezime=?, DatumRodjenja=?, Email=?, Telefon=?, Lozinka=?
                 WHERE KorisnikId=?"
            );
            $upd->bind_param('sssssss', $ime, $prezime, $datumr, $email, $telefon, $lozinka, $korisnik);
        } else {
            $upd = $conn->prepare(
                "UPDATE korisnik SET Ime=?, Prezime=?, DatumRodjenja=?, Email=?, Telefon=?
                 WHERE KorisnikId=?"
            );
            $upd->bind_param('ssssss', $ime, $prezime, $datumr, $email, $telefon, $korisnik);
        }
        $upd->execute();
        $upd->close();
        $_SESSION['ime']     = $ime;
        $_SESSION['prezime'] = $prezime;
        $success = true;
    }
} else {
    $poruka = "";
}

// Always reload fresh data from DB
$sel = $conn->prepare("SELECT * FROM korisnik WHERE KorisnikId=?");
$sel->bind_param('s', $korisnik);
$sel->execute();
$data = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$success || !isset($ime)) {
    $ime     = $data['Ime'];
    $prezime = $data['Prezime'];
    $datumr  = $data['DatumRodjenja'];
    $email   = $data['Email'];
    $telefon = $data['Telefon'];
}
$nivo = (int)$data['Nivo'];
$nivoLabel = ['0' => 'Neaktivan', '1' => 'Klijent', '2' => 'Frizer', '9' => 'Administrator'];
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Moj profil – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card auth-card--wide">

    <div class="auth-header">
      <span class="ct-eyebrow"><?= htmlspecialchars($korisnik) ?></span>
      <h1 class="auth-title">Moj profil</h1>
      <div class="ct-orn">
        <span></span>
        <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
        <span></span>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="ct-alert ct-alert--ok">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      Podaci su uspešno sačuvani.
    </div>
    <?php elseif (!empty($poruka)): ?>
    <div class="ct-alert ct-alert--err">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
      </svg>
      <?= htmlspecialchars($poruka) ?>
    </div>
    <?php endif; ?>

    <form method="post" class="auth-form" autocomplete="off">

      <div class="auth-grid">
        <div class="ct-field">
          <label>Korisničko ime</label>
          <input type="text" value="<?= htmlspecialchars($korisnik) ?>" readonly>
        </div>
        <div class="ct-field">
          <label>Nivo pristupa</label>
          <input type="text" value="<?= htmlspecialchars($nivoLabel[(string)$nivo] ?? (string)$nivo) ?>" readonly>
        </div>
      </div>

      <div class="auth-grid">
        <div class="ct-field">
          <label for="ime">Ime <span class="ct-req">*</span></label>
          <input type="text" id="ime" name="ime"
                 value="<?= htmlspecialchars($ime) ?>" autocomplete="off">
        </div>
        <div class="ct-field">
          <label for="prezime">Prezime <span class="ct-req">*</span></label>
          <input type="text" id="prezime" name="prezime"
                 value="<?= htmlspecialchars($prezime) ?>" autocomplete="off">
        </div>
      </div>

      <div class="ct-field">
        <label for="datumr">Datum rođenja <span class="ct-req">*</span></label>
        <input type="date" id="datumr" name="datumr"
               value="<?= htmlspecialchars($datumr) ?>" autocomplete="off">
      </div>

      <div class="ct-field">
        <label for="email">Email <span class="ct-req">*</span></label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($email) ?>" autocomplete="off">
      </div>

      <div class="ct-field">
        <label for="telefon">Telefon <span class="ct-req">*</span></label>
        <input type="tel" id="telefon" name="telefon"
               value="<?= htmlspecialchars($telefon) ?>" autocomplete="off">
      </div>

      <div class="prof-divider">
        <span>Promena lozinke <span class="ct-opt">(opciono)</span></span>
      </div>

      <div class="auth-grid">
        <div class="ct-field">
          <label for="lozinka">Nova lozinka</label>
          <input type="password" id="lozinka" name="lozinka"
                 value="" autocomplete="new-password" placeholder="Ostavite prazno da ne menjate">
        </div>
        <div class="ct-field">
          <label for="lozinka2">Ponovite lozinku</label>
          <input type="password" id="lozinka2" name="lozinka2"
                 value="" autocomplete="new-password" placeholder="Ponovite novu lozinku">
        </div>
      </div>

      <button type="submit" class="auth-btn">Sačuvaj izmene</button>

    </form>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
