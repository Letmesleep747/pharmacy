-- Pharmacy Management System — demo data
-- Import AFTER schema.sql
-- Seed login password for all demo users: password
-- Hash is PHP password_hash() compatible bcrypt.

USE pharmacy;

-- DELETE (not TRUNCATE): InnoDB refuses TRUNCATE on a parent table while
-- child FKs exist. phpMyAdmin also re-enables FK checks, which ignores
-- SET FOREIGN_KEY_CHECKS = 0. Child tables first, then parents.
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM promotion_products;
DELETE FROM promotions;
DELETE FROM branch_stock;
DELETE FROM products;
DELETE FROM categories;
DELETE FROM branches;
DELETE FROM users;

ALTER TABLE order_items AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE promotions AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE branches AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

INSERT INTO users (id, name, email, password_hash, role) VALUES
(1, 'System Admin', 'admin@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Aisha Rahman', 'staff@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
(3, 'Lim Wei Ming', 'customer@pharmacy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

INSERT INTO branches (id, name, address, phone, opening_hours, is_active) VALUES
(1, 'Branch A — KL City', '12 Jalan Ampang, Kuala Lumpur', '03-2161-1001', '8:30–21:00', 1),
(2, 'Branch B — Petaling Jaya', '88 Jalan Universiti, Petaling Jaya', '03-7956-2002', '9:00–20:00', 1),
(3, 'Branch C — Subang Jaya', '5 Jalan SS15/4, Subang Jaya', '03-5633-3003', '9:00–18:00', 1);

INSERT INTO categories (id, name) VALUES
(1, 'Pain relief'),
(2, 'Antibiotics'),
(3, 'Allergy'),
(4, 'Digestive'),
(5, 'Vitamins'),
(6, 'Respiratory'),
(7, 'Chronic care'),
(8, 'First aid');

INSERT INTO products (id, category_id, name, sku, description, unit_price, is_active) VALUES
(1, 1, 'Paracetamol 500mg', 'PARA-500', 'Film-coated tablets for mild to moderate pain and fever. Pack of 20 tablets.', 8.90, 1),
(2, 1, 'Ibuprofen 200mg', 'IBU-200', 'Non-steroidal anti-inflammatory tablets. Pack of 20.', 12.50, 1),
(3, 2, 'Amoxicillin 250mg', 'AMOX-250', 'Capsule antibiotic. Dispense according to prescription guidance. Pack of 21.', 18.00, 1),
(4, 3, 'Cetirizine 10mg', 'CET-10', 'Once-daily antihistamine for hay fever and urticaria. Pack of 10.', 15.90, 1),
(5, 3, 'Loratadine 10mg', 'LOR-10', 'Non-drowsy antihistamine tablets. Pack of 10.', 14.50, 1),
(6, 4, 'Omeprazole 20mg', 'OME-20', 'Proton-pump inhibitor capsules for acid reflux. Pack of 14.', 22.00, 1),
(7, 5, 'Vitamin C 1000mg', 'VITC-1000', 'Effervescent vitamin C tablets. Tube of 20.', 19.90, 1),
(8, 5, 'Vitamin D3 1000IU', 'VITD-1000', 'Softgel capsules supporting bone health. Bottle of 60.', 24.00, 1),
(9, 6, 'Salbutamol Inhaler', 'SALB-INH', 'Reliever inhaler for bronchospasm. 200 metered doses.', 32.00, 1),
(10, 6, 'Cough Syrup 100ml', 'COUGH-100', 'Soothing cough mixture for dry cough. 100 ml bottle.', 11.80, 1),
(11, 1, 'Aspirin 100mg', 'ASP-100', 'Low-dose aspirin tablets. Pack of 28.', 9.50, 1),
(12, 7, 'Metformin 500mg', 'MET-500', 'Oral antidiabetic tablets. Pack of 56.', 16.40, 1),
(13, 7, 'Amlodipine 5mg', 'AML-5', 'Calcium-channel blocker tablets. Pack of 28.', 13.20, 1),
(14, 8, 'Hydrocortisone Cream 1%', 'HC-1', 'Topical cream for mild inflammatory skin conditions. 15 g tube.', 10.90, 1),
(15, 8, 'Oral Rehydration Salts', 'ORS-1', 'WHO-formula ORS sachets. Box of 10.', 7.50, 1),
(16, 8, 'Antiseptic Liquid 50ml', 'ANT-50', 'Antiseptic solution for minor cuts and grazes.', 6.80, 1);

-- Branch C has Paracetamol 500mg out of stock (qty 0) as in the assignment example
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

-- Historical sales so analytics charts are non-empty (prices match catalogue; discounts applied on promo lines)
INSERT INTO orders (id, user_id, branch_id, source, subtotal, discount_total, total, created_at) VALUES
(1, 3, 1, 'online', 17.80, 2.67, 15.13, '2026-08-10 10:15:00'),
(2, NULL, 1, 'instore', 32.00, 0.00, 32.00, '2026-08-12 16:40:00'),
(3, 3, 2, 'online', 39.80, 5.00, 34.80, '2026-08-18 09:05:00'),
(4, NULL, 2, 'instore', 25.00, 1.88, 23.12, '2026-08-22 14:20:00'),
(5, 3, 1, 'online', 48.00, 0.00, 48.00, '2026-08-28 11:30:00'),
(6, NULL, 3, 'instore', 35.40, 1.34, 34.06, '2026-09-01 13:10:00'),
(7, 3, 1, 'online', 26.80, 0.00, 26.80, '2026-09-02 18:45:00'),
(8, NULL, 2, 'instore', 64.00, 0.00, 64.00, '2026-09-03 10:00:00'),
(9, 3, 3, 'online', 22.50, 0.00, 22.50, '2026-09-03 19:20:00'),
(10, NULL, 1, 'instore', 53.40, 4.01, 49.39, '2026-09-04 09:50:00');

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
