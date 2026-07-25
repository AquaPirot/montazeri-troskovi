<?php
/** Lista terena šefa ekipe. */
if (!defined('TEREN')) { exit; }

$k = trazi_prijavu();
if ($k['uloga'] === 'admin') idi('admin');

$lista = redovi(sql_lista_terena() . ' WHERE t.korisnik_id = ? ORDER BY t.vreme_polaska DESC, t.id DESC LIMIT 100',
                [$k['id']]);

$ispravke = 0;
foreach ($lista as $t) if ($t['status'] === 'ispravka') $ispravke++;

pocetak_strane('Moji tereni');
?>
<div class="sadrzaj">
<?php if ($ispravke > 0): ?>
    <?= napomena_box('<b>' . $ispravke . ' ' . ($ispravke === 1 ? 'izveštaj je vraćen' : 'izveštaja su vraćena')
        . ' na ispravku.</b> Otvori ga i pogledaj komentar kancelarije.', 'greska', 'alarm') ?>
<?php endif; ?>

<?php if (!$lista): ?>
    <?= prazno_stanje('lista', 'Nema terena', 'Tvoji tereni će se pojaviti ovde.') ?>
<?php else: ?>
    <?php foreach ($lista as $t) echo stavka_terena($t); ?>
<?php endif; ?>
</div>
<?php kraj_strane(nav_sef('moji')); ?>
