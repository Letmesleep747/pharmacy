-- Run once if pharmacy already exists. Skip any statement that errors (column exists).
-- Prefer a fresh import of schema.sql + seed.sql when possible.

USE pharmacy;

ALTER TABLE branch_stock
  ADD COLUMN reserved_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;

ALTER TABLE orders
  ADD COLUMN pickup_by DATETIME NULL AFTER status;

-- Widen status enum (MySQL replaces the whole ENUM list)
ALTER TABLE orders
  MODIFY COLUMN status ENUM(
    'pending_approval',
    'approved',
    'rejected',
    'ready_for_pickup',
    'completed',
    'expired'
  ) NOT NULL DEFAULT 'completed';
