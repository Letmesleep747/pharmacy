-- OverDose Pharmacy — one import for tables and demo data.
-- phpMyAdmin: Import this file only. Do not import the old migration scripts.
-- Re-importing drops and recreates the database, so it never hits "Duplicate column".
-- Demo password for every account: password

CREATE DATABASE IF NOT EXISTS pharmacy
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pharmacy;

-- Optional limited-privilege account for the application (run as root).
-- CREATE USER IF NOT EXISTS 'pharmacy_app'@'localhost' IDENTIFIED BY 'change_this_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON pharmacy.* TO 'pharmacy_app'@'localhost';
-- FLUSH PRIVILEGES;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS promotion_products;
DROP TABLE IF EXISTS promotions;
DROP TABLE IF EXISTS branch_stock;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE branches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  opening_hours VARCHAR(120) NOT NULL DEFAULT '9:00–18:00',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer',
  -- Staff home branch (NULL for customers and administrators)
  branch_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_branch (branch_id),
  CONSTRAINT fk_users_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(40) NOT NULL,
  description TEXT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  image_path VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  -- 1 = customer must upload a prescription; staff must approve before stock is taken
  requires_prescription TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_name (name),
  KEY idx_products_category (category_id),
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE branch_stock (
  branch_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  -- Held for uncollected pickup orders. Available to customers = quantity - reserved_qty.
  reserved_qty INT UNSIGNED NOT NULL DEFAULT 0,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 10,
  PRIMARY KEY (branch_id, product_id),
  KEY idx_stock_quantity (quantity),
  KEY idx_stock_product (product_id),
  CONSTRAINT fk_stock_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_stock_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE promotions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  discount_type ENUM('percent', 'fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_promotions_dates (start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE promotion_products (
  promotion_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (promotion_id, product_id),
  KEY idx_pp_product (product_id),
  CONSTRAINT fk_pp_promotion
    FOREIGN KEY (promotion_id) REFERENCES promotions (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pp_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  branch_id INT UNSIGNED NOT NULL,
  source ENUM('online', 'instore') NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL,
  -- pending_approval → ready_for_pickup (after Rx) → completed at collection, or expired
  status ENUM('pending_approval', 'approved', 'rejected', 'cancelled', 'ready_for_pickup', 'completed', 'expired') NOT NULL DEFAULT 'completed',
  pickup_by DATETIME NULL,
  prescription_path VARCHAR(255) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(255) NULL,
  -- Online checkout stays pending_review until staff approve the receipt.
  payment_status ENUM('pending_review','approved','rejected','refunded') NOT NULL DEFAULT 'approved',
  payment_method VARCHAR(40) NULL,
  payment_receipt_path VARCHAR(255) NULL,
  payment_review_note VARCHAR(255) NULL,
  payment_reviewed_by INT UNSIGNED NULL,
  payment_reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_created (created_at),
  KEY idx_orders_branch (branch_id),
  KEY idx_orders_user (user_id),
  KEY idx_orders_status (status),
  KEY idx_orders_payment_status (payment_status),
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_orders_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_orders_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_orders_payment_reviewer
    FOREIGN KEY (payment_reviewed_by) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_items_order (order_id),
  KEY idx_items_product (product_id),
  CONSTRAINT fk_items_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_items_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  type ENUM(
    'prescription_submitted',
    'prescription_approved',
    'prescription_rejected',
    'refund_completed',
    'order_ready_for_pickup',
    'order_completed',
    'payment_submitted',
    'payment_approved',
    'payment_rejected_refunded'
  ) NOT NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notification_event (user_id, order_id, type),
  KEY idx_notifications_user (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_notifications_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id INT UNSIGNED NULL,
  actor_name VARCHAR(120) NOT NULL,
  actor_role ENUM('staff','admin','system') NOT NULL DEFAULT 'system',
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  branch_id INT UNSIGNED NULL,
  summary VARCHAR(255) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_created (created_at),
  KEY idx_activity_actor (actor_id),
  KEY idx_activity_action (action),
  KEY idx_activity_entity (entity_type, entity_id),
  KEY idx_activity_branch (branch_id),
  CONSTRAINT fk_activity_actor
    FOREIGN KEY (actor_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_activity_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Demo data. Password for every account below is: password

INSERT INTO branches (id, name, address, phone, opening_hours, is_active) VALUES
(1, 'Branch A — KL City', '12 Jalan Ampang, Kuala Lumpur', '03-2161-1001', '8:30–21:00', 1),
(2, 'Branch B — Petaling Jaya', '88 Jalan Universiti, Petaling Jaya', '03-7956-2002', '9:00–20:00', 1),
(3, 'Branch C — Subang Jaya', '5 Jalan SS15/4, Subang Jaya', '03-5633-3003', '9:00–18:00', 1);

INSERT INTO users (id, name, email, password_hash, role, branch_id) VALUES
(1, 'System Admin', 'admin@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
(2, 'Aisha Rahman', 'staff@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 1),
(3, 'Lim Wei Ming', 'customer@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', NULL),
(4, 'Siti Aminah', 'staff_b@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 2),
(5, 'Raj Kumar', 'staff_c@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 3);

INSERT INTO categories (id, name) VALUES
(1, 'Pain relief'),
(2, 'Antibiotics'),
(3, 'Allergy'),
(4, 'Digestive'),
(5, 'Vitamins'),
(6, 'Respiratory'),
(7, 'Chronic care'),
(8, 'First aid');

INSERT INTO products (id, category_id, name, sku, description, unit_price, is_active, requires_prescription) VALUES
(1, 1, 'Paracetamol 500mg', 'PARA-500', 'Film-coated tablets for mild to moderate pain and fever. Pack of 20 tablets.', 8.90, 1, 0),
(2, 1, 'Ibuprofen 200mg', 'IBU-200', 'Non-steroidal anti-inflammatory tablets. Pack of 20.', 12.50, 1, 0),
(3, 2, 'Amoxicillin 250mg', 'AMOX-250', 'Capsule antibiotic. Requires a valid prescription before dispensing. Pack of 21.', 18.00, 1, 1),
(4, 3, 'Cetirizine 10mg', 'CET-10', 'Once-daily antihistamine for hay fever and urticaria. Pack of 10.', 15.90, 1, 0),
(5, 3, 'Loratadine 10mg', 'LOR-10', 'Non-drowsy antihistamine tablets. Pack of 10.', 14.50, 1, 0),
(6, 4, 'Omeprazole 20mg', 'OME-20', 'Proton-pump inhibitor capsules for acid reflux. Pack of 14.', 22.00, 1, 0),
(7, 5, 'Vitamin C 1000mg', 'VITC-1000', 'Effervescent vitamin C tablets. Tube of 20.', 19.90, 1, 0),
(8, 5, 'Vitamin D3 1000IU', 'VITD-1000', 'Softgel capsules supporting bone health. Bottle of 60.', 24.00, 1, 0),
(9, 6, 'Salbutamol Inhaler', 'SALB-INH', 'Reliever inhaler for bronchospasm. Requires prescription. 200 metered doses.', 32.00, 1, 1),
(10, 6, 'Cough Syrup 100ml', 'COUGH-100', 'Soothing cough mixture for dry cough. 100 ml bottle.', 11.80, 1, 0),
(11, 1, 'Aspirin 100mg', 'ASP-100', 'Low-dose aspirin tablets. Pack of 28.', 9.50, 1, 0),
(12, 7, 'Metformin 500mg', 'MET-500', 'Oral antidiabetic tablets. Requires prescription. Pack of 56.', 16.40, 1, 1),
(13, 7, 'Amlodipine 5mg', 'AML-5', 'Calcium-channel blocker tablets. Requires prescription. Pack of 28.', 13.20, 1, 1),
(14, 8, 'Hydrocortisone Cream 1%', 'HC-1', 'Topical cream for mild inflammatory skin conditions. 15 g tube.', 10.90, 1, 0),
(15, 8, 'Oral Rehydration Salts', 'ORS-1', 'WHO-formula ORS sachets. Box of 10.', 7.50, 1, 0),
(16, 8, 'Antiseptic Liquid 50ml', 'ANT-50', 'Antiseptic solution for minor cuts and grazes.', 6.80, 1, 0);

INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES
(1, 1, 80, 15), (2, 1, 42, 15), (3, 1, 0, 15),
(1, 2, 55, 12), (2, 2, 8, 12), (3, 2, 30, 12),
(1, 3, 20, 10), (2, 3, 18, 10), (3, 3, 6, 10),
(1, 4, 40, 10), (2, 4, 22, 10), (3, 4, 14, 10),
(1, 5, 25, 8), (2, 5, 0, 8), (3, 5, 19, 8),
(1, 6, 33, 10), (2, 6, 16, 10), (3, 6, 11, 10),
(1, 7, 70, 20), (2, 7, 45, 20), (3, 7, 12, 20),
(1, 8, 28, 10), (2, 8, 9, 10), (3, 8, 21, 10),
(1, 9, 15, 6), (2, 9, 10, 6), (3, 9, 4, 6),
(1, 10, 36, 12), (2, 10, 5, 12), (3, 10, 24, 12),
(1, 11, 50, 15), (2, 11, 27, 15), (3, 11, 18, 15),
(1, 12, 22, 10), (2, 12, 14, 10), (3, 12, 0, 10),
(1, 13, 18, 8), (2, 13, 7, 8), (3, 13, 12, 8),
(1, 14, 26, 8), (2, 14, 13, 8), (3, 14, 9, 8),
(1, 15, 60, 20), (2, 15, 3, 20), (3, 15, 40, 20),
(1, 16, 44, 12), (2, 16, 31, 12), (3, 16, 17, 12);

INSERT INTO promotions (id, name, description, discount_type, discount_value, start_date, end_date, is_active) VALUES
(1, 'Fever Care 15% Off', 'Discount on selected pain and fever relief this month.', 'percent', 15.00, '2026-08-01', '2026-09-30', 1),
(2, 'Vitamin Wellness RM5 Off', 'Fixed RM5 off selected vitamin products.', 'fixed', 5.00, '2026-08-15', '2026-10-15', 1),
(3, 'Expired Winter Promo', 'Inactive historical promotion for testing status handling.', 'percent', 50.00, '2025-12-01', '2026-01-31', 0);

INSERT INTO promotion_products (promotion_id, product_id) VALUES
(1, 1), (1, 2), (1, 11),
(2, 7), (2, 8),
(3, 9);

INSERT INTO orders (id, user_id, branch_id, source, subtotal, discount_total, total, status, pickup_by, created_at) VALUES
(1, 3, 1, 'online', 17.80, 2.67, 15.13, 'completed', '2026-08-12 10:15:00', '2026-08-10 10:15:00'),
(2, NULL, 1, 'instore', 32.00, 0.00, 32.00, 'completed', NULL, '2026-08-12 16:40:00'),
(3, 3, 2, 'online', 39.80, 5.00, 34.80, 'completed', '2026-08-20 09:05:00', '2026-08-18 09:05:00'),
(4, NULL, 2, 'instore', 25.00, 1.88, 23.12, 'completed', NULL, '2026-08-22 14:20:00'),
(5, 3, 1, 'online', 48.00, 0.00, 48.00, 'completed', '2026-08-30 11:30:00', '2026-08-28 11:30:00'),
(6, NULL, 3, 'instore', 35.40, 1.34, 34.06, 'completed', NULL, '2026-09-01 13:10:00'),
(7, 3, 1, 'online', 26.80, 0.00, 26.80, 'completed', '2026-09-04 18:45:00', '2026-09-02 18:45:00'),
(8, NULL, 2, 'instore', 64.00, 0.00, 64.00, 'completed', NULL, '2026-09-03 10:00:00'),
(9, 3, 3, 'online', 22.50, 0.00, 22.50, 'completed', '2026-09-05 19:20:00', '2026-09-03 19:20:00'),
(10, NULL, 1, 'instore', 53.40, 4.01, 49.39, 'completed', NULL, '2026-09-04 09:50:00');

INSERT INTO order_items (order_id, product_id, quantity, unit_price, discount_amount, line_total) VALUES
(1, 1, 2, 8.90, 2.67, 15.13),
(2, 9, 1, 32.00, 0.00, 32.00),
(3, 7, 2, 19.90, 5.00, 34.80),
(4, 2, 2, 12.50, 1.88, 23.12),
(5, 8, 2, 24.00, 0.00, 48.00),
(6, 1, 1, 8.90, 1.34, 7.56),
(6, 10, 1, 11.80, 0.00, 11.80),
(6, 16, 2, 6.80, 0.00, 13.60),
(7, 12, 1, 16.40, 0.00, 16.40),
(7, 14, 1, 10.90, 0.00, 10.90),
(8, 9, 2, 32.00, 0.00, 64.00),
(9, 15, 3, 7.50, 0.00, 22.50),
(10, 1, 3, 8.90, 4.01, 22.69),
(10, 4, 1, 15.90, 0.00, 15.90),
(10, 11, 1, 9.50, 0.00, 9.50);
