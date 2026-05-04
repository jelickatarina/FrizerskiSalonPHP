<?php
require_once 'sesija.php';
if((int)$_SESSION['nivo'] < 9) die("Pristup odbijen.");
include 'konekcija.php';

$conn->query("ALTER TABLE termin ADD COLUMN IF NOT EXISTS Potvrdjeno TINYINT NOT NULL DEFAULT 1");
$conn->query("UPDATE termin SET Potvrdjeno=1 WHERE Potvrdjeno IS NULL");

echo "<style>body{font-family:sans-serif;background:#100f0c;color:#f0ece4;padding:2rem;}</style>";
echo "<h2>Migracija završena!</h2>";
echo "<p>Kolona <code>Potvrdjeno</code> dodata u tabelu <code>termin</code>.</p>";
echo "<p><a href='termini.php' style='color:#d4a828'>→ Termini</a></p>";
