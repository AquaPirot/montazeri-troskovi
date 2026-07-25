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

    if ($akcija === 'izmeni') {
        $id   = postCeo('korisnik_id');
        $k    = $id ? red('SELECT * FROM korisnici WHERE id = ?', [$id]) : null;
        $ime  = post('ime');
        $kime = mb_strtolower(post('korisnicko_ime'));
        $tel  = post('telefon');
        $ulog = post('uloga') === 'admin' ? 'admin' : 'sef';

        if (!$k) {
            $greske[] = 'Korisnik nije pronađen.';
        } else {
            // Svoju ulogu administrator ne menja – da ne bi ostao bez pristupa.
            if ($id === (int)$ja['id']) $ulog = $k['uloga'];

            if ($ime === '')                                 $greske[] = 'Unesi ime i prezime.';
            if (!preg_match('/^[a-z0-9._-]{3,50}$/', $kime))  $greske[] = 'Korisničko ime: 3–50 znakova, mala slova, brojevi, tačka, crta ili donja crta.';
            if (!$greske && vrednost('SELECT COUNT(*) FROM korisnici WHERE korisnicko_ime = ? AND id <> ?', [$kime, $id]))
                                                             $greske[] = 'To korisničko ime već koristi neko drugi.';

            // Poslednji uključeni administrator mora da ostane administrator.
            if (!$greske && $k['uloga'] === 'admin' && (int)$k['aktivan'] === 1
                && $ulog !== 'admin' && broj_aktivnih_admina() <= 1)
                $greske[] = 'Ovo je jedini administrator. Prvo dodaj drugog pa onda promeni ulogu.';

            // Šef koji je na terenu ne može da postane administrator (teren bi ostao bez vlasnika ekrana).
            if (!$greske && $k['uloga'] === 'sef' && $ulog === 'admin'
                && vrednost('SELECT COUNT(*) FROM tereni WHERE korisnik_id = ? AND status = "aktivan"', [$id]))
                $greske[] = 'Taj korisnik je trenutno na terenu. Prvo se teren mora zatvoriti.';
        }

        if (!$greske) {
            upit('UPDATE korisnici SET ime = ?, korisnicko_ime = ?, telefon = ?, uloga = ? WHERE id = ?',
                 [$ime, $kime, $tel !== '' ? $tel : null, $ulog, $id]);
            postavi_poruku('Podaci su sačuvani.');
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
        $k  = $id ? red('SELECT * FROM korisnici WHERE id = ?', [$id]) : null;
        if ($id === (int)$ja['id']) {
            postavi_poruku('Ne možeš da isključiš sopstveni nalog.', 'greska');
        } elseif ($k) {
            $aktivnih = (int)vrednost('SELECT COUNT(*) FROM tereni WHERE korisnik_id = ? AND status = "aktivan"', [$id]);
            if ($aktivnih > 0) {
                postavi_poruku('Taj korisnik ima otvoren teren. Prvo se teren mora zatvoriti.', 'greska');
            } elseif ($k['uloga'] === 'admin' && (int)$k['aktivan'] === 1 && broj_aktivnih_admina() <= 1) {
                postavi_poruku('Ovo je jedini administrator. Nalog ne može da se isključi.', 'greska');
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
                <span class="cip"><?= h(mnozina((int)$kk['broj_terena'], 'teren', 'terena', 'terena')) ?></span>
            <?php endif; ?>
            <?php if (!(int)$kk['aktivan']): ?><span class="cip">nalog isključen</span><?php endif; ?>
        </div>

        <details style="margin-top:12px">
            <summary class="sitno" style="cursor:pointer;font-weight:600;color:var(--tekst-2)">Izmeni podatke, lozinku ili stanje naloga</summary>

            <div class="razdelnik"></div>
            <form method="post">
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="izmeni">
                <input type="hidden" name="korisnik_id" value="<?= (int)$kk['id'] ?>">
                <div class="polje">
                    <label for="i<?= (int)$kk['id'] ?>">Ime i prezime</label>
                    <input class="unos" id="i<?= (int)$kk['id'] ?>" name="ime" maxlength="100" required
                           value="<?= h($kk['ime']) ?>">
                </div>
                <div class="polje">
                    <label for="u<?= (int)$kk['id'] ?>">Korisničko ime <span class="opc">(za prijavu)</span></label>
                    <input class="unos" id="u<?= (int)$kk['id'] ?>" name="korisnicko_ime" maxlength="50" required
                           autocapitalize="none" value="<?= h($kk['korisnicko_ime']) ?>">
                </div>
                <div class="polje">
                    <label for="t<?= (int)$kk['id'] ?>">Telefon <span class="opc">(nije obavezno)</span></label>
                    <input class="unos" id="t<?= (int)$kk['id'] ?>" name="telefon" maxlength="30"
                           value="<?= h($kk['telefon']) ?>">
                </div>
                <div class="polje">
                    <label for="r<?= (int)$kk['id'] ?>">Uloga</label>
                    <?php if ((int)$kk['id'] === (int)$ja['id']): ?>
                        <input class="unos" value="Administrator (sopstveni nalog)" disabled>
                        <div class="sitno" style="margin-top:6px">Sopstvenu ulogu ne možeš da promeniš.</div>
                    <?php else: ?>
                        <select class="unos" id="r<?= (int)$kk['id'] ?>" name="uloga">
                            <option value="sef"   <?= $kk['uloga'] === 'sef'   ? 'selected' : '' ?>>Šef ekipe</option>
                            <option value="admin" <?= $kk['uloga'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    <?php endif; ?>
                </div>
                <button class="dugme d-glavno d-malo" type="submit"><?= ikona('cek', 18) ?> Sačuvaj podatke</button>
            </form>

            <div class="razdelnik"></div>
            <form method="post">
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="lozinka">
                <input type="hidden" name="korisnik_id" value="<?= (int)$kk['id'] ?>">
                <label class="nalepnica" for="l<?= (int)$kk['id'] ?>">Nova lozinka</label>
                <div style="display:flex;gap:8px">
                    <input class="unos" id="l<?= (int)$kk['id'] ?>" name="lozinka" type="text" minlength="6" required
                           autocomplete="new-password" placeholder="najmanje 6 znakova" style="flex:1">
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
