-- Password-Reset-Flow (Pro-Sync A.1, #107). Each reset request creates one
-- row; the token itself never hits the DB — only its SHA-256 hex digest.
-- Rows carry an expiry (1h) and a used-at marker so a token can only redeem
-- once. The scheduler (A.3) prunes expired rows; until then they are
-- harmless.

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
