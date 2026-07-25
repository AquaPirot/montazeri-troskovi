<?php
/**
 * Prikaz fotografije. Folder uploads/ nije dostupan direktno preko interneta –
 * fotografija se šalje tek posle provere da korisnik sme da je vidi.
 */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();

$f = basename(get('f'));
if ($f === '' || !preg_match('/^[A-Za-z0-9_.-]+\.(jpg|jpeg|png|webp)$/i', $f)) {
    http_response_code(404);
    exit('Fotografija nije pronađena.');
}

/* Kojem terenu fotografija pripada. */
$teren_id = vrednost(
    'SELECT id FROM tereni WHERE foto_km_start = ? OR foto_km_kraj = ?
      UNION SELECT teren_id FROM troskovi WHERE foto_racun = ? OR foto_slip = ?
      UNION SELECT teren_id FROM prijave  WHERE foto = ?
      LIMIT 1', [$f, $f, $f, $f, $f]);

if (!$teren_id) {
    http_response_code(404);
    exit('Fotografija nije pronađena.');
}

if ($k['uloga'] !== 'admin') {
    $moj = vrednost('SELECT COUNT(*) FROM tereni WHERE id = ? AND korisnik_id = ?', [$teren_id, $k['id']]);
    if (!$moj) {
        http_response_code(403);
        exit('Nemaš pristup toj fotografiji.');
    }
}

$putanja = folder_uploada() . '/' . $f;
if (!is_file($putanja)) {
    http_response_code(404);
    exit('Fotografija više ne postoji.');
}

$tipovi = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));

header('Content-Type: ' . ($tipovi[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($putanja));
header('Content-Disposition: inline; filename="' . $f . '"');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($putanja);
