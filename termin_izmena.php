<?php
include 'konekcija.php';
include_once 'email.php';

// ── Helper functions ─────────────────────────────────
function _tiStr($slot) {
    return sprintf('%02d:%02d', (int)($slot / 2), ($slot % 2) * 30);
}
function _slotIzStr($s) {
    $p = explode(':', $s);
    return (int)$p[0] * 2 + ((int)$p[1] >= 30 ? 1 : 0);
}
function _tpoc($datum) {
    if ($datum !== date('Y-m-d')) return 18;
    $ch = (int)date('G');
    $cm = (int)date('i');
    return max(18, $ch * 2 + (int)($cm / 30) + 1);
}
function _frizeriDostupni($conn, $datum, $slot, $ukupno) {
    $result = [];
    $q = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    if (!$q) return $result;
    while ($fr = $q->fetch_assoc()) {
        $sl = $conn->prepare("SELECT 1 FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=? LIMIT 1");
        $sl->bind_param('sss', $fr['KorisnikId'], $datum, $datum);
        $sl->execute();
        $onLeave = (bool)$sl->get_result()->fetch_assoc();
        $sl->close();
        if ($onLeave) continue;

        $st = $conn->prepare(
            "SELECT t.Vreme, COALESCE(u.Trajanje, 30) AS Trajanje
             FROM termin t LEFT JOIN usluga u ON t.UslugaId=u.UslugaId
             WHERE t.KorisnikFrizerId=? AND t.Datum=?
               AND (t.Otkazano IS NULL OR t.Otkazano=0) AND t.Uradjeno=0"
        );
        $st->bind_param('ss', $fr['KorisnikId'], $datum);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $occ = [];
        foreach ($rows as $r) {
            $n = max(1, (int)ceil($r['Trajanje'] / 30));
            for ($i = 0; $i < $n; $i++) $occ[$r['Vreme'] + $i] = true;
        }
        $free = true;
        for ($i = 0; $i < $ukupno; $i++) {
            if (!empty($occ[$slot + $i])) { $free = false; break; }
        }
        if ($free) $result[] = $fr;
    }
    return $result;
}
function _frizeriSaSlotovima($conn, $datum, $ukupno) {
    $tpoc   = _tpoc($datum);
    $result = [];
    $q = $conn->query("SELECT KorisnikId, Ime, Prezime FROM korisnik WHERE Nivo=2 ORDER BY Ime, Prezime");
    if (!$q) return $result;
    while ($fr = $q->fetch_assoc()) {
        $sl = $conn->prepare("SELECT 1 FROM frizer_odmor WHERE KorisnikFrizerId=? AND DatumOd<=? AND DatumDo>=? LIMIT 1");
        $sl->bind_param('sss', $fr['KorisnikId'], $datum, $datum);
        $sl->execute();
        $onLeave = (bool)$sl->get_result()->fetch_assoc();
        $sl->close();
        if ($onLeave) continue;

        $st = $conn->prepare(
            "SELECT t.Vreme, COALESCE(u.Trajanje, 30) AS Trajanje
             FROM termin t LEFT JOIN usluga u ON t.UslugaId=u.UslugaId
             WHERE t.KorisnikFrizerId=? AND t.Datum=?
               AND (t.Otkazano IS NULL OR t.Otkazano=0) AND t.Uradjeno=0"
        );
        $st->bind_param('ss', $fr['KorisnikId'], $datum);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $occ = [];
        foreach ($rows as $r) {
            $n = max(1, (int)ceil($r['Trajanje'] / 30));
            for ($i = 0; $i < $n; $i++) $occ[$r['Vreme'] + $i] = true;
        }
        $freeSlots = [];
        for ($s = $tpoc; $s <= 34 - $ukupno; $s++) {
            $free = true;
            for ($i = 0; $i < $ukupno; $i++) {
                if (!empty($occ[$s + $i])) { $free = false; break; }
            }
            if ($free) $freeSlots[] = _tiStr($s);
        }
        if (!empty($freeSlots)) $result[] = ['frizer' => $fr, 'slots' => $freeSlots];
    }
    return $result;
}
function _useToken($conn, $token) {
    $st = $conn->prepare("UPDATE termin_akcija_token SET Iskoriscen=1 WHERE Token=?");
    $st->bind_param('s', $token);
    $st->execute();
    $st->close();
}
function _loadRec($conn, $token) {
    $st = $conn->prepare(
        "SELECT tat.TokenId, tat.TerminId, tat.IsticeOd, tat.Iskoriscen,
                t.Datum, t.Vreme, t.KorisnikId, t.KorisnikFrizerId,
                t.Otkazano, t.Uradjeno,
                COALESCE(u.Naziv, 'Usluga') AS UslugaNaziv,
                COALESCE(u.Trajanje, 30) AS Trajanje,
                k.Ime  AS KIme,  k.Prezime  AS KPrezime,  k.Email AS KEmail,
                kf.Ime AS FIme,  kf.Prezime AS FPrezime
         FROM termin_akcija_token tat
         JOIN termin t  ON tat.TerminId   = t.TerminId
         LEFT JOIN usluga   u  ON t.UslugaId        = u.UslugaId
         LEFT JOIN korisnik k  ON t.KorisnikId       = k.KorisnikId
         LEFT JOIN korisnik kf ON t.KorisnikFrizerId = kf.KorisnikId
         WHERE tat.Token = ?"
    );
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row;
}

// ── Main logic ───────────────────────────────────────
$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$step     = trim($_POST['step'] ?? '');
$tokenErr = '';
$poruka   = '';
$view     = 'main';
$rec      = null;
$viewData = [];

if (!$token || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $tokenErr = 'Nevažeći link.';
} else {
    $rec = _loadRec($conn, $token);
    if (!$rec)                                          $tokenErr = 'Link nije pronađen ili je nevažeći.';
    elseif ($rec['Iskoriscen'])                         $tokenErr = 'Ovaj link je već iskorišćen.';
    elseif ($rec['IsticeOd'] < date('Y-m-d H:i:s'))    $tokenErr = 'Link je istekao.';
    elseif ((int)$rec['Otkazano'])                      $tokenErr = 'Vaš termin je već otkazan.';
    elseif ((int)$rec['Uradjeno'])                      $tokenErr = 'Vaš termin je već realizovan.';
}

if (!$tokenErr && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ukupno = max(1, (int)ceil((int)$rec['Trajanje'] / 30));

    if ($step === 'cancel') {
        $upd = $conn->prepare("UPDATE termin SET Otkazano=1 WHERE TerminId=?");
        $upd->bind_param('i', $rec['TerminId']);
        $upd->execute();
        $upd->close();
        _useToken($conn, $token);
        $view = 'success_cancel';

    } elseif ($step === 'frizer') {
        $frizeri = _frizeriDostupni($conn, $rec['Datum'], (int)$rec['Vreme'], $ukupno);
        if (empty($frizeri)) {
            $poruka = 'Nema dostupnih frizera za ovaj termin. Možete pomeriti na drugi datum.';
            $view = 'main';
        } else {
            $viewData['frizeri'] = $frizeri;
            $view = 'frizer';
        }

    } elseif ($step === 'frizer_confirm') {
        $noviFrizerId = trim($_POST['frizer'] ?? '');
        $dostupni     = _frizeriDostupni($conn, $rec['Datum'], (int)$rec['Vreme'], $ukupno);
        $validIds     = array_column($dostupni, 'KorisnikId');
        if (!$noviFrizerId || !in_array($noviFrizerId, $validIds)) {
            $poruka = 'Izabrani frizer nije dostupan. Pokušajte ponovo.';
            $viewData['frizeri'] = $dostupni;
            $view = 'frizer';
        } else {
            $sfn = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
            $sfn->bind_param('s', $noviFrizerId);
            $sfn->execute();
            $nfRow = $sfn->get_result()->fetch_assoc();
            $sfn->close();

            $upd = $conn->prepare("UPDATE termin SET KorisnikFrizerId=? WHERE TerminId=?");
            $upd->bind_param('si', $noviFrizerId, $rec['TerminId']);
            $upd->execute();
            $upd->close();
            _useToken($conn, $token);

            if (!empty($rec['KEmail'])) {
                $noviNaziv = $nfRow ? $nfRow['Ime'].' '.$nfRow['Prezime'] : $noviFrizerId;
                emailPotvrdaZahteva($rec['KEmail'],
                    $rec['KIme'].' '.$rec['KPrezime'],
                    $rec['UslugaNaziv'],
                    $rec['Datum'],
                    _tiStr((int)$rec['Vreme']),
                    $noviNaziv);
            }
            $viewData['noviNaziv'] = $nfRow ? $nfRow['Ime'].' '.$nfRow['Prezime'] : $noviFrizerId;
            $viewData['datum']     = $rec['Datum'];
            $viewData['vreme']     = _tiStr((int)$rec['Vreme']);
            $view = 'success_frizer';
        }

    } elseif ($step === 'datum') {
        $view = 'datum';

    } elseif ($step === 'datum_slotovi') {
        $newDatum = trim($_POST['datum'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDatum) || $newDatum < date('Y-m-d')) {
            $poruka = 'Izaberite važeći datum (danas ili u budućnosti).';
            $view = 'datum';
        } else {
            $slotovi = _frizeriSaSlotovima($conn, $newDatum, $ukupno);
            if (empty($slotovi)) {
                $poruka = 'Nema slobodnih termina za izabrani datum. Pokušajte drugi datum.';
                $view = 'datum';
            } else {
                $viewData['slotovi'] = $slotovi;
                $viewData['datum']   = $newDatum;
                $view = 'datum_slotovi';
            }
        }

    } elseif ($step === 'datum_confirm') {
        $newDatum    = trim($_POST['datum']   ?? '');
        $newVremeStr = trim($_POST['vreme']   ?? '');
        $newFrizerId = trim($_POST['frizer']  ?? '');
        $valid       = true;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDatum) || $newDatum < date('Y-m-d')) {
            $poruka = 'Nevažeći datum. Pokušajte ponovo.'; $valid = false;
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $newVremeStr)) {
            $poruka = 'Nevažeće vreme. Pokušajte ponovo.'; $valid = false;
        }

        if ($valid) {
            $newSlot = _slotIzStr($newVremeStr);
            if ($newSlot < 18 || $newSlot > 34 - $ukupno) {
                $poruka = 'Nevažeće vreme. Pokušajte ponovo.'; $valid = false;
            }
        }

        if ($valid) {
            // Re-validate that slot is still free
            $slotovi  = _frizeriSaSlotovima($conn, $newDatum, $ukupno);
            $found    = false;
            foreach ($slotovi as $entry) {
                if ($entry['frizer']['KorisnikId'] === $newFrizerId
                    && in_array($newVremeStr, $entry['slots'])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $poruka = 'Izabrani termin više nije dostupan. Pokušajte ponovo.';
                $viewData['slotovi'] = $slotovi;
                $viewData['datum']   = $newDatum;
                $view = 'datum_slotovi';
                $valid = false;
            }
        }

        if ($valid) {
            $newSlot = _slotIzStr($newVremeStr);
            $upd = $conn->prepare("UPDATE termin SET Datum=?, Vreme=?, KorisnikFrizerId=? WHERE TerminId=?");
            $upd->bind_param('sisi', $newDatum, $newSlot, $newFrizerId, $rec['TerminId']);
            $upd->execute();
            $upd->close();
            _useToken($conn, $token);

            $sfn = $conn->prepare("SELECT Ime, Prezime FROM korisnik WHERE KorisnikId=?");
            $sfn->bind_param('s', $newFrizerId);
            $sfn->execute();
            $nfRow = $sfn->get_result()->fetch_assoc();
            $sfn->close();
            $noviNaziv = $nfRow ? $nfRow['Ime'].' '.$nfRow['Prezime'] : $newFrizerId;

            if (!empty($rec['KEmail'])) {
                emailPotvrdaZahteva($rec['KEmail'],
                    $rec['KIme'].' '.$rec['KPrezime'],
                    $rec['UslugaNaziv'],
                    $newDatum,
                    $newVremeStr,
                    $noviNaziv);
            }
            $viewData['noviNaziv'] = $noviNaziv;
            $viewData['datum']     = $newDatum;
            $viewData['vreme']     = $newVremeStr;
            $view = 'success_datum';
        }
    }
}

// Helpers for display
$frizerNazivRec = $rec ? trim(($rec['FIme'] ?? '') . ' ' . ($rec['FPrezime'] ?? '')) : '';
$klijentNaziv   = $rec ? trim(($rec['KIme'] ?? '') . ' ' . ($rec['KPrezime'] ?? '')) : '';
$veziStr        = $rec ? date('d.m.Y.', strtotime($rec['Datum'])) : '';
$vremeStr       = $rec ? _tiStr((int)($rec['Vreme'] ?? 0)) : '';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mojstil.css">
    <title>Izmena termina – Frizerski salon</title>
    <style>
        .ti-page { max-width: 500px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
        .ti-brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(176,164,148,0.1);
            margin-bottom: 2.5rem;
        }
        .ti-brand a { text-decoration: none; }
        .ti-brand-name {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1rem;
            font-weight: 400;
            color: #d4a828;
            text-transform: uppercase;
            letter-spacing: .2em;
        }
        .ti-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(176,164,148,0.14);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .ti-card-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.35rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin: 0 0 1rem;
        }
        .ti-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 0.45rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-family: 'Jost', sans-serif;
            font-size: 0.85rem;
        }
        .ti-detail-row:last-child { border-bottom: none; }
        .ti-detail-label { color: rgba(255,255,255,0.45); }
        .ti-detail-val { color: rgba(255,255,255,0.88); font-weight: 500; text-align: right; }
        .ti-actions { display: flex; flex-direction: column; gap: 0.7rem; }
        .ti-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.2rem;
            border-radius: 10px;
            border: 1px solid rgba(176,164,148,0.2);
            background: rgba(255,255,255,0.03);
            color: rgba(255,255,255,0.82);
            font-family: 'Jost', sans-serif;
            font-size: 0.88rem;
            font-weight: 400;
            cursor: pointer;
            text-align: left;
            width: 100%;
            transition: background 0.15s, border-color 0.15s;
        }
        .ti-btn:hover { background: rgba(196,167,108,0.08); border-color: rgba(196,167,108,0.35); }
        .ti-btn--danger {
            border-color: rgba(180,60,60,0.25);
            color: #d47070;
        }
        .ti-btn--danger:hover { background: rgba(180,60,60,0.08); border-color: rgba(180,60,60,0.45); }
        .ti-btn-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ti-btn-icon--gold { background: rgba(196,167,108,0.12); color: #c4a76c; }
        .ti-btn-icon--red  { background: rgba(180,60,60,0.1); color: #d47070; }
        .ti-btn-text { display: flex; flex-direction: column; gap: 0.1rem; }
        .ti-btn-sub { font-size: 0.72rem; color: rgba(255,255,255,0.38); font-weight: 300; }
        .ti-back {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-family: 'Jost', sans-serif; font-size: 0.8rem;
            color: rgba(255,255,255,0.4); text-decoration: none;
            margin-bottom: 1.2rem;
            transition: color 0.15s;
        }
        .ti-back:hover { color: rgba(196,167,108,0.8); }
        .ti-heading {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.2rem; font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin: 0 0 1.2rem;
        }
        .ti-frizer-list { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.2rem; }
        .ti-frizer-row {
            display: flex; align-items: center; gap: 0.85rem;
            padding: 0.75rem 1rem; border-radius: 10px;
            border: 1px solid rgba(176,164,148,0.15);
            background: rgba(255,255,255,0.025);
            cursor: pointer; transition: all 0.15s;
        }
        .ti-frizer-row:has(input:checked) {
            border-color: rgba(196,167,108,0.55);
            background: rgba(196,167,108,0.07);
        }
        .ti-frizer-row input { display: none; }
        .ti-frizer-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(196,167,108,0.15);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Cormorant Garamond', serif; font-size: 0.88rem;
            color: #c4a76c; font-weight: 600; flex-shrink: 0;
        }
        .ti-frizer-name {
            font-family: 'Jost', sans-serif; font-size: 0.88rem;
            color: rgba(255,255,255,0.82); flex: 1;
        }
        .ti-frizer-check { color: #c4a76c; opacity: 0; transition: opacity 0.15s; }
        .ti-frizer-row:has(input:checked) .ti-frizer-check { opacity: 1; }
        .ti-submit {
            width: 100%; padding: 0.8rem; border-radius: 8px;
            background: rgba(196,167,108,0.15);
            border: 1px solid rgba(196,167,108,0.4);
            color: #c4a76c;
            font-family: 'Jost', sans-serif; font-size: 0.88rem; font-weight: 500;
            cursor: pointer; letter-spacing: 0.04em;
            transition: background 0.15s;
        }
        .ti-submit:hover { background: rgba(196,167,108,0.22); }
        .ti-submit:disabled { opacity: 0.4; cursor: default; }
        .ti-date-field { margin-bottom: 1.2rem; }
        .ti-date-field label {
            display: block; margin-bottom: 0.4rem;
            font-family: 'Jost', sans-serif; font-size: 0.78rem;
            color: rgba(255,255,255,0.45); letter-spacing: 0.06em; text-transform: uppercase;
        }
        .ti-date-field input[type=date] {
            width: 100%; padding: 0.65rem 0.9rem; border-radius: 8px;
            background: rgba(255,255,255,0.04); border: 1px solid rgba(176,164,148,0.2);
            color: rgba(255,255,255,0.85); font-family: 'Jost', sans-serif; font-size: 0.9rem;
            box-sizing: border-box;
        }
        .ti-success-icon {
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(60,180,100,0.12); border: 1px solid rgba(60,180,100,0.25);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.2rem;
        }
        .ti-success-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.4rem; font-weight: 600;
            color: rgba(255,255,255,0.92); margin: 0 0 0.5rem; text-align: center;
        }
        .ti-success-sub {
            font-family: 'Jost', sans-serif; font-size: 0.85rem;
            color: rgba(255,255,255,0.5); text-align: center; margin: 0 0 1.5rem;
        }
        .ti-home-link {
            display: block; text-align: center;
            font-family: 'Jost', sans-serif; font-size: 0.82rem;
            color: rgba(196,167,108,0.65); text-decoration: none;
        }
        .ti-home-link:hover { color: #c4a76c; }
        .ti-err-box {
            text-align: center; padding: 3rem 1rem;
        }
        .ti-err-icon {
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(180,60,60,0.1); border: 1px solid rgba(180,60,60,0.25);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }
        .ti-err-title {
            font-family: 'Cormorant Garamond', serif; font-size: 1.3rem;
            color: rgba(255,255,255,0.8); margin: 0 0 0.5rem;
        }
        .ti-err-sub {
            font-family: 'Jost', sans-serif; font-size: 0.85rem;
            color: rgba(255,255,255,0.42);
        }
        .ti-alert {
            padding: 0.75rem 1rem; border-radius: 8px;
            background: rgba(180,60,60,0.08); border: 1px solid rgba(180,60,60,0.22);
            color: #d47070; font-family: 'Jost', sans-serif; font-size: 0.83rem;
            margin-bottom: 1rem;
        }
        .ti-slot-hint {
            font-family: 'Jost', sans-serif; font-size: 0.78rem;
            color: rgba(196,167,108,0.75); min-height: 1.2em; margin: 0.5rem 0 0.9rem;
            letter-spacing: 0.04em;
        }
    </style>
</head>
<body style="background:#100f0c;min-height:100vh;padding-top:0;">

<div class="ti-brand">
    <a href="index.php"><span class="ti-brand-name">Frizerski salon</span></a>
</div>

<div class="ti-page">

<?php if ($tokenErr): ?>
<!-- ── Token error ─────────────────────────────────── -->
<div class="ti-err-box">
    <div class="ti-err-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#d47070" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="0.5" fill="#d47070"/></svg>
    </div>
    <p class="ti-err-title"><?= htmlspecialchars($tokenErr) ?></p>
    <p class="ti-err-sub">Ukoliko imate pitanja, kontaktirajte nas.</p>
    <a href="index.php" class="ti-home-link" style="margin-top:1.5rem;">← Početna stranica</a>
</div>

<?php elseif ($view === 'success_cancel'): ?>
<!-- ── Success: cancelled ──────────────────────────── -->
<div class="ti-card" style="text-align:center;padding:2rem 1.5rem;">
    <div class="ti-success-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#5dba7d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <p class="ti-success-title">Termin je otkazan</p>
    <p class="ti-success-sub">
        <?= htmlspecialchars($rec['UslugaNaziv']) ?> &bull;
        <?= htmlspecialchars($veziStr) ?> u <?= htmlspecialchars($vremeStr) ?>
    </p>
    <a href="index.php" class="ti-home-link">← Početna stranica</a>
</div>

<?php elseif ($view === 'success_frizer'): ?>
<!-- ── Success: frizer changed ────────────────────── -->
<div class="ti-card" style="text-align:center;padding:2rem 1.5rem;">
    <div class="ti-success-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#5dba7d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <p class="ti-success-title">Termin je prebačen</p>
    <p class="ti-success-sub">
        <?= htmlspecialchars($rec['UslugaNaziv']) ?> &bull;
        <?= htmlspecialchars(date('d.m.Y.', strtotime($viewData['datum']))) ?> u <?= htmlspecialchars($viewData['vreme']) ?><br>
        Frizer: <strong style="color:rgba(255,255,255,0.75);"><?= htmlspecialchars($viewData['noviNaziv']) ?></strong>
    </p>
    <a href="index.php" class="ti-home-link">← Početna stranica</a>
</div>

<?php elseif ($view === 'success_datum'): ?>
<!-- ── Success: date/time changed ─────────────────── -->
<div class="ti-card" style="text-align:center;padding:2rem 1.5rem;">
    <div class="ti-success-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#5dba7d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <p class="ti-success-title">Termin je pomeren</p>
    <p class="ti-success-sub">
        <?= htmlspecialchars($rec['UslugaNaziv']) ?><br>
        <?= htmlspecialchars(date('d.m.Y.', strtotime($viewData['datum']))) ?> u <?= htmlspecialchars($viewData['vreme']) ?><br>
        Frizer: <strong style="color:rgba(255,255,255,0.75);"><?= htmlspecialchars($viewData['noviNaziv']) ?></strong>
    </p>
    <a href="index.php" class="ti-home-link">← Početna stranica</a>
</div>

<?php elseif ($view === 'frizer'): ?>
<!-- ── Choose replacement frizer ──────────────────── -->
<a class="ti-back" href="termin_izmena.php?token=<?= urlencode($token) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
    Nazad
</a>
<p class="ti-heading">Izaberite novog frizera</p>
<p style="font-family:'Jost',sans-serif;font-size:0.82rem;color:rgba(255,255,255,0.4);margin:-0.5rem 0 1.1rem;">
    Isti termin: <?= htmlspecialchars($veziStr) ?> u <?= htmlspecialchars($vremeStr) ?>
</p>
<?php if ($poruka): ?><div class="ti-alert"><?= htmlspecialchars($poruka) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="step"  value="frizer_confirm">
    <div class="ti-frizer-list">
    <?php foreach ($viewData['frizeri'] as $fr):
        $initials = mb_strtoupper(mb_substr($fr['Ime'], 0, 1) . mb_substr($fr['Prezime'], 0, 1));
    ?>
    <label class="ti-frizer-row">
        <input type="radio" name="frizer" value="<?= htmlspecialchars($fr['KorisnikId']) ?>" required>
        <div class="ti-frizer-avatar"><?= htmlspecialchars($initials) ?></div>
        <span class="ti-frizer-name"><?= htmlspecialchars($fr['Ime'].' '.$fr['Prezime']) ?></span>
        <div class="ti-frizer-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
    </label>
    <?php endforeach; ?>
    </div>
    <button type="submit" class="ti-submit">Potvrdi izbor frizera</button>
</form>

<?php elseif ($view === 'datum'): ?>
<!-- ── Choose new date ────────────────────────────── -->
<a class="ti-back" href="termin_izmena.php?token=<?= urlencode($token) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
    Nazad
</a>
<p class="ti-heading">Izaberite novi datum</p>
<?php if ($poruka): ?><div class="ti-alert"><?= htmlspecialchars($poruka) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="step"  value="datum_slotovi">
    <div class="ti-date-field">
        <label>Datum</label>
        <input type="date" name="datum" required min="<?= date('Y-m-d') ?>"
               value="<?= htmlspecialchars($_POST['datum'] ?? '') ?>">
    </div>
    <button type="submit" class="ti-submit">Prikaži slobodne termine</button>
</form>

<?php elseif ($view === 'datum_slotovi'): ?>
<!-- ── Choose frizer + time slot ─────────────────── -->
<?php
$newDatum = $viewData['datum'];
$slotovi  = $viewData['slotovi'];
?>
<a class="ti-back" href="termin_izmena.php?token=<?= urlencode($token) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
    Nazad
</a>
<p class="ti-heading">Izaberite termin</p>
<p style="font-family:'Jost',sans-serif;font-size:0.82rem;color:rgba(255,255,255,0.4);margin:-0.5rem 0 1.1rem;">
    <?= htmlspecialchars(date('d.m.Y.', strtotime($newDatum))) ?>
</p>
<?php if ($poruka): ?><div class="ti-alert"><?= htmlspecialchars($poruka) ?></div><?php endif; ?>
<form method="post" id="form-slot">
    <input type="hidden" name="token"  value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="step"   value="datum_confirm">
    <input type="hidden" name="datum"  value="<?= htmlspecialchars($newDatum) ?>">
    <input type="hidden" name="frizer" id="sel-frizer" value="">
    <input type="hidden" name="vreme"  id="sel-vreme"  value="">

    <div class="zk-slot-section">
    <?php foreach ($slotovi as $entry):
        $fId    = $entry['frizer']['KorisnikId'];
        $fNaziv = $entry['frizer']['Ime'].' '.$entry['frizer']['Prezime'];
        $fInit  = mb_strtoupper(mb_substr($entry['frizer']['Ime'], 0, 1) . mb_substr($entry['frizer']['Prezime'], 0, 1));
    ?>
    <div class="zk-slot-frizer" data-frizer="<?= htmlspecialchars($fId) ?>">
        <div class="zk-slot-frizer-header">
            <div class="zk-frizer-row-avatar"><?= htmlspecialchars($fInit) ?></div>
            <span><?= htmlspecialchars($fNaziv) ?></span>
        </div>
        <div class="zk-slot-times">
        <?php foreach ($entry['slots'] as $slot): ?>
            <button type="button" class="zk-slot-btn"
                    data-frizer="<?= htmlspecialchars($fId) ?>"
                    data-frizer-naziv="<?= htmlspecialchars($fNaziv) ?>"
                    data-vreme="<?= htmlspecialchars($slot) ?>">
                <?= htmlspecialchars($slot) ?>
            </button>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="ti-slot-hint" id="slot-hint"></div>
    <button type="submit" class="ti-submit" id="btn-potvrdi" disabled>Potvrdi termin</button>
</form>

<?php else: ?>
<!-- ── Main view ──────────────────────────────────── -->
<?php if ($poruka): ?><div class="ti-alert"><?= htmlspecialchars($poruka) ?></div><?php endif; ?>

<div class="ti-card">
    <p class="ti-card-title">Vaš termin</p>
    <div class="ti-detail-row">
        <span class="ti-detail-label">Usluga</span>
        <span class="ti-detail-val"><?= htmlspecialchars($rec['UslugaNaziv']) ?></span>
    </div>
    <div class="ti-detail-row">
        <span class="ti-detail-label">Datum</span>
        <span class="ti-detail-val"><?= htmlspecialchars($veziStr) ?></span>
    </div>
    <div class="ti-detail-row">
        <span class="ti-detail-label">Vreme</span>
        <span class="ti-detail-val"><?= htmlspecialchars($vremeStr) ?></span>
    </div>
    <div class="ti-detail-row">
        <span class="ti-detail-label">Frizer</span>
        <span class="ti-detail-val"><?= htmlspecialchars($frizerNazivRec) ?></span>
    </div>
</div>

<p style="font-family:'Jost',sans-serif;font-size:0.82rem;color:rgba(255,255,255,0.4);margin:0 0 1rem;">
    Vaš frizer nije dostupan u ovom terminu. Šta biste voleli da uradite?
</p>

<div class="ti-actions">
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="step"  value="frizer">
        <button type="submit" class="ti-btn">
            <div class="ti-btn-icon ti-btn-icon--gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="ti-btn-text">
                <span>Izaberite drugog frizera</span>
                <span class="ti-btn-sub">Isti datum i vreme, drugi frizer</span>
            </div>
        </button>
    </form>

    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="step"  value="datum">
        <button type="submit" class="ti-btn">
            <div class="ti-btn-icon ti-btn-icon--gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="ti-btn-text">
                <span>Pomerite termin</span>
                <span class="ti-btn-sub">Izaberite novi datum, vreme i frizera</span>
            </div>
        </button>
    </form>

    <form method="post" onsubmit="return confirm('Sigurno želite da otkažete termin?');">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="step"  value="cancel">
        <button type="submit" class="ti-btn ti-btn--danger">
            <div class="ti-btn-icon ti-btn-icon--red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div class="ti-btn-text">
                <span>Otkažite termin</span>
                <span class="ti-btn-sub">Termin se trajno otkazuje</span>
            </div>
        </button>
    </form>
</div>
<?php endif; ?>

</div>

<script>
(function () {
    // Slot picker for datum_slotovi view
    const selFrizer  = document.getElementById('sel-frizer');
    const selVreme   = document.getElementById('sel-vreme');
    const slotHint   = document.getElementById('slot-hint');
    const btnPotvrdi = document.getElementById('btn-potvrdi');

    document.querySelectorAll('.zk-slot-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.zk-slot-btn').forEach(function (b) {
                b.classList.remove('zk-slot-btn--active');
            });
            document.querySelectorAll('.zk-slot-frizer').forEach(function (s) {
                s.classList.remove('zk-slot-frizer--active');
            });
            btn.classList.add('zk-slot-btn--active');
            const fId    = btn.getAttribute('data-frizer');
            const fNaziv = btn.getAttribute('data-frizer-naziv');
            const vreme  = btn.getAttribute('data-vreme');
            const sec    = document.querySelector('.zk-slot-frizer[data-frizer="' + fId + '"]');
            if (sec) sec.classList.add('zk-slot-frizer--active');
            if (selFrizer) selFrizer.value = fId;
            if (selVreme)  selVreme.value  = vreme;
            if (slotHint)  slotHint.textContent = vreme + ' · ' + fNaziv;
            if (btnPotvrdi) btnPotvrdi.disabled = false;
        });
    });

    const formSlot = document.getElementById('form-slot');
    if (formSlot) {
        formSlot.addEventListener('submit', function (e) {
            if (!selFrizer || !selFrizer.value) {
                e.preventDefault();
                const section = document.querySelector('.zk-slot-section');
                if (section) {
                    section.classList.add('zk-slot-shake');
                    section.addEventListener('animationend', function () {
                        section.classList.remove('zk-slot-shake');
                    }, { once: true });
                }
            }
        });
    }
})();
</script>
</body>
</html>
