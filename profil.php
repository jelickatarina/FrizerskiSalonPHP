<?php
require_once 'sesija.php';
requireNivo('1');

$korisnik  = $_SESSION['korisnik'];
include 'konekcija.php';

$activeTab    = 'podaci';
$success_info = false;
$success_loz  = false;
$poruka_info  = "";
$poruka_loz   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tip = $_POST['tip'] ?? '';

    if ($tip === 'info') {
        $activeTab = 'podaci';
        $ime     = trim($_POST['ime']     ?? '');
        $prezime = trim($_POST['prezime'] ?? '');
        $datumr  = trim($_POST['datumr']  ?? '');
        $email   = trim($_POST['email']   ?? '');
        $telefon = trim($_POST['telefon'] ?? '');

        $dateObj = $datumr ? DateTime::createFromFormat('Y-m-d', $datumr) : false;

        if (strlen($ime) < 2)
            $poruka_info = "Ime mora imati najmanje 2 karaktera.";
        elseif (!preg_match('/^[\p{L}\s\-]+$/u', $ime))
            $poruka_info = "Ime sadrži nedozvoljene znakove.";
        elseif (strlen($prezime) < 2)
            $poruka_info = "Prezime mora imati najmanje 2 karaktera.";
        elseif (!preg_match('/^[\p{L}\s\-]+$/u', $prezime))
            $poruka_info = "Prezime sadrži nedozvoljene znakove.";
        elseif (!$dateObj || $dateObj > new DateTime())
            $poruka_info = "Unesite ispravan datum rođenja.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $poruka_info = "Unesite ispravnu email adresu.";
        elseif (!preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $telefon))
            $poruka_info = "Unesite ispravan broj telefona (cifre, +, -, razmaci).";
        else {
            $upd = $conn->prepare(
                "UPDATE korisnik SET Ime=?, Prezime=?, DatumRodjenja=?, Email=?, Telefon=? WHERE KorisnikId=?"
            );
            $upd->bind_param('ssssss', $ime, $prezime, $datumr, $email, $telefon, $korisnik);
            $upd->execute();
            $upd->close();
            $_SESSION['ime']     = $ime;
            $_SESSION['prezime'] = $prezime;
            $success_info = true;
        }

    } elseif ($tip === 'lozinka') {
        $activeTab = 'lozinka';
        $lozinka  = $_POST['lozinka']  ?? '';
        $lozinka2 = $_POST['lozinka2'] ?? '';

        if (strlen($lozinka) < 6)
            $poruka_loz = "Lozinka mora imati najmanje 6 karaktera.";
        elseif ($lozinka !== $lozinka2)
            $poruka_loz = "Lozinke se ne podudaraju.";
        else {
            $upd = $conn->prepare("UPDATE korisnik SET Lozinka=? WHERE KorisnikId=?");
            $upd->bind_param('ss', $lozinka, $korisnik);
            $upd->execute();
            $upd->close();
            $success_loz = true;
        }
    }
}

$sel = $conn->prepare("SELECT * FROM korisnik WHERE KorisnikId=?");
$sel->bind_param('s', $korisnik);
$sel->execute();
$data = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$success_info) {
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

    <div class="prof-tabs">

      <div class="prof-tab-nav">
        <button class="prof-tab-btn <?= $activeTab === 'podaci'  ? 'active' : '' ?>" data-tab="podaci">Moji podaci</button>
        <button class="prof-tab-btn <?= $activeTab === 'lozinka' ? 'active' : '' ?>" data-tab="lozinka">Promena lozinke</button>
      </div>

      <!-- Tab: Moji podaci -->
      <div id="tab-podaci" class="prof-tab-panel" <?= $activeTab !== 'podaci' ? 'hidden' : '' ?>>

        <?php if ($success_info): ?>
        <div class="ct-alert ct-alert--ok">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Podaci su uspešno sačuvani.
        </div>
        <?php elseif ($poruka_info !== ''): ?>
        <div class="ct-alert ct-alert--err">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
          </svg>
          <?= htmlspecialchars($poruka_info) ?>
        </div>
        <?php endif; ?>

        <form method="post" class="auth-form" autocomplete="off">
          <input type="hidden" name="tip" value="info">

          <div class="auth-grid">
            <div class="ct-field">
              <label>Korisničko ime</label>
              <input type="text" value="<?= htmlspecialchars($korisnik) ?>" readonly>
            </div>
            <?php if ($nivo >= 2): ?>
            <div class="ct-field">
              <label>Nivo pristupa</label>
              <input type="text" value="<?= htmlspecialchars($nivoLabel[(string)$nivo] ?? (string)$nivo) ?>" readonly>
            </div>
            <?php endif; ?>
          </div>

          <div class="auth-grid">
            <div class="ct-field">
              <label for="ime">Ime <span class="ct-req">*</span></label>
              <input type="text" id="ime" name="ime" value="<?= htmlspecialchars($ime) ?>" autocomplete="off">
            </div>
            <div class="ct-field">
              <label for="prezime">Prezime <span class="ct-req">*</span></label>
              <input type="text" id="prezime" name="prezime" value="<?= htmlspecialchars($prezime) ?>" autocomplete="off">
            </div>
          </div>

          <div class="ct-field">
            <label for="datumr">Datum rođenja <span class="ct-req">*</span></label>
            <input type="date" id="datumr" name="datumr" value="<?= htmlspecialchars($datumr) ?>"
                   max="<?= date('Y-m-d') ?>" autocomplete="off">
          </div>

          <div class="ct-field">
            <label for="email">Email <span class="ct-req">*</span></label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" autocomplete="off">
          </div>

          <div class="ct-field">
            <label for="telefon">Telefon <span class="ct-req">*</span></label>
            <input type="tel" id="telefon" name="telefon" value="<?= htmlspecialchars($telefon) ?>"
                   placeholder="+381 6x xxxxxxx" autocomplete="off">
          </div>

          <button type="submit" class="auth-btn">Sačuvaj podatke</button>
        </form>
      </div>

      <!-- Tab: Promena lozinke -->
      <div id="tab-lozinka" class="prof-tab-panel" <?= $activeTab !== 'lozinka' ? 'hidden' : '' ?>>

        <?php if ($success_loz): ?>
        <div class="ct-alert ct-alert--ok">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Lozinka je uspešno promenjena.
        </div>
        <?php elseif ($poruka_loz !== ''): ?>
        <div class="ct-alert ct-alert--err">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
          </svg>
          <?= htmlspecialchars($poruka_loz) ?>
        </div>
        <?php endif; ?>

        <form method="post" class="auth-form" autocomplete="off">
          <input type="hidden" name="tip" value="lozinka">

          <div class="ct-field">
            <label for="lozinka">Nova lozinka <span class="ct-req">*</span></label>
            <input type="password" id="lozinka" name="lozinka" value="" autocomplete="new-password">
          </div>
          <div class="ct-field">
            <label for="lozinka2">Ponovite lozinku <span class="ct-req">*</span></label>
            <input type="password" id="lozinka2" name="lozinka2" value="" autocomplete="new-password">
          </div>

          <button type="submit" class="auth-btn">Promeni lozinku</button>
        </form>
      </div>

    </div><!-- .prof-tabs -->
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const btns   = document.querySelectorAll('.prof-tab-btn');
  const panels = document.querySelectorAll('.prof-tab-panel');
  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => b.classList.remove('active'));
      panels.forEach(p => p.hidden = true);
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).hidden = false;
    });
  });
})();
</script>
</body>
</html>
