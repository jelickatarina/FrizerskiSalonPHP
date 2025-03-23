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
<nav class="navbar navbar-expand-lg border-bottom bg-body-tertiary"  data-bs-theme="dark">
  <div class="container">
    <a class="navbar-brand" href="#">Frizerski salon</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="index.html">O nama</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="registracija.php">Registruj se</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="prijava.php">Prijavi se</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="termin.php">Termini</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="odjava.php">Odjava</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<form action="odjava.php" method="post" class="forma">
    <fieldset>
    <h1>Odjava</h1>
    <p>Da li ste sigurni da želite da se odjavite?</p>
      <input type="submit" name="potvrda" value="Potvrdi" class="button">
    </fieldset>
    <?php
        if(isset($_POST['potvrda']))
        {
            session_destroy();
        }
    ?>
</form>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>