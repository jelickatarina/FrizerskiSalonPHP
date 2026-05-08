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
$showU   = isset($_GET['uradjeni']);
$showO   = isset($_GET['otkazani']);

$validViews = ['danas', 'nedeljni', 'mesecni', 'svi', 'zahtevi'];
if (!in_array($view, $validViews)) $view = 'danas';
if ($view === 'zahtevi' && $nivo < 2) $view = 'danas';

$dow        = (int)date('N');
$weekStart  = date('Y-m-d', strtotime('-'.($dow-1).' days'));
$weekEnd    = date('Y-m-d', strtotime('+'.( 7-$dow).' days'));
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

if ($nivo === 1)     $baseWh = "t.KorisnikId='".$conn->real_escape_string($me)."'";
elseif ($nivo === 2) $baseWh = "t.KorisnikFrizerId='".$conn->real_escape_string($me)."'";
else                 $baseWh = "1=1";
if ($frizerF !== '') $baseWh .= " AND t.KorisnikFrizerId='".$conn->real_escape_string($frizerF)."'";

$joinK = "LEFT JOIN korisnik k ON t.KorisnikId=k.KorisnikId";

function searchClause($conn, $q, $inclDatum = false) {
    $esc = $conn->real_escape_string($q);
    $c = " AND (t.UslugaId LIKE '%$esc%' OR t.KorisnikId LIKE '%$esc%' OR t.KorisnikFrizerId LIKE '%$esc%'
              OR CONCAT(k.Ime,' ',k.Prezime) LIKE '%$esc%'";
    if ($inclDatum) $c .= " OR t.Datum LIKE '%$esc%'";
    return $c . ")";
}

$filterParts = ["(t.Potvrdjeno=1 AND t.Uradjeno=0 AND (t.Otkazano IS NULL OR t.Otkazano=0))"];
if ($showU) $filterParts[] = "(t.Potvrdjeno=1 AND t.Uradjeno=1 AND (t.Otkazano IS NULL OR t.Otkazano=0))";
if ($showO) $filterParts[] = "t.Otkazano=1";
$filterCond = "(" . implode(" OR ", $filterParts) . ")";

if ($view === 'danas')        $dateCond = "AND t.Datum='$danas'";
elseif ($view === 'nedeljni') $dateCond = "AND t.Datum BETWEEN '$weekStart' AND '$weekEnd'";
elseif ($view === 'mesecni')  $dateCond = "AND t.Datum BETWEEN '$monthStart' AND '$monthEnd'";
else                          $dateCond = '';

$perPage   = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page-1)*$perPage;
$total     = 0; $pages = 0;
$todayRows = []; $byDay = []; $result = null; $statsRows = [];

$cols = "SELECT t.*, k.Ime, k.Prezime, k.Email AS KEmail, k.Telefon AS KTel FROM termin t $joinK";

$statsSQL = "SELECT t.KorisnikFrizerId, k.Ime, k.Prezime,
                SUM(CASE WHEN t.Potvrdjeno=1 AND t.Uradjeno=0 AND (t.Otkazano IS NULL OR t.Otkazano=0) THEN 1 ELSE 0 END) AS zakazano,
                SUM(CASE WHEN t.Uradjeno=1 AND (t.Otkazano IS NULL OR t.Otkazano=0) THEN 1 ELSE 0 END) AS uradjeno,
                SUM(CASE WHEN t.Otkazano=1 THEN 1 ELSE 0 END) AS otkazano,
                COALESCE(SUM(CASE WHEN t.Uradjeno=1 AND (t.Otkazano IS NULL OR t.Otkazano=0) THEN t.CenaNaplacena ELSE 0 END),0) AS prihod
             FROM termin t LEFT JOIN korisnik k ON t.KorisnikFrizerId=k.KorisnikId
             WHERE %s AND (t.Potvrdjeno=1 OR t.Otkazano=1)
             GROUP BY t.KorisnikFrizerId, k.Ime, k.Prezime ORDER BY %s";

if ($view === 'danas') {
    $wh = "WHERE $baseWh $dateCond AND $filterCond";
    if ($q !== '') $wh .= searchClause($conn, $q, false);
    $res = $conn->query("$cols $wh ORDER BY t.Vreme");
    while ($d = $res->fetch_assoc()) $todayRows[] = $d;
    $total = count($todayRows);

} elseif ($view === 'nedeljni') {
    $wh = "WHERE $baseWh $dateCond AND $filterCond";
    if ($q !== '') $wh .= searchClause($conn, $q, false);
    $res = $conn->query("$cols $wh ORDER BY t.Datum, t.Vreme");
    while ($row = $res->fetch_assoc()) { $byDay[$row['Datum']][] = $row; $total++; }
    if ($nivo === 9) {
        $sQ = $conn->query(sprintf($statsSQL, "t.Datum BETWEEN '$weekStart' AND '$weekEnd'", "uradjeno DESC"));
        while ($s = $sQ->fetch_assoc()) $statsRows[] = $s;
    }

} elseif ($view === 'zahtevi') {
    $wh = "WHERE $baseWh AND t.Potvrdjeno=0 AND (t.Otkazano IS NULL OR t.Otkazano=0)";
    if ($q !== '') $wh .= searchClause($conn, $q, true);
    $total  = (int)$conn->query("SELECT COUNT(*) FROM termin t $joinK $wh")->fetch_row()[0];
    $pages  = (int)ceil($total/$perPage);
    $result = $conn->query("$cols $wh ORDER BY t.Datum, t.Vreme LIMIT $perPage OFFSET $offset");

} else {
    $wh = "WHERE $baseWh $dateCond AND $filterCond";
    if ($q !== '') $wh .= searchClause($conn, $q, true);
    $total    = (int)$conn->query("SELECT COUNT(*) FROM termin t $joinK $wh")->fetch_row()[0];
    $pages    = (int)ceil($total/$perPage);
    $orderDir = ($view === 'mesecni') ? 'ASC' : 'DESC';
    $result   = $conn->query("$cols $wh ORDER BY t.Datum $orderDir, t.Vreme LIMIT $perPage OFFSET $offset");
    if ($nivo === 9 && $view === 'mesecni') {
        $sQ = $conn->query(sprintf($statsSQL, "t.Datum BETWEEN '$monthStart' AND '$monthEnd'", "prihod DESC"));
        while ($s = $sQ->fetch_assoc()) $statsRows[] = $s;
    }
}

$frizerList = [];
if ($nivo === 9) {
    $fr = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime");
    while ($row = $fr->fetch_assoc()) $frizerList[] = $row;
}

$qParam = $q !== '' ? '&q='.urlencode($q) : '';
$fFr    = $frizerF !== '' ? '&frizer='.urlencode($frizerF) : '';
$fU     = $showU ? '&uradjeni=1' : '';
$fO     = $showO ? '&otkazani=1' : '';
$fAll   = $fFr.$fU.$fO;

$daniSr    = ['1'=>'Ponedeljak','2'=>'Utorak','3'=>'Sreda','4'=>'Četvrtak','5'=>'Petak','6'=>'Subota','7'=>'Nedelja'];
$meseciSr  = ['01'=>'Januar','02'=>'Februar','03'=>'Mart','04'=>'April','05'=>'Maj','06'=>'Jun',
               '07'=>'Jul','08'=>'Avgust','09'=>'Septembar','10'=>'Oktobar','11'=>'Novembar','12'=>'Decembar'];
$mesecStr  = ($meseciSr[date('m')] ?? date('m')).' '.date('Y').'.';
$uToggle   = "termini.php?view=$view$fFr$qParam".($showO?'&otkazani=1':'').(!$showU?'&uradjeni=1':'');
$oToggle   = "termini.php?view=$view$fFr$qParam".($showU?'&uradjeni=1':'').(!$showO?'&otkazani=1':'');
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

        <!-- TOOLBAR -->
        <div class="pg-toolbar ter-search-wrap">
            <form method="get" class="search-form" style="flex:1;min-width:0;">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <?php if ($frizerF !== ''): ?><input type="hidden" name="frizer" value="<?= htmlspecialchars($frizerF) ?>"><?php endif; ?>
                <?php if ($showU): ?><input type="hidden" name="uradjeni" value="1"><?php endif; ?>
                <?php if ($showO): ?><input type="hidden" name="otkazani" value="1"><?php endif; ?>
                <input class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Pretraži po usluzi, korisniku<?= $view !== 'danas' ? ', datumu' : '' ?>...">
                <button class="search-btn" type="submit">Traži</button>
            </form>
            <?php if ($nivo === 9 && count($frizerList) > 0): ?>
            <form method="get" style="display:flex;align-items:center;gap:0.5rem;">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                <?php if ($showU): ?><input type="hidden" name="uradjeni" value="1"><?php endif; ?>
                <?php if ($showO): ?><input type="hidden" name="otkazani" value="1"><?php endif; ?>
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

        <?php if (!empty($_GET['err'])): ?>
        <div class="ct-alert ct-alert--err" style="margin-bottom:1rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/></svg>
            <?php if ($_GET['err'] === 'prebaci_notfound'): ?>Termin nije pronađen.
            <?php elseif ($_GET['err'] === 'prebaci_denied'): ?>Nemate dozvolu da prebacite ovaj termin.
            <?php else: ?><?= htmlspecialchars($_GET['err']) ?><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="ter-tabs">
            <a class="ter-tab <?= $view==='danas'?'ter-tab--active':'' ?>" href="termini.php?view=danas<?= $fAll.$qParam ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Danas
            </a>
            <a class="ter-tab <?= $view==='nedeljni'?'ter-tab--active':'' ?>" href="termini.php?view=nedeljni<?= $fAll.$qParam ?>">Nedeljni</a>
            <a class="ter-tab <?= $view==='mesecni'?'ter-tab--active':'' ?>" href="termini.php?view=mesecni<?= $fAll.$qParam ?>">Mesečni</a>
            <a class="ter-tab <?= $view==='svi'?'ter-tab--active':'' ?>" href="termini.php?view=svi<?= $fAll.$qParam ?>">Svi</a>
            <?php if ($nivo >= 2): ?>
            <a class="ter-tab <?= $view==='zahtevi'?'ter-tab--active':'' ?>" href="termini.php?view=zahtevi<?= $fFr.$qParam ?>">Zahtevi</a>
            <?php endif; ?>
        </div>

        <!-- FILTER CHIPS -->
        <?php if ($view !== 'zahtevi'): ?>
        <div class="ter-filter-row">
            <a class="ter-filter-chip <?= $showU?'ter-filter-chip--on':'' ?>" href="<?= $uToggle ?>">
                <?php if ($showU): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                Urađeni
            </a>
            <a class="ter-filter-chip <?= $showO?'ter-filter-chip--on':'' ?>" href="<?= $oToggle ?>">
                <?php if ($showO): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                Otkazani
            </a>
        </div>
        <?php endif; ?>

        <?php if ($q !== ''): ?>
        <p class="search-info">
            <?= $total ?> rezultata za „<?= htmlspecialchars($q) ?>"
            — <a href="termini.php?view=<?= $view ?><?= $fAll ?>">Poništi</a>
        </p>
        <?php endif; ?>

        <!-- ADMIN STATS PANEL -->
        <?php if ($nivo === 9 && $statsRows && in_array($view, ['nedeljni','mesecni'])): ?>
        <div class="stats-panel">
            <div class="stats-panel-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <?php if ($view === 'nedeljni'): ?>
                Nedelja <?= date('d.m.', strtotime($weekStart)) ?> — <?= date('d.m.Y.', strtotime($weekEnd)) ?>
                <?php else: ?>
                <?= $mesecStr ?>
                <?php endif; ?>
            </div>
            <div class="stats-grid">
            <?php foreach ($statsRows as $s):
                $prihodFmt = number_format((float)$s['prihod'], 0, '.', '.');
            ?>
            <div class="stats-card">
                <div class="stats-card-name"><?= htmlspecialchars($s['Ime'].' '.$s['Prezime']) ?></div>
                <div class="stats-card-metrics">
                    <div class="stats-metric">
                        <span class="stats-metric-val"><?= (int)$s['zakazano'] ?></span>
                        <span class="stats-metric-lbl">Zakazano</span>
                    </div>
                    <div class="stats-metric stats-metric--done">
                        <span class="stats-metric-val"><?= (int)$s['uradjeno'] ?></span>
                        <span class="stats-metric-lbl">Urađeno</span>
                    </div>
                    <div class="stats-metric stats-metric--cancel">
                        <span class="stats-metric-val"><?= (int)$s['otkazano'] ?></span>
                        <span class="stats-metric-lbl">Otkazano</span>
                    </div>
                    <div class="stats-metric stats-metric--revenue">
                        <span class="stats-metric-val"><?= $prihodFmt ?></span>
                        <span class="stats-metric-lbl">RSD prihod</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── DANAS ───────────────────────────────── -->
        <?php if ($view === 'danas'): ?>
        <div class="today-section">
            <div class="today-header">
                <?php $danSr = $daniSr[(string)(int)date('N')]; ?>
                <div class="today-title"><?= date('d.m.Y.') ?> — <?= $danSr ?></div>
                <span class="today-count"><?= count($todayRows) ?> termin<?= count($todayRows)===1?'':'a' ?></span>
            </div>
            <?php if (count($todayRows) === 0): ?>
            <div class="ter-empty">
                <svg class="ter-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" stroke-width="2.2"/></svg>
                <h3 class="ter-empty-title"><?= $q!==''?'Nema rezultata':'Nema termina' ?></h3>
                <p class="ter-empty-sub"><?= $q!==''?'Nema termina za ovu pretragu.':($showU||$showO?'Nema termina za izabrane filtere.':'Nema zakazanih termina za danas.') ?></p>
                <?php if ($q==='' && !$showU && !$showO && $nivo===1): ?>
                <a href="ternovi.php" class="ct-btn" style="margin-top:0.6rem;">+ Zakaži termin</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="today-cards">
                <?php foreach ($todayRows as $row):
                    $tv   = sprintf('%02d:%02d', (int)($row['Vreme']/2), ($row['Vreme']%2)*30);
                    $done = $row['Uradjeno'] == 1;
                    $canc = $row['Otkazano'] == 1;
                    $displayName = ($row['Ime'] && $row['Prezime']) ? $row['Ime'].' '.$row['Prezime'] : $row['KorisnikId'];
                ?>
                <div class="today-card <?= $done?'today-card--done':'' ?> <?= $canc?'today-card--cancelled':'' ?>">
                    <div class="today-card-time"><?= $tv ?></div>
                    <div class="today-card-info">
                        <div class="today-card-usluga"><?= htmlspecialchars($row['UslugaId']) ?></div>
                        <div class="today-card-meta">
                            <?php if ($nivo !== 1): ?>
                            <span><?= htmlspecialchars($displayName) ?></span>
                            <?php if ($row['KTel'] || $row['KEmail']): ?>
                            <span class="tbl-contact" style="display:inline-flex;">
                                <?php if ($row['KTel']): ?><a href="tel:<?= htmlspecialchars($row['KTel']) ?>"><?= htmlspecialchars($row['KTel']) ?></a><?php endif; ?>
                                <?php if ($row['KEmail']): ?><a href="mailto:<?= htmlspecialchars($row['KEmail']) ?>"><?= htmlspecialchars($row['KEmail']) ?></a><?php endif; ?>
                            </span>
                            <?php endif; ?>
                            <span class="today-card-dot">·</span>
                            <?php endif; ?>
                            <span>Frizer: <?= htmlspecialchars($row['KorisnikFrizerId']) ?></span>
                            <?php if ($canc): ?>
                            <span class="ter-status-chip ter-status-chip--cancel">Otkazano</span>
                            <?php elseif ($done): ?>
                            <span class="ter-status-chip ter-status-chip--done">Urađeno</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$done && !$canc): ?>
                    <div class="today-card-actions">
                        <?php if ($nivo >= 2): ?><a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?= $row['TerminId'] ?>">Urađeno</a><?php endif; ?>
                        <?php if ($nivo >= 1): ?><a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $row['TerminId'] ?>">Otkaži</a><?php endif; ?>
                        <?php if ($nivo >= 2): ?><a class="tbl-btn" href="terprebaci.php?p=<?= $row['TerminId'] ?>">Prebaci</a><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── NEDELJNI (grouped by day) ────────────── -->
        <?php elseif ($view === 'nedeljni'): ?>
        <div class="ter-week">
            <?php
            $calDay = new DateTime($weekStart);
            for ($wi = 0; $wi < 7; $wi++, $calDay->modify('+1 day')):
                $dk      = $calDay->format('Y-m-d');
                $dayRows = $byDay[$dk] ?? [];
                $isToday = $dk === $danas;
                $isPast  = $dk < $danas;
                $dayNum  = $calDay->format('N');
            ?>
            <div class="ter-day-group <?= $isToday?'ter-day-group--today':'' ?> <?= ($isPast&&!$dayRows)?'ter-day-group--past':'' ?>">
                <div class="ter-day-header">
                    <span class="ter-day-name"><?= $daniSr[$dayNum] ?></span>
                    <span class="ter-day-date"><?= $calDay->format('d.m.') ?></span>
                    <?php if ($dayRows): ?>
                    <span class="ter-day-count"><?= count($dayRows) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($dayRows): ?>
                <div class="today-cards">
                    <?php foreach ($dayRows as $row):
                        $tv   = sprintf('%02d:%02d', (int)($row['Vreme']/2), ($row['Vreme']%2)*30);
                        $done = $row['Uradjeno'] == 1;
                        $canc = $row['Otkazano'] == 1;
                        $displayName = ($row['Ime'] && $row['Prezime']) ? $row['Ime'].' '.$row['Prezime'] : $row['KorisnikId'];
                    ?>
                    <div class="today-card <?= $done?'today-card--done':'' ?> <?= $canc?'today-card--cancelled':'' ?>">
                        <div class="today-card-time"><?= $tv ?></div>
                        <div class="today-card-info">
                            <div class="today-card-usluga"><?= htmlspecialchars($row['UslugaId']) ?></div>
                            <div class="today-card-meta">
                                <?php if ($nivo !== 1): ?>
                                <span><?= htmlspecialchars($displayName) ?></span>
                                <span class="today-card-dot">·</span>
                                <?php endif; ?>
                                <span>Frizer: <?= htmlspecialchars($row['KorisnikFrizerId']) ?></span>
                                <?php if ($canc): ?>
                                <span class="ter-status-chip ter-status-chip--cancel">Otkazano</span>
                                <?php elseif ($done): ?>
                                <span class="ter-status-chip ter-status-chip--done">Urađeno</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$done && !$canc): ?>
                        <div class="today-card-actions">
                            <?php if ($nivo >= 2): ?><a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?= $row['TerminId'] ?>">Urađeno</a><?php endif; ?>
                            <?php if ($nivo >= 1): ?><a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $row['TerminId'] ?>">Otkaži</a><?php endif; ?>
                            <?php if ($nivo >= 2): ?><a class="tbl-btn" href="terprebaci.php?p=<?= $row['TerminId'] ?>">Prebaci</a><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="ter-day-empty">Nema termina</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <!-- ── ZAHTEVI ────────────────────────────────── -->
        <?php elseif ($view === 'zahtevi'): ?>
        <div class="tbl-wrap" style="margin-top:1.2rem;">
            <table>
                <thead>
                    <tr><th>#</th><th>Usluga</th><th>Klijent</th><th>Datum</th><th>Vreme</th><th>Frizer</th><th>Cena</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php $rows = 0; while ($data = $result->fetch_assoc()): $rows++;
                    $tv = sprintf('%02d:%02d', (int)($data['Vreme']/2), ($data['Vreme']%2)*30);
                    $displayName = ($data['Ime'] && $data['Prezime']) ? $data['Ime'].' '.$data['Prezime'] : $data['KorisnikId'];
                ?>
                    <tr>
                        <td data-label="#"><?= $data['TerminId'] ?></td>
                        <td data-label="Usluga"><?= htmlspecialchars($data['UslugaId']) ?></td>
                        <td data-label="Klijent">
                            <div><?= htmlspecialchars($displayName) ?></div>
                            <?php if ($data['KTel'] || $data['KEmail']): ?>
                            <div class="tbl-contact">
                                <?php if ($data['KTel']): ?><a href="tel:<?= htmlspecialchars($data['KTel']) ?>"><?= htmlspecialchars($data['KTel']) ?></a><?php endif; ?>
                                <?php if ($data['KEmail']): ?><a href="mailto:<?= htmlspecialchars($data['KEmail']) ?>"><?= htmlspecialchars($data['KEmail']) ?></a><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Datum"><?= htmlspecialchars($data['Datum']) ?></td>
                        <td data-label="Vreme"><?= $tv ?></td>
                        <td data-label="Frizer"><?= htmlspecialchars($data['KorisnikFrizerId']) ?></td>
                        <td data-label="Cena"><?= $data['CenaNaplacena'] !== null ? number_format((float)$data['CenaNaplacena'],0,'.','.') . ' RSD' : '—' ?></td>
                        <td data-label="Status"><span class="srv-badge srv-badge--pending">Čeka odobrenje</span></td>
                        <td data-label=""><div class="tbl-actions">
                            <a class="tbl-btn tbl-btn--green" href="potvrdi.php?p=<?= $data['TerminId'] ?>">Potvrdi</a>
                            <a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $data['TerminId'] ?>">Odbij</a>
                        </div></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($rows === 0): ?>
                    <tr><td colspan="9" class="tbl-empty"><div class="ter-empty">
                        <svg class="ter-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" stroke-width="2.2"/></svg>
                        <h3 class="ter-empty-title"><?= $q!==''?'Nema rezultata':'Nema zahteva' ?></h3>
                        <p class="ter-empty-sub"><?= $q!==''?'Nema zahteva za ovu pretragu.':'Nema novih zahteva za odobrenje.' ?></p>
                    </div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="pg-pagination">
            <?php if ($page > 1): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page-1 ?><?= $qParam.$fFr ?>">&larr;</a><?php endif; ?>
            <?php for ($i=1;$i<=$pages;$i++): ?><a class="pg-page <?= $i==$page?'pg-page--active':'' ?>" href="?view=<?= $view ?>&page=<?= $i ?><?= $qParam.$fFr ?>"><?= $i ?></a><?php endfor; ?>
            <?php if ($page < $pages): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page+1 ?><?= $qParam.$fFr ?>">&rarr;</a><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── MESECNI / SVI — TABLE ─────────────────── -->
        <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1.2rem;">
            <table>
                <thead>
                    <tr>
                        <?php if ($nivo !== 1): ?><th>#</th><?php endif; ?>
                        <th>Usluga</th>
                        <?php if ($nivo !== 1): ?><th>Klijent</th><?php endif; ?>
                        <th>Datum</th><th>Vreme</th><th>Frizer</th><th>Cena</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php $rows = 0; while ($data = $result->fetch_assoc()): $rows++;
                    $tv   = sprintf('%02d:%02d', (int)($data['Vreme']/2), ($data['Vreme']%2)*30);
                    $done = $data['Uradjeno'] == 1;
                    $canc = $data['Otkazano'] == 1;
                    $displayName = ($data['Ime'] && $data['Prezime']) ? $data['Ime'].' '.$data['Prezime'] : $data['KorisnikId'];
                ?>
                    <tr class="<?= $done?'tr--done':'' ?> <?= $canc?'tr--cancelled':'' ?>">
                        <?php if ($nivo !== 1): ?><td data-label="#"><?= $data['TerminId'] ?></td><?php endif; ?>
                        <td data-label="Usluga"><?= htmlspecialchars($data['UslugaId']) ?></td>
                        <?php if ($nivo !== 1): ?>
                        <td data-label="Klijent">
                            <div><?= htmlspecialchars($displayName) ?></div>
                            <?php if ($data['KTel'] || $data['KEmail']): ?>
                            <div class="tbl-contact">
                                <?php if ($data['KTel']): ?><a href="tel:<?= htmlspecialchars($data['KTel']) ?>"><?= htmlspecialchars($data['KTel']) ?></a><?php endif; ?>
                                <?php if ($data['KEmail']): ?><a href="mailto:<?= htmlspecialchars($data['KEmail']) ?>"><?= htmlspecialchars($data['KEmail']) ?></a><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td data-label="Datum"><?= htmlspecialchars($data['Datum']) ?></td>
                        <td data-label="Vreme"><?= $tv ?></td>
                        <td data-label="Frizer"><?= htmlspecialchars($data['KorisnikFrizerId']) ?></td>
                        <td data-label="Cena"><?= $data['CenaNaplacena'] !== null ? number_format((float)$data['CenaNaplacena'],0,'.','.') . ' RSD' : '—' ?></td>
                        <td data-label="Status"><?php
                            if ($canc) echo '<span class="srv-badge srv-badge--off">Otkazano</span>';
                            elseif ($done) echo '<span class="srv-badge srv-badge--on">Urađeno</span>';
                            else echo '<span class="srv-badge srv-badge--pending">Zakazano</span>';
                        ?></td>
                        <td data-label=""><div class="tbl-actions">
                            <?php if (!$done && !$canc): ?>
                            <?php if ($nivo >= 1): ?><a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?= $data['TerminId'] ?>">Otkaži</a><?php endif; ?>
                            <?php if ($nivo >= 2): ?><a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?= $data['TerminId'] ?>">Urađeno</a><?php endif; ?>
                            <?php if ($nivo >= 2): ?><a class="tbl-btn" href="terprebaci.php?p=<?= $data['TerminId'] ?>">Prebaci</a><?php endif; ?>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($rows === 0): ?>
                    <tr><td colspan="<?= $nivo!==1?9:7 ?>" class="tbl-empty"><div class="ter-empty">
                        <svg class="ter-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" stroke-width="2.2"/></svg>
                        <h3 class="ter-empty-title"><?= $q!==''?'Nema rezultata':'Nema termina' ?></h3>
                        <p class="ter-empty-sub"><?= $q!==''?'Nema termina za ovu pretragu.':'U ovoj kategoriji nema termina.' ?></p>
                        <?php if ($q===''&&$nivo===1): ?><a href="ternovi.php" class="ct-btn" style="margin-top:0.6rem;">+ Zakaži termin</a><?php endif; ?>
                    </div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="pg-pagination">
            <?php if ($page > 1): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page-1 ?><?= $qParam.$fAll ?>">&larr;</a><?php endif; ?>
            <?php for ($i=1;$i<=$pages;$i++): ?><a class="pg-page <?= $i==$page?'pg-page--active':'' ?>" href="?view=<?= $view ?>&page=<?= $i ?><?= $qParam.$fAll ?>"><?= $i ?></a><?php endfor; ?>
            <?php if ($page < $pages): ?><a class="pg-page" href="?view=<?= $view ?>&page=<?= $page+1 ?><?= $qParam.$fAll ?>">&rarr;</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  if (nav) window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));
</script>
</body>
</html>
