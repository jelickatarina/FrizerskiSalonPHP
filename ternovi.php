<?php
require_once 'sesija.php';
include 'konekcija.php';
requireNivo('1');

$nivo          = (int)$_SESSION['nivo'];
$poruka        = '';
$success       = false;
$t1            = 1;
$korisnik      = $nivo >= 2 ? '' : $_SESSION['korisnik'];
$korisnikNaziv = '';
$usluga        = '';
$datum         = '';
$vreme         = '';
$frizer        = '';
$frizeriDostupni = [];

// Returns list of frizeri who are free at $vremeSlot for $ukupnoTermina slots on $datum
function getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina) {
    $result = [];
    $q = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    if (!$q) return $result;
    while ($fr = $q->fetch_assoc()) {
        // Skip if on leave
        $sl = $conn->prepare("SELECT 1 FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=? LIMIT 1");
        $sl->bind_param('sss', $fr['KorisnikId'], $datum, $datum);
        $sl->execute();
        $slRow = $sl->get_result()->fetch_assoc();
        $sl->close();
        if ($slRow) continue;

        // Build occupied slot map
        $st = $conn->prepare(
            "SELECT t.Vreme, COALESCE(u.Trajanje, 30) AS Trajanje
             FROM termin t LEFT JOIN usluga u ON t.UslugaId=u.UslugaId
             WHERE t.KorisnikFrizerId=? AND t.Datum=?
               AND (t.Otkazano IS NULL OR t.Otkazano=0) AND t.Uradjeno=0"
        );
        $st->bind_param('ss', $fr['KorisnikId'], $datum);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $occ = [];
        foreach ($rows as $r) {
            $n = max(1, (int)ceil($r['Trajanje'] / 30));
            for ($i = 0; $i < $n; $i++) $occ[$r['Vreme'] + $i] = true;
        }

        $free = true;
        for ($i = 0; $i < $ukupnoTermina; $i++) {
            if (!empty($occ[$vremeSlot + $i])) { $free = false; break; }
        }
        if ($free) $result[] = $fr;
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t1_post       = (int)trim($_POST['t1'] ?? '1');
    $korisnik      = trim($_POST['korisnik']       ?? ($nivo >= 2 ? '' : $_SESSION['korisnik']));
    $korisnikNaziv = trim($_POST['korisnik_naziv'] ?? '');
    $usluga        = trim($_POST['usluga']         ?? '');
    $datum         = trim($_POST['datum']          ?? '');
    $vreme         = trim($_POST['vreme']          ?? '');

    // Pre-compute slot info (used by both steps)
    $vremeSlot     = 0;
    $ukupnoTermina = 1;
    if ($usluga !== '' && preg_match('/^\d{2}:\d{2}$/', $vreme)) {
        $sut = $conn->prepare("SELECT Trajanje FROM usluga WHERE UslugaId=?");
        $sut->bind_param('s', $usluga);
        $sut->execute();
        $sutRow = $sut->get_result()->fetch_assoc();
        $sut->close();
        if ($sutRow) {
            $ukupnoTermina = max(1, (int)ceil($sutRow['Trajanje'] / 30));
            $h = (int)substr($vreme, 0, 2);
            $m = (int)substr($vreme, 3, 2);
            $vremeSlot = $h * 2 + (int)($m / 30);
        }
    }

    if ($t1_post === 1) {
        // ── Step 1: validate inputs, find available frizeri ──────────
        $d1   = new DateTime(); $d1->setTime(0,0,0,0);
        $d2   = $datum ? new DateTime($datum) : null;
        $diff = $d2 ? (int)$d1->diff($d2)->format('%R%a') : -999;

        if ($nivo >= 2 && $korisnik === '')  $poruka = "Izaberite klijenta.";
        elseif ($usluga === '')              $poruka = "Niste izabrali uslugu.";
        elseif ($datum === '')               $poruka = "Unesite datum.";
        elseif ($vreme === '')               $poruka = "Izaberite vreme.";
        elseif ($d2 && date('w', strtotime($datum)) == 0) $poruka = "Ne radimo nedeljom.";
        elseif ($diff < 0)                   $poruka = "Datum je u prošlosti.";
        elseif ($diff > 7)                   $poruka = "Zakazivanje maksimalno 7 dana unapred.";
        elseif ($vremeSlot < 18 || $vremeSlot + $ukupnoTermina - 1 > 34)
                                             $poruka = "Vreme nije u radnom vremenu salona (09:00–17:00).";
        else {
            if ($diff === 0) {
                $now     = date("H:i");
                $ch      = (int)substr($now, 0, 2);
                $cm      = (int)substr($now, 3, 2);
                $curSlot = $ch * 2 + (int)($cm / 30);
                if ($vremeSlot <= $curSlot) $poruka = "Izabrani termin je u prošlosti.";
            }

            if (!$poruka && $nivo >= 2) {
                $ck = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=? AND Nivo=1");
                $ck->bind_param('s', $korisnik);
                $ck->execute();
                $kRow2 = $ck->get_result()->fetch_assoc();
                $ck->close();
                if (!$kRow2) $poruka = "Klijent nije pronađen. Izaberite iz liste.";
                else $korisnikNaziv = $kRow2['Ime'].' '.$kRow2['Prezime'];
            }

            if (!$poruka) {
                $frizeriDostupni = getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina);
                if (empty($frizeriDostupni)) {
                    $poruka = "Nema dostupnih frizera za " . date('d.m.Y.', strtotime($datum)) . " u " . $vreme . ". Pokušajte drugi termin.";
                } else {
                    $t1 = 2;
                }
            }
        }

    } else {
        // ── Step 2: book appointment ──────────────────────────────────
        $frizer = trim($_POST['frizer'] ?? '');

        if ($frizer === '') {
            $poruka = "Izaberite frizera.";
        } else {
            // Validate frizer exists
            $vfr = $conn->prepare("SELECT KorisnikId FROM korisnik WHERE KorisnikId=? AND Nivo=2");
            $vfr->bind_param('s', $frizer);
            $vfr->execute();
            $vfrOK = (bool)$vfr->get_result()->fetch_assoc();
            $vfr->close();

            if (!$vfrOK) {
                $poruka = "Izabrani frizer nije pronađen.";
            } else {
                // Re-check availability (race condition guard)
                $frizeriDostupni = getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina);
                $izabraniOK = false;
                foreach ($frizeriDostupni as $fd) {
                    if ($fd['KorisnikId'] === $frizer) { $izabraniOK = true; break; }
                }
                if (!$izabraniOK) {
                    $poruka = "Izabrani frizer više nije dostupan u tom terminu. Izaberite drugog.";
                }
            }
        }

        if (!$poruka) {
            // Calculate price
            $sp = $conn->prepare("SELECT Cena, Popust, PopustOd, PopustDo FROM usluga WHERE UslugaId=?");
            $sp->bind_param('s', $usluga);
            $sp->execute();
            $spRow = $sp->get_result()->fetch_assoc();
            $sp->close();
            $cenaNaplacena = null;
            if ($spRow) {
                $p = (int)($spRow['Popust'] ?? 0);
                $d0 = date('Y-m-d');
                $naAkciji = $p > 0 && !empty($spRow['PopustOd']) && !empty($spRow['PopustDo'])
                    && $d0 >= $spRow['PopustOd'] && $d0 <= $spRow['PopustDo'];
                $cenaNaplacena = $naAkciji ? round($spRow['Cena'] * (1 - $p / 100), 2) : (float)$spRow['Cena'];
            }

            $potvrdjeno = ($nivo === 1) ? 0 : 1;
            $stmt = $conn->prepare(
                "INSERT INTO termin (UslugaId, KorisnikId, Datum, Vreme, KorisnikFrizerId, Uradjeno, Potvrdjeno, CenaNaplacena)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?)"
            );
            $stmt->bind_param('sssssid', $usluga, $korisnik, $datum, $vremeSlot, $frizer, $potvrdjeno, $cenaNaplacena);
            $stmt->execute();
            $stmt->close();

            if ($nivo === 1) {
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
                        $usluga, $datum, $vreme
                    );
                }
                $success = true;
            } else {
                header('Location: termini.php');
                exit;
            }
        } else {
            // Error in step 2 — re-render step 2 if frizeri still available
            if (!empty($frizeriDostupni)) {
                $t1 = 2;
            }
            // else $t1 stays 1, user needs to pick a new slot
        }
    }
}

// Resolve client name for display
if ($korisnik !== '') {
    $sn = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
    $sn->bind_param('s', $korisnik);
    $sn->execute();
    $snRow = $sn->get_result()->fetch_assoc();
    $sn->close();
    if ($snRow) $korisnikNaziv = $snRow['Ime'].' '.$snRow['Prezime'];
}

// Queries for step 1 combos
$usluge   = $conn->query("SELECT UslugaId FROM usluga WHERE Aktivna=1 ORDER BY UslugaId");
$klijenti = ($nivo >= 2)
    ? $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=1 ORDER BY Ime, Prezime")
    : null;

// Time slot options 09:00–17:00
$timeSlots = [];
for ($s = 18; $s <= 34; $s++) {
    $timeSlots[] = sprintf('%02d:%02d', (int)($s/2), ($s%2)*30);
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
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      Zahtev je uspešno poslat! Frizer će ga pregledati i potvrditi.
    </div>
    <a href="termini.php" class="auth-btn" style="display:block;text-align:center;text-decoration:none;margin-top:1rem;">Moji termini</a>

    <?php else: ?>

    <?php if (!empty($poruka)): ?>
    <div class="ct-alert ct-alert--err">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/></svg>
      <?= htmlspecialchars($poruka) ?>
    </div>
    <?php endif; ?>

    <?php if ($t1 == 1): ?>
    <!-- ── STEP 1: Pick service, date, time ── -->
    <form method="post" class="auth-form">
      <input type="hidden" name="t1" value="1">

      <?php if ($nivo >= 2): ?>
      <div class="ct-field">
        <label for="zk-korisnik-text">Klijent <span class="ct-req">*</span></label>
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
      <?php else: ?>
      <div class="ct-field" style="display:none;">
        <input type="hidden" name="korisnik" value="<?= htmlspecialchars($korisnik) ?>">
      <?php endif; ?>
      </div>

      <div class="ct-field">
        <label for="zk-usluga-text">Usluga <span class="ct-req">*</span></label>
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
      </div>

      <div class="ct-field">
        <label for="zk-datum">Datum <span class="ct-req">*</span></label>
        <input type="date" id="zk-datum" name="datum"
               value="<?= htmlspecialchars($datum) ?>"
               min="<?= date('Y-m-d') ?>"
               max="<?= date('Y-m-d', strtotime('+7 days')) ?>">
      </div>

      <div class="ct-field">
        <label for="zk-vreme">Vreme <span class="ct-req">*</span></label>
        <select id="zk-vreme" name="vreme">
          <option value="">— Izaberite vreme —</option>
          <?php foreach ($timeSlots as $ts): ?>
          <option value="<?= $ts ?>" <?= $ts === $vreme ? 'selected' : '' ?>><?= $ts ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="auth-btn">Pronađi slobodne frizere</button>
    </form>

    <?php else: ?>
    <!-- ── STEP 2: Pick frizer from available list ── -->
    <div class="zk-booking-summary">
      <span><strong>Usluga:</strong> <?= htmlspecialchars($usluga) ?></span>
      <span><strong>Datum:</strong> <?= date('d.m.Y.', strtotime($datum)) ?></span>
      <span><strong>Vreme:</strong> <?= htmlspecialchars($vreme) ?></span>
      <?php if ($korisnikNaziv): ?>
      <span><strong>Klijent:</strong> <?= htmlspecialchars($korisnikNaziv) ?></span>
      <?php endif; ?>
    </div>

    <form method="post" class="auth-form">
      <input type="hidden" name="t1"            value="2">
      <input type="hidden" name="korisnik"       value="<?= htmlspecialchars($korisnik) ?>">
      <input type="hidden" name="korisnik_naziv" value="<?= htmlspecialchars($korisnikNaziv) ?>">
      <input type="hidden" name="usluga"         value="<?= htmlspecialchars($usluga) ?>">
      <input type="hidden" name="datum"          value="<?= htmlspecialchars($datum) ?>">
      <input type="hidden" name="vreme"          value="<?= htmlspecialchars($vreme) ?>">

      <div class="ct-field">
        <label>Izaberite frizera <span class="ct-req">*</span></label>
        <div class="zk-frizer-grid">
          <?php foreach ($frizeriDostupni as $fd): ?>
          <label class="zk-frizer-card">
            <input type="radio" name="frizer" value="<?= htmlspecialchars($fd['KorisnikId']) ?>"
                   <?= $fd['KorisnikId'] === $frizer ? 'checked' : '' ?> required>
            <div class="zk-frizer-card-body">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                   stroke-linecap="round" stroke-linejoin="round" width="28" height="28">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <span><?= htmlspecialchars($fd['Ime'].' '.$fd['Prezime']) ?></span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:0.7rem;flex-wrap:wrap;margin-top:0.4rem;">
        <button type="submit" class="auth-btn" style="flex:1;">
          <?= $nivo === 1 ? 'Pošalji zahtev' : 'Potvrdi zakazivanje' ?>
        </button>
        <a href="ternovi.php" class="odmor-btn odmor-btn--ghost"
           style="display:flex;align-items:center;padding:0.6rem 1.1rem;border-radius:6px;">
          ← Promeni termin
        </a>
      </div>
    </form>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
const nav = document.querySelector('.kn-nav');
window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));

// Hide past time slots when today is selected
(function () {
  const dateEl = document.getElementById('zk-datum');
  const timeEl = document.getElementById('zk-vreme');
  if (!dateEl || !timeEl) return;

  // Store all slots once
  const allSlots = <?= json_encode($timeSlots) ?>;

  function refreshTimes() {
    const today = new Date().toISOString().slice(0, 10);
    const isToday = dateEl.value === today;
    const now = new Date();
    const curSlot = now.getHours() * 2 + Math.floor(now.getMinutes() / 30);
    const currentVal = timeEl.value;

    // Rebuild options list, skipping past slots for today
    while (timeEl.options.length > 1) timeEl.remove(1);
    allSlots.forEach(ts => {
      const [h, m] = ts.split(':').map(Number);
      const slot = h * 2 + m / 30;
      if (isToday && slot <= curSlot) return;
      const opt = document.createElement('option');
      opt.value = ts;
      opt.textContent = ts;
      if (ts === currentVal) opt.selected = true;
      timeEl.appendChild(opt);
    });

    if (!timeEl.value) timeEl.selectedIndex = 0;
  }

  dateEl.addEventListener('change', refreshTimes);
  refreshTimes();
})();

// Combo box behaviour
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

    textInput.addEventListener('focus', () => { filter(textInput.value); open(); });
    textInput.addEventListener('input', () => { hidden.value = ''; filter(textInput.value); open(); });

    items.forEach(li => {
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        const label = li.dataset.label ?? li.dataset.val;
        textInput.value = label;
        hidden.value    = li.dataset.val;
        const nazField = combo.querySelector('input[id$="-naziv"]');
        if (nazField) nazField.value = label;
        close();
      });
    });

    document.addEventListener('click', e => {
      if (!combo.contains(e.target)) { close(); if (!hidden.value) textInput.value = ''; }
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
        if (sel) { e.preventDefault(); textInput.value = sel.dataset.label ?? sel.dataset.val; hidden.value = sel.dataset.val; close(); }
      } else if (e.key === 'Escape') { close(); }
    });
  }

  document.querySelectorAll('.zk-combo').forEach(initCombo);

  // Enforce combo selection on submit
  const form = document.querySelector('form[method="post"]');
  if (form && form.querySelector('input[name="t1"][value="1"]')) {
    form.addEventListener('submit', e => {
      const checks = [
        { val: 'zk-korisnik-val', txt: 'zk-korisnik-text', combo: 'combo-korisnik' },
        { val: 'zk-usluga-val',   txt: 'zk-usluga-text',   combo: 'combo-usluga'   },
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
