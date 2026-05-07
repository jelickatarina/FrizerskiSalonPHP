<?php
require_once 'sesija.php';
include 'konekcija.php';
requireNivo('1');

$nivo             = (int)$_SESSION['nivo'];
$poruka           = '';
$success          = false;
$t1               = 1;
$korisnik         = $nivo >= 2 ? '' : $_SESSION['korisnik'];
$korisnikNaziv    = '';
$usluga           = '';
$datum            = '';
$vreme            = '';
$frizer           = '';
$vremeMode        = true;   // true = specific time chosen, false = any time
$frizeriDostupni  = [];     // vremeMode=true
$frizeriSaSlotovima = [];   // vremeMode=false

// First valid slot for a given date
function getTpoc($datum) {
    if ($datum !== date('Y-m-d')) return 18;
    $now = date('H:i');
    $ch  = (int)substr($now, 0, 2);
    $cm  = (int)substr($now, 3, 2);
    return max(18, $ch * 2 + (int)($cm / 30) + 1);
}

// Frizeri free at one specific slot
function getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina) {
    $result = [];
    $q = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    if (!$q) return $result;
    while ($fr = $q->fetch_assoc()) {
        $sl = $conn->prepare("SELECT 1 FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=? LIMIT 1");
        $sl->bind_param('sss', $fr['KorisnikId'], $datum, $datum);
        $sl->execute();
        $slRow = $sl->get_result()->fetch_assoc();
        $sl->close();
        if ($slRow) continue;

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

// Each frizer with all their free slots for the day
function getFrizeriSaSlotovima($conn, $datum, $ukupnoTermina) {
    $tpoc   = getTpoc($datum);
    $result = [];
    $q = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    if (!$q) return $result;
    while ($fr = $q->fetch_assoc()) {
        $sl = $conn->prepare("SELECT 1 FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=? LIMIT 1");
        $sl->bind_param('sss', $fr['KorisnikId'], $datum, $datum);
        $sl->execute();
        $slRow = $sl->get_result()->fetch_assoc();
        $sl->close();
        if ($slRow) continue;

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

        $freeSlots = [];
        for ($slot = $tpoc; $slot <= 34 - $ukupnoTermina; $slot++) {
            $free = true;
            for ($i = 0; $i < $ukupnoTermina; $i++) {
                if (!empty($occ[$slot + $i])) { $free = false; break; }
            }
            if ($free) $freeSlots[] = sprintf('%02d:%02d', (int)($slot/2), ($slot%2)*30);
        }

        if (!empty($freeSlots)) $result[] = ['frizer' => $fr, 'slots' => $freeSlots];
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
    $vremeMode     = (bool)(int)($_POST['vreme_fiksno'] ?? ($vreme !== '' ? 1 : 0));

    // Always compute service duration (needed by both modes)
    $ukupnoTermina = 1;
    if ($usluga !== '') {
        $sut = $conn->prepare("SELECT Trajanje FROM usluga WHERE UslugaId=?");
        $sut->bind_param('s', $usluga);
        $sut->execute();
        $sutRow = $sut->get_result()->fetch_assoc();
        $sut->close();
        if ($sutRow) $ukupnoTermina = max(1, (int)ceil($sutRow['Trajanje'] / 30));
    }

    // Compute slot number when vreme is set
    $vremeSlot = 0;
    if (preg_match('/^\d{2}:\d{2}$/', $vreme)) {
        $h = (int)substr($vreme, 0, 2);
        $m = (int)substr($vreme, 3, 2);
        $vremeSlot = $h * 2 + (int)($m / 30);
    }

    if ($t1_post === 1) {
        // ── Step 1 ──────────────────────────────────────────────────
        $d1   = new DateTime(); $d1->setTime(0,0,0,0);
        $d2   = $datum ? new DateTime($datum) : null;
        $diff = $d2 ? (int)$d1->diff($d2)->format('%R%a') : -999;

        if ($nivo >= 2 && $korisnik === '')  $poruka = "Izaberite klijenta.";
        elseif ($usluga === '')              $poruka = "Niste izabrali uslugu.";
        elseif ($datum === '')               $poruka = "Unesite datum.";
        elseif ($d2 && date('w', strtotime($datum)) == 0) $poruka = "Ne radimo nedeljom.";
        elseif ($diff < 0)                   $poruka = "Datum je u prošlosti.";
        elseif ($diff > 7)                   $poruka = "Zakazivanje maksimalno 7 dana unapred.";
        else {
            if ($vreme !== '') {
                // Validate specific time if provided
                if ($vremeSlot < 18 || $vremeSlot + $ukupnoTermina - 1 > 34)
                    $poruka = "Vreme nije u radnom vremenu salona (09:00–17:00).";
                elseif ($diff === 0 && $vremeSlot < getTpoc($datum))
                    $poruka = "Izabrani termin je u prošlosti.";
                $vremeMode = true;
            } else {
                $vremeMode = false;
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
                if ($vremeMode) {
                    $frizeriDostupni = getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina);
                    if (empty($frizeriDostupni))
                        $poruka = "Nema dostupnih frizera za " . date('d.m.Y.', strtotime($datum)) . " u " . $vreme . ".";
                    else $t1 = 2;
                } else {
                    $frizeriSaSlotovima = getFrizeriSaSlotovima($conn, $datum, $ukupnoTermina);
                    if (empty($frizeriSaSlotovima))
                        $poruka = "Nema slobodnih termina za " . date('d.m.Y.', strtotime($datum)) . ".";
                    else $t1 = 2;
                }
            }
        }

    } else {
        // ── Step 2: book ─────────────────────────────────────────────
        $frizer = trim($_POST['frizer'] ?? '');

        if ($frizer === '')     $poruka = "Izaberite frizera.";
        elseif ($vreme === '')  $poruka = "Izaberite termin.";
        else {
            $vfr = $conn->prepare("SELECT KorisnikId FROM korisnik WHERE KorisnikId=? AND Nivo=2");
            $vfr->bind_param('s', $frizer);
            $vfr->execute();
            $vfrOK = (bool)$vfr->get_result()->fetch_assoc();
            $vfr->close();

            if (!$vfrOK) {
                $poruka = "Izabrani frizer nije pronađen.";
            } else {
                $chk = getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina);
                $izabraniOK = false;
                foreach ($chk as $fd) {
                    if ($fd['KorisnikId'] === $frizer) { $izabraniOK = true; break; }
                }
                if (!$izabraniOK) $poruka = "Izabrani frizer više nije dostupan u tom terminu.";
            }
        }

        if (!$poruka) {
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
            // Re-render step 2 on error
            if ($vremeMode) {
                $frizeriDostupni = getFrizeriDostupni($conn, $datum, $vremeSlot, $ukupnoTermina);
                if (!empty($frizeriDostupni)) $t1 = 2;
            } else {
                $frizeriSaSlotovima = getFrizeriSaSlotovima($conn, $datum, $ukupnoTermina);
                if (!empty($frizeriSaSlotovima)) $t1 = 2;
            }
        }
    }
}

// Resolve client name
if ($korisnik !== '') {
    $sn = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
    $sn->bind_param('s', $korisnik);
    $sn->execute();
    $snRow = $sn->get_result()->fetch_assoc();
    $sn->close();
    if ($snRow) $korisnikNaziv = $snRow['Ime'].' '.$snRow['Prezime'];
}

$usluge   = $conn->query("SELECT UslugaId FROM usluga WHERE Aktivna=1 ORDER BY UslugaId");
$klijenti = ($nivo >= 2)
    ? $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=1 ORDER BY Ime, Prezime")
    : null;

$timeSlots = [];
for ($s = 18; $s <= 34; $s++) $timeSlots[] = sprintf('%02d:%02d', (int)($s/2), ($s%2)*30);
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
    <!-- ── STEP 1 ── -->
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
        <label for="zk-vreme">Vreme <span style="font-size:0.72rem;opacity:0.55;font-weight:400;">(opcionalno)</span></label>
        <select id="zk-vreme" name="vreme">
          <option value="">— Prikaži sve slobodne termine —</option>
          <?php foreach ($timeSlots as $ts): ?>
          <option value="<?= $ts ?>" <?= $ts === $vreme ? 'selected' : '' ?>><?= $ts ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="auth-btn">Pronađi slobodne frizere</button>
    </form>

    <?php else: ?>
    <!-- ── STEP 2 ── -->
    <div class="zk-booking-summary">
      <span><strong>Usluga:</strong> <?= htmlspecialchars($usluga) ?></span>
      <span><strong>Datum:</strong> <?= date('d.m.Y.', strtotime($datum)) ?></span>
      <?php if ($vreme && $vremeMode): ?>
      <span><strong>Vreme:</strong> <?= htmlspecialchars($vreme) ?></span>
      <?php endif; ?>
      <?php if ($korisnikNaziv): ?>
      <span><strong>Klijent:</strong> <?= htmlspecialchars($korisnikNaziv) ?></span>
      <?php endif; ?>
    </div>

    <form method="post" class="auth-form" id="form-step2">
      <input type="hidden" name="t1"            value="2">
      <input type="hidden" name="korisnik"       value="<?= htmlspecialchars($korisnik) ?>">
      <input type="hidden" name="korisnik_naziv" value="<?= htmlspecialchars($korisnikNaziv) ?>">
      <input type="hidden" name="usluga"         value="<?= htmlspecialchars($usluga) ?>">
      <input type="hidden" name="datum"          value="<?= htmlspecialchars($datum) ?>">
      <input type="hidden" name="vreme_fiksno"   value="<?= $vremeMode ? '1' : '0' ?>">

      <?php if ($vremeMode): ?>
      <!-- Fixed time: vreme pre-set, user picks frizer via radio -->
      <input type="hidden" name="vreme" value="<?= htmlspecialchars($vreme) ?>">
      <div class="ct-field">
        <label>Izaberite frizera <span class="ct-req">*</span></label>
        <div class="zk-frizer-list">
          <?php foreach ($frizeriDostupni as $fd): ?>
          <label class="zk-frizer-row">
            <input type="radio" name="frizer" value="<?= htmlspecialchars($fd['KorisnikId']) ?>"
                   <?= $fd['KorisnikId'] === $frizer ? 'checked' : '' ?> required>
            <div class="zk-frizer-row-avatar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                   stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
            <span class="zk-frizer-row-name"><?= htmlspecialchars($fd['Ime'].' '.$fd['Prezime']) ?></span>
            <div class="zk-frizer-row-check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                   stroke-linecap="round" stroke-linejoin="round" width="11" height="11">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php else: ?>
      <!-- Any time: user clicks a slot to pick frizer + time -->
      <input type="hidden" name="frizer" id="sel-frizer" value="<?= htmlspecialchars($frizer) ?>">
      <input type="hidden" name="vreme"  id="sel-vreme"  value="<?= htmlspecialchars($vreme) ?>">

      <div class="ct-field">
        <label>Izaberite termin i frizera <span class="ct-req">*</span></label>
        <div class="zk-slot-section" id="slot-section">
          <?php foreach ($frizeriSaSlotovima as $entry): ?>
          <?php
            $fId      = $entry['frizer']['KorisnikId'];
            $fNaziv   = $entry['frizer']['Ime'].' '.$entry['frizer']['Prezime'];
            $isActive = ($fId === $frizer);
          ?>
          <div class="zk-slot-frizer <?= $isActive ? 'zk-slot-frizer--active' : '' ?>"
               data-frizer="<?= htmlspecialchars($fId) ?>">
            <div class="zk-slot-frizer-header">
              <div class="zk-frizer-row-avatar" style="width:30px;height:30px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" width="15" height="15">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </div>
              <span><?= htmlspecialchars($fNaziv) ?></span>
            </div>
            <div class="zk-slot-times">
              <?php foreach ($entry['slots'] as $slot): ?>
              <button type="button" class="zk-slot-btn <?= ($isActive && $slot === $vreme) ? 'zk-slot-btn--active' : '' ?>"
                      data-frizer="<?= htmlspecialchars($fId) ?>"
                      data-vreme="<?= htmlspecialchars($slot) ?>">
                <?= htmlspecialchars($slot) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="slot-hint" style="margin-top:0.5rem;font-size:0.75rem;color:rgba(212,168,40,0.65);display:none;">
          Izabrano: <span id="slot-hint-txt"></span>
        </div>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:0.7rem;flex-wrap:wrap;margin-top:0.4rem;">
        <button type="submit" class="auth-btn" style="flex:1;" id="btn-submit">
          <?= $nivo === 1 ? 'Pošalji zahtev' : 'Potvrdi zakazivanje' ?>
        </button>
        <a href="ternovi.php" class="odmor-btn odmor-btn--ghost"
           style="display:flex;align-items:center;padding:0.6rem 1.1rem;border-radius:6px;">
          ← Promeni
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

// ── Slot-picker (any-time mode) ──────────────────────────────────
(function () {
  const selFrizer = document.getElementById('sel-frizer');
  const selVreme  = document.getElementById('sel-vreme');
  const hint      = document.getElementById('slot-hint');
  const hintTxt   = document.getElementById('slot-hint-txt');
  if (!selFrizer || !selVreme) return;

  // Restore visual state if frizer+vreme were pre-filled (re-render after error)
  if (selFrizer.value && selVreme.value) updateHint();

  document.querySelectorAll('.zk-slot-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.zk-slot-btn').forEach(b => b.classList.remove('zk-slot-btn--active'));
      document.querySelectorAll('.zk-slot-frizer').forEach(s => s.classList.remove('zk-slot-frizer--active'));
      this.classList.add('zk-slot-btn--active');
      this.closest('.zk-slot-frizer')?.classList.add('zk-slot-frizer--active');
      selFrizer.value = this.dataset.frizer;
      selVreme.value  = this.dataset.vreme;
      updateHint();
    });
  });

  function updateHint() {
    if (!hint || !hintTxt) return;
    if (selFrizer.value && selVreme.value) {
      const frizerEl = document.querySelector(`.zk-slot-frizer[data-frizer="${selFrizer.value}"] .zk-slot-frizer-header span`);
      const frizerNaziv = frizerEl ? frizerEl.textContent : '';
      hintTxt.textContent = selVreme.value + (frizerNaziv ? ' · ' + frizerNaziv : '');
      hint.style.display = 'block';
    } else {
      hint.style.display = 'none';
    }
  }

  // Validate on submit
  const form = document.getElementById('form-step2');
  if (form) {
    form.addEventListener('submit', e => {
      if (!selFrizer.value || !selVreme.value) {
        e.preventDefault();
        document.getElementById('slot-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('slot-section')?.classList.add('zk-slot-shake');
        setTimeout(() => document.getElementById('slot-section')?.classList.remove('zk-slot-shake'), 500);
      }
    });
  }
})();

// ── Hide past time slots for today ──────────────────────────────
(function () {
  const dateEl = document.getElementById('zk-datum');
  const timeEl = document.getElementById('zk-vreme');
  if (!dateEl || !timeEl) return;

  const allSlots = <?= json_encode($timeSlots) ?>;

  function refreshTimes() {
    const today = new Date().toISOString().slice(0, 10);
    const isToday = dateEl.value === today;
    const now = new Date();
    const curSlot = now.getHours() * 2 + Math.floor(now.getMinutes() / 30);
    const currentVal = timeEl.value;

    while (timeEl.options.length > 1) timeEl.remove(1);
    allSlots.forEach(ts => {
      const [h, m] = ts.split(':').map(Number);
      if (isToday && h * 2 + m / 30 <= curSlot) return;
      const opt = document.createElement('option');
      opt.value = ts; opt.textContent = ts;
      if (ts === currentVal) opt.selected = true;
      timeEl.appendChild(opt);
    });
    if (!timeEl.value) timeEl.selectedIndex = 0;
  }

  dateEl.addEventListener('change', refreshTimes);
  refreshTimes();
})();

// ── Combo boxes ──────────────────────────────────────────────────
(function () {
  function initCombo(combo) {
    const textInput = combo.querySelector('.zk-combo-input');
    const hidden    = combo.querySelector('input[type="hidden"]');
    const list      = combo.querySelector('.zk-combo-list');
    const items     = Array.from(list.querySelectorAll('li'));
    const open  = () => list.classList.add('open');
    const close = () => list.classList.remove('open');
    const filter = q => { const lq = q.toLowerCase().trim(); let any = false; items.forEach(li => { const m = li.textContent.trim().toLowerCase().includes(lq); li.hidden = !m; if (m) any = true; }); return any; };

    textInput.addEventListener('focus', () => { filter(textInput.value); open(); });
    textInput.addEventListener('input', () => { hidden.value = ''; filter(textInput.value); open(); });
    items.forEach(li => {
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        const label = li.dataset.label ?? li.dataset.val;
        textInput.value = label; hidden.value = li.dataset.val;
        const naz = combo.querySelector('input[id$="-naziv"]');
        if (naz) naz.value = label;
        close();
      });
    });
    document.addEventListener('click', e => { if (!combo.contains(e.target)) { close(); if (!hidden.value) textInput.value = ''; } });
    textInput.addEventListener('keydown', e => {
      const vis = items.filter(li => !li.hidden);
      const act = list.querySelector('.zk-active');
      let idx = vis.indexOf(act);
      if (e.key === 'ArrowDown') { e.preventDefault(); if (act) act.classList.remove('zk-active'); vis[Math.min(idx+1,vis.length-1)]?.classList.add('zk-active'); open(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (act) act.classList.remove('zk-active'); vis[Math.max(idx-1,0)]?.classList.add('zk-active'); open(); }
      else if (e.key === 'Enter') { const sel = list.querySelector('.zk-active')||(vis.length===1?vis[0]:null); if (sel){e.preventDefault();textInput.value=sel.dataset.label??sel.dataset.val;hidden.value=sel.dataset.val;close();} }
      else if (e.key === 'Escape') close();
    });
  }
  document.querySelectorAll('.zk-combo').forEach(initCombo);

  const form = document.querySelector('form[method="post"] input[name="t1"][value="1"]')?.closest('form');
  if (form) {
    form.addEventListener('submit', e => {
      for (const c of [{val:'zk-korisnik-val',txt:'zk-korisnik-text',combo:'combo-korisnik'},{val:'zk-usluga-val',txt:'zk-usluga-text',combo:'combo-usluga'}]) {
        const v = document.getElementById(c.val);
        if (v && !v.value) { e.preventDefault(); document.getElementById(c.txt)?.focus(); document.getElementById(c.combo)?.querySelector('.zk-combo-list')?.classList.add('open'); break; }
      }
    });
  }
})();
</script>
</body>
</html>
