# OverDose Pharmacy

Three separate sites share one MySQL database. They are hosted **locally on XAMPP**, not on a public cloud.

## Where each site is hosted

Start **Apache** and **MySQL** in the XAMPP control panel first.

| Site | URL | Who it is for |
| --- | --- | --- |
| Customer storefront | [http://localhost/pharmacy/](http://localhost/pharmacy/) | Shop, cart, online pickup |
| Staff counter | [http://localhost/pharmacy/staff/login.php](http://localhost/pharmacy/staff/login.php) | POS, stock, Rx approvals |
| Administrator | [http://localhost/pharmacy/admin/login.php](http://localhost/pharmacy/admin/login.php) | Catalogue, promotions, reports, Rx approvals |
| phpMyAdmin | [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) | Database (local only) |

## Prescription (Rx) flow

Some products are marked **Requires prescription** (Amoxicillin, Metformin, Amlodipine, Salbutamol in the seed data).

1. Customer adds an Rx item → at checkout must upload PDF/JPG/PNG.
2. Order status becomes **pending_approval** (stock is **not** reduced yet).
3. Staff or admin opens **Rx approvals**, views the file, then **Approve** (stock deducted) or **Reject**.
4. In-store POS: staff must tick **Prescription verified in person** before completing an Rx sale.

## Demo logins

Password for every seeded account: **`password`**

| Site | Email | After sign-in |
| --- | --- | --- |
| Customer | `customer@pharmacy.local` | Storefront |
| Staff | `staff@pharmacy.local` | Counter (POS, Rx, stock) |
| Admin | `admin@pharmacy.local` | Dashboard |

## Database setup

**Fresh install** — import in order:

1. `sql/schema.sql`
2. `sql/seed.sql`

**Existing database** — also run `sql/migrate_prescription.sql` once (or re-import schema + seed).

Do not run `TRUNCATE TABLE orders` on its own.

## Apache notes

Enable `mod_rewrite` for `/api/products`. See `apache/httpd-notes.conf`.
