<?php
if (!isset($_REQUEST['p'])) { header('Location: termini.php'); exit; }

require_once 'sesija.php';
requireNivo('2');
include 'konekcija.php';
include_once 'email.php';

$terminId = (int)$_REQUEST['p'];
$nivo     = (int)$_SESSION['nivo'];
$me       = $_SESSION['korisnik'];

// Load appointment
$sel = $conn->prepare(
    "SELECT t.*, COALESCE(u.Trajanje, 30) AS Trajanje,
            k.Ime  AS KIme,  k.Prezime  AS KPrezime, k.Email AS KEmail,
            f.Ime  AS FIme,  f.Prezime  AS FPrezime
     FROM termin t
     LEFT JOIN usluga   u ON u.UslugaId   = t.UslugaId
     LEFT JOIN korisnik k ON k.KorisnikId = t.KorisnikId
     LEFT JOIN korisnik f ON f.KorisnikId = t.KorisnikFrizerId
     WHERE t.TerminId=? AND t.Otkazano=0 AND t.Uradjeno=0"
);
$sel->bind_param('i', $terminId);
$sel->execute();
$data = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$data) { header('Location: termini.php'); exit; }

// Frizer can only transfer their own appointment
if ($nivo === 2 && $data['KorisnikFrizerId'] !== $me) {
    header('Location: termini.php'); exit;
}

$vremeSlot    = (int)$data['Vreme'];
$ukupno       = max(1, (int)ceil((int)$data['Trajanje'] / 30));
$datum        = $data['Datum'];
$tv           = sprintf('%02d:%02d', (int)($vremeSlot/2), ($vremeSlot%2)*30);
$klijentNaziv = trim(($data['KIme'] ?? '').' '.($data['KPrezime'] ?? '')) ?: $data['KorisnikId'];
$frizerNaziv  = trim(($data['FIme'] ?? '').' '.($data['FPrezime'] ?? '')) ?: $data['KorisnikFrizerId'];

// Find frizeri who are free at this date+time, excluding current frizer
function slobodniFrizeri($conn, $datum, $slot, $ukupno, $izuzmiId) {
    $result = [];
    $q = $conn->query(
        "SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2
         AND KorisnikId != '" . $conn->real_escape_string($izuzmiId) . "'
         ORDER BY Ime, Prezime"
    );
    if (!$q) return $result;
    while ($fr = $q->fetch_assoc()) {
        $sl = $conn->prepare("SELECT 1 FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=? LIMIT 1");
        $sl->bind_param('sss', $fr['KorisnikId'], $datum, $datum);
        $sl->execute();
        $onLeave = (bool)$sl->get_result()->fetch_assoc();
        $sl->close();
        if ($onLeave) continue;

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
        for ($i = 0; $i < $ukupno; $i++) {
            if (!empty($occ[$slot + $i])) { $free = false; break; }
        }
        if ($free) $result[] = $fr;
    }
    return $result;
}

$poruka   = '';
$frizeri  = slobodniFrizeri($conn, $datum, $vremeSlot, $ukupno, $data['KorisnikFrizerId']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noviFrizerId  = trim($_POST['novi_frizer']     ?? '');
    $posaljiEmail  = !empty($_POST['posalji_email']);

    if ($noviFrizerId === '') {
        $poruka = 'Izaberite frizera.';
    } else {
        // Validate frizer is nivo=2
        $svf = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=? AND Nivo=2");
        $svf->bind_param('s', $noviFrizerId);
        $svf->execute();
        $nfRow = $svf->get_result()->fetch_assoc();
        $svf->close();

        if (!$nfRow) {
            $poruka = 'Frizer nije pronađen.';
        } else {
            // Re-validate availability (race condition guard)
            $dostupni = slobodniFrizeri($conn, $datum, $vremeSlot, $ukupno, $data['KorisnikFrizerId']);
            if (!in_array($noviFrizerId, array_column($dostupni, 'KorisnikId'))) {
                $poruka = 'Izabrani frizer više nije slobodan u ovom terminu.';
                $frizeri = $dostupni;
            } else {
                $upd = $conn->prepare("UPDATE termin SET KorisnikFrizerId=? WHERE TerminId=?");
                $upd->bind_param('si', $noviFrizerId, $terminId);
                $upd->execute();
                $upd->close();

                if ($posaljiEmail && !empty($data['KEmail'])) {
                    $noviNaziv = $nfRow['Ime'].' '.$nfRow['Prezime'];
                    emailPotvrdaZahteva($data['KEmail'], $klijentNaziv,
                        $data['UslugaId'], $datum, $tv, $noviNaziv);
                }

                header('Location: termini.php?prebacen=1');
                exit;
            }
        }
    }
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
    <title>Prebacivanje termina – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="kp-page">
    <header class="kp-header">
        <p class="ct-eyebrow">Frizerski salon</p>
        <h1 class="kp-title">Prebaci termin</h1>
        <div class="ct-orn">
            <span></span>
            <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="2"/></svg>
            <span></span>
        </div>
    </header>

    <div class="pg-wrap" style="max-width:520px;">

        <?php if ($poruka): ?>
        <div class="ct-alert ct-alert--err" style="margin-bottom:1.2rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/></svg>
            <?= htmlspecialchars($poruka) ?>
        </div>
        <?php endif; ?>

        <!-- Termin detalji -->
        <div class="odmor-card" style="margin-bottom:1.4rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 1.2rem;">
                <div class="ct-field" style="margin:0;">
                    <label style="font-size:.7rem;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;">Usluga</label>
                    <div style="font-family:'Jost',sans-serif;font-size:.88rem;color:rgba(255,255,255,.82);padding:.25rem 0;">
                        <?= htmlspecialchars($data['UslugaId']) ?>
                    </div>
                </div>
                <div class="ct-field" style="margin:0;">
                    <label style="font-size:.7rem;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;">Klijent</label>
                    <div style="font-family:'Jost',sans-serif;font-size:.88rem;color:rgba(255,255,255,.82);padding:.25rem 0;">
                        <?= htmlspecialchars($klijentNaziv) ?>
                    </div>
                </div>
                <div class="ct-field" style="margin:0;">
                    <label style="font-size:.7rem;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;">Datum i vreme</label>
                    <div style="font-family:'Jost',sans-serif;font-size:.88rem;color:rgba(255,255,255,.82);padding:.25rem 0;">
                        <?= htmlspecialchars(date('d.m.Y.', strtotime($datum))) ?> u <?= htmlspecialchars($tv) ?>
                    </div>
                </div>
                <div class="ct-field" style="margin:0;">
                    <label style="font-size:.7rem;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;">Trenutni frizer</label>
                    <div style="font-family:'Jost',sans-serif;font-size:.88rem;color:rgba(255,255,255,.82);padding:.25rem 0;">
                        <?= htmlspecialchars($frizerNaziv) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($frizeri)): ?>
        <div class="ct-alert ct-alert--warn" style="margin-bottom:1.4rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
            Nema dostupnih frizera za <?= htmlspecialchars(date('d.m.Y.', strtotime($datum))) ?> u <?= htmlspecialchars($tv) ?>.
        </div>
        <p class="auth-switch"><a href="termini.php">← Nazad na termine</a></p>

        <?php else: ?>
        <form method="post">
            <input type="hidden" name="p" value="<?= $terminId ?>">

            <p style="font-family:'Jost',sans-serif;font-size:.82rem;color:rgba(255,255,255,.45);margin:0 0 .9rem;">
                Slobodni frizeri za <?= htmlspecialchars(date('d.m.Y.', strtotime($datum))) ?> u <?= htmlspecialchars($tv) ?>:
            </p>

            <div class="zk-frizer-list" style="margin-bottom:1.2rem;">
            <?php foreach ($frizeri as $fr):
                $initials = mb_strtoupper(mb_substr($fr['Ime'], 0, 1) . mb_substr($fr['Prezime'], 0, 1));
            ?>
            <label class="zk-frizer-row">
                <input type="radio" name="novi_frizer" value="<?= htmlspecialchars($fr['KorisnikId']) ?>" required>
                <div class="zk-frizer-row-avatar"><?= htmlspecialchars($initials) ?></div>
                <span class="zk-frizer-row-name"><?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?></span>
                <div class="zk-frizer-row-check">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </label>
            <?php endforeach; ?>
            </div>

            <?php if (!empty($data['KEmail'])): ?>
            <label class="zk-frizer-row" style="margin-bottom:1.2rem;cursor:pointer;">
                <input type="checkbox" name="posalji_email" value="1" style="width:16px;height:16px;accent-color:#c4a76c;flex-shrink:0;">
                <span style="font-family:'Jost',sans-serif;font-size:.82rem;color:rgba(255,255,255,.65);">
                    Obavesti klijenta mejlom o novom frizeru
                </span>
            </label>
            <?php endif; ?>

            <div style="display:flex;gap:.7rem;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="auth-btn" style="flex:1;min-width:160px;">Potvrdi prebacivanje</button>
                <a href="termini.php" style="font-family:'Jost',sans-serif;font-size:.82rem;color:rgba(255,255,255,.38);text-decoration:none;">Otkaži</a>
            </div>
        </form>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  if (nav) window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));
</script>
</body>
</html>
