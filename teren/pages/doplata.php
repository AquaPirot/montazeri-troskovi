<?php
/** Naknadno primljeni gotovinski depozit na terenu. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

$trazeni = getId('teren');
if ($trazeni) {
    $t = ucitaj_teren($trazeni);
    if (!$t || (int)$t['korisnik_id'] !== (int)$k['id'] || !in_array($t['status'], ['aktivan', 'ispravka'], true)) {
        postavi_poruku('Na taj teren ne može da se doda novac.', 'greska');
        idi('pocetna');
    }
} else {
    $t = aktivan_teren((int)$k['id']);
    if (!$t) {
        postavi_poruku('Prvo otvori teren.', 'info');
        idi('pocetna');
    }
}

$greske = [];
$nazad = 'index.php?s=' . ($t['status'] === 'ispravka' ? 'izvestaj&id=' . (int)$t['id'] : 'pocetna');

if (je_post()) {
    proveri_csrf();
    $valuta = post('valuta');
    $iznos  = postNovac('iznos', $valuta);
    $predao = post('predao');
    $napomena = post('napomena');

    if (!in_array($valuta, ['EUR', 'RSD'], true)) $greske[] = 'Izaberi valutu.';
    if ($iznos === null || $iznos <= 0) $greske[] = 'Unesi iznos veći od nule.';
    if (mb_strlen($predao) < 2) $greske[] = 'Upiši ko je predao novac.';
    if (mb_strlen($predao) > 100) $greske[] = 'Ime osobe je predugačko.';
    if (mb_strlen($napomena) > 500) $greske[] = 'Napomena je predugačka.';

    if (!$greske) {
        $trenutniStatus = vrednost('SELECT status FROM tereni WHERE id = ? AND korisnik_id = ?', [$t['id'], $k['id']]);
        if (!in_array($trenutniStatus, ['aktivan', 'ispravka'], true)) {
            $greske[] = 'Teren je u međuvremenu zatvoren i doplata nije sačuvana.';
        }
    }

    if (!$greske) {
        upit('INSERT INTO doplate (teren_id, iznos, valuta, predao, napomena, uneo_id)
              VALUES (?,?,?,?,?,?)', [
            $t['id'], $iznos, $valuta, $predao,
            $napomena !== '' ? $napomena : null, $k['id'],
        ]);
        postavi_poruku('Dodatni novac je evidentiran: ' . novac((float)$iznos, $valuta) . '.');
        idi($t['status'] === 'ispravka' ? 'izvestaj' : 'pocetna',
            $t['status'] === 'ispravka' ? ['id' => (int)$t['id']] : []);
    }
}

$izValuta = post('valuta');
pocetak_strane('Dodaj primljeni novac', ['nazad' => $nazad]);
?>
<form class="sadrzaj" method="post">
<?= csrf_polje() ?>

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?= napomena_box(
    'Ovde evidentiraš <b>gotovinu koju si naknadno dobio na terenu</b>. Taj iznos se dodaje depozitu i ulazi u obračun vraćenog novca.',
    'info', 'novac'
) ?>

<div class="karta"><div class="karta-telo">
    <div class="polje">
        <label for="di">Primljeni iznos</label>
        <div class="dva iznos">
            <input class="unos veliki" id="di" name="iznos" type="text" required
                   inputmode="decimal" placeholder="0" value="<?= h(post('iznos')) ?>">
            <div class="plocice k2" style="gap:6px">
                <?= plocica('valuta', 'EUR', 'EUR', $izValuta === 'EUR', '', 'zlato valuta') ?>
                <?= plocica('valuta', 'RSD', 'RSD', $izValuta === 'RSD', '', 'zlato valuta') ?>
            </div>
        </div>
    </div>
    <div class="polje">
        <label for="dp">Ko je predao novac?</label>
        <input class="unos" id="dp" name="predao" maxlength="100" required
               placeholder="Ime i prezime" value="<?= h(post('predao')) ?>">
    </div>
    <div class="polje">
        <label for="dn">Napomena <span class="opc">(nije obavezno)</span></label>
        <textarea class="unos" id="dn" name="napomena" maxlength="500"
                  placeholder="npr. Doplata za nastavak terena"><?= h(post('napomena')) ?></textarea>
    </div>
</div></div>

<div class="dno-akcija">
    <button class="dugme d-zlato" type="submit"><?= ikona('plus', 20) ?> Sačuvaj primljeni novac</button>
</div>
</form>
<?php kraj_strane(); ?>
