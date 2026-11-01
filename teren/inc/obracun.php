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
    return redovi('SELECT x.*, k.ime AS uneo_ime
                     FROM troskovi x
                LEFT JOIN korisnici k ON k.id = x.uneo_id
                    WHERE x.teren_id = ?
                 ORDER BY x.kreiran ASC, x.id ASC', [$teren_id]);
}

function prijave_terena(int $teren_id): array
{
    return redovi('SELECT * FROM prijave WHERE teren_id = ? ORDER BY kreirana ASC, id ASC', [$teren_id]);
}

/** Naknadno primljeni gotovinski depozit na jednom terenu. */
function doplate_terena(int $teren_id): array
{
    return redovi('SELECT d.*, k.ime AS uneo_ime
                     FROM doplate d
                LEFT JOIN korisnici k ON k.id = d.uneo_id
                    WHERE d.teren_id = ?
                 ORDER BY d.kreirana ASC, d.id ASC', [$teren_id]);
}

/**
 * Administrator trajno briše jedan teren i sve njegove fotografije.
 *
 * Troškovi i prijave se brišu preko ON DELETE CASCADE pravila u bazi.
 * Fotografije se uklanjaju tek nakon uspešnog brisanja iz baze, da zapis ne bi
 * ostao bez dokaza ako SQL operacija ne uspe.
 */
function obrisi_teren_sa_slikama(int $teren_id): bool
{
    $t = red('SELECT foto_km_start, foto_km_kraj FROM tereni WHERE id = ?', [$teren_id]);
    if (!$t) return false;

    $fotografije = [];
    foreach ([$t['foto_km_start'], $t['foto_km_kraj']] as $foto) {
        if ($foto) $fotografije[] = $foto;
    }

    $troskovi = redovi('SELECT foto_racun, foto_slip FROM troskovi WHERE teren_id = ?', [$teren_id]);
    foreach ($troskovi as $tr) {
        if ($tr['foto_racun']) $fotografije[] = $tr['foto_racun'];
        if ($tr['foto_slip'])  $fotografije[] = $tr['foto_slip'];
    }

    $prijave = redovi('SELECT foto FROM prijave WHERE teren_id = ?', [$teren_id]);
    foreach ($prijave as $p) {
        if ($p['foto']) $fotografije[] = $p['foto'];
    }

    $pdo = baza();
    $pdo->beginTransaction();
    try {
        $obrisano = upit('DELETE FROM tereni WHERE id = ?', [$teren_id])->rowCount();
        if ($obrisano !== 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    foreach (array_unique($fotografije) as $foto) obrisi_foto($foto);
    return true;
}

/**
 * Kompletan obračun jednog terena.
 *
 * Vraća polja:
 *   got_eur/got_rsd   – potrošeno gotovinom iz depozita
 *   kar_eur/kar_rsd   – plaćeno službenom karticom (ne dira depozit)
 *   lic_eur/lic_rsd   – plaćeno ličnim novcem (dug firme prema zaposlenom)
 *   dop_eur/dop_rsd   – naknadno primljeni gotovinski depozit
 *   uk_depozit_*      – početni depozit + doplate
 *   ocek_eur/ocek_rsd – koliko gotovine treba vratiti
 *   raz_eur/raz_rsd   – razlika stvarno vraćeno − očekivano (null dok se ne vrati)
 *   km, litri, gorivo_eur/gorivo_rsd, potrosnja, ocek_potrosnja, odstupanje, upozorenje
 */
function obracun(array $t, ?array $troskovi = null, ?array $doplate = null): array
{
    $troskovi = $troskovi ?? troskovi_terena((int)$t['id']);
    $doplate  = $doplate  ?? doplate_terena((int)$t['id']);

    $o = [
        'got_eur' => 0.0, 'got_rsd' => 0.0,
        'kar_eur' => 0.0, 'kar_rsd' => 0.0,
        'lic_eur' => 0.0, 'lic_rsd' => 0.0,
        'gorivo_eur' => 0.0, 'gorivo_rsd' => 0.0,
        'dop_eur' => 0.0, 'dop_rsd' => 0.0,
        'litri' => 0.0,
        'broj_troskova' => count($troskovi),
        'broj_doplata' => count($doplate),
        'bez_racuna' => 0,
    ];

    // Zbir po vrsti troška – EUR i RSD odvojeno, bez obzira na način plaćanja.
    $o['po_vrsti'] = [];
    foreach (array_keys(VRSTE) as $v) {
        $o['po_vrsti'][$v] = ['eur' => 0.0, 'rsd' => 0.0, 'broj' => 0, 'litri' => 0.0];
    }

    $mapa = ['gotovina' => 'got', 'kartica' => 'kar', 'licni' => 'lic'];
    foreach ($troskovi as $tr) {
        $vr = isset($o['po_vrsti'][$tr['vrsta']]) ? $tr['vrsta'] : 'ostalo';
        $o['po_vrsti'][$vr][strtolower($tr['valuta'])] += (float)$tr['iznos'];
        $o['po_vrsti'][$vr]['broj']++;
        if ($vr === 'gorivo') $o['po_vrsti'][$vr]['litri'] += (float)$tr['litri'];

        $kljuc = $mapa[$tr['nacin_placanja']] . '_' . strtolower($tr['valuta']);
        $o[$kljuc] += (float)$tr['iznos'];
        if ($tr['vrsta'] === 'gorivo') {
            $o['litri'] += (float)$tr['litri'];
            $o['gorivo_' . strtolower($tr['valuta'])] += (float)$tr['iznos'];
        }
        if (empty($tr['foto_racun'])) $o['bez_racuna']++;
    }

    foreach ($doplate as $d) {
        $o['dop_' . strtolower($d['valuta'])] += (float)$d['iznos'];
    }

    // Gotovina: samo gotovinski troškovi skidaju depozit.
    // Ukupan trošak puta (gotovina + kartica + lični novac).
    $o['uk_eur'] = round($o['got_eur'] + $o['kar_eur'] + $o['lic_eur'], 2);
    $o['uk_rsd'] = round($o['got_rsd'] + $o['kar_rsd'] + $o['lic_rsd'], 2);

    // Trajanje u kalendarskim danima, uključujući dan polaska i povratka.
    $od = strtotime(substr((string)$t['vreme_polaska'], 0, 10));
    $do = strtotime(substr((string)($t['vreme_povratka'] ?: date('Y-m-d')), 0, 10));
    $o['dana'] = ($od && $do && $do >= $od) ? (int)round(($do - $od) / 86400) + 1 : null;

    $o['uk_depozit_eur'] = round((float)$t['depozit_eur'] + $o['dop_eur'], 2);
    $o['uk_depozit_rsd'] = round((float)$t['depozit_rsd'] + $o['dop_rsd'], 2);
    $o['ocek_eur'] = round($o['uk_depozit_eur'] - $o['got_eur'], 2);
    $o['ocek_rsd'] = round($o['uk_depozit_rsd'] - $o['got_rsd'], 2);

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
