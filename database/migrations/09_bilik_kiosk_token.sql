-- Migrasi 09: Kunci Akses Kiosk Independen per Bilik (Tanpa Sesi Login Admin)

ALTER TABLE `bilik_suaras`
    ADD COLUMN `kiosk_token` VARCHAR(128) DEFAULT NULL AFTER `previous_token`,
    ADD KEY `idx_bilik_kiosk_token` (`kiosk_token`);

UPDATE `bilik_suaras`
SET `kiosk_token` = LOWER(SHA2(CONCAT(UUID(), RAND(), id, current_token), 256))
WHERE `kiosk_token` IS NULL OR `kiosk_token` = '';
