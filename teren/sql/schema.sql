-- ============================================================
--  PROHORECA AG GROUP – TEREN
--  MySQL šema (MySQL 5.7+ / MariaDB 10.3+)
--
--  Uvoz preko cPanel > phpMyAdmin:
--    1. Napravi bazu i korisnika baze u cPanel > MySQL Databases
--    2. Otvori phpMyAdmin, izaberi bazu, kartica "Import"
--    3. Izaberi ovaj fajl i klikni "Go"
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Korisnici: šefovi ekipa i administratori
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS korisnici (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ime             VARCHAR(100)  NOT NULL,
  korisnicko_ime  VARCHAR(50)   NOT NULL,
  lozinka_hash    VARCHAR(255)  NOT NULL,
  telefon         VARCHAR(30)       NULL,
  uloga           ENUM('sef','admin') NOT NULL DEFAULT 'sef',
  aktivan         TINYINT(1)    NOT NULL DEFAULT 1,
  kreiran         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_korisnicko_ime (korisnicko_ime),
  KEY ix_uloga (uloga, aktivan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Vozila
-- ocekivana_potrosnja je orijentir za proveru (L/100 km)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vozila (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  naziv                VARCHAR(100) NOT NULL,
  registracija         VARCHAR(30)  NOT NULL,
  ocekivana_potrosnja  DECIMAL(5,2)     NULL,
  aktivno              TINYINT(1)   NOT NULL DEFAULT 1,
  kreirano             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_aktivno (aktivno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tereni (jedan izlazak ekipe od polaska do povratka)
--
-- status:
--   aktivan   – ekipa je na terenu
--   pregled   – izveštaj poslat, čeka administratora
--   ispravka  – administrator vratio uz komentar
--   odobren   – zatvoreno
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tereni (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  korisnik_id        INT UNSIGNED NOT NULL,
  vozilo_id          INT UNSIGNED NOT NULL,
  projekat           VARCHAR(200) NOT NULL,
  status             ENUM('aktivan','pregled','ispravka','odobren') NOT NULL DEFAULT 'aktivan',

  -- polazak
  depozit_eur        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  depozit_rsd        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  km_start           INT UNSIGNED  NOT NULL,
  foto_km_start      VARCHAR(150)      NULL,
  p_vozilo           TINYINT(1)    NOT NULL DEFAULT 0,
  p_gorivo           TINYINT(1)    NOT NULL DEFAULT 0,
  p_teret            TINYINT(1)    NOT NULL DEFAULT 0,
  napomena_polazak   TEXT              NULL,
  vreme_polaska      DATETIME      NOT NULL,

  -- povratak
  km_kraj            INT UNSIGNED      NULL,
  foto_km_kraj       VARCHAR(150)      NULL,
  vraceno_eur        DECIMAL(10,2)     NULL,
  vraceno_rsd        DECIMAL(12,2)     NULL,
  k_vozilo           TINYINT(1)    NOT NULL DEFAULT 0,
  k_kabina           TINYINT(1)    NOT NULL DEFAULT 0,
  k_alat             TINYINT(1)    NOT NULL DEFAULT 0,
  k_racuni           TINYINT(1)    NOT NULL DEFAULT 0,
  napomena_povratak  TEXT              NULL,
  vreme_povratka     DATETIME          NULL,

  -- administracija
  komentar_admina    TEXT              NULL,
  odobrio_id         INT UNSIGNED      NULL,
  vreme_odobrenja    DATETIME          NULL,
  kreiran            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_status (status, vreme_polaska),
  KEY ix_korisnik (korisnik_id, status),
  KEY ix_vozilo (vozilo_id),
  CONSTRAINT fk_teren_korisnik FOREIGN KEY (korisnik_id) REFERENCES korisnici (id),
  CONSTRAINT fk_teren_vozilo   FOREIGN KEY (vozilo_id)   REFERENCES vozila (id),
  CONSTRAINT fk_teren_odobrio  FOREIGN KEY (odobrio_id)  REFERENCES korisnici (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Troškovi
--
-- EUR i RSD se čuvaju odvojeno i nikada se ne preračunavaju.
-- nacin_placanja:
--   gotovina – skida se sa gotovinskog depozita
--   kartica  – NE skida se sa depozita
--   licni    – dug firme prema zaposlenom
-- litri i km_sipanja se popunjavaju samo kada je vrsta = gorivo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS troskovi (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teren_id       INT UNSIGNED NOT NULL,
  iznos          DECIMAL(12,2) NOT NULL,
  valuta         ENUM('EUR','RSD') NOT NULL,
  vrsta          ENUM('gorivo','put','smestaj','hrana','materijal','ostalo') NOT NULL,
  nacin_placanja ENUM('gotovina','kartica','licni') NOT NULL,
  litri          DECIMAL(7,2)     NULL,
  km_sipanja     INT UNSIGNED     NULL,
  foto_racun     VARCHAR(150)     NULL,
  foto_slip      VARCHAR(150)     NULL,
  kreiran        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_teren (teren_id, kreiran),
  CONSTRAINT fk_trosak_teren FOREIGN KEY (teren_id) REFERENCES tereni (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Prijave sa terena (lom, potreban materijal, nezavršeno, vozilo, napomena)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prijave (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teren_id  INT UNSIGNED NOT NULL,
  tip       ENUM('lom','materijal','nezavrseno','vozilo','ostalo') NOT NULL,
  opis      TEXT         NOT NULL,
  foto      VARCHAR(150)     NULL,
  kreirana  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_teren (teren_id, kreirana),
  CONSTRAINT fk_prijava_teren FOREIGN KEY (teren_id) REFERENCES tereni (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  POČETNI PODACI
--
--  Prvi administrator:  korisničko ime  admin
--                       lozinka         admin123
--
--  ODMAH POSLE PRVE PRIJAVE PROMENI OVU LOZINKU
--  (Korisnici > Kancelarija > Promeni lozinku)
-- ============================================================
INSERT INTO korisnici (ime, korisnicko_ime, lozinka_hash, uloga, aktivan)
SELECT 'Kancelarija', 'admin',
       '$2y$12$bpU7SOmgp1uWJMgmbC3uF.BhhGaNYruHWfJIH8qQpuPRRGDY4kHZG',
       'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM korisnici WHERE korisnicko_ime = 'admin');
