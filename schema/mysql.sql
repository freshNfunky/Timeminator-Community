-- Timeminator Community - MySQL/MariaDB schema
-- Charset utf8mb4, InnoDB. Timestamps are stored as naive local datetimes
-- (app timezone, see config) in the format 'YYYY-MM-DD HH:MM:SS'.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ---------- Auth: roles / permissions / users ----------

CREATE TABLE IF NOT EXISTS roles (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(64)  NOT NULL UNIQUE,      -- machine name, e.g. 'admin'
  label         VARCHAR(128) NOT NULL,             -- display label
  is_system     TINYINT(1)   NOT NULL DEFAULT 0,   -- system roles cannot be deleted
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(96)  NOT NULL UNIQUE,       -- e.g. 'entries.manage'
  label         VARCHAR(160) NOT NULL,
  grp           VARCHAR(64)  NOT NULL DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(96)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(160) NOT NULL DEFAULT '',
  role_id       INT NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Domain: clients / projects / tasks / entries ----------

CREATE TABLE IF NOT EXISTS clients (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(191) NOT NULL,
  code          VARCHAR(64)  NOT NULL UNIQUE,       -- stable key for mapping/re-use
  track         VARCHAR(96)  NULL,                  -- generic grouping label ("Gleis")
  color         VARCHAR(16)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     INT NOT NULL,
  name          VARCHAR(191) NOT NULL,
  code          VARCHAR(64)  NOT NULL UNIQUE,
  track         VARCHAR(96)  NULL,                  -- overrides client track when set
  color         VARCHAR(16)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_packages (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  project_id    INT          NOT NULL,
  code          VARCHAR(64)  NOT NULL,
  name          VARCHAR(191) NOT NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL,
  UNIQUE KEY uq_wp_project_code (project_id, code),
  KEY idx_wp_project_active (project_id, active),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  project_id      INT NOT NULL,
  work_package_id INT NULL,
  name            VARCHAR(191) NOT NULL,
  kind            VARCHAR(64)  NULL,                  -- e.g. 'dev', 'edit'
  active          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL,
  KEY idx_tasks_work_package (work_package_id),
  FOREIGN KEY (project_id)      REFERENCES projects(id),
  FOREIGN KEY (work_package_id) REFERENCES work_packages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS import_batches (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  label         VARCHAR(191) NOT NULL,
  source        VARCHAR(96)  NOT NULL DEFAULT 'manual',
  note          TEXT NULL,
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS time_entries (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  task_id         INT NOT NULL,
  work_package_id INT NULL,
  project_id      INT NOT NULL,                       -- denormalized for stable history + fast stats
  client_id       INT NOT NULL,                       -- denormalized
  start_ts        DATETIME     NOT NULL,
  end_ts          DATETIME     NULL,                  -- NULL while a timer is running
  duration_min    INT          NULL,                  -- NULL while running; else minutes
  note            VARCHAR(500) NOT NULL DEFAULT '',
  source          VARCHAR(32)  NOT NULL DEFAULT 'manual', -- manual | timer | import
  evidence        TEXT NULL,                          -- provenance for imported entries
  batch_id        INT NULL,                           -- for whole-import rollback
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  KEY idx_time_entries_work_package (work_package_id),
  FOREIGN KEY (user_id)         REFERENCES users(id),
  FOREIGN KEY (task_id)         REFERENCES tasks(id),
  FOREIGN KEY (work_package_id) REFERENCES work_packages(id),
  FOREIGN KEY (project_id)      REFERENCES projects(id),
  FOREIGN KEY (client_id)       REFERENCES clients(id),
  FOREIGN KEY (batch_id)        REFERENCES import_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_entries_user    ON time_entries (user_id);
CREATE INDEX idx_entries_start   ON time_entries (start_ts);
CREATE INDEX idx_entries_project ON time_entries (project_id);
CREATE INDEX idx_entries_client  ON time_entries (client_id);
CREATE INDEX idx_entries_batch   ON time_entries (batch_id);

-- Saved analysis views ("Nachweis-Sicht"), fully generic (no hardcoded projects).
CREATE TABLE IF NOT EXISTS scopes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(191) NOT NULL,
  description   TEXT NULL,
  config        TEXT NOT NULL,                      -- JSON: include/exclude/cutover/track
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(96) NULL,
  user_id      INT NULL,
  ip_address   VARCHAR(45) NULL,
  success      TINYINT(1)  NOT NULL DEFAULT 0,
  attempted_at DATETIME    NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_login_attempts_user ON login_attempts (username, attempted_at);
CREATE INDEX idx_login_attempts_ip   ON login_attempts (ip_address, attempted_at);

CREATE TABLE IF NOT EXISTS schema_migrations (
  version    VARCHAR(64) PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- Hinweis: Planung/Budget/Report, Angebote und Rechnungen (inkl. Buchhaltungs-
-- Konnektoren) sind Teil von Timeminator Pro und nicht in dieser Community-
-- Edition enthalten. Siehe README.md.
