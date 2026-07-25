<?php
/** Vozila i očekivana prosečna potrošnja. */
if (!defined('TEREN')) { exit; }

trazi_admina();
$greske = [];

if (je_post()) {
    proveri_csrf();
    $akcija = post('akcija');

    if ($akcija === 'dodaj') {
        $naziv = post('naziv');
        $reg   = post('registracija');
        $pot   = postBroj('ocekivana_potrosnja');

        if ($naziv === '')                     $greske[] = 'Unesi naziv vozila.';
        if ($reg === '')                       $greske[] = 'Unesi registraciju.';
        if ($pot !== null && ($pot <= 0 || $pot > 100)) $greske[] = 'Očekivana potrošnja mora biti između 0 i 100 L/100 km.';

        if (!$greske) {
            upit('INSERT INTO vozila (naziv, registracija, ocekivana_potrosnja) VALUES (?,?,?)',
                 [$naziv, $reg, $pot]);
            postavi_poruku('Vozilo je dodato.');
            idi('vozila');
        }
    }

    if ($akcija === 'potrosnja') {
        $id  = postCeo('vozilo_id');
        $pot = postBroj('ocekivana_potrosnja');
        if ($id && ($pot === null || ($pot > 0 && $pot <= 100))) {
            upit('UPDATE vozila SET ocekivana_potrosnja = ? WHERE id = ?', [$pot, $id]);
            postavi_poruku('Očekivana potrošnja je sačuvana.');
        } else {
            postavi_poruku('Očekivana potrošnja mora biti između 0 i 100 L/100 km.', 'greska');
        }
        idi('vozila');
    }

    if ($akcija === 'stanje') {
        $id = postCeo('vozilo_id');
        if ($id) {
            upit('UPDATE vozila SET aktivno = 1 - aktivno WHERE id = ?', [$id]);
            postavi_poruku('Sačuvano.');
        }
        idi('vozila');
    }
}

$vozila = redovi('SELECT v.*,
                         (SELECT COUNT(*) FROM tereni t WHERE t.vozilo_id = v.id) AS broj_terena
                    FROM vozila v ORDER BY v.aktivno DESC, v.naziv');

pocetak_strane('Vozila');
?>
<div class="sadrzaj">

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?= napomena_box('Očekivana potrošnja se koristi samo kao <b>orijentir za proveru</b> izveštaja, '
    . 'a ne kao dokaz o nepravilnosti.', 'info', 'grafik') ?>

<?php foreach ($vozila as $v): ?>
    <div class="karta"><div class="karta-telo">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
            <span style="width:44px;height:44px;border-radius:10px;background:var(--grafit);color:var(--zlatna);display:flex;align-items:center;justify-content:center;flex:none">
                <?= ikona('kamion', 22) ?>
            </span>
            <span style="flex:1;min-width:0">
                <span style="display:block;font-size:16px;font-weight:700"><?= h($v['naziv']) ?></span>
                <span class="sitno"><?= h($v['registracija']) ?> · <?= (int)$v['broj_terena'] ?> terena</span>
            </span>
            <?php if (!(int)$v['aktivno']): ?><span class="oznaka o-siva">Neaktivno</span><?php endif; ?>
        </div>

        <form method="post">
            <?= csrf_polje() ?>
            <input type="hidden" name="akcija" value="potrosnja">
            <input type="hidden" name="vozilo_id" value="<?= (int)$v['id'] ?>">
            <label class="nalepnica" for="p<?= (int)$v['id'] ?>">Očekivana prosečna potrošnja</label>
            <div style="display:flex;gap:8px">
                <div class="unos-sa-sufiksom" style="flex:1">
                    <input class="unos" id="p<?= (int)$v['id'] ?>" name="ocekivana_potrosnja" type="number"
                           step="0.1" min="0" max="100" inputmode="decimal" placeholder="npr. 11,5"
                           value="<?= $v['ocekivana_potrosnja'] !== null ? h(rtrim(rtrim(number_format((float)$v['ocekivana_potrosnja'], 2, '.', ''), '0'), '.')) : '' ?>">
                    <span class="sufiks">L/100</span>
                </div>
                <button class="dugme d-glavno d-malo" type="submit" style="width:auto;padding-left:18px;padding-right:18px">Sačuvaj</button>
            </div>
        </form>

        <form method="post" style="margin-top:10px">
            <?= csrf_polje() ?>
            <input type="hidden" name="akcija" value="stanje">
            <input type="hidden" name="vozilo_id" value="<?= (int)$v['id'] ?>">
            <button class="dugme d-obrub d-malo" type="submit"
                    data-pitaj="<?= (int)$v['aktivno'] ? 'Sakriti vozilo iz izbora pri polasku?' : 'Vratiti vozilo u izbor?' ?>">
                <?= (int)$v['aktivno'] ? 'Isključi iz upotrebe' : 'Vrati u upotrebu' ?>
            </button>
        </form>
    </div></div>
<?php endforeach; ?>

<div class="sekcija-naslov">Novo vozilo</div>
<div class="karta"><div class="karta-telo">
    <form method="post">
        <?= csrf_polje() ?>
        <input type="hidden" name="akcija" value="dodaj">
        <div class="polje">
            <label for="nv">Naziv</label>
            <input class="unos" id="nv" name="naziv" maxlength="100" required placeholder="npr. Renault Master">
        </div>
        <div class="polje">
            <label for="rv">Registracija</label>
            <input class="unos" id="rv" name="registracija" maxlength="30" required placeholder="npr. BG-114-ŽŠ">
        </div>
        <div class="polje">
            <label for="pv">Očekivana prosečna potrošnja <span class="opc">(nije obavezno)</span></label>
            <div class="unos-sa-sufiksom">
                <input class="unos" id="pv" name="ocekivana_potrosnja" type="number" step="0.1" min="0" max="100"
                       inputmode="decimal" placeholder="npr. 11,5">
                <span class="sufiks">L/100</span>
            </div>
        </div>
        <button class="dugme d-zlato" type="submit"><?= ikona('plus', 20) ?> Dodaj vozilo</button>
    </form>
</div></div>

</div>
<?php kraj_strane(nav_admin('vozila')); ?>
