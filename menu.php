<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$nivo    = isset($_SESSION['nivo'])    ? $_SESSION['nivo']    : '0';
$sesIme  = isset($_SESSION['ime'])     ? $_SESSION['ime']     : '';
$sesPrez = isset($_SESSION['prezime']) ? $_SESSION['prezime'] : '';
$sesKor  = isset($_SESSION['korisnik'])? $_SESSION['korisnik']: '';

// Build initials for avatar
if ($sesIme !== '' && $sesPrez !== '') {
    $initials = mb_strtoupper(mb_substr($sesIme, 0, 1) . mb_substr($sesPrez, 0, 1));
    $fullName = $sesIme . ' ' . $sesPrez;
} elseif ($sesKor !== '') {
    $initials = mb_strtoupper(mb_substr($sesKor, 0, 2));
    $fullName = $sesKor;
} else {
    $initials = '';
    $fullName = '';
}

$nivoLabel = ['1' => 'Klijent', '2' => 'Frizer', '9' => 'Administrator'];
$roleLabel = $nivoLabel[$nivo] ?? '';
?>
<nav class="kn-nav">
  <a class="kn-brand" href="index.php">
    <span class="kn-brand-word">Frizerski</span>
    <span class="kn-brand-dot">·</span>
    <span class="kn-brand-word">Salon</span>
  </a>

  <div class="kn-right">
    <ul class="kn-links">
      <li><a href="index.php">Početna</a></li>
      <li><a href="kontakt.php">Kontakt</a></li>
      <?php if ($nivo >= '1'): ?>
      <li><a href="termini.php">Termini</a></li>
      <li><a href="usluge.php">Usluge</a></li>
      <?php endif; ?>
      <?php if ($nivo >= '2'): ?>
      <li><a href="korisnici.php">Korisnici</a></li>
      <?php endif; ?>
      <?php if ($nivo == '0'): ?>
      <li><a href="prijava.php">Prijavi se</a></li>
      <li><a href="registracija.php">Registruj se</a></li>
      <?php endif; ?>
    </ul>

    <?php if ($nivo != '0'): ?>
    <div class="kn-profile" id="kn-profile">
      <button class="kn-avatar" id="kn-avatar-btn" aria-label="Moj profil" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </button>
      <div class="kn-drop" id="kn-drop" role="menu">
        <div class="kn-drop-info">
          <div class="kn-drop-name"><?= htmlspecialchars($fullName) ?></div>
          <?php if ($roleLabel): ?>
          <div class="kn-drop-role"><?= htmlspecialchars($roleLabel) ?></div>
          <?php endif; ?>
        </div>
        <div class="kn-drop-sep"></div>
        <a href="profil.php" class="kn-drop-link" role="menuitem">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Moj profil
        </a>
        <div class="kn-drop-sep"></div>
        <a href="odjava.php" class="kn-drop-link kn-drop-link--out" role="menuitem">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Odjava
        </a>
      </div>
    </div>
    <?php endif; ?>

    <button class="kn-toggler" id="kn-toggler" aria-label="Meni">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<script>
(function () {
  const toggler = document.getElementById('kn-toggler');
  const nav     = document.querySelector('.kn-nav');
  if (toggler) toggler.addEventListener('click', () => nav.classList.toggle('kn-open'));

  const avatarBtn = document.getElementById('kn-avatar-btn');
  const drop      = document.getElementById('kn-drop');
  if (avatarBtn && drop) {
    avatarBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const open = drop.classList.toggle('kn-drop--open');
      avatarBtn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', function () {
      drop.classList.remove('kn-drop--open');
      avatarBtn.setAttribute('aria-expanded', 'false');
    });
    drop.addEventListener('click', e => e.stopPropagation());
  }
})();
</script>

<?php if (isset($_SESSION['korisnik'])) { ?>
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
