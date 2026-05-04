<?php
require_once 'sesija.php';
if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'1'))
  header('Location: nemaovlascenje.html');
include 'konekcija.php';

$danas = date('Y-m-d');

// Termini danas (samo za ulogovanog korisnika)
if($_SESSION['nivo']==1)
  $whDanas = "where KorisnikId='".$conn->real_escape_string($_SESSION['korisnik'])."' and Datum='$danas'";
else if($_SESSION['nivo']==2)
  $whDanas = "where KorisnikFrizerId='".$conn->real_escape_string($_SESSION['korisnik'])."' and Datum='$danas'";
else
  $whDanas = "where Datum='$danas'";
$resDanas = $conn->query("select * from termin $whDanas order by Vreme");

// Svi termini sa filterom
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$perPage = 15;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

if($_SESSION['nivo']==1)      $wh = "where KorisnikId='".$conn->real_escape_string($_SESSION['korisnik'])."'";
else if($_SESSION['nivo']==2) $wh = "where KorisnikFrizerId='".$conn->real_escape_string($_SESSION['korisnik'])."'";
else                           $wh = "where 1=1";
if($status==='ceka')    $wh .= " and Uradjeno=0";
if($status==='uradjeno') $wh .= " and Uradjeno=1";
if($q!=='') {
  $esc = $conn->real_escape_string($q);
  $wh .= " and (UslugaId like '%$esc%' or KorisnikId like '%$esc%' or KorisnikFrizerId like '%$esc%' or Datum like '%$esc%')";
}
$total  = $conn->query("select count(*) from termin $wh")->fetch_row()[0];
$pages  = (int)ceil($total / $perPage);
$result = $conn->query("select * from termin $wh order by Datum desc, Vreme limit $perPage offset $offset");
$qParam = ($q!==''?'&q='.urlencode($q):'').($status!==''?'&status='.urlencode($status):'');
?>
<!DOCTYPE html>
<html lang="en">
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

        <!-- DANAS -->
        <div class="today-section">
            <div class="today-header">
                <div class="today-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Danas — <?= date('d.m.Y.') ?>
                </div>
                <?php if($_SESSION['nivo']>='1') { ?>
                <a class="ct-btn" href="ternovi.php" style="font-size:0.62rem;padding:0.45rem 1.1rem;">+ Zakaži</a>
                <?php } ?>
            </div>
            <?php
            $todayRows = [];
            while($d = $resDanas->fetch_assoc()) $todayRows[] = $d;
            if(count($todayRows) === 0) { ?>
            <p class="today-empty">Nema zakazanih termina za danas.</p>
            <?php } else { ?>
            <div class="today-cards">
                <?php foreach($todayRows as $d) {
                    $tv = sprintf('%02d:%02d', (int)($d['Vreme']/2), ($d['Vreme']%2)*30);
                    $done = $d['Uradjeno']==1;
                ?>
                <div class="today-card <?= $done ? 'today-card--done' : '' ?>">
                    <div class="today-card-time"><?= $tv ?></div>
                    <div class="today-card-info">
                        <div class="today-card-usluga"><?= htmlspecialchars($d['UslugaId']) ?></div>
                        <div class="today-card-meta">
                            <?php if($_SESSION['nivo']!=1) { ?>
                            <span><?= htmlspecialchars($d['KorisnikId']) ?></span>
                            <span class="today-card-dot">·</span>
                            <?php } ?>
                            <span>Frizer: <?= htmlspecialchars($d['KorisnikFrizerId']) ?></span>
                        </div>
                    </div>
                    <div class="today-card-actions">
                        <?= $done
                            ? '<span class="srv-badge srv-badge--on">Urađeno</span>'
                            : '<span class="srv-badge srv-badge--off">Čeka</span>' ?>
                        <?php if(!$done && $_SESSION['nivo']>='2') { ?>
                        <a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?=$d['TerminId']?>">Urađeno</a>
                        <?php } ?>
                        <?php if(!$done && $_SESSION['nivo']>='1') { ?>
                        <a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?=$d['TerminId']?>">Otkaži</a>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>

        <!-- SVI TERMINI -->
        <div class="today-title" style="margin-bottom:1rem;">Svi termini</div>
        <div class="pg-toolbar">
            <form method="get" class="search-form">
                <input class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Pretraži po usluzi, korisniku, datumu...">
                <select class="filter-select" name="status">
                    <option value="" <?= $status===''?'selected':'' ?>>Svi statusi</option>
                    <option value="ceka" <?= $status==='ceka'?'selected':'' ?>>Čeka</option>
                    <option value="uradjeno" <?= $status==='uradjeno'?'selected':'' ?>>Urađeno</option>
                </select>
                <button class="search-btn" type="submit">Traži</button>
            </form>
        </div>
        <?php if($q!==''||$status!=='') { ?>
        <p class="search-info">
            <?= $total ?> rezultata
            — <a href="termini.php">Poništi</a>
        </p>
        <?php } ?>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Usluga</th><th>Korisnik</th>
                        <th>Datum</th><th>Vreme</th><th>Frizer</th>
                        <th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
<?php
$rows = 0;
while($data=$result->fetch_assoc()) {
  $rows++;
  $tv = sprintf('%02d:%02d', (int)($data['Vreme']/2), ($data['Vreme']%2)*30);
  $done = $data['Uradjeno']==1;
?>
                    <tr class="<?= $done?'tr--done':'' ?>">
                        <td><?=$data['TerminId']?></td>
                        <td><?= htmlspecialchars($data['UslugaId']) ?></td>
                        <td><?= htmlspecialchars($data['KorisnikId']) ?></td>
                        <td><?=$data['Datum']?></td>
                        <td><?=$tv?></td>
                        <td><?= htmlspecialchars($data['KorisnikFrizerId']) ?></td>
                        <td><?= $done
                            ? '<span class="srv-badge srv-badge--on">Urađeno</span>'
                            : '<span class="srv-badge srv-badge--off">Čeka</span>' ?></td>
                        <td><div class="tbl-actions">
                            <?php if(!$done&&$_SESSION['nivo']>='1') { ?>
                                <a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?=$data['TerminId']?>">Otkaži</a>
                            <?php } ?>
                            <?php if(!$done&&$_SESSION['nivo']>='2') { ?>
                                <a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?=$data['TerminId']?>">Urađeno</a>
                            <?php } ?>
                        </div></td>
                    </tr>
<?php } ?>
<?php if($rows===0) { ?>
                    <tr><td colspan="8" class="tbl-empty">Nema termina<?= $q!==''?' za ovu pretragu':'' ?></td></tr>
<?php } ?>
                </tbody>
            </table>
        </div>
        <?php if($pages>1) { ?>
        <div class="pg-pagination">
            <?php if($page>1) { ?><a class="pg-page" href="?page=<?=$page-1?><?=$qParam?>">&larr;</a><?php } ?>
            <?php for($i=1;$i<=$pages;$i++) { ?>
            <a class="pg-page <?=$i==$page?'pg-page--active':''?>" href="?page=<?=$i?><?=$qParam?>"><?=$i?></a>
            <?php } ?>
            <?php if($page<$pages) { ?><a class="pg-page" href="?page=<?=$page+1?><?=$qParam?>">&rarr;</a><?php } ?>
        </div>
        <?php } ?>
    </div>
</div>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
