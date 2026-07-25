<?php
/**
 * Povratak sa terena – zatvaranje terena i slanje izveštaja.
 * Isti ekran služi i za ispravku izveštaja koji je administrator vratio.
 */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

$t = null;
$trazeni = getId('teren');
if ($trazeni) {
    $t = ucitaj_teren($trazeni);
    if (!$t || (int)$t['korisnik_id'] !== (int)$k['id'] || $t['status'] !== 'ispravka') {
        postavi_poruku('Taj izveštaj ne može da se menja.', 'greska');
        idi('pocetna');
    }
} else {
    $t = aktivan_teren((int)$k['id']);
    if (!$t) {
        postavi_poruku('Nemaš otvoren teren.', 'info');
        idi('pocetna');
    }
}

$ispravka = $t['status'] === 'ispravka';
$nazad    = 'index.php?s=' . ($ispravka ? 'izvestaj&id=' . (int)$t['id'] : 'pocetna');
$o        = obracun($t);
$greske   = [];

if (je_post()) {
    proveri_csrf();

    $kmKraj = postCeo('km_kraj');
    $vrEur  = postBroj('vraceno_eur');
    $vrRsd  = postBroj('vraceno_rsd');

    if ($kmKraj === null)                   $greske[] = 'Unesi završnu kilometražu.';
    elseif ($kmKraj < (int)$t['km_start'])  $greske[] = 'Završna kilometraža je manja od polazne (' . broj($t['km_start'], 0) . ').';
    if ($vrEur !== null && $vrEur < 0)      $greske[] = 'Vraćeni iznos u EUR ne može biti negativan.';
    if ($vrRsd !== null && $vrRsd < 0)      $greske[] = 'Vraćeni iznos u RSD ne može biti negativan.';

    // Ako polje nije popunjeno, uzima se očekivani iznos.
    if ($vrEur === null) $vrEur = max(0, $o['ocek_eur']);
    if ($vrRsd === null) $vrRsd = max(0, $o['ocek_rsd']);

    $foto = null;
    if (!$greske) {
        try {
            $foto = primi_foto('foto_km_kraj', 'km');
        } catch (RuntimeException $e) {
            $greske[] = $e->getMessage();
        }
    }

    if (!$greske) {
        if ($foto && $t['foto_km_kraj']) obrisi_foto($t['foto_km_kraj']);
        upit('UPDATE tereni SET
                 km_kraj = ?, foto_km_kraj = COALESCE(?, foto_km_kraj),
                 vraceno_eur = ?, vraceno_rsd = ?,
                 k_vozilo = ?, k_kabina = ?, k_alat = ?, k_racuni = ?,
                 napomena_povratak = ?, vreme_povratka = COALESCE(vreme_povratka, NOW()),
                 status = "pregled"
               WHERE id = ? AND korisnik_id = ?', [
            $kmKraj, $foto, $vrEur, $vrRsd,
            postCek('k_vozilo'), postCek('k_kabina'), postCek('k_alat'), postCek('k_racuni'),
            post('napomena_povratak') !== '' ? post('napomena_povratak') : null,
            $t['id'], $k['id'],
        ]);
        postavi_poruku($ispravka ? 'Ispravljen izveštaj je poslat kancelariji.' : 'Izveštaj je poslat kancelariji.');
        idi('moji');
    }
}

/* Vrednosti u poljima: iz POST-a posle greške, inače postojeće (kod ispravke). */
$vKm   = je_post() ? post('km_kraj')     : ($t['km_kraj'] !== null ? (string)$t['km_kraj'] : '');
$vEur  = je_post() ? post('vraceno_eur') : ($t['vraceno_eur'] !== null ? rtrim(rtrim(number_format((float)$t['vraceno_eur'], 2, '.', ''), '0'), '.') : '');
$vRsd  = je_post() ? post('vraceno_rsd') : ($t['vraceno_rsd'] !== null ? (string)(int)$t['vraceno_rsd'] : '');
$vNap  = je_post() ? post('napomena_povratak') : (string)$t['napomena_povratak'];
$cek   = function (string $polje) use ($t) {
    return je_post() ? (postCek($polje) === 1) : ((int)$t[$polje] === 1);
};

pocetak_strane($ispravka ? 'Ispravi izveštaj' : 'Povratak sa terena', ['nazad' => $nazad]);
?>
<form class="sadrzaj" method="post" enctype="multipart/form-data"
      data-povratak
      data-km-start="<?= (int)$t['km_start'] ?>"
      data-ocek-eur="<?= h(number_format($o['ocek_eur'], 2, '.', '')) ?>"
      data-ocek-rsd="<?= h(number_format($o['ocek_rsd'], 2, '.', '')) ?>">
<?= csrf_polje() ?>

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?php if ($ispravka && $t['komentar_admina']): ?>
    <?= napomena_box('<b>Kancelarija traži ispravku:</b><br>' . nl2br(h($t['komentar_admina'])), 'greska', 'vrati') ?>
<?php endif; ?>

<div class="sekcija-naslov">Kilometraža na povratku</div>
<div class="karta"><div class="karta-telo">
    <div class="polje">
        <label for="kk">Stanje kilometar-sata <span class="opc">(polazak: <?= broj($t['km_start'], 0) ?>)</span></label>
        <div class="unos-sa-sufiksom">
            <input class="unos" id="kk" name="km_kraj" type="number" step="1" min="0" required
                   inputmode="numeric" placeholder="npr. <?= (int)$t['km_start'] + 500 ?>" value="<?= h($vKm) ?>">
            <span class="sufiks">km</span>
        </div>
        <div class="sitno" style="margin-top:7px;color:var(--tekst-2)" data-km-rezultat>Ukupno pređeno: —</div>
    </div>
    <?= foto_polje('foto_km_kraj',
            $t['foto_km_kraj'] ? 'Zameni fotografiju instrument table' : 'Slikaj instrument tablu',
            'Mora da se vidi kilometraža') ?>
</div></div>

<div class="sekcija-naslov">Vraćanje novca</div>
<div class="karta">
    <div class="karta-zaglavlje"><?= ikona('novac', 16) ?> Obračun gotovine</div>
    <div class="karta-telo">
        <div class="novac-mreza" style="margin-bottom:14px">
            <div class="novac">
                <div class="val">Treba vratiti EUR</div>
                <div class="iznos"><?= broj($o['ocek_eur'], 2) ?></div>
                <div class="opis"><?= broj($t['depozit_eur'], 0) ?> – <?= broj($o['got_eur'], 2) ?> potrošeno</div>
            </div>
            <div class="novac">
                <div class="val">Treba vratiti RSD</div>
                <div class="iznos"><?= broj($o['ocek_rsd'], 0) ?></div>
                <div class="opis"><?= broj($t['depozit_rsd'], 0) ?> – <?= broj($o['got_rsd'], 0) ?> potrošeno</div>
            </div>
        </div>
        <div class="dva">
            <div class="polje">
                <label for="ve">Vraćam EUR</label>
                <div class="unos-sa-sufiksom">
                    <input class="unos" id="ve" name="vraceno_eur" type="number" step="0.01" min="0"
                           inputmode="decimal" placeholder="<?= broj($o['ocek_eur'], 2) ?>" value="<?= h($vEur) ?>">
                    <span class="sufiks">EUR</span>
                </div>
            </div>
            <div class="polje">
                <label for="vr">Vraćam RSD</label>
                <div class="unos-sa-sufiksom">
                    <input class="unos" id="vr" name="vraceno_rsd" type="number" step="1" min="0"
                           inputmode="numeric" placeholder="<?= broj($o['ocek_rsd'], 0) ?>" value="<?= h($vRsd) ?>">
                    <span class="sufiks">RSD</span>
                </div>
            </div>
        </div>
        <div style="margin-top:12px" data-razlika></div>
    </div>
</div>

<?php if ($o['lic_eur'] > 0 || $o['lic_rsd'] > 0):
    $d = [];
    if ($o['lic_eur'] > 0) $d[] = novac($o['lic_eur'], 'EUR');
    if ($o['lic_rsd'] > 0) $d[] = novac($o['lic_rsd'], 'RSD');
?>
    <?= napomena_box('Firma ti duguje za troškove plaćene ličnim novcem: <b>' . h(implode(' i ', $d)) . '</b>.', 'info', 'novcanik') ?>
<?php endif; ?>

<div class="sekcija-naslov">Potvrda pre zatvaranja</div>
<?= potvrda('k_vozilo', 'Stanje vozila provereno i problem prijavljen', $cek('k_vozilo')) ?>
<?= potvrda('k_kabina', 'Kabina i tovarni prostor su uredni',           $cek('k_kabina')) ?>
<?= potvrda('k_alat',   'Alat i preostali materijal su vraćeni',        $cek('k_alat')) ?>
<?= potvrda('k_racuni', 'Svi računi su uneti',                          $cek('k_racuni')) ?>

<div class="polje" style="margin-top:12px" data-napomena-ako-fali="k_vozilo,k_kabina,k_alat,k_racuni">
    <label class="nalepnica" for="nk">Ako nešto nije u redu, ukratko opiši <span class="opc">(nije obavezno)</span></label>
    <textarea class="unos" id="nk" name="napomena_povratak" maxlength="1000"
              placeholder="npr. Fali mi jedan račun za parking."><?= h($vNap) ?></textarea>
</div>

<div class="dno-akcija">
    <button class="dugme d-glavno" type="submit">
        <?= ikona('povratak', 20) ?> <?= $ispravka ? 'Pošalji ispravljen izveštaj' : 'Zatvori teren i pošalji izveštaj' ?>
    </button>
</div>
</form>
<?php kraj_strane(); ?>
