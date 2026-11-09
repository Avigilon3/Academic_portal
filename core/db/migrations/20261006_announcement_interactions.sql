-- Incremental database changes for announcement reactions and comments.
-- Safe to run on the current database; it only creates missing tables/columns.

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  created_by INT NULL,
  legacy_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_announcements_created_at (created_at),
  UNIQUE KEY uq_announcements_legacy_hash (legacy_hash),
  CONSTRAINT fk_announcements_author
      FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  user_id INT NOT NULL,
  reaction_type VARCHAR(30) NOT NULL DEFAULT 'like',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_announcement_reaction (announcement_id, user_id),
  KEY idx_announcement_reactions_user (user_id),
  CONSTRAINT fk_announcement_reactions_post
      FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_reactions_user
      FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  user_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_announcement_comments_post_created (announcement_id, created_at),
  KEY idx_announcement_comments_user (user_id),
  CONSTRAINT fk_announcement_comments_post
    FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_comments_user
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications
  ADD COLUMN announcement_id INT NULL;

ALTER TABLE notifications
  ADD CONSTRAINT fk_notifications_announcement
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE;