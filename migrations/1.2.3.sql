SET foreign_key_checks = 0;

ALTER TABLE lite_arac_turleri 
  ADD COLUMN IF NOT EXISTS oncelik INT NOT NULL DEFAULT 1 AFTER tur_adi;

SET foreign_key_checks = 1;