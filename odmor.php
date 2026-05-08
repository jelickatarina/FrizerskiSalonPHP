<?php
require_once 'sesija.php';
requireNivo('2');
include 'konekcija.php';
include_once 'email.php';

$nivo = (int)$_SESSION['nivo'];
$me   = $_SESSION['korisnik'];

$poruka  = '';
$success = '';
$danas   = date('Y-m-d');
$tipLabel = ['odmor' => 'Godišnji odmor', 'slobodan_dan' => 'Slobodan dan', 'bolovanje' => 'Bolovanje'];

// Build set of occupied 30-min slots for a frizer in a period
function buildOccupied($conn, $frizerId, $datumOd, $datumDo) {
    $st = $conn->prepare(
        "SELECT t.Datum, t.Vreme, COALESCE(u.Trajanje, 30) AS Trajanje
         FROM termin t LEFT JOIN usluga u ON t.UslugaId=u.UslugaId
         WHERE t.KorisnikFrizerId=? AND t.Datum BETWEEN ? AND ?
           AND (t.Otkazano IS NULL OR t.Otkazano=0) AND t.Uradjeno=0"
    );
    $st->bind_param('sss', $frizerId, $datumOd, $datumDo);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $occ = [];
    foreach ($rows as $r) {
        $slots = max(1, (int)ceil($r['Trajanje'] / 30));
        for ($s = 0; $s < $slots; $s++) $occ[$r['Datum']][$r['Vreme'] + $s] = true;
    }
    return $occ;
}

// ── Handle DELETE (admin only) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && $nivo === 9) {
    $id = (int)($_POST['odmor_id'] ?? 0);
    if ($id > 0) {
        $sd = $conn->prepare("DELETE FROM frizer_odmor WHERE OdmorId=?");
        $sd->bind_param('i', $id);
        $sd->execute();
        $sd->close();
        $success = 'Odsustvo je obrisano.';
    }
}

// ── Handle ADD (admin only) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add' && $nivo === 9) {

    // ── STEP 3: Execute from session (after conflict confirmation) ──
    if (isset($_POST['potvrda_konflikta'])) {
        $p = $_SESSION['odmor_pending'] ?? null;
        if ($p && isset($p['transfer_noviFrizer'])) {
            $frizer      = $p['frizer'];
            $datumOd     = $p['datumOd'];
            $datumDo     = $p['datumDo'];
            $tip         = $p['tip'];
            $napomena    = $p['napomena'];
            $noviFrizer  = $p['transfer_noviFrizer'];
            $freeIds     = $p['transfer_freeIds'];
            $conflictIds = $p['transfer_conflictIds'];
            $akcijaK     = trim($_POST['akcija_konflikta'] ?? 'transfer');

            $otkazanoTermina  = 0;
            $prebacenoTermina = 0;

            if ($akcijaK === 'cancel_all') {
                $allIds = array_merge($freeIds, $conflictIds);
                if ($allIds) {
                    $ids = implode(',', array_map('intval', $allIds));
                    $conn->query("UPDATE termin SET Otkazano=1 WHERE TerminId IN ($ids)");
                    $otkazanoTermina = count($allIds);
                }
            } else {
                if ($freeIds) {
                    $nfe = $conn->real_escape_string($noviFrizer);
                    $ids = implode(',', array_map('intval', $freeIds));
                    $conn->query("UPDATE termin SET KorisnikFrizerId='$nfe' WHERE TerminId IN ($ids)");
                    $prebacenoTermina = count($freeIds);
                }
                if ($conflictIds) {
                    $ids = implode(',', array_map('intval', $conflictIds));
                    $conn->query("UPDATE termin SET Otkazano=1 WHERE TerminId IN ($ids)");
                    $otkazanoTermina = count($conflictIds);
                }
            }

            $si = $conn->prepare(
                "INSERT INTO frizer_odmor (KorisnikFrizerId, DatumOd, DatumDo, Tip, Napomena, OtkazanoTermina)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $si->bind_param('sssssi', $frizer, $datumOd, $datumDo, $tip, $napomena, $otkazanoTermina);
            $si->execute();
            $si->close();
            unset($_SESSION['odmor_pending']);

            $msg = 'Odsustvo (' . $tipLabel[$tip] . ') je upisano.';
            if ($prebacenoTermina > 0)
                $msg .= ' Prebačeno ' . $prebacenoTermina . ' termin' . ($prebacenoTermina === 1 ? '' : 'a') . ' kod zamenika.';
            if ($otkazanoTermina > 0)
                $msg .= ' Otkazano ' . $otkazanoTermina . ' termin' . ($otkazanoTermina === 1 ? '.' : 'a.');
            $success = $msg;
        } else {
            $poruka = 'Sesija je istekla. Pokušajte ponovo.';
        }

    } elseif (isset($_POST['potvrda_raspodele'])) {
        // ── STEP 3b: Execute distribution from session ──
        $p = $_SESSION['odmor_pending'] ?? null;
        if (!$p || empty($p['raspodela_termini'])) {
            $poruka = 'Sesija je istekla. Pokušajte ponovo.';
        } else {
            $frizer           = $p['frizer'];
            $datumOd          = $p['datumOd'];
            $datumDo          = $p['datumDo'];
            $tip              = $p['tip'];
            $napomena         = $p['napomena'];
            $raspodelaTermini = $p['raspodela_termini'];

            // Build occupied maps for all frizeri that will receive assignments
            $assignedFrizeriIds = [];
            foreach ($raspodelaTermini as $asgn) {
                $a = trim($_POST['akcija_termin'][$asgn['terminId']] ?? 'cancel');
                if (!in_array($a, ['cancel', 'notify', ''])) $assignedFrizeriIds[$a] = true;
            }
            $frizerOccupied = [];
            foreach (array_keys($assignedFrizeriIds) as $fId) {
                $frizerOccupied[$fId] = buildOccupied($conn, $fId, $datumOd, $datumDo);
            }

            // Lazy-load absent frizer name (for notify emails)
            $frizerNazivEmailR = null;
            $appUrl  = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $expiry  = date('Y-m-d H:i:s', strtotime('+7 days'));

            $prebacenoTermina = 0;
            $otkazanoTermina  = 0;
            $poslatoEmailR    = 0;

            foreach ($raspodelaTermini as $asgn) {
                $tid    = $asgn['terminId'];
                $akcija = trim($_POST['akcija_termin'][$tid] ?? 'cancel');
                $ukupno = max(1, (int)ceil((int)$asgn['trajanje'] / 30));

                if ($akcija === 'notify') {
                    if (!empty($asgn['klijentEmail'])) {
                        if ($frizerNazivEmailR === null) {
                            $sfnR = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
                            $sfnR->bind_param('s', $frizer);
                            $sfnR->execute();
                            $fnrRow = $sfnR->get_result()->fetch_assoc();
                            $sfnR->close();
                            $frizerNazivEmailR = $fnrRow ? $fnrRow['Ime'].' '.$fnrRow['Prezime'] : $frizer;
                        }
                        $tok = bin2hex(random_bytes(32));
                        $sit = $conn->prepare("INSERT INTO termin_akcija_token (TerminId, Token, IsticeOd) VALUES (?,?,?)");
                        $sit->bind_param('iss', $tid, $tok, $expiry);
                        $sit->execute(); $sit->close();
                        emailOdsustvo($asgn['klijentEmail'], $asgn['klijentIme'], $asgn['uslugaNaziv'],
                            $asgn['datum'], $asgn['vremeStr'], $frizerNazivEmailR,
                            $appUrl . '/termin_izmena.php?token=' . $tok);
                        $poslatoEmailR++;
                    } else {
                        $upd = $conn->prepare("UPDATE termin SET Otkazano=1 WHERE TerminId=?");
                        $upd->bind_param('i', $tid); $upd->execute(); $upd->close();
                        $otkazanoTermina++;
                    }
                } elseif ($akcija === 'cancel' || $akcija === '') {
                    $upd = $conn->prepare("UPDATE termin SET Otkazano=1 WHERE TerminId=?");
                    $upd->bind_param('i', $tid); $upd->execute(); $upd->close();
                    $otkazanoTermina++;
                } else {
                    $fId = $akcija;
                    if (!isset($frizerOccupied[$fId])) {
                        $frizerOccupied[$fId] = buildOccupied($conn, $fId, $datumOd, $datumDo);
                    }
                    $free = true;
                    for ($i = 0; $i < $ukupno; $i++) {
                        if (!empty($frizerOccupied[$fId][$asgn['datum']][$asgn['vremeSlot'] + $i])) {
                            $free = false; break;
                        }
                    }
                    if ($free) {
                        $upd = $conn->prepare("UPDATE termin SET KorisnikFrizerId=? WHERE TerminId=?");
                        $upd->bind_param('si', $fId, $tid); $upd->execute(); $upd->close();
                        $prebacenoTermina++;
                        for ($i = 0; $i < $ukupno; $i++) {
                            $frizerOccupied[$fId][$asgn['datum']][$asgn['vremeSlot'] + $i] = true;
                        }
                    } else {
                        // Race condition — cancel as fallback
                        $upd = $conn->prepare("UPDATE termin SET Otkazano=1 WHERE TerminId=?");
                        $upd->bind_param('i', $tid); $upd->execute(); $upd->close();
                        $otkazanoTermina++;
                    }
                }
            }

            $siOd = $conn->prepare(
                "INSERT INTO frizer_odmor (KorisnikFrizerId, DatumOd, DatumDo, Tip, Napomena, OtkazanoTermina)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $siOd->bind_param('sssssi', $frizer, $datumOd, $datumDo, $tip, $napomena, $otkazanoTermina);
            $siOd->execute(); $siOd->close();
            unset($_SESSION['odmor_pending']);

            $msg = 'Odsustvo (' . $tipLabel[$tip] . ') je upisano.';
            if ($prebacenoTermina > 0) $msg .= ' Raspodeljeno ' . $prebacenoTermina . ' termin' . ($prebacenoTermina === 1 ? '' : 'a') . '.';
            if ($otkazanoTermina  > 0) $msg .= ' Otkazano ' . $otkazanoTermina . ' termin' . ($otkazanoTermina === 1 ? '.' : 'a.');
            if ($poslatoEmailR    > 0) $msg .= ' Poslato ' . $poslatoEmailR . ' email obaveštenje' . ($poslatoEmailR === 1 ? '.' : 'a.');
            $success = $msg;
        }

    } else {
        // ── STEP 1 / STEP 2: Read from POST ──
        $frizer   = trim($_POST['frizer']   ?? '');
        $datumOd  = trim($_POST['datum_od'] ?? '');
        $datumDo  = trim($_POST['datum_do'] ?? '');
        $tip      = trim($_POST['tip']      ?? 'odmor');
        $napomena = trim($_POST['napomena'] ?? '');
        $confirmed = isset($_POST['potvrda_otkazivanja']);

        if ($frizer === '') {
            $poruka = 'Izaberite frizera.';
        } elseif ($datumOd === '' || $datumDo === '') {
            $poruka = 'Unesite datum od i datum do.';
        } elseif ($datumDo < $datumOd) {
            $poruka = 'Datum do mora biti isti ili posle datuma od.';
        } elseif (!in_array($tip, ['odmor', 'slobodan_dan', 'bolovanje'])) {
            $poruka = 'Neispravan tip odsustva.';
        } else {
            $so = $conn->prepare(
                "SELECT COUNT(*) FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=?"
            );
            $so->bind_param('sss', $frizer, $datumDo, $datumOd);
            $so->execute();
            $overlap = (int)$so->get_result()->fetch_row()[0];
            $so->close();

            if ($overlap > 0) {
                $poruka = 'Frizer već ima odsustvo koje se preklapa sa izabranim periodom.';
            } else {
                $sc = $conn->prepare(
                    "SELECT COUNT(*) FROM termin
                     WHERE KorisnikFrizerId=? AND Datum BETWEEN ? AND ?
                       AND (Otkazano IS NULL OR Otkazano=0) AND Uradjeno=0"
                );
                $sc->bind_param('sss', $frizer, $datumOd, $datumDo);
                $sc->execute();
                $brTermina = (int)$sc->get_result()->fetch_row()[0];
                $sc->close();

                if ($brTermina > 0 && !$confirmed) {
                    // ── STEP 1: Show appointment confirmation ──
                    $_SESSION['odmor_pending'] = compact('frizer','datumOd','datumDo','tip','napomena','brTermina');
                    $poruka = '__confirm__';
                } else {
                    // ── STEP 2: Execute chosen action ──
                    $akcija     = trim($_POST['otkazi_termine'] ?? '');
                    $noviFrizer = trim($_POST['novi_frizer']    ?? '');
                    $otkazanoTermina  = 0;
                    $prebacenoTermina = 0;

                    $handled = false;
                    if ($akcija === '1') {
                        if ($brTermina > 0) {
                            $su = $conn->prepare(
                                "UPDATE termin SET Otkazano=1
                                 WHERE KorisnikFrizerId=? AND Datum BETWEEN ? AND ?
                                   AND Otkazano=0 AND Uradjeno=0"
                            );
                            $su->bind_param('sss', $frizer, $datumOd, $datumDo);
                            $su->execute();
                            $otkazanoTermina = $su->affected_rows;
                            $su->close();
                        }
                    } elseif ($akcija === 'transfer' && $noviFrizer !== '') {
                        // Validate replacement
                        $svf = $conn->prepare("SELECT KorisnikId FROM korisnik WHERE KorisnikId=? AND Nivo=2");
                        $svf->bind_param('s', $noviFrizer);
                        $svf->execute();
                        $vfRow = $svf->get_result()->fetch_assoc();
                        $svf->close();

                        $sfO = $conn->prepare(
                            "SELECT COUNT(*) FROM frizer_odmor
                             WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=?"
                        );
                        $sfO->bind_param('sss', $noviFrizer, $datumDo, $datumOd);
                        $sfO->execute();
                        $zamenaNaOdmoru = (int)$sfO->get_result()->fetch_row()[0];
                        $sfO->close();

                        if (!$vfRow) {
                            $poruka = 'Izabrani zamenik nije pronađen.';
                        } elseif ($zamenaNaOdmoru > 0) {
                            $poruka = 'Izabrani zamenik je takođe na odsustvu u tom periodu.';
                        } else {
                            // Load all appointments to check
                            $sat = $conn->prepare(
                                "SELECT t.TerminId, t.Datum, t.Vreme,
                                        COALESCE(u.Trajanje, 30) AS Trajanje
                                 FROM termin t LEFT JOIN usluga u ON t.UslugaId=u.UslugaId
                                 WHERE t.KorisnikFrizerId=? AND t.Datum BETWEEN ? AND ?
                                   AND t.Otkazano=0 AND t.Uradjeno=0"
                            );
                            $sat->bind_param('sss', $frizer, $datumOd, $datumDo);
                            $sat->execute();
                            $appts = $sat->get_result()->fetch_all(MYSQLI_ASSOC);
                            $sat->close();

                            // Build replacement's occupied slots
                            $occupied = buildOccupied($conn, $noviFrizer, $datumOd, $datumDo);

                            // Classify appointments
                            $freeIds      = [];
                            $conflictIds  = [];
                            $freeTimes    = [];
                            $conflictTimes = [];
                            foreach ($appts as $appt) {
                                $slots = max(1, (int)ceil($appt['Trajanje'] / 30));
                                $hasConflict = false;
                                for ($s = 0; $s < $slots; $s++) {
                                    if (!empty($occupied[$appt['Datum']][$appt['Vreme'] + $s])) {
                                        $hasConflict = true;
                                        break;
                                    }
                                }
                                $tStr = sprintf('%02d:%02d', (int)($appt['Vreme']/2), ($appt['Vreme']%2)*30);
                                if ($hasConflict) { $conflictIds[] = $appt['TerminId']; $conflictTimes[] = $tStr; }
                                else              { $freeIds[]     = $appt['TerminId']; $freeTimes[]     = $tStr; }
                            }

                            if (count($conflictIds) > 0) {
                                // ── STEP 2b: Conflict confirmation needed ──
                                $sfn = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
                                $sfn->bind_param('s', $noviFrizer);
                                $sfn->execute();
                                $nfRow = $sfn->get_result()->fetch_assoc();
                                $sfn->close();

                                $_SESSION['odmor_pending'] = [
                                    'frizer'               => $frizer,
                                    'datumOd'              => $datumOd,
                                    'datumDo'              => $datumDo,
                                    'tip'                  => $tip,
                                    'napomena'             => $napomena,
                                    'brTermina'            => $brTermina,
                                    'transfer_noviFrizer'  => $noviFrizer,
                                    'transfer_noviNaziv'   => $nfRow ? $nfRow['Ime'].' '.$nfRow['Prezime'] : $noviFrizer,
                                    'transfer_freeIds'     => $freeIds,
                                    'transfer_conflictIds' => $conflictIds,
                                    'transfer_freeTimes'   => $freeTimes,
                                    'transfer_conflictTimes' => $conflictTimes,
                                ];
                                $poruka = '__transfer_conflict__';
                            } else {
                                // No conflicts: transfer all
                                if ($freeIds) {
                                    $nfe = $conn->real_escape_string($noviFrizer);
                                    $ids = implode(',', array_map('intval', $freeIds));
                                    $conn->query("UPDATE termin SET KorisnikFrizerId='$nfe' WHERE TerminId IN ($ids)");
                                    $prebacenoTermina = count($freeIds);
                                }
                            }
                        }
                    } elseif ($akcija === 'notify') {
                        $handled = true;
                        // Get frizer name for email
                        $sfn = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
                        $sfn->bind_param('s', $frizer);
                        $sfn->execute();
                        $frizRow = $sfn->get_result()->fetch_assoc();
                        $sfn->close();
                        $frizerNazivEmail = $frizRow ? $frizRow['Ime'].' '.$frizRow['Prezime'] : $frizer;

                        // Insert absence first so getFrizeriDostupni excludes this frizer
                        $otkazanoTermina = 0;
                        $siOd = $conn->prepare(
                            "INSERT INTO frizer_odmor (KorisnikFrizerId, DatumOd, DatumDo, Tip, Napomena, OtkazanoTermina)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $siOd->bind_param('sssssi', $frizer, $datumOd, $datumDo, $tip, $napomena, $otkazanoTermina);
                        $siOd->execute();
                        $siOd->close();

                        // Fetch affected appointments with client info
                        $sat = $conn->prepare(
                            "SELECT t.TerminId, t.Datum, t.Vreme,
                                    COALESCE(t.UslugaId, 'Usluga') AS UslugaNaziv,
                                    k.Ime AS KIme, k.Prezime AS KPrezime, k.Email AS KEmail
                             FROM termin t
                             LEFT JOIN usluga u ON t.UslugaId = u.UslugaId
                             LEFT JOIN korisnik k ON t.KorisnikId = k.KorisnikId
                             WHERE t.KorisnikFrizerId=? AND t.Datum BETWEEN ? AND ?
                               AND t.Otkazano=0 AND t.Uradjeno=0"
                        );
                        $sat->bind_param('sss', $frizer, $datumOd, $datumDo);
                        $sat->execute();
                        $appts = $sat->get_result()->fetch_all(MYSQLI_ASSOC);
                        $sat->close();

                        $appUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                                . '://' . $_SERVER['HTTP_HOST'];
                        $expiry  = date('Y-m-d H:i:s', strtotime('+7 days'));
                        $poslato = 0;

                        foreach ($appts as $appt) {
                            if (empty($appt['KEmail'])) continue;
                            $tok = bin2hex(random_bytes(32));
                            $sit = $conn->prepare(
                                "INSERT INTO termin_akcija_token (TerminId, Token, IsticeOd) VALUES (?, ?, ?)"
                            );
                            $sit->bind_param('iss', $appt['TerminId'], $tok, $expiry);
                            $sit->execute();
                            $sit->close();

                            $vremeStr   = sprintf('%02d:%02d', (int)($appt['Vreme']/2), ($appt['Vreme']%2)*30);
                            $klijentIme = $appt['KIme'].' '.$appt['KPrezime'];
                            $url        = $appUrl . '/termin_izmena.php?token=' . $tok;
                            emailOdsustvo($appt['KEmail'], $klijentIme, $appt['UslugaNaziv'],
                                $appt['Datum'], $vremeStr, $frizerNazivEmail, $url);
                            $poslato++;
                        }

                        unset($_SESSION['odmor_pending']);
                        $success = 'Odsustvo (' . $tipLabel[$tip] . ') je upisano.'
                            . ($poslato > 0
                                ? ' Poslato ' . $poslato . ' obaveštenje' . ($poslato === 1 ? ' klijentu.' : 'a klijentima.')
                                : ' Nema klijenata sa email adresom za obaveštavanje.');
                    }

                    if ($akcija === 'raspodela' && !$handled) {
                        $handled = true;
                        // Load all affected appointments
                        $satR = $conn->prepare(
                            "SELECT t.TerminId, t.Datum, t.Vreme,
                                    COALESCE(u.Trajanje, 30) AS Trajanje,
                                    COALESCE(t.UslugaId, 'Usluga') AS UslugaNaziv,
                                    k.Ime AS KIme, k.Prezime AS KPrezime, k.Email AS KEmail
                             FROM termin t
                             LEFT JOIN usluga u ON t.UslugaId = u.UslugaId
                             LEFT JOIN korisnik k ON t.KorisnikId = k.KorisnikId
                             WHERE t.KorisnikFrizerId=? AND t.Datum BETWEEN ? AND ?
                               AND t.Otkazano=0 AND t.Uradjeno=0
                             ORDER BY t.Datum, t.Vreme"
                        );
                        $satR->bind_param('sss', $frizer, $datumOd, $datumDo);
                        $satR->execute();
                        $apptsR = $satR->get_result()->fetch_all(MYSQLI_ASSOC);
                        $satR->close();

                        // All frizeri not on leave in this period (excluding the absent frizer)
                        $allFrizeriR = [];
                        $sqfR = $conn->query(
                            "SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2
                             AND KorisnikId != '" . $conn->real_escape_string($frizer) . "'
                             ORDER BY Ime, Prezime"
                        );
                        while ($f = $sqfR->fetch_assoc()) {
                            $sfOR = $conn->prepare(
                                "SELECT COUNT(*) FROM frizer_odmor
                                 WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=?"
                            );
                            $sfOR->bind_param('sss', $f['KorisnikId'], $datumDo, $datumOd);
                            $sfOR->execute();
                            $onLeave = (int)$sfOR->get_result()->fetch_row()[0];
                            $sfOR->close();
                            if (!$onLeave) $allFrizeriR[] = $f;
                        }

                        // Build initial occupied maps for all available frizeri
                        $frizerOccR = [];
                        foreach ($allFrizeriR as $f) {
                            $frizerOccR[$f['KorisnikId']] = buildOccupied($conn, $f['KorisnikId'], $datumOd, $datumDo);
                        }

                        // Auto-distribute: assign each appointment to least-busy available frizer
                        $frizerLoadR      = [];
                        $raspodelaTermini = [];
                        foreach ($apptsR as $apptR) {
                            $ukupno    = max(1, (int)ceil($apptR['Trajanje'] / 30));
                            $dostupniR = [];
                            foreach ($allFrizeriR as $f) {
                                $fId  = $f['KorisnikId'];
                                $free = true;
                                for ($i = 0; $i < $ukupno; $i++) {
                                    if (!empty($frizerOccR[$fId][$apptR['Datum']][$apptR['Vreme'] + $i])) {
                                        $free = false; break;
                                    }
                                }
                                if ($free) $dostupniR[] = $f;
                            }

                            $predlozeniId = null;
                            if (!empty($dostupniR)) {
                                usort($dostupniR, fn($a, $b) =>
                                    ($frizerLoadR[$a['KorisnikId']] ?? 0) <=> ($frizerLoadR[$b['KorisnikId']] ?? 0));
                                $predlozeniId = $dostupniR[0]['KorisnikId'];
                                $frizerLoadR[$predlozeniId] = ($frizerLoadR[$predlozeniId] ?? 0) + 1;
                                // Mark slots occupied so subsequent appointments see it
                                for ($i = 0; $i < $ukupno; $i++) {
                                    $frizerOccR[$predlozeniId][$apptR['Datum']][$apptR['Vreme'] + $i] = true;
                                }
                            }

                            $raspodelaTermini[] = [
                                'terminId'        => $apptR['TerminId'],
                                'datum'           => $apptR['Datum'],
                                'vremeSlot'       => $apptR['Vreme'],
                                'vremeStr'        => sprintf('%02d:%02d', (int)($apptR['Vreme']/2), ($apptR['Vreme']%2)*30),
                                'trajanje'        => $apptR['Trajanje'],
                                'klijentIme'      => trim(($apptR['KIme'] ?? '') . ' ' . ($apptR['KPrezime'] ?? '')),
                                'klijentEmail'    => $apptR['KEmail'] ?? '',
                                'uslugaNaziv'     => $apptR['UslugaNaziv'],
                                'dostupniFrizeri' => $dostupniR,
                                'predlozeniId'    => $predlozeniId,
                            ];
                        }

                        $_SESSION['odmor_pending'] = [
                            'frizer'             => $frizer,
                            'datumOd'            => $datumOd,
                            'datumDo'            => $datumDo,
                            'tip'                => $tip,
                            'napomena'           => $napomena,
                            'raspodela_termini'  => $raspodelaTermini,
                        ];
                        $poruka = '__raspodela__';
                    }

                    if (!$poruka && !$handled) {
                        $si = $conn->prepare(
                            "INSERT INTO frizer_odmor (KorisnikFrizerId, DatumOd, DatumDo, Tip, Napomena, OtkazanoTermina)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $si->bind_param('sssssi', $frizer, $datumOd, $datumDo, $tip, $napomena, $otkazanoTermina);
                        $si->execute();
                        $si->close();
                        unset($_SESSION['odmor_pending']);

                        $msg = 'Odsustvo (' . $tipLabel[$tip] . ') je upisano.';
                        if ($prebacenoTermina > 0)
                            $msg .= ' Prebačeno ' . $prebacenoTermina . ' termin' . ($prebacenoTermina === 1 ? '' : 'a') . ' kod zamenika.';
                        if ($otkazanoTermina > 0)
                            $msg .= ' Otkazano ' . $otkazanoTermina . ' termin' . ($otkazanoTermina === 1 ? '.' : 'a.');
                        $success = $msg;
                    }
                }
            }
        }
    }
}

// Render state
$pending          = ($poruka === '__confirm__')          ? ($_SESSION['odmor_pending'] ?? null) : null;
$transferConflict = ($poruka === '__transfer_conflict__') ? ($_SESSION['odmor_pending'] ?? null) : null;
$raspodela        = ($poruka === '__raspodela__')         ? ($_SESSION['odmor_pending']['raspodela_termini'] ?? []) : null;

// Load frizeri (admin)
$frizerList = [];
if ($nivo === 9) {
    $fr = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    while ($row = $fr->fetch_assoc()) $frizerList[] = $row;
}

// Frizer filter for listing
$frizerFilter = ($nivo === 9) ? trim($_GET['frizer'] ?? '') : $me;

// Load odmor records
$whFrizer = ($nivo === 9 && $frizerFilter === '') ? '1=1' : 'o.KorisnikFrizerId=?';
$qArgs    = ($nivo === 9 && $frizerFilter === '') ? [] : [$frizerFilter];
$sql = "SELECT o.*, k.Ime, k.Prezime
        FROM frizer_odmor o JOIN korisnik k ON o.KorisnikFrizerId=k.KorisnikId
        WHERE $whFrizer ORDER BY o.DatumOd DESC";
if ($qArgs) {
    $st = $conn->prepare($sql);
    $st->bind_param('s', ...$qArgs);
    $st->execute();
    $odmori = $st->get_result();
    $st->close();
} else {
    $odmori = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mojstil.css">
    <title>Odsustvo – Frizerski salon</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="kp-page">
    <header class="kp-header">
        <p class="ct-eyebrow">Frizerski salon</p>
        <h1 class="kp-title">Odsustvo</h1>
        <div class="ct-orn">
            <span></span>
            <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="2"/></svg>
            <span></span>
        </div>
    </header>

    <div class="pg-wrap">

        <?php if ($success): ?>
        <div class="ct-alert ct-alert--ok" style="margin-bottom:1.4rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($poruka && !in_array($poruka, ['__confirm__','__transfer_conflict__'])): ?>
        <div class="ct-alert ct-alert--err" style="margin-bottom:1.4rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/></svg>
            <?= htmlspecialchars($poruka) ?>
        </div>
        <?php endif; ?>

        <!-- ── ADD / CONFIRMATION CARD (admin only) ──── -->
        <?php if ($nivo === 9): ?>
        <div class="odmor-card" style="margin-bottom:2rem;">

            <?php if ($transferConflict): ?>
            <!-- ── STEP 3: Transfer conflict confirmation ── -->
            <?php
                $nFree          = count($transferConflict['transfer_freeIds']);
                $nConflict      = count($transferConflict['transfer_conflictIds']);
                $noviNaziv      = htmlspecialchars($transferConflict['transfer_noviNaziv']);
                $freeTimes      = $transferConflict['transfer_freeTimes']    ?? [];
                $conflictTimes  = $transferConflict['transfer_conflictTimes'] ?? [];
            ?>
            <h2 class="odmor-section-title">Konflikt rasporeda</h2>
            <div class="ct-alert ct-alert--warn" style="margin-bottom:1.4rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
                <div>
                    Zamenik <strong><?= $noviNaziv ?></strong> već ima zauzete termine u ovom periodu.
                    <?php if ($nFree > 0): ?>
                    <strong><?= $nFree ?></strong> termin<?= $nFree === 1 ? '' : 'a' ?> može biti prebačeno,
                    a <strong><?= $nConflict ?></strong> <?= $nConflict === 1 ? 'mora biti otkazan' : 'moraju biti otkazani' ?>.
                    <?php else: ?>
                    Svih <strong><?= $nConflict ?></strong> termin<?= $nConflict === 1 ? '' : 'a' ?> mora biti otkazan<?= $nConflict === 1 ? '' : 'o' ?>.
                    <?php endif; ?>
                </div>
            </div>

            <div class="odmor-conflict-stats">
                <?php if ($nFree > 0): ?>
                <div class="odmor-conflict-stat odmor-conflict-stat--free">
                    <div class="odmor-conflict-num"><?= $nFree ?></div>
                    <div class="odmor-conflict-label">Slobodno</div>
                </div>
                <?php endif; ?>
                <div class="odmor-conflict-stat odmor-conflict-stat--busy">
                    <div class="odmor-conflict-num"><?= $nConflict ?></div>
                    <div class="odmor-conflict-label">Zauzeto</div>
                </div>
            </div>

            <?php if ($freeTimes || $conflictTimes): ?>
            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1.2rem;">
                <?php foreach ($freeTimes as $t): ?>
                <span style="padding:0.2rem 0.6rem;border-radius:4px;font-size:0.74rem;
                    background:rgba(60,180,100,0.12);border:1px solid rgba(60,180,100,0.3);color:#6dcf95;">
                    <?= htmlspecialchars($t) ?> ✓
                </span>
                <?php endforeach; ?>
                <?php foreach ($conflictTimes as $t): ?>
                <span style="padding:0.2rem 0.6rem;border-radius:4px;font-size:0.74rem;
                    background:rgba(180,60,60,0.12);border:1px solid rgba(180,60,60,0.3);color:#d47070;">
                    <?= htmlspecialchars($t) ?> ✗
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post" class="odmor-confirm-form" style="margin-top:1.2rem;">
                <input type="hidden" name="action"           value="add">
                <input type="hidden" name="potvrda_konflikta" value="1">
                <div class="odmor-confirm-btns">
                    <?php if ($nFree > 0): ?>
                    <button type="submit" name="akcija_konflikta" value="transfer" class="odmor-btn odmor-btn--secondary">
                        Prebaci <?= $nFree ?>, otkaži <?= $nConflict ?>
                    </button>
                    <?php endif; ?>
                    <button type="submit" name="akcija_konflikta" value="cancel_all" class="odmor-btn odmor-btn--danger">
                        Otkaži sve (<?= $nFree + $nConflict ?>)
                    </button>
                    <a href="odmor.php" class="odmor-btn odmor-btn--ghost">Poništi</a>
                </div>
            </form>

            <?php elseif ($raspodela !== null): ?>
            <!-- ── STEP 3b: Distribution confirmation ── -->
            <?php
                $nRasp      = count($raspodela);
                $nNema      = count(array_filter($raspodela, fn($a) => empty($a['dostupniFrizeri'])));
                $nDostupno  = $nRasp - $nNema;
            ?>
            <h2 class="odmor-section-title">Raspodela termina</h2>

            <?php if ($nNema > 0): ?>
            <div class="ct-alert ct-alert--warn" style="margin-bottom:1.2rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
                <div>
                    <strong><?= $nDostupno ?></strong> od <strong><?= $nRasp ?></strong> termina mogu biti prebačeni.
                    Za <strong><?= $nNema ?></strong> termin<?= $nNema === 1 ? '' : 'a' ?> nema slobodnog frizera — izaberite otkazivanje ili obaveštenje klijenta.
                </div>
            </div>
            <?php else: ?>
            <div class="ct-alert ct-alert--ok" style="margin-bottom:1.2rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Svih <strong><?= $nRasp ?></strong> termin<?= $nRasp === 1 ? '' : 'a' ?> može biti raspoređeno. Proverite i potvrdite predlog.
            </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action"            value="add">
                <input type="hidden" name="potvrda_raspodele" value="1">

                <div class="odmor-raspodela-list">
                <?php foreach ($raspodela as $asgn):
                    $hasEmail = !empty($asgn['klijentEmail']);
                    $nemFriz  = empty($asgn['dostupniFrizeri']);
                ?>
                <div class="odmor-raspodela-row <?= $nemFriz ? 'odmor-raspodela-row--nema' : '' ?>">
                    <div class="odmor-raspodela-info">
                        <span class="odmor-raspodela-time">
                            <?= htmlspecialchars(date('d.m.', strtotime($asgn['datum']))) ?>
                            <strong><?= htmlspecialchars($asgn['vremeStr']) ?></strong>
                        </span>
                        <span class="odmor-raspodela-klijent"><?= htmlspecialchars($asgn['klijentIme']) ?></span>
                        <span class="odmor-raspodela-usluga"><?= htmlspecialchars($asgn['uslugaNaziv']) ?></span>
                    </div>
                    <div class="odmor-raspodela-sel">
                        <select name="akcija_termin[<?= (int)$asgn['terminId'] ?>]" class="filter-select odmor-raspodela-select">
                            <?php if (!empty($asgn['dostupniFrizeri'])): ?>
                            <optgroup label="Prebaci kod:">
                                <?php foreach ($asgn['dostupniFrizeri'] as $df): ?>
                                <option value="<?= htmlspecialchars($df['KorisnikId']) ?>"
                                    <?= $df['KorisnikId'] === $asgn['predlozeniId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($df['Ime'].' '.$df['Prezime']) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($hasEmail): ?>
                            <option value="notify" <?= $nemFriz ? 'selected' : '' ?>>
                                Obavesti klijenta mejlom
                            </option>
                            <?php endif; ?>
                            <option value="cancel" <?= ($nemFriz && !$hasEmail) ? 'selected' : '' ?>>
                                Otkaži termin
                            </option>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

                <div class="odmor-confirm-btns" style="margin-top:1.4rem;">
                    <button type="submit" class="odmor-btn odmor-btn--secondary">Potvrdi raspodelu</button>
                    <a href="odmor.php" class="odmor-btn odmor-btn--ghost">Poništi</a>
                </div>
            </form>

            <?php elseif ($pending): ?>
            <!-- ── STEP 2: Appointment confirmation ── -->
            <?php $zameneFrizeri = array_filter($frizerList, fn($f) => $f['KorisnikId'] !== $pending['frizer']); ?>
            <h2 class="odmor-section-title">Novo odsustvo</h2>
            <div class="ct-alert ct-alert--warn" style="margin-bottom:1.4rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
                <div>
                    <strong>Pažnja!</strong> U izabranom periodu postoji
                    <strong><?= $pending['brTermina'] ?></strong>
                    zakazan<?= $pending['brTermina'] === 1 ? '' : 'ih' ?>
                    termin<?= $pending['brTermina'] === 1 ? '.' : 'a.' ?>
                    Izaberite šta uraditi s njima:
                </div>
            </div>

            <form method="post" class="odmor-confirm-form">
                <input type="hidden" name="action"              value="add">
                <input type="hidden" name="frizer"              value="<?= htmlspecialchars($pending['frizer']) ?>">
                <input type="hidden" name="datum_od"            value="<?= htmlspecialchars($pending['datumOd']) ?>">
                <input type="hidden" name="datum_do"            value="<?= htmlspecialchars($pending['datumDo']) ?>">
                <input type="hidden" name="tip"                 value="<?= htmlspecialchars($pending['tip']) ?>">
                <input type="hidden" name="napomena"            value="<?= htmlspecialchars($pending['napomena']) ?>">
                <input type="hidden" name="potvrda_otkazivanja" value="1">

                <div class="odmor-confirm-options">
                    <div class="odmor-confirm-option">
                        <div class="odmor-confirm-option-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            Otkaži sve termine
                        </div>
                        <p class="odmor-confirm-desc">Svi termini u ovom periodu biće otkazani.</p>
                        <button type="submit" name="otkazi_termine" value="1" class="odmor-btn odmor-btn--danger">
                            Otkaži termine i upiši odsustvo
                        </button>
                    </div>

                    <?php if (count($zameneFrizeri) > 0): ?>
                    <div class="odmor-confirm-option odmor-confirm-option--transfer">
                        <div class="odmor-confirm-option-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            Prebaci termine kod zamenika
                        </div>
                        <p class="odmor-confirm-desc">Slobodni termini se prebacuju, zauzeti se otkazuju.</p>
                        <div class="odmor-transfer-row">
                            <select name="novi_frizer" class="filter-select" style="flex:1;" id="sel-zamenik">
                                <option value="">— Izaberite zamenika —</option>
                                <?php foreach ($zameneFrizeri as $zf): ?>
                                <option value="<?= htmlspecialchars($zf['KorisnikId']) ?>">
                                    <?= htmlspecialchars($zf['Ime'].' '.$zf['Prezime']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="otkazi_termine" value="transfer" id="btn-transfer" class="odmor-btn odmor-btn--secondary">
                                Prebaci i upiši odsustvo
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="odmor-confirm-option odmor-confirm-option--raspodela">
                        <div class="odmor-confirm-option-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><line x1="16" y1="3" x2="16" y2="21"/><line x1="8" y1="3" x2="8" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></svg>
                            Raspodeli između dostupnih frizera
                        </div>
                        <p class="odmor-confirm-desc">Sistem pronalazi koji frizer može svaki termin i predlaže raspodelu. Vi potvrđujete ili menjate pre izvršavanja.</p>
                        <button type="submit" name="otkazi_termine" value="raspodela" class="odmor-btn odmor-btn--secondary">
                            Prikaži raspodelu
                        </button>
                    </div>

                    <div class="odmor-confirm-option odmor-confirm-option--notify">
                        <div class="odmor-confirm-option-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            Obavesti klijente mejlom
                        </div>
                        <p class="odmor-confirm-desc">Svaki klijent dobija mejl sa linkom. Sami biraju: otkazivanje, drugi frizer, ili novi termin. Link važi 7 dana.</p>
                        <button type="submit" name="otkazi_termine" value="notify" class="odmor-btn odmor-btn--secondary">
                            Pošalji obaveštenja i upiši odsustvo
                        </button>
                    </div>
                </div>

                <a href="odmor.php" class="odmor-btn odmor-btn--ghost" style="align-self:flex-start;margin-top:0.4rem;">Poništi</a>
            </form>

            <?php else: ?>
            <!-- ── Normal add form ── -->
            <h2 class="odmor-section-title">Novo odsustvo</h2>
            <form method="post" class="odmor-add-form">
                <input type="hidden" name="action" value="add">

                <div class="ct-field">
                    <label>Frizer <span class="ct-req">*</span></label>
                    <select name="frizer" required>
                        <option value="">— Izaberite frizera —</option>
                        <?php foreach ($frizerList as $fr): ?>
                        <option value="<?= htmlspecialchars($fr['KorisnikId']) ?>">
                            <?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="odmor-date-row">
                    <div class="ct-field">
                        <label>Datum od <span class="ct-req">*</span></label>
                        <input type="date" name="datum_od" required min="<?= $danas ?>">
                    </div>
                    <div class="ct-field">
                        <label>Datum do <span class="ct-req">*</span></label>
                        <input type="date" name="datum_do" required min="<?= $danas ?>">
                    </div>
                </div>

                <div class="ct-field">
                    <label>Tip odsustva <span class="ct-req">*</span></label>
                    <select name="tip">
                        <option value="odmor">Godišnji odmor</option>
                        <option value="slobodan_dan">Slobodan dan</option>
                        <option value="bolovanje">Bolovanje</option>
                    </select>
                </div>

                <div class="ct-field">
                    <label>Napomena</label>
                    <input type="text" name="napomena" maxlength="255" placeholder="Opcionalno…">
                </div>

                <button type="submit" class="auth-btn" style="align-self:flex-start;">Dodaj odsustvo</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── ADMIN FRIZER FILTER ─────────────────────── -->
        <?php if ($nivo === 9 && count($frizerList) > 0): ?>
        <div class="pg-toolbar" style="margin-bottom:0.8rem;">
            <form method="get" style="display:flex;align-items:center;gap:0.6rem;">
                <select name="frizer" class="filter-select" onchange="this.form.submit()">
                    <option value="">Svi frizeri</option>
                    <?php foreach ($frizerList as $fr): ?>
                    <option value="<?= htmlspecialchars($fr['KorisnikId']) ?>"
                        <?= $frizerFilter === $fr['KorisnikId'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── LIST ───────────────────────────────────── -->
        <div class="odmor-list">
            <h2 class="odmor-section-title">
                <?= $nivo === 9 ? 'Upisana odsustva' : 'Moja odsustva' ?>
            </h2>

            <?php
            $rows = [];
            while ($row = $odmori->fetch_assoc()) $rows[] = $row;
            ?>

            <?php if (count($rows) === 0): ?>
            <div class="ter-empty">
                <svg class="ter-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <h3 class="ter-empty-title"><?= ($nivo===9 && $frizerFilter!=='') ? 'Nema odsustva za ovog frizera' : 'Nema upisanih odsustva' ?></h3>
                <p class="ter-empty-sub">Odsustva će se prikazati ovde kada budu upisana.</p>
            </div>
            <?php else: ?>
            <div class="odmor-entries">
                <?php foreach ($rows as $r):
                    $isActive = $r['DatumDo'] >= $danas && $r['DatumOd'] <= $danas;
                    $isFuture = $r['DatumOd'] > $danas;
                    $isPast   = $r['DatumDo'] < $danas;
                    $tipKey   = $r['Tip'];
                    $statusClass = $isActive ? 'odmor-status--active' : ($isFuture ? 'odmor-status--future' : 'odmor-status--past');
                    $statusLabel = $isActive ? 'Aktivno' : ($isFuture ? 'Predstojeće' : 'Prošlo');
                ?>
                <div class="odmor-entry <?= $isPast ? 'odmor-entry--past' : '' ?>">
                    <div class="odmor-entry-left">
                        <span class="odmor-tip odmor-tip--<?= $tipKey ?>">
                            <?php if ($tipKey === 'odmor'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <?php elseif ($tipKey === 'slobodan_dan'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            <?php endif; ?>
                            <?= htmlspecialchars($tipLabel[$tipKey] ?? $tipKey) ?>
                        </span>
                        <?php if ($nivo === 9): ?>
                        <span class="odmor-frizer"><?= htmlspecialchars($r['Ime'].' '.$r['Prezime']) ?></span>
                        <?php endif; ?>
                        <span class="odmor-dates">
                            <?= date('d.m.Y.', strtotime($r['DatumOd'])) ?>
                            <?= $r['DatumOd'] !== $r['DatumDo'] ? ' — ' . date('d.m.Y.', strtotime($r['DatumDo'])) : '' ?>
                        </span>
                        <?php if ($r['Napomena']): ?>
                        <span class="odmor-napomena"><?= htmlspecialchars($r['Napomena']) ?></span>
                        <?php endif; ?>
                        <?php if ($r['OtkazanoTermina'] > 0): ?>
                        <span class="odmor-cancelled-badge">
                            <?= $r['OtkazanoTermina'] ?> termin<?= $r['OtkazanoTermina'] === 1 ? '' : 'a' ?> otkazan<?= $r['OtkazanoTermina'] === 1 ? '' : 'o' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="odmor-entry-right">
                        <span class="odmor-status <?= $statusClass ?>"><?= $statusLabel ?></span>
                        <?php if (!$isPast && $nivo === 9): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Obrisati ovo odsustvo?');">
                            <input type="hidden" name="action"   value="delete">
                            <input type="hidden" name="odmor_id" value="<?= $r['OdmorId'] ?>">
                            <button type="submit" class="tbl-btn tbl-btn--red">Obriši</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php include 'footer.php'; ?>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
  const nav = document.querySelector('.kn-nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 10));

  const od  = document.querySelector('input[name="datum_od"]');
  const _do = document.querySelector('input[name="datum_do"]');
  if (od && _do) {
    od.addEventListener('change', () => {
      if (_do.value && _do.value < od.value) _do.value = od.value;
      _do.min = od.value;
    });
    [od, _do].forEach(el => {
      el.addEventListener('click', function () { try { this.showPicker(); } catch(e) {} });
    });
  }

  const btnTransfer = document.getElementById('btn-transfer');
  const selZamenik  = document.getElementById('sel-zamenik');
  if (btnTransfer && selZamenik) {
    btnTransfer.closest('form').addEventListener('submit', function(e) {
      if (e.submitter === btnTransfer && !selZamenik.value) {
        e.preventDefault();
        selZamenik.focus();
        selZamenik.style.outline = '1px solid rgba(212,168,40,0.8)';
      }
    });
  }
</script>
</body>
</html>
