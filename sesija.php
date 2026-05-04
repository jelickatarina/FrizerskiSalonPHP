<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(['lifetime' => 1800, 'path' => '/']);
    session_start();
}
$timeout = 1800;
if (isset($_SESSION['korisnik'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: prijava.php?reason=timeout');
        exit();
    }
    $_SESSION['last_activity'] = time();
}
