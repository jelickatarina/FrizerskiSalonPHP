<?php
  require_once 'sesija.php';
  if(isset($_SESSION['nivo'])) $nivo=$_SESSION['nivo'];
  else $nivo='0';
?>
<nav class="kn-nav">
  <a class="kn-brand" href="index.php">
    <span class="kn-brand-word">Frizerski</span>
    <span class="kn-brand-dot">·</span>
    <span class="kn-brand-word">Salon</span>
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
    <li><a href="prijava.php">Prijavi se</a></li>
    <li><a href="registracija.php">Registruj se</a></li>
    <?php else: ?>
    <li><a href="odjava.php">Odjava</a></li>
    <?php endif; ?>
  </ul>
</nav>

<?php if(isset($_SESSION['korisnik'])) { ?>
<div id="idle-warning" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
  background:#1a1814;border:1px solid rgba(212,168,40,0.4);border-radius:10px;
  padding:1rem 1.4rem;font-family:'Jost',sans-serif;font-size:0.82rem;color:#eeeae2;
  box-shadow:0 8px 30px rgba(0,0,0,0.5);max-width:280px;">
  <strong style="color:#d4a828;">Upozorenje</strong><br>
  Biće te odjavljen za <span id="idle-countdown">60</span> sekundi zbog neaktivnosti.
  <br><button onclick="resetIdle()" style="margin-top:0.6rem;padding:0.3rem 1rem;
    border:1px solid rgba(212,168,40,0.45);background:transparent;color:#d4a828;
    border-radius:4px;cursor:pointer;font-size:0.72rem;letter-spacing:0.1em;">
    Ostani prijavljen
  </button>
</div>
<script>
(function(){
  const WARN = 29 * 60, TOTAL = 30 * 60;
  let last = Date.now(), warned = false, timer = null;
  const warning = document.getElementById('idle-warning');
  const countdown = document.getElementById('idle-countdown');

  function resetIdle() {
    last = Date.now(); warned = false;
    warning.style.display = 'none';
    clearInterval(timer);
  }
  window.resetIdle = resetIdle;

  ['mousemove','keydown','click','scroll','touchstart'].forEach(e =>
    document.addEventListener(e, resetIdle, {passive:true})
  );

  setInterval(function(){
    const idle = Math.floor((Date.now() - last) / 1000);
    if(idle >= TOTAL) { window.location.href = 'odjava.php'; }
    else if(idle >= WARN) {
      if(!warned) { warned = true; timer = setInterval(function(){
        const left = TOTAL - Math.floor((Date.now()-last)/1000);
        countdown.textContent = Math.max(0, left);
      }, 1000); }
      warning.style.display = 'block';
    }
  }, 5000);
})();
</script>
<?php } ?>
