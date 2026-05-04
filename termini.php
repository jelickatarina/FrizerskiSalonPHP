<?php
  session_start();
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'1'))
    header('Location: nemaovlascenje.html');
  include 'konekcija.php';
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
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Termin</th>
                        <th>Usluga</th>
                        <th>Korisnik</th>
                        <th>Datum</th>
                        <th>Vreme</th>
                        <th>Frizer</th>
                        <th>Urađeno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
<?php
  if($_SESSION['nivo']==1) $wh1=" where KorisnikId='".$_SESSION['korisnik']."' ";
  else if($_SESSION['nivo']==2) $wh1=" where KorisnikFrizerId='".$_SESSION['korisnik']."' ";
  else $wh1=" ";
  $sql = "select * from termin".$wh1."order by Datum,Vreme";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc()) {
    $tmp1=$data['Vreme']/2;
    $tmp2=($data['Vreme'] % 2) * 30;
    $tv=sprintf('%02d:%02d', $tmp1,$tmp2);
?>
                    <tr>
                        <td><?=$data['TerminId']?></td>
                        <td><?=$data['UslugaId']?></td>
                        <td><?=$data['KorisnikId']?></td>
                        <td><?=$data['Datum']?></td>
                        <td><?=$tv?></td>
                        <td><?=$data['KorisnikFrizerId']?></td>
                        <td><input disabled <?=($data['Uradjeno']==1)?'checked':'';?> type="checkbox"></td>
                        <td>
                            <div class="tbl-actions">
                            <?php if($_SESSION['nivo']>='1' && $data['Uradjeno']==0) { ?>
                                <a class="tbl-btn tbl-btn--red" href="terotkazi.php?p=<?=$data['TerminId']?>">Otkaži</a>
                            <?php } ?>
                            <?php if($_SESSION['nivo']>='2' && $data['Uradjeno']==0) { ?>
                                <a class="tbl-btn tbl-btn--green" href="teruradjen.php?p=<?=$data['TerminId']?>">Urađeno</a>
                            <?php } ?>
                            </div>
                        </td>
                    </tr>
<?php } ?>
                </tbody>
            </table>
        </div>
        <?php if($_SESSION['nivo']>='1') { ?>
        <div class="pg-action">
            <a class="ct-btn" href="ternovi.php">Zakaži termin</a>
        </div>
        <?php } ?>
    </div>
</div>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
