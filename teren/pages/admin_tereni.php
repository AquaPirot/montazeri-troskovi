<?php
/** Administratorski pregled: novac kod ekipa, brojači po statusu i lista terena. */
if (!defined('TEREN')) { exit; }

trazi_admina();

$kartice = [
    'pregled'  => ['Čeka pregled', 'k-narandzasta', 'izveštaja za odobrenje'],
    'aktivan'  => ['Na terenu',    'k-plava',       'ekipa trenutno'],
    'ispravka' => ['Na ispravci',  'k-crvena',      'vraćeno šefovima'],
    'odobren'  => ['Odobreni',     'k-zelena',      'zatvorenih terena'],
];

$tab = get('tab', 'pregled');
if (!isset($kartice[$tab])) $tab = 'pregled';

$br = brojaci_statusa();

/* Novac koji je trenutno van kancelarije – EUR i RSD odvojeno. */
$kodEkipa = ['eur' => 0.0, 'rsd' => 0.0];
$dugRadnicima = ['eur' => 0.0, 'rsd' => 0.0];
foreach (redovi('SELECT id FROM tereni WHERE status IN ("aktivan","pregled","ispravka")') as $r) {
    $t = ucitaj_teren((int)$r['id']);
    if (!$t) continue;
    $o = obracun($t);
    if ($t['status'] === 'aktivan') {
        $kodEkipa['eur'] += max(0, $o['ocek_eur']);
        $kodEkipa['rsd'] += max(0, $o['ocek_rsd']);
    }
    $dugRadnicima['eur'] += $o['lic_eur'];
    $dugRadnicima['rsd'] += $o['lic_rsd'];
}

$lista = redovi(
    sql_lista_terena() . ' WHERE t.status = ? ORDER BY t.vreme_polaska DESC, t.id DESC LIMIT 200',
    [$tab]
);

pocetak_strane('Tereni', ['sirina' => 'siroka']);
?>
<div class="sadrzaj">

<section class="hero" aria-label="Novac na terenu">
    <div class="hero-iznosi">
        <div>
            <div class="h-lab">Gotovina kod ekipa na terenu · EUR</div>
            <div class="h-iznos"><?= broj($kodEkipa['eur'], 2) ?><small>€</small></div>
        </div>
        <div>
            <div class="h-lab">Gotovina kod ekipa na terenu · RSD</div>
            <div class="h-iznos"><?= broj($kodEkipa['rsd'], 0) ?><small>RSD</small></div>
        </div>
    </div>
    <div class="h-crta"></div>
    <div class="h-red">
        <div>
            <div class="h-lab">Firma duguje radnicima · lični novac</div>
            <div class="h-iznos"><?= broj($dugRadnicima['eur'], 2) ?><small>€</small>
                &nbsp;·&nbsp; <?= broj($dugRadnicima['rsd'], 0) ?><small>RSD</small></div>
            <div class="h-sitno">sa terena koji još nisu odobreni</div>
        </div>
    </div>
</section>

<div class="kpi-red">
<?php foreach ($kartice as $kljuc => [$naziv, $boja, $pod]): ?>
    <a class="kpi <?= $boja ?> <?= $tab === $kljuc ? 'aktivan' : '' ?>" href="index.php?s=admin&amp;tab=<?= $kljuc ?>"
       <?= $tab === $kljuc ? 'aria-current="page"' : '' ?>>
        <div class="k-lab"><?= h($naziv) ?></div>
        <div class="k-br"><?= (int)$br[$kljuc] ?></div>
        <div class="k-pod"><?= h($pod) ?></div>
    </a>
<?php endforeach; ?>
</div>

<div class="sekcija-naslov"><?= h($kartice[$tab][0]) ?> (<?= count($lista) ?>)</div>

<?php if (!$lista): ?>
    <?= prazno_stanje('lista', 'Nema terena u ovoj grupi', 'Izaberi drugu karticu iznad.') ?>
<?php else: ?>
    <div class="mreza-stavki">
    <?php foreach ($lista as $t) echo stavka_terena($t, true); ?>
    </div>
<?php endif; ?>

</div>
<?php kraj_strane(nav_admin('admin')); ?>
