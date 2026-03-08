
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  k VARCHAR(120) NOT NULL UNIQUE,
  v MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tg_users (
  id BIGINT PRIMARY KEY,
  username VARCHAR(128) NULL,
  first_name VARCHAR(128) NULL,
  last_name VARCHAR(128) NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_seen_at TIMESTAMP NULL,
  requests_count BIGINT NOT NULL DEFAULT 0,
  daily_limit INT NULL,
  monthly_limit INT NULL,
  daily_tokens_limit INT NOT NULL DEFAULT 0,
  monthly_tokens_limit INT NOT NULL DEFAULT 0,
  is_pro TINYINT(1) NOT NULL DEFAULT 0,
  memory_enabled TINYINT(1) NOT NULL DEFAULT 1,
  ui_lang VARCHAR(8) NULL,
  model VARCHAR(64) NULL,
  system_prompt MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tg_user_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  model VARCHAR(64) NOT NULL,
  temperature DECIMAL(4,3) NULL,
  max_output_tokens INT NULL,
  prompt_text MEDIUMTEXT NOT NULL,
  response_text MEDIUMTEXT NULL,
  status VARCHAR(32) NULL,
  error_text TEXT NULL,
  latency_ms INT NULL,
  input_tokens INT NULL,
  output_tokens INT NULL,
  total_tokens INT NULL,
  INDEX idx_user_time (tg_user_id, created_at),
  CONSTRAINT fk_ai_logs_user FOREIGN KEY (tg_user_id) REFERENCES tg_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(16) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  tokens_total INT NOT NULL DEFAULT 0,
  thread_id INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_created (user_id, created_at),
  KEY idx_user_thread (user_id, thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
