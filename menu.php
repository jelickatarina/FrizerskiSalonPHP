<?php
  if(session_status() === PHP_SESSION_NONE) session_start(); // startuj sesiju ako nije startovana
  if(isset($_SESSION['nivo'])) $nivo=$_SESSION['nivo'];
  else $nivo='0';
?>
<nav class="navbar navbar-expand-lg border-bottom bg-body-tertiary"  data-bs-theme="dark">
  <div class="container">
    <a class="navbar-brand" href="index.php">Frizerski salon</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
<?php if($nivo=='0') { ?> 
        <li class="nav-item">
          <a class="nav-link" href="prijava.php">Prijavi se</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="registracija.php">Registruj se</a>
        </li>
<?php } ?> 
<?php if($nivo>='1') { ?> 
        <li class="nav-item">
          <a class="nav-link" href="termini.php">Termini</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="usluge.php">Usluge</a>
        </li>
<?php } ?> 
<?php if($nivo>='2') { ?> 
        <li class="nav-item">
          <a class="nav-link" href="korisnici.php">Korisnici</a>
        </li>
<?php } ?> 
<?php if($nivo>'0') { ?> 
        <li class="nav-item">
          <a class="nav-link" href="odjava.php">Odjava</a>
        </li>
<?php } ?> 
      </ul>
    </div>
  </div>
</nav>
