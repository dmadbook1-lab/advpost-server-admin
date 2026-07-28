-- ============================================================
-- AdvPost: S3-only media schema + AWS credentials
-- Database: adbookuser_adbookuser
-- Run this once in MySQL Workbench / mysql CLI
-- ============================================================

-- 1) Widen URL columns for full S3 URLs
ALTER TABLE post_image
  MODIFY COLUMN new_post TEXT NULL;

ALTER TABLE post_video
  MODIFY COLUMN post_video TEXT NULL,
  MODIFY COLUMN post_video_hls TEXT NULL,
  MODIFY COLUMN post_video_thumbnail TEXT NULL;

ALTER TABLE reels_video
  MODIFY COLUMN reel_video TEXT NULL,
  MODIFY COLUMN reel_video_thumbnail TEXT NULL;

ALTER TABLE story
  MODIFY COLUMN preview_video TEXT NULL;

ALTER TABLE products
  MODIFY COLUMN images TEXT NULL;

ALTER TABLE admins
  MODIFY COLUMN profile_pic TEXT NULL;

-- 2) Brand logo on user profile (S3 URL)
-- Safe to re-run if column already exists? MySQL 8 may error — ignore if exists.
SET @exist := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'logo_url'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE users ADD COLUMN logo_url TEXT NULL AFTER profile_pic',
  'SELECT \"users.logo_url already exists\"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Link AI poster job → published post
SET @exist := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'image_creation_jobs'
    AND COLUMN_NAME = 'published_post_id'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE image_creation_jobs ADD COLUMN published_post_id INT NULL AFTER output_file_path',
  'SELECT \"image_creation_jobs.published_post_id already exists\"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) AI video jobs (S3 URLs)
CREATE TABLE IF NOT EXISTS video_creation_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  starting_image_url TEXT NOT NULL,
  ad_text TEXT NOT NULL,
  language VARCHAR(50) DEFAULT 'Marathi',
  duration_seconds INT DEFAULT 30,
  camera_motion VARCHAR(100) DEFAULT 'Zoom (In)',
  status ENUM('PENDING','PROCESSING','COMPLETED','FAILED') DEFAULT 'PENDING',
  output_video_url TEXT NULL,
  output_file_path TEXT NULL,
  error_message TEXT NULL,
  published_reel_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
);

-- 5) Store AWS credentials (source of truth for PHP Aws_s3 library)
DELETE FROM aws_credential;
INSERT INTO aws_credential (
  aws_access_key_id,
  aws_secret_access_key,
  aws_bucket,
  aws_url,
  created_at,
  updated_at
) VALUES (
  'YOUR_AWS_ACCESS_KEY_ID',
  'YOUR_AWS_SECRET_ACCESS_KEY',
  'advpost',
  'https://advpost.s3.ap-south-1.amazonaws.com',
  NOW(),
  NOW()
);
