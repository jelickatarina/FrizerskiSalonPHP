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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Termini</title>
</head>
<body>
<?php include 'menu.php'; ?> 
    <h1>Termini</h1>
      <table>
        <tr>
            <td>Termin</td>
	          <td>Usluga</td>
            <td>Korisnik</td>
            <td>Datum</td>
            <td>Vreme</td>
            <td>Frizer</td>
            <td>Urađeno</td>
            <td></td>
        </tr>
<?php
  if($_SESSION['nivo']==1) $wh1=" where KorisnikId='".$_SESSION['korisnik']."' ";
  else if($_SESSION['nivo']==2) $wh1=" where KorisnikFrizerId='".$_SESSION['korisnik']."' ";
  else $wh1=" ";
  $sql = "select * from termin".$wh1."order by Datum,Vreme";
  $result = $conn->query($sql);

  while($data=$result->fetch_assoc())  { 
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
<?php if($_SESSION['nivo']>='1'&&$data['Uradjeno']==0) { ?>
            <td><a class="nav-link" href="terotkazi.php?p=<?=$data['TerminId']?>">Otkaži</a></td>
<?php } ?>
<?php if($_SESSION['nivo']>='2'&&$data['Uradjeno']==0) { ?>
            <td><a class="nav-link" href="teruradjen.php?p=<?=$data['TerminId']?>">Urađeno</a></td>
<?php } ?>
        </tr>
<?php } ?> 
      </table>
<?php if($_SESSION['nivo']=='1') { ?> 
    <div class="forma">
        <button class="button" type="click" onclick="location.href='ternovi.php'">Zakaži</button>
    </div>
<?php } ?>  
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>