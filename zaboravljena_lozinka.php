<?php
require_once 'sesija.php';
require_once 'email.php';

$poruka  = "";
$success = false;
$tempPass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnik = trim($_POST['korisnik'] ?? '');
    $email    = trim($_POST['email']    ?? '');

    if (strlen($korisnik) === 0)
        $poruka = "Unesite korisničko ime.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $poruka = "Unesite ispravnu email adresu.";
    else {
        include 'konekcija.php';
        $sel = $conn->prepare("SELECT KorisnikId, Ime, Email FROM korisnik WHERE KorisnikId=? AND Email=? AND Nivo>0");
        $sel->bind_param('ss', $korisnik, $email);
        $sel->execute();
        $data = $sel->get_result()->fetch_assoc();
        $sel->close();

        if (!$data) {
            // Always show success to prevent username enumeration
            $success = true;
        } else {
            $chars    = 'abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $tempPass = '';
            for ($i = 0; $i < 8; $i++) {
                $tempPass .= $chars[random_int(0, strlen($chars) - 1)];
            }

            $upd = $conn->prepare("UPDATE korisnik SET Lozinka=? WHERE KorisnikId=?");
            $upd->bind_param('ss', $tempPass, $korisnik);
            $upd->execute();
            $upd->close();

            $body = _emailTpl("Resetovanje lozinke",
                "<p>Zdravo, <strong>" . htmlspecialchars($data['Ime']) . "</strong>!</p>
                 <p>Vaša lozinka je resetovana. Nova privremena lozinka je:</p>
                 <div style='background:#1a1814;border:1px solid rgba(212,168,40,0.3);border-radius:6px;
                   padding:0.9rem 1.2rem;margin:1rem 0;font-family:monospace;font-size:1.1rem;
                   letter-spacing:0.15em;color:#d4a828;text-align:center;'>" . htmlspecialchars($tempPass) . "</div>
                 <p style='color:#888;font-size:0.85rem;'>Prijavite se sa ovom lozinkom i odmah je promenite u podešavanjima profila.</p>");

            posaljiEmail($data['Email'], "Resetovanje lozinke – Frizerski salon", $body);
            $success = true;
        }
    }
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
    <title>Zaboravljena lozinka – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <span class="ct-eyebrow">Oporavak pristupa</span>
      <h1 class="auth-title">Lozinka</h1>
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
      Ako korisničko ime i email adresa odgovaraju nalogu, poslaćemo vam novu privremenu lozinku. Proverite email.
    </div>
    <a href="prijava.php" class="auth-btn" style="display:block;text-align:center;text-decoration:none;margin-top:0.5rem;">Nazad na prijavu</a>

    <?php else: ?>

    <?php if ($poruka !== ''): ?>
    <div class="ct-alert ct-alert--err">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
      </svg>
      <?= htmlspecialchars($poruka) ?>
    </div>
    <?php endif; ?>

    <p style="font-family:'Jost',sans-serif;font-size:0.82rem;color:rgba(255,255,255,0.4);margin:0;">
      Unesite vaše korisničko ime i email adresu. Poslaćemo vam privremenu lozinku.
    </p>

    <form method="post" class="auth-form" autocomplete="off">
      <div class="ct-field">
        <label for="korisnik">Korisničko ime <span class="ct-req">*</span></label>
        <input type="text" id="korisnik" name="korisnik"
               value="<?= htmlspecialchars($_POST['korisnik'] ?? '') ?>" autocomplete="off">
      </div>
      <div class="ct-field">
        <label for="email">Email adresa <span class="ct-req">*</span></label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="off">
      </div>
      <button type="submit" class="auth-btn">Pošalji novu lozinku</button>
    </form>

    <p class="auth-switch"><a href="prijava.php">← Nazad na prijavu</a></p>

    <?php endif; ?>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
