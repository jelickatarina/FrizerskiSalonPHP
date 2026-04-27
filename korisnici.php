<?php 
  session_start(); 
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'2')) 
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mojstil.css">
    <title>Korisnici</title>
</head>
<body>
<?php include 'menu.php'; ?> 
    <h1>Korisnici</h1>
      <table>
        <tr>
            <td>Korisničko ime</td>
<?php if($_SESSION['nivo']=='9') { ?> 
	    <td>Lozinka</td>
<?php } ?> 
            <td>Ime</td>
            <td>Prezime</td>
            <td>Datum rodjenja</td>
            <td>Email</td>
            <td>Telefon</td>
            <td>Nivo</td>
<?php if($_SESSION['nivo']=='9') { ?> 
            <td></td>
<?php } ?> 
        </tr>
<?php
  $sql = "select * from korisnik order by KorisnikId";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc())  { ?>
        <tr>
            <td><?=$data['KorisnikId']?></td>
<?php if($_SESSION['nivo']=='9') { ?>
	    <td><?=$data['Lozinka']?></td>
<?php } ?>
            <td><?=$data['Ime']?></td>
            <td><?=$data['Prezime']?></td>
            <td><?=$data['DatumRodjenja']?></td>
            <td><?=$data['Email']?></td>
            <td><?=$data['Telefon']?></td>
            <td><?=$data['Nivo']?></td>
<?php if($_SESSION['nivo']=='9') { ?> 
            <td><a class="nav-link" href="korizmeni.php?p=<?=$data['KorisnikId']?>">Izmeni</a></td>
<?php } ?> 
        </tr>
<?php } ?> 
      </table>
<?php if($_SESSION['nivo']=='9') { ?> 
    <div class="forma">
        <button class="button" type="click" onclick="location.href='kornovi.php'">Novi korisnik</button>
    </div>
<?php } ?>  
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>