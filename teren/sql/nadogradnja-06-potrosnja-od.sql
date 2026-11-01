-- ============================================================
--  PROHORECA TEREN - nadogradnja 06 (verzija 1.8)
--  Prosek potrošnje vozila računa se od zadatog datuma.
--  Ništa se ne briše: stari tereni ostaju u istoriji.
--
--  Pokrenuti JEDNOM. Druga naredba postavlja početak za SVA vozila
--  na 2. novembar 2026.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE vozila
  ADD COLUMN potrosnja_od DATE NULL AFTER ocekivana_potrosnja;

UPDATE vozila SET potrosnja_od = '2026-11-02';
