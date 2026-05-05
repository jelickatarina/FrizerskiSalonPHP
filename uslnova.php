<?php
require_once 'sesija.php';
requireNivo('2');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka   = "";
    $usluga   = trim($_POST['usluga']   ?? '');
    $cena     = trim($_POST['cena']     ?? '');
    $trajanje = trim($_POST['trajanje'] ?? '');
    $opis     = trim($_POST['opis']     ?? '');
    $aktivna  = isset($_POST['aktivna']) ? 1 : 0;

    if (strlen($usluga)   === 0) $poruka = "Unesite naziv usluge.";
    elseif (strlen($cena) === 0) $poruka = "Unesite cenu.";
    elseif (!is_numeric($cena)) $poruka = "Cena mora biti broj.";
    elseif (strlen($trajanje) === 0) $poruka = "Unesite trajanje.";
    elseif (!is_numeric($trajanje)) $poruka = "Trajanje mora biti broj.";
    elseif (strlen($opis) === 0) $poruka = "Unesite opis.";
    else {
        include 'konekcija.php';
        $chk = $conn->prepare("SELECT COUNT(*) AS c1 FROM usluga WHERE UslugaId=?");
        $chk->bind_param('s', $usluga);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($row['c1'] > 0) {
            $poruka = "Usluga „" . htmlspecialchars($usluga) . "" već postoji.";
        } else {
            include_once 'konekcija.php';
            $ins = $conn->prepare("INSERT INTO usluga (UslugaId, Cena, Trajanje, Opis, Aktivna) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('sddsi', $usluga, $cena, $trajanje, $opis, $aktivna);
            $ins->execute();
            $ins->close();
            header('Location: usluge.php');
            exit;
        }
    }
} else {
    $poruka   = "";
    $usluga   = "";
    $cena     = "";
    $trajanje = "";
    $opis     = "";
    $aktivna  = 1;
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
    <title>Nova usluga – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <span class="ct-eyebrow">Upravljanje uslugama</span>
      <h1 class="auth-title">Nova usluga</h1>
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
        <label for="usluga">Naziv usluge <span class="ct-req">*</span></label>
        <input type="text" id="usluga" name="usluga"
               value="<?= htmlspecialchars($usluga) ?>"
               placeholder="npr. Šišanje, Bojenje…" autocomplete="off">
      </div>

      <div class="auth-grid">
        <div class="ct-field">
          <label for="cena">Cena (RSD) <span class="ct-req">*</span></label>
          <input type="number" id="cena" name="cena" min="0" step="any"
                 value="<?= htmlspecialchars($cena) ?>"
                 placeholder="0" autocomplete="off">
        </div>
        <div class="ct-field">
          <label for="trajanje">Trajanje (min) <span class="ct-req">*</span></label>
          <input type="number" id="trajanje" name="trajanje" min="1" step="1"
                 value="<?= htmlspecialchars($trajanje) ?>"
                 placeholder="30" autocomplete="off">
        </div>
      </div>

      <div class="ct-field">
        <label for="opis">Opis <span class="ct-req">*</span></label>
        <textarea id="opis" name="opis" rows="3"
                  placeholder="Kratak opis usluge…"><?= htmlspecialchars($opis) ?></textarea>
      </div>

      <div class="ct-field">
        <label>Status</label>
        <div class="ct-toggle">
          <input type="checkbox" id="aktivna" name="aktivna"
                 <?= $aktivna ? 'checked' : '' ?>>
          <label for="aktivna" class="ct-toggle-label">Aktivna usluga</label>
        </div>
      </div>

      <button type="submit" class="auth-btn">Sačuvaj uslugu</button>

    </form>

    <p class="auth-switch"><a href="usluge.php">← Nazad na usluge</a></p>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
