-- Help / service requests submitted from the mobile app "All Services" grid.
CREATE TABLE IF NOT EXISTS service_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  service_key VARCHAR(64) NOT NULL,
  service_name VARCHAR(150) NOT NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  business_name VARCHAR(190) NULL,
  message TEXT NULL,
  status ENUM('new','in_progress','done','rejected') NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_status (status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
