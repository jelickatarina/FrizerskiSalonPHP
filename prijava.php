<?php 

  session_start();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnik = trim($_REQUEST['korisnik']);
    $lozinka = trim($_REQUEST['lozinka']);
    
    if(strlen($korisnik)==0) $poruka="Unesite korisničko ime";
    else if(strlen($lozinka)==0) $poruka="Unesite lozinku";
    else
    {
      include 'konekcija.php';
      $sql = "select nivo from korisnik where KorisnikId='".$korisnik."' and lozinka='".$lozinka."' and nivo>0";
      $result = $conn->query($sql);
      if(!($data=$result->fetch_assoc())) $poruka="Neispravni podaci";
      else
      {
        $_SESSION['korisnik'] = $korisnik; 
        $_SESSION['nivo'] = $data['nivo']; 
        header('Location: index.php');
      }
    }
  }
  else // GET
  {
    unset($_SESSION['korisnik']);
    unset($_SESSION['nivo']);
    $poruka = "";
    $korisnik = "";
    $lozinka = "";
  }
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Prijava</title>
</head>
<body>
<?php include 'menu.php'; ?> 
<form action="prijava.php" method="post" class="forma">
    <fieldset>
    <h1>Prijava</h1>
    <h5 style="color:#FF0000;"><?=$poruka?><h5>
      <table>
        <tr>
            <td>Korisničko ime:</td>
            <td><input type="text" name="korisnik" value="<?=$korisnik?>"></td>
        </tr>
        <tr>
          <td>Lozinka:</td>
          <td><input type="password" name="lozinka" value="<?=$lozinka?>"></td>
        </tr>
      </table>
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>