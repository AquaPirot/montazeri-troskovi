<?php
/**
 * Jedno vozilo: zbirna potrošnja, servisna knjiga i istorija terena.
 */
if (!defined('TEREN')) { exit; }

$ja = trazi_admina();
$id = getId('id');
$v  = $id ? red('SELECT * FROM vozila WHERE id = ?', [$id]) : null;

if (!$v) {
    postavi_poruku('Vozilo nije pronađeno.', 'greska');
    idi('vozila');
}

$greske = [];

if (je_post()) {
    proveri_csrf();
    $akcija = post('akcija');

    /* --- očekivana potrošnja --- */
    if ($akcija === 'potrosnja') {
        $pot = postBroj('ocekivana_potrosnja');
        if ($pot === null || ($pot > 0 && $pot <= 100)) {
            upit('UPDATE vozila SET ocekivana_potrosnja = ? WHERE id = ?', [$pot, $v['id']]);
            postavi_poruku('Očekivana potrošnja je sačuvana.');
        } else {
            postavi_poruku('Očekivana potrošnja mora biti između 0 i 100 L/100 km.', 'greska');
        }
        idi('vozilo', ['id' => (int)$v['id']]);
    }

    /* --- uključivanje/isključivanje iz upotrebe --- */
    if ($akcija === 'stanje') {
        upit('UPDATE vozila SET aktivno = 1 - aktivno WHERE id = ?', [$v['id']]);
        postavi_poruku('Sačuvano.');
        idi('vozilo', ['id' => (int)$v['id']]);
    }

    /* --- novi unos u servisnu knjigu --- */
    if ($akcija === 'servis') {
        $datum = post('datum');
        $vrsta = post('vrsta');
        $opis  = post('opis');
        $km    = postCeo('km');
        $tros  = postBroj('trosak');
        $val   = post('valuta');
        $vazi  = post('vazi_do');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || !strtotime($datum)) $greske[] = 'Izaberi datum.';
        if (!isset(VRSTE_SERVISA[$vrsta]))   $greske[] = 'Izaberi vrstu unosa.';
        if (mb_strlen($opis) < 3)            $greske[] = 'Napiši šta je urađeno.';
        if (mb_strlen($opis) > 2000)         $greske[] = 'Opis je predugačak.';
        if ($km !== null && $km < 0)         $greske[] = 'Kilometraža ne može biti negativna.';
        if ($tros !== null && $tros < 0)     $greske[] = 'Trošak ne može biti negativan.';
        if ($tros !== null && !in_array($val, ['EUR', 'RSD'], true)) $greske[] = 'Uz trošak izaberi valutu.';
        if ($vazi !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vazi) || !strtotime($vazi)))
                                             $greske[] = 'Datum važenja registracije nije ispravan.';

        $foto = null;
        if (!$greske) {
            try {
                $foto = primi_foto('foto', 'servis');
            } catch (RuntimeException $e) {
                $greske[] = $e->getMessage();
            }
        }

        if (!$greske) {
            upit('INSERT INTO servis (vozilo_id, datum, vrsta, km, opis, trosak, valuta, vazi_do, foto, kreirao_id)
                  VALUES (?,?,?,?,?,?,?,?,?,?)', [
                $v['id'], $datum, $vrsta, $km, $opis,
                $tros, $tros !== null ? $val : null,
                ($vrsta === 'registracija' && $vazi !== '') ? $vazi : null,
                $foto, $ja['id'],
            ]);
            postavi_poruku('Unos je sačuvan u servisnu knjigu.');
            idi('vozilo', ['id' => (int)$v['id']]);
        }
    }

    /* --- brisanje unosa --- */
    if ($akcija === 'obrisi_servis') {
        $sid = postCeo('servis_id');
        $s = $sid ? red('SELECT * FROM servis WHERE id = ? AND vozilo_id = ?', [$sid, $v['id']]) : null;
        if ($s) {
            obrisi_foto($s['foto']);
            upit('DELETE FROM servis WHERE id = ? AND vozilo_id = ?', [$sid, $v['id']]);
            postavi_poruku('Unos je obrisan.');
        }
        idi('vozilo', ['id' => (int)$v['id']]);
    }
}

$st      = statistika_vozila((int)$v['id']);
$knjiga  = servis_vozila((int)$v['id']);
$tereni  = tereni_vozila((int)$v['id']);
$reg     = registracija_vozila((int)$v['id']);
$danaReg = $reg ? danaDo($reg['vazi_do']) : null;

pocetak_strane($v['naziv'], ['nazad' => 'index.php?s=vozila', 'naslov_gore' => $v['naziv']]);
?>
<div class="sadrzaj">

<?php foreach ($greske as $g) echo napomena_box(h($g), 'greska', 'alarm'); ?>

<!-- ================= OSNOVNO ================= -->
<div class="karta"><div class="karta-telo">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <span style="width:48px;height:48px;border-radius:12px;background:var(--grafit);color:var(--zlatna);display:flex;align-items:center;justify-content:center;flex:none">
            <?= ikona('kamion', 24) ?>
        </span>
        <span style="flex:1;min-width:0">
            <span style="display:block;font-size:18px;font-weight:700;letter-spacing:-.3px"><?= h($v['naziv']) ?></span>
            <span class="sitno"><?= h($v['registracija']) ?></span>
        </span>
        <?php if (!(int)$v['aktivno']): ?><span class="oznaka o-siva">Neaktivno</span><?php endif; ?>
    </div>

    <?php if ($reg): ?>
        <div class="red">
            <span class="k">Registracija važi do</span>
            <span class="v mono"><?= h(datumDan($reg['vazi_do'])) ?></span>
        </div>
    <?php endif; ?>
    <div class="red"><span class="k">Zatvorenih terena</span><span class="v mono"><?= (int)$st['broj_terena'] ?></span></div>
</div></div>

<?php if ($danaReg !== null): ?>
    <?php if ($danaReg < 0): ?>
        <?= napomena_box('<b>Registracija je istekla</b> pre ' . abs($danaReg) . ' dana ('
            . h(datumDan($reg['vazi_do'])) . ').', 'greska', 'alarm') ?>
    <?php elseif ($danaReg <= 30): ?>
        <?= napomena_box('<b>Registracija ističe za ' . $danaReg . ' dana</b> ('
            . h(datumDan($reg['vazi_do'])) . ').', 'upozorenje', 'kalendar') ?>
    <?php endif; ?>
<?php endif; ?>

<!-- ================= POTROŠNJA ================= -->
<div class="sekcija-naslov">Potrošnja kroz sve terene</div>
<div class="karta"><div class="karta-telo">
    <div class="novac-mreza" style="margin-bottom:12px">
        <div class="novac">
            <div class="val">Stvarna potrošnja</div>
            <div class="iznos"><?= $st['potrosnja'] !== null ? broj($st['potrosnja'], 1) : '—' ?></div>
            <div class="opis">L/100 km</div>
        </div>
        <div class="novac">
            <div class="val">Očekivana</div>
            <div class="iznos"><?= $st['ocek_potrosnja'] !== null ? broj($st['ocek_potrosnja'], 1) : '—' ?></div>
            <div class="opis">L/100 km</div>
        </div>
    </div>
    <div class="red"><span class="k">Ukupno pređeno</span><span class="v mono"><?= $st['km'] > 0 ? kmF($st['km']) : '—' ?></span></div>
    <div class="red"><span class="k">Ukupno sipano</span><span class="v mono"><?= broj($st['litri'], 1) ?> L</span></div>
    <div class="red"><span class="k">Ukupan trošak goriva</span><span class="v mono"><?php
        $g = [];
        if ($st['gorivo_rsd'] > 0) $g[] = novac($st['gorivo_rsd'], 'RSD');
        if ($st['gorivo_eur'] > 0) $g[] = novac($st['gorivo_eur'], 'EUR');
        echo $g ? implode('<br>', array_map('h', $g)) : '—';
    ?></span></div>
</div></div>

<?php if ($st['odstupanje'] !== null): ?>
    <?php if ($st['upozorenje']): ?>
        <?= napomena_box('<b>Prosek vozila odstupa ' . ($st['odstupanje'] > 0 ? '+' : '')
            . broj($st['odstupanje'], 1) . '% od očekivanog.</b><br>Signal za proveru – može biti i da je '
            . 'očekivana vrednost postavljena prenisko za način na koji se vozilo koristi.', 'upozorenje', 'alarm') ?>
    <?php else: ?>
        <?= napomena_box('Prosek vozila je u očekivanom opsegu (' . ($st['odstupanje'] > 0 ? '+' : '')
            . broj($st['odstupanje'], 1) . '%).', 'uspeh', 'cek') ?>
    <?php endif; ?>
<?php elseif ($st['potrosnja'] === null): ?>
    <?= napomena_box('Još nema dovoljno podataka. Prosek se računa iz zatvorenih terena sa unetim gorivom.', 'info', 'ostalo') ?>
<?php endif; ?>

<div class="karta"><div class="karta-telo">
    <form method="post">
        <?= csrf_polje() ?>
        <input type="hidden" name="akcija" value="potrosnja">
        <label class="nalepnica" for="op">Očekivana prosečna potrošnja</label>
        <div style="display:flex;gap:8px">
            <div class="unos-sa-sufiksom" style="flex:1">
                <input class="unos" id="op" name="ocekivana_potrosnja" type="number" step="0.1" min="0" max="100"
                       inputmode="decimal" placeholder="npr. 11,5"
                       value="<?= $v['ocekivana_potrosnja'] !== null ? h(rtrim(rtrim(number_format((float)$v['ocekivana_potrosnja'], 2, '.', ''), '0'), '.')) : '' ?>">
                <span class="sufiks">L/100</span>
            </div>
            <button class="dugme d-glavno d-malo" type="submit" style="width:auto;padding-left:18px;padding-right:18px">Sačuvaj</button>
        </div>
    </form>
</div></div>

<!-- ================= SERVISNA KNJIGA ================= -->
<div class="sekcija-naslov">Servisna knjiga (<?= count($knjiga) ?>)</div>

<?php if (!$knjiga): ?>
    <div class="karta"><div class="karta-telo sitno">Još nema unosa. Dodaj prvi ispod.</div></div>
<?php else: foreach ($knjiga as $s): ?>
    <div class="karta"><div class="karta-telo">
        <div style="display:flex;align-items:flex-start;gap:11px">
            <span style="width:36px;height:36px;border-radius:8px;background:var(--grafit);color:var(--zlatna);display:flex;align-items:center;justify-content:center;flex:none">
                <?= ikona(VRSTE_SERVISA[$s['vrsta']]['ik'], 18) ?>
            </span>
            <span style="flex:1;min-width:0">
                <span style="display:flex;justify-content:space-between;gap:8px;align-items:baseline">
                    <span style="font-size:15px;font-weight:700"><?= h(VRSTE_SERVISA[$s['vrsta']]['n']) ?></span>
                    <span class="sitno mono"><?= h(datumDan($s['datum'])) ?></span>
                </span>
                <span class="sitno" style="display:block">
                    <?= $s['km'] !== null ? h(broj((int)$s['km'], 0)) . ' km' : '' ?>
                    <?= ($s['km'] !== null && $s['uneo']) ? ' · ' : '' ?>
                    <?= $s['uneo'] ? 'uneo ' . h($s['uneo']) : '' ?>
                </span>
            </span>
            <form method="post" style="flex:none" data-bez-zakljucavanja>
                <?= csrf_polje() ?>
                <input type="hidden" name="akcija" value="obrisi_servis">
                <input type="hidden" name="servis_id" value="<?= (int)$s['id'] ?>">
                <button class="brisi" type="submit" data-pitaj="Obrisati ovaj unos iz servisne knjige?"
                        aria-label="Obriši unos"><?= ikona('kanta', 18) ?></button>
            </form>
        </div>

        <div style="font-size:15px;line-height:1.5;margin-top:8px"><?= nl2br(h($s['opis'])) ?></div>

        <?php if ($s['trosak'] !== null || $s['vazi_do'] || $s['foto']): ?>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;align-items:center">
                <?php if ($s['trosak'] !== null): ?>
                    <span class="cip"><?= h(novac((float)$s['trosak'], $s['valuta'] ?: 'RSD')) ?></span>
                <?php endif; ?>
                <?php if ($s['vazi_do']): ?>
                    <span class="cip">važi do <?= h(datumDan($s['vazi_do'])) ?></span>
                <?php endif; ?>
                <?php if ($s['foto']): ?>
                    <a class="cip" href="index.php?s=slika&f=<?= h(rawurlencode($s['foto'])) ?>&v=<?= (int)$v['id'] ?>"
                       target="_blank" rel="noopener">fotografija</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div></div>
<?php endforeach; endif; ?>

<div class="karta"><div class="karta-telo">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_polje() ?>
        <input type="hidden" name="akcija" value="servis">

        <div class="polje">
            <label>Šta se upisuje</label>
            <div class="plocice k3">
            <?php foreach (VRSTE_SERVISA as $kljuc => $x): ?>
                <?= plocica('vrsta', $kljuc, $x['n'], post('vrsta', 'servis') === $kljuc, $x['ik']) ?>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="dva">
            <div class="polje">
                <label for="dt">Datum</label>
                <input class="unos" id="dt" name="datum" type="date" required
                       value="<?= h(post('datum', date('Y-m-d'))) ?>">
            </div>
            <div class="polje">
                <label for="kmv">Kilometraža <span class="opc">(nije obavezno)</span></label>
                <div class="unos-sa-sufiksom">
                    <input class="unos" id="kmv" name="km" type="number" step="1" min="0"
                           inputmode="numeric" placeholder="npr. 128450" value="<?= h(post('km')) ?>">
                    <span class="sufiks">km</span>
                </div>
            </div>
        </div>

        <div class="polje">
            <label for="ops">Šta je urađeno</label>
            <textarea class="unos" id="ops" name="opis" maxlength="2000" required
                      placeholder="npr. Veliki servis: ulje, filter ulja, filter goriva, kabinski filter."><?= h(post('opis')) ?></textarea>
        </div>

        <div class="polje" data-prikazi-ako="vrsta=registracija" <?= post('vrsta') === 'registracija' ? '' : 'hidden' ?>>
            <label for="vd">Registracija važi do</label>
            <input class="unos" id="vd" name="vazi_do" type="date" value="<?= h(post('vazi_do')) ?>">
            <div class="sitno" style="margin-top:6px">Aplikacija javlja 30 dana pre isteka.</div>
        </div>

        <div class="polje">
            <label for="tr">Trošak <span class="opc">(nije obavezno)</span></label>
            <div class="dva iznos">
                <input class="unos" id="tr" name="trosak" type="number" step="0.01" min="0"
                       inputmode="decimal" placeholder="0,00" value="<?= h(post('trosak')) ?>">
                <div class="plocice k2" style="gap:6px">
                    <?= plocica('valuta', 'RSD', 'RSD', post('valuta', 'RSD') === 'RSD', '', 'zlato valuta') ?>
                    <?= plocica('valuta', 'EUR', 'EUR', post('valuta') === 'EUR', '', 'zlato valuta') ?>
                </div>
            </div>
        </div>

        <div class="polje">
            <?= foto_polje('foto', 'Dodaj fotografiju računa', 'Nije obavezno') ?>
        </div>

        <button class="dugme d-zlato" type="submit"><?= ikona('plus', 20) ?> Upiši u servisnu knjigu</button>
    </form>
</div></div>

<!-- ================= ISTORIJA TERENA ================= -->
<div class="sekcija-naslov">Istorija terena (<?= count($tereni) ?>)</div>

<?php if (!$tereni): ?>
    <div class="karta"><div class="karta-telo sitno">Ovim vozilom još nije išao nijedan teren.</div></div>
<?php else: ?>
    <div class="karta"><div class="karta-telo">
    <?php foreach ($tereni as $t):
        $odst = ($t['potrosnja'] !== null && $st['ocek_potrosnja'])
            ? round(($t['potrosnja'] - $st['ocek_potrosnja']) / $st['ocek_potrosnja'] * 100, 1) : null;
        $lose = $odst !== null && abs($odst) > $st['prag'];
    ?>
        <a class="trosak" href="index.php?s=izvestaj&id=<?= (int)$t['id'] ?>">
            <span class="ik"><?= ikona('kamion', 18) ?></span>
            <span class="sred">
                <span class="n1"><?= h($t['projekat']) ?></span>
                <span class="n2"><?= h(datumDan($t['vreme_polaska'])) ?> · <?= h($t['sef_ime']) ?><?php
                    if ($t['km'] !== null) echo ' · ' . h(broj($t['km'], 0)) . ' km';
                ?></span>
            </span>
            <span class="iz" style="<?= $lose ? 'color:var(--narandzasta)' : '' ?>">
                <?= $t['potrosnja'] !== null ? h(broj($t['potrosnja'], 1)) : '—' ?>
                <small><?= $t['potrosnja'] !== null ? 'L/100' : ($t['km_kraj'] === null ? 'u toku' : 'bez goriva') ?></small>
            </span>
        </a>
    <?php endforeach; ?>
    </div></div>
    <?php if ($st['ocek_potrosnja']): ?>
        <div class="sitno" style="margin:-4px 2px 12px">
            Narandžasto je označen teren koji odstupa više od <?= broj($st['prag'], 0) ?>% od očekivanih
            <?= broj($st['ocek_potrosnja'], 1) ?> L/100 km.
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ================= STANJE VOZILA ================= -->
<form method="post" style="margin-bottom:20px">
    <?= csrf_polje() ?>
    <input type="hidden" name="akcija" value="stanje">
    <button class="dugme d-obrub d-malo" type="submit"
            data-pitaj="<?= (int)$v['aktivno'] ? 'Sakriti vozilo iz izbora pri polasku?' : 'Vratiti vozilo u izbor?' ?>">
        <?= (int)$v['aktivno'] ? 'Isključi iz upotrebe' : 'Vrati u upotrebu' ?>
    </button>
</form>

</div>
<?php kraj_strane(nav_admin('vozila')); ?>
