-- Timeminator Community - SQLite schema (local/dev mode, zero infra)
-- Mirrors schema/mysql.sql. Timestamps stored as 'YYYY-MM-DD HH:MM:SS' local.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS roles (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL UNIQUE,
  label         TEXT NOT NULL,
  is_system     INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS permissions (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT NOT NULL UNIQUE,
  label         TEXT NOT NULL,
  grp           TEXT NOT NULL DEFAULT 'general'
);

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       INTEGER NOT NULL,
  permission_id INTEGER NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name  TEXT NOT NULL DEFAULT '',
  role_id       INTEGER NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT NULL,
  created_at    TEXT NOT NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS clients (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  code          TEXT NOT NULL UNIQUE,
  track         TEXT NULL,
  color         TEXT NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS projects (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  client_id     INTEGER NOT NULL,
  category_id   INTEGER NULL,
  name          TEXT NOT NULL,
  code          TEXT NOT NULL UNIQUE,
  track         TEXT NULL,
  color         TEXT NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  target_hours  REAL NULL,
  planned_start TEXT NULL,
  planned_end   TEXT NULL,
  kickoff_at    TEXT NULL,
  closeout_at   TEXT NULL,
  review_interval_days INTEGER NULL,
  created_at    TEXT NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (category_id) REFERENCES project_categories(id)
);

CREATE TABLE IF NOT EXISTS project_categories (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ckey          TEXT    NOT NULL UNIQUE,
  label         TEXT    NOT NULL,
  needs_client  INTEGER NOT NULL DEFAULT 1,
  client_label  TEXT    NULL,
  position      INTEGER NOT NULL DEFAULT 0,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS absence_categories (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ckey          TEXT    NOT NULL UNIQUE,
  label         TEXT    NOT NULL,
  position      INTEGER NOT NULL DEFAULT 0,
  active        INTEGER NOT NULL DEFAULT 1,
  account       TEXT    NULL,
  created_at    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS absences (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL,
  category_id   INTEGER NOT NULL,
  start_date    TEXT    NOT NULL,
  end_date      TEXT    NOT NULL,
  note          TEXT    NULL,
  hours         REAL    NULL,
  created_at    TEXT    NOT NULL,
  updated_at    TEXT    NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES absence_categories(id)
);
CREATE INDEX IF NOT EXISTS idx_absences_user ON absences (user_id, start_date, end_date);

CREATE TABLE IF NOT EXISTS process_categories (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ckey          TEXT    NOT NULL UNIQUE,
  label         TEXT    NOT NULL,
  position      INTEGER NOT NULL DEFAULT 0,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS process_entries (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL,
  category_id   INTEGER NOT NULL,
  start_ts      TEXT    NOT NULL,
  end_ts        TEXT    NOT NULL,
  duration_min  INTEGER NOT NULL,
  note          TEXT    NULL,
  created_at    TEXT    NOT NULL,
  updated_at    TEXT    NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES process_categories(id)
);
CREATE INDEX IF NOT EXISTS idx_process_entries_user ON process_entries (user_id, start_ts);

CREATE TABLE IF NOT EXISTS work_packages (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id    INTEGER NOT NULL,
  code          TEXT    NOT NULL,
  name          TEXT    NOT NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT    NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id),
  UNIQUE (project_id, code)
);

CREATE INDEX IF NOT EXISTS idx_work_packages_project_active
  ON work_packages (project_id, active);

CREATE TABLE IF NOT EXISTS tasks (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id      INTEGER NOT NULL,
  work_package_id INTEGER NULL,
  name            TEXT    NOT NULL,
  kind            TEXT    NULL,
  active          INTEGER NOT NULL DEFAULT 1,
  created_at      TEXT    NOT NULL,
  FOREIGN KEY (project_id)      REFERENCES projects(id),
  FOREIGN KEY (work_package_id) REFERENCES work_packages(id)
);

CREATE TABLE IF NOT EXISTS import_batches (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  label         TEXT NOT NULL,
  source        TEXT NOT NULL DEFAULT 'manual',
  note          TEXT NULL,
  user_id       INTEGER NULL,
  created_at    TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_import_batches_user ON import_batches (user_id);

CREATE TABLE IF NOT EXISTS time_entries (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id         INTEGER NOT NULL,
  task_id         INTEGER NOT NULL,
  work_package_id INTEGER NULL,
  project_id      INTEGER NOT NULL,
  client_id       INTEGER NOT NULL,
  start_ts        TEXT NOT NULL,
  end_ts          TEXT NULL,
  duration_min    INTEGER NULL,
  note            TEXT NOT NULL DEFAULT '',
  source          TEXT NOT NULL DEFAULT 'manual',
  evidence        TEXT NULL,
  batch_id        INTEGER NULL,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL,
  FOREIGN KEY (user_id)         REFERENCES users(id),
  FOREIGN KEY (task_id)         REFERENCES tasks(id),
  FOREIGN KEY (work_package_id) REFERENCES work_packages(id),
  FOREIGN KEY (project_id)      REFERENCES projects(id),
  FOREIGN KEY (client_id)       REFERENCES clients(id),
  FOREIGN KEY (batch_id)        REFERENCES import_batches(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_entries_user    ON time_entries (user_id);
CREATE INDEX IF NOT EXISTS idx_entries_start   ON time_entries (start_ts);
CREATE INDEX IF NOT EXISTS idx_entries_project ON time_entries (project_id);
CREATE INDEX IF NOT EXISTS idx_entries_client  ON time_entries (client_id);
CREATE INDEX IF NOT EXISTS idx_entries_batch   ON time_entries (batch_id);

CREATE TABLE IF NOT EXISTS scopes (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  description   TEXT NULL,
  config        TEXT NOT NULL,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  username     TEXT NULL,
  user_id      INTEGER NULL,
  ip_address   TEXT NULL,
  success      INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_user ON login_attempts (username, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip   ON login_attempts (ip_address, attempted_at);

CREATE TABLE IF NOT EXISTS sentiment_entries (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL,
  as_of      TEXT    NOT NULL,
  mood       INTEGER NULL,
  flow       INTEGER NULL,
  note       TEXT    NULL,
  created_at TEXT    NOT NULL,
  updated_at TEXT    NULL,
  UNIQUE (user_id, as_of),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_sentiment_as_of ON sentiment_entries (as_of);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL,
  token_hash  TEXT    NOT NULL,
  expires_at  TEXT    NOT NULL,
  used_at     TEXT    NULL,
  created_at  TEXT    NOT NULL,
  requester_ip TEXT   NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_prt_token_hash ON password_reset_tokens (token_hash);
CREATE INDEX IF NOT EXISTS idx_prt_user     ON password_reset_tokens (user_id);
CREATE INDEX IF NOT EXISTS idx_prt_expires  ON password_reset_tokens (expires_at);

CREATE TABLE IF NOT EXISTS project_trackers (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id  INTEGER NOT NULL,
  type        TEXT    NOT NULL DEFAULT 'status_report',
  status      TEXT    NULL,       -- status_report sub-status: on_plan|delay|extension
  date_value  TEXT    NULL,       -- new deadline (ISO date) for delay/extension
  note        TEXT    NOT NULL,
  value       REAL    NULL,       -- progress now %, 0..100
  value_forecast REAL NULL,       -- 1-month-ahead forecast %, 0..100
  project_mood  INTEGER NULL,     -- v1.0 F2: 1..5 bei type='progress'
  customer_mood INTEGER NULL,     -- v1.0 F2: 1..5 bei type='progress'
  user_id     INTEGER NULL,
  created_at  TEXT    NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_project_trackers_project ON project_trackers (project_id, created_at);

CREATE TABLE IF NOT EXISTS scheduler_runs (
  name              TEXT    PRIMARY KEY,
  last_run_at       TEXT    NULL,
  last_result       TEXT    NULL,
  last_duration_ms  INTEGER NULL,
  last_error        TEXT    NULL
);

CREATE TABLE IF NOT EXISTS schema_migrations (
  version    TEXT PRIMARY KEY,
  applied_at TEXT NOT NULL
);

-- v1.0 F2: Plan-Allokationen (Soll-Stunden-Matrix Mitarbeiter x Zeitraum).
CREATE TABLE IF NOT EXISTS plan_allocations (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id      INTEGER NOT NULL,
  user_id         INTEGER NULL,
  period_kind     TEXT    NOT NULL DEFAULT 'total',
  period_start    TEXT    NULL,
  planned_minutes INTEGER NOT NULL DEFAULT 0,
  note            TEXT    NULL,
  created_at      TEXT    NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_plan_project      ON plan_allocations (project_id);
CREATE INDEX IF NOT EXISTS idx_plan_project_week ON plan_allocations (project_id, period_kind, period_start);

-- v1.0 F2: Terminverschiebungs-Log fuer Projekte (und optional Arbeitspakete).
CREATE TABLE IF NOT EXISTS date_shifts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  kind       TEXT    NOT NULL,
  object_id  INTEGER NOT NULL,
  project_id INTEGER NOT NULL,
  old_end    TEXT    NULL,
  new_end    TEXT    NULL,
  reason     TEXT    NOT NULL,
  user_id    INTEGER NULL,
  created_at TEXT    NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_date_shifts_project ON date_shifts (project_id, created_at);
CREATE INDEX IF NOT EXISTS idx_date_shifts_object  ON date_shifts (kind, object_id, created_at);

-- v1.0 F1: Mitarbeiter-Vertragsdaten (historisiert) + Jahresuebertraege.
-- Mitgefuehrt in Migration 2026-10-28-01-employee-terms.php; hier fuer
-- frische Installationen und Tests (bootInMemoryDb) direkt mit angelegt.
CREATE TABLE IF NOT EXISTS employee_terms (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id         INTEGER NOT NULL,
  valid_from      TEXT    NOT NULL,
  employment_type TEXT    NOT NULL,
  weekly_hours    REAL    NOT NULL DEFAULT 40,
  vacation_days   REAL    NOT NULL DEFAULT 25,
  holiday_region  TEXT    NULL,
  created_at      TEXT    NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_employee_terms_user_from ON employee_terms (user_id, valid_from);

CREATE TABLE IF NOT EXISTS account_carryovers (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL,
  year           INTEGER NOT NULL,
  vacation_days  REAL    NOT NULL DEFAULT 0,
  overtime_hours REAL    NOT NULL DEFAULT 0,
  UNIQUE (user_id, year),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Hinweis: Planung/Budget/Report, Angebote und Rechnungen (inkl. Buchhaltungs-
-- Konnektoren) sind Teil von Timeminator Pro und nicht in dieser Community-
-- Edition enthalten. Siehe README.md.
