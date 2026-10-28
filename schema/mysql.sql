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

CREATE TABLE IF NOT EXISTS project_categories (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ckey          VARCHAR(64)  NOT NULL UNIQUE,
  label         VARCHAR(191) NOT NULL,
  needs_client  TINYINT(1)   NOT NULL DEFAULT 1,
  client_label  VARCHAR(64)  NULL,
  position      INT          NOT NULL DEFAULT 0,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     INT NOT NULL,
  category_id   INT NULL,
  name          VARCHAR(191) NOT NULL,
  code          VARCHAR(64)  NOT NULL UNIQUE,
  track         VARCHAR(96)  NULL,                  -- overrides client track when set
  color         VARCHAR(16)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  target_hours  DECIMAL(10,2) NULL,
  planned_end   DATE         NULL,
  created_at    DATETIME     NOT NULL,
  FOREIGN KEY (client_id)   REFERENCES clients(id),
  FOREIGN KEY (category_id) REFERENCES project_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS absence_categories (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ckey          VARCHAR(64)  NOT NULL UNIQUE,
  label         VARCHAR(191) NOT NULL,
  position      INT          NOT NULL DEFAULT 0,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  account       VARCHAR(16)  NULL,
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absences (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  category_id   INT NOT NULL,
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  note          VARCHAR(255) NULL,
  hours         DECIMAL(4,2) NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX idx_absences_user (user_id, start_date, end_date),
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES absence_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS process_categories (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ckey          VARCHAR(64)  NOT NULL UNIQUE,
  label         VARCHAR(191) NOT NULL,
  position      INT          NOT NULL DEFAULT 0,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS process_entries (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  category_id   INT NOT NULL,
  start_ts      DATETIME NOT NULL,
  end_ts        DATETIME NOT NULL,
  duration_min  INT      NOT NULL,
  note          VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX idx_process_entries_user (user_id, start_ts),
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES process_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  user_id       INT NULL,
  created_at    DATETIME     NOT NULL,
  KEY idx_import_batches_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS sentiment_entries (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT      NOT NULL,
  as_of      DATE     NOT NULL,
  mood       TINYINT  NULL,
  flow       TINYINT  NULL,
  note       VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_sentiment_user_day (user_id, as_of),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_sentiment_as_of ON sentiment_entries (as_of);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT       NOT NULL,
  token_hash   CHAR(64)  NOT NULL,
  expires_at   DATETIME  NOT NULL,
  used_at      DATETIME  NULL,
  created_at   DATETIME  NOT NULL,
  requester_ip VARCHAR(64) NULL,
  UNIQUE KEY uq_prt_token_hash (token_hash),
  KEY idx_prt_user    (user_id),
  KEY idx_prt_expires (expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_trackers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  project_id  INT NOT NULL,
  type        VARCHAR(64) NOT NULL DEFAULT 'status_report',
  status      VARCHAR(32) NULL,          -- status_report sub-status
  date_value  DATE NULL,                 -- delay/extension new deadline
  note        TEXT NOT NULL,
  value       DECIMAL(10,2) NULL,
  value_forecast DECIMAL(10,2) NULL,
  user_id     INT NULL,
  created_at  DATETIME NOT NULL,
  KEY idx_project_trackers_project (project_id, created_at),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheduler_runs (
  name              VARCHAR(64)  PRIMARY KEY,
  last_run_at       DATETIME     NULL,
  last_result       VARCHAR(32)  NULL,
  last_duration_ms  INT          NULL,
  last_error        VARCHAR(512) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version    VARCHAR(64) PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- v1.0 F1: Mitarbeiter-Vertragsdaten (historisiert) + Jahresuebertraege.
CREATE TABLE IF NOT EXISTS employee_terms (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  valid_from      DATE NOT NULL,
  employment_type VARCHAR(16) NOT NULL,
  weekly_hours    DECIMAL(5,2) NOT NULL DEFAULT 40,
  vacation_days   DECIMAL(5,2) NOT NULL DEFAULT 25,
  holiday_region  VARCHAR(32) NULL,
  created_at      DATETIME NOT NULL,
  INDEX idx_employee_terms_user_from (user_id, valid_from),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_carryovers (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  year           INT NOT NULL,
  vacation_days  DECIMAL(6,2) NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uk_carry_user_year (user_id, year),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- Hinweis: Planung/Budget/Report, Angebote und Rechnungen (inkl. Buchhaltungs-
-- Konnektoren) sind Teil von Timeminator Pro und nicht in dieser Community-
-- Edition enthalten. Siehe README.md.
