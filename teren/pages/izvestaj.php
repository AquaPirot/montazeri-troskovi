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
    if ($tr) {
        obrisi_foto($tr['foto_racun']);
        obrisi_foto($tr['foto_slip']);
        upit('DELETE FROM troskovi WHERE id = ? AND teren_id = ?', [$tid, $t['id']]);
        postavi_poruku('Trošak je obrisan.');
    }
    idi('izvestaj', ['id' => (int)$t['id']]);
}

$troskovi = troskovi_terena((int)$t['id']);
$prijave  = prijave_terena((int)$t['id']);
$o        = obracun($t, $troskovi);
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

pocetak_strane('Izveštaj sa terena', ['nazad' => 'index.php?s=' . ($jeAdmin ? 'admin' : 'moji')]);
?>
<div class="sadrzaj">

<!-- ================= OSNOVNI PODACI ================= -->
<div class="karta"><div class="karta-telo">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px">
        <div style="font-size:19px;font-weight:700;letter-spacing:-.3px;line-height:1.25"><?= h($t['projekat']) ?></div>
        <?= oznaka_statusa($t['status']) ?>
    </div>
    <div class="red"><span class="k">Šef ekipe</span><span class="v"><?= h($t['sef_ime']) ?></span></div>
    <div class="red"><span class="k">Vozilo</span><span class="v"><?= h($t['vozilo_naziv']) ?> · <?= h($t['vozilo_reg']) ?></span></div>
    <div class="red"><span class="k">Polazak</span><span class="v mono"><?= h(datum($t['vreme_polaska'])) ?></span></div>
    <div class="red"><span class="k">Povratak</span><span class="v mono"><?= $t['vreme_povratka'] ? h(datum($t['vreme_povratka'])) : 'u toku' ?></span></div>
    <?php if ($t['odobrio_ime']): ?>
        <div class="red"><span class="k">Odobrio</span><span class="v"><?= h($t['odobrio_ime']) ?> · <?= h(datum($t['vreme_odobrenja'], false)) ?></span></div>
    <?php endif; ?>
</div></div>

<?php if ($t['status'] === 'ispravka' && $t['komentar_admina']): ?>
    <?= napomena_box('<b>Vraćeno na ispravku:</b><br>' . nl2br(h($t['komentar_admina'])), 'greska', 'vrati') ?>
<?php endif; ?>

<!-- ================= NOVAC ================= -->
<div class="sekcija-naslov">Novac – EUR i RSD odvojeno</div>

<div class="karta">
    <div class="karta-zaglavlje"><?= ikona('novac', 16) ?> Gotovina iz depozita</div>
    <div class="karta-telo">
        <?= novac_red('Primljen depozit', (float)$t['depozit_eur'], (float)$t['depozit_rsd']) ?>
        <?= novac_red('Potrošeno gotovinom', -$o['got_eur'], -$o['got_rsd']) ?>
        <?= novac_red('Očekivano za vraćanje', $o['ocek_eur'], $o['ocek_rsd']) ?>
        <?= novac_red('Stvarno vraćeno',
                $t['vraceno_eur'] === null ? null : (float)$t['vraceno_eur'],
                $t['vraceno_rsd'] === null ? null : (float)$t['vraceno_rsd']) ?>
        <?php if ($o['raz_eur'] !== null || $o['raz_rsd'] !== null): ?>
            <?php if (ima_razlike($o['raz_eur'], $o['raz_rsd'])): ?>
                <div style="margin-top:12px">
                <?= napomena_box('Razlika: <b>' . h(opis_razlike($o['raz_eur'], $o['raz_rsd']))
                    . '</b> – proveriti sa šefom ekipe.', 'upozorenje', 'alarm') ?>
                </div>
            <?php else: ?>
                <div style="margin-top:12px">
                <?= napomena_box('Vraćeni iznosi se slažu sa obračunom.', 'uspeh', 'cek') ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="karta">
    <div class="karta-zaglavlje"><?= ikona('kartica', 16) ?> Službena kartica</div>
    <div class="karta-telo"><div class="novac-mreza">
        <div class="novac"><div class="val">EUR</div><div class="iznos"><?= broj($o['kar_eur'], 2) ?></div><div class="opis">ne dira depozit</div></div>
        <div class="novac"><div class="val">RSD</div><div class="iznos"><?= broj($o['kar_rsd'], 0) ?></div><div class="opis">ne dira depozit</div></div>
    </div></div>
</div>

<div class="karta <?= ($o['lic_eur'] > 0 || $o['lic_rsd'] > 0) ? 'zlatni-okvir' : '' ?>">
    <div class="karta-zaglavlje"><?= ikona('novcanik', 16) ?> Lični novac zaposlenog</div>
    <div class="karta-telo"><div class="novac-mreza">
        <div class="novac <?= $o['lic_eur'] > 0 ? 'negativno' : '' ?>">
            <div class="val">EUR</div><div class="iznos"><?= broj($o['lic_eur'], 2) ?></div>
            <div class="opis">dug firme prema zaposlenom</div>
        </div>
        <div class="novac <?= $o['lic_rsd'] > 0 ? 'negativno' : '' ?>">
            <div class="val">RSD</div><div class="iznos"><?= broj($o['lic_rsd'], 0) ?></div>
            <div class="opis">dug firme prema zaposlenom</div>
        </div>
    </div></div>
</div>

<!-- ================= KILOMETRAŽA I GORIVO ================= -->
<div class="sekcija-naslov">Kilometraža i gorivo</div>
<div class="karta"><div class="karta-telo">
    <div class="red"><span class="k">Početna kilometraža</span><span class="v mono"><?= kmF($t['km_start']) ?></span></div>
    <div class="red"><span class="k">Završna kilometraža</span><span class="v mono"><?= kmF($t['km_kraj']) ?></span></div>
    <div class="red"><span class="k">Ukupno pređeno</span><span class="v mono"><?= kmF($o['km']) ?></span></div>
    <div class="red"><span class="k">Ukupno sipano</span><span class="v mono"><?= broj($o['litri'], 1) ?> L</span></div>
    <div class="red"><span class="k">Ukupan trošak goriva</span><span class="v mono"><?php
        $g = [];
        if ($o['gorivo_rsd'] > 0) $g[] = novac($o['gorivo_rsd'], 'RSD');
        if ($o['gorivo_eur'] > 0) $g[] = novac($o['gorivo_eur'], 'EUR');
        echo $g ? implode('<br>', array_map('h', $g)) : '—';
    ?></span></div>
    <div class="red"><span class="k">Procenjena prosečna potrošnja</span><span class="v mono"><?=
        $o['potrosnja'] !== null ? broj($o['potrosnja'], 1) . ' L/100 km' : '—' ?></span></div>
    <div class="red"><span class="k">Očekivano za ovo vozilo</span><span class="v mono"><?=
        $o['ocek_potrosnja'] !== null ? broj($o['ocek_potrosnja'], 1) . ' L/100 km' : 'nije uneto' ?></span></div>
</div></div>

<?php if ($o['odstupanje'] !== null): ?>
    <?php if ($o['upozorenje']): ?>
        <?= napomena_box('<b>Odstupanje ' . ($o['odstupanje'] > 0 ? '+' : '') . broj($o['odstupanje'], 1)
            . '% u odnosu na očekivanu potrošnju.</b><br>Ovo je samo signal za proveru – uzrok može biti '
            . 'teret, teren, gužva, način vožnje ili greška u unosu.', 'upozorenje', 'alarm') ?>
    <?php else: ?>
        <?= napomena_box('Potrošnja je u očekivanom opsegu (' . ($o['odstupanje'] > 0 ? '+' : '')
            . broj($o['odstupanje'], 1) . '%).', 'uspeh', 'cek') ?>
    <?php endif; ?>
<?php elseif ($o['litri'] > 0 && $o['ocek_potrosnja'] === null): ?>
    <?= napomena_box('Za ovo vozilo nije uneta očekivana potrošnja, pa nema poređenja.', 'info', 'ostalo') ?>
<?php endif; ?>

<!-- ================= TROŠKOVI ================= -->
<div class="sekcija-naslov">Troškovi (<?= count($troskovi) ?>)</div>
<div class="karta"><div class="karta-telo">
<?php if (!$troskovi): ?>
    <div class="sitno">Nema unetih troškova.</div>
<?php else: foreach ($troskovi as $tr): ?>
    <div class="trosak">
        <span class="ik"><?= ikona(VRSTE[$tr['vrsta']]['ik'], 18) ?></span>
        <span class="sred">
            <span class="n1"><?= h(VRSTE[$tr['vrsta']]['n']) ?></span>
            <span class="n2"><?= h(datumKratko($tr['kreiran'])) ?> · <?= h(PLACANJA[$tr['nacin_placanja']]['k']) ?><?php
                if ($tr['vrsta'] === 'gorivo' && $tr['litri'] !== null) {
                    echo ' · ' . broj($tr['litri'], 1) . ' L na ' . broj((int)$tr['km_sipanja'], 0) . ' km';
                }
                if (!$tr['foto_racun']) echo ' · <span class="lose">bez računa</span>';
            ?></span>
        </span>
        <span class="iz"><?= broj($tr['iznos'], $tr['valuta'] === 'RSD' ? 0 : 2) ?><small><?= h($tr['valuta']) ?></small></span>
        <?php if ($mozeIspravku): ?>
            <form method="post" style="flex:none" data-bez-zakljucavanja>
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="obrisi_trosak">
                <input type="hidden" name="trosak_id" value="<?= (int)$tr['id'] ?>">
                <button class="brisi" type="submit" data-pitaj="Obrisati ovaj trošak?" aria-label="Obriši trošak"><?= ikona('kanta', 18) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; endif; ?>
</div></div>

<!-- ================= DOKAZI ================= -->
<div class="sekcija-naslov">Računi, slipovi i fotografije (<?= count($dokazi) ?>)</div>
<?php if (!$dokazi): ?>
    <div class="karta"><div class="karta-telo sitno">Nema priloženih fotografija.</div></div>
<?php else: ?>
    <div class="galerija">
    <?php foreach ($dokazi as $d): ?>
        <a class="dokaz" href="index.php?s=slika&f=<?= h(rawurlencode($d['f'])) ?>" target="_blank" rel="noopener">
            <img src="index.php?s=slika&f=<?= h(rawurlencode($d['f'])) ?>" alt="<?= h($d['l']) ?>" loading="lazy">
            <span class="lab"><?= h($d['l']) ?></span>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ================= PRIJAVE ================= -->
<div class="sekcija-naslov">Prijave sa terena (<?= count($prijave) ?>)</div>
<?php if (!$prijave): ?>
    <div class="karta"><div class="karta-telo sitno">Nema prijava.</div></div>
<?php else: foreach ($prijave as $p): ?>
    <div class="karta"><div class="karta-telo">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <span style="width:32px;height:32px;border-radius:8px;background:var(--grafit);color:var(--zlatna);display:flex;align-items:center;justify-content:center;flex:none">
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
        <a class="dugme d-obrub" href="index.php?s=trosak&teren=<?= (int)$t['id'] ?>"><?= ikona('plus', 20) ?> Dodaj trošak koji fali</a>
        <a class="dugme d-obrub" href="index.php?s=prijavi&teren=<?= (int)$t['id'] ?>"><?= ikona('alarm', 20) ?> Dodaj prijavu</a>
        <a class="dugme d-zlato" href="index.php?s=povratak&teren=<?= (int)$t['id'] ?>"><?= ikona('olovka', 20) ?> Ispravi i pošalji ponovo</a>
    </div>
<?php endif; ?>

</div>
<?php kraj_strane($jeAdmin ? nav_admin('admin') : nav_sef('moji')); ?>
