<?php if(session_status() === PHP_SESSION_NONE) session_start(); ?>
<footer class="site-footer">
  <div class="site-footer-inner">

    <div class="sf-brand">
      <a href="index.php" class="sf-logo">
        <span>Frizerski</span>
        <span class="sf-dot">·</span>
        <span>Salon</span>
      </a>
      <p class="sf-tagline">Vaš stil, naša strast</p>
      <div class="sf-social">
        <a href="#" class="sf-social-link" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="currentColor"/>
          </svg>
        </a>
        <a href="#" class="sf-social-link" aria-label="Facebook">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
          </svg>
        </a>
      </div>
    </div>

    <div class="sf-col">
      <h4 class="sf-col-title">Navigacija</h4>
      <ul class="sf-links">
        <li><a href="index.php">Početna</a></li>
        <li><a href="kontakt.php">Kontakt</a></li>
        <?php if(isset($_SESSION['korisnik'])): ?>
        <li><a href="ternovi.php">Zakaži termin</a></li>
        <li><a href="termini.php">Moji termini</a></li>
        <li><a href="odjava.php">Odjava</a></li>
        <?php else: ?>
        <li><a href="prijava.php">Prijava</a></li>
        <li><a href="registracija.php">Registracija</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="sf-col">
      <h4 class="sf-col-title">Kontakt</h4>
      <ul class="sf-links sf-contact">
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          <span>09:00 – 17:00, Pon – Sub</span>
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/>
          </svg>
          <span>Bulevar oslobodjenja 45, Beograd</span>
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
          </svg>
          <span>065-676-5532</span>
        </li>
      </ul>
    </div>

  </div>

  <div class="sf-bottom">
    <span>© <?= date('Y') ?> Frizerski salon. Sva prava zadržana.</span>
  </div>
</footer>
