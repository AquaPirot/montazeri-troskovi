-- PROHORECA TEREN - nadogradnja 02
-- Doplate depozita i evidencija ko je uneo trošak/gorivo.
-- Pokrenuti JEDNOM nad postojećom bazom pre postavljanja PHP fajlova verzije 1.4.

SET NAMES utf8mb4;

ALTER TABLE troskovi
  ADD COLUMN uneo_id INT UNSIGNED NULL AFTER teren_id,
  ADD COLUMN izvor ENUM('radnik','admin') NOT NULL DEFAULT 'radnik' AFTER uneo_id,
  ADD KEY ix_trosak_uneo (uneo_id),
  ADD CONSTRAINT fk_trosak_uneo FOREIGN KEY (uneo_id) REFERENCES korisnici (id);

CREATE TABLE doplate (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teren_id    INT UNSIGNED NOT NULL,
  iznos       DECIMAL(12,2) NOT NULL,
  valuta      ENUM('EUR','RSD') NOT NULL,
  predao      VARCHAR(100)  NOT NULL,
  napomena    VARCHAR(500)      NULL,
  uneo_id     INT UNSIGNED      NULL,
  kreirana    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_doplata_teren (teren_id, kreirana),
  KEY ix_doplata_uneo (uneo_id),
  CONSTRAINT fk_doplata_teren FOREIGN KEY (teren_id) REFERENCES tereni (id) ON DELETE CASCADE,
  CONSTRAINT fk_doplata_uneo  FOREIGN KEY (uneo_id)  REFERENCES korisnici (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

