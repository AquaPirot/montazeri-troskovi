/* ============================================================
   PROHORECA AG GROUP – TEREN
   Mala pomoćna skripta. Aplikacija radi i bez JavaScripta,
   ovo samo ubrzava unos i daje trenutni obračun.
   ============================================================ */
(function () {
  'use strict';

  /* ---------- 1. Izbori (radio dugmad prerušena u pločice) ---------- */
  function osveziIzbore() {
    document.querySelectorAll('[data-izbor] input[type=radio]').forEach(function (r) {
      var okvir = r.closest('[data-izbor]');
      okvir.classList.toggle('izabrana', r.checked);
    });
  }

  /* ---------- 2. Potvrde ---------- */
  function osveziPotvrde() {
    document.querySelectorAll('[data-potvrda] input[type=checkbox]').forEach(function (c) {
      c.closest('[data-potvrda]').classList.toggle('cek', c.checked);
    });
  }

  /* ---------- 3. Uslovna polja ----------
     <div data-prikazi-ako="vrsta=gorivo">  – vidljivo samo kada je
     izabrano radio dugme iz grupe "vrsta" sa vrednošću "gorivo".
     Više vrednosti: data-prikazi-ako="nacin=kartica|licni"
     Suprotno: data-sakrij-ako="..."                                   */
  function stanjeGrupe(ime) {
    var izabran = document.querySelector('input[name="' + ime + '"]:checked');
    return izabran ? izabran.value : null;
  }
  function poklapa(uslov) {
    var d = uslov.split('=');
    var vrednosti = (d[1] || '').split('|');
    return vrednosti.indexOf(stanjeGrupe(d[0])) !== -1;
  }
  function osveziUslovna() {
    document.querySelectorAll('[data-prikazi-ako]').forEach(function (e) {
      e.hidden = !poklapa(e.getAttribute('data-prikazi-ako'));
    });
    document.querySelectorAll('[data-sakrij-ako]').forEach(function (e) {
      e.hidden = poklapa(e.getAttribute('data-sakrij-ako'));
    });
  }

  /* ---------- 4. Napomena se nudi tek kada nešto nije potvrđeno ---------- */
  function osveziNapomenu() {
    var okvir = document.querySelector('[data-napomena-ako-fali]');
    if (!okvir) return;
    var imena = okvir.getAttribute('data-napomena-ako-fali').split(',');
    var fali = imena.some(function (n) {
      var c = document.querySelector('input[name="' + n.trim() + '"]');
      return c && !c.checked;
    });
    okvir.hidden = !fali;
  }

  /* ---------- 5. Fotografija: pregled pre slanja ---------- */
  document.addEventListener('change', function (e) {
    if (!e.target.matches('[data-foto] input[type=file]')) return;
    var okvir = e.target.closest('[data-foto]');
    var slika = okvir.querySelector('.pregled');
    var f = e.target.files && e.target.files[0];
    if (!f) {
      okvir.classList.remove('ima');
      slika.hidden = true;
      return;
    }
    okvir.classList.add('ima');
    okvir.querySelector('.t1').textContent = 'Fotografija dodata';
    okvir.querySelector('.t2').textContent = 'Dodirni da promeniš';
    var r = new FileReader();
    r.onload = function () { slika.src = r.result; slika.hidden = false; };
    r.readAsDataURL(f);
  });

  /* ---------- 6. Živi obračun na ekranu povratka ---------- */
  function broj(n, dec) {
    var neg = n < 0;
    n = Math.abs(n).toFixed(dec);
    var d = n.split('.');
    return (neg ? '-' : '') + d[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (d[1] ? ',' + d[1] : '');
  }
  function osveziPovratak() {
    var f = document.querySelector('[data-povratak]');
    if (!f) return;

    var kmStart = parseInt(f.getAttribute('data-km-start'), 10);
    var ocekEur = parseFloat(f.getAttribute('data-ocek-eur'));
    var ocekRsd = parseFloat(f.getAttribute('data-ocek-rsd'));

    var kmPolje = f.querySelector('[name=km_kraj]');
    var kmIzlaz = f.querySelector('[data-km-rezultat]');
    if (kmPolje && kmIzlaz) {
      var v = parseInt(kmPolje.value, 10);
      if (!v) {
        kmIzlaz.innerHTML = 'Ukupno pređeno: —';
      } else if (v < kmStart) {
        kmIzlaz.innerHTML = '<b style="color:var(--crvena)">Manje od polazne kilometraže (' + broj(kmStart, 0) + ').</b>';
      } else {
        kmIzlaz.innerHTML = 'Ukupno pređeno: <b>' + broj(v - kmStart, 0) + ' km</b>';
      }
    }

    var izlaz = f.querySelector('[data-razlika]');
    if (!izlaz) return;
    var pe = f.querySelector('[name=vraceno_eur]');
    var pr = f.querySelector('[name=vraceno_rsd]');
    var e = pe && pe.value !== '' ? parseFloat(pe.value.replace(',', '.')) : null;
    var r = pr && pr.value !== '' ? parseFloat(pr.value.replace(',', '.')) : null;

    if (e === null && r === null) { izlaz.innerHTML = ''; return; }
    var de = e === null ? 0 : e - ocekEur;
    var dr = r === null ? 0 : r - ocekRsd;

    if (Math.abs(de) < 0.01 && Math.abs(dr) < 1) {
      izlaz.innerHTML = '<div class="napomena uspeh bez-margine"><div>Iznosi se slažu sa obračunom.</div></div>';
      return;
    }
    var d = [];
    if (Math.abs(de) >= 0.01) d.push((de > 0 ? 'višak ' : 'manjak ') + broj(Math.abs(de), 2) + ' EUR');
    if (Math.abs(dr) >= 1) d.push((dr > 0 ? 'višak ' : 'manjak ') + broj(Math.abs(dr), 0) + ' RSD');
    izlaz.innerHTML = '<div class="napomena upozorenje bez-margine"><div>Razlika u odnosu na obračun: <b>' +
      d.join(', ') + '</b>. Ako je tako, dodaj kratko objašnjenje ispod.</div></div>';
  }

  /* ---------- 7. Zaštita od dvostrukog slanja ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.hasAttribute('data-bez-zakljucavanja')) return;
    var d = f.querySelector('button[type=submit], .dugme[type=submit]');
    if (!d) return;
    setTimeout(function () {
      d.disabled = true;
      d.textContent = 'Šalje se…';
    }, 0);
  });

  /* ---------- 8. Potvrda pre brisanja ---------- */
  document.addEventListener('click', function (e) {
    var d = e.target.closest('[data-pitaj]');
    if (d && !window.confirm(d.getAttribute('data-pitaj'))) e.preventDefault();
  });

  /* ---------- pokretanje ---------- */
  function sve() {
    osveziIzbore();
    osveziPotvrde();
    osveziUslovna();
    osveziNapomenu();
    osveziPovratak();
  }
  document.addEventListener('change', sve);
  document.addEventListener('input', osveziPovratak);
  sve();
})();
