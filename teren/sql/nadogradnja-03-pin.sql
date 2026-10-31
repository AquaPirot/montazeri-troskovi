-- ============================================================
--  PROHORECA TEREN - nadogradnja 03 (verzija 1.5)
--  Prijava PIN-om od 4 broja.
--
--  Pokrenuti JEDNOM nad postojećom bazom, PRE postavljanja PHP
--  fajlova verzije 1.5. Ne menja postojeće korisnike, lozinke
--  ni podatke – samo dodaje mesto za PIN i dve male tabele.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE korisnici
  ADD COLUMN pin_hash CHAR(64) NULL AFTER lozinka_hash,
  ADD UNIQUE KEY uq_pin_hash (pin_hash);

CREATE TABLE IF NOT EXISTS podesavanja (
  kljuc     VARCHAR(50)  NOT NULL,
  vrednost  VARCHAR(255) NOT NULL,
  PRIMARY KEY (kljuc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO podesavanja (kljuc, vrednost)
SELECT 'pin_tajna', SHA2(CONCAT(UUID(), RAND(), NOW(6)), 256) FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM podesavanja WHERE kljuc = 'pin_tajna');

CREATE TABLE IF NOT EXISTS pokusaji_prijave (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip     VARCHAR(45)  NOT NULL,
  vreme  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ip_vreme (ip, vreme)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
