<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Zakazi termin</title>
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
          <a class="nav-link active" href="termin.php">Termini</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="odjava.php">Odjava</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
    <div class="forma">
        <h1>Termini</h1>
        <button class="button" type="click" onclick="location.href='zakazivanje.php'">Zakazi termin</button>
        <button class="button" type="click" onclick="location.href='pregledajTermin.php'">Pregledaj zakazane termine</button>
    </div>
</body>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</html>