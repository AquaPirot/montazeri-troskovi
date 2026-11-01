-- ============================================================
--  PROHORECA TEREN - nadogradnja 05 (verzija 1.7)
--  Vrste troška po novom: "Hrana i piće" (piće se spaja sa hranom),
--  "Put i parking", nova vrsta "Alat" i obavezan opis za "Ostalo".
--
--  Radi i ako je 1.6 postavljena i ako nije. Pokrenuti JEDNOM,
--  PRE postavljanja PHP fajlova verzije 1.7.
--  Postojeći troškovi ostaju; samo se "Piće" prebacuje u "Hrana i piće".
-- ============================================================

SET NAMES utf8mb4;

-- 1) privremeno dozvoli sve stare i nove vrednosti
ALTER TABLE troskovi
  MODIFY vrsta ENUM('gorivo','put','smestaj','hrana','pice','materijal','alat','ostalo') NOT NULL;

-- 2) piće ide u "Hrana i piće"
UPDATE troskovi SET vrsta = 'hrana' WHERE vrsta = 'pice';

-- 3) konačan spisak vrsta
ALTER TABLE troskovi
  MODIFY vrsta ENUM('gorivo','put','smestaj','hrana','materijal','alat','ostalo') NOT NULL;

-- 4) kratak opis (obavezan za "Ostalo")
ALTER TABLE troskovi
  ADD COLUMN opis VARCHAR(200) NULL AFTER nacin_placanja;
