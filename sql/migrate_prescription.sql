-- Run once if pharmacy DB already exists (phpMyAdmin → SQL).
-- If a column already exists, skip that statement. Prefer re-importing schema.sql + seed.sql.

USE pharmacy;

ALTER TABLE products
  ADD COLUMN requires_prescription TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

ALTER TABLE orders
  ADD COLUMN status ENUM('pending_approval', 'approved', 'rejected', 'completed')
    NOT NULL DEFAULT 'completed' AFTER total;

ALTER TABLE orders ADD COLUMN prescription_path VARCHAR(255) NULL AFTER status;
ALTER TABLE orders ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER prescription_path;
ALTER TABLE orders ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by;
ALTER TABLE orders ADD COLUMN review_note VARCHAR(255) NULL AFTER reviewed_at;

UPDATE products SET requires_prescription = 1
WHERE sku IN ('AMOX-250', 'MET-500', 'AML-5', 'SALB-INH');

UPDATE orders SET status = 'completed' WHERE status IS NULL OR status = '';
