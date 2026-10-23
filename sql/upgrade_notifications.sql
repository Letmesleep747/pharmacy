-- For existing installations only; keeps existing notification rows.
ALTER TABLE notifications MODIFY COLUMN type ENUM(
 'prescription_submitted', 'prescription_approved', 'prescription_rejected',
 'refund_completed', 'order_ready_for_pickup', 'order_completed'
) NOT NULL;
