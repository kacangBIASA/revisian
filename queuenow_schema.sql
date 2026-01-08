-- =========================
-- QueueNow - MySQL Schema
-- =========================

CREATE DATABASE IF NOT EXISTS queuenow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE queuenow;

-- -------------------------
-- 1) Owners (Auth)
-- -------------------------
CREATE TABLE IF NOT EXISTS owners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,     -- bcrypt hash
  phone VARCHAR(30) NULL,

  -- info bisnis saat registrasi (biar sesuai form kamu)
  business_name VARCHAR(150) NOT NULL,
  business_category VARCHAR(100) NULL,

  plan ENUM('FREE','PRO') NOT NULL DEFAULT 'FREE',
  pro_since DATETIME NULL,
  pro_until DATETIME NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_owners_email (email)
) ENGINE=InnoDB;

-- -------------------------
-- 2) Businesses
-- (owner bisa punya >1 bisnis jika nanti dibutuhkan)
-- -------------------------
CREATE TABLE IF NOT EXISTS businesses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id BIGINT UNSIGNED NOT NULL,

  name VARCHAR(150) NOT NULL,
  category VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_businesses_owner (owner_id),
  CONSTRAINT fk_businesses_owner
    FOREIGN KEY (owner_id) REFERENCES owners(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- 3) Branches (Cabang)
-- -------------------------
CREATE TABLE IF NOT EXISTS branches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,

  name VARCHAR(150) NOT NULL,
  address TEXT NULL,

  start_queue_number INT NOT NULL DEFAULT 1,     -- nomor antrean awal
  is_active TINYINT(1) NOT NULL DEFAULT 1,

  -- jadwal operasional (simple): simpan JSON
  -- contoh: {"mon":["08:00","17:00"],"tue":["08:00","17:00"],"sat":["09:00","14:00"]}
  operational_hours JSON NULL,

  -- QR untuk ambil antrean di tempat
  qr_token VARCHAR(64) NOT NULL,                -- random token
  qr_image_path VARCHAR(255) NULL,              -- kalau kamu simpan file qrcode png

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_branches_qr_token (qr_token),
  KEY idx_branches_business (business_id),
  CONSTRAINT fk_branches_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- 4) Daily counter (reset harian)
-- Menyimpan nomor terakhir per cabang per tanggal
-- -------------------------
CREATE TABLE IF NOT EXISTS branch_daily_counters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  queue_date DATE NOT NULL,
  last_number INT NOT NULL DEFAULT 0,
  reset_at DATETIME NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_counter_branch_date (branch_id, queue_date),
  KEY idx_counter_branch (branch_id),
  CONSTRAINT fk_counter_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- 5) Queue tickets (antrean + riwayat)
-- Ini sekaligus jadi "history"
-- Free nanti dihapus via job (lebih dari 30 hari)
-- -------------------------
CREATE TABLE IF NOT EXISTS queue_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,

  queue_date DATE NOT NULL,
  queue_number INT NOT NULL,

  source ENUM('ONLINE','QR') NOT NULL,  -- online / scan QR
  status ENUM('WAITING','CALLED','SKIPPED','DONE','CANCELLED') NOT NULL DEFAULT 'WAITING',

  taken_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- waktu diambil
  called_at DATETIME NULL,                               -- waktu dipanggil
  finished_at DATETIME NULL,                             -- selesai (atau skip)
  note VARCHAR(255) NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_branch_date_number (branch_id, queue_date, queue_number),
  KEY idx_ticket_branch_date (branch_id, queue_date),
  KEY idx_ticket_branch_status (branch_id, status),
  CONSTRAINT fk_ticket_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- 6) Subscription history (opsional tapi rapi)
-- Owner plan aktif tetap disimpan di owners.plan
-- Table ini untuk histori upgrade/downgrade
-- -------------------------
CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id BIGINT UNSIGNED NOT NULL,

  plan ENUM('FREE','PRO') NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_sub_owner (owner_id),
  KEY idx_sub_status (status),
  CONSTRAINT fk_sub_owner
    FOREIGN KEY (owner_id) REFERENCES owners(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- 7) Midtrans Transactions
-- -------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,

  order_id VARCHAR(64) NOT NULL,
  gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'IDR',

  -- status mengikuti notifikasi Midtrans (dibuat enum cukup lengkap)
  status ENUM(
    'PENDING','SETTLEMENT','CAPTURE','DENY','CANCEL','EXPIRE','FAILURE',
    'REFUND','CHARGEBACK','PARTIAL_REFUND','PARTIAL_CHARGEBACK'
  ) NOT NULL DEFAULT 'PENDING',

  payment_type VARCHAR(50) NULL,
  transaction_time DATETIME NULL,

  snap_token VARCHAR(255) NULL,
  raw_notification JSON NULL, -- simpan payload notifikasi midtrans

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_trx_order_id (order_id),
  KEY idx_trx_owner (owner_id),
  KEY idx_trx_status (status),
  CONSTRAINT fk_trx_owner
    FOREIGN KEY (owner_id) REFERENCES owners(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_trx_subscription
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- 8) Export logs (PDF/Excel)
-- (khusus pro, tapi ini cuma pencatatan)
-- -------------------------
CREATE TABLE IF NOT EXISTS report_exports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,

  export_type ENUM('PDF','EXCEL') NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,

  file_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_export_owner (owner_id),
  KEY idx_export_branch (branch_id),
  CONSTRAINT fk_export_owner
    FOREIGN KEY (owner_id) REFERENCES owners(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_export_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE owners
  ADD COLUMN google_id VARCHAR(64) NULL AFTER email,
  ADD COLUMN avatar VARCHAR(255) NULL AFTER google_id,
  ADD COLUMN auth_provider ENUM('LOCAL','GOOGLE') NOT NULL DEFAULT 'LOCAL' AFTER avatar;

-- agar akun google bisa tanpa password
ALTER TABLE owners
  MODIFY password_hash VARCHAR(255) NULL;

ALTER TABLE transactions
  ADD COLUMN redirect_url TEXT NULL AFTER snap_token;

-- 1) Branch: simpan sesi antrean aktif per hari
ALTER TABLE branches
  ADD COLUMN queue_session_date DATE NULL,
  ADD COLUMN queue_session_no INT NOT NULL DEFAULT 1;

-- 2) Tickets: simpan sesi agar nomor boleh mulai dari 1 lagi setelah reset
ALTER TABLE queue_tickets
  ADD COLUMN session_no INT NOT NULL DEFAULT 1 AFTER queue_date;

-- 3) Counter: simpan sesi juga (counter per cabang-per hari-per sesi)
ALTER TABLE branch_daily_counters
  ADD COLUMN session_no INT NOT NULL DEFAULT 1 AFTER queue_date;

-- 4) Ubah UNIQUE ticket: sekarang uniknya termasuk session_no
ALTER TABLE queue_tickets
  DROP INDEX uq_ticket_branch_date_number,
  ADD UNIQUE KEY uq_ticket_branch_date_session_number (branch_id, queue_date, session_no, queue_number),
  ADD KEY idx_ticket_branch_date_session_status (branch_id, queue_date, session_no, status, queue_number);

-- 5) Ubah UNIQUE counter: termasuk session_no
ALTER TABLE branch_daily_counters
  DROP INDEX uq_counter_branch_date,
  ADD UNIQUE KEY uq_counter_branch_date_session (branch_id, queue_date, session_no),
  ADD KEY idx_counter_branch_date_session (branch_id, queue_date, session_no);
