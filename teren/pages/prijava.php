<?php
/**
 * Prijava. Podrazumevano PIN-om od 4 broja; prijava korisničkim imenom
 * i lozinkom ostaje kao rezervni ulaz za kancelariju.
 */
if (!defined('TEREN')) { exit; }

if (prijavljen()) idi(admin() ? 'admin' : 'pocetna');

$nacin  = get('nacin') === 'lozinka' ? 'lozinka' : 'pin';
$greska = '';

if (je_post()) {
    proveri_csrf();

    $blok = blokada_minuta();
    if ($blok !== null) {
        $greska = 'Previše pogrešnih pokušaja. Pokušaj ponovo za ' . $blok . ' min.';
    } elseif ($nacin === 'pin') {
        $pin = preg_replace('/\D/', '', post('pin'));
        if (strlen($pin) !== 4) {
            $greska = 'PIN ima 4 broja.';
        } elseif (prijavi_pinom($pin)) {
            ocisti_neuspehe();
            idi(admin() ? 'admin' : 'pocetna');
        } else {
            zabelezi_neuspeh();
            $greska = 'Pogrešan PIN.';
            usleep(400000);
        }
    } else {
        $korisnicko = post('korisnicko_ime');
        $lozinka    = (string)($_POST['lozinka'] ?? '');
        if ($korisnicko === '' || $lozinka === '') {
            $greska = 'Unesi korisničko ime i lozinku.';
        } elseif (prijavi($korisnicko, $lozinka)) {
            ocisti_neuspehe();
            idi(admin() ? 'admin' : 'pocetna');
        } else {
            zabelezi_neuspeh();
            $greska = 'Pogrešno korisničko ime ili lozinka.';
            usleep(400000);
        }
    }
}

pocetak_strane('Prijava', ['bez_zaglavlja' => true]);
?>
<main class="prijava-ekran">
    <div class="prijava-karta">
        <div class="logo"><?= ikona('kamion', 28) ?></div>
        <h1>AG GROUP · Teren</h1>

        <?php if ($nacin === 'pin'): ?>
            <p class="pod">Unesite svoj PIN za pristup</p>

            <?php if ($greska): ?>
                <div class="greska-prijava" role="alert"><?= ikona('alarm', 18) ?><span><?= h($greska) ?></span></div>
            <?php endif; ?>

            <form method="post" data-pin-forma>
                <?= csrf_polje() ?>
                <input class="unos pin" id="pin" name="pin" type="password" inputmode="numeric"
                       pattern="[0-9]{4}" maxlength="4" autocomplete="one-time-code" aria-label="PIN"
                       placeholder="••••" required autofocus data-pin>
                <button class="dugme d-glavno" type="submit">Prijavi se</button>
            </form>

            <a class="prijava-link" href="index.php?s=prijava&amp;nacin=lozinka">Prijava korisničkim imenom i lozinkom</a>
        <?php else: ?>
            <p class="pod">Rezervna prijava za kancelariju</p>

            <?php if ($greska): ?>
                <div class="greska-prijava" role="alert"><?= ikona('alarm', 18) ?><span><?= h($greska) ?></span></div>
            <?php endif; ?>

            <form method="post" action="index.php?s=prijava&amp;nacin=lozinka">
                <?= csrf_polje() ?>
                <div class="polje">
                    <label for="ki">Korisničko ime</label>
                    <input class="unos" id="ki" name="korisnicko_ime" autocapitalize="none" autocomplete="username"
                           value="<?= h(post('korisnicko_ime')) ?>" required autofocus>
                </div>
                <div class="polje">
                    <label for="lz">Lozinka</label>
                    <input class="unos" id="lz" name="lozinka" type="password" autocomplete="current-password" required>
                </div>
                <button class="dugme d-glavno" type="submit">Prijavi se</button>
            </form>

            <a class="prijava-link" href="index.php?s=prijava">← Nazad na prijavu PIN-om</a>
        <?php endif; ?>
    </div>
</main>
<?php kraj_strane(); ?>
