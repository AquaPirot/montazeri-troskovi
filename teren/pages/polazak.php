<?php
/** Polazak na teren – otvaranje novog terena. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

if (aktivan_teren((int)$k['id'])) {
    postavi_poruku('Već imaš otvoren teren. Prvo ga zatvori.', 'info');
    idi('pocetna');
}

$vozila = redovi('SELECT * FROM vozila WHERE aktivno = 1 ORDER BY naziv');
if (!$vozila) {
    postavi_poruku('Nema unetih vozila. Javi se kancelariji.', 'greska');
    idi('pocetna');
}

$greske = [];

if (je_post()) {
    proveri_csrf();

    $projekat = post('projekat');
    $vozilo   = postCeo('vozilo_id');
    $depEur   = postBroj('depozit_eur') ?? 0.0;
    $depRsd   = postBroj('depozit_rsd') ?? 0.0;
    $kmStart  = postCeo('km_start');

    if ($projekat === '')            $greske[] = 'Unesi naziv projekta ili mesto montaže.';
    if (mb_strlen($projekat) > 200)  $greske[] = 'Naziv projekta je predugačak.';
    if (!$vozilo || !array_filter($vozila, fn($v) => (int)$v['id'] === $vozilo)) $greske[] = 'Izaberi vozilo.';
    if ($kmStart === null || $kmStart < 0) $greske[] = 'Unesi početnu kilometražu.';
    if ($depEur < 0 || $depRsd < 0)  $greske[] = 'Depozit ne može biti negativan.';

    $foto = null;
    if (!$greske) {
        try {
            $foto = primi_foto('foto_km_start', 'km');
        } catch (RuntimeException $e) {
            $greske[] = $e->getMessage();
        }
    }

    if (!$greske) {
        upit('INSERT INTO tereni
                (korisnik_id, vozilo_id, projekat, status, depozit_eur, depozit_rsd,
                 km_start, foto_km_start, p_vozilo, p_gorivo, p_teret, napomena_polazak, vreme_polaska)
              VALUES (?,?,?,"aktivan",?,?,?,?,?,?,?,?,NOW())', [
            $k['id'], $vozilo, $projekat, $depEur, $depRsd, $kmStart, $foto,
            postCek('p_vozilo'), postCek('p_gorivo'), postCek('p_teret'),
            post('napomena_polazak') !== '' ? post('napomena_polazak') : null,
        ]);
        postavi_poruku('Teren je otvoren. Srećan put!');
        idi('pocetna');
    }
}

pocetak_strane('Polazak na teren', ['nazad' => 'index.php?s=pocetna']);
?>
<form class="sadrzaj" method="post" enctype="multipart/form-data">
<?= csrf_polje() ?>

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?= napomena_box('Unos traje oko <b>30 sekundi</b>. Treba samo projekat, vozilo, novac i kilometraža.', 'info', 'sat') ?>

<div class="karta"><div class="karta-telo">
    <div class="polje">
        <label for="pr">Projekat ili mesto montaže</label>
        <input class="unos" id="pr" name="projekat" maxlength="200" required
               placeholder="npr. Hotel Zlatibor – kuhinja" value="<?= h(post('projekat')) ?>">
    </div>
    <div class="polje">
        <label>Vozilo</label>
        <div class="izbor-lista">
        <?php foreach ($vozila as $v): ?>
            <?= izbor_red('vozilo_id', (string)$v['id'], $v['naziv'], $v['registracija'],
                          postCeo('vozilo_id') === (int)$v['id'], 'kamion') ?>
        <?php endforeach; ?>
        </div>
    </div>
</div></div>

<div class="sekcija-naslov">Primljeni depozit</div>
<div class="karta"><div class="karta-telo"><div class="dva">
    <div class="polje">
        <label for="de">EUR</label>
        <div class="unos-sa-sufiksom">
            <input class="unos" id="de" name="depozit_eur" type="number" step="0.01" min="0"
                   inputmode="decimal" placeholder="0" value="<?= h(post('depozit_eur')) ?>">
            <span class="sufiks">EUR</span>
        </div>
    </div>
    <div class="polje">
        <label for="dr">RSD</label>
        <div class="unos-sa-sufiksom">
            <input class="unos" id="dr" name="depozit_rsd" type="number" step="1" min="0"
                   inputmode="numeric" placeholder="0" value="<?= h(post('depozit_rsd')) ?>">
            <span class="sufiks">RSD</span>
        </div>
    </div>
</div></div></div>

<div class="sekcija-naslov">Kilometraža na polasku</div>
<div class="karta"><div class="karta-telo">
    <div class="polje">
        <label for="km">Stanje kilometar-sata</label>
        <div class="unos-sa-sufiksom">
            <input class="unos" id="km" name="km_start" type="number" step="1" min="0" required
                   inputmode="numeric" placeholder="npr. 128450" value="<?= h(post('km_start')) ?>">
            <span class="sufiks">km</span>
        </div>
    </div>
    <?= foto_polje('foto_km_start', 'Slikaj instrument tablu', 'Mora da se vidi kilometraža') ?>
</div></div>

<div class="sekcija-naslov">Kratka provera pre polaska</div>
<?= potvrda('p_vozilo', 'Stanje vozila je provereno', postCek('p_vozilo') === 1) ?>
<?= potvrda('p_gorivo', 'Gorivo je provereno',        postCek('p_gorivo') === 1) ?>
<?= potvrda('p_teret',  'Tovarni prostor uredan i teret obezbeđen', postCek('p_teret') === 1) ?>

<div class="polje" style="margin-top:12px" data-napomena-ako-fali="p_vozilo,p_gorivo,p_teret">
    <label class="nalepnica" for="np">Ako nešto nije u redu, ukratko opiši <span class="opc">(nije obavezno)</span></label>
    <textarea class="unos" id="np" name="napomena_polazak" maxlength="1000"
              placeholder="npr. Zadnja desna guma slabije naduvana."><?= h(post('napomena_polazak')) ?></textarea>
</div>

<div class="dno-akcija">
    <button class="dugme d-zlato" type="submit"><?= ikona('cek', 20) ?> Kreni na teren</button>
</div>
</form>
<?php kraj_strane(); ?>
