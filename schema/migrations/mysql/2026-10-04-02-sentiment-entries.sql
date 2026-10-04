-- Team-Sentiment-Tracking (fixes #92). Each user records a daily mood score
-- (1=awful .. 5=great), optionally with a short note. One entry per user per
-- day; a second write on the same day updates the first via UPSERT in the
-- controller. Score range is enforced in PHP (CHECK constraints are MySQL 8.0+).

CREATE TABLE IF NOT EXISTS sentiment_entries (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT      NOT NULL,
  as_of      DATE     NOT NULL,
  score      TINYINT  NOT NULL,
  note       VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_sentiment_user_day (user_id, as_of),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_sentiment_as_of ON sentiment_entries (as_of);
