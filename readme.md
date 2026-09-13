# OverDose Pharmacy

Three separate sites share one MySQL database. They are hosted **locally on XAMPP** for development and should be deployed to a live PHP/MySQL host for the assessment demo.

## Where each site is hosted (local)

Start **Apache** and **MySQL** in the XAMPP control panel first.

| Site | URL | Who it is for |
| --- | --- | --- |
| Customer storefront | [http://localhost/pharmacy/](http://localhost/pharmacy/) | Shop, cart, online pickup |
| Staff counter | [http://localhost/pharmacy/staff/login.php](http://localhost/pharmacy/staff/login.php) | POS, stock, Rx approvals, pickups |
| Administrator | [http://localhost/pharmacy/admin/login.php](http://localhost/pharmacy/admin/login.php) | Catalogue, promotions, reports, Rx, pickups |
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

## Demo logins

Password for every seeded account: **`password`**

| Site | Email | After sign-in |
| --- | --- | --- |
| Customer | `customer@pharmacy.local` | Storefront |
| Staff | `staff@pharmacy.local` | Counter |
| Admin | `admin@pharmacy.local` | Dashboard |

## Database setup

**Fresh install** — import in order:

1. `sql/schema.sql`
2. `sql/seed.sql`

**Existing database** — run in order (skip statements that error if already applied):

1. `sql/migrate_prescription.sql` (if needed)
2. `sql/migrate_reservation.sql`

## Apache notes

Enable `mod_rewrite` for `/api/products`. See `apache/httpd-notes.conf`.
