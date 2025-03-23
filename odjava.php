<?php 
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    unset($_SESSION['korisnik']);
    unset($_SESSION['nivo']);
    header('Location: index.php');
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
    <title>Odjava</title>
</head>
<body>
<?php include 'menu.php'; ?> 
<form action method="post" class="forma">
    <fieldset>
    <h1>Odjava</h1>
    <p>Da li ste sigurni da želite da se odjavite?</p>
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>
