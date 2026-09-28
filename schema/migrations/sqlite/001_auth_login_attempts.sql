-- Login throttling table (issue #7). Idempotent for both fresh installs and upgrades.
CREATE TABLE IF NOT EXISTS auth_login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  bucket       TEXT NOT NULL,
  attempted_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_ala_bucket_time ON auth_login_attempts (bucket, attempted_at);
