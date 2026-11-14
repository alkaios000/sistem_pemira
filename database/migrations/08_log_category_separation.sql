-- Migrasi 08: Pemisahan Kategori Log Pemilih vs Log Admin (Manajemen Panel)

ALTER TABLE `audit_logs`
    ADD COLUMN `log_category` ENUM('pemilih', 'admin') NOT NULL DEFAULT 'pemilih' AFTER `actor_role`,
    ADD KEY `idx_audit_log_category` (`log_category`);

UPDATE `audit_logs`
SET `log_category` = 'admin'
WHERE `actor_role` IS NOT NULL
   OR `actor_user_id` IS NOT NULL
   OR `actor_username` IS NOT NULL
   OR `device_id` LIKE 'panel_%'
   OR `kode_event` LIKE 'ADMIN_%'
   OR `kode_event` LIKE 'USER_%'
   OR `kode_event` LIKE 'BILIK_%'
   OR `kode_event` LIKE 'PORTAL_%'
   OR `kode_event` LIKE 'CHECKIN_%'
   OR `kode_event` LIKE 'LIVE_COUNT_%'
   OR `kode_event` LIKE 'QR_FORCE_%'
   OR `kode_event` LIKE 'KANDIDAT_%'
   OR `kode_event` LIKE 'MAHASISWA_%'
   OR `kode_event` LIKE 'KELAS_%'
   OR `kode_event` LIKE 'JURUSAN_%'
   OR `kode_event` LIKE 'LOG_%';
