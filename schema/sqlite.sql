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
  name          TEXT NOT NULL,
  code          TEXT NOT NULL UNIQUE,
  track         TEXT NULL,
  color         TEXT NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE IF NOT EXISTS tasks (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id    INTEGER NOT NULL,
  name          TEXT NOT NULL,
  kind          TEXT NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS import_batches (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  label         TEXT NOT NULL,
  source        TEXT NOT NULL DEFAULT 'manual',
  note          TEXT NULL,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS time_entries (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL,
  task_id       INTEGER NOT NULL,
  project_id    INTEGER NOT NULL,
  client_id     INTEGER NOT NULL,
  start_ts      TEXT NOT NULL,
  end_ts        TEXT NULL,
  duration_min  INTEGER NULL,
  note          TEXT NOT NULL DEFAULT '',
  source        TEXT NOT NULL DEFAULT 'manual',
  evidence      TEXT NULL,
  batch_id      INTEGER NULL,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL,
  FOREIGN KEY (user_id)    REFERENCES users(id),
  FOREIGN KEY (task_id)    REFERENCES tasks(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (client_id)  REFERENCES clients(id),
  FOREIGN KEY (batch_id)   REFERENCES import_batches(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS schema_migrations (
  version    TEXT PRIMARY KEY,
  applied_at TEXT NOT NULL
);

-- Login throttling (issue #7). One row per failed attempt; buckets are
-- 'ip:<addr>' and 'user:<name>' so both dimensions are throttled.
CREATE TABLE IF NOT EXISTS auth_login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  bucket       TEXT NOT NULL,
  attempted_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_ala_bucket_time ON auth_login_attempts (bucket, attempted_at);

-- Hinweis: Planung/Budget/Report, Angebote und Rechnungen (inkl. Buchhaltungs-
-- Konnektoren) sind Teil von Timeminator Pro und nicht in dieser Community-
-- Edition enthalten. Siehe README.md.
