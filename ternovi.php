<?php
  session_start();
  include 'konekcija.php';
  if (!isset($_SESSION['korisnik']) || $_SESSION['nivo'] != '1')
    header('Location: nemaovlascenje.html');

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka  = "";
    $t1      = trim($_POST['t1'] ?? '1');
    $korisnik = trim($_POST['korisnik'] ?? $_SESSION['korisnik']);
    $usluga  = trim($_POST['usluga']  ?? '');
    $datum   = trim($_POST['datum']   ?? '');
    $frizer  = trim($_POST['frizer']  ?? '');

    if ($t1 == 1) {
      $date1 = new DateTime();
      $date1->setTime(0,0,0,0);
      $date2   = new DateTime($datum);
      $interval = $date1->diff($date2);
      $tmp1    = intval($interval->format('%R%a'));

      if ($tmp1 == 0) {
        $tv   = date("H:i:s");
        $tmp2 = substr($tv, 0, 2);
        $tmp3 = substr($tv, 3, 2);
        $tpoc = $tmp2 * 2 + (int)($tmp3 / 30) + 1;
      } else $tpoc = 18;

      if ($usluga === '')  $poruka = "Niste izabrali uslugu.";
      elseif ($datum === '') $poruka = "Unesite datum.";
      elseif ($frizer === '') $poruka = "Niste izabrali frizera.";
      elseif (date('w', strtotime($datum)) == 0) $poruka = "Ne radimo nedeljom.";
      elseif ($tmp1 < 0)  $poruka = "Datum je u prošlosti.";
      elseif ($tmp1 > 7)  $poruka = "Zakazivanje maksimalno 7 dana unapred.";
      else {
        $stmt = $conn->prepare("SELECT Trajanje FROM usluga WHERE UslugaId=?");
        $stmt->bind_param('s', $usluga);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$data) die("Ne mogu da pročitam trajanje termina.");

        $ukupnoTermina = (int)(1 + ($data['Trajanje'] - 1) / 30);
        $zauzetiTermini = [];

        $stmt = $conn->prepare("SELECT Vreme, UslugaId FROM termin WHERE KorisnikFrizerId=? AND Datum=?");
        $stmt->bind_param('ss', $frizer, $datum);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        while ($row = $res->fetch_assoc()) {
          $tv = $row['Vreme'];
          $zauzetiTermini[] = $tv;
          $stmt2 = $conn->prepare("SELECT Trajanje FROM usluga WHERE UslugaId=?");
          $stmt2->bind_param('s', $row['UslugaId']);
          $stmt2->execute();
          $d2 = $stmt2->get_result()->fetch_assoc();
          $stmt2->close();
          $trajanje2 = (int)(1 + ($d2['Trajanje'] - 1) / 30);
          for ($i = 1; $i < $trajanje2; $i++) { $tv++; $zauzetiTermini[] = $tv; }
        }

        $vremena = [];
        for ($i = $tpoc; $i <= (34 - $ukupnoTermina); $i++) {
          $nemoze = false;
          if (!in_array($i, $zauzetiTermini)) {
            for ($j = 1; $j < $ukupnoTermina; $j++)
              if (in_array($i + $j, $zauzetiTermini)) $nemoze = true;
            if (!$nemoze) {
              $vremena[] = sprintf('%02d:%02d', (int)($i / 2), ($i % 2) * 30);
            }
          }
        }

        if (count($vremena) == 0) $poruka = "Nema slobodnih termina za izabrani dan.";
        else { $vreme = ""; $t1 = 2; }
      }
    } else {
      $vreme = trim($_POST['vreme'] ?? '');
      if ($vreme === '') $poruka = "Izaberite vreme.";
      else {
        $h   = (int)substr($vreme, 0, 2);
        $m   = (int)substr($vreme, 3, 2);
        $res = $h * 2 + (int)($m / 30);
        $stmt = $conn->prepare(
          "INSERT INTO termin (UslugaId, KorisnikId, Datum, Vreme, KorisnikFrizerId, Uradjeno)
           VALUES (?, ?, ?, ?, ?, 0)"
        );
        $stmt->bind_param('sssss', $usluga, $korisnik, $datum, $res, $frizer);
        $stmt->execute();
        $stmt->close();
        header('Location: termini.php');
        exit;
      }
    }
  } else {
    $poruka = ""; $t1 = 1;
    $korisnik = $_SESSION['korisnik'];
    $usluga = $datum = $frizer = $vreme = "";
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
    <title>Zakazivanje – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <span class="ct-eyebrow">
        <?= $t1 == 1 ? 'Korak 1 od 2' : 'Korak 2 od 2' ?>
      </span>
      <h1 class="auth-title">Zakazivanje</h1>
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
      <input type="hidden" name="t1" value="<?= $t1 ?>">
      <input type="hidden" name="korisnik" value="<?= htmlspecialchars($korisnik) ?>">

      <div class="ct-field">
        <label>Korisnik</label>
        <input type="text" value="<?= htmlspecialchars($korisnik) ?>" disabled>
      </div>

      <div class="ct-field">
        <label for="zk-usluga">Usluga <span class="ct-req">*</span></label>
        <?php if ($t1 == 1): ?>
        <select id="zk-usluga" name="usluga">
          <?php
            $res = $conn->query("SELECT UslugaId, Opis, Cena FROM usluga WHERE Aktivna=1 ORDER BY UslugaId");
            while ($row = $res->fetch_assoc()):
          ?>
          <option value="<?= htmlspecialchars($row['UslugaId']) ?>"
            <?= $row['UslugaId'] === $usluga ? 'selected' : '' ?>>
            <?= htmlspecialchars($row['UslugaId']) ?> — <?= htmlspecialchars($row['Opis']) ?> (<?= $row['Cena'] ?> RSD)
          </option>
          <?php endwhile; ?>
        </select>
        <?php else: ?>
        <input type="text" name="usluga" value="<?= htmlspecialchars($usluga) ?>" readonly>
        <?php endif; ?>
      </div>

      <div class="ct-field">
        <label for="zk-datum">Datum <span class="ct-req">*</span></label>
        <?php if ($t1 == 1): ?>
        <input type="date" id="zk-datum" name="datum"
               value="<?= htmlspecialchars($datum) ?>"
               min="<?= date('Y-m-d') ?>"
               max="<?= date('Y-m-d', strtotime('+7 days')) ?>">
        <?php else: ?>
        <input type="text" name="datum" value="<?= htmlspecialchars($datum) ?>" readonly>
        <?php endif; ?>
      </div>

      <div class="ct-field">
        <label for="zk-frizer">Frizer <span class="ct-req">*</span></label>
        <?php if ($t1 == 1): ?>
        <select id="zk-frizer" name="frizer">
          <?php
            $res = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime");
            while ($row = $res->fetch_assoc()):
          ?>
          <option value="<?= htmlspecialchars($row['KorisnikId']) ?>"
            <?= $row['KorisnikId'] === $frizer ? 'selected' : '' ?>>
            <?= htmlspecialchars($row['Ime'] . ' ' . $row['Prezime']) ?>
          </option>
          <?php endwhile; ?>
        </select>
        <?php else: ?>
        <input type="text" name="frizer" value="<?= htmlspecialchars($frizer) ?>" readonly>
        <?php endif; ?>
      </div>

      <?php if ($t1 == 2): ?>
      <div class="ct-field">
        <label for="zk-vreme">Slobodni termini <span class="ct-req">*</span></label>
        <select id="zk-vreme" name="vreme">
          <?php foreach ($vremena as $tv): ?>
          <option value="<?= $tv ?>" <?= $tv === $vreme ? 'selected' : '' ?>><?= $tv ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <button type="submit" class="auth-btn">
        <?= $t1 == 1 ? 'Pronađi termine' : 'Potvrdi zakazivanje' ?>
      </button>
    </form>

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
