<?php
if (!isset($_REQUEST['p'])) { echo "Neispravan poziv strane"; exit; }

require_once 'sesija.php';
requireNivo('9');

$korisnik = $_REQUEST['p'];
include 'konekcija.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka  = "";
    $korisnik = trim($_POST['korisnik']  ?? '');
    $lozinka  = trim($_POST['lozinka']   ?? '');
    $ime      = trim($_POST['ime']       ?? '');
    $prezime  = trim($_POST['prezime']   ?? '');
    $datumr   = trim($_POST['datumr']    ?? '');
    $email    = trim($_POST['email']     ?? '');
    $telefon  = trim($_POST['telefon']   ?? '');
    $nivop    = trim($_POST['nivo']      ?? '');
    $doznivo  = ['0', '1', '2', '9'];

    if (strlen($korisnik) === 0)         $poruka = "Korisničko ime je obavezno.";
    elseif (strlen($lozinka) === 0)      $poruka = "Lozinka je obavezna.";
    elseif (strlen($ime) === 0)          $poruka = "Unesite ime.";
    elseif (strlen($prezime) === 0)      $poruka = "Unesite prezime.";
    elseif (strlen($datumr) === 0)       $poruka = "Unesite datum rođenja.";
    elseif (strlen($email) === 0)        $poruka = "Unesite email.";
    elseif (strlen($telefon) === 0)      $poruka = "Unesite telefon.";
    elseif (strlen($nivop) === 0)        $poruka = "Izaberite nivo pristupa.";
    elseif (!in_array($nivop, $doznivo)) $poruka = "Nivo može biti 0, 1, 2 ili 9.";
    else {
        $nivoInt = (int)$nivop;
        $upd = $conn->prepare(
            "UPDATE korisnik SET Lozinka=?, Ime=?, Prezime=?, DatumRodjenja=?, Email=?, Telefon=?, Nivo=?
             WHERE KorisnikId=?"
        );
        $upd->bind_param('ssssssis', $lozinka, $ime, $prezime, $datumr, $email, $telefon, $nivoInt, $korisnik);
        $upd->execute();
        $upd->close();
        header('Location: korisnici.php');
        exit;
    }
} else {
    $poruka = "";
    $sel = $conn->prepare("SELECT * FROM korisnik WHERE KorisnikId=?");
    $sel->bind_param('s', $korisnik);
    $sel->execute();
    $data = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$data) { echo "Korisnik nije pronađen."; exit; }
    $lozinka = $data['Lozinka'];
    $ime     = $data['Ime'];
    $prezime = $data['Prezime'];
    $datumr  = $data['DatumRodjenja'];
    $email   = $data['Email'];
    $telefon = $data['Telefon'];
    $nivop   = (string)$data['Nivo'];
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Izmeni korisnika – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card auth-card--wide">

    <div class="auth-header">
      <span class="ct-eyebrow">Upravljanje korisnicima</span>
      <h1 class="auth-title">Izmeni korisnika</h1>
      <div class="ct-orn">
        <span></span>
        <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
        <span></span>
      </div>
    </div>

    <?php if (!empty($poruka)): ?>
    <div class="ct-alert ct-alert--err">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
      </svg>
      <?= htmlspecialchars($poruka) ?>
    </div>
    <?php endif; ?>

    <form method="post" class="auth-form" autocomplete="off">

      <div class="ct-field">
        <label>Korisničko ime</label>
        <input type="text" name="korisnik" value="<?= htmlspecialchars($korisnik) ?>" readonly>
      </div>

      <div class="ct-field">
        <label for="lozinka">Lozinka <span class="ct-req">*</span></label>
        <input type="text" id="lozinka" name="lozinka"
               value="<?= htmlspecialchars($lozinka) ?>" autocomplete="new-password">
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

      <div class="ct-field">
        <label for="nivo">Nivo pristupa <span class="ct-req">*</span></label>
        <select id="nivo" name="nivo">
          <option value="0" <?= $nivop === '0' ? 'selected' : '' ?>>0 – Neaktivan</option>
          <option value="1" <?= $nivop === '1' ? 'selected' : '' ?>>1 – Klijent</option>
          <option value="2" <?= $nivop === '2' ? 'selected' : '' ?>>2 – Frizer</option>
          <option value="9" <?= $nivop === '9' ? 'selected' : '' ?>>9 – Administrator</option>
        </select>
      </div>

      <button type="submit" class="auth-btn">Sačuvaj izmene</button>

    </form>

    <p class="auth-switch"><a href="korisnici.php">← Nazad na korisnike</a></p>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
