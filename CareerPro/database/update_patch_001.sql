-- ============================================================================
-- CareerPro Suite — Database Patch 001
-- Apply AFTER careerpro_db.sql if already imported.
-- Run in phpMyAdmin SQL tab or: mysql -u root careerpro_db < update_patch_001.sql
-- ============================================================================

USE `careerpro_db`;

-- ----------------------------------------------------------------
-- 1. Set the Gemini API key (use valid AIzaSy... key or leave blank
--    to rely on Pollinations AI fallback)
-- ----------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`)
VALUES ('gemini_api_key', '')
ON DUPLICATE KEY UPDATE `setting_value` = IF(`setting_value` = '', '', `setting_value`);

-- ----------------------------------------------------------------
-- 2. Ensure all four required settings rows exist
-- ----------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
    ('platform_name',    'CareerPro Suite'),
    ('support_email',    'support@careerpro.com'),
    ('maintenance_mode', 'false')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ----------------------------------------------------------------
-- 3. Performance index — speed up the daily duplicate check in
--    matcher-api.php (DATE(applied_at) scan)
-- ----------------------------------------------------------------
ALTER TABLE `applications`
    ADD INDEX IF NOT EXISTS `idx_applications_user_job_date`
    (`user_id`, `job_id`, `applied_at`);

-- ----------------------------------------------------------------
-- 4. Performance index — speed up the system_settings lookup
--    used by chat-handler and builder-api on every request
-- ----------------------------------------------------------------
ALTER TABLE `system_settings`
    ADD INDEX IF NOT EXISTS `idx_settings_key` (`setting_key`);

-- ----------------------------------------------------------------
-- Verify
-- ----------------------------------------------------------------
SELECT setting_key, CONCAT(LEFT(IFNULL(setting_value,'<empty>'), 12), '…') AS preview
FROM system_settings ORDER BY setting_key;
