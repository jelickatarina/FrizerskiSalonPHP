<?php
require_once 'sesija.php';
requireNivo('2');
include 'konekcija.php';

$nivoLabel = ['0'=>'Neaktivan','1'=>'Klijent','2'=>'Frizer','9'=>'Admin'];
$nivoClass  = ['0'=>'badge--blocked','1'=>'badge--client','2'=>'badge--staff','9'=>'badge--admin'];

$q    = trim($_GET['q'] ?? '');
$uloga = $_GET['uloga'] ?? '';
$wh = "where Nivo>0";
if(in_array($uloga, ['0','1','2','9'])) $wh = "where Nivo=".(int)$uloga;
if($q !== '') {
  $esc = $conn->real_escape_string($q);
  $wh .= " and (KorisnikId like '%$esc%' or Ime like '%$esc%'
           or Prezime like '%$esc%' or Email like '%$esc%' or Telefon like '%$esc%')";
}

$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$total = $conn->query("select count(*) from korisnik $wh")->fetch_row()[0];
$pages = (int)ceil($total / $perPage);
$result = $conn->query("select * from korisnik $wh order by Ime, Prezime limit $perPage offset $offset");
$qParam = ($q !== '' ? '&q='.urlencode($q) : '').($uloga !== '' ? '&uloga='.urlencode($uloga) : '');
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
    <title>Korisnici</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="kp-page">
    <header class="kp-header">
        <p class="ct-eyebrow">Frizerski salon</p>
        <h1 class="kp-title">Korisnici</h1>
        <div class="ct-orn">
            <span></span>
            <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="2"/></svg>
            <span></span>
        </div>
    </header>
    <div class="pg-wrap">
        <div class="pg-toolbar">
            <form method="get" class="search-form">
                <input class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Pretraži po imenu, emailu, telefonu...">
                <select class="filter-select" name="uloga">
                    <option value="" <?= $uloga===''?'selected':'' ?>>Sve uloge</option>
                    <option value="1" <?= $uloga==='1'?'selected':'' ?>>Klijent</option>
                    <option value="2" <?= $uloga==='2'?'selected':'' ?>>Frizer</option>
                    <option value="9" <?= $uloga==='9'?'selected':'' ?>>Admin</option>
                    <option value="0" <?= $uloga==='0'?'selected':'' ?>>Neaktivni</option>
                </select>
                <button class="search-btn" type="submit">Traži</button>
            </form>
            <?php if($_SESSION['nivo']=='9') { ?>
            <a class="ct-btn" href="kornovi.php">+ Novi korisnik</a>
            <?php } ?>
        </div>
        <?php if($q !== '') { ?>
        <p class="search-info">Rezultati za: <strong><?= htmlspecialchars($q) ?></strong>
            (<?= $total ?>) — <a href="korisnici.php">Poništi pretragu</a></p>
        <?php } ?>
        <div class="cards-grid">
<?php
$rows = 0;
while($data=$result->fetch_assoc()) {
  $rows++;
  $initials = mb_substr($data['Ime'],0,1).mb_substr($data['Prezime'],0,1);
  $nivo = (string)$data['Nivo'];
  $label = $nivoLabel[$nivo] ?? $nivo;
  $cls   = $nivoClass[$nivo] ?? '';
?>
            <div class="usr-card">
                <div class="usr-card-top">
                    <div class="usr-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="usr-info">
                        <div class="usr-name"><?= htmlspecialchars($data['Ime'].' '.$data['Prezime']) ?></div>
                        <div class="usr-username">@<?= htmlspecialchars($data['KorisnikId']) ?></div>
                    </div>
                    <span class="usr-badge <?= $cls ?>"><?= $label ?></span>
                </div>
                <div class="usr-card-body">
                    <div class="usr-detail">
                        <span class="usr-detail-label">Email</span>
                        <span class="usr-detail-val"><?= htmlspecialchars($data['Email']) ?></span>
                    </div>
                    <div class="usr-detail">
                        <span class="usr-detail-label">Telefon</span>
                        <span class="usr-detail-val"><?= htmlspecialchars($data['Telefon']) ?></span>
                    </div>
                </div>
                <?php if($_SESSION['nivo']=='9') { ?>
                <a class="srv-card-edit" href="korizmeni.php?p=<?= urlencode($data['KorisnikId']) ?>">Izmeni</a>
                <?php } ?>
            </div>
<?php } ?>
<?php if($rows===0) { ?>
        <div class="ter-empty" style="grid-column:1/-1;">
            <svg class="ter-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <h3 class="ter-empty-title"><?= $q!==''?'Nema rezultata':($uloga==='0'?'Nema neaktivnih korisnika':'Nema korisnika') ?></h3>
            <p class="ter-empty-sub"><?= $q!==''?'Pokušajte sa drugačijim pojmom pretrage.':'Još uvek nema registrovanih korisnika.' ?></p>
        </div>
<?php } ?>
        </div>
        <?php if($pages > 1) { ?>
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
