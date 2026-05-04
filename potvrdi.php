<?php
require_once 'sesija.php';
requireNivo('2');
include 'konekcija.php';
include_once 'email.php';

$id   = (int)($_GET['p'] ?? 0);
$nivo = (int)$_SESSION['nivo'];
$me   = $_SESSION['korisnik'];

if ($id <= 0) { header('Location: termini.php?view=zahtevi'); exit; }

// Load termin + client info
$stmt = $conn->prepare(
    "SELECT t.*, k.Ime, k.Prezime, k.Email AS KEmail, k.Telefon AS KTel
     FROM termin t
     LEFT JOIN korisnik k ON t.KorisnikId = k.KorisnikId
     WHERE t.TerminId = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$t) { header('Location: termini.php?view=zahtevi'); exit; }

// Frizer can only confirm their own requests
if ($nivo === 2 && $t['KorisnikFrizerId'] !== $me) {
    header('Location: termini.php?view=zahtevi'); exit;
}

// Already confirmed
if ($t['Potvrdjeno'] == 1) { header('Location: termini.php?view=zakazani'); exit; }

// Confirm
$stmt = $conn->prepare("UPDATE termin SET Potvrdjeno=1 WHERE TerminId=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

// Send confirmation email to client
if (!empty($t['KEmail'])) {
    $vreme = sprintf('%02d:%02d', (int)($t['Vreme']/2), ($t['Vreme']%2)*30);
    // Get frizer display name
    $sf = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
    $sf->bind_param('s', $t['KorisnikFrizerId']);
    $sf->execute();
    $fr = $sf->get_result()->fetch_assoc();
    $sf->close();
    $frizerNaziv = $fr ? $fr['Ime'].' '.$fr['Prezime'] : $t['KorisnikFrizerId'];
    $klijentIme  = $t['Ime'].' '.$t['Prezime'];
    emailPotvrdaZahteva($t['KEmail'], $klijentIme, $t['UslugaId'], $t['Datum'], $vreme, $frizerNaziv);
}

header('Location: termini.php?view=zahtevi');
exit;
