<?php
/** Administratorski pregled terena po statusu. */
if (!defined('TEREN')) { exit; }

trazi_admina();

$tabovi = [
    'pregled'  => 'Čeka pregled',
    'aktivan'  => 'Aktivni',
    'ispravka' => 'Na ispravci',
    'odobren'  => 'Odobreni',
];

$tab = get('tab', 'pregled');
if (!isset($tabovi[$tab])) $tab = 'pregled';

$br = brojaci_statusa();

$lista = redovi(
    sql_lista_terena() . ' WHERE t.status = ? ORDER BY t.vreme_polaska DESC, t.id DESC LIMIT 200',
    [$tab]
);

pocetak_strane('Pregled terena');
?>
<div class="sadrzaj">

<div class="tabovi">
<?php foreach ($tabovi as $kljuc => $naziv): ?>
    <a class="tab <?= $tab === $kljuc ? 'aktivan' : '' ?>" href="index.php?s=admin&tab=<?= $kljuc ?>">
        <?= h($naziv) ?><span class="br"><?= (int)$br[$kljuc] ?></span>
    </a>
<?php endforeach; ?>
</div>

<?php if (!$lista): ?>
    <?= prazno_stanje('lista', 'Nema terena u ovoj grupi', 'Izaberi drugu karticu iznad.') ?>
<?php else: ?>
    <?php foreach ($lista as $t) echo stavka_terena($t, true); ?>
<?php endif; ?>

</div>
<?php kraj_strane(nav_admin('admin')); ?>
