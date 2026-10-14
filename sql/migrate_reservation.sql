-- Run once if pharmacy already exists. Skip any statement that errors (column exists).
-- Prefer a fresh import of schema.sql + seed.sql when possible.

USE pharmacy;

-- 1. Stock: Add reservation quantity tracking
ALTER TABLE branch_stock
  ADD COLUMN reserved_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;

-- 2. Orders: Add pickup_by column (now that status exists from migrate_prescription.sql)
ALTER TABLE orders
  ADD COLUMN pickup_by DATETIME NULL AFTER status;

-- 3. Widen status enum to support the full reservation lifecycle
-- (This replaces the 4-option ENUM from migrate_prescription.sql with 6 options)
ALTER TABLE orders
  MODIFY COLUMN status ENUM(
    'pending_approval',
    'approved',
    'rejected',
    'ready_for_pickup',
    'completed',
    'expired'
  ) NOT NULL DEFAULT 'completed';