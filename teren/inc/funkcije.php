<?php
/**
 * Šifarnici, formatiranje, obrada fotografija, poruke.
 */
if (!defined('TEREN')) { http_response_code(403); exit('Zabranjen pristup.'); }

/* ============================================================
   ŠIFARNICI
   ============================================================ */
const VRSTE = [
    'gorivo'    => ['n' => 'Gorivo',           'ik' => 'gorivo'],
    'put'       => ['n' => 'Put i putarina',   'ik' => 'put'],
    'smestaj'   => ['n' => 'Smeštaj',          'ik' => 'krevet'],
    'hrana'     => ['n' => 'Hrana',            'ik' => 'hrana'],
    'materijal' => ['n' => 'Materijal',        'ik' => 'materijal'],
    'ostalo'    => ['n' => 'Ostalo',           'ik' => 'ostalo'],
];

const PLACANJA = [
    'gotovina' => ['n' => 'Gotovina iz depozita',    'k' => 'Gotovina',     'ik' => 'novac'],
    'kartica'  => ['n' => 'Službena kartica',        'k' => 'Kartica',      'ik' => 'kartica'],
    'licni'    => ['n' => 'Lični novac zaposlenog',  'k' => 'Lični novac',  'ik' => 'novcanik'],
];

const TIPOVI_PRIJAVE = [
    'lom'        => ['n' => 'Nešto je polomljeno',         'ik' => 'lom'],
    'materijal'  => ['n' => 'Treba materijal ili alat',    'ik' => 'kljuc'],
    'nezavrseno' => ['n' => 'Ostalo je nešto da se završi','ik' => 'posao'],
    'vozilo'     => ['n' => 'Problem sa vozilom',          'ik' => 'kamion'],
    'ostalo'     => ['n' => 'Ostala napomena',             'ik' => 'ostalo'],
];

/** Vrste unosa u servisnoj knjizi vozila. */
const VRSTE_SERVISA = [
    'servis'       => ['n' => 'Servis',       'ik' => 'kljuc'],
    'registracija' => ['n' => 'Registracija', 'ik' => 'dokument'],
    'popravka'     => ['n' => 'Popravka',     'ik' => 'alat'],
    'gume'         => ['n' => 'Gume',         'ik' => 'guma'],
    'ostalo'       => ['n' => 'Ostalo',       'ik' => 'ostalo'],
];

const STATUSI = [
    'aktivan'  => ['n' => 'Na terenu',          'kl' => 'o-plava'],
    'pregled'  => ['n' => 'Čeka pregled',       'kl' => 'o-narandzasta'],
    'ispravka' => ['n' => 'Vraćen na ispravku', 'kl' => 'o-crvena'],
    'odobren'  => ['n' => 'Odobren',            'kl' => 'o-zelena'],
];

/* ============================================================
   FORMATIRANJE
   ============================================================ */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 1234.5 -> "1.234,50" */
function broj($n, int $dec = 2): string
{
    return number_format((float)$n, $dec, ',', '.');
}

/** Iznos sa valutom; RSD bez decimala. */
function novac($n, string $valuta): string
{
    return broj($n, $valuta === 'RSD' ? 0 : 2) . ' ' . $valuta;
}

function kmF($n): string
{
    return $n === null ? '—' : broj($n, 0) . ' km';
}

/** "2026-07-22 06:40:00" -> "22.07.2026. 06:40" */
function datum(?string $dt, bool $saVremenom = true): string
{
    if (!$dt) return '—';
    $t = strtotime($dt);
    if (!$t) return '—';
    return date($saVremenom ? 'd.m.Y. H:i' : 'd.m.Y.', $t);
}

/** Samo datum: "22.07.2026." */
function datumDan(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d.m.Y.', $t) : '—';
}

/** Broj dana od danas do datuma (negativno = prošlo). */
function danaDo(?string $d): ?int
{
    if (!$d) return null;
    $t = strtotime($d);
    if (!$t) return null;
    return (int)floor(($t - strtotime('today')) / 86400);
}

/** "22.07. 06:40" – kraći oblik za liste */
function datumKratko(?string $dt): string
{
    if (!$dt) return '—';
    $t = strtotime($dt);
    return $t ? date('d.m. H:i', $t) : '—';
}

/**
 * Srpska množina: mnozina(1,'teren','terena','terena') -> "1 teren"
 *   1, 21, 31…  -> jednina        (ali ne 11)
 *   2–4, 22–24… -> dvojina        (ali ne 12–14)
 *   ostalo      -> množina
 */
function mnozina(int $n, string $jednina, string $dvojina, string $mnozina): string
{
    $j = abs($n) % 10;
    $d = abs($n) % 100;
    if ($j === 1 && $d !== 11)                 return $n . ' ' . $jednina;
    if ($j >= 2 && $j <= 4 && ($d < 12 || $d > 14)) return $n . ' ' . $dvojina;
    return $n . ' ' . $mnozina;
}

function inicijali(string $ime): string
{
    $d = preg_split('/\s+/u', trim($ime));
    $r = '';
    foreach ($d as $x) {
        if ($x !== '') $r .= mb_strtoupper(mb_substr($x, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($r, 'UTF-8') >= 2) break;
    }
    return $r;
}

/* ============================================================
   ULAZNI PODACI
   ============================================================ */
function post(string $k, string $podrazumevano = ''): string
{
    return isset($_POST[$k]) && is_scalar($_POST[$k]) ? trim((string)$_POST[$k]) : $podrazumevano;
}

/**
 * Broj unet onako kako se kod nas piše.
 * Prihvata sve ove oblike:
 *   "62,5"      -> 62.5      (zarez kao decimalni znak)
 *   "62.5"      -> 62.5      (tačka kao decimalni znak)
 *   "40.000"    -> 40000     (tačka kao znak za hiljade)
 *   "12.469,50" -> 12469.50  (i jedno i drugo)
 *   "1 250"     -> 1250      (razmak kao znak za hiljade)
 */
function postBroj(string $k): ?float
{
    $v = post($k);
    if ($v === '') return null;

    $v = str_replace(["\u{00A0}", ' '], '', $v);

    $imaTacku = str_contains($v, '.');
    $imaZarez = str_contains($v, ',');

    if ($imaTacku && $imaZarez) {
        // "12.469,50" – tačka su hiljade, zarez je decimalni znak
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } elseif ($imaZarez) {
        $v = str_replace(',', '.', $v);
    } elseif ($imaTacku) {
        // "40.000" je četrdeset hiljada, a "62.5" je šezdeset dva i po
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);
        }
    }

    return is_numeric($v) ? (float)$v : null;
}

function postCeo(string $k): ?int
{
    $v = postBroj($k);
    return $v === null ? null : (int)round($v);
}

function postCek(string $k): int
{
    return post($k) === '1' ? 1 : 0;
}

function get(string $k, string $podrazumevano = ''): string
{
    return isset($_GET[$k]) && is_scalar($_GET[$k]) ? trim((string)$_GET[$k]) : $podrazumevano;
}

function getId(string $k = 'id'): int
{
    return max(0, (int)get($k));
}

/* ============================================================
   PORUKE IZMEĐU ZAHTEVA
   ============================================================ */
function postavi_poruku(string $tekst, string $vrsta = 'uspeh'): void
{
    $_SESSION['poruka'] = ['t' => $tekst, 'v' => $vrsta];
}
function uzmi_poruku(): ?array
{
    if (empty($_SESSION['poruka'])) return null;
    $p = $_SESSION['poruka'];
    unset($_SESSION['poruka']);
    return $p;
}

function idi(string $strana, array $par = []): void
{
    $par = array_merge(['s' => $strana], $par);
    header('Location: index.php?' . http_build_query($par));
    exit;
}

/* ============================================================
   FOTOGRAFIJE
   ============================================================ */
function folder_uploada(): string
{
    $f = dirname(__DIR__) . '/uploads';
    if (!is_dir($f)) @mkdir($f, 0755, true);
    return $f;
}

/**
 * Prima fotografiju iz $_FILES, smanjuje je i snima.
 * Vraća naziv fajla, ili null ako fotografija nije poslata.
 * Baca RuntimeException ako je fajl neispravan.
 */
function primi_foto(string $polje, string $prefiks = 'foto'): ?string
{
    if (empty($_FILES[$polje]) || !is_array($_FILES[$polje])) return null;
    $f = $_FILES[$polje];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Fotografija je prevelika. Pokušaj ponovo sa manjom slikom.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('Fotografija nije prenesena. Pokušaj ponovo.');
    }

    $p = podesavanja();
    $maks = ((int)($p['max_foto_mb'] ?? 12)) * 1024 * 1024;
    if ($f['size'] > $maks) {
        throw new RuntimeException('Fotografija je prevelika (najviše ' . (int)($p['max_foto_mb'] ?? 12) . ' MB).');
    }

    $info = @getimagesize($f['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('Poslati fajl nije fotografija.');
    }
    $dozvoljeni = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!isset($dozvoljeni[$info[2]])) {
        throw new RuntimeException('Dozvoljene su samo JPG, PNG i WEBP fotografije.');
    }

    $naziv = $prefiks . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5));
    $folder = folder_uploada();

    // Smanjivanje preko GD-a (ako postoji) – manji fajl i uklonjeni EXIF podaci.
    if (function_exists('imagecreatetruecolor') && $info[2] !== IMAGETYPE_WEBP) {
        $slika = $info[2] === IMAGETYPE_JPEG
            ? @imagecreatefromjpeg($f['tmp_name'])
            : @imagecreatefrompng($f['tmp_name']);
        if ($slika) {
            $slika = ispravi_orijentaciju($slika, $f['tmp_name'], $info[2]);
            $sirina = imagesx($slika);
            $visina = imagesy($slika);
            $ciljna = (int)($p['foto_strana'] ?? 1600);
            $duza = max($sirina, $visina);
            if ($duza > $ciljna) {
                $odnos = $ciljna / $duza;
                $nova = imagescale($slika, (int)round($sirina * $odnos), (int)round($visina * $odnos));
                if ($nova) { imagedestroy($slika); $slika = $nova; }
            }
            $putanja = $folder . '/' . $naziv . '.jpg';
            $bela = imagecreatetruecolor(imagesx($slika), imagesy($slika));
            imagefill($bela, 0, 0, imagecolorallocate($bela, 255, 255, 255));
            imagecopy($bela, $slika, 0, 0, 0, 0, imagesx($slika), imagesy($slika));
            imagejpeg($bela, $putanja, 82);
            imagedestroy($slika);
            imagedestroy($bela);
            @chmod($putanja, 0644);
            return $naziv . '.jpg';
        }
    }

    // Bez GD-a: snimi original.
    $ext = $dozvoljeni[$info[2]];
    $putanja = $folder . '/' . $naziv . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $putanja)) {
        throw new RuntimeException('Fotografija nije mogla da se sačuva.');
    }
    @chmod($putanja, 0644);
    return $naziv . '.' . $ext;
}

/** Okreće fotografiju prema EXIF podatku telefona. */
function ispravi_orijentaciju($slika, string $putanja, int $tip)
{
    if ($tip !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) return $slika;
    $exif = @exif_read_data($putanja);
    $o = $exif['Orientation'] ?? 1;
    if ($o === 3) return imagerotate($slika, 180, 0);
    if ($o === 6) return imagerotate($slika, -90, 0);
    if ($o === 8) return imagerotate($slika, 90, 0);
    return $slika;
}

function obrisi_foto(?string $naziv): void
{
    if (!$naziv) return;
    $naziv = basename($naziv);
    $p = folder_uploada() . '/' . $naziv;
    if (is_file($p)) @unlink($p);
}
