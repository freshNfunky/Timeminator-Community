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
