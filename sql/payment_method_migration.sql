-- Run once in phpMyAdmin if the pharmacy database already exists.
ALTER TABLE orders
ADD COLUMN payment_method ENUM('fpx','online_banking','card','tng_ewallet','duitnow_qr','pay_on_collection') NOT NULL DEFAULT 'pay_on_collection';
