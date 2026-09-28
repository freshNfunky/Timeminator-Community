-- Login throttling table (issue #7). Idempotent for both fresh installs and upgrades.
CREATE TABLE IF NOT EXISTS auth_login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  bucket       VARCHAR(160) NOT NULL,
  attempted_at DATETIME     NOT NULL,
  INDEX idx_ala_bucket_time (bucket, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
