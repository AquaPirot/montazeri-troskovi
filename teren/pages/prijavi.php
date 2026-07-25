<?php
/** Prijava sa terena – tip, kratko objašnjenje, opciona fotografija. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

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

    $tip  = post('tip');
    $opis = post('opis');

    if (!isset(TIPOVI_PRIJAVE[$tip]))  $greske[] = 'Izaberi šta prijavljuješ.';
    if (mb_strlen($opis) < 3)          $greske[] = 'Napiši kratko objašnjenje.';
    if (mb_strlen($opis) > 2000)       $greske[] = 'Objašnjenje je predugačko.';

    $foto = null;
    if (!$greske) {
        try {
            $foto = primi_foto('foto', 'prijava');
        } catch (RuntimeException $e) {
            $greske[] = $e->getMessage();
        }
    }

    if (!$greske) {
        upit('INSERT INTO prijave (teren_id, tip, opis, foto) VALUES (?,?,?,?)',
             [$t['id'], $tip, $opis, $foto]);
        postavi_poruku('Prijava je poslata kancelariji.');
        idi($t['status'] === 'ispravka' ? 'izvestaj' : 'pocetna',
            $t['status'] === 'ispravka' ? ['id' => (int)$t['id']] : []);
    }
}

$izTip = post('tip');

pocetak_strane('Prijavi sa terena', ['nazad' => $nazad]);
?>
<form class="sadrzaj" method="post" enctype="multipart/form-data">
<?= csrf_polje() ?>

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<div class="sekcija-naslov">Šta prijavljuješ?</div>
<div class="izbor-lista" style="margin-bottom:16px">
<?php foreach (TIPOVI_PRIJAVE as $kljuc => $p): ?>
    <?= izbor_red('tip', $kljuc, $p['n'], '', $izTip === $kljuc, $p['ik']) ?>
<?php endforeach; ?>
</div>

<div data-prikazi-ako="tip=lom|materijal|nezavrseno|vozilo|ostalo" <?= $izTip ? '' : 'hidden' ?>>
    <div class="karta"><div class="karta-telo">
        <div class="polje">
            <label for="op">Kratko objašnjenje</label>
            <textarea class="unos" id="op" name="opis" maxlength="2000"
                      placeholder="Napiši u jednoj ili dve rečenice šta je problem."><?= h(post('opis')) ?></textarea>
        </div>
        <?= foto_polje('foto', 'Dodaj fotografiju', 'Nije obavezno') ?>
    </div></div>

    <?= napomena_box('Kancelarija dobija prijavu odmah. Ti ne moraš ništa dalje da popunjavaš.', 'info', 'stit') ?>

    <div class="dno-akcija">
        <button class="dugme d-glavno" type="submit"><?= ikona('alarm', 20) ?> Pošalji prijavu</button>
    </div>
</div>
</form>
<?php kraj_strane(); ?>
