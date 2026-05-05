<?php
if (!isset($_REQUEST['p'])) { echo "Neispravan poziv strane"; exit; }

require_once 'sesija.php';
requireNivo('2');

$usluga = $_REQUEST['p'];
include 'konekcija.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka    = "";
    $usluga    = trim($_POST['usluga']    ?? '');
    $cena      = trim($_POST['cena']      ?? '');
    $trajanje  = trim($_POST['trajanje']  ?? '');
    $opis      = trim($_POST['opis']      ?? '');
    $aktivna   = isset($_POST['aktivna']) ? 1 : 0;
    $imaPopust = isset($_POST['ima_popust']);
    $popust    = $imaPopust ? (int)($_POST['popust'] ?? 0) : 0;
    $popustOd  = $imaPopust ? trim($_POST['popust_od'] ?? '') : null;
    $popustDo  = $imaPopust ? trim($_POST['popust_do'] ?? '') : null;

    if (strlen($usluga) === 0)       $poruka = "Naziv usluge je obavezan.";
    elseif (strlen($cena) === 0)     $poruka = "Unesite cenu.";
    elseif (!is_numeric($cena))      $poruka = "Cena mora biti broj.";
    elseif ((float)$cena < 0)        $poruka = "Cena ne može biti negativna.";
    elseif (strlen($trajanje) === 0) $poruka = "Unesite trajanje.";
    elseif (!is_numeric($trajanje) || (int)$trajanje < 1) $poruka = "Trajanje mora biti pozitivan broj.";
    elseif (strlen($opis) === 0)     $poruka = "Unesite opis.";
    elseif ($imaPopust && ($popust < 1 || $popust > 99))  $poruka = "Popust mora biti između 1 i 99%.";
    elseif ($imaPopust && !$popustOd) $poruka = "Unesite datum početka popusta.";
    elseif ($imaPopust && !$popustDo) $poruka = "Unesite datum kraja popusta.";
    elseif ($imaPopust && $popustOd > $popustDo) $poruka = "Datum početka mora biti pre datuma kraja.";
    else {
        $upd = $conn->prepare(
            "UPDATE usluga SET Cena=?, Trajanje=?, Opis=?, Aktivna=?, Popust=?, PopustOd=?, PopustDo=?
             WHERE UslugaId=?"
        );
        $upd->bind_param('ddsiisss', $cena, $trajanje, $opis, $aktivna, $popust, $popustOd, $popustDo, $usluga);
        $upd->execute();
        $upd->close();
        header('Location: usluge.php');
        exit;
    }
} else {
    $poruka = "";
    $sel = $conn->prepare("SELECT * FROM usluga WHERE UslugaId=?");
    $sel->bind_param('s', $usluga);
    $sel->execute();
    $data = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$data) { echo "Usluga nije pronađena."; exit; }
    $cena      = $data['Cena'];
    $trajanje  = $data['Trajanje'];
    $opis      = $data['Opis'];
    $aktivna   = (int)$data['Aktivna'];
    $popust    = (int)($data['Popust'] ?? 0);
    $popustOd  = $data['PopustOd'] ?? '';
    $popustDo  = $data['PopustDo'] ?? '';
    $imaPopust = $popust > 0;
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
    <title>Izmeni uslugu – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <span class="ct-eyebrow">Upravljanje uslugama</span>
      <h1 class="auth-title">Izmeni uslugu</h1>
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

    <form method="post" class="auth-form">

      <div class="ct-field">
        <label>Naziv usluge</label>
        <input type="text" name="usluga" value="<?= htmlspecialchars($usluga) ?>" readonly>
      </div>

      <div class="auth-grid">
        <div class="ct-field">
          <label for="cena">Cena (RSD) <span class="ct-req">*</span></label>
          <input type="number" id="cena" name="cena" min="0" step="any" value="<?= htmlspecialchars($cena) ?>">
        </div>
        <div class="ct-field">
          <label for="trajanje">Trajanje (min) <span class="ct-req">*</span></label>
          <input type="number" id="trajanje" name="trajanje" min="1" step="1" value="<?= htmlspecialchars($trajanje) ?>">
        </div>
      </div>

      <div class="ct-field">
        <label for="opis">Opis <span class="ct-req">*</span></label>
        <textarea id="opis" name="opis" rows="3"><?= htmlspecialchars($opis) ?></textarea>
      </div>

      <div class="ct-field">
        <label>Status</label>
        <div class="ct-toggle">
          <input type="checkbox" id="aktivna" name="aktivna" <?= $aktivna ? 'checked' : '' ?>>
          <label for="aktivna" class="ct-toggle-label">Aktivna usluga</label>
        </div>
      </div>

      <div class="prof-divider"><span>Popust</span></div>

      <div class="ct-field">
        <div class="ct-toggle">
          <input type="checkbox" id="ima_popust" name="ima_popust" <?= $imaPopust ? 'checked' : '' ?>>
          <label for="ima_popust" class="ct-toggle-label">Na popustu</label>
        </div>
      </div>

      <div id="popust-fields" <?= $imaPopust ? '' : 'hidden' ?>>
        <div class="auth-grid" style="gap:1.1rem">
          <div class="ct-field">
            <label for="popust">Popust (%) <span class="ct-req">*</span></label>
            <input type="number" id="popust" name="popust" min="1" max="99"
                   value="<?= htmlspecialchars($popust ?: '') ?>">
          </div>
          <div></div>
        </div>
        <div class="auth-grid" style="gap:1.1rem;margin-top:1.1rem">
          <div class="ct-field">
            <label for="popust_od">Važi od <span class="ct-req">*</span></label>
            <input type="date" id="popust_od" name="popust_od" value="<?= htmlspecialchars($popustOd ?? '') ?>">
          </div>
          <div class="ct-field">
            <label for="popust_do">Važi do <span class="ct-req">*</span></label>
            <input type="date" id="popust_do" name="popust_do" value="<?= htmlspecialchars($popustDo ?? '') ?>">
          </div>
        </div>
      </div>

      <button type="submit" class="auth-btn">Sačuvaj izmene</button>
    </form>

    <p class="auth-switch"><a href="usluge.php">← Nazad na usluge</a></p>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const tog = document.getElementById('ima_popust');
  const flds = document.getElementById('popust-fields');
  if (tog) tog.addEventListener('change', () => flds.hidden = !tog.checked);
})();
</script>
</body>
</html>
