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
