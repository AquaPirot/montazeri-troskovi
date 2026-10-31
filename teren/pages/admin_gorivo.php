<?php
/** Administrator evidentira sipanje goriva na konkretnom terenu. */
if (!defined('TEREN')) { exit; }

$k = trazi_admina();
$terenId = getId('teren');
$t = $terenId ? ucitaj_teren($terenId) : null;

if (!$t || !in_array($t['status'], ['aktivan', 'pregled', 'ispravka'], true)) {
    postavi_poruku('Gorivo može da se doda samo na aktivan izveštaj ili izveštaj koji još nije odobren.', 'greska');
    idi('admin');
}

$greske = [];
if (je_post()) {
    proveri_csrf();

    $valuta = post('valuta');
    $iznos  = postNovac('iznos', $valuta);
    $nacin  = post('nacin_placanja');
    $litri  = postBroj('litri');
    $km     = postCeo('km_sipanja');

    if (!in_array($valuta, ['EUR', 'RSD'], true)) $greske[] = 'Izaberi valutu.';
    if ($iznos === null || $iznos <= 0) $greske[] = 'Unesi iznos goriva veći od nule.';
    if (!isset(PLACANJA[$nacin])) $greske[] = 'Izaberi način plaćanja.';
    if ($litri === null || $litri <= 0) $greske[] = 'Unesi količinu goriva u litrima.';
    if ($km === null || $km < (int)$t['km_start']) {
        $greske[] = 'Kilometraža sipanja ne može biti manja od početne kilometraže terena.';
    }
    if ($km !== null && $t['km_kraj'] !== null && $km > (int)$t['km_kraj']) {
        $greske[] = 'Kilometraža sipanja ne može biti veća od završne kilometraže terena.';
    }

    if (!$greske) {
        $trenutniStatus = vrednost('SELECT status FROM tereni WHERE id = ?', [$t['id']]);
        if (!in_array($trenutniStatus, ['aktivan', 'pregled', 'ispravka'], true)) {
            $greske[] = 'Izveštaj je u međuvremenu odobren i gorivo nije sačuvano.';
        }
    }

    $fotoRacun = null;
    $fotoSlip = null;
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
        upit('INSERT INTO troskovi
                (teren_id, uneo_id, izvor, iznos, valuta, vrsta, nacin_placanja,
                 litri, km_sipanja, foto_racun, foto_slip)
              VALUES (?,?,"admin",?, ?,"gorivo",?,?,?,?,?)', [
            $t['id'], $k['id'], $iznos, $valuta, $nacin,
            $litri, $km, $fotoRacun, $fotoSlip,
        ]);
        postavi_poruku('Gorivo je evidentirano: ' . broj($litri, 1) . ' L na ' . broj($km, 0) . ' km.');
        idi('izvestaj', ['id' => (int)$t['id']]);
    }
}

$izValuta = post('valuta', 'RSD');
$izNacin = post('nacin_placanja', 'kartica');
pocetak_strane('Dodaj gorivo', ['nazad' => 'index.php?s=izvestaj&id=' . (int)$t['id']]);
?>
<form class="sadrzaj" method="post" enctype="multipart/form-data">
<?= csrf_polje() ?>

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?= napomena_box(
    'Unos pravi kancelarija za <b>' . h($t['vozilo_naziv']) . ' · ' . h($t['vozilo_reg'])
    . '</b> na terenu <b>' . h($t['projekat']) . '</b>. Biće jasno označeno ko ga je uneo.',
    'info', 'gorivo'
) ?>

<div class="karta"><div class="karta-telo">
    <div class="polje">
        <label for="ag-iz">Iznos goriva</label>
        <div class="dva iznos">
            <input class="unos veliki" id="ag-iz" name="iznos" type="text" required
                   inputmode="decimal" placeholder="0" value="<?= h(post('iznos')) ?>">
            <div class="plocice k2" style="gap:6px">
                <?= plocica('valuta', 'RSD', 'RSD', $izValuta === 'RSD', '', 'akcenat valuta') ?>
                <?= plocica('valuta', 'EUR', 'EUR', $izValuta === 'EUR', '', 'akcenat valuta') ?>
            </div>
        </div>
    </div>
    <div class="dva">
        <div class="polje">
            <label for="ag-li">Litara</label>
            <div class="unos-sa-sufiksom">
                <input class="unos" id="ag-li" name="litri" type="text" required inputmode="decimal"
                       placeholder="0,0" value="<?= h(post('litri')) ?>">
                <span class="sufiks">L</span>
            </div>
        </div>
        <div class="polje">
            <label for="ag-km">Kilometraža</label>
            <div class="unos-sa-sufiksom">
                <input class="unos" id="ag-km" name="km_sipanja" type="text" required inputmode="numeric"
                       placeholder="<?= broj($t['km_start'], 0) ?>" value="<?= h(post('km_sipanja')) ?>">
                <span class="sufiks">km</span>
            </div>
        </div>
    </div>
</div></div>

<div class="sekcija-naslov">Način plaćanja</div>
<div class="izbor-lista" style="margin-bottom:12px">
<?php foreach (PLACANJA as $kljuc => $p): ?>
    <?= izbor_red('nacin_placanja', $kljuc, $p['n'], '', $izNacin === $kljuc, $p['ik']) ?>
<?php endforeach; ?>
</div>

<div class="sekcija-naslov">Račun i slip</div>
<?= foto_polje('foto_racun', 'Dodaj fotografiju računa', 'Slikaj sada ili izaberi iz galerije') ?>
<div data-prikazi-ako="nacin_placanja=kartica" <?= $izNacin === 'kartica' ? '' : 'hidden' ?>>
    <?= foto_polje('foto_slip', 'Dodaj fotografiju slipa', 'Nije obavezno ako je slip na računu') ?>
</div>

<div class="dno-akcija">
    <button class="dugme d-akcenat" type="submit"><?= ikona('gorivo', 20) ?> Sačuvaj gorivo</button>
</div>
</form>
<?php kraj_strane(); ?>
