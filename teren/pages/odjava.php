<?php
/** Odjava. */
if (!defined('TEREN')) { exit; }

if (je_post()) {
    proveri_csrf();
    odjavi();
    pokreni_sesiju();
    postavi_poruku('Odjavljen si.', 'info');
}
idi('prijava');
