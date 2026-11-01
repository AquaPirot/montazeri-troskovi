-- ============================================================
--  PROHORECA TEREN - nadogradnja 04 (verzija 1.6)
--  Nove vrste troška: Piće i Alat.
--
--  Pokrenuti JEDNOM nad postojećom bazom, PRE postavljanja PHP
--  fajlova verzije 1.6. Postojeći troškovi ostaju kakvi jesu.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE troskovi
  MODIFY vrsta ENUM('gorivo','put','smestaj','hrana','pice','materijal','alat','ostalo') NOT NULL;
