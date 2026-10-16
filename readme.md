# OverDose Pharmacy

Three separate sites share one MySQL database. They are hosted **locally on XAMPP** for development and should be deployed to a live PHP/MySQL host for the assessment demo.

## Where each site is hosted (local)

Start **Apache** and **MySQL** in the XAMPP control panel first.

| Site | URL | Who it is for |
| --- | --- | --- |
| Customer storefront | [http://localhost/pharmacy/](http://localhost/pharmacy/) | Shop, cart, online pickup |
| Staff counter | [http://localhost/pharmacy/staff/login.php](http://localhost/pharmacy/staff/login.php) | POS, stock, Rx, pickups (one branch per staff) |
| Administrator | [http://localhost/pharmacy/admin/login.php](http://localhost/pharmacy/admin/login.php) | Catalogue, promotions, reports, all-branch Rx/pickups |
| phpMyAdmin | [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) | Database (local only) |

## Stock reservation and pickup

Online orders **reserve** stock (`reserved_qty`) instead of deducting it immediately.

**Available stock shown to customers = quantity − reserved_qty**

1. Customer checks out → stock reserved at the chosen branch for **48 hours** (`pickup_by`).
2. OTC → status `ready_for_pickup`. Rx → `pending_approval` (still reserved).
3. Staff/admin approve Rx → `ready_for_pickup`. Reject → reservation released.
4. Staff **Pickups** → Mark collected → on-hand quantity reduced and reservation cleared.
5. If not collected by `pickup_by` → order `expired`, reserved stock released automatically.

## Prescription (Rx) products

Seeded Rx items: Amoxicillin, Metformin, Amlodipine, Salbutamol.

## Checkout & Payment Options (Demo)
Customers can select from the following payment methods during checkout:
- **FPX** (requires selecting a bank from a dropdown)
- **Credit / Debit Card** (demo only, requires test card details)
- **Touch 'n Go eWallet** (redirects to a simulated gateway page)
- **DuitNow QR** (shows a demo QR code)

*Note: All payments are simulated for this assessment. No real transactions are processed. A demo receipt is generated after the order is placed.*

## Branch scope (staff vs admin)

- Each **staff** account is assigned to one home branch and only sees that branch’s sales, Rx queue, pickups, stock, and POS.
- **Admin** keeps a centralized view across all branches (reports, Rx, pickups).
- For a parallel demo: open two browsers (or normal + Incognito), sign in as Branch A and Branch B staff at the same time.

## Demo logins

Password for every seeded account: **`password`**

| Site | Email | After sign-in |
| --- | --- | --- |
| Customer | `customer@pharmacy.local` | Storefront |
| Staff (Branch A) | `staff@pharmacy.local` | Counter — Branch A only |
| Staff (Branch B) | `staff_b@pharmacy.local` | Counter — Branch B only |
| Staff (Branch C) | `staff_c@pharmacy.local` | Counter — Branch C only |
| Admin | `admin@pharmacy.local` | Dashboard (all branches) |

## Database setup

**Fresh install** — import in order:

1. `sql/schema.sql`
2. `sql/seed.sql`

**Existing database** — run in order (skip statements that error if already applied):

1. `sql/migrate_prescription.sql` (adds `requires_prescription`, `status`, `prescription_path`, etc.)
2. `sql/migrate_reservation.sql` (adds `reserved_qty`, `pickup_by`, and widens the `status` enum)
3. `sql/payment_method_migration.sql` (adds `payment_method` column to orders)
4. `sql/migrate_staff_branch.sql` (adds `users.branch_id` and Branch B/C staff accounts)

## Apache notes

Enable `mod_rewrite` for `/api/products`. See `apache/httpd-notes.conf`.