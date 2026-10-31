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
        $tel  = post('telefon');
        $pin  = preg_replace('/\D/', '', post('pin'));
        $ulog = post('uloga') === 'admin' ? 'admin' : 'sef';

        if ($ime === '') $greske[] = 'Unesi ime i prezime.';
        if (!$greske && ($g = proveri_novi_pin($pin))) $greske[] = $g;

        if (!$greske) {
            // Korisničko ime treba samo za rezervnu prijavu lozinkom – pravi se iz imena.
            $kime = slobodno_korisnicko_ime($ime);
            // Lozinka se ne zna dok je administrator ne postavi; do tada ulaz je samo PIN-om.
            upit('INSERT INTO korisnici (ime, korisnicko_ime, lozinka_hash, pin_hash, telefon, uloga) VALUES (?,?,?,?,?,?)',
                 [$ime, $kime, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), pin_hash($pin),
                  $tel !== '' ? $tel : null, $ulog]);
            postavi_poruku('Dodat je ' . $ime . '. PIN za prijavu: ' . $pin . ' – saopšti mu ga lično.');
            idi('korisnici');
        }
    }

    if ($akcija === 'pin') {
        $id  = postCeo('korisnik_id');
        $k   = $id ? red('SELECT * FROM korisnici WHERE id = ?', [$id]) : null;
        $pin = preg_replace('/\D/', '', post('pin'));
        if (!$k) {
            postavi_poruku('Korisnik nije pronađen.', 'greska');
        } elseif (!(int)$k['aktivan']) {
            postavi_poruku('Nalog je isključen. Prvo ga uključi.', 'greska');
        } elseif ($g = proveri_novi_pin($pin, (int)$id)) {
            postavi_poruku($g, 'greska');
        } else {
            upit('UPDATE korisnici SET pin_hash = ? WHERE id = ?', [pin_hash($pin), $id]);
            postavi_poruku('Novi PIN za ' . $k['ime'] . ': ' . $pin . ' – saopšti ga lično. Stari PIN više ne važi.');
        }
        idi('korisnici');
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
            } elseif ((int)$k['aktivan'] === 1) {
                // Isključen nalog oslobađa PIN; pri ponovnom uključenju dodeljuje se nov.
                upit('UPDATE korisnici SET aktivan = 0, pin_hash = NULL WHERE id = ?', [$id]);
                postavi_poruku('Nalog je isključen i njegov PIN više ne važi.');
            } else {
                upit('UPDATE korisnici SET aktivan = 1 WHERE id = ?', [$id]);
                postavi_poruku('Nalog je uključen. Dodeli mu PIN da bi mogao da se prijavi.');
            }
        }
        idi('korisnici');
    }
}

$korisnici = redovi('SELECT k.*,
                            (SELECT COUNT(*) FROM tereni t WHERE t.korisnik_id = k.id) AS broj_terena
                       FROM korisnici k ORDER BY k.aktivan DESC, k.uloga, k.ime');

$bezPina = array_filter($korisnici, fn($x) => (int)$x['aktivan'] === 1 && empty($x['pin_hash']));
$predlog = predlozi_pin();

pocetak_strane('Korisnici', ['sirina' => 'siroka']);
?>
<div class="sadrzaj">

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?php if ($bezPina): ?>
    <?= napomena_box('<b>' . h(mnozina(count($bezPina), 'korisnik još nema', 'korisnika još nemaju', 'korisnika još nema'))
        . ' PIN</b> i ne može da se prijavi: ' . h(implode(', ', array_map(fn($x) => $x['ime'], $bezPina)))
        . '. Otvori korisnika i dodeli PIN.', 'upozorenje', 'kljucic') ?>
<?php endif; ?>

<div class="mreza-stavki">
<?php foreach ($korisnici as $kk):
    $imaPin = !empty($kk['pin_hash']);
?>
    <div class="karta"><div class="karta-telo">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="width:44px;height:44px;border-radius:12px;background:var(--tamna);color:var(--akcenat);display:flex;align-items:center;justify-content:center;font-weight:800;flex:none">
                <?= h(inicijali($kk['ime'])) ?>
            </span>
            <span style="flex:1;min-width:0">
                <span style="display:block;font-size:16px;font-weight:800"><?= h($kk['ime']) ?></span>
                <span class="sitno"><?= $kk['telefon'] ? h($kk['telefon']) : 'bez telefona' ?></span>
            </span>
            <span class="oznaka <?= $kk['uloga'] === 'admin' ? 'o-siva' : 'o-plava' ?>">
                <?= $kk['uloga'] === 'admin' ? 'Administrator' : 'Šef ekipe' ?>
            </span>
        </div>

        <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
            <?php if (!(int)$kk['aktivan']): ?>
                <span class="cip siva">nalog isključen</span>
            <?php elseif ($imaPin): ?>
                <span class="cip" style="background:var(--zelena-bg);color:#15803D">PIN dodeljen</span>
            <?php else: ?>
                <span class="cip upozorenje">nema PIN</span>
            <?php endif; ?>
            <?php if ($kk['uloga'] === 'sef'): ?>
                <span class="cip siva"><?= h(mnozina((int)$kk['broj_terena'], 'teren', 'terena', 'terena')) ?></span>
            <?php endif; ?>
        </div>

        <?php if ((int)$kk['aktivan']): ?>
        <form method="post" style="margin-top:12px">
            <?= csrf_polje() ?>
            <input type="hidden" name="akcija" value="pin">
            <input type="hidden" name="korisnik_id" value="<?= (int)$kk['id'] ?>">
            <label class="nalepnica" for="p<?= (int)$kk['id'] ?>"><?= $imaPin ? 'Promeni PIN' : 'Dodeli PIN' ?></label>
            <div style="display:flex;gap:8px">
                <input class="unos pin" id="p<?= (int)$kk['id'] ?>" name="pin" type="text" inputmode="numeric"
                       pattern="[0-9]{4}" maxlength="4" required autocomplete="off" data-pin
                       style="flex:1;font-size:22px" value="<?= $imaPin ? '' : h($predlog) ?>"
                       placeholder="<?= h($predlog) ?>">
                <button class="dugme d-glavno d-malo" type="submit" style="width:auto;padding-left:18px;padding-right:18px">
                    Sačuvaj
                </button>
            </div>
            <div class="sitno" style="margin-top:6px">Predlog slobodnog PIN-a: <b class="mono"><?= h($predlog) ?></b>. PIN se vidi samo u trenutku dodele.</div>
        </form>
        <?php endif; ?>

        <details style="margin-top:12px">
            <summary class="sitno" style="font-weight:600;color:var(--tekst-2)">Izmeni podatke, lozinku ili stanje naloga</summary>

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
                    <label for="u<?= (int)$kk['id'] ?>">Korisničko ime <span class="opc">(samo za prijavu lozinkom)</span></label>
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
                <label class="nalepnica" for="l<?= (int)$kk['id'] ?>">Lozinka za rezervnu prijavu <span class="opc">(nije obavezno)</span></label>
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
</div>

<div class="sekcija-naslov">Novi korisnik</div>
<div class="karta" style="max-width:560px"><div class="karta-telo">
    <form method="post">
        <?= csrf_polje() ?>
        <input type="hidden" name="akcija" value="dodaj">
        <div class="polje">
            <label for="ik">Ime i prezime</label>
            <input class="unos" id="ik" name="ime" maxlength="100" required placeholder="npr. Marko Ilić"
                   value="<?= h(post('ime')) ?>">
        </div>
        <div class="dva">
            <div class="polje">
                <label for="np">PIN za prijavu</label>
                <input class="unos pin" id="np" name="pin" type="text" inputmode="numeric" pattern="[0-9]{4}"
                       maxlength="4" required autocomplete="off" data-pin style="font-size:24px"
                       value="<?= h(post('pin', $predlog)) ?>">
            </div>
            <div class="polje">
                <label for="tk">Telefon <span class="opc">(nije obavezno)</span></label>
                <input class="unos" id="tk" name="telefon" maxlength="30" placeholder="063 111 222"
                       value="<?= h(post('telefon')) ?>">
            </div>
        </div>
        <div class="polje">
            <label>Uloga</label>
            <div class="plocice k2">
                <?= plocica('uloga', 'sef',   'Šef ekipe',     true,  'korisnik') ?>
                <?= plocica('uloga', 'admin', 'Administrator', false, 'stit') ?>
            </div>
        </div>
        <button class="dugme d-akcenat" type="submit"><?= ikona('plus', 20) ?> Dodaj korisnika</button>
    </form>
</div></div>

</div>
<?php kraj_strane(nav_admin('korisnici')); ?>
