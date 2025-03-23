<?php 
  session_start(); 
  include 'konekcija.php';
  if(!isset($_SESSION['korisnik'])||($_SESSION['nivo']!='1')) 
    header('Location: nemaovlascenje.html');

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poruka="";
    $t1 = trim($_REQUEST['t1']);
    $korisnik = trim($_REQUEST['korisnik']);
    if(isset($_REQUEST['usluga'])) $usluga = trim($_REQUEST['usluga']);
    else $usluga="";
    $datum = trim($_REQUEST['datum']); 
    if(isset($_REQUEST['frizer'])) $frizer = trim($_REQUEST['frizer']);  
    else $frizer="";
    if($t1==1)
    {
      $date1 = new DateTime(); // Current date and time    
      $date1->setTime(0,0,0,0); // h,m,s,mikros, i vreme je u $date2 nula
      $date2 = new DateTime($datum);
      $interval = $date1->diff($date2);
      $tmp1 = intval($interval->format('%R%a'));

      if($tmp1==0) 
      {
        // danas, izracunaj sledeci termin imajuci u vidu trenutno vreme
        $tv=date("H:i:s"); // format je HH:MM:SS
        $tmp2 = substr($tv,0, 2);
        $tmp3 = substr($tv,3, 2);
        $i1 = $tmp2 * 2;
        $i2 = (int)($tmp3 / 30);
        $tpoc = $i1+$i2+1;
      }
      else $tpoc=18; // 09:00

      if(strlen($usluga)==0) $poruka="Niste uzabrali uslugu";
      else if(strlen($datum)==0) $poruka="Unesite datum";
      else if(strlen($frizer)==0) $poruka="Niste izabrali frizera";
      else if(date('w', strtotime($datum))==0) $poruka="Ne radimo nedeljom";
      else if($tmp1<0) $poruka="Datum je u prošlosti";
      else if($tmp1>7) $poruka="Zakazivanje maksimalno 7 dana unapred";
      else 
      {
        $sql = "select Trajanje from usluga where UslugaId='".$usluga."'";
        $result = $conn->query($sql);
        if(!$data=$result->fetch_assoc())  die("Ne mogu da pročitam trajanje termina");
        $ukupnoTermina = (int)(1+($data['Trajanje']-1)/30);
        $zauzetiTermini = array();

        // Zauzeti termini frizera za taj dan
        $sql = "select Vreme,UslugaId from termin where KorisnikFrizerId='".$frizer."' and Datum='".$datum."'";
        $result = $conn->query($sql);
        while($data=$result->fetch_assoc())  
        {
          $tv = $data['Vreme'];
          $zauzetiTermini[] = $tv;
          $sql2 = "select Trajanje from usluga where UslugaId='".$data['UslugaId']."'";
          $result2 = $conn->query($sql2);
          $data2=$result2->fetch_assoc();
          $trajanjeTermina = (int)(1 + ($data2['Trajanje']-1) / 30);
          for($i=1;$i<$trajanjeTermina;$i++)
          {
            $tv++;
            $zauzetiTermini[] = $tv;
          }
	      }

        // Pronadji slobodan termin
        $vremena = array();
        for ($i = $tpoc; $i <= (34 - $ukupnoTermina); $i++)
        {
          $nemoze = false;
          if(!in_array($i, $zauzetiTermini))
          {
            for($j=1; $j<$ukupnoTermina; $j++)
              if (in_array($i+$j, $zauzetiTermini)) $nemoze = true;
            if (!$nemoze) 
            {
              $tmp1=$i/2;
              $tmp2=($i % 2) * 30;
              $tv=sprintf('%02d:%02d', $tmp1,$tmp2);
              $vremena[]=$tv;
            }
          }
        }

        if(count($vremena)==0) $poruka = "Nema slobodnih termina";
        else
        {  
          $vreme = "";
          $t1=2;
        }
      }
    }
    else // t1=2
    {
      if(isset($_REQUEST['vreme'])) $vreme = trim($_REQUEST['vreme']);
      else $vreme="";
      if(strlen($vreme)==0) $poruka="Unesite vreme";      
      else
      {
        $tmp1 = substr($vreme,0, 2);
        $tmp2 = substr($vreme,3, 2);
        $i1 = $tmp1 * 2;
        $i2 = (int)($tmp2 / 30);
        $res = $i1+$i2;
	      $sql = "insert into termin (UslugaId,KorisnikId,Datum,Vreme,KorisnikFrizerId,Uradjeno) values ( 
         '".$usluga."',
         '".$korisnik."',
         '".$datum."',
         $res,
         '".$frizer."',
         0)";
        $conn->query($sql);
        header('Location: termini.php');
      }
    }
  }
  else // GET
  {
    $poruka="";
    $t1=1;
    $korisnik=$_SESSION['korisnik'];
    $usluga="";
    $datum="";
    $frizer="";
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
    <title>Zakazivanje</title>
</head>
<body>
<?php include 'menu.php'; ?> 
<form method="post" class="forma">
    <fieldset>
    <h1>Zakazivanje</h1>
    <h5 style="color:#FF0000;"><?=$poruka?><h5>
      <table>
        <tr>
            <td>Korisnik:</td>
            <td><input readonly type="text" name="korisnik" value="<?=$korisnik?>"></td>
        </tr>
        <tr>
            <td>Usluga:</td>
            <td>
<?php if($t1==1) { ?>
<select name="usluga">
<?php
  $sql = "select UslugaId from usluga where Aktivna=1 order by UslugaId";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc())  { ?>
    <option value="<?=$data['UslugaId']?>"
      <?=($data['UslugaId'] == $usluga) ? "selected" : ''?>
      ><?=$data['UslugaId']?></option>
<?php } ?>
</select>
<?php } else { ?>
<input readonly type="text" name="usluga" value="<?=$usluga?>">
<?php } ?>
            </td>
        </tr>
        <tr>
            <td>Datum:</td>
<?php if($t1==1) { ?>
            <td><input type="date" name="datum" value="<?=$datum?>"></td>
<?php } else { ?>
            <td><input readonly type="text" name="datum" value="<?=$datum?>"></td>
<?php } ?>
        </tr>
        <tr>
            <td>Frizer:</td>
<td>
<?php if($t1==1) { ?>
<select name="frizer">
<?php
  $sql = "select KorisnikId from korisnik where Nivo=2 order by KorisnikId";
  $result = $conn->query($sql);
  while($data=$result->fetch_assoc())  { ?>
    <option value="<?=$data['KorisnikId']?>"
      <?=($data['KorisnikId'] == $frizer) ? "selected" : ''?>
      ><?=$data['KorisnikId']?></option>
<?php } ?>
</select>
<?php } else { ?>
<input readonly type="text" name="frizer" value="<?=$frizer?>">
<?php } ?>
</td>
        </tr>
<?php if($t1==2) { ?>
        <tr>
            <td>Vreme:</td>
<td>
<select name="vreme">
<?php
  $sql = "select KorisnikId from korisnik where Nivo=2 order by KorisnikId";
  $result = $conn->query($sql);
  foreach($vremena as $tv) { ?>
    <option value="<?=$tv?>"
      <?=($tv == $vreme) ? "selected" : ''?>
      ><?=$tv?></option>
<?php } ?>
</select>
</td>
        </tr>
<?php } ?>
      </table>
      <input type="hidden" name="t1" value="<?=$t1?>">
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>