<?php 
if(!isset($_REQUEST['p'])) echo "Neispravan poziv strane"; 
else 
{
  session_start(); 
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'1')) 
    header('Location: nemaovlascenje.html');

  $termin = $_REQUEST['p']; 
  include 'konekcija.php';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka="";
    $termin = trim($_REQUEST['termin']);
    $sql = "delete from termin where TerminId=".$termin;
    $conn->query($sql);
    header('Location: termini.php');
  }
  else // GET
  {
    $poruka="";
    $sql = "select * from termin where TerminId='".$termin."'";
    $result = $conn->query($sql);
    if($data=$result->fetch_assoc())  { 
      $usluga=$data['UslugaId'];
      $korisnikt=$data['KorisnikId'];
      $datum=$data['Datum'];
      $tmp1=$data['Vreme']/2;
      $tmp2=($data['Vreme'] % 2) * 30;
      $tv=sprintf('%02d:%02d', $tmp1,$tmp2);
      $frizer=$data['KorisnikFrizerId'];
    }
    else
      die("Ne mogu da proadjem termin:".$termin);
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
    <title>Otkazivanje termina</title>
</head>
<body>
<?php include 'menu.php'; ?> 
<form method="post" class="forma">
    <fieldset>
    <h1>Otkazivanje termina</h1>
    <h5 style="color:#FF0000;"><?=$poruka?><h5>
      <table>
        <tr>
            <td>Termin:</td>
            <td><input readonly type="text" name="termin" value="<?=$termin?>"></td>
        </tr>
        <tr>
            <td>Usluga:</td>
            <td><input readonly type="text" name="usluga" value="<?=$usluga?>"></td>
        </tr>
        <tr>
          <td>Korisnik:</td>
          <td><input readonly type="text" name="korisnikt" value="<?=$korisnikt?>"></td>
        </tr>
        <tr>
          <td>Datum:</td>
          <td><input readonly type="text" name="datum" value="<?=$datum?>"></td>
        </tr>
        <tr>
            <td>Vreme:</td>
            <td><input readonly type="text" name="vreme" value="<?=$tv?>"></td>
        </tr>
        <tr>
            <td>Frizer:</td>
            <td><input readonly type="text" name="aktivna" value="<?=$frizer?>"></td>
        </tr>
      </table>
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>
<?php } ?>