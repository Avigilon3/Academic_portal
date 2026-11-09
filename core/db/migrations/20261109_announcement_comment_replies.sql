-- Add support for threaded announcement comments on existing databases.
SET @parent_comment_column_exists = (
  SELECT COUNT(*)
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'announcement_comments'
     AND COLUMN_NAME = 'parent_comment_id'
);

SET @parent_comment_migration = IF(
  @parent_comment_column_exists = 0,
  'ALTER TABLE announcement_comments ADD COLUMN parent_comment_id INT NULL AFTER user_id',
  'SELECT 1'
);

PREPARE parent_comment_statement FROM @parent_comment_migration;
EXECUTE parent_comment_statement;
DEALLOCATE PREPARE parent_comment_statement;