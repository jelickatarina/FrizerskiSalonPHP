<?php
// Run daily at 20:00 via Railway cron.
// Sends reminder emails for tomorrow's confirmed appointments booked > 24h ago.
include_once __DIR__.'/konekcija.php';
include_once __DIR__.'/email.php';

$sutra = date('Y-m-d', strtotime('+1 day'));

$res = $conn->query(
    "SELECT t.TerminId, t.UslugaId, t.Datum, t.Vreme, t.KorisnikFrizerId,
            k.Ime, k.Prezime, k.Email
     FROM termin t
     LEFT JOIN korisnik k ON t.KorisnikId = k.KorisnikId
     WHERE t.Potvrdjeno=1
       AND t.Uradjeno=0
       AND t.Datum='$sutra'
       AND k.Email IS NOT NULL AND k.Email != ''"
);

$sent = 0;
while ($row = $res->fetch_assoc()) {
    $vreme = sprintf('%02d:%02d', (int)($row['Vreme']/2), ($row['Vreme']%2)*30);

    $sf = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
    $sf->bind_param('s', $row['KorisnikFrizerId']);
    $sf->execute();
    $fr = $sf->get_result()->fetch_assoc();
    $sf->close();
    $frizerNaziv = $fr ? $fr['Ime'].' '.$fr['Prezime'] : $row['KorisnikFrizerId'];

    emailPodsetnik(
        $row['Email'],
        $row['Ime'].' '.$row['Prezime'],
        $row['UslugaId'],
        $row['Datum'],
        $vreme,
        $frizerNaziv
    );
    $sent++;
}

echo "Podsetnici poslati: $sent\n";
