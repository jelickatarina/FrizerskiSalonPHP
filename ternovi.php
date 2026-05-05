<?php
require_once 'sesija.php';
include 'konekcija.php';
requireNivo('1');

$nivo     = (int)$_SESSION['nivo'];
$poruka   = "";
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t1          = trim($_POST['t1'] ?? '1');
    $korisnik    = trim($_POST['korisnik']      ?? ($nivo >= 2 ? '' : $_SESSION['korisnik']));
    $korisnikNaziv = trim($_POST['korisnik_naziv'] ?? '');
    $usluga      = trim($_POST['usluga']        ?? '');
    $datum       = trim($_POST['datum']         ?? '');
    $frizer      = trim($_POST['frizer']        ?? '');
    $frizerNaziv = trim($_POST['frizer_naziv']  ?? '');

    if ($t1 == 1) {
        // Step 1: validate and find frizer KorisnikId from displayed name
        $date1 = new DateTime(); $date1->setTime(0,0,0,0);
        $date2 = new DateTime($datum);
        $diff  = (int)$date1->diff($date2)->format('%R%a');

        if ($nivo >= 2 && $korisnik === '') $poruka = "Izaberite klijenta.";
        elseif ($usluga === '')  $poruka = "Niste izabrali uslugu.";
        elseif ($datum === '') $poruka = "Unesite datum.";
        elseif ($frizerNaziv === '') $poruka = "Niste izabrali frizera.";
        elseif (date('w', strtotime($datum)) == 0) $poruka = "Ne radimo nedeljom.";
        elseif ($diff < 0)   $poruka = "Datum je u prošlosti.";
        elseif ($diff > 7)   $poruka = "Zakazivanje maksimalno 7 dana unapred.";
        else {
            // Resolve frizer name → KorisnikId
            $parts = explode(' ', $frizerNaziv, 2);
            $ime   = $parts[0] ?? '';
            $prez  = $parts[1] ?? '';
            $sf    = $conn->prepare("SELECT KorisnikId FROM korisnik WHERE Ime=? AND Prezime=? AND Nivo=2");
            $sf->bind_param('ss', $ime, $prez);
            $sf->execute();
            $fr = $sf->get_result()->fetch_assoc();
            $sf->close();
            if (!$fr) { $poruka = "Frizer nije pronađen. Izaberite iz liste."; }
            else {
                $frizer = $fr['KorisnikId'];

                if ($nivo >= 2) {
                    $ck = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=? AND Nivo=1");
                    $ck->bind_param('s', $korisnik);
                    $ck->execute();
                    $kRow2 = $ck->get_result()->fetch_assoc();
                    $ck->close();
                    if (!$kRow2) $poruka = "Klijent nije pronađen. Izaberite iz liste.";
                    else $korisnikNaziv = $kRow2['Ime'].' '.$kRow2['Prezime'];
                }

                if (!$poruka) {
                    $stmt = $conn->prepare("SELECT Trajanje FROM usluga WHERE UslugaId=?");
                    $stmt->bind_param('s', $usluga);
                    $stmt->execute();
                    $data = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$data) { $poruka = "Usluga nije pronađena."; }
                    else {
                        $ukupnoTermina  = (int)(1 + ($data['Trajanje'] - 1) / 30);
                        $zauzetiTermini = [];
                        $stmt = $conn->prepare("SELECT t.Vreme, t.UslugaId FROM termin t WHERE t.KorisnikFrizerId=? AND t.Datum=?");
                        $stmt->bind_param('ss', $frizer, $datum);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $stmt->close();
                        while ($row = $res->fetch_assoc()) {
                            $tv = $row['Vreme'];
                            $zauzetiTermini[] = $tv;
                            $s2 = $conn->prepare("SELECT Trajanje FROM usluga WHERE UslugaId=?");
                            $s2->bind_param('s', $row['UslugaId']);
                            $s2->execute();
                            $d2 = $s2->get_result()->fetch_assoc();
                            $s2->close();
                            $t2 = (int)(1 + ($d2['Trajanje'] - 1) / 30);
                            for ($i = 1; $i < $t2; $i++) { $tv++; $zauzetiTermini[] = $tv; }
                        }

                        if ($diff == 0) {
                            $tv   = date("H:i:s");
                            $h    = (int)substr($tv, 0, 2);
                            $m    = (int)substr($tv, 3, 2);
                            $tpoc = $h * 2 + (int)($m / 30) + 1;
                        } else { $tpoc = 18; }

                        $vremena = [];
                        for ($i = $tpoc; $i <= (34 - $ukupnoTermina); $i++) {
                            $nemoze = false;
                            if (!in_array($i, $zauzetiTermini)) {
                                for ($j = 1; $j < $ukupnoTermina; $j++)
                                    if (in_array($i + $j, $zauzetiTermini)) $nemoze = true;
                                if (!$nemoze) $vremena[] = sprintf('%02d:%02d', (int)($i/2), ($i%2)*30);
                            }
                        }

                        if (count($vremena) == 0) $poruka = "Nema slobodnih termina za izabrani dan.";
                        else { $vreme = ""; $t1 = 2; }
                    }
                }
            }
        }
    } else {
        // Step 2: insert appointment
        $vreme = trim($_POST['vreme'] ?? '');
        if ($vreme === '') { $poruka = "Izaberite vreme."; }
        else {
            $h   = (int)substr($vreme, 0, 2);
            $m   = (int)substr($vreme, 3, 2);
            $res = $h * 2 + (int)($m / 30);

            $potvrdjeno = ($nivo === 1) ? 0 : 1;

            $stmt = $conn->prepare(
                "INSERT INTO termin (UslugaId, KorisnikId, Datum, Vreme, KorisnikFrizerId, Uradjeno, Potvrdjeno)
                 VALUES (?, ?, ?, ?, ?, 0, ?)"
            );
            $stmt->bind_param('sssssi', $usluga, $korisnik, $datum, $res, $frizer, $potvrdjeno);
            $stmt->execute();
            $stmt->close();

            if ($nivo === 1) {
                // Notify frizer by email
                include_once 'email.php';
                $sf = $conn->prepare("SELECT Email, Ime, Prezime FROM korisnik WHERE KorisnikId=?");
                $sf->bind_param('s', $frizer);
                $sf->execute();
                $frRow = $sf->get_result()->fetch_assoc();
                $sf->close();

                $sk = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
                $sk->bind_param('s', $korisnik);
                $sk->execute();
                $kRow = $sk->get_result()->fetch_assoc();
                $sk->close();

                if ($frRow && !empty($frRow['Email'])) {
                    emailNoviZahtev(
                        $frRow['Email'],
                        $frRow['Ime'].' '.$frRow['Prezime'],
                        $kRow ? $kRow['Ime'].' '.$kRow['Prezime'] : $korisnik,
                        $usluga,
                        $datum,
                        $vreme
                    );
                }
                $success = true;
            } else {
                header('Location: termini.php');
                exit;
            }
        }
    }
} else {
    $poruka = ""; $t1 = 1;
    $korisnik     = ($nivo >= 2) ? '' : $_SESSION['korisnik'];
    $korisnikNaziv = '';
    $usluga = $datum = $frizer = $frizerNaziv = $vreme = "";
}

// Load lists for combos
$usluge   = $conn->query("SELECT UslugaId FROM usluga WHERE Aktivna=1 ORDER BY UslugaId");
$frizeri  = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime");
$klijenti = ($nivo >= 2)
    ? $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=1 ORDER BY Ime, Prezime")
    : null;
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
        <?= $success ? 'Zahtev primljen' : ($t1 == 1 ? 'Korak 1 od 2' : 'Korak 2 od 2') ?>
      </span>
      <h1 class="auth-title">Zakazivanje</h1>
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
      Zahtev je uspešno poslat! Frizer će ga pregledati i potvrditi.
    </div>
    <a href="termini.php" class="auth-btn" style="display:block;text-align:center;text-decoration:none;margin-top:1rem;">Moji termini</a>

    <?php else: ?>

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
        <label for="<?= $nivo >= 2 ? 'zk-korisnik-text' : 'zk-korisnik-ro' ?>">
          Klijent<?= $nivo >= 2 ? ' <span class="ct-req">*</span>' : '' ?>
        </label>
        <?php if ($nivo >= 2 && $t1 == 1): ?>
        <div class="zk-combo" id="combo-korisnik">
          <input type="text" class="zk-combo-input" id="zk-korisnik-text"
                 value="<?= htmlspecialchars($korisnikNaziv) ?>"
                 placeholder="Pretražite ili izaberite klijenta…" autocomplete="off">
          <input type="hidden" name="korisnik"       id="zk-korisnik-val"   value="<?= htmlspecialchars($korisnik) ?>">
          <input type="hidden" name="korisnik_naziv" id="zk-korisnik-naziv" value="<?= htmlspecialchars($korisnikNaziv) ?>">
          <ul class="zk-combo-list" role="listbox">
            <?php while ($row = $klijenti->fetch_assoc()): ?>
            <li role="option"
                data-val="<?= htmlspecialchars($row['KorisnikId']) ?>"
                data-label="<?= htmlspecialchars($row['Ime'].' '.$row['Prezime']) ?>">
              <?= htmlspecialchars($row['Ime'].' '.$row['Prezime']) ?>
            </li>
            <?php endwhile; ?>
          </ul>
        </div>
        <?php elseif ($nivo >= 2): ?>
        <input type="hidden" name="korisnik"       value="<?= htmlspecialchars($korisnik) ?>">
        <input type="hidden" name="korisnik_naziv" value="<?= htmlspecialchars($korisnikNaziv) ?>">
        <input id="zk-korisnik-ro" type="text" value="<?= htmlspecialchars($korisnikNaziv ?: $korisnik) ?>" readonly>
        <?php else: ?>
        <input type="hidden" name="korisnik" value="<?= htmlspecialchars($korisnik) ?>">
        <input id="zk-korisnik-ro" type="text" value="<?= htmlspecialchars($korisnik) ?>" disabled>
        <?php endif; ?>
      </div>

      <div class="ct-field">
        <label for="zk-usluga-text">Usluga <span class="ct-req">*</span></label>
        <?php if ($t1 == 1): ?>
        <div class="zk-combo" id="combo-usluga">
          <input type="text" class="zk-combo-input" id="zk-usluga-text"
                 value="<?= htmlspecialchars($usluga) ?>"
                 placeholder="Pretražite ili izaberite uslugu…" autocomplete="off">
          <input type="hidden" name="usluga" id="zk-usluga-val" value="<?= htmlspecialchars($usluga) ?>">
          <ul class="zk-combo-list" role="listbox">
            <?php while ($row = $usluge->fetch_assoc()): ?>
            <li role="option" data-val="<?= htmlspecialchars($row['UslugaId']) ?>">
              <?= htmlspecialchars($row['UslugaId']) ?>
            </li>
            <?php endwhile; ?>
          </ul>
        </div>
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
        <label for="zk-frizer-text">Frizer <span class="ct-req">*</span></label>
        <?php if ($t1 == 1): ?>
        <div class="zk-combo" id="combo-frizer">
          <input type="text" class="zk-combo-input" id="zk-frizer-text"
                 value="<?= htmlspecialchars($frizerNaziv) ?>"
                 placeholder="Pretražite ili izaberite frizera…" autocomplete="off">
          <input type="hidden" name="frizer_naziv" id="zk-frizer-val" value="<?= htmlspecialchars($frizerNaziv) ?>">
          <ul class="zk-combo-list" role="listbox">
            <?php while ($row = $frizeri->fetch_assoc()): ?>
            <li role="option" data-val="<?= htmlspecialchars($row['Ime'].' '.$row['Prezime']) ?>">
              <?= htmlspecialchars($row['Ime'].' '.$row['Prezime']) ?>
            </li>
            <?php endwhile; ?>
          </ul>
        </div>
        <?php else: ?>
        <input type="hidden" name="frizer" value="<?= htmlspecialchars($frizer) ?>">
        <input type="text" value="<?= htmlspecialchars($frizerNaziv ?: $frizer) ?>" readonly>
        <input type="hidden" name="frizer_naziv" value="<?= htmlspecialchars($frizerNaziv) ?>">
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
        <?php if ($t1 == 1): ?>
          Pronađi termine
        <?php elseif ($nivo === 1): ?>
          Pošalji zahtev
        <?php else: ?>
          Potvrdi zakazivanje
        <?php endif; ?>
      </button>
    </form>
    <?php endif; ?>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));

  (function () {
    function initCombo(combo) {
      const textInput = combo.querySelector('.zk-combo-input');
      const hidden    = combo.querySelector('input[type="hidden"]');
      const list      = combo.querySelector('.zk-combo-list');
      const items     = Array.from(list.querySelectorAll('li'));

      function open()  { list.classList.add('open'); }
      function close() { list.classList.remove('open'); }

      function filter(q) {
        const lq = q.toLowerCase().trim();
        let any = false;
        items.forEach(li => {
          const match = li.textContent.trim().toLowerCase().includes(lq);
          li.hidden = !match;
          if (match) any = true;
        });
        return any;
      }

      textInput.addEventListener('focus', () => {
        filter(textInput.value);
        open();
      });

      textInput.addEventListener('input', () => {
        hidden.value = '';
        filter(textInput.value) ? open() : open();
      });

      items.forEach(li => {
        li.addEventListener('mousedown', e => {
          e.preventDefault();
          // data-label = display text; data-val = submitted value
          const label = li.dataset.label ?? li.dataset.val;
          textInput.value = label;
          hidden.value    = li.dataset.val;
          // sync companion naziv field if present
          const nazField = combo.querySelector('input[id$="-naziv"]');
          if (nazField) nazField.value = label;
          close();
        });
      });

      document.addEventListener('click', e => {
        if (!combo.contains(e.target)) {
          close();
          if (!hidden.value) textInput.value = '';
        }
      });

      textInput.addEventListener('keydown', e => {
        const visible = items.filter(li => !li.hidden);
        const active  = list.querySelector('.zk-active');
        let idx = visible.indexOf(active);
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (active) active.classList.remove('zk-active');
          visible[Math.min(idx + 1, visible.length - 1)]?.classList.add('zk-active');
          open();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (active) active.classList.remove('zk-active');
          visible[Math.max(idx - 1, 0)]?.classList.add('zk-active');
          open();
        } else if (e.key === 'Enter') {
          const sel = list.querySelector('.zk-active') || (visible.length === 1 ? visible[0] : null);
          if (sel) {
            e.preventDefault();
            textInput.value = sel.dataset.val;
            hidden.value    = sel.dataset.val;
            close();
          }
        } else if (e.key === 'Escape') {
          close();
        }
      });
    }

    document.querySelectorAll('.zk-combo').forEach(initCombo);

    // Client-side enforce selection
    const form = document.querySelector('form[method="post"]');
    if (form) {
      form.addEventListener('submit', e => {
        const checks = [
          { val: 'zk-korisnik-val', txt: 'zk-korisnik-text', combo: 'combo-korisnik' },
          { val: 'zk-usluga-val',   txt: 'zk-usluga-text',   combo: 'combo-usluga'   },
          { val: 'zk-frizer-val',   txt: 'zk-frizer-text',   combo: 'combo-frizer'   },
        ];
        for (const c of checks) {
          const valEl = document.getElementById(c.val);
          if (valEl && !valEl.value) {
            e.preventDefault();
            document.getElementById(c.txt)?.focus();
            document.getElementById(c.combo)?.querySelector('.zk-combo-list')?.classList.add('open');
            break;
          }
        }
      });
    }
  })();
</script>
</body>
</html>
