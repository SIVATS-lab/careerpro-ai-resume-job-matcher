-- ============================================================================
-- CareerPro Suite - Complete Database Schema
-- Database: careerpro_db
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- 
-- Tables:
--   1. admins           - Administrator accounts
--   2. users            - Student accounts
--   3. resumes          - JSON resume data per student
--   4. jobs             - Job postings managed by admin
--   5. applications     - ATS scan logs / job applications
--   6. system_settings  - Platform-wide configuration & API keys
--
-- Run this file in phpMyAdmin or via:
--   mysql -u root -p < careerpro_db.sql
-- ============================================================================

-- ------------------------------------------------
-- Create & select the database
-- ------------------------------------------------
CREATE DATABASE IF NOT EXISTS `careerpro_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `careerpro_db`;

-- ============================================================================
-- TABLE 1: admins
-- Stores admin portal login credentials.
-- The application checks $_SESSION['admin_id'] for admin access.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)    NOT NULL,
    `email`         VARCHAR(180)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,          -- bcrypt hash (cost 12)
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLE 2: users
-- Stores student accounts.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)    NOT NULL,
    `email`         VARCHAR(180)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,          -- bcrypt hash (cost 12)
    `phone`         VARCHAR(20)     DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1, -- 1=active, 0=locked
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLE 3: resumes
-- One resume row per user. Stores the complete resume as a JSON blob.
-- The builder-api.php upserts this row on every auto-save.
--
-- JSON structure example (stored in resume_data):
-- {
--   "summary":    "Full-stack developer...",
--   "skills":     ["PHP", "JavaScript", "MySQL"],
--   "experience": [{"title":"Dev","company":"X","from":"2022","to":"2023","desc":"..."}],
--   "education":  [{"degree":"BCA","school":"PCTE","year":"2024"}],
--   "projects":   [{"name":"P1","tech":"React,Node","desc":"..."}],
--   "certifications": ["AWS CCP"],
--   "links":      {"linkedin":"https://...","github":"https://..."}
-- }
-- ============================================================================
CREATE TABLE IF NOT EXISTS `resumes` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `resume_data`  MEDIUMTEXT   DEFAULT NULL,           -- full JSON payload
    `last_updated` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resumes_user` (`user_id`),           -- one resume per user
    CONSTRAINT `fk_resumes_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLE 4: jobs
-- Job postings created by admins.
--
-- req_skills stores a JSON array of required skill strings, e.g.:
--   ["PHP", "MySQL", "JavaScript", "React"]
-- The matcher-api.php parses this array for ATS scoring.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `jobs` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(160)    NOT NULL,
    `company`     VARCHAR(120)    NOT NULL,
    `location`    VARCHAR(120)    NOT NULL DEFAULT 'Remote',
    `job_type`    VARCHAR(60)     NOT NULL DEFAULT 'Full-time',  -- Full-time, Part-time, Internship, Contract
    `salary`      VARCHAR(80)     DEFAULT NULL,                  -- e.g. "₹4–6 LPA" (display string)
    `description` TEXT            NOT NULL,                      -- full JD shown to students
    `req_skills`  TEXT            DEFAULT NULL,                  -- JSON array: ["PHP","MySQL",...]
    `logo`        VARCHAR(10)     DEFAULT NULL,                  -- single emoji or 1–2 char abbreviation
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,            -- 1=live, 0=archived
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_jobs_is_active`  (`is_active`),
    KEY `idx_jobs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLE 5: applications
-- Records every ATS scan a student runs against a job.
-- One row per (user, job) per day — the matcher-api.php updates the score
-- if the student re-scans the same job on the same day.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `applications` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL,
    `job_id`     INT UNSIGNED    NOT NULL,
    `ats_score`  TINYINT UNSIGNED NOT NULL DEFAULT 0,            -- 0–100
    `applied_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_applications_user`       (`user_id`),
    KEY `idx_applications_job`        (`job_id`),
    KEY `idx_applications_applied_at` (`applied_at`),
    KEY `idx_applications_score`      (`ats_score`),
    CONSTRAINT `fk_applications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_applications_job`
        FOREIGN KEY (`job_id`)  REFERENCES `jobs`  (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLE 6: system_settings
-- Key-value store for platform-wide configuration.
-- admin/settings.php reads and writes these rows.
--
-- Expected keys:
--   platform_name      CareerPro Suite
--   support_email      support@careerpro.com
--   maintenance_mode   false | true
--   gemini_api_key     AIza... (set via admin panel, never hard-coded)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         DEFAULT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- SEED DATA
-- ============================================================================

-- ------------------------------------------------
-- Default system settings
-- (gemini_api_key is intentionally blank — set it via Admin > Platform Config)
-- ------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
    ('platform_name',     'CareerPro Suite'),
    ('support_email',     'support@careerpro.com'),
    ('maintenance_mode',  'false'),
    ('gemini_api_key',    '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);


-- ------------------------------------------------
-- Default admin account
-- Email   : admin@careerpro.com
-- Password: Admin@1234
-- Hash    : bcrypt cost-12
-- ------------------------------------------------
INSERT INTO `admins` (`name`, `email`, `password_hash`) VALUES
(
    'CareerPro Administrator',
    'admin@careerpro.com',
    '$2y$12$hY5DiWMvUuHGtUS.RlfRqOvTslCSOAt32rPnbfN6Tss07g2MZEPE.'
)
ON DUPLICATE KEY UPDATE
    `name`          = VALUES(`name`),
    `password_hash` = VALUES(`password_hash`);


-- ------------------------------------------------
-- Sample student account
-- Email   : student@gmail.com
-- Password: Student@1234
-- ------------------------------------------------
INSERT INTO `users` (`name`, `email`, `password_hash`, `phone`, `is_active`) VALUES
(
    'Demo Student',
    'student@gmail.com',
    '$2y$12$A7cMn0hOWRuFpO1ydpCePOYC5JHr5IHqL1OT5kZ3Tq1xDzr6bOc2i',
    '+91 98765 43210',
    1
)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Create a blank resume row for the demo student
INSERT INTO `resumes` (`user_id`, `resume_data`, `last_updated`)
SELECT `id`, NULL, NOW() FROM `users` WHERE `email` = 'student@gmail.com'
ON DUPLICATE KEY UPDATE `last_updated` = NOW();


-- ------------------------------------------------
-- Sample job postings
-- ------------------------------------------------
INSERT INTO `jobs` (`title`, `company`, `location`, `job_type`, `salary`, `description`, `req_skills`, `logo`, `is_active`) VALUES

(
    'Full-Stack PHP Developer',
    'TechCorp India',
    'Ludhiana, Punjab',
    'Full-time',
    '₹3.5 – 5 LPA',
    'We are looking for a Full-Stack PHP Developer to build and maintain web applications. You will work with a modern PHP/MySQL stack, develop RESTful APIs, and collaborate closely with our design and product teams.',
    '["PHP", "MySQL", "JavaScript", "HTML", "CSS", "REST API", "Git"]',
    '💻',
    1
),

(
    'React Frontend Developer',
    'PixelCraft Studios',
    'Remote',
    'Full-time',
    '₹4 – 7 LPA',
    'Join our fast-growing product team as a React Developer. You will build performant, accessible UIs, integrate with backend APIs, and own the frontend architecture of multiple SaaS products.',
    '["React", "JavaScript", "TypeScript", "HTML", "CSS", "Tailwind", "REST API", "Git"]',
    '⚛️',
    1
),

(
    'Python & Machine Learning Intern',
    'DataMind Solutions',
    'Chandigarh',
    'Internship',
    '₹15,000 / month',
    'Exciting internship opportunity for students passionate about AI/ML. You will assist in building ML pipelines, cleaning datasets, and deploying models using Python and popular ML frameworks.',
    '["Python", "Machine Learning", "NumPy", "Pandas", "scikit-learn", "SQL"]',
    '🤖',
    1
),

(
    'Java Backend Engineer',
    'FinServe Technologies',
    'Mohali, Punjab',
    'Full-time',
    '₹5 – 8 LPA',
    'We need a strong Java Backend Engineer to design and implement scalable microservices. Experience with Spring Boot, REST APIs, and cloud infrastructure (AWS/GCP) is a plus.',
    '["Java", "Spring Boot", "MySQL", "REST API", "Git", "Docker", "AWS"]',
    '☕',
    1
),

(
    'UI/UX Designer',
    'Creative Axis',
    'Remote',
    'Contract',
    '₹2.5 – 4 LPA',
    'We are hiring a UI/UX Designer to craft beautiful, user-centric digital experiences. You will create wireframes, prototypes, and high-fidelity designs for web and mobile applications.',
    '["Figma", "Adobe XD", "HTML", "CSS", "User Research", "Prototyping", "Wireframing"]',
    '🎨',
    1
),

(
    'DevOps Engineer',
    'CloudBase Systems',
    'Bangalore (Hybrid)',
    'Full-time',
    '₹6 – 10 LPA',
    'Looking for a DevOps Engineer to manage our CI/CD pipelines, infrastructure-as-code, and cloud deployments. You will work with Docker, Kubernetes, and AWS to keep our platform running reliably.',
    '["Docker", "Kubernetes", "AWS", "Linux", "CI/CD", "Git", "Python", "Bash"]',
    '⚙️',
    1
),

(
    'Android App Developer',
    'MobiEdge Labs',
    'Ludhiana, Punjab',
    'Full-time',
    '₹3 – 5.5 LPA',
    'Build innovative Android applications from scratch. You will design and implement new features, write clean Kotlin code, and work with REST APIs to connect to our backend services.',
    '["Android", "Kotlin", "Java", "REST API", "Git", "Firebase", "XML"]',
    '📱',
    1
),

(
    'Data Analyst',
    'InsightBridge Analytics',
    'Remote',
    'Full-time',
    '₹3.5 – 6 LPA',
    'We need a Data Analyst to transform raw data into actionable insights. You will write SQL queries, build dashboards in Power BI or Tableau, and present findings to stakeholders.',
    '["SQL", "Python", "Excel", "Power BI", "Tableau", "Data Visualization", "Statistics"]',
    '📊',
    1
);


-- ============================================================================
-- HELPER: Verify the schema was created correctly
-- Uncomment to run a quick check after import.
-- ============================================================================
-- SHOW TABLES;
-- SELECT COUNT(*) AS admin_count    FROM admins;
-- SELECT COUNT(*) AS settings_count FROM system_settings;
-- SELECT COUNT(*) AS job_count      FROM jobs;
