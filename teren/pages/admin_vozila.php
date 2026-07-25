<?php
/** Vozni park. Dodirom na vozilo otvara se istorija i servisna knjiga. */
if (!defined('TEREN')) { exit; }

trazi_admina();
$greske = [];

if (je_post()) {
    proveri_csrf();

    if (post('akcija') === 'dodaj') {
        $naziv = post('naziv');
        $reg   = post('registracija');
        $pot   = postBroj('ocekivana_potrosnja');

        if ($naziv === '') $greske[] = 'Unesi naziv vozila.';
        if ($reg === '')   $greske[] = 'Unesi registraciju.';
        if ($pot !== null && ($pot <= 0 || $pot > 100)) $greske[] = 'Očekivana potrošnja mora biti između 0 i 100 L/100 km.';

        if (!$greske) {
            upit('INSERT INTO vozila (naziv, registracija, ocekivana_potrosnja) VALUES (?,?,?)',
                 [$naziv, $reg, $pot]);
            postavi_poruku('Vozilo je dodato.');
            idi('vozila');
        }
    }
}

$vozila = redovi('SELECT * FROM vozila ORDER BY aktivno DESC, naziv');

pocetak_strane('Vozila');
?>
<div class="sadrzaj">

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<?php if (!$vozila): ?>
    <?= prazno_stanje('kamion', 'Nema vozila', 'Dodaj prvo vozilo ispod.') ?>
<?php else: foreach ($vozila as $v):
    $st      = statistika_vozila((int)$v['id']);
    $reg     = registracija_vozila((int)$v['id']);
    $danaReg = $reg ? danaDo($reg['vazi_do']) : null;
?>
    <a class="stavka" href="index.php?s=vozilo&id=<?= (int)$v['id'] ?>">
        <span class="gore">
            <span style="display:flex;align-items:center;gap:11px;min-width:0">
                <span style="width:40px;height:40px;border-radius:10px;background:var(--grafit);color:var(--zlatna);display:flex;align-items:center;justify-content:center;flex:none">
                    <?= ikona('kamion', 20) ?>
                </span>
                <span style="min-width:0">
                    <span class="proj" style="display:block"><?= h($v['naziv']) ?></span>
                    <span class="sitno"><?= h($v['registracija']) ?></span>
                </span>
            </span>
            <?php if (!(int)$v['aktivno']): ?>
                <span class="oznaka o-siva">Neaktivno</span>
            <?php elseif ($danaReg !== null && $danaReg < 0): ?>
                <span class="oznaka o-crvena"><span class="tacka"></span>Registracija istekla</span>
            <?php elseif ($danaReg !== null && $danaReg <= 30): ?>
                <span class="oznaka o-narandzasta"><span class="tacka"></span>Reg. za <?= (int)$danaReg ?> dana</span>
            <?php endif; ?>
        </span>

        <span class="dole" style="border-top:none;margin-top:4px;padding-top:0">
            <span class="cip <?= $st['upozorenje'] ? 'upozorenje' : '' ?>">
                <?= $st['potrosnja'] !== null ? h(broj($st['potrosnja'], 1)) . ' L/100' : 'nema podataka' ?>
            </span>
            <?php if ($st['ocek_potrosnja'] !== null): ?>
                <span class="cip">očekivano <?= h(broj($st['ocek_potrosnja'], 1)) ?></span>
            <?php endif; ?>
            <span class="cip"><?= h(mnozina((int)$st['broj_terena'], 'teren', 'terena', 'terena')) ?></span>
            <?php if ($st['km'] > 0): ?>
                <span class="cip"><?= h(broj($st['km'], 0)) ?> km</span>
            <?php endif; ?>
        </span>
    </a>
<?php endforeach; endif; ?>

<?= napomena_box('Dodirni vozilo za <b>istoriju terena, potrošnju po terenu i servisnu knjigu</b>.', 'info', 'lista') ?>

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
