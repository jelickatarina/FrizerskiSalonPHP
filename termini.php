<?php
require_once 'sesija.php';
requireNivo('1');
include 'konekcija.php';

$danas = date('Y-m-d');
$nivo  = (int)$_SESSION['nivo'];
$me    = $_SESSION['korisnik'];

$view    = $_GET['view'] ?? 'danas';
$q       = trim($_GET['q'] ?? '');
$frizerF = ($nivo === 9) ? trim($_GET['frizer'] ?? '') : '';

// Base WHERE per role (using table alias t)
if ($nivo === 1)      $baseWh = "t.KorisnikId='".$conn->real_escape_string($me)."'";
elseif ($nivo === 2)  $baseWh = "t.KorisnikFrizerId='".$conn->real_escape_string($me)."'";
else                  $baseWh = "1=1";

// Admin frizer filter
if ($frizerF !== '') $baseWh .= " AND t.KorisnikFrizerId='".$conn->real_escape_string($frizerF)."'";

$joinK = "LEFT JOIN korisnik k ON t.KorisnikId=k.KorisnikId";

// Search clause helper
function searchClause($esc, $inclDatum = false) {
    $c = " AND (t.UslugaId LIKE '%$esc%' OR t.KorisnikId LIKE '%$esc%' OR t.KorisnikFrizerId LIKE '%$esc%'
              OR CONCAT(k.Ime,' ',k.Prezime) LIKE '%$esc%'";
    if ($inclDatum) $c .= " OR t.Datum LIKE '%$esc%'";
    return $c . ")";
}

// --- DANAS tab ---
if ($view === 'danas') {
    $wh = "WHERE $baseWh AND t.Datum='$danas' AND t.Potvrdjeno=1 AND t.Uradjeno=0 AND (t.Otkazano IS NULL OR t.Otkazano=0)";
    if ($q !== '') {
        $esc = $conn->real_escape_string($q);
        $wh .= searchClause($esc, false);
    }
    $resDanas  = $conn->query("SELECT t.*, k.Ime, k.Prezime, k.Email AS KEmail, k.Telefon AS KTel FROM termin t $joinK $wh ORDER BY t.Vreme");
    $todayRows = [];
    while ($d = $resDanas->fetch_assoc()) $todayRows[] = $d;
}

// --- ZAHTEVI tab ---
if ($view === 'zahtevi') {
    $perPage = 15;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $offset  = ($page-1)*$perPage;
    $wh = "WHERE $baseWh AND t.Potvrdjeno=0 AND (t.Otkazano IS NULL OR t.Otkazano=0)";
    if ($q !== '') {
        $esc = $conn->real_escape_string($q);
        $wh .= searchClause($esc, true);
    }
    $total  = $conn->query("SELECT COUNT(*) FROM termin t $joinK $wh")->fetch_row()[0];
    $pages  = (int)ceil($total/$perPage);
    $result = $conn->query("SELECT t.*, k.Ime, k.Prezime, k.Email AS KEmail, k.Telefon AS KTel FROM termin t $joinK $wh ORDER BY t.Datum, t.Vreme LIMIT $perPage OFFSET $offset");
    $qParam = $q !== '' ? '&q='.urlencode($q) : '';
}

// --- OTHER tabs (Svi / Zakazani / Urađeni) ---
if (!in_array($view, ['danas', 'zahtevi'])) {
    $perPage = 15;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $offset  = ($page-1)*$perPage;
    if ($view === 'svi')      $wh = "WHERE $baseWh AND (t.Potvrdjeno=1 OR t.Otkazano=1)";
    else                      $wh = "WHERE $baseWh AND t.Potvrdjeno=1";
    if ($view === 'zakazani') $wh .= " AND t.Uradjeno=0 AND (t.Otkazano IS NULL OR t.Otkazano=0)";
    if ($view === 'uradjeni') $wh .= " AND t.Uradjeno=1";
    if ($q !== '') {
        $esc = $conn->real_escape_string($q);
        $wh .= searchClause($esc, true);
    }
    $total  = $conn->query("SELECT COUNT(*) FROM termin t $joinK $wh")->fetch_row()[0];
    $pages  = (int)ceil($total/$perPage);
    $result = $conn->query("SELECT t.*, k.Ime, k.Prezime, k.Email AS KEmail, k.Telefon AS KTel FROM termin t $joinK $wh ORDER BY t.Datum DESC, t.Vreme LIMIT $perPage OFFSET $offset");
    $qParam = $q !== '' ? '&q='.urlencode($q) : '';
}

// Admin: load frizer list for filter dropdown
$frizerList = [];
if ($nivo === 9) {
    $fr = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime");
    while ($row = $fr->fetch_assoc()) $frizerList[] = $row;
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
    <title>Termini</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="kp-page">
    <header class="kp-header">
        <p class="ct-eyebrow">Frizerski salon</p>
        <h1 class="kp-title">Termini</h1>
        <div class="ct-orn">
            <span></span>
            <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="2"/></svg>
            <span></span>
        </div>
    </header>
    <div class="pg-wrap">

        <!-- TOOLBAR: search + frizer filter + CTA -->
        <div class="pg-toolbar ter-search-wrap">
            <form method="get" class="search-form" style="flex:1;min-width:0;">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <?php if ($frizerF !== ''): ?>
                <input type="hidden" name="frizer" value="<?= htmlspecialchars($frizerF) ?>">
                <?php endif; ?>
                <input class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Pretraži po usluzi, korisniku<?= $view !== 'danas' ? ', datumu' : '' ?>...">
                <button class="search-btn" type="submit">Traži</button>
            </form>
            <?php if ($nivo === 9 && count($frizerList) > 0): ?>
            <form method="get" style="display:flex;align-items:center;gap:0.5rem;">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                <select name="frizer" class="filter-select" onchange="this.form.submit()">
                    <option value="">Svi frizeri</option>
                    <?php foreach ($frizerList as $fr): ?>
                    <option value="<?= htmlspecialchars($fr['KorisnikId']) ?>"
                        <?= $frizerF === $fr['KorisnikId'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <a class="ct-btn" href="ternovi.php">+ Zakaži termin</a>
        </div>

        <?php if ($q !== ''): ?>
        <p class="search-info">
            <?= $view === 'danas' ? count($todayRows) : $total ?> rezultata za „<?= htmlspecialchars($q) ?>"
            — <a href="termini.php?view=<?= $view ?><?= $frizerF !== '' ? '&frizer='.urlencode($frizerF) : '' ?>">Poništi</a>
        </p>
        <?php endif; ?>

        <!-- TABS -->
        <?php
        $fQ  = $q !== '' ? '&q='.urlencode($q) : '';
        $fFr = $frizerF !== '' ? '&frizer='.urlencode($frizerF) : '';
        ?>
        <div class="ter-tabs">
            <a class="ter-tab <?= $view==='danas'?'ter-tab--active':'' ?>" href="termini.php?view=danas<?= $fQ.$fFr ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Danas
            </a>
            <?php if ($nivo >= 2): ?>
            <a class="ter-tab <?= $view==='zahtevi'?'ter-tab--active':'' ?>" href="termini.php?view=zahtevi<?= $fQ.$fFr ?>">Zahtevi</a>
            <?php endif; ?>
            <a class="ter-tab <?= $view==='svi'?'ter-tab--active':'' ?>" href="termini.php?view=svi<?= $fQ.$fFr ?>">Svi</a>
            <a class="ter-tab <?= $view==='zakazani'?'ter-tab--active':'' ?>" href="termini.php?view=zakazani<?= $fQ.$fFr ?>">Zakazani</a>
            <a class="ter-tab <?= $view==='uradjeni'?'ter-tab--active':'' ?>" href="termini.php?view=uradjeni<?= $fQ.$fFr ?>">Urađeni</a>
        </div>

        <!-- ── DANAS ─────────────────────────────────── -->
        <?php if ($view === 'danas'): ?>
        <div class="today-section">
            <div class="today-header">
                <div class="today-title"><?= date('d.m.Y.') ?> — <?= date('l') ?></div>
                <span class="today-count"><?= count($todayRows) ?> termin<?= count($todayRows)===1?'':'a' ?></span>
            </div>
            <?php if (count($todayRows) === 0): ?>
            <p class="today-empty">Nema zakazanih termina za danas<?= $q !== '' ? ' za ovu pretragu' : '' ?>.</p>
            <?php else: ?>
            <div class="today-cards">
                <?php foreach ($todayRows as $d):
                    $tv   = sprintf('%02d:%02d', (int)($d['Vreme']/2), ($d['Vreme']%2)*30);
                    $done = $d['Uradjeno'] == 1;
                    $displayName = ($d['Ime'] && $d['Prezime']) ? $d['Ime'].' '.$d['Prezime'] : $d['KorisnikId'];
                ?>
                <div class="today-card <?= $done ? 'today-card--done' : '' ?>">
                    <div class="today-card-time"><?= $tv ?></div>
                    <div class="today-card-info">
                        <div class="today-card-usluga"><?= htmlspecialchars($d['UslugaId']) ?></div>
                        <div class="today-card-meta">
                            <?php if ($nivo !== 1): ?>
                            <span><?= htmlspecialchars($displayName) ?></span>
                            <?php if ($d['KTel'] || $d['KEmail']): ?>
                            <span class="tbl-contact" style="display:inline-flex;">
                                <?php if ($d['KTel']): ?><a href="tel:<?= htmlspecialchars($d['KTel']) ?>"><?= htmlspecialchars($d['KTel']) ?></a><?php endif; ?>
                                <?php if ($d['KEmail']): ?><a href="mailto:<?= htmlspecialchars($d['KEmail']) ?>"><?= htmlspecialchars($d['KEmail']) ?></a><?php endif; ?>
                            </span>
                            <?php endif; ?>
                            <span class="today-card-dot">·</span>
                            <?php endif; ?>
                            <span>Frizer: <?= htmlspecialchars($d['KorisnikFrizerId']) ?></span>
                        </div>
                    </div>
                    <div class="today-card-actions">
                        <?php if ($nivo >= 2): ?><a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?= $d['TerminId'] ?>">Urađeno</a><?php endif; ?>
                        <?php if ($nivo >= 1): ?><a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $d['TerminId'] ?>">Otkaži</a><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── ZAHTEVI ────────────────────────────────── -->
        <?php elseif ($view === 'zahtevi'): ?>
        <div class="tbl-wrap" style="margin-top:1.2rem;">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Usluga</th><th>Klijent</th>
                        <th>Datum</th><th>Vreme</th><th>Frizer</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rows = 0;
                while ($data = $result->fetch_assoc()):
                    $rows++;
                    $tv   = sprintf('%02d:%02d', (int)($data['Vreme']/2), ($data['Vreme']%2)*30);
                    $displayName = ($data['Ime'] && $data['Prezime']) ? $data['Ime'].' '.$data['Prezime'] : $data['KorisnikId'];
                ?>
                    <tr>
                        <td><?= $data['TerminId'] ?></td>
                        <td><?= htmlspecialchars($data['UslugaId']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($displayName) ?></div>
                            <?php if ($data['KTel'] || $data['KEmail']): ?>
                            <div class="tbl-contact">
                                <?php if ($data['KTel']): ?><a href="tel:<?= htmlspecialchars($data['KTel']) ?>"><?= htmlspecialchars($data['KTel']) ?></a><?php endif; ?>
                                <?php if ($data['KEmail']): ?><a href="mailto:<?= htmlspecialchars($data['KEmail']) ?>"><?= htmlspecialchars($data['KEmail']) ?></a><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($data['Datum']) ?></td>
                        <td><?= $tv ?></td>
                        <td><?= htmlspecialchars($data['KorisnikFrizerId']) ?></td>
                        <td><span class="srv-badge srv-badge--pending">Čeka odobrenje</span></td>
                        <td><div class="tbl-actions">
                            <a class="tbl-btn tbl-btn--green" href="potvrdi.php?p=<?= $data['TerminId'] ?>">Potvrdi</a>
                            <a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $data['TerminId'] ?>">Odbij</a>
                        </div></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($rows === 0): ?>
                    <tr><td colspan="8" class="tbl-empty">Nema zahteva<?= $q !== '' ? ' za ovu pretragu' : '' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($pages) && $pages > 1): ?>
        <div class="pg-pagination">
            <?php if ($page > 1): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page-1 ?><?= $qParam.$fFr ?>">&larr;</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="pg-page <?= $i == $page ? 'pg-page--active' : '' ?>" href="?view=<?= $view ?>&page=<?= $i ?><?= $qParam.$fFr ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page+1 ?><?= $qParam.$fFr ?>">&rarr;</a><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── SVI / ZAKAZANI / URAĐENI ─────────────── -->
        <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1.2rem;">
            <table>
                <thead>
                    <tr>
                        <?php if ($nivo !== 1): ?><th>#</th><?php endif; ?>
                        <th>Usluga</th>
                        <?php if ($nivo !== 1): ?><th>Klijent</th><?php endif; ?>
                        <th>Datum</th><th>Vreme</th><th>Frizer</th>
                        <th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rows = 0;
                while ($data = $result->fetch_assoc()):
                    $rows++;
                    $tv        = sprintf('%02d:%02d', (int)($data['Vreme']/2), ($data['Vreme']%2)*30);
                    $done      = $data['Uradjeno'] == 1;
                    $cancelled = $data['Otkazano'] == 1;
                    $displayName = ($data['Ime'] && $data['Prezime']) ? $data['Ime'].' '.$data['Prezime'] : $data['KorisnikId'];
                ?>
                    <tr class="<?= $done ? 'tr--done' : '' ?>">
                        <?php if ($nivo !== 1): ?><td><?= $data['TerminId'] ?></td><?php endif; ?>
                        <td><?= htmlspecialchars($data['UslugaId']) ?></td>
                        <?php if ($nivo !== 1): ?>
                        <td>
                            <div><?= htmlspecialchars($displayName) ?></div>
                            <?php if ($data['KTel'] || $data['KEmail']): ?>
                            <div class="tbl-contact">
                                <?php if ($data['KTel']): ?><a href="tel:<?= htmlspecialchars($data['KTel']) ?>"><?= htmlspecialchars($data['KTel']) ?></a><?php endif; ?>
                                <?php if ($data['KEmail']): ?><a href="mailto:<?= htmlspecialchars($data['KEmail']) ?>"><?= htmlspecialchars($data['KEmail']) ?></a><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($data['Datum']) ?></td>
                        <td><?= $tv ?></td>
                        <td><?= htmlspecialchars($data['KorisnikFrizerId']) ?></td>
                        <td><?php
                            if ($cancelled) echo '<span class="srv-badge srv-badge--off">Otkazano</span>';
                            elseif ($done)  echo '<span class="srv-badge srv-badge--on">Urađeno</span>';
                            else            echo '<span class="srv-badge srv-badge--off">Čeka</span>';
                        ?></td>
                        <td><div class="tbl-actions">
                            <?php if (!$done && !$cancelled && $nivo >= 1): ?><a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $data['TerminId'] ?>">Otkaži</a><?php endif; ?>
                            <?php if (!$done && !$cancelled && $nivo >= 2): ?><a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?= $data['TerminId'] ?>">Urađeno</a><?php endif; ?>
                        </div></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($rows === 0): ?>
                    <tr><td colspan="<?= $nivo !== 1 ? 8 : 6 ?>" class="tbl-empty">Nema termina<?= $q !== '' ? ' za ovu pretragu' : '' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($pages) && $pages > 1): ?>
        <div class="pg-pagination">
            <?php if ($page > 1): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page-1 ?><?= $qParam.$fFr ?>">&larr;</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="pg-page <?= $i == $page ? 'pg-page--active' : '' ?>" href="?view=<?= $view ?>&page=<?= $i ?><?= $qParam.$fFr ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page+1 ?><?= $qParam.$fFr ?>">&rarr;</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));
</script>
</body>
</html>
