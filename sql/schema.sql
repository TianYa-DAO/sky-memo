CREATE DATABASE IF NOT EXISTS sky_memo DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sky_memo;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bosses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  game_id VARCHAR(64) NOT NULL DEFAULT '',
  account TEXT,
  password TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(32) NOT NULL UNIQUE,
  boss_id INT UNSIGNED NOT NULL,
  type VARCHAR(16) NOT NULL DEFAULT '代跑',
  types TEXT,                          -- 多选任务类型 JSON 数组，如 ["每日任务","10蜡烛"]
  repeat_weekly TINYINT NOT NULL DEFAULT 1, -- 1=每周重复(按weekdays) 0=指定日期(按selected_dates)
  selected_dates TEXT,                 -- repeat_weekly=0 时：具体日期 JSON 数组
  daily_figure INT NOT NULL DEFAULT 0,
  daily_task INT NOT NULL DEFAULT 0,
  daily_currency INT NOT NULL DEFAULT 0,
  weekdays VARCHAR(32) NOT NULL DEFAULT '[1,2,3,4,5]',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  price DECIMAL(10,2) DEFAULT 0,
  payment_status VARCHAR(8) NOT NULL DEFAULT '未付',
  status VARCHAR(8) NOT NULL DEFAULT '待接',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_boss (boss_id),
  KEY idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_date DATE NOT NULL,
  order_id INT UNSIGNED NULL,
  figure_count INT NOT NULL DEFAULT 0,
  task_count INT NOT NULL DEFAULT 0,
  currency_count INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uq_date_order (record_date, order_id),
  KEY idx_date (record_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendar_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_date DATE NOT NULL,
  order_id INT UNSIGNED NULL,
  type VARCHAR(16) NOT NULL DEFAULT '其他',
  title VARCHAR(255) NOT NULL,
  done TINYINT NOT NULL DEFAULT 0,
  KEY idx_event_date (event_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS client_views (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  token CHAR(32) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;