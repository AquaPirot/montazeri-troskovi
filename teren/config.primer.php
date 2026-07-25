<?php
/**
 * PROHORECA AG GROUP – TEREN
 * Podešavanja.
 *
 * Kopiraj ovaj fajl u config.php i upiši svoje podatke.
 * (cPanel > File Manager > Copy, ili preko FTP-a)
 */

return [
    // --- Baza podataka (cPanel > MySQL Databases) -----------------
    'db_host'  => 'localhost',
    'db_naziv' => 'korisnik_teren',
    'db_user'  => 'korisnik_teren',
    'db_pass'  => 'lozinka_baze',

    // --- Aplikacija ----------------------------------------------
    'naziv'    => 'Prohoreca AG group – Teren',

    // Vremenska zona za datume i vreme
    'zona'     => 'Europe/Belgrade',

    // Odstupanje potrošnje goriva preko kog se prikazuje upozorenje (u %)
    'prag_potrosnje' => 15,

    // Najveća dozvoljena veličina fotografije u megabajtima
    'max_foto_mb'    => 12,

    // Duža strana fotografije posle smanjivanja (u pikselima).
    // Smanjivanje radi samo ako je na serveru uključena GD ekstenzija.
    'foto_strana'    => 1600,
];
