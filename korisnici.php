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
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Korisničko ime</th>
                        <?php if($_SESSION['nivo']=='9') { ?><th>Lozinka</th><?php } ?>
                        <th>Ime</th>
                        <th>Prezime</th>
                        <th>Datum rođenja</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Nivo</th>
                        <?php if($_SESSION['nivo']=='9') { ?><th></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
<?php
  $sql = "select * from korisnik order by KorisnikId";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc()) { ?>
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
                        <td><a class="tbl-btn" href="korizmeni.php?p=<?=$data['KorisnikId']?>">Izmeni</a></td>
                        <?php } ?>
                    </tr>
<?php } ?>
                </tbody>
            </table>
        </div>
        <?php if($_SESSION['nivo']=='9') { ?>
        <div class="pg-action">
            <a class="ct-btn" href="kornovi.php">Novi korisnik</a>
        </div>
        <?php } ?>
    </div>
</div>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
