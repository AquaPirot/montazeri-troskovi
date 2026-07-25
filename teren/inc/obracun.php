<?php
/**
 * Učitavanje terena i obračun novca, kilometraže i potrošnje goriva.
 *
 * EUR i RSD se vode potpuno odvojeno i nikada se ne sabiraju.
 */
if (!defined('TEREN')) { http_response_code(403); exit('Zabranjen pristup.'); }

/** Teren sa podacima o šefu ekipe i vozilu. */
function ucitaj_teren(int $id): ?array
{
    return red(
        'SELECT t.*,
                k.ime AS sef_ime, k.telefon AS sef_telefon,
                v.naziv AS vozilo_naziv, v.registracija AS vozilo_reg,
                v.ocekivana_potrosnja AS vozilo_potrosnja,
                a.ime AS odobrio_ime
           FROM tereni t
           JOIN korisnici k ON k.id = t.korisnik_id
           JOIN vozila    v ON v.id = t.vozilo_id
      LEFT JOIN korisnici a ON a.id = t.odobrio_id
          WHERE t.id = ?', [$id]);
}

/** Otvoren teren datog šefa ekipe (ili null). */
function aktivan_teren(int $korisnik_id): ?array
{
    $r = red('SELECT id FROM tereni WHERE korisnik_id = ? AND status = "aktivan" ORDER BY id DESC LIMIT 1',
             [$korisnik_id]);
    return $r ? ucitaj_teren((int)$r['id']) : null;
}

function troskovi_terena(int $teren_id): array
{
    return redovi('SELECT * FROM troskovi WHERE teren_id = ? ORDER BY kreiran ASC, id ASC', [$teren_id]);
}

function prijave_terena(int $teren_id): array
{
    return redovi('SELECT * FROM prijave WHERE teren_id = ? ORDER BY kreirana ASC, id ASC', [$teren_id]);
}

/**
 * Kompletan obračun jednog terena.
 *
 * Vraća polja:
 *   got_eur/got_rsd   – potrošeno gotovinom iz depozita
 *   kar_eur/kar_rsd   – plaćeno službenom karticom (ne dira depozit)
 *   lic_eur/lic_rsd   – plaćeno ličnim novcem (dug firme prema zaposlenom)
 *   ocek_eur/ocek_rsd – koliko gotovine treba vratiti
 *   raz_eur/raz_rsd   – razlika stvarno vraćeno − očekivano (null dok se ne vrati)
 *   km, litri, gorivo_eur/gorivo_rsd, potrosnja, ocek_potrosnja, odstupanje, upozorenje
 */
function obracun(array $t, ?array $troskovi = null): array
{
    $troskovi = $troskovi ?? troskovi_terena((int)$t['id']);

    $o = [
        'got_eur' => 0.0, 'got_rsd' => 0.0,
        'kar_eur' => 0.0, 'kar_rsd' => 0.0,
        'lic_eur' => 0.0, 'lic_rsd' => 0.0,
        'gorivo_eur' => 0.0, 'gorivo_rsd' => 0.0,
        'litri' => 0.0,
        'broj_troskova' => count($troskovi),
        'bez_racuna' => 0,
    ];

    $mapa = ['gotovina' => 'got', 'kartica' => 'kar', 'licni' => 'lic'];
    foreach ($troskovi as $tr) {
        $kljuc = $mapa[$tr['nacin_placanja']] . '_' . strtolower($tr['valuta']);
        $o[$kljuc] += (float)$tr['iznos'];
        if ($tr['vrsta'] === 'gorivo') {
            $o['litri'] += (float)$tr['litri'];
            $o['gorivo_' . strtolower($tr['valuta'])] += (float)$tr['iznos'];
        }
        if (empty($tr['foto_racun'])) $o['bez_racuna']++;
    }

    // Gotovina: samo gotovinski troškovi skidaju depozit.
    $o['ocek_eur'] = round((float)$t['depozit_eur'] - $o['got_eur'], 2);
    $o['ocek_rsd'] = round((float)$t['depozit_rsd'] - $o['got_rsd'], 2);

    $o['raz_eur'] = $t['vraceno_eur'] === null ? null : round((float)$t['vraceno_eur'] - $o['ocek_eur'], 2);
    $o['raz_rsd'] = $t['vraceno_rsd'] === null ? null : round((float)$t['vraceno_rsd'] - $o['ocek_rsd'], 2);

    // Kilometraža i potrošnja.
    $o['km'] = ($t['km_kraj'] !== null) ? ((int)$t['km_kraj'] - (int)$t['km_start']) : null;
    $o['potrosnja'] = ($o['km'] && $o['km'] > 0 && $o['litri'] > 0)
        ? round($o['litri'] / $o['km'] * 100, 2) : null;

    $ocekP = $t['vozilo_potrosnja'] !== null ? (float)$t['vozilo_potrosnja'] : null;
    $o['ocek_potrosnja'] = $ocekP;
    $o['odstupanje'] = ($o['potrosnja'] !== null && $ocekP) ? round(($o['potrosnja'] - $ocekP) / $ocekP * 100, 1) : null;

    $prag = (float)(podesavanja()['prag_potrosnje'] ?? 15);
    $o['upozorenje'] = $o['odstupanje'] !== null && abs($o['odstupanje']) > $prag;
    $o['prag'] = $prag;

    return $o;
}

/** Da li je razlika u vraćenom novcu značajna. */
function ima_razlike(?float $eur, ?float $rsd): bool
{
    return (abs((float)$eur) >= 0.01) || (abs((float)$rsd) >= 1);
}

/** Tekstualni opis razlike, npr. "manjak 20,00 EUR, manjak 2.380 RSD". */
function opis_razlike(?float $eur, ?float $rsd): string
{
    $d = [];
    if ($eur !== null && abs($eur) >= 0.01) $d[] = ($eur > 0 ? 'višak ' : 'manjak ') . broj(abs($eur), 2) . ' EUR';
    if ($rsd !== null && abs($rsd) >= 1)    $d[] = ($rsd > 0 ? 'višak ' : 'manjak ') . broj(abs($rsd), 0) . ' RSD';
    return implode(', ', $d);
}

/** Spisak svih fotografija jednog terena (za galeriju u izveštaju). */
function dokazi_terena(array $t, array $troskovi, array $prijave): array
{
    $d = [];
    if ($t['foto_km_start']) $d[] = ['f' => $t['foto_km_start'], 'l' => 'Km polazak',  'ik' => 'slika'];
    if ($t['foto_km_kraj'])  $d[] = ['f' => $t['foto_km_kraj'],  'l' => 'Km povratak', 'ik' => 'slika'];
    foreach ($troskovi as $tr) {
        $naziv = VRSTE[$tr['vrsta']]['n'];
        if ($tr['foto_racun']) $d[] = ['f' => $tr['foto_racun'], 'l' => 'Račun · ' . $naziv, 'ik' => 'racun'];
        if ($tr['foto_slip'])  $d[] = ['f' => $tr['foto_slip'],  'l' => 'Slip · ' . $naziv,  'ik' => 'kartica'];
    }
    foreach ($prijave as $p) {
        if ($p['foto']) $d[] = ['f' => $p['foto'], 'l' => 'Prijava · ' . TIPOVI_PRIJAVE[$p['tip']]['n'], 'ik' => 'alarm'];
    }
    return $d;
}

/* ============================================================
   VOZILA – zbirni podaci i istorija
   ============================================================ */

/**
 * Zbirna potrošnja jednog vozila kroz sve zatvorene terene.
 *
 * Računa se samo iz terena koji imaju završnu kilometražu, da bi
 * pređeni kilometri i sipani litri pripadali istom periodu.
 */
function statistika_vozila(int $vozilo_id): array
{
    $t = red('SELECT COUNT(*) AS broj, COALESCE(SUM(km_kraj - km_start), 0) AS km
                FROM tereni WHERE vozilo_id = ? AND km_kraj IS NOT NULL', [$vozilo_id]);

    $g = red('SELECT COALESCE(SUM(x.litri), 0) AS litri,
                     COALESCE(SUM(CASE WHEN x.valuta = "EUR" THEN x.iznos ELSE 0 END), 0) AS eur,
                     COALESCE(SUM(CASE WHEN x.valuta = "RSD" THEN x.iznos ELSE 0 END), 0) AS rsd
                FROM troskovi x
                JOIN tereni t ON t.id = x.teren_id
               WHERE t.vozilo_id = ? AND t.km_kraj IS NOT NULL AND x.vrsta = "gorivo"', [$vozilo_id]);

    $s = [
        'broj_terena' => (int)$t['broj'],
        'km'          => (int)$t['km'],
        'litri'       => (float)$g['litri'],
        'gorivo_eur'  => (float)$g['eur'],
        'gorivo_rsd'  => (float)$g['rsd'],
    ];

    $s['potrosnja'] = ($s['km'] > 0 && $s['litri'] > 0)
        ? round($s['litri'] / $s['km'] * 100, 2) : null;

    $ocek = vrednost('SELECT ocekivana_potrosnja FROM vozila WHERE id = ?', [$vozilo_id]);
    $s['ocek_potrosnja'] = $ocek !== null && $ocek !== false ? (float)$ocek : null;
    $s['odstupanje'] = ($s['potrosnja'] !== null && $s['ocek_potrosnja'])
        ? round(($s['potrosnja'] - $s['ocek_potrosnja']) / $s['ocek_potrosnja'] * 100, 1) : null;

    $prag = (float)(podesavanja()['prag_potrosnje'] ?? 15);
    $s['upozorenje'] = $s['odstupanje'] !== null && abs($s['odstupanje']) > $prag;
    $s['prag'] = $prag;

    return $s;
}

/** Tereni jednog vozila, sa potrošnjom po svakom terenu. */
function tereni_vozila(int $vozilo_id, int $koliko = 50): array
{
    $lista = redovi(
        'SELECT t.id, t.projekat, t.status, t.vreme_polaska, t.vreme_povratka,
                t.km_start, t.km_kraj, k.ime AS sef_ime,
                (SELECT COALESCE(SUM(x.litri), 0) FROM troskovi x
                  WHERE x.teren_id = t.id AND x.vrsta = "gorivo") AS litri
           FROM tereni t
           JOIN korisnici k ON k.id = t.korisnik_id
          WHERE t.vozilo_id = ?
       ORDER BY t.vreme_polaska DESC, t.id DESC
          LIMIT ' . (int)$koliko, [$vozilo_id]);

    foreach ($lista as &$t) {
        $t['km'] = ($t['km_kraj'] !== null) ? ((int)$t['km_kraj'] - (int)$t['km_start']) : null;
        $t['potrosnja'] = ($t['km'] && $t['km'] > 0 && (float)$t['litri'] > 0)
            ? round((float)$t['litri'] / $t['km'] * 100, 2) : null;
    }
    return $lista;
}

/** Unosi iz servisne knjige, najnoviji prvi. */
function servis_vozila(int $vozilo_id): array
{
    return redovi('SELECT s.*, k.ime AS uneo
                     FROM servis s
                LEFT JOIN korisnici k ON k.id = s.kreirao_id
                    WHERE s.vozilo_id = ?
                 ORDER BY s.datum DESC, s.id DESC', [$vozilo_id]);
}

/** Poslednji upis registracije sa datumom važenja (ili null). */
function registracija_vozila(int $vozilo_id): ?array
{
    return red('SELECT datum, vazi_do FROM servis
                 WHERE vozilo_id = ? AND vrsta = "registracija" AND vazi_do IS NOT NULL
              ORDER BY vazi_do DESC LIMIT 1', [$vozilo_id]);
}

/** Brojači za kartice u administratorskom pregledu. */
function brojaci_statusa(): array
{
    $r = redovi('SELECT status, COUNT(*) AS br FROM tereni GROUP BY status');
    $b = ['aktivan' => 0, 'pregled' => 0, 'ispravka' => 0, 'odobren' => 0];
    foreach ($r as $x) $b[$x['status']] = (int)$x['br'];
    return $b;
}
