<?php
/** Korisnici: šefovi ekipa i administratori. */
if (!defined('TEREN')) { exit; }

$ja = trazi_admina();
$greske = [];

if (je_post()) {
    proveri_csrf();
    $akcija = post('akcija');

    if ($akcija === 'dodaj') {
        $ime  = post('ime');
        $kime = mb_strtolower(post('korisnicko_ime'));
        $tel  = post('telefon');
        $loz  = (string)($_POST['lozinka'] ?? '');
        $ulog = post('uloga') === 'admin' ? 'admin' : 'sef';

        if ($ime === '')                              $greske[] = 'Unesi ime i prezime.';
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $kime)) $greske[] = 'Korisničko ime: 3–50 znakova, mala slova, brojevi, tačka, crta ili donja crta.';
        if (mb_strlen($loz) < 6)                      $greske[] = 'Lozinka mora imati najmanje 6 znakova.';
        if (!$greske && vrednost('SELECT COUNT(*) FROM korisnici WHERE korisnicko_ime = ?', [$kime]))
                                                      $greske[] = 'To korisničko ime već postoji.';

        if (!$greske) {
            upit('INSERT INTO korisnici (ime, korisnicko_ime, lozinka_hash, telefon, uloga) VALUES (?,?,?,?,?)',
                 [$ime, $kime, password_hash($loz, PASSWORD_DEFAULT), $tel !== '' ? $tel : null, $ulog]);
            postavi_poruku('Korisnik je dodat.');
            idi('korisnici');
        }
    }

    if ($akcija === 'lozinka') {
        $id  = postCeo('korisnik_id');
        $loz = (string)($_POST['lozinka'] ?? '');
        if (mb_strlen($loz) < 6) {
            postavi_poruku('Lozinka mora imati najmanje 6 znakova.', 'greska');
        } elseif ($id) {
            upit('UPDATE korisnici SET lozinka_hash = ? WHERE id = ?', [password_hash($loz, PASSWORD_DEFAULT), $id]);
            postavi_poruku('Lozinka je promenjena.');
        }
        idi('korisnici');
    }

    if ($akcija === 'stanje') {
        $id = postCeo('korisnik_id');
        if ($id === (int)$ja['id']) {
            postavi_poruku('Ne možeš da isključiš sopstveni nalog.', 'greska');
        } elseif ($id) {
            $aktivnih = (int)vrednost('SELECT COUNT(*) FROM tereni WHERE korisnik_id = ? AND status = "aktivan"', [$id]);
            if ($aktivnih > 0) {
                postavi_poruku('Taj korisnik ima otvoren teren. Prvo se teren mora zatvoriti.', 'greska');
            } else {
                upit('UPDATE korisnici SET aktivan = 1 - aktivan WHERE id = ?', [$id]);
                postavi_poruku('Sačuvano.');
            }
        }
        idi('korisnici');
    }
}

$korisnici = redovi('SELECT k.*,
                            (SELECT COUNT(*) FROM tereni t WHERE t.korisnik_id = k.id) AS broj_terena
                       FROM korisnici k ORDER BY k.aktivan DESC, k.uloga, k.ime');

pocetak_strane('Korisnici');
?>
<div class="sadrzaj">

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?php foreach ($korisnici as $kk): ?>
    <div class="karta"><div class="karta-telo">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="width:44px;height:44px;border-radius:50%;background:var(--grafit);color:var(--zlatna);display:flex;align-items:center;justify-content:center;font-weight:700;flex:none">
                <?= h(inicijali($kk['ime'])) ?>
            </span>
            <span style="flex:1;min-width:0">
                <span style="display:block;font-size:16px;font-weight:700"><?= h($kk['ime']) ?></span>
                <span class="sitno"><?= h($kk['korisnicko_ime']) ?><?= $kk['telefon'] ? ' · ' . h($kk['telefon']) : '' ?></span>
            </span>
            <span class="oznaka <?= $kk['uloga'] === 'admin' ? 'o-siva' : 'o-plava' ?>">
                <?= $kk['uloga'] === 'admin' ? 'Administrator' : 'Šef ekipe' ?>
            </span>
        </div>

        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <?php if ($kk['uloga'] === 'sef'): ?>
                <span class="cip"><?= (int)$kk['broj_terena'] ?> terena</span>
            <?php endif; ?>
            <?php if (!(int)$kk['aktivan']): ?><span class="cip">nalog isključen</span><?php endif; ?>
        </div>

        <details style="margin-top:12px">
            <summary class="sitno" style="cursor:pointer;font-weight:600;color:var(--tekst-2)">Promeni lozinku ili stanje naloga</summary>
            <form method="post" style="margin-top:10px">
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="lozinka">
                <input type="hidden" name="korisnik_id" value="<?= (int)$kk['id'] ?>">
                <div style="display:flex;gap:8px">
                    <input class="unos" name="lozinka" type="text" minlength="6" required
                           autocomplete="new-password" placeholder="nova lozinka" style="flex:1">
                    <button class="dugme d-glavno d-malo" type="submit" style="width:auto;padding-left:18px;padding-right:18px">Sačuvaj</button>
                </div>
            </form>
            <?php if ((int)$kk['id'] !== (int)$ja['id']): ?>
                <form method="post" style="margin-top:8px">
                    <?= csrf_polje() ?>
                    <input type="hidden" name="akcija" value="stanje">
                    <input type="hidden" name="korisnik_id" value="<?= (int)$kk['id'] ?>">
                    <button class="dugme d-obrub d-malo" type="submit"
                            data-pitaj="<?= (int)$kk['aktivan'] ? 'Isključiti ovaj nalog?' : 'Ponovo uključiti nalog?' ?>">
                        <?= (int)$kk['aktivan'] ? 'Isključi nalog' : 'Uključi nalog' ?>
                    </button>
                </form>
            <?php endif; ?>
        </details>
    </div></div>
<?php endforeach; ?>

<div class="sekcija-naslov">Novi korisnik</div>
<div class="karta"><div class="karta-telo">
    <form method="post">
        <?= csrf_polje() ?>
        <input type="hidden" name="akcija" value="dodaj">
        <div class="polje">
            <label for="ik">Ime i prezime</label>
            <input class="unos" id="ik" name="ime" maxlength="100" required placeholder="npr. Marko Ilić">
        </div>
        <div class="polje">
            <label for="uk">Korisničko ime</label>
            <input class="unos" id="uk" name="korisnicko_ime" maxlength="50" required
                   autocapitalize="none" placeholder="npr. marko">
        </div>
        <div class="polje">
            <label for="tk">Telefon <span class="opc">(nije obavezno)</span></label>
            <input class="unos" id="tk" name="telefon" maxlength="30" placeholder="npr. 063 111 222">
        </div>
        <div class="polje">
            <label for="lk">Početna lozinka</label>
            <input class="unos" id="lk" name="lozinka" type="text" minlength="6" required
                   autocomplete="new-password" placeholder="najmanje 6 znakova">
        </div>
        <div class="polje">
            <label>Uloga</label>
            <div class="plocice k2">
                <?= plocica('uloga', 'sef',   'Šef ekipe',     true,  'korisnik') ?>
                <?= plocica('uloga', 'admin', 'Administrator', false, 'stit') ?>
            </div>
        </div>
        <button class="dugme d-zlato" type="submit"><?= ikona('plus', 20) ?> Dodaj korisnika</button>
    </form>
</div></div>

</div>
<?php kraj_strane(nav_admin('korisnici')); ?>
