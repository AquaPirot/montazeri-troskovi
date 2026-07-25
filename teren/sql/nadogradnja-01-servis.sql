-- ============================================================
--  NADOGRADNJA 01 – servisna knjiga vozila
--
--  Pokreni SAMO ako je baza već napravljena starijom verzijom
--  schema.sql (dakle nema tabelu `servis`).
--  cPanel > phpMyAdmin > izaberi bazu > Import > ovaj fajl.
--
--  Nova instalacija ovo NE pokreće – schema.sql već sadrži tabelu.
-- ============================================================

CREATE TABLE IF NOT EXISTS servis (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vozilo_id   INT UNSIGNED NOT NULL,
  datum       DATE         NOT NULL,
  vrsta       ENUM('servis','registracija','popravka','gume','ostalo') NOT NULL DEFAULT 'servis',
  km          INT UNSIGNED     NULL,
  opis        TEXT         NOT NULL,
  trosak      DECIMAL(12,2)    NULL,
  valuta      ENUM('EUR','RSD') NULL,
  vazi_do     DATE             NULL,
  foto        VARCHAR(150)     NULL,
  kreirao_id  INT UNSIGNED     NULL,
  kreiran     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_vozilo (vozilo_id, datum),
  KEY ix_registracija (vozilo_id, vrsta, vazi_do),
  CONSTRAINT fk_servis_vozilo  FOREIGN KEY (vozilo_id)  REFERENCES vozila (id) ON DELETE CASCADE,
  CONSTRAINT fk_servis_kreirao FOREIGN KEY (kreirao_id) REFERENCES korisnici (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
