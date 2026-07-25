<?php
/**
 * Zajednički delovi izgleda: ikone, zaglavlje, podnožje, elementi forme.
 */
if (!defined('TEREN')) { http_response_code(403); exit('Zabranjen pristup.'); }

/* ============================================================
   IKONE (SVG, bez spoljnih fajlova)
   ============================================================ */
function ikona(string $ime, int $v = 22): string
{
    static $p = [
        'kamion'    => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/>',
        'racun'     => '<path d="M6 3h12v18l-2.5-1.6L13 21l-2.5-1.6L8 21l-2-1.5z"/><path d="M9.5 8h5M9.5 12h5"/>',
        'alarm'     => '<path d="M12 3l9.5 17H2.5z"/><path d="M12 9.5v4.5M12 17h.01"/>',
        'povratak'  => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/><path d="M10 20v-5.5h4V20"/>',
        'strelica'  => '<path d="M9 5l7 7-7 7"/>',
        'nazad'     => '<path d="M15 5l-7 7 7 7"/>',
        'kamera'    => '<path d="M3 8h4l1.5-2h7L17 8h4v12H3z"/><circle cx="12" cy="13.5" r="3.6"/>',
        'cek'       => '<path d="M4.5 12.5l5 5 10-11"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'gorivo'    => '<path d="M4 21V4.5A1.5 1.5 0 0 1 5.5 3h6A1.5 1.5 0 0 1 13 4.5V21"/><path d="M3 21h11"/><path d="M4 10h9"/><path d="M16 8l3 3v7a1.6 1.6 0 0 1-3.2 0V14H13"/>',
        'put'       => '<path d="M9 3v4M9 11v4M9 19v2M15 3v2M15 9v4M15 17v4"/><path d="M4 21 7 3M20 21 17 3"/>',
        'krevet'    => '<path d="M3 19v-9h11a5 5 0 0 1 5 5v4"/><path d="M3 14h16"/><circle cx="7" cy="12.5" r="1.6"/><path d="M3 19v2M21 19v2"/>',
        'hrana'     => '<path d="M5 3v8a2.5 2.5 0 0 0 5 0V3"/><path d="M7.5 3v18"/><path d="M17 3c-1.5 2-2 3.5-2 6s.8 3.5 2 3.5V21"/>',
        'materijal' => '<path d="M3.5 8 12 3.5 20.5 8v8L12 20.5 3.5 16z"/><path d="M3.5 8 12 12.5 20.5 8M12 12.5V20.5"/>',
        'ostalo'    => '<circle cx="12" cy="12" r="8.5"/><path d="M9.8 9.6a2.3 2.3 0 1 1 3.3 2.1c-.7.4-1.1.9-1.1 1.7"/><path d="M12 16.6h.01"/>',
        'lom'       => '<path d="m13.5 3-7 9.5H12l-1.5 8.5 7-10H12z"/>',
        'kljuc'     => '<path d="M14.5 6.5a4.5 4.5 0 1 0 3 4.2l3.5 3.5-2 2-1.6-1.6-1.6 1.6-1.6-1.6"/><circle cx="10.5" cy="10.5" r="1.6"/>',
        'posao'     => '<path d="M4 7h16v13H4z"/><path d="M9 7V4h6v3"/><path d="M4 12h16"/>',
        'novac'     => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/>',
        'kartica'   => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="M6 14.5h4"/>',
        'novcanik'  => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H19v3"/><path d="M3 7.5V18a2 2 0 0 0 2 2h15V8H5.5"/><circle cx="16.5" cy="14" r="1.2"/>',
        'korisnik'  => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5c1.2-3.6 4-5.4 7.5-5.4s6.3 1.8 7.5 5.4"/>',
        'lista'     => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'grafik'    => '<path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/>',
        'sat'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'izlaz'     => '<path d="M15 4h4v16h-4"/><path d="M11 8l-4 4 4 4M7 12h9"/>',
        'stit'      => '<path d="M12 3l7.5 3v6c0 4.4-3 7.6-7.5 9-4.5-1.4-7.5-4.6-7.5-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'olovka'    => '<path d="M4 20h4L20 8l-4-4L4 16z"/><path d="M14.5 5.5 18.5 9.5"/>',
        'slika'     => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.5" cy="10" r="1.8"/><path d="m3.5 18 5-5 3.5 3.5L16 13l4.5 4.5"/>',
        'kanta'     => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>',
        'vrati'     => '<path d="M9.5 5 4 10.5 9.5 16"/><path d="M4 10.5h10a6 6 0 0 1 0 12h-3"/>',
        'kljucic'   => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.4"/>',
    ];
    $d = $p[$ime] ?? '';
    return '<svg width="' . $v . '" height="' . $v . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
         . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* ============================================================
   OKVIR STRANE
   ============================================================ */
function pocetak_strane(string $naslov, array $o = []): void
{
    $p = podesavanja();
    $k = ja();
    ?><!DOCTYPE html>
<html lang="sr-Latn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#22252A">
<meta name="robots" content="noindex, nofollow">
<title><?= h($naslov) ?> · <?= h($p['naziv']) ?></title>
<link rel="stylesheet" href="assets/css/teren.css?v=1">
</head>
<body>
<div id="app">
<?php if (!empty($o['bez_zaglavlja'])) { return; } ?>
<div class="top">
    <?php if (!empty($o['nazad'])): ?>
        <a class="nazad" href="<?= h($o['nazad']) ?>" aria-label="Nazad"><?= ikona('nazad', 20) ?></a>
    <?php endif; ?>
    <div class="rast">
        <div class="marka">Prohoreca AG group</div>
        <div class="naslov"><?= h($o['naslov_gore'] ?? $naslov) ?></div>
    </div>
    <?php if ($k): ?>
        <a class="avatar" href="index.php?s=nalog" aria-label="Nalog"><?= h(inicijali($k['ime'])) ?></a>
    <?php endif; ?>
</div>
<?php
    poruka_html();
}

function poruka_html(): void
{
    $p = uzmi_poruku();
    if (!$p) return;
    $kl = ['uspeh' => 'p-uspeh', 'greska' => 'p-greska', 'info' => 'p-info'][$p['v']] ?? 'p-info';
    $ik = ['uspeh' => 'cek', 'greska' => 'alarm', 'info' => 'ostalo'][$p['v']] ?? 'ostalo';
    echo '<div class="traka-poruka ' . $kl . '">' . ikona($ik, 18) . '<span>' . h($p['t']) . '</span></div>';
}

function kraj_strane(string $nav = ''): void
{
    echo $nav;
    ?>
</div>
<script src="assets/js/teren.js?v=1"></script>
</body>
</html>
<?php
}

/* ============================================================
   DONJA NAVIGACIJA
   ============================================================ */
function nav_sef(string $aktivna): string
{
    $s = [
        'pocetna' => ['Početna', 'kamion'],
        'moji'    => ['Moji tereni', 'lista'],
    ];
    return nav_html($s, $aktivna);
}

function nav_admin(string $aktivna): string
{
    $s = [
        'admin'     => ['Tereni', 'lista'],
        'vozila'    => ['Vozila', 'kamion'],
        'korisnici' => ['Korisnici', 'korisnik'],
    ];
    return nav_html($s, $aktivna);
}

function nav_html(array $stavke, string $aktivna): string
{
    $h = '<nav class="nav">';
    foreach ($stavke as $kljuc => $x) {
        $a = $kljuc === $aktivna ? ' class="aktivan"' : '';
        $h .= '<a href="index.php?s=' . $kljuc . '"' . $a . '>' . ikona($x[1], 22)
            . '<span>' . h($x[0]) . '</span><i class="pod"></i></a>';
    }
    return $h . '</nav>';
}

/* ============================================================
   ELEMENTI
   ============================================================ */
function oznaka_statusa(string $status): string
{
    $s = STATUSI[$status] ?? ['n' => $status, 'kl' => 'o-siva'];
    return '<span class="oznaka ' . $s['kl'] . '"><span class="tacka"></span>' . h($s['n']) . '</span>';
}

/** Veliko dugme na početnom ekranu šefa ekipe. */
function veliko_dugme(string $ik, string $t1, string $t2, ?string $href, bool $istaknuto = false): string
{
    $kl = 'akcija' . ($istaknuto ? ' istaknuta' : '');
    $unutra = '<span class="ikona">' . ikona($ik, 26) . '</span>'
            . '<span class="tekst"><span class="t1">' . h($t1) . '</span><span class="t2">' . h($t2) . '</span></span>'
            . '<span class="str">' . ikona('strelica', 20) . '</span>';
    if ($href === null) {
        return '<div class="' . $kl . ' iskljucena">' . $unutra . '</div>';
    }
    return '<a class="' . $kl . '" href="' . h($href) . '">' . $unutra . '</a>';
}

/** Veliki red sa kvadratićem za potvrdu. */
function potvrda(string $ime, string $tekst, bool $cekirano = false): string
{
    return '<label class="potvrda' . ($cekirano ? ' cek' : '') . '" data-potvrda>'
         . '<input type="checkbox" name="' . h($ime) . '" value="1"' . ($cekirano ? ' checked' : '') . '>'
         . '<span class="kvadrat">' . ikona('cek', 16) . '</span>'
         . '<span class="txt">' . h($tekst) . '</span></label>';
}

/** Pločica za izbor (radio dugme prerušeno u dugme). */
function plocica(string $grupa, string $vrednost, string $natpis, bool $izabrana = false, string $ik = '', string $dodatnaKlasa = ''): string
{
    $kl = 'plocica ' . $dodatnaKlasa . ($izabrana ? ' izabrana' : '');
    return '<label class="' . trim($kl) . '" data-izbor>'
         . '<input type="radio" name="' . h($grupa) . '" value="' . h($vrednost) . '"' . ($izabrana ? ' checked' : '') . '>'
         . ($ik ? '<span class="pi">' . ikona($ik, 22) . '</span>' : '')
         . '<span class="pn">' . h($natpis) . '</span></label>';
}

/** Red iz liste izbora (vozilo, način plaćanja, tip prijave). */
function izbor_red(string $grupa, string $vrednost, string $natpis, string $podnatpis = '', bool $izabran = false, string $ik = 'ostalo'): string
{
    return '<label class="izbor' . ($izabran ? ' izabrana' : '') . '" data-izbor>'
         . '<input type="radio" name="' . h($grupa) . '" value="' . h($vrednost) . '"' . ($izabran ? ' checked' : '') . '>'
         . '<span class="ik">' . ikona($ik, 20) . '</span>'
         . '<span class="tx"><span class="nas">' . h($natpis) . '</span>'
         . ($podnatpis ? '<span class="pod2">' . h($podnatpis) . '</span>' : '') . '</span></label>';
}

/** Polje za fotografiju sa pregledom pre slanja. */
function foto_polje(string $ime, string $naslov, string $opis = 'Dodirni i slikaj telefonom'): string
{
    return '<label class="foto" data-foto>'
         . '<span class="ik">' . ikona('kamera', 22) . '</span>'
         . '<span class="tx"><span class="t1">' . h($naslov) . '</span>'
         . '<span class="t2">' . h($opis) . '</span></span>'
         . '<img class="pregled" alt="" hidden>'
         . '<input type="file" name="' . h($ime) . '" accept="image/*" capture="environment">'
         . '</label>';
}

function napomena_box(string $tekst, string $vrsta = 'info', string $ik = 'ostalo'): string
{
    $kl = ['info' => '', 'upozorenje' => ' upozorenje', 'greska' => ' greska', 'uspeh' => ' uspeh'][$vrsta] ?? '';
    return '<div class="napomena' . $kl . '">' . ikona($ik, 18) . '<div>' . $tekst . '</div></div>';
}

function prazno_stanje(string $ik, string $naslov, string $opis): string
{
    return '<div class="prazno"><div class="ik">' . ikona($ik, 44) . '</div>'
         . '<div class="t">' . h($naslov) . '</div><div class="p">' . h($opis) . '</div></div>';
}

/** Kartica jednog terena u listi. */
function stavka_terena(array $t, bool $prikaziSefa = false): string
{
    $km = ($t['km_kraj'] !== null) ? broj((int)$t['km_kraj'] - (int)$t['km_start'], 0) . ' km' : 'u toku';
    $h  = '<a class="stavka" href="index.php?s=izvestaj&id=' . (int)$t['id'] . '">';
    $h .= '<span class="gore"><span class="proj">' . h($t['projekat']) . '</span>' . oznaka_statusa($t['status']) . '</span>';
    $h .= '<span class="meta">';
    if ($prikaziSefa) $h .= '<span>' . ikona('korisnik', 13) . ' ' . h($t['sef_ime']) . '</span>';
    $h .= '<span>' . h($t['vozilo_naziv']) . ' · ' . h($t['vozilo_reg']) . '</span></span>';
    $h .= '<span class="meta"><span>' . datum($t['vreme_polaska'], false)
        . ($t['vreme_povratka'] ? ' – ' . datum($t['vreme_povratka'], false) : ' – u toku') . '</span></span>';
    $h .= '<span class="dole"><span class="cip">' . $km . '</span>';
    $h .= '<span class="cip">' . (int)$t['broj_troskova'] . ' troškova</span>';
    if ((int)$t['broj_prijava'] > 0) $h .= '<span class="cip">' . (int)$t['broj_prijava'] . ' prijava</span>';
    $h .= '</span></a>';
    return $h;
}

/** SQL za listu terena sa brojačima. */
function sql_lista_terena(): string
{
    return 'SELECT t.*, k.ime AS sef_ime, v.naziv AS vozilo_naziv, v.registracija AS vozilo_reg,
                   (SELECT COUNT(*) FROM troskovi x WHERE x.teren_id = t.id) AS broj_troskova,
                   (SELECT COUNT(*) FROM prijave  y WHERE y.teren_id = t.id) AS broj_prijava
              FROM tereni t
              JOIN korisnici k ON k.id = t.korisnik_id
              JOIN vozila    v ON v.id = t.vozilo_id';
}
