<?php
require_once 'sesija.php';
if(!isset($_SESSION['korisnik']) || (int)$_SESSION['nivo'] < 9)
  die("Pristup odbijen.");
include 'konekcija.php';

$sql = "
INSERT IGNORE INTO korisnik (KorisnikId,Lozinka,Ime,Prezime,DatumRodjenja,Telefon,Email,Nivo) VALUES
('Milica','Milica123','Milica','Stanković','1990-03-14','0641234567','milica.s@gmail.com',2),
('Petar','Petar123','Petar','Đorđević','1988-07-22','0652345678','petar.dj@gmail.com',2),
('Ana','Ana123','Ana','Jovanović','1995-11-05','0611111111','ana.j@gmail.com',1),
('Stefan','Stefan123','Stefan','Nikolić','1993-04-18','0622222222','stefan.n@gmail.com',1),
('Maja','Maja123','Maja','Petrović','1998-08-30','0633333333','maja.p@gmail.com',1),
('Nikola','Nikola123','Nikola','Milošević','1991-12-01','0644444444','nikola.m@gmail.com',1),
('Jelena','Jelena123','Jelena','Ristić','2000-02-14','0655555555','jelena.r@gmail.com',1),
('Marko','Marko123','Marko','Lazić','1996-06-20','0666666666','marko.l@gmail.com',1),
('Ivana','Ivana123','Ivana','Kovačević','1997-09-11','0677777777','ivana.k@gmail.com',1),
('Luka','Luka123','Luka','Savić','1994-03-25','0688888888','luka.s@gmail.com',1),
('Sara','Sara123','Sara','Đukić','2001-07-08','0699999999','sara.dj@gmail.com',1);

INSERT IGNORE INTO usluga (UslugaId,Cena,Trajanje,Opis,Aktivna) VALUES
('Šišanje muško',    1200, 30,  'Klasično muško šišanje sa stilizovanjem', 1),
('Šišanje žensko',   1800, 60,  'Žensko šišanje sa pranjem i feniranjem', 1),
('Farbanje kose',    3500, 120, 'Farbanje cele kose u izabranu nijansu', 1),
('Highlights',       4200, 150, 'Pramenovi svetliji od prirodne boje', 1),
('Pranje i feniranje', 1000, 45,'Pranje sa regeneratorom i profesionalno feniranje', 1),
('Tretman kose',     2200, 60,  'Dubinska nega oštećene ili suhe kose', 1),
('Keratin tretman',  5500, 180, 'Ispravljanje i zaglađivanje kosom keratinom', 1),
('Nadogradnja',      6000, 120, 'Nadogradnja prirodnom ili sintetičkom kosom', 0);

INSERT IGNORE INTO termin (UslugaId,KorisnikId,Datum,Vreme,KorisnikFrizerId,Uradjeno) VALUES
-- Danas
('Šišanje muško',      'Stefan', CURDATE(), 18, 'Milica', 0),
('Pranje i feniranje', 'Ana',    CURDATE(), 20, 'Petar',  0),
('Highlights',         'Maja',   CURDATE(), 22, 'Milica', 0),
('Tretman kose',       'Jelena', CURDATE(), 26, 'Petar',  0),
('Šišanje žensko',     'Marko',  CURDATE(), 28, 'Milica', 1),
-- Sutra i dalje
('Šišanje muško',      'Nikola', DATE_ADD(CURDATE(),INTERVAL 1 DAY), 18, 'Petar',  0),
('Farbanje kose',      'Ana',    DATE_ADD(CURDATE(),INTERVAL 1 DAY), 20, 'Milica', 0),
('Keratin tretman',    'Maja',   DATE_ADD(CURDATE(),INTERVAL 2 DAY), 18, 'Petar',  0),
('Highlights',         'Stefan', DATE_ADD(CURDATE(),INTERVAL 2 DAY), 24, 'Milica', 0),
('Šišanje žensko',     'Jelena', DATE_ADD(CURDATE(),INTERVAL 3 DAY), 22, 'Petar',  0),
-- Prethodni (urađeni)
('Šišanje žensko',     'Jelena', DATE_SUB(CURDATE(),INTERVAL 2 DAY), 18, 'Milica', 1),
('Farbanje kose',      'Stefan', DATE_SUB(CURDATE(),INTERVAL 4 DAY), 20, 'Petar',  1);
";

$conn->multi_query($sql);
do { $conn->use_result(); } while ($conn->more_results() && $conn->next_result());

echo "<style>body{font-family:sans-serif;background:#100f0c;color:#f0ece4;padding:2rem;}</style>";
echo "<h2>Seed završen!</h2>";
echo "<p>Dodato: 11 korisnika, 8 usluga, 12 termina (INSERT IGNORE - duplikati se preskačaju).</p>";
echo "<p><a href='korisnici.php' style='color:#d4a828'>→ Korisnici</a> &nbsp;
      <a href='usluge.php' style='color:#d4a828'>→ Usluge</a> &nbsp;
      <a href='termini.php' style='color:#d4a828'>→ Termini</a></p>";
