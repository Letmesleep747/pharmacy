-- Run once in phpMyAdmin after selecting the pharmacy database.
ALTER TABLE orders
  ADD COLUMN payment_status ENUM('pending_review','approved','rejected','refunded') NOT NULL DEFAULT 'approved',
  ADD COLUMN payment_method VARCHAR(40) NULL,
  ADD COLUMN payment_receipt_path VARCHAR(255) NULL,
  ADD COLUMN payment_review_note VARCHAR(255) NULL,
  ADD COLUMN payment_reviewed_by INT UNSIGNED NULL,
  ADD COLUMN payment_reviewed_at DATETIME NULL,
  ADD KEY idx_orders_payment_status (payment_status),
  ADD CONSTRAINT fk_orders_payment_reviewer FOREIGN KEY (payment_reviewed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE notifications
  MODIFY COLUMN type ENUM(
    'prescription_submitted',
    'prescription_approved',
    'prescription_rejected',
    'refund_completed',
    'payment_submitted',
    'payment_approved',
    'payment_rejected_refunded'
  ) NOT NULL;
