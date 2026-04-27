<?php
  if(session_status() === PHP_SESSION_NONE) session_start();
  if(isset($_SESSION['nivo'])) $nivo=$_SESSION['nivo'];
  else $nivo='0';
?>
<nav class="kn-nav">
  <a class="kn-brand" href="index.php">
    <span class="kn-brand-thin">Frizerski</span>
    <span class="kn-brand-bold">Salon</span>
  </a>

  <button class="kn-toggler" onclick="this.closest('nav').classList.toggle('kn-open')" aria-label="Meni">
    <span></span><span></span><span></span>
  </button>

  <ul class="kn-links">
    <li><a href="index.php">Početna</a></li>
    <li><a href="kontakt.php">Kontakt</a></li>
    <?php if($nivo>='1'): ?>
    <li><a href="termini.php">Termini</a></li>
    <li><a href="usluge.php">Usluge</a></li>
    <?php endif; ?>
    <?php if($nivo>='2'): ?>
    <li><a href="korisnici.php">Korisnici</a></li>
    <?php endif; ?>
    <?php if($nivo=='0'): ?>
    <li class="kn-sep"></li>
    <li><a href="prijava.php" class="kn-link-outline">Prijavi se</a></li>
    <li><a href="registracija.php" class="kn-link-fill">Registruj se</a></li>
    <?php else: ?>
    <li class="kn-sep"></li>
    <li><a href="odjava.php" class="kn-link-outline">Odjava</a></li>
    <?php endif; ?>
  </ul>
</nav>
