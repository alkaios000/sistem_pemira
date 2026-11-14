-- Migrasi 07: Pengerasan Keamanan, RBAC 3 Peran (superadmin, kprm, banwasra),
-- Audit Aktor, Rate Limiting Database, dan Kotak Suara Anonim (Secret Ballot Trail)

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('admin', 'panitia', 'superadmin', 'kprm', 'banwasra') NOT NULL DEFAULT 'kprm',
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
    ADD COLUMN `last_login_at` DATETIME DEFAULT NULL AFTER `is_active`;

UPDATE `users` SET `role` = 'superadmin' WHERE `role` = 'admin';
UPDATE `users` SET `role` = 'kprm' WHERE `role` = 'panitia';

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('superadmin', 'kprm', 'banwasra') NOT NULL DEFAULT 'kprm';

ALTER TABLE `audit_logs`
    ADD COLUMN `actor_user_id` INT UNSIGNED DEFAULT NULL AFTER `bilik_id`,
    ADD COLUMN `actor_username` VARCHAR(60) DEFAULT NULL AFTER `actor_user_id`,
    ADD COLUMN `actor_role` ENUM('superadmin', 'kprm', 'banwasra') DEFAULT NULL AFTER `actor_username`,
    ADD KEY `idx_audit_actor_role` (`actor_role`),
    ADD KEY `idx_audit_actor_user` (`actor_user_id`);

CREATE TABLE IF NOT EXISTS `security_rate_limits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(40) NOT NULL,
    `identifier` VARCHAR(191) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` DATETIME NOT NULL,
    `locked_until` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_limit_scope_ident` (`scope`, `identifier`),
    KEY `idx_rate_limit_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ballot_boxes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ballot_hash` CHAR(64) NOT NULL,
    `bilik_id` INT UNSIGNED DEFAULT NULL,
    `kandidat_bem_id` INT UNSIGNED NOT NULL,
    `kandidat_bpm_id` INT UNSIGNED NOT NULL,
    `cast_bucket` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ballot_hash` (`ballot_hash`),
    KEY `idx_ballot_bem` (`kandidat_bem_id`),
    KEY `idx_ballot_bpm` (`kandidat_bpm_id`),
    KEY `idx_ballot_bucket` (`cast_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pengaturan_sistem` (`kunci`, `nilai`, `keterangan`)
VALUES ('live_count_publik', 'BUKA', 'Status tampilan Live Count publik (BUKA / TUTUP)');
