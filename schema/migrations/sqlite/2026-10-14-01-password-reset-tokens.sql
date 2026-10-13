-- Password-Reset-Flow (Pro-Sync A.1, #107). Each reset request creates one
-- row; the token itself never hits the DB — only its SHA-256 hex digest.
-- Rows carry an expiry (1h) and a used-at marker so a token can only redeem
-- once. The scheduler (A.3) prunes expired rows; until then they are
-- harmless.

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
