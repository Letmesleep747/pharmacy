-- Run once in phpMyAdmin if the pharmacy database already exists.
-- (Run this AFTER migrate_prescription.sql and migrate_reservation.sql)

USE pharmacy;

ALTER TABLE orders
ADD COLUMN payment_method ENUM('fpx','card','tng','duitnow','pay_on_collection') 
NOT NULL DEFAULT 'pay_on_collection';