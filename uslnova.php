<?php 
  session_start(); 
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']<'2')) 
    header('Location: nemaovlascenje.html');

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka="";
    $usluga = trim($_REQUEST['usluga']);
    $cena = trim($_REQUEST['cena']);
    $trajanje = trim($_REQUEST['trajanje']);
    $opis = trim($_REQUEST['opis']);
    if(isset($_REQUEST['aktivna']))
    {
      $aktivna = 1;
      $aktivnaf = "checked";
    }
    else
    {
      $aktivna = 0;
      $aktivnaf = "";
    }
  
    if(strlen($usluga)==0) $poruka="Unesite uslugu";
    else if(strlen($cena)==0) $poruka="Unesite cenu";
    else if(!is_numeric($cena)) $poruka="Cena je broj";
    else if(strlen($trajanje)==0) $poruka="Unesite trajanje";
    else if(!is_numeric($trajanje)) $poruka="Trajanje je broj";
    else if(strlen($opis)==0) $poruka="Unesite opis";
    else
    {
      include 'konekcija.php';
      $sql = "select count(*) as c1 from usluga where UslugaId='".$usluga."'";
      $result = $conn->query($sql);
      $data=$result->fetch_assoc();
      if($data['c1']>0) $poruka="Usluga ".$usluga." već postoji";
      else
      {
	      $sql = "insert into usluga (UslugaId,Cena,Trajanje,Opis,Aktivna) values ( 
         '".$usluga."',
         $cena,
         $trajanje,
         '".$opis."',
         $aktivna)";
        echo $sql;
        $conn->query($sql);
        header('Location: usluge.php');
      }
    }
  }
  else // GET
  {
    $poruka="";
    $usluga = "";
    $cena = "";
    $trajanje = "";
    $opis = "";
    $aktivnaf = "checked";
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
    <title>Nova usluga</title>
</head>
<body>
<?php include 'menu.php'; ?> 
<form method="post" class="forma">
    <fieldset>
    <h1>Nova usluga</h1>
    <h5 style="color:#FF0000;"><?=$poruka?><h5>
      <table>
        <tr>
            <td>Usluga:</td>
            <td><input type="text" name="usluga" value="<?=$usluga?>"></td>
        </tr>
        <tr>
          <td>Cena:</td>
          <td><input type="text" name="cena" value="<?=$cena?>"></td>
        </tr>
        <tr>
          <td>Trajanje:</td>
          <td><input type="text" name="trajanje" value="<?=$trajanje?>"></td>
        </tr>
        <tr>
            <td>Opis:</td>
            <td><input type="text" name="opis" value="<?=$opis?>"></td>
        </tr>
        <tr>
            <td>Aktivna:</td>
            <td><input <?=$aktivnaf?> type="checkbox" name="aktivna" value="<?=$aktivna?>"></td>
        </tr>
      </table>
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>