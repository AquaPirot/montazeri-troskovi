<?php
/** Moj nalog: promena lozinke i odjava. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
$greske = [];

if (je_post()) {
    proveri_csrf();
    if (post('akcija') === 'lozinka') {
        $stara = (string)($_POST['stara'] ?? '');
        $nova  = (string)($_POST['nova'] ?? '');

        if (!password_verify($stara, $k['lozinka_hash'])) $greske[] = 'Stara lozinka nije tačna.';
        if (mb_strlen($nova) < 6)                         $greske[] = 'Nova lozinka mora imati najmanje 6 znakova.';

        if (!$greske) {
            upit('UPDATE korisnici SET lozinka_hash = ? WHERE id = ?',
                 [password_hash($nova, PASSWORD_DEFAULT), $k['id']]);
            postavi_poruku('Lozinka je promenjena.');
            idi('nalog');
        }
    }
}

pocetak_strane('Moj nalog', ['nazad' => 'index.php?s=' . ($k['uloga'] === 'admin' ? 'admin' : 'pocetna')]);
?>
<div class="sadrzaj">

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<div class="karta"><div class="karta-telo">
    <div class="red"><span class="k">Ime</span><span class="v"><?= h($k['ime']) ?></span></div>
    <div class="red"><span class="k">Korisničko ime</span><span class="v"><?= h($k['korisnicko_ime']) ?></span></div>
    <div class="red"><span class="k">Uloga</span><span class="v"><?= $k['uloga'] === 'admin' ? 'Administrator' : 'Šef ekipe' ?></span></div>
</div></div>

<div class="sekcija-naslov">Promena lozinke</div>
<div class="karta"><div class="karta-telo">
    <form method="post">
        <?= csrf_polje() ?>
        <input type="hidden" name="akcija" value="lozinka">
        <div class="polje">
            <label for="sl">Stara lozinka</label>
            <input class="unos" id="sl" name="stara" type="password" required autocomplete="current-password">
        </div>
        <div class="polje">
            <label for="nl">Nova lozinka</label>
            <input class="unos" id="nl" name="nova" type="password" minlength="6" required autocomplete="new-password">
        </div>
        <button class="dugme d-glavno" type="submit"><?= ikona('kljucic', 20) ?> Sačuvaj novu lozinku</button>
    </form>
</div></div>

<div class="sekcija-naslov">Odjava</div>
<form method="post" action="index.php?s=odjava">
    <?= csrf_polje() ?>
    <button class="dugme d-obrub" type="submit"><?= ikona('izlaz', 20) ?> Odjavi se</button>
</form>

</div>
<?php kraj_strane($k['uloga'] === 'admin' ? nav_admin('') : nav_sef('')); ?>
