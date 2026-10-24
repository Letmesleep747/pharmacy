-- Widen notifications.type for payment receipt review events.
USE pharmacy;

ALTER TABLE notifications
  MODIFY COLUMN type ENUM(
    'prescription_submitted',
    'prescription_approved',
    'prescription_rejected',
    'refund_completed',
    'order_ready_for_pickup',
    'order_completed',
    'payment_submitted',
    'payment_approved',
    'payment_rejected_refunded'
  ) NOT NULL;
