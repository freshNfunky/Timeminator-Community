-- Team-Sentiment-Tracking (fixes #92). Each user records a daily mood score
-- (1=awful .. 5=great), optionally with a short note. One entry per user per
-- day; a second write on the same day updates the first via UPSERT in the
-- controller. Score range is enforced in PHP (keep SQLite portable).

CREATE TABLE IF NOT EXISTS sentiment_entries (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL,
  as_of      TEXT    NOT NULL,
  score      INTEGER NOT NULL,
  note       TEXT    NULL,
  created_at TEXT    NOT NULL,
  updated_at TEXT    NULL,
  UNIQUE (user_id, as_of),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sentiment_as_of ON sentiment_entries (as_of);
