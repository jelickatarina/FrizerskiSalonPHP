<?php
function posaljiEmail($to, $subject, $body) {
    if(empty($to)) return false;
    $from     = getenv('MAIL_FROM') ?: 'salon@frizerskisalon.rs';
    $fromName = 'Frizerski salon';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$from>\r\n";

    $smtpHost = getenv('SMTP_HOST');
    if($smtpHost) return _sendSmtp($to, $subject, $body, $from, $fromName);
    return @mail($to, $subject, $body, $headers);
}

function _sendSmtp($to, $subject, $body, $from, $fromName) {
    $host = getenv('SMTP_HOST');
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $user = getenv('SMTP_USER');
    $pass = getenv('SMTP_PASS');
    try {
        $sock = fsockopen("tcp://$host", $port, $errno, $errstr, 10);
        if(!$sock) return false;
        fgets($sock, 512);
        $cmd = function($c) use ($sock) { fwrite($sock, $c."\r\n"); return fgets($sock, 512); };
        $cmd("EHLO ".gethostname());
        if($port == 587) {
            $cmd("STARTTLS");
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd("EHLO ".gethostname());
        }
        $cmd("AUTH LOGIN");
        $cmd(base64_encode($user));
        $cmd(base64_encode($pass));
        $cmd("MAIL FROM:<$from>");
        $cmd("RCPT TO:<$to>");
        $cmd("DATA");
        $msg  = "From: $fromName <$from>\r\nTo: $to\r\n";
        $msg .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
        $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $body."\r\n.";
        $cmd($msg);
        $cmd("QUIT");
        fclose($sock);
        return true;
    } catch(Exception $e) { return false; }
}

function _emailTpl($title, $content) {
    return "
    <div style='font-family:sans-serif;max-width:500px;margin:0 auto;background:#100f0c;color:#f0ece4;border-radius:10px;overflow:hidden;'>
      <div style='background:#1a1814;padding:1.4rem 2rem;border-bottom:1px solid #28241c;'>
        <span style='font-family:Georgia,serif;font-size:1.1rem;letter-spacing:.2em;text-transform:uppercase;color:#d4a828;'>Frizerski salon</span>
      </div>
      <div style='padding:2rem;'>
        <h2 style='color:#d4a828;margin:0 0 1rem;font-family:Georgia,serif;'>$title</h2>
        $content
      </div>
      <div style='padding:.8rem 2rem;background:#0d0c09;font-size:.75rem;color:#555;'>
        Frizerski salon &bull; Bulevar oslobodjenja 45, Beograd
      </div>
    </div>";
}

function _emailTerminTabela($usluga, $datum, $vreme, $frizer) {
    $d = date('d.m.Y.', strtotime($datum));
    return "
    <table style='width:100%;border-collapse:collapse;margin:1rem 0;'>
      <tr><td style='padding:7px 0;color:#888;border-bottom:1px solid #222;'>Usluga</td><td style='border-bottom:1px solid #222;'><strong>$usluga</strong></td></tr>
      <tr><td style='padding:7px 0;color:#888;border-bottom:1px solid #222;'>Datum</td><td style='border-bottom:1px solid #222;'><strong>$d</strong></td></tr>
      <tr><td style='padding:7px 0;color:#888;border-bottom:1px solid #222;'>Vreme</td><td style='border-bottom:1px solid #222;'><strong>$vreme</strong></td></tr>
      <tr><td style='padding:7px 0;color:#888;'>Frizer</td><td><strong>$frizer</strong></td></tr>
    </table>";
}

function emailNoviZahtev($frizerEmail, $frizerIme, $klijentIme, $usluga, $datum, $vreme) {
    $tbl = _emailTerminTabela($usluga, $datum, $vreme, '—');
    $body = _emailTpl("Novi zahtev za termin",
        "<p>Zdravo, <strong>$frizerIme</strong>!</p>
         <p>Klijent <strong>$klijentIme</strong> želi da zakaže termin:</p>
         $tbl
         <p style='margin-top:1.2rem;'>Prijavite se na sajt i potvrdite ili odbijte zahtev.</p>");
    posaljiEmail($frizerEmail, "Novi zahtev za termin – Frizerski salon", $body);
}

function emailPotvrdaZahteva($klijentEmail, $klijentIme, $usluga, $datum, $vreme, $frizerNaziv) {
    $tbl = _emailTerminTabela($usluga, $datum, $vreme, $frizerNaziv);
    $body = _emailTpl("Termin potvrđen ✓",
        "<p>Zdravo, <strong>$klijentIme</strong>!</p>
         <p>Vaš zahtev za termin je potvrđen:</p>
         $tbl
         <p style='margin-top:1.2rem;color:#888;'>Vidimo se! 💇</p>");
    posaljiEmail($klijentEmail, "Termin potvrđen – Frizerski salon", $body);
}

function emailOdsustvo($klijentEmail, $klijentIme, $usluga, $datum, $vreme, $frizerNaziv, $akcijaUrl) {
    $tbl = _emailTerminTabela($usluga, $datum, $vreme, $frizerNaziv);
    $btnStyle = "display:inline-block;padding:.65rem 2rem;background:#d4a828;color:#0d0c09;border-radius:6px;text-decoration:none;font-weight:600;font-family:sans-serif;letter-spacing:.04em;font-size:.95rem;";
    $body = _emailTpl("Vaš termin zahteva izmenu",
        "<p>Zdravo, <strong>" . htmlspecialchars($klijentIme) . "</strong>!</p>
         <p>Vaš frizer <strong>" . htmlspecialchars($frizerNaziv) . "</strong> neće biti dostupan u vreme Vašeg termina.</p>
         $tbl
         <p style='margin:.5rem 0 1.5rem;'>Molimo Vas da izaberete šta raditi sa terminom:</p>
         <p style='text-align:center;margin:1.5rem 0;'>
           <a href='" . htmlspecialchars($akcijaUrl) . "' style='$btnStyle'>Upravljaj terminom →</a>
         </p>
         <p style='font-size:.75rem;color:#666;margin-top:1.5rem;'>
           Možete otkazati termin, izabrati drugog frizera ili pomeriti na novi datum.<br>
           Link važi 7 dana.
         </p>");
    posaljiEmail($klijentEmail, "Vaš termin zahteva izmenu – Frizerski salon", $body);
}

function emailPodsetnik($klijentEmail, $klijentIme, $usluga, $datum, $vreme, $frizerNaziv) {
    $tbl = _emailTerminTabela($usluga, $datum, $vreme, $frizerNaziv);
    $body = _emailTpl("Podsetnik: termin sutra 📅",
        "<p>Zdravo, <strong>$klijentIme</strong>!</p>
         <p>Podsetnik da imate zakazan termin <strong>sutra</strong>:</p>
         $tbl");
    posaljiEmail($klijentEmail, "Podsetnik za termin – Frizerski salon", $body);
}
