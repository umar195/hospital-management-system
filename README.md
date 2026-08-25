# Hospital Management System

A complete, **offline-friendly** Hospital &amp; Diagnostic Laboratory Management System written in
plain **PHP 7.4+ / MySQL** (PDO, no framework, no Composer). Drop the folder into
`htdocs` (XAMPP / Laragon / WAMP), run the installer and start working.

---

## Features

| Module | What you can do |
| --- | --- |
| **Dashboard** | Today's patients, walk-ins, appointments, lab tests, pending/completed reports, daily &amp; monthly revenue, pending payments, quick actions, recent patients and orders |
| **Patients** | Full CRUD, auto patient ID (`PAT-YYYY-00001`), search + pagination, complete profile with visits, orders, reports, invoices, payments and prescriptions |
| **Walk-In** | Four-step wizard: find/create patient → pick tests → billing (discount, paid amount, method) → printable receipt. Creates visit + order + items + invoice + payment in one transaction |
| **Doctors** | Referring/consulting doctor directory with specialty, licence, activation |
| **Appointments** | Day-wise list, status workflow (Scheduled / Arrived / Completed / Cancelled / No Show) |
| **Tests** | Test categories, test catalogue with price, sample type, reference ranges (general / male / female / child) and multi-parameter panels (CBC, LFT, RFT, Lipid, Thyroid, Urine R/E, Widal seeded) |
| **Lab orders** | Create orders, per-item lab status, results entry with **automatic Low / Normal / High flagging**, payments, report generation |
| **Reports** | Report register, remarks &amp; authorization, print-optimised A4 letterhead, WhatsApp share link |
| **Billing** | Invoices (items, discount, print), payment ledger, receive payments against invoices or lab orders |
| **Prescriptions** | Multi-medicine prescriptions with dosage/frequency/duration, printable |
| **Expenses** | Categorised expense register with totals |
| **Inventory** | Items with batch/expiry, min-stock alerts, stock-in / stock-out / adjustment transactions |
| **Suppliers** | Supplier directory linked to inventory |
| **Analytics** | Income vs expense trend, monthly collections, top tests, expense split, gender split, payment mix, referring doctors (Chart.js) |
| **Backup** | One-click pure-PHP SQL dump, download, delete and restore (with automatic safety backup) |
| **Settings** | Hospital branding + logo, currency, date/time formats, ID prefixes, report/invoice texts, authorized signatory, admin account &amp; password |

Also included: CSRF protection on every POST form, prepared statements everywhere,
input sanitisation, flash messages, 20-rows-per-page pagination, search filters,
activity logging and print stylesheets.

---

## Requirements

* PHP **7.4** or newer (tested on PHP 8.3) with `pdo_mysql` enabled
* MySQL **5.7+** or MariaDB **10.3+**
* Apache (XAMPP, Laragon, WAMP, MAMP) — or PHP's built-in server for a quick test

---

## Installation (XAMPP / Laragon)

1. **Copy the project** into your web root so the path is:

   ```
   C:\xampp\htdocs\hospital-management-system      (XAMPP)
   C:\laragon\www\hospital-management-system       (Laragon)
   ```

2. **Start Apache and MySQL** from the XAMPP/Laragon control panel.

3. **Open the installer** in a browser:

   ```
   http://localhost/hospital-management-system/install.php
   ```

4. Fill in the wizard:
   * MySQL host / user / password (XAMPP default: `root` with an empty password)
   * Database name (default `hospital_db`) — it is created if missing
   * Administrator username, full name and password
   * Optionally tick **Import demo data** to load sample patients, orders, invoices and expenses

5. The installer creates `config/db_config.php`, imports `database/hospital.sql`
   and takes you to the login page.

6. **Log in** and start using the system. Default credentials when you keep the
   suggested values (or when you import the SQL manually):

   ```
   Username: admin
   Password: admin123
   ```

   > Change the password right after the first login from **Settings → Administrator account**.

7. For security, delete `install.php` once the system is running.

### Manual installation (without the wizard)

```bash
mysql -u root -p < database/hospital.sql
mysql -u root -p hospital_db < database/demo_data.sql   # optional demo data
```

Then edit `config/config.php` (or create `config/db_config.php`) with your credentials:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hospital_db');
```

`BASE_URL` is detected automatically, so the app works from any sub-folder. If you
need to force it, define `BASE_URL` in `config/db_config.php` before it is auto-detected.

### Quick test without Apache

```bash
cd hospital-management-system
php -S localhost:8080
# open http://localhost:8080
```

---

## HTML preview (no PHP, no database)

`preview.html` in the project root is a **single self-contained file** that mirrors
every screen of the application so the UI can be reviewed without installing PHP or
MySQL. Just double-click the file (or open it in any browser) — it needs no server.

* Sign in with the pre-filled demo credentials `admin` / `admin123` (nothing is
  validated — the button simply opens the dashboard).
* Navigation uses hash routes, e.g. `#/patients`, `#/order-results?id=1`,
  `#/walkin?receipt=1`. **All Screens** in the sidebar (`#/screens`) lists every
  preview screen with its link.
* Covered modules: dashboard, walk-in registration wizard, patients, appointments,
  doctors, prescriptions, test orders, result entry, reports, tests/parameters/
  categories, invoices, payments, expenses, inventory, stock movements, suppliers,
  analytics charts, backup and settings — including the printable report, invoice,
  prescription and receipt sheets (use the Print buttons).
* Interactive parts work client-side: order/billing calculators, automatic
  High/Low/Normal result flags, table search boxes, dynamic parameter and medicine
  rows and the four-step walk-in wizard.
* Everything runs on in-memory demo data. Saving, deleting, filtering and
  exporting only show a toast — no data is stored and the PHP application is not
  affected. Chart.js and Bootstrap are loaded from the CDN, so charts and icons
  need an internet connection.

---

## Full offline mode (no CDN)

The UI uses Bootstrap 5, Bootstrap Icons and Chart.js. By default the pages load
them from jsDelivr **only when a local copy is missing** — as soon as you place the
files below in the project they are served locally and no internet connection is needed:

| Download | Save as |
| --- | --- |
| https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css | `assets/css/bootstrap.min.css` |
| https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js | `assets/js/bootstrap.bundle.min.js` |
| https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css | `assets/css/bootstrap-icons.css` |
| bootstrap-icons `fonts/` folder (`.woff`, `.woff2`) | `assets/fonts/` |
| https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js | `assets/js/chart.umd.min.js` |

After copying `bootstrap-icons.css`, open it and make sure the `src:url(...)` paths
point to `../fonts/`.

Handy one-liner (run from the project root):

```bash
curl -Lo assets/css/bootstrap.min.css      https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css
curl -Lo assets/js/bootstrap.bundle.min.js https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js
curl -Lo assets/css/bootstrap-icons.css    https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css
curl -Lo assets/fonts/bootstrap-icons.woff2 https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/fonts/bootstrap-icons.woff2
curl -Lo assets/fonts/bootstrap-icons.woff  https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/fonts/bootstrap-icons.woff
curl -Lo assets/js/chart.umd.min.js        https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js
```

---

## Daily workflow

1. **Walk-In** → search the patient (or register a new one in the same screen) →
   tick the required tests → apply discount and collect payment → print the receipt.
2. **Laboratory → Test Orders** → open the order → **Enter results**.
   Numeric values are compared with the reference range for the patient's gender/age
   and flagged **Low / Normal / High** automatically (you can override any flag).
3. Back on the order, click **Generate report** → open the report → add remarks and
   set it to **Final** → **Print** (A4 letterhead) or **Share on WhatsApp**.
4. **Billing → Payments** to collect any remaining balance.
5. **Analytics** for revenue/expense insight, **Backup** at the end of the day.

### PDF &amp; WhatsApp

No PDF library is required. Every printable screen (receipt, invoice, report,
prescription) has a **Print** button that opens the browser print dialog — choose
*Save as PDF* to produce a file. The **WhatsApp** button opens `wa.me` with a
pre-filled message; attach the PDF you just saved before sending.

---

## Folder structure

```
hospital-management-system/
├── index.php                 # entry point (redirects to dashboard/login)
├── login.php  logout.php     # authentication
├── dashboard.php             # main dashboard
├── install.php               # installation wizard
├── preview.html              # standalone HTML preview of every screen (demo data)
├── config/
│   ├── config.php            # paths, DB connection (PDO), BASE_URL
│   ├── db_config.php         # created by the installer (not in git)
│   └── session.php           # session bootstrap + login guard
├── includes/
│   ├── header.php  sidebar.php  footer.php
│   └── functions.php         # ID generators, flash, CSRF, formatting, flags...
├── modules/
│   ├── patients/  walkin/  doctors/  appointments/
│   ├── tests/  orders/  reports/
│   ├── billing/  expenses/  prescriptions/
│   ├── inventory/  suppliers/
│   └── analytics/  backup/  settings/
├── ajax/                     # patient search, test price, dashboard stats (JSON)
├── assets/css  assets/js  assets/fonts
├── uploads/logos  uploads/reports  uploads/backups
└── database/
    ├── hospital.sql          # schema + default admin, settings, 7 categories, 25 tests
    └── demo_data.sql         # optional sample data
```

---

## Auto-generated identifiers

| Entity | Format | Example |
| --- | --- | --- |
| Patient | `PAT-YYYY-00001` | `PAT-2025-00042` |
| Visit | `VIS-YYYYMMDD-0001` | `VIS-20250131-0003` |
| Test order | `ORD-YYYYMMDD-0001` | `ORD-20250131-0007` |
| Report | `RPT-YYYYMMDD-0001` | `RPT-20250131-0002` |
| Invoice | `INV-YYYYMMDD-0001` | `INV-20250131-0009` |
| Expense | `EXP-YYYYMM-0001` | `EXP-202501-0011` |
| Inventory item | `ITM-00001` | `ITM-00023` |
| Test | `T-0001` | `T-0026` |

Prefixes for patients, orders, reports and invoices can be changed in **Settings**.

---

## Backup &amp; restore

* **Backup → Create backup now** writes a full `.sql` dump (structure + data) to
  `uploads/backups/` using pure PHP — `mysqldump` is *not* required.
* Download or delete any backup from the same screen.
* **Restore** accepts an uploaded `.sql` file or one of the stored backups. A safety
  backup of the current database is taken automatically before the restore runs.
* Keep a copy of `uploads/` (logos) together with your SQL dumps.

---

## Troubleshooting

| Problem | Fix |
| --- | --- |
| Redirected to `install.php` every time | The database is unreachable — check credentials in `config/db_config.php` and that MySQL is running |
| `Access denied for user 'root'@'localhost'` | Set the correct MySQL password in `config/db_config.php` |
| Blank page / HTTP 500 | Enable `display_errors` in `php.ini` and check the Apache error log |
| Icons or layout missing | You are offline and the CDN is unreachable — follow *Full offline mode* above |
| Logo or backup cannot be saved | Give the web server write permission on `uploads/` (`chmod -R 775 uploads` on Linux) |
| Reports print with browser headers/footers | In the print dialog turn off *Headers and footers* and set margins to *Default* |

---

## Security notes

* Passwords are stored with `password_hash()` (bcrypt).
* Every form is protected with a per-session CSRF token.
* All queries use PDO prepared statements; output is escaped with `htmlspecialchars()`.
* Delete `install.php` after installation and change the default password.
* The system is intended for a **local, offline network**. Do not expose it directly
  to the internet without adding HTTPS and a hardened server configuration.

---

## License

Provided as-is for use in clinics, laboratories and small hospitals. Adapt it freely
to your requirements.
