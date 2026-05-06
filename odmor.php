<?php
require_once 'sesija.php';
requireNivo('2');
include 'konekcija.php';

$nivo = (int)$_SESSION['nivo'];
$me   = $_SESSION['korisnik'];

$poruka  = '';
$success = '';

// Admin can view/manage any frizer; frizer sees only their own
$frizerFilter = ($nivo === 9) ? trim($_GET['frizer'] ?? '') : $me;

// ── Handle DELETE ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['odmor_id'] ?? 0);
    $row = null;
    if ($id > 0) {
        $sq = $conn->prepare("SELECT * FROM frizer_odmor WHERE OdmorId=?");
        $sq->bind_param('i', $id);
        $sq->execute();
        $row = $sq->get_result()->fetch_assoc();
        $sq->close();
    }
    if ($row && ($nivo === 9 || $row['KorisnikFrizerId'] === $me)) {
        $conn->prepare("DELETE FROM frizer_odmor WHERE OdmorId=?")->execute() ?: null;
        $sd = $conn->prepare("DELETE FROM frizer_odmor WHERE OdmorId=?");
        $sd->bind_param('i', $id);
        $sd->execute();
        $sd->close();
        $success = 'Odsustvo je obrisano.';
    } else {
        $poruka = 'Nije moguće obrisati odsustvo.';
    }
}

// ── Handle ADD ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $frizer    = ($nivo === 9) ? trim($_POST['frizer'] ?? '') : $me;
    $datumOd   = trim($_POST['datum_od'] ?? '');
    $datumDo   = trim($_POST['datum_do'] ?? '');
    $tip       = trim($_POST['tip'] ?? 'odmor');
    $napomena  = trim($_POST['napomena'] ?? '');
    $otkaziStr = trim($_POST['otkazi_termine'] ?? '0');
    $otkazi    = ($otkaziStr === '1');

    $validTips = ['odmor', 'slobodan_dan', 'bolovanje'];

    if ($frizer === '') {
        $poruka = 'Izaberite frizera.';
    } elseif ($datumOd === '' || $datumDo === '') {
        $poruka = 'Unesite datum od i datum do.';
    } elseif ($datumDo < $datumOd) {
        $poruka = 'Datum do mora biti isti ili posle datuma od.';
    } elseif (!in_array($tip, $validTips)) {
        $poruka = 'Neispravan tip odsustva.';
    } else {
        // Check for overlap with existing records
        $so = $conn->prepare(
            "SELECT COUNT(*) FROM frizer_odmor
             WHERE KorisnikFrizerId=? AND DatumOd <= ? AND DatumDo >= ?"
        );
        $so->bind_param('sss', $frizer, $datumDo, $datumOd);
        $so->execute();
        $overlap = (int)$so->get_result()->fetch_row()[0];
        $so->close();

        if ($overlap > 0) {
            $poruka = 'Frizer već ima odsustvo koje se preklapa sa izabranim periodom.';
        } else {
            // Count active (non-cancelled, non-done) appointments in the period
            $sc = $conn->prepare(
                "SELECT COUNT(*) FROM termin
                 WHERE KorisnikFrizerId=? AND Datum BETWEEN ? AND ?
                   AND Otkazano=0 AND Uradjeno=0 AND Potvrdjeno=1"
            );
            $sc->bind_param('sss', $frizer, $datumOd, $datumDo);
            $sc->execute();
            $brTermina = (int)$sc->get_result()->fetch_row()[0];
            $sc->close();

            // Also count pending (Potvrdjeno=0) appointments
            $sp2 = $conn->prepare(
                "SELECT COUNT(*) FROM termin
                 WHERE KorisnikFrizerId=? AND Datum BETWEEN ? AND ?
                   AND Otkazano=0 AND Uradjeno=0 AND Potvrdjeno=0"
            );
            $sp2->bind_param('sss', $frizer, $datumOd, $datumDo);
            $sp2->execute();
            $brZahteva = (int)$sp2->get_result()->fetch_row()[0];
            $sp2->close();

            $ukupno = $brTermina + $brZahteva;

            // If there are appointments and user hasn't confirmed yet — show warning
            if ($ukupno > 0 && !$otkazi && !isset($_POST['potvrda_otkazivanja'])) {
                // Re-render form with confirmation step (store data in session)
                $_SESSION['odmor_pending'] = compact(
                    'frizer','datumOd','datumDo','tip','napomena','brTermina','brZahteva'
                );
                // Show confirmation prompt (handled in form below)
                $poruka = '__confirm__';
            } else {
                // Save the leave
                $si = $conn->prepare(
                    "INSERT INTO frizer_odmor (KorisnikFrizerId, DatumOd, DatumDo, Tip, Napomena, OtkazanoTermina)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $otkazanoTermina = 0;
                if ($otkazi && $ukupno > 0) {
                    $su = $conn->prepare(
                        "UPDATE termin SET Otkazano=1
                         WHERE KorisnikFrizerId=? AND Datum BETWEEN ? AND ?
                           AND Otkazano=0 AND Uradjeno=0"
                    );
                    $su->bind_param('sss', $frizer, $datumOd, $datumDo);
                    $su->execute();
                    $otkazanoTermina = $su->affected_rows;
                    $su->close();
                }
                $si->bind_param('sssssi', $frizer, $datumOd, $datumDo, $tip, $napomena, $otkazanoTermina);
                $si->execute();
                $si->close();
                unset($_SESSION['odmor_pending']);

                $tipLabel = ['odmor' => 'Godišnji odmor', 'slobodan_dan' => 'Slobodan dan', 'bolovanje' => 'Bolovanje'];
                $msg = 'Odsustvo (' . $tipLabel[$tip] . ') je upisano.';
                if ($otkazanoTermina > 0)
                    $msg .= ' Otkazano je ' . $otkazanoTermina . ' termin' . ($otkazanoTermina === 1 ? '.' : 'a.');
                $success = $msg;
            }
        }
    }
}

// Load pending confirmation from session if set
$pending = $_SESSION['odmor_pending'] ?? null;
if ($poruka !== '__confirm__') $pending = null;

// ── Load frizeri list (admin only) ────────────────────
$frizerList = [];
if ($nivo === 9) {
    $fr = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    while ($row = $fr->fetch_assoc()) $frizerList[] = $row;
}

// ── Load odmor records ────────────────────────────────
$whFrizer = ($nivo === 9 && $frizerFilter === '') ? '1=1' : 'o.KorisnikFrizerId=?';
$qArgs    = ($nivo === 9 && $frizerFilter === '') ? [] : [$frizerFilter];

$sql = "SELECT o.*, k.Ime, k.Prezime
        FROM frizer_odmor o
        JOIN korisnik k ON o.KorisnikFrizerId = k.KorisnikId
        WHERE $whFrizer
        ORDER BY o.DatumOd DESC";
if ($qArgs) {
    $st = $conn->prepare($sql);
    $st->bind_param('s', ...$qArgs);
    $st->execute();
    $odmori = $st->get_result();
    $st->close();
} else {
    $odmori = $conn->query($sql);
}

$danas  = date('Y-m-d');
$tipLabel = ['odmor' => 'Godišnji odmor', 'slobodan_dan' => 'Slobodan dan', 'bolovanje' => 'Bolovanje'];
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
    <title>Odsustvo – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="kp-page">
    <header class="kp-header">
        <p class="ct-eyebrow">Frizerski salon</p>
        <h1 class="kp-title">Odsustvo</h1>
        <div class="ct-orn">
            <span></span>
            <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="2"/></svg>
            <span></span>
        </div>
    </header>

    <div class="pg-wrap">

        <?php if ($success): ?>
        <div class="ct-alert ct-alert--ok" style="margin-bottom:1.4rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($poruka && $poruka !== '__confirm__'): ?>
        <div class="ct-alert ct-alert--err" style="margin-bottom:1.4rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/></svg>
            <?= htmlspecialchars($poruka) ?>
        </div>
        <?php endif; ?>

        <!-- ── ADD FORM ───────────────────────────────── -->
        <div class="odmor-card">
            <h2 class="odmor-section-title">Novo odsustvo</h2>

            <?php if ($pending): ?>
            <!-- Confirmation step: appointments exist -->
            <div class="ct-alert ct-alert--warn" style="margin-bottom:1.2rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
                <div>
                    <strong>Pažnja!</strong> U izabranom periodu postoji
                    <?php
                        $parts = [];
                        if ($pending['brTermina'] > 0) $parts[] = $pending['brTermina'].' potvrđen'.($pending['brTermina']===1?'':'ih').' termin'.($pending['brTermina']===1?'':'a');
                        if ($pending['brZahteva'] > 0) $parts[] = $pending['brZahteva'].' zahtev'.($pending['brZahteva']===1?'':'a').' za termin';
                        echo implode(' i ', $parts) . '.';
                    ?>
                    Šta uraditi sa njima?
                </div>
            </div>

            <form method="post" class="auth-form" style="gap:1rem;">
                <input type="hidden" name="action"       value="add">
                <input type="hidden" name="frizer"       value="<?= htmlspecialchars($pending['frizer']) ?>">
                <input type="hidden" name="datum_od"     value="<?= htmlspecialchars($pending['datumOd']) ?>">
                <input type="hidden" name="datum_do"     value="<?= htmlspecialchars($pending['datumDo']) ?>">
                <input type="hidden" name="tip"          value="<?= htmlspecialchars($pending['tip']) ?>">
                <input type="hidden" name="napomena"     value="<?= htmlspecialchars($pending['napomena']) ?>">
                <input type="hidden" name="potvrda_otkazivanja" value="1">

                <div class="odmor-confirm-btns">
                    <button type="submit" name="otkazi_termine" value="1" class="odmor-btn odmor-btn--danger">
                        Otkaži termine i upiši odsustvo
                    </button>
                    <button type="submit" name="otkazi_termine" value="0" class="odmor-btn odmor-btn--secondary">
                        Zapiši odsustvo bez otkazivanja
                    </button>
                    <a href="odmor.php" class="odmor-btn odmor-btn--ghost">Poništi</a>
                </div>
            </form>

            <?php else: ?>

            <form method="post" class="odmor-add-form">
                <input type="hidden" name="action" value="add">

                <?php if ($nivo === 9): ?>
                <div class="ct-field">
                    <label>Frizer <span class="ct-req">*</span></label>
                    <select name="frizer" required>
                        <option value="">— Izaberite frizera —</option>
                        <?php foreach ($frizerList as $fr): ?>
                        <option value="<?= htmlspecialchars($fr['KorisnikId']) ?>">
                            <?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="odmor-date-row">
                    <div class="ct-field">
                        <label>Datum od <span class="ct-req">*</span></label>
                        <input type="date" name="datum_od" required min="<?= $danas ?>">
                    </div>
                    <div class="ct-field">
                        <label>Datum do <span class="ct-req">*</span></label>
                        <input type="date" name="datum_do" required min="<?= $danas ?>">
                    </div>
                </div>

                <div class="ct-field">
                    <label>Tip odsustva <span class="ct-req">*</span></label>
                    <select name="tip" id="tip-select">
                        <option value="odmor">Godišnji odmor</option>
                        <option value="slobodan_dan">Slobodan dan</option>
                        <option value="bolovanje">Bolovanje</option>
                    </select>
                </div>

                <div class="ct-field">
                    <label>Napomena</label>
                    <input type="text" name="napomena" maxlength="255" placeholder="Opcionalno…">
                </div>

                <button type="submit" class="auth-btn" style="align-self:flex-start;">Dodaj odsustvo</button>
            </form>

            <p class="odmor-hint">
                Ako u izabranom periodu postoje zakazani termini, sistem će vas pitati šta da uradi s njima pre nego što upiše odsustvo.
            </p>
            <?php endif; ?>
        </div>

        <!-- ── ADMIN FRIZER FILTER ────────────────────── -->
        <?php if ($nivo === 9 && count($frizerList) > 0): ?>
        <div class="pg-toolbar" style="margin-top:2rem;margin-bottom:0.5rem;">
            <form method="get" style="display:flex;align-items:center;gap:0.6rem;">
                <select name="frizer" class="filter-select" onchange="this.form.submit()">
                    <option value="">Svi frizeri</option>
                    <?php foreach ($frizerList as $fr): ?>
                    <option value="<?= htmlspecialchars($fr['KorisnikId']) ?>"
                        <?= $frizerFilter === $fr['KorisnikId'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── LIST ──────────────────────────────────── -->
        <div class="odmor-list" style="margin-top:<?= $nivo === 9 ? '0.8rem' : '2rem' ?>;">
            <h2 class="odmor-section-title">Upisana odsustva</h2>

            <?php
            $rows = [];
            while ($row = $odmori->fetch_assoc()) $rows[] = $row;
            ?>

            <?php if (count($rows) === 0): ?>
            <p class="today-empty">Nema upisanih odsustva<?= ($nivo===9 && $frizerFilter!=='') ? ' za ovog frizera' : '' ?>.</p>
            <?php else: ?>
            <div class="odmor-entries">
                <?php foreach ($rows as $r):
                    $isActive = $r['DatumDo'] >= $danas && $r['DatumOd'] <= $danas;
                    $isFuture = $r['DatumOd'] > $danas;
                    $isPast   = $r['DatumDo'] < $danas;
                    $tipKey   = $r['Tip'];
                    $statusClass = $isActive ? 'odmor-status--active' : ($isFuture ? 'odmor-status--future' : 'odmor-status--past');
                    $statusLabel = $isActive ? 'Aktivno' : ($isFuture ? 'Predstojeće' : 'Prošlo');
                ?>
                <div class="odmor-entry <?= $isPast ? 'odmor-entry--past' : '' ?>">
                    <div class="odmor-entry-left">
                        <span class="odmor-tip odmor-tip--<?= $tipKey ?>">
                            <?php if ($tipKey === 'odmor'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <?php elseif ($tipKey === 'slobodan_dan'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            <?php endif; ?>
                            <?= htmlspecialchars($tipLabel[$tipKey] ?? $tipKey) ?>
                        </span>

                        <?php if ($nivo === 9): ?>
                        <span class="odmor-frizer"><?= htmlspecialchars($r['Ime'].' '.$r['Prezime']) ?></span>
                        <?php endif; ?>

                        <span class="odmor-dates">
                            <?= date('d.m.Y.', strtotime($r['DatumOd'])) ?>
                            <?= $r['DatumOd'] !== $r['DatumDo'] ? ' — ' . date('d.m.Y.', strtotime($r['DatumDo'])) : '' ?>
                        </span>

                        <?php if ($r['Napomena']): ?>
                        <span class="odmor-napomena"><?= htmlspecialchars($r['Napomena']) ?></span>
                        <?php endif; ?>

                        <?php if ($r['OtkazanoTermina'] > 0): ?>
                        <span class="odmor-cancelled-badge">
                            <?= $r['OtkazanoTermina'] ?> termin<?= $r['OtkazanoTermina'] === 1 ? '' : 'a' ?> otkazan<?= $r['OtkazanoTermina'] === 1 ? '' : 'o' ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="odmor-entry-right">
                        <span class="odmor-status <?= $statusClass ?>"><?= $statusLabel ?></span>
                        <?php if (!$isPast): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Obrisati ovo odsustvo?');">
                            <input type="hidden" name="action"   value="delete">
                            <input type="hidden" name="odmor_id" value="<?= $r['OdmorId'] ?>">
                            <button type="submit" class="tbl-btn tbl-btn--red">Obriši</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));

  // Keep datum_do >= datum_od
  const od = document.querySelector('input[name="datum_od"]');
  const _do = document.querySelector('input[name="datum_do"]');
  if (od && _do) {
    od.addEventListener('change', () => {
      if (_do.value && _do.value < od.value) _do.value = od.value;
      _do.min = od.value;
    });
  }
</script>
</body>
</html>
