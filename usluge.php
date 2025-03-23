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
    <title>Usluge</title>
</head>
<body>
<?php include 'menu.php'; ?> 
    <h1>Usluge</h1>
      <table>
        <tr>
            <td>Usluga</td>
	    <td>Cena</td>
            <td>Trajanje</td>
            <td>Opis</td>
            <td>Aktivna</td>
            <td></td>
        </tr>
<?php
  $sql = "select * from usluga order by UslugaId";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc())  { ?>
        <tr>
            <td><?=$data['UslugaId']?></td>
	    <td><?=$data['Cena']?></td>
            <td><?=$data['Trajanje']?></td>
            <td><?=$data['Opis']?></td>
            <td><input disabled <?=($data['Aktivna']==1)?'checked':'';?> type="checkbox"></td>      
<?php if($_SESSION['nivo']>='2') { ?>
            <td><a class="nav-link" href="uslizmeni.php?p=<?=$data['UslugaId']?>">Izmeni</a></td>
<?php } ?>
        </tr>
<?php } ?> 
      </table>
<?php if($_SESSION['nivo']>='2') { ?> 
    <div class="forma">
        <button class="button" type="click" onclick="location.href='uslnova.php'">Nova usluga</button>
    </div>
<?php } ?>  
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>