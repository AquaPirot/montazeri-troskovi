<?php
/** Početni ekran šefa ekipe – četiri glavne funkcije. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

$t = aktivan_teren((int)$k['id']);

/* Pogrešan trošak može odmah da se obriše dok je teren još otvoren. */
if (je_post() && post('akcija') === 'obrisi_trosak') {
    proveri_csrf();
    if (!$t) {
        postavi_poruku('Nemaš otvoren teren.', 'greska');
        idi('pocetna');
    }

    $trosakId = postCeo('trosak_id');
    $trosak = $trosakId
        ? red('SELECT * FROM troskovi WHERE id = ? AND teren_id = ?', [$trosakId, $t['id']])
        : null;

    if (!$trosak) {
        postavi_poruku('Trošak nije pronađen.', 'greska');
        idi('pocetna');
    }
    if (($trosak['izvor'] ?? 'radnik') === 'admin') {
        postavi_poruku('Ovaj unos je napravila kancelarija i može ga menjati samo administrator.', 'greska');
        idi('pocetna');
    }

    $obrisano = upit('DELETE FROM troskovi WHERE id = ? AND teren_id = ?', [$trosakId, $t['id']])->rowCount();
    if ($obrisano === 1) {
        obrisi_foto($trosak['foto_racun']);
        obrisi_foto($trosak['foto_slip']);
        postavi_poruku('Pogrešan trošak je obrisan. Sada možeš da ga uneseš ponovo.');
    }
    idi('pocetna');
}

/* Pogrešno evidentirana doplata može da se obriše dok je teren otvoren. */
if (je_post() && post('akcija') === 'obrisi_doplatu') {
    proveri_csrf();
    if (!$t) {
        postavi_poruku('Nemaš otvoren teren.', 'greska');
        idi('pocetna');
    }
    $doplataId = postCeo('doplata_id');
    $obrisano = $doplataId
        ? upit('DELETE FROM doplate WHERE id = ? AND teren_id = ?', [$doplataId, $t['id']])->rowCount()
        : 0;
    postavi_poruku($obrisano === 1 ? 'Pogrešna doplata je obrisana.' : 'Doplata nije pronađena.',
                   $obrisano === 1 ? 'uspeh' : 'greska');
    idi('pocetna');
}

$troskovi = $t ? troskovi_terena((int)$t['id']) : [];
$doplate = $t ? doplate_terena((int)$t['id']) : [];
$o = $t ? obracun($t, $troskovi, $doplate) : null;

// Vraćeni izveštaji koje treba ispraviti.
$ispravke = (int)vrednost('SELECT COUNT(*) FROM tereni WHERE korisnik_id = ? AND status = "ispravka"', [$k['id']]);

$ime = explode(' ', $k['ime'])[0];
pocetak_strane('Zdravo, ' . $ime);
?>
<div class="sadrzaj">

<?php if ($ispravke > 0): ?>
    <a href="index.php?s=moji" style="display:block">
    <?= napomena_box('<b>' . $ispravke . ' ' . ($ispravke === 1 ? 'izveštaj je vraćen' : 'izveštaja su vraćena')
        . ' na ispravku.</b> Dodirni da pogledaš.', 'greska', 'alarm') ?>
    </a>
<?php endif; ?>

<?php if ($t):
    $brT = count($troskovi);
    $brP = (int)vrednost('SELECT COUNT(*) FROM prijave WHERE teren_id = ?', [$t['id']]);
?>
    <section class="hero" aria-label="Tekući teren">
        <div class="hero-gore">
            <div style="min-width:0">
                <div class="h-lab">Tekući teren</div>
                <div class="h-proj"><?= h($t['projekat']) ?></div>
                <div class="h-meta"><?= h($t['vozilo_naziv']) ?> · <?= h($t['vozilo_reg']) ?> · od <?= h(datum($t['vreme_polaska'])) ?></div>
            </div>
            <?= oznaka_statusa($t['status']) ?>
        </div>
        <div class="hero-iznosi">
            <div>
                <div class="h-lab">Gotovina · EUR</div>
                <div class="h-iznos"><?= broj($o['ocek_eur'], 2) ?><small>€</small></div>
                <div class="h-sitno">od <?= broj($o['uk_depozit_eur'], 2) ?> primljeno</div>
            </div>
            <div>
                <div class="h-lab">Gotovina · RSD</div>
                <div class="h-iznos"><?= broj($o['ocek_rsd'], 0) ?><small>RSD</small></div>
                <div class="h-sitno">od <?= broj($o['uk_depozit_rsd'], 0) ?> primljeno</div>
            </div>
        </div>
        <div class="h-crta"></div>
        <div class="h-red">
            <?php if ($o['lic_eur'] > 0 || $o['lic_rsd'] > 0): ?>
            <div>
                <div class="h-lab">Firma ti duguje (lični novac)</div>
                <div class="h-iznos"><?php
                    $d = [];
                    if ($o['lic_eur'] > 0) $d[] = broj($o['lic_eur'], 2) . '<small>€</small>';
                    if ($o['lic_rsd'] > 0) $d[] = broj($o['lic_rsd'], 0) . '<small>RSD</small>';
                    echo implode(' &nbsp;·&nbsp; ', $d);
                ?></div>
            </div>
            <?php endif; ?>
            <div class="h-sitno" style="align-self:flex-end">
                <?= h(mnozina($brT, 'trošak', 'troška', 'troškova')) ?> · <?= h(mnozina($brP, 'prijava', 'prijave', 'prijava')) ?>
                <?php if ($doplate): ?> · <?= h(mnozina(count($doplate), 'doplata', 'doplate', 'doplata')) ?><?php endif; ?>
                · start <?= broj($t['km_start'], 0) ?> km
            </div>
        </div>
    </section>
<?php endif; ?>

    <div class="sekcija-naslov">Šta radiš sada?</div>

<?php if ($t): ?>
    <?= veliko_dugme('racun',    'Dodaj trošak',        'Račun, gorivo, putarina, hrana',  'index.php?s=trosak', true) ?>
    <?= veliko_dugme('alarm',    'Prijavi sa terena',   'Lom, materijal, nezavršeno',      'index.php?s=prijavi') ?>
    <?= veliko_dugme('novac',    'Dodaj primljeni novac','Naknadna doplata EUR ili RSD',     'index.php?s=doplata') ?>
    <?= veliko_dugme('povratak', 'Povratak i izveštaj', 'Zatvori teren i pošalji izveštaj','index.php?s=povratak') ?>
    <?= veliko_dugme('kamion',   'Polazak na teren',    'Teren je već otvoren',            null) ?>
<?php else: ?>
    <?= veliko_dugme('kamion',   'Polazak na teren',    'Otvori novi teren',               'index.php?s=polazak', true) ?>
    <?= veliko_dugme('racun',    'Dodaj trošak',        'Prvo otvori teren',               null) ?>
    <?= veliko_dugme('alarm',    'Prijavi sa terena',   'Prvo otvori teren',               null) ?>
    <?= veliko_dugme('povratak', 'Povratak i izveštaj', 'Prvo otvori teren',               null) ?>
    <div style="margin-top:14px">
        <?= napomena_box('Nemaš otvoren teren. Kada kreneš, dodirni <b>Polazak na teren</b>.', 'info', 'ostalo') ?>
    </div>
<?php endif; ?>


<?php if ($t): ?>
    <?php if ($doplate): ?>
    <div class="sekcija-naslov">Naknadno primljeni novac (<?= count($doplate) ?>)</div>
    <div class="karta"><div class="karta-telo">
    <?php foreach (array_reverse($doplate) as $d): ?>
        <div class="trosak">
            <span class="ik"><?= ikona('novac', 18) ?></span>
            <span class="sred">
                <span class="n1">Doplata depozita</span>
                <span class="n2"><?= h(datumKratko($d['kreirana'])) ?> · predao/la <?= h($d['predao']) ?></span>
                <?php if ($d['napomena']): ?><span class="n2"><?= h($d['napomena']) ?></span><?php endif; ?>
            </span>
            <span class="iz"><?= broj($d['iznos'], $d['valuta'] === 'RSD' ? 0 : 2) ?><small><?= h($d['valuta']) ?></small></span>
            <form method="post" data-bez-zakljucavanja>
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="obrisi_doplatu">
                <input type="hidden" name="doplata_id" value="<?= (int)$d['id'] ?>">
                <button class="brisi" type="submit" data-pitaj="Obrisati ovu pogrešnu doplatu?"
                        aria-label="Obriši doplatu"><?= ikona('kanta', 18) ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    </div></div>
    <?php endif; ?>

    <div class="sekcija-naslov">Troškovi na ovom terenu (<?= count($troskovi) ?>)</div>
    <div class="karta"><div class="karta-telo">
    <?php if (!$troskovi): ?>
        <div class="sitno">Još nema unetih troškova.</div>
    <?php else: foreach (array_reverse($troskovi) as $tr): ?>
        <div class="trosak">
            <span class="ik"><?= ikona(vrsta($tr['vrsta'])['ik'], 18) ?></span>
            <span class="sred">
                <span class="n1"><?= h(vrsta($tr['vrsta'])['n']) ?></span>
                <span class="n2">
                    <?= h(datumKratko($tr['kreiran'])) ?> · <?= h(PLACANJA[$tr['nacin_placanja']]['k']) ?>
                    <?php if (($tr['izvor'] ?? 'radnik') === 'admin'): ?> · unela kancelarija<?php endif; ?>
                    <?php if ($tr['vrsta'] === 'gorivo' && $tr['litri'] !== null): ?>
                        · <?= broj($tr['litri'], 1) ?> L
                    <?php endif; ?>
                    <?php if (!$tr['foto_racun']): ?> · <span class="lose">bez računa</span><?php endif; ?>
                </span>
                <?php if ($tr['foto_racun']): ?>
                    <a class="sitno" href="index.php?s=slika&amp;f=<?= h(rawurlencode($tr['foto_racun'])) ?>"
                       target="_blank" rel="noopener">Pogledaj račun</a>
                <?php endif; ?>
            </span>
            <span class="iz"><?= broj($tr['iznos'], $tr['valuta'] === 'RSD' ? 0 : 2) ?><small><?= h($tr['valuta']) ?></small></span>
            <?php if (($tr['izvor'] ?? 'radnik') !== 'admin'): ?>
            <form method="post" style="flex:none" data-bez-zakljucavanja>
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="obrisi_trosak">
                <input type="hidden" name="trosak_id" value="<?= (int)$tr['id'] ?>">
                <button class="brisi" type="submit" data-pitaj="Obrisati ovaj pogrešan trošak?"
                        aria-label="Obriši trošak"><?= ikona('kanta', 18) ?></button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
    </div></div>
<?php endif; ?>

</div>
<?php kraj_strane(nav_sef('pocetna')); ?>
