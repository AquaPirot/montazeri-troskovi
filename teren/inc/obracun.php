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

/** Brojači za kartice u administratorskom pregledu. */
function brojaci_statusa(): array
{
    $r = redovi('SELECT status, COUNT(*) AS br FROM tereni GROUP BY status');
    $b = ['aktivan' => 0, 'pregled' => 0, 'ispravka' => 0, 'odobren' => 0];
    foreach ($r as $x) $b[$x['status']] = (int)$x['br'];
    return $b;
}
