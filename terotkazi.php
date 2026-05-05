<?php
if (!isset($_REQUEST['p'])) { echo "Neispravan poziv strane"; exit; }

require_once 'sesija.php';
requireNivo('1');

$termin = (int)$_REQUEST['p'];
include 'konekcija.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $terminId = (int)($_POST['termin'] ?? 0);
    $upd = $conn->prepare("UPDATE termin SET Otkazano=1 WHERE TerminId=?");
    $upd->bind_param('i', $terminId);
    $upd->execute();
    $upd->close();
    header('Location: termini.php');
    exit;
}

// GET – load appointment details
$sel = $conn->prepare(
    "SELECT t.*, k.Ime AS KIme, k.Prezime AS KPrezime,
            f.Ime AS FIme, f.Prezime AS FPrezime
     FROM termin t
     LEFT JOIN korisnik k ON k.KorisnikId = t.KorisnikId
     LEFT JOIN korisnik f ON f.KorisnikId = t.KorisnikFrizerId
     WHERE t.TerminId=?"
);
$sel->bind_param('i', $termin);
$sel->execute();
$data = $sel->get_result()->fetch_assoc();
$sel->close();
if (!$data) { echo "Termin nije pronađen."; exit; }

$usluga      = $data['UslugaId'];
$klijentNaziv = trim(($data['KIme'] ?? '') . ' ' . ($data['KPrezime'] ?? '')) ?: $data['KorisnikId'];
$frizerNaziv  = trim(($data['FIme'] ?? '') . ' ' . ($data['FPrezime'] ?? '')) ?: $data['KorisnikFrizerId'];
$datum        = $data['Datum'];
$tmp1 = (int)($data['Vreme'] / 2);
$tmp2 = ($data['Vreme'] % 2) * 30;
$tv   = sprintf('%02d:%02d', $tmp1, $tmp2);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Otkazivanje termina – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <span class="ct-eyebrow">Potvrda akcije</span>
      <h1 class="auth-title">Otkazivanje</h1>
      <div class="ct-orn">
        <span></span>
        <svg viewBox="0 0 10 10"><polygon points="5,0 10,5 5,10 0,5" fill="currentColor"/></svg>
        <span></span>
      </div>
    </div>

    <div class="ct-alert ct-alert--err">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/>
      </svg>
      Da li ste sigurni da želite da otkažete ovaj termin? Akcija se ne može poništiti.
    </div>

    <div class="auth-form">
      <div class="ct-field">
        <label>Usluga</label>
        <input type="text" value="<?= htmlspecialchars($usluga) ?>" readonly>
      </div>
      <div class="ct-field">
        <label>Klijent</label>
        <input type="text" value="<?= htmlspecialchars($klijentNaziv) ?>" readonly>
      </div>
      <div class="auth-grid">
        <div class="ct-field">
          <label>Datum</label>
          <input type="text" value="<?= htmlspecialchars($datum) ?>" readonly>
        </div>
        <div class="ct-field">
          <label>Vreme</label>
          <input type="text" value="<?= htmlspecialchars($tv) ?>" readonly>
        </div>
      </div>
      <div class="ct-field">
        <label>Frizer</label>
        <input type="text" value="<?= htmlspecialchars($frizerNaziv) ?>" readonly>
      </div>

      <form method="post">
        <input type="hidden" name="termin" value="<?= $termin ?>">
        <button type="submit" class="auth-btn auth-btn--danger">Potvrdi otkazivanje</button>
      </form>
    </div>

    <p class="auth-switch"><a href="termini.php">← Nazad na termine</a></p>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
