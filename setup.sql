-- ═══════════════════════════════════════════════
--  Fini — Database Setup
--  Run this once in phpMyAdmin or MySQL CLI:
--    mysql -u root -p < setup.sql
-- ═══════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS fini_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fini_db;

-- ── Accounts ──────────────────────────────────
CREATE TABLE IF NOT EXISTS accounts (
    id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    email        VARCHAR(120) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) DEFAULT NULL,
    bio          TEXT         DEFAULT NULL,
    gender       VARCHAR(30)  DEFAULT NULL,
    location     VARCHAR(120) DEFAULT NULL,
    avatar_data  MEDIUMTEXT   DEFAULT NULL,
    cover_data   MEDIUMTEXT   DEFAULT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Run this if upgrading an existing install (safe to re-run):
ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS bio          TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS gender       VARCHAR(30)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location     VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS avatar_data  MEDIUMTEXT   DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cover_data   MEDIUMTEXT   DEFAULT NULL;

-- ── Tasks ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS tasks (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(255) NOT NULL,
    status     ENUM('todo','inprogress','done') NOT NULL DEFAULT 'todo',
    tag_class  VARCHAR(30)  NOT NULL DEFAULT 'tag-blue',
    tag_label  VARCHAR(50)  NOT NULL DEFAULT 'Task',
    due_date   DATE         DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Password Resets ───────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(120) NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
