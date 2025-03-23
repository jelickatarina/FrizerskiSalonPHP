<?php 
  session_start(); 
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'9')) 
    header('Location: nemaovlascenje.html');

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka="";
    $korisnik = trim($_REQUEST['korisnik']);
    $lozinka = trim($_REQUEST['lozinka']);
    $ime = trim($_REQUEST['ime']);
    $prezime = trim($_REQUEST['prezime']);
    $datumr = trim($_REQUEST['datumr']);
    $email = trim($_REQUEST['email']);
    $telefon = trim($_REQUEST['telefon']);
    $nivop = trim($_REQUEST['nivo']);
    $doznivo = ['0', '1', '2', '9'];  
  
    if(strlen($korisnik)==0) $poruka="Unesite korisničko ime";
    else if(strlen($lozinka)==0) $poruka="Unesite lozinku";
    else if(strlen($ime)==0) $poruka="Unesite ime";
    else if(strlen($prezime)==0) $poruka="Unesite prezime";
    else if(strlen($datumr)==0) $poruka="Unesite datum rođenja";
    else if(strlen($email)==0) $poruka="Unesite email";
    else if(strlen($telefon)==0) $poruka="Unesite telefon";
    else if(strlen($nivop)==0) $poruka="Unesite nivo";
    else if(!in_array($nivop, $doznivo)) $poruka="Nivo može da ima vrednost 0,1,2 i 9";
    else
    {
      include 'konekcija.php';
      $sql = "select count(*) as c1 from korisnik where KorisnikId='".$korisnik."'";
      $result = $conn->query($sql);
      $data=$result->fetch_assoc();
      if($data['c1']>0) $poruka="Korisnik ".$korisnik." je već registrovan";
      else
      {
	$sql = "insert into korisnik (KorisnikId,Lozinka,Ime,Prezime,DatumRodjenja,Telefon,Email,Nivo) values ( 
         '".$korisnik."',
         '".$lozinka."',
         '".$ime."',
         '".$prezime."',
         '".$datumr."',
         '".$telefon."',
         '".$email."',
         $nivop)";
        $conn->query($sql);
        header('Location: korisnici.php');
      }
    }
  }
  else // GET
  {
    $poruka="";
    $korisnik = "";
    $lozinka = "";
    $ime = "";
    $prezime = "";
    $datumr = "";
    $email = "";
    $telefon = "";
    $nivop = "";
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
    <title>Novi korisnik</title>
</head>
<body>
<?php include 'menu.php'; ?> 
<form method="post" class="forma">
    <fieldset>
    <h1>Novi korisnik</h1>
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
        <tr>
          <td>Ime:</td>
          <td><input type="text" name="ime" value="<?=$ime?>"></td>
        </tr>
        <tr>
            <td>Prezime:</td>
            <td><input type="text" name="prezime" value="<?=$prezime?>"></td>
        </tr>
        <tr>
            <td>Datum rodjenja:</td>
            <td><input type="date" name="datumr" value="<?=$datumr?>"></td>
        </tr>
        <tr>
            <td>Email:</td>
            <td><input type="text" name="email" value="<?=$email?>"></td>
        </tr>
        <tr>
            <td>Telefon:</td>
            <td><input type="text" name="telefon" value="<?=$telefon?>"></td>
        </tr>
        <tr>
            <td>Nivo:</td>
            <td><input type="text" name="nivo" value="<?=$nivop?>"></td>
        </tr>
      </table>
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>