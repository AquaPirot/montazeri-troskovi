<?php
/** Prijava na sistem. */
if (!defined('TEREN')) { exit; }

if (prijavljen()) idi(admin() ? 'admin' : 'pocetna');

$greska = '';
if (je_post()) {
    proveri_csrf();
    $korisnicko = post('korisnicko_ime');
    $lozinka    = (string)($_POST['lozinka'] ?? '');

    if ($korisnicko === '' || $lozinka === '') {
        $greska = 'Unesi korisničko ime i lozinku.';
    } elseif (prijavi($korisnicko, $lozinka)) {
        idi(admin() ? 'admin' : 'pocetna');
    } else {
        $greska = 'Pogrešno korisničko ime ili lozinka.';
        usleep(400000);
    }
}

pocetak_strane('Prijava', ['bez_zaglavlja' => true]);
?>
<div class="prijava-ekran">
    <div class="znak"><?= ikona('kamion', 30) ?></div>
    <h1>Teren</h1>
    <div class="pod">Prohoreca AG group</div>

    <?php if ($greska): ?>
        <div class="greska-prijava"><?= ikona('alarm', 18) ?><span><?= h($greska) ?></span></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
        <?= csrf_polje() ?>
        <div class="polje">
            <label for="ki">Korisničko ime</label>
            <input class="unos" id="ki" name="korisnicko_ime" autocapitalize="none" autocomplete="username"
                   value="<?= h(post('korisnicko_ime')) ?>" required>
        </div>
        <div class="polje">
            <label for="lz">Lozinka</label>
            <input class="unos" id="lz" name="lozinka" type="password" autocomplete="current-password" required>
        </div>
        <button class="dugme d-zlato" type="submit">Prijavi se</button>
    </form>

    <div class="podnozje-prijava">Ako si zaboravio lozinku, javi se kancelariji.</div>
</div>
<?php kraj_strane(); ?>
