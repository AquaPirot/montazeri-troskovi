<?php
/**
 * Detaljan izveštaj sa terena.
 * Isti ekran vide administrator i šef ekipe – administrator dodatno dobija dugmad za odluku.
 */
if (!defined('TEREN')) { exit; }

$k  = trazi_prijavu();
$id = getId('id');
$t  = $id ? ucitaj_teren($id) : null;

if (!$t) {
    postavi_poruku('Izveštaj nije pronađen.', 'greska');
    idi($k['uloga'] === 'admin' ? 'admin' : 'moji');
}

$jeAdmin = $k['uloga'] === 'admin';
$mojJe   = (int)$t['korisnik_id'] === (int)$k['id'];
if (!$jeAdmin && !$mojJe) {
    postavi_poruku('Nemaš pristup tom izveštaju.', 'greska');
    idi('moji');
}

/* ---------------- Odluka administratora ---------------- */
if (je_post() && $jeAdmin) {
    proveri_csrf();
    $akcija = post('akcija');

    if ($akcija === 'obrisi_admin_trosak' && $t['status'] !== 'odobren') {
        $tid = postCeo('trosak_id');
        $tr = $tid ? red('SELECT * FROM troskovi WHERE id = ? AND teren_id = ? AND izvor = "admin"', [$tid, $t['id']]) : null;
        if ($tr) {
            upit('DELETE FROM troskovi WHERE id = ? AND teren_id = ? AND izvor = "admin"', [$tid, $t['id']]);
            obrisi_foto($tr['foto_racun']);
            obrisi_foto($tr['foto_slip']);
            postavi_poruku('Administratorski unos goriva je obrisan.');
        }
        idi('izvestaj', ['id' => (int)$t['id']]);
    }

    if ($akcija === 'obrisi_teren') {
        if (post('potvrda_brisanja') !== 'OBRISI') {
            postavi_poruku('Za trajno brisanje upiši OBRISI velikim slovima.', 'greska');
            idi('izvestaj', ['id' => (int)$t['id']]);
        }

        $povratniTab = $t['status'];
        if (obrisi_teren_sa_slikama((int)$t['id'])) {
            postavi_poruku('Teren, njegovi troškovi, prijave i fotografije su trajno obrisani.');
            idi('admin', ['tab' => $povratniTab]);
        }

        postavi_poruku('Teren nije pronađen ili je već obrisan.', 'greska');
        idi('admin');
    }

    if ($akcija === 'odobri' && in_array($t['status'], ['pregled', 'ispravka'], true)) {
        upit('UPDATE tereni SET status = "odobren", odobrio_id = ?, vreme_odobrenja = NOW(),
                     komentar_admina = NULL WHERE id = ?', [$k['id'], $t['id']]);
        postavi_poruku('Izveštaj je odobren.');
        idi('admin', ['tab' => 'odobren']);
    }

    if ($akcija === 'vrati' && in_array($t['status'], ['pregled', 'odobren'], true)) {
        $komentar = post('komentar');
        if (mb_strlen($komentar) < 3) {
            postavi_poruku('Napiši komentar šta treba ispraviti.', 'greska');
            idi('izvestaj', ['id' => (int)$t['id']]);
        }
        upit('UPDATE tereni SET status = "ispravka", komentar_admina = ?,
                     odobrio_id = NULL, vreme_odobrenja = NULL WHERE id = ?', [$komentar, $t['id']]);
        postavi_poruku('Izveštaj je vraćen na ispravku.');
        idi('admin', ['tab' => 'ispravka']);
    }
}

/* ---------------- Šef ekipe briše pogrešan trošak tokom ispravke ---------------- */
if (je_post() && $mojJe && $t['status'] === 'ispravka' && post('akcija') === 'obrisi_trosak') {
    proveri_csrf();
    $tid = postCeo('trosak_id');
    $tr  = $tid ? red('SELECT * FROM troskovi WHERE id = ? AND teren_id = ?', [$tid, $t['id']]) : null;
    if ($tr && ($tr['izvor'] ?? 'radnik') !== 'admin') {
        obrisi_foto($tr['foto_racun']);
        obrisi_foto($tr['foto_slip']);
        upit('DELETE FROM troskovi WHERE id = ? AND teren_id = ?', [$tid, $t['id']]);
        postavi_poruku('Trošak je obrisan.');
    }
    idi('izvestaj', ['id' => (int)$t['id']]);
}

/* Šef ekipe briše pogrešnu doplatu tokom ispravke. */
if (je_post() && $mojJe && $t['status'] === 'ispravka' && post('akcija') === 'obrisi_doplatu') {
    proveri_csrf();
    $did = postCeo('doplata_id');
    if ($did) {
        upit('DELETE FROM doplate WHERE id = ? AND teren_id = ?', [$did, $t['id']]);
        postavi_poruku('Doplata je obrisana.');
    }
    idi('izvestaj', ['id' => (int)$t['id']]);
}

$troskovi = troskovi_terena((int)$t['id']);
$prijave  = prijave_terena((int)$t['id']);
$doplate  = doplate_terena((int)$t['id']);
$o        = obracun($t, $troskovi, $doplate);
$dokazi   = dokazi_terena($t, $troskovi, $prijave);

/* Šef ekipe može da dopuni teren koji je vraćen na ispravku. */
$mozeIspravku = $mojJe && $t['status'] === 'ispravka';

function cek_red(string $tekst, bool $ok): string
{
    return '<div class="cek-red ' . ($ok ? 'da' : 'ne') . '"><span class="i">'
         . ikona($ok ? 'cek' : 'alarm', 17) . '</span><span>' . h($tekst) . '</span></div>';
}
function novac_red(string $naziv, ?float $eur, ?float $rsd): string
{
    $fe = $eur === null ? '—' : broj($eur, 2) . ' EUR';
    $fr = $rsd === null ? '—' : broj($rsd, 0) . ' RSD';
    return '<div class="red"><span class="k">' . h($naziv) . '</span>'
         . '<span class="v mono">' . $fe . '<br>' . $fr . '</span></div>';
}

pocetak_strane('Obračun terena', ['nazad' => 'index.php?s=' . ($jeAdmin ? 'admin' : 'moji')]);
?>
<div class="sadrzaj">

<?php
    $zavrsen   = $t['km_kraj'] !== null;
    $problemi  = nepravilnosti($t, $o, $troskovi);
    $poKat     = [];
    foreach ($troskovi as $tr) $poKat[vrsta_kljuc($tr['vrsta'])][] = $tr;
    $sumaVrste = array_filter($o['po_vrsti'], fn($x) => $x['broj'] > 0);
    $iznosEurRsd = function (float $eur, float $rsd, bool $nula = true): string {
        $d = [];
        if ($rsd > 0 || ($nula && $eur <= 0)) $d[] = broj($rsd, 0) . ' RSD';
        if ($eur > 0) $d[] = broj($eur, 2) . ' EUR';
        return implode(' · ', $d);
    };
    $kolone = function (?float $eur, ?float $rsd, string $klasa = ''): string {
        $e = $eur === null ? '—' : broj($eur, 2);
        $r = $rsd === null ? '—' : broj($rsd, 0);
        return '<td class="desno mono ' . $klasa . '">' . $r . '</td><td class="desno mono ' . $klasa . '">' . $e . '</td>';
    };
?>
<!-- ================= OBRAČUN TERENA ================= -->
<div class="sekcija-naslov" style="display:flex;justify-content:space-between;align-items:center;gap:10px">
    <span>Obračun terena<?= $zavrsen ? '' : ' · u toku' ?></span>
    <button type="button" class="stampaj ne-stampaj" onclick="window.print()"><?= ikona('stampa', 16) ?> Štampaj / PDF</button>
</div>

<div class="karta obracun-puta"><div class="karta-telo">
    <div class="op-glava">
        <div style="min-width:0">
            <div class="op-naziv"><?= h($t['projekat']) ?></div>
            <div class="op-meta">
                <span><?= ikona('korisnik', 14) ?> <?= h($t['sef_ime']) ?></span>
                <span><?= ikona('kamion', 14) ?> <?= h($t['vozilo_naziv']) ?> · <?= h($t['vozilo_reg']) ?></span>
                <span><?= ikona('kalendar', 14) ?> <?= h(datum($t['vreme_polaska'])) ?> – <?= $t['vreme_povratka'] ? h(datum($t['vreme_povratka'])) : 'u toku' ?></span>
                <?php if ($t['odobrio_ime']): ?>
                    <span><?= ikona('cek', 14) ?> odobrio <?= h($t['odobrio_ime']) ?>, <?= h(datum($t['vreme_odobrenja'], false)) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?= oznaka_statusa($t['status']) ?>
    </div>

    <div class="op-brojke">
        <div>
            <div class="op-lab">Evidentirani troškovi</div>
            <div class="op-br"><?= broj($o['uk_rsd'], 0) ?><small>RSD</small></div>
            <div class="op-pod"><?= $o['uk_eur'] > 0 ? '+ ' . broj($o['uk_eur'], 2) . ' EUR' : 'bez troškova u EUR' ?></div>
        </div>
        <div>
            <div class="op-lab">Pređeno</div>
            <div class="op-br"><?= $o['km'] !== null ? broj($o['km'], 0) : '—' ?><small>km</small></div>
            <div class="op-pod"><?= broj($t['km_start'], 0) ?> → <?= $zavrsen ? broj($t['km_kraj'], 0) : 'u toku' ?></div>
        </div>
        <div>
            <div class="op-lab">Trajanje</div>
            <div class="op-br"><?= $o['dana'] ?? '—' ?><small><?= $o['dana'] === 1 ? 'dan' : 'dana' ?></small></div>
            <div class="op-pod"><?= $zavrsen ? 'od polaska do povratka' : 'do danas' ?></div>
        </div>
        <div>
            <div class="op-lab">Sipano gorivo</div>
            <div class="op-br"><?= broj($o['litri'], 1) ?><small>L</small></div>
            <div class="op-pod"><?= $o['potrosnja'] !== null
                ? 'procenjeno ' . broj($o['potrosnja'], 1) . ' L/100 km'
                : ($zavrsen ? 'nema sipanja za procenu' : 'procena posle povratka') ?></div>
        </div>
    </div>
    <?php if ($o['potrosnja'] !== null): ?>
        <div class="op-fusnota">Procena je zbir sipanja podeljen sa pređenim kilometrima. Tačna je samo ako je rezervoar
            na polasku bio pun i na kraju ponovo dopunjen do punog – nije osnova za ocenu vozača.</div>
    <?php endif; ?>

    <?php if ($problemi): ?>
        <div class="op-provera">
            <div class="op-lab" style="color:#9A3412"><?= ikona('alarm', 14) ?> Za proveru</div>
            <ul><?php foreach ($problemi as $p): ?><li><?= h($p) ?></li><?php endforeach; ?></ul>
        </div>
    <?php elseif ($zavrsen): ?>
        <div class="op-provera ok"><?= ikona('cek', 16) ?> Nema ničeg neuobičajenog u unetim podacima.</div>
    <?php endif; ?>
</div></div>

<?php if ($t['status'] === 'ispravka' && $t['komentar_admina']): ?>
    <?= napomena_box('<b>Vraćeno na ispravku:</b><br>' . nl2br(h($t['komentar_admina'])), 'greska', 'vrati') ?>
<?php endif; ?>

<!-- ================= TROŠKOVI PO KATEGORIJI ================= -->
<div class="sekcija-naslov">Na šta je potrošeno</div>
<div class="karta"><div class="karta-telo">
<?php if (!$sumaVrste): ?>
    <div class="sitno">Još nema unetih troškova.</div>
<?php else: ?>
    <div class="kat-glava"><span>Kategorija</span><span>RSD</span><span>EUR</span></div>
    <?php foreach ($sumaVrste as $kljuc => $x): ?>
    <details class="kat">
        <summary>
            <span class="kat-ime"><?= ikona(VRSTE[$kljuc]['ik'], 17) ?>
                <span><b><?= h(VRSTE[$kljuc]['n']) ?></b>
                <span class="sitno"><?= h(mnozina($x['broj'], 'račun', 'računa', 'računa')) ?><?=
                    $kljuc === 'gorivo' && $x['litri'] > 0 ? ' · ' . broj($x['litri'], 1) . ' L' : '' ?></span></span>
            </span>
            <span class="mono"><?= $x['rsd'] > 0 ? broj($x['rsd'], 0) : '—' ?></span>
            <span class="mono"><?= $x['eur'] > 0 ? broj($x['eur'], 2) : '—' ?></span>
        </summary>
        <div class="kat-stavke">
        <?php foreach ($poKat[$kljuc] as $tr): ?>
            <div class="trosak">
                <span class="sred">
                    <span class="n1"><?= $tr['opis'] ?? null ? h($tr['opis']) : h(datumKratko($tr['kreiran'])) ?></span>
                    <span class="n2"><?php
                        if ($tr['opis'] ?? null) echo h(datumKratko($tr['kreiran'])) . ' · ';
                        echo h(PLACANJA[$tr['nacin_placanja']]['k']);
                        if (($tr['izvor'] ?? 'radnik') === 'admin') echo ' · unela kancelarija' . ($tr['uneo_ime'] ? ': ' . h($tr['uneo_ime']) : '');
                        if ($tr['vrsta'] === 'gorivo' && $tr['litri'] !== null) echo ' · ' . broj($tr['litri'], 1) . ' L na ' . broj((int)$tr['km_sipanja'], 0) . ' km';
                        if ($kljuc === 'ostalo' && !($tr['opis'] ?? null)) echo ' · <span class="lose">bez opisa</span>';
                        if (!$tr['foto_racun']) echo ' · <span class="lose">bez računa</span>';
                    ?></span>
                    <?php if ($tr['foto_racun'] || $tr['foto_slip']): ?>
                    <span class="n2 ne-stampaj">
                        <?php if ($tr['foto_racun']): ?><a class="link" href="index.php?s=slika&amp;f=<?= h(rawurlencode($tr['foto_racun'])) ?>" target="_blank" rel="noopener">račun</a><?php endif; ?>
                        <?php if ($tr['foto_slip']): ?> · <a class="link" href="index.php?s=slika&amp;f=<?= h(rawurlencode($tr['foto_slip'])) ?>" target="_blank" rel="noopener">slip</a><?php endif; ?>
                    </span>
                    <?php endif; ?>
                </span>
                <span class="iz"><?= broj($tr['iznos'], $tr['valuta'] === 'RSD' ? 0 : 2) ?><small><?= h($tr['valuta']) ?></small></span>
                <?php if ($mozeIspravku && ($tr['izvor'] ?? 'radnik') !== 'admin'): ?>
                    <form method="post" data-bez-zakljucavanja>
                        <?= csrf_polje() ?>
                        <input type="hidden" name="akcija" value="obrisi_trosak">
                        <input type="hidden" name="trosak_id" value="<?= (int)$tr['id'] ?>">
                        <button class="brisi" type="submit" data-pitaj="Obrisati ovaj trošak?" aria-label="Obriši trošak"><?= ikona('kanta', 18) ?></button>
                    </form>
                <?php elseif ($jeAdmin && ($tr['izvor'] ?? 'radnik') === 'admin' && $t['status'] !== 'odobren'): ?>
                    <form method="post" data-bez-zakljucavanja>
                        <?= csrf_polje() ?>
                        <input type="hidden" name="akcija" value="obrisi_admin_trosak">
                        <input type="hidden" name="trosak_id" value="<?= (int)$tr['id'] ?>">
                        <button class="brisi" type="submit" data-pitaj="Obrisati ovaj unos goriva kancelarije?" aria-label="Obriši unos goriva"><?= ikona('kanta', 18) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
    <div class="kat-ukupno"><span>Ukupno evidentirano</span>
        <span class="mono"><?= broj($o['uk_rsd'], 0) ?></span><span class="mono"><?= broj($o['uk_eur'], 2) ?></span></div>
    <div class="sitno ne-stampaj" style="margin-top:8px">Dodirni kategoriju za pojedinačne račune.</div>
<?php endif; ?>
</div></div>

<!-- ================= RAZDUŽENJE ================= -->
<div class="sekcija-naslov">Razduženje · čijim novcem</div>
<div class="karta"><div class="karta-telo">
    <table class="razd">
        <thead><tr><th></th><th class="desno">RSD</th><th class="desno">EUR</th></tr></thead>
        <tbody>
            <tr><td>Primljena gotovina<?= ($o['dop_eur'] > 0 || $o['dop_rsd'] > 0)
                    ? '<span class="sitno">uključuje doplate: ' . h($iznosEurRsd($o['dop_eur'], $o['dop_rsd'], false)) . '</span>' : '' ?></td>
                <?= $kolone($o['uk_depozit_eur'], $o['uk_depozit_rsd']) ?></tr>
            <tr><td>Potrošeno iz gotovine</td><?= $kolone($o['got_eur'], $o['got_rsd']) ?></tr>
            <tr class="jace"><td>Treba vratiti</td><?= $kolone($o['ocek_eur'], $o['ocek_rsd']) ?></tr>
            <tr><td>Stvarno vraćeno</td><?= $kolone(
                    $t['vraceno_eur'] === null ? null : (float)$t['vraceno_eur'],
                    $t['vraceno_rsd'] === null ? null : (float)$t['vraceno_rsd']) ?></tr>
            <?php if ($o['raz_eur'] !== null && ima_razlike($o['raz_eur'], $o['raz_rsd'])): ?>
            <tr class="lose"><td>Razlika (+ višak, − manjak)</td><?= $kolone($o['raz_eur'], $o['raz_rsd']) ?></tr>
            <?php endif; ?>
            <tr class="razmak"><td>Plaćeno službenom karticom</td><?= $kolone($o['kar_eur'], $o['kar_rsd']) ?></tr>
            <tr class="<?= ($o['lic_eur'] > 0 || $o['lic_rsd'] > 0) ? 'jace' : '' ?>"><td>Radnik platio svojim novcem<span class="sitno">firma mu duguje nadoknadu</span></td>
                <?= $kolone($o['lic_eur'], $o['lic_rsd']) ?></tr>
        </tbody>
    </table>
    <div class="op-fusnota">Kategorije pokazuju <b>na šta</b> je novac potrošen, a razduženje <b>čijim novcem</b>.
        To su dva pogleda na iste troškove i ne sabiraju se.</div>

    <?php if ($doplate): ?>
    <details class="sklop">
        <summary><?= h(mnozina(count($doplate), 'doplata', 'doplate', 'doplata')) ?> tokom puta</summary>
        <?php foreach ($doplate as $d): ?>
        <div class="trosak">
            <span class="sred">
                <span class="n1">Predao/la <?= h($d['predao']) ?></span>
                <span class="n2"><?= h(datumKratko($d['kreirana'])) ?><?php if ($d['uneo_ime']): ?> · evidentirao/la <?= h($d['uneo_ime']) ?><?php endif; ?><?= $d['napomena'] ? ' · ' . h($d['napomena']) : '' ?></span>
            </span>
            <span class="iz"><?= broj($d['iznos'], $d['valuta'] === 'RSD' ? 0 : 2) ?><small><?= h($d['valuta']) ?></small></span>
            <?php if ($mozeIspravku): ?>
                <form method="post" data-bez-zakljucavanja>
                    <?= csrf_polje() ?>
                    <input type="hidden" name="akcija" value="obrisi_doplatu">
                    <input type="hidden" name="doplata_id" value="<?= (int)$d['id'] ?>">
                    <button class="brisi" type="submit" data-pitaj="Obrisati ovu doplatu?" aria-label="Obriši doplatu"><?= ikona('kanta', 18) ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </details>
    <?php endif; ?>
</div></div>

<!-- ================= DOKAZI (sklopljeno) ================= -->
<details class="karta sklop-karta ne-stampaj">
    <summary><?= ikona('slika', 17) ?> Računi, slipovi i fotografije (<?= count($dokazi) ?>)</summary>
    <div class="karta-telo">
    <?php if (!$dokazi): ?>
        <div class="sitno">Nema priloženih fotografija.</div>
    <?php else: ?>
        <div class="galerija">
        <?php foreach ($dokazi as $d): ?>
            <a class="dokaz" href="index.php?s=slika&amp;f=<?= h(rawurlencode($d['f'])) ?>" target="_blank" rel="noopener">
                <img src="index.php?s=slika&amp;f=<?= h(rawurlencode($d['f'])) ?>" alt="<?= h($d['l']) ?>" loading="lazy">
                <span class="lab"><?= h($d['l']) ?></span>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</details>

<!-- ================= PRIJAVE ================= -->
<div class="sekcija-naslov">Prijave sa terena (<?= count($prijave) ?>)</div>
<?php if (!$prijave): ?>
    <div class="karta"><div class="karta-telo sitno">Nema prijava.</div></div>
<?php else: foreach ($prijave as $p): ?>
    <div class="karta"><div class="karta-telo">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <span style="width:32px;height:32px;border-radius:8px;background:var(--tamna);color:var(--akcenat);display:flex;align-items:center;justify-content:center;flex:none">
                <?= ikona(TIPOVI_PRIJAVE[$p['tip']]['ik'], 17) ?>
            </span>
            <span>
                <span style="display:block;font-size:15px;font-weight:700"><?= h(TIPOVI_PRIJAVE[$p['tip']]['n']) ?></span>
                <span class="sitno"><?= h(datumKratko($p['kreirana'])) ?><?= $p['foto'] ? ' · sa fotografijom' : '' ?></span>
            </span>
        </div>
        <div style="font-size:15px;line-height:1.5"><?= nl2br(h($p['opis'])) ?></div>
    </div></div>
<?php endforeach; endif; ?>

<!-- ================= POTVRDE ================= -->
<div class="sekcija-naslov">Potvrde šefa ekipe</div>
<div class="karta"><div class="karta-telo">
    <?= cek_red('Vozilo provereno na polasku', (int)$t['p_vozilo'] === 1) ?>
    <?= cek_red('Gorivo provereno',            (int)$t['p_gorivo'] === 1) ?>
    <?= cek_red('Teret obezbeđen',             (int)$t['p_teret'] === 1) ?>
    <?php if ($t['napomena_polazak']): ?>
        <div style="margin-top:10px"><?= napomena_box(nl2br(h($t['napomena_polazak'])), 'upozorenje', 'olovka') ?></div>
    <?php endif; ?>

    <?php if ($t['vreme_povratka']): ?>
        <div class="razdelnik"></div>
        <?= cek_red('Vozilo provereno na povratku',     (int)$t['k_vozilo'] === 1) ?>
        <?= cek_red('Kabina i tovarni prostor uredni',  (int)$t['k_kabina'] === 1) ?>
        <?= cek_red('Alat i materijal vraćeni',         (int)$t['k_alat'] === 1) ?>
        <?= cek_red('Svi računi uneti',                 (int)$t['k_racuni'] === 1) ?>
        <?php if ($t['napomena_povratak']): ?>
            <div style="margin-top:10px"><?= napomena_box(nl2br(h($t['napomena_povratak'])), 'upozorenje', 'olovka') ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div></div>

<!-- ================= AKCIJE ================= -->
<div class="ne-stampaj">
<?php if ($jeAdmin && in_array($t['status'], ['aktivan', 'pregled', 'ispravka'], true)): ?>
    <div class="sekcija-naslov">Unos kancelarije</div>
    <div class="dugmad">
        <a class="dugme d-akcenat" href="index.php?s=admin_gorivo&teren=<?= (int)$t['id'] ?>">
            <?= ikona('gorivo', 20) ?> Dodaj sipanje goriva
        </a>
    </div>
<?php endif; ?>

<?php if ($jeAdmin && in_array($t['status'], ['pregled', 'ispravka'], true)): ?>
    <div class="sekcija-naslov">Odluka</div>
    <div class="dugmad">
        <form method="post">
            <?= csrf_polje() ?>
            <input type="hidden" name="akcija" value="odobri">
            <button class="dugme d-zeleno" type="submit" data-pitaj="Odobriti ovaj izveštaj?">
                <?= ikona('cek', 20) ?> Odobri izveštaj
            </button>
        </form>
    </div>
    <?php if ($t['status'] === 'pregled'): ?>
        <div class="karta"><div class="karta-telo">
            <form method="post">
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="vrati">
                <div class="polje">
                    <label for="km2">Vrati na ispravku uz komentar</label>
                    <textarea class="unos" id="km2" name="komentar" maxlength="1000" required
                              placeholder="npr. Fali račun za gorivo od 15.07."></textarea>
                </div>
                <button class="dugme d-obrub" type="submit"><?= ikona('vrati', 20) ?> Vrati na ispravku</button>
            </form>
        </div></div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($mozeIspravku): ?>
    <div class="sekcija-naslov">Ispravka</div>
    <div class="dugmad">
        <a class="dugme d-obrub" href="index.php?s=doplata&teren=<?= (int)$t['id'] ?>"><?= ikona('novac', 20) ?> Dodaj primljeni novac</a>
        <a class="dugme d-obrub" href="index.php?s=trosak&teren=<?= (int)$t['id'] ?>"><?= ikona('plus', 20) ?> Dodaj trošak koji fali</a>
        <a class="dugme d-obrub" href="index.php?s=prijavi&teren=<?= (int)$t['id'] ?>"><?= ikona('alarm', 20) ?> Dodaj prijavu</a>
        <a class="dugme d-akcenat" href="index.php?s=povratak&teren=<?= (int)$t['id'] ?>"><?= ikona('olovka', 20) ?> Ispravi i pošalji ponovo</a>
    </div>
<?php endif; ?>

<?php if ($jeAdmin): ?>
    <div class="sekcija-naslov">Brisanje testnog terena</div>
    <div class="karta"><div class="karta-telo">
        <?= napomena_box(
            '<b>Koristi samo za probne unose.</b> Teren, svi troškovi, prijave i fotografije biće trajno obrisani.',
            'greska',
            'kanta'
        ) ?>
        <form method="post">
            <?= csrf_polje() ?>
            <input type="hidden" name="akcija" value="obrisi_teren">
            <div class="polje">
                <label for="potvrda_brisanja">Za potvrdu upiši OBRISI</label>
                <input class="unos" id="potvrda_brisanja" name="potvrda_brisanja"
                       type="text" autocomplete="off" required placeholder="OBRISI">
            </div>
            <button class="dugme d-obrub" type="submit"
                    data-pitaj="Trajno obrisati ovaj testni teren i sve njegove podatke?">
                <?= ikona('kanta', 20) ?> Trajno obriši testni teren
            </button>
        </form>
    </div></div>
<?php endif; ?>
</div>

</div>
<?php kraj_strane($jeAdmin ? nav_admin('admin') : nav_sef('moji')); ?>
