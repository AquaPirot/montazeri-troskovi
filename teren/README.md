# Prohoreca AG group – Teren

Evidencija i obračun terenskog rada monterskih ekipa.
PHP 8.1+ i MySQL, napravljeno za cPanel hosting i za rad preko telefona.

---

## Šta sadrži

| Fajl / folder        | Šta je                                                              |
|----------------------|---------------------------------------------------------------------|
| `prototip.html`      | Klikabilan vizuelni prototip sa izmišljenim podacima. Otvara se direktno na telefonu, bez servera. |
| `index.php`          | Ulazna tačka aplikacije                                              |
| `inc/`               | Baza, prijava, obračun, izgled                                       |
| `pages/`             | Ekrani                                                               |
| `assets/`            | CSS i JavaScript                                                     |
| `sql/schema.sql`     | MySQL šema                                                           |
| `sql/nadogradnja-*`  | Dopune šeme za baze napravljene ranijom verzijom                      |
| `uploads/`           | Fotografije (nisu javno dostupne)                                    |
| `config.primer.php`  | Predložak podešavanja                                                |

---

## Instalacija na cPanel – pet koraka

**1. Napravi bazu**

cPanel → *MySQL Databases*: napravi bazu i korisnika, i dodaj korisnika bazi
sa svim privilegijama. Zapiši naziv baze, korisnika i lozinku.

**2. Uvezi šemu**

cPanel → *phpMyAdmin* → izaberi bazu → kartica **Import** → izaberi
`sql/schema.sql` → **Go**.

Ako je baza već napravljena ranijom verzijom, uvezi i fajlove
`sql/nadogradnja-*.sql` istim putem. Nova instalacija ih ne pokreće.

Time se pravi i prvi administratorski nalog:

    korisničko ime: admin
    lozinka:        admin123

**3. Prekopiraj fajlove**

Sadržaj foldera `teren/` prebaci u folder subdomena
(npr. `/home/korisnik/teren.prohoreca.rs/`).

**4. Podesi vezu sa bazom**

Kopiraj `config.primer.php` u `config.php` i upiši podatke iz prvog koraka:

```php
'db_host'  => 'localhost',
'db_naziv' => 'korisnik_teren',
'db_user'  => 'korisnik_teren',
'db_pass'  => 'lozinka_baze',
```

**5. Prava pristupa**

Folder `uploads/` mora da bude upisiv (`755`, na nekim hostinzima `775`).

Otvori subdomen u pregledaču, prijavi se kao `admin` i **odmah promeni lozinku**
(dodirni krug sa inicijalima gore desno → *Promena lozinke*).

---

## Prvo podešavanje

1. **Vozila** → dodaj vozila i, po želji, očekivanu prosečnu potrošnju (L/100 km).
2. **Korisnici** → dodaj šefove ekipa (uloga *Šef ekipe*) sa početnim lozinkama.

Šefovi ekipa se prijavljuju na istoj adresi i odmah vide svoja četiri dugmeta.

Aplikacija radi kao obična web stranica u pregledaču telefona. Preporuka:
u Chrome-u na Androidu otvoriti adresu → meni ⋮ → *Dodaj na početni ekran*.

---

## Kako radi

### Šef monterske ekipe

Početni ekran ima samo četiri funkcije:

1. **Polazak na teren** – projekat, vozilo, depozit u EUR i RSD, početna
   kilometraža, fotografija instrument table i tri kratke potvrde
   (vozilo, gorivo, tovarni prostor). Ako nešto nije u redu, polje za kratak opis.
2. **Dodaj trošak** – iznos, valuta, vrsta, način plaćanja, fotografija računa.
   Polja za litre i kilometražu pojavljuju se **samo** kada je izabrano gorivo.
   Polje za slip pojavljuje se **samo** kod kartičnog plaćanja.
3. **Prijavi sa terena** – jedna od pet opcija, kratko objašnjenje i opciona
   fotografija. Bez prioriteta, rokova i zaduženja.
4. **Povratak i izveštaj** – završna kilometraža, fotografija instrument table
   i vraćen novac. Sistem unapred prikazuje koliko treba vratiti i odmah javlja
   ako se uneti iznos ne slaže. Zatim četiri potvrde i slanje izveštaja.

Šef može da ima **samo jedan otvoren teren** u isto vreme.

### Vozila

Ekran **Vozila** pokazuje ceo park na jednom mestu: za svako vozilo stvarnu
prosečnu potrošnju, očekivanu, broj terena i pređene kilometre. Ako registracija
ističe u narednih 30 dana ili je već istekla, vozilo dobija oznaku.

Dodirom na vozilo otvara se njegov karton:

- **Potrošnja kroz sve terene** – stvarna i očekivana, ukupno pređeno, ukupno
  sipano i ukupan trošak goriva. Računa se samo iz zatvorenih terena, da bi
  kilometri i litri pripadali istom periodu.
- **Servisna knjiga** – ručni unosi: servis, registracija, popravka, gume, ostalo.
  Za svaki unos: datum, kratak opis šta je urađeno, kilometraža, trošak i
  fotografija računa. Kod registracije se upisuje i do kada važi, pa aplikacija
  javlja 30 dana pre isteka.
- **Istorija terena** – svaki teren sa potrošnjom po tom terenu. Narandžasto je
  označen teren koji odstupa više od praga, pa se odmah vidi da li potrošnja
  raste stalno ili je jedan teren izuzetak.

### Administrator

Pregled terena po statusu: **Čeka pregled**, **Aktivni**, **Na ispravci**, **Odobreni**.

U izveštaju vidi sve na jednom mestu:

- šefa ekipe, vozilo, projekat i datume
- primljen, potrošen i vraćen novac – **odvojeno u EUR i RSD**
- troškove plaćene karticom (ne diraju depozit)
- troškove plaćene ličnim novcem (dug firme prema zaposlenom)
- sve račune, slipove i fotografije
- početnu i završnu kilometražu i ukupan broj pređenih kilometara
- ukupno sipanih litara, ukupan trošak goriva i procenjenu prosečnu potrošnju
- prijavljene lomove, potrebe i nezavršene radove
- potvrde koje je šef ekipe dao na polasku i povratku

Zatim može da **odobri** izveštaj ili da ga **vrati na ispravku uz komentar**.
Šef tada vidi komentar, dopuni trošak ili prijavu, ispravi podatke i pošalje ponovo.

---

## Novac

EUR i RSD se vode potpuno odvojeno i **nikada se ne preračunavaju**.

| Način plaćanja       | Efekat                                            |
|----------------------|---------------------------------------------------|
| Gotovina iz depozita | umanjuje iznos koji šef vraća firmi                |
| Službena kartica     | **ne dira** gotovinski depozit                     |
| Lični novac          | evidentira se kao **dug firme prema zaposlenom**   |

Očekivani povraćaj = primljeni depozit − gotovinski troškovi, posebno po valuti.
Ako se uneti iznos razlikuje, prikazuje se višak ili manjak — i šefu pri unosu i
administratoru u izveštaju.

## Potrošnja goriva

    prosečna potrošnja = ukupno sipanih litara ÷ pređenih kilometara × 100

Ako odstupanje od očekivane potrošnje vozila pređe **15%**, izveštaj dobija
narandžasto upozorenje. To je **signal za proveru, a ne dokaz o nepravilnosti** —
uzrok može biti teret, teren, gužva, način vožnje ili greška u unosu.

Isti obračun postoji na dva nivoa: **po terenu** (u izveštaju) i **zbirno po
vozilu** (u kartonu vozila). Zbirni prosek je pouzdaniji, jer se greška u jednom
punjenju rezervoara razlaže na više terena.

Prag se menja u `config.php` (`prag_potrosnje`).

---

## Fotografije

- Snimaju se u `uploads/`, koji je zatvoren za direktan pristup preko interneta.
- Prikazuju se isključivo kroz aplikaciju, tek nakon provere da korisnik sme da
  ih vidi (šef vidi samo svoje terene, administrator sve).
- Ako je na serveru uključena GD ekstenzija, slike se automatski smanjuju na
  1600 px duže strane i okreću prema EXIF podatku telefona.
- Dozvoljeni formati: JPG, PNG, WEBP. Najveća veličina: 12 MB (`config.php`).

---

## Sigurnost

- Lozinke se čuvaju kao `password_hash` (bcrypt).
- Svi upiti idu preko pripremljenih izraza (nema SQL injekcije).
- Svaka izmena podataka traži CSRF token.
- Sesijski kolačić je `HttpOnly`, `SameSite=Lax` i `Secure` kada je sajt na HTTPS.
- `config.php`, `inc/`, `pages/`, `sql/` i `uploads/` zatvoreni su `.htaccess` fajlovima.

Preporuka: uključi besplatan SSL (cPanel → *SSL/TLS Status* → *Run AutoSSL*)
i forsiraj HTTPS.

---

## Zahtevi

- PHP 8.1 ili noviji, sa ekstenzijama `pdo_mysql` i (poželjno) `gd` i `exif`
- MySQL 5.7+ ili MariaDB 10.3+
- Apache sa `.htaccess` (standardno na cPanel-u)

---

## Prototip

`prototip.html` je samostalan fajl sa ugrađenim CSS-om i JavaScriptom i
izmišljenim podacima. Prebaci ga na telefon i otvori — služi za pregled izgleda
i toka rada. Nema veze sa bazom i ništa ne pamti.
