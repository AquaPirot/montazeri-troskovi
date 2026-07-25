<?php
/** Početni ekran šefa ekipe – četiri glavne funkcije. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

$t = aktivan_teren((int)$k['id']);
$o = $t ? obracun($t) : null;

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
    $brT = (int)vrednost('SELECT COUNT(*) FROM troskovi WHERE teren_id = ?', [$t['id']]);
    $brP = (int)vrednost('SELECT COUNT(*) FROM prijave  WHERE teren_id = ?', [$t['id']]);
?>
    <div class="karta">
        <div class="karta-telo">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px">
                <div>
                    <div class="sitno" style="font-weight:700;letter-spacing:.1em;text-transform:uppercase">Tekući teren</div>
                    <div style="font-size:17px;font-weight:700;letter-spacing:-.3px"><?= h($t['projekat']) ?></div>
                </div>
                <?= oznaka_statusa($t['status']) ?>
            </div>
            <div class="sitno" style="color:var(--tekst-2);margin-bottom:14px">
                <?= h($t['vozilo_naziv']) ?> · <?= h($t['vozilo_reg']) ?> · od <?= h(datum($t['vreme_polaska'])) ?>
            </div>
            <div class="novac-mreza">
                <div class="novac">
                    <div class="val">Gotovina EUR</div>
                    <div class="iznos"><?= broj($o['ocek_eur'], 2) ?></div>
                    <div class="opis">preostalo od <?= broj($t['depozit_eur'], 0) ?></div>
                </div>
                <div class="novac">
                    <div class="val">Gotovina RSD</div>
                    <div class="iznos"><?= broj($o['ocek_rsd'], 0) ?></div>
                    <div class="opis">preostalo od <?= broj($t['depozit_rsd'], 0) ?></div>
                </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                <span class="cip"><?= h(mnozina($brT, 'trošak', 'troška', 'troškova')) ?></span>
                <span class="cip"><?= h(mnozina($brP, 'prijava', 'prijave', 'prijava')) ?></span>
                <span class="cip">start <?= broj($t['km_start'], 0) ?> km</span>
            </div>
        </div>
    </div>
<?php endif; ?>

    <div class="sekcija-naslov">Šta radiš sada?</div>

<?php if ($t): ?>
    <?= veliko_dugme('racun',    'Dodaj trošak',        'Račun, gorivo, putarina, hrana',  'index.php?s=trosak', true) ?>
    <?= veliko_dugme('alarm',    'Prijavi sa terena',   'Lom, materijal, nezavršeno',      'index.php?s=prijavi') ?>
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

</div>
<?php kraj_strane(nav_sef('pocetna')); ?>
