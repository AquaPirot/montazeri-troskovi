<?php
/**
 * PROHORECA AG GROUP – TEREN
 * Ulazna tačka aplikacije.
 */
declare(strict_types=1);
define('TEREN', 1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/funkcije.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/obracun.php';
require __DIR__ . '/inc/layout.php';

date_default_timezone_set(podesavanja()['zona'] ?? 'Europe/Belgrade');
pokreni_sesiju();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$strane = [
    // šef ekipe
    'prijava'   => 'prijava.php',
    'odjava'    => 'odjava.php',
    'pocetna'   => 'pocetna.php',
    'polazak'   => 'polazak.php',
    'trosak'    => 'trosak.php',
    'prijavi'   => 'prijavi.php',
    'povratak'  => 'povratak.php',
    'moji'      => 'moji.php',
    'izvestaj'  => 'izvestaj.php',
    'slika'     => 'slika.php',
    'nalog'     => 'nalog.php',
    // administrator
    'admin'     => 'admin_tereni.php',
    'vozila'    => 'admin_vozila.php',
    'korisnici' => 'admin_korisnici.php',
];

$s = get('s', prijavljen() ? (admin() ? 'admin' : 'pocetna') : 'prijava');
if (!isset($strane[$s])) {
    http_response_code(404);
    $s = prijavljen() ? (admin() ? 'admin' : 'pocetna') : 'prijava';
}

require __DIR__ . '/pages/' . $strane[$s];
