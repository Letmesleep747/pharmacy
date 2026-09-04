# Wellspring Pharmacy

Three separate sites share one MySQL database. They are hosted **locally on XAMPP**, not on a public cloud.

## Where each site is hosted

Start **Apache** and **MySQL** in the XAMPP control panel first.

| Site | URL | Who it is for |
| --- | --- | --- |
| Customer storefront | [http://localhost/pharmacy/](http://localhost/pharmacy/) | Shop, cart, online pickup |
| Staff counter | [http://localhost/pharmacy/staff/login.php](http://localhost/pharmacy/staff/login.php) | In-store POS and stock |
| Administrator | [http://localhost/pharmacy/admin/login.php](http://localhost/pharmacy/admin/login.php) | Catalogue, branches, promotions, reports |
| phpMyAdmin | [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) | Database (local only) |

App files: `/Applications/XAMPP/xamppfiles/htdocs/pharmacy`  
Database: MySQL `pharmacy` on `localhost` (see `config/config.php`).

Customer accounts cannot sign in on staff or admin. Staff cannot open the admin site. Admins and staff who open the customer shop are sent back to their own site.

## Demo logins

Password for every seeded account: **`password`**

| Site | Email | After sign-in |
| --- | --- | --- |
| Customer | `customer@pharmacy.local` | Storefront |
| Staff | `staff@pharmacy.local` | Counter (POS, stock) |
| Admin | `admin@pharmacy.local` | Dashboard (catalogue, reports) |

New **customer** accounts: [http://localhost/pharmacy/pages/register.php](http://localhost/pharmacy/pages/register.php)

## Database setup

Import in this order:

1. `sql/schema.sql`
2. `sql/seed.sql`

Use **Import** on the file. Do not run `TRUNCATE TABLE orders` on its own.

## Apache notes

Enable `mod_rewrite` for `/api/products`. See `apache/httpd-notes.conf` for a custom virtual host.
