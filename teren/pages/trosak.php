<?php
/** Dodavanje troška. Unos je namerno kratak – dodatna polja se pojavljuju samo za gorivo. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

/** Teren u koji se upisuje: otvoreni teren ili onaj koji je vraćen na ispravku. */
$t = null;
$trazeni = getId('teren');
if ($trazeni) {
    $t = ucitaj_teren($trazeni);
    if (!$t || (int)$t['korisnik_id'] !== (int)$k['id'] || !in_array($t['status'], ['aktivan', 'ispravka'], true)) {
        postavi_poruku('Taj teren ne može da se menja.', 'greska');
        idi('pocetna');
    }
} else {
    $t = aktivan_teren((int)$k['id']);
    if (!$t) {
        postavi_poruku('Prvo otvori teren.', 'info');
        idi('pocetna');
    }
}

$nazad = 'index.php?s=' . ($t['status'] === 'ispravka' ? 'izvestaj&id=' . (int)$t['id'] : 'pocetna');
$greske = [];

if (je_post()) {
    proveri_csrf();

    $val   = post('valuta');
    $iznos = postNovac('iznos', $val);
    $vrsta = post('vrsta');
    $nacin = post('nacin_placanja');

    if ($iznos === null || $iznos <= 0)           $greske[] = 'Unesi iznos veći od nule.';
    if (!in_array($val, ['EUR', 'RSD'], true))    $greske[] = 'Izaberi valutu (EUR ili RSD).';
    if (!isset(VRSTE[$vrsta]))                    $greske[] = 'Izaberi vrstu troška.';
    if (!isset(PLACANJA[$nacin]))                 $greske[] = 'Izaberi način plaćanja.';

    $opis = null;
    if ($vrsta === 'ostalo') {
        $opis = post('opis');
        if (mb_strlen($opis) < 3)   $greske[] = 'Za „Ostalo" napiši kratko šta je plaćeno.';
        if (mb_strlen($opis) > 200) $greske[] = 'Opis je predugačak (najviše 200 znakova).';
    }

    $litri = null; $kmSip = null;
    if ($vrsta === 'gorivo') {
        $litri = postBroj('litri');
        $kmSip = postCeo('km_sipanja');
        if ($litri === null || $litri <= 0) $greske[] = 'Za gorivo unesi količinu u litrima.';
        if ($kmSip === null || $kmSip <= 0) $greske[] = 'Za gorivo unesi kilometražu u trenutku sipanja.';
    }

    $fotoRacun = null; $fotoSlip = null;
    if (!$greske) {
        try {
            $fotoRacun = primi_foto('foto_racun', 'racun');
            if ($nacin === 'kartica') $fotoSlip = primi_foto('foto_slip', 'slip');
        } catch (RuntimeException $e) {
            $greske[] = $e->getMessage();
            obrisi_foto($fotoRacun);
            $fotoRacun = null;
        }
    }

    if (!$greske) {
        // Kolona "opis" se šalje samo za "Ostalo", da ostali unosi rade i pre SQL nadogradnje 05.
        $kolone = ['teren_id', 'uneo_id', 'izvor', 'iznos', 'valuta', 'vrsta', 'nacin_placanja',
                   'litri', 'km_sipanja', 'foto_racun', 'foto_slip'];
        $vrednosti = [$t['id'], $k['id'], 'radnik', $iznos, $val, $vrsta, $nacin, $litri, $kmSip, $fotoRacun, $fotoSlip];
        if ($opis !== null) { $kolone[] = 'opis'; $vrednosti[] = $opis; }
        try {
            upit('INSERT INTO troskovi (' . implode(', ', $kolone) . ') VALUES ('
                 . implode(',', array_fill(0, count($kolone), '?')) . ')', $vrednosti);
        } catch (PDOException $e) {
            // "Alat" i opis za "Ostalo" traže SQL nadogradnju 05.
            obrisi_foto($fotoRacun);
            obrisi_foto($fotoSlip);
            if (!in_array($vrsta, ['alat', 'ostalo'], true)) throw $e;
            error_log('TEREN trošak: ' . $e->getMessage());
            postavi_poruku('„' . VRSTE[$vrsta]['n'] . '" još nije uključeno u bazi. '
                . 'Izaberi „Materijal" i javi kancelariji (nadogradnja 05).', 'greska');
            idi('trosak', $t['status'] === 'ispravka' ? ['teren' => (int)$t['id']] : []);
        }

        postavi_poruku('Sačuvano: ' . novac((float)$iznos, $val) . ' · ' . VRSTE[$vrsta]['n'] . '.');
        idi($t['status'] === 'ispravka' ? 'izvestaj' : 'pocetna',
            $t['status'] === 'ispravka' ? ['id' => (int)$t['id']] : []);
    }
}

$izVrsta = post('vrsta');
$izNacin = post('nacin_placanja');
$izVal   = post('valuta');

pocetak_strane('Dodaj trošak', ['nazad' => $nazad]);
?>
<form class="sadrzaj" method="post" enctype="multipart/form-data">
<?= csrf_polje() ?>

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<div class="karta"><div class="karta-telo">
    <div class="polje">
        <label for="iz">Iznos</label>
        <div class="dva iznos">
            <input class="unos veliki" id="iz" name="iznos" type="text" required
                   inputmode="decimal" placeholder="0,00" value="<?= h(post('iznos')) ?>">
            <div class="plocice k2" style="gap:6px">
                <?= plocica('valuta', 'EUR', 'EUR', $izVal === 'EUR', '', 'akcenat valuta') ?>
                <?= plocica('valuta', 'RSD', 'RSD', $izVal === 'RSD', '', 'akcenat valuta') ?>
            </div>
        </div>
        <div class="sitno" style="margin-top:7px">Za 1.650 dinara možeš uneti: <b>1650</b>, <b>1.650</b> ili <b>1,650</b>.</div>
    </div>
</div></div>

<div class="sekcija-naslov">Vrsta troška</div>
<div class="karta"><div class="karta-telo">
    <div class="plocice k3">
    <?php foreach (VRSTE as $kljuc => $v): ?>
        <?= plocica('vrsta', $kljuc, $v['n'], $izVrsta === $kljuc, $v['ik']) ?>
    <?php endforeach; ?>
    </div>
    <div class="polje" style="margin-top:14px" data-prikazi-ako="vrsta=ostalo" <?= $izVrsta === 'ostalo' ? '' : 'hidden' ?>>
        <label for="opis">Šta je plaćeno?</label>
        <input class="unos" id="opis" name="opis" maxlength="200" placeholder="npr. pranje kombija, fotokopije"
               value="<?= h(post('opis')) ?>">
    </div>
</div></div>

<div class="karta istaknut-okvir" data-prikazi-ako="vrsta=gorivo" <?= $izVrsta === 'gorivo' ? '' : 'hidden' ?>>
    <div class="karta-zaglavlje" style="color:var(--akcenat);border-color:#DBE7FB">
        <?= ikona('gorivo', 16) ?> Podaci o sipanju
    </div>
    <div class="karta-telo"><div class="dva">
        <div class="polje">
            <label for="li">Litara</label>
            <div class="unos-sa-sufiksom">
                <input class="unos" id="li" name="litri" type="text" inputmode="decimal" placeholder="0,0" value="<?= h(post('litri')) ?>">
                <span class="sufiks">L</span>
            </div>
        </div>
        <div class="polje">
            <label for="ks">Kilometraža</label>
            <div class="unos-sa-sufiksom">
                <input class="unos" id="ks" name="km_sipanja" type="text" inputmode="numeric" placeholder="<?= broj($t['km_start'], 0) ?>" value="<?= h(post('km_sipanja')) ?>">
                <span class="sufiks">km</span>
            </div>
        </div>
    </div></div>
</div>

<div class="sekcija-naslov">Način plaćanja</div>
<div class="izbor-lista" style="margin-bottom:12px">
<?php foreach (PLACANJA as $kljuc => $p): ?>
    <?= izbor_red('nacin_placanja', $kljuc, $p['n'], '', $izNacin === $kljuc, $p['ik']) ?>
<?php endforeach; ?>
</div>

<div data-prikazi-ako="nacin_placanja=licni" <?= $izNacin === 'licni' ? '' : 'hidden' ?>>
    <?= napomena_box('Ovaj iznos se vodi kao <b>dug firme prema tebi</b> i biće ti vraćen.', 'info', 'novcanik') ?>
</div>
<div data-prikazi-ako="nacin_placanja=kartica" <?= $izNacin === 'kartica' ? '' : 'hidden' ?>>
    <?= napomena_box('Kartično plaćanje se <b>ne oduzima</b> od tvog gotovinskog depozita.', 'info', 'kartica') ?>
</div>

<div class="sekcija-naslov">Dokaz</div>
<?= foto_polje('foto_racun', 'Dodaj fotografiju računa', 'Slikaj sada ili izaberi iz galerije') ?>
<div data-prikazi-ako="nacin_placanja=kartica" <?= $izNacin === 'kartica' ? '' : 'hidden' ?>>
    <?= foto_polje('foto_slip', 'Dodaj fotografiju slipa', 'Slikaj ili izaberi iz galerije; nije obavezno ako je na računu') ?>
</div>

<div class="dno-akcija">
    <button class="dugme d-akcenat" type="submit"><?= ikona('plus', 20) ?> Sačuvaj trošak</button>
</div>
</form>
<?php kraj_strane(); ?>
