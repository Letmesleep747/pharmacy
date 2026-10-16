-- Assign staff accounts to a home branch so each counter only sees its own orders/stock.
-- Run once on an existing pharmacy database. Prefer a fresh schema.sql + seed.sql when possible.

USE pharmacy;

-- 1. Staff home branch (skip if column already exists)
ALTER TABLE users
  ADD COLUMN branch_id INT UNSIGNED NULL AFTER role;

ALTER TABLE users
  ADD KEY idx_users_branch (branch_id);

ALTER TABLE users
  ADD CONSTRAINT fk_users_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- 2. Map existing demo staff to Branch A; add Branch B / C counters for parallel demos
UPDATE users SET branch_id = 1 WHERE role = 'staff' AND email = 'staff@pharmacy.local';

INSERT INTO users (name, email, password_hash, role, branch_id)
SELECT 'Siti Aminah', 'staff_b@pharmacy.local', password_hash, 'staff', 2
FROM users WHERE email = 'staff@pharmacy.local' LIMIT 1
ON DUPLICATE KEY UPDATE branch_id = 2, role = 'staff';

INSERT INTO users (name, email, password_hash, role, branch_id)
SELECT 'Raj Kumar', 'staff_c@pharmacy.local', password_hash, 'staff', 3
FROM users WHERE email = 'staff@pharmacy.local' LIMIT 1
ON DUPLICATE KEY UPDATE branch_id = 3, role = 'staff';
