# MedXpert

A healthcare web app for Bangladesh. Book doctors, find blood donors, order medicine, and request an ambulance.

Stack: PHP 8.1+, MySQL or MariaDB, plain JavaScript and CSS.

## Setup

1. Create a database and import `database.sql`.
2. Copy `config/config.example.php` to `config/config.local.php`.
3. Fill in your database host, user, password, name and port.
4. Point your web server at the project root.
5. Make `uploads/` writable by the web server.

You can also set the environment variables MX_DB_HOST, MX_DB_USER, MX_DB_PASS, MX_DB_NAME and MX_DB_PORT instead of using the file.

`config/config.local.php` is ignored by git. Never commit it.

## Admin account

- Email: admin@medxpert.com
- The seed password is not published. Set your own: generate a hash with `php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"` and update the `users` row for the admin.
- The seed users john and sarah cannot log in. Register new accounts instead.

## Upgrading an existing database

Run these files in order on a database that already has data:

- `migrations/001_add_doctor_verified.sql`
- `migrations/002_decode_html_entities.sql` (fixes names saved as `&amp;` and `&#039;`)
- `migrations/003_login_attempts.sql` (adds login rate limiting)
- `migrations/004_phase4_features.sql` (doctor schedules, prescriptions, notifications, account tokens, order tracking)
- `migrations/005_phase5_production.sql` (audit log and consent records)

Back up your data first.

## Security features

- Prepared statements for every query.
- CSRF token on every form and request that changes data.
- Logout and delete use POST only.
- Session ID is regenerated on login. Cookies are HttpOnly and SameSite.
- Uploads accept JPG, PNG and WebP up to 2 MB. Files get random names. Scripts cannot run in `uploads/`.
- Login limit: 5 failed tries per email and 20 per IP in 15 minutes.
- Passwords need 8 to 72 characters.
- Errors are logged, not shown to visitors.

## Email

Set `mail_mode` in `config/config.local.php`.

- `log` (default): each email is written to `logs/mail.log`. Use this on your computer. Open the file to copy a reset link.
- `mail`: sends with PHP `mail()`. Also set `app_url` (for example `https://medxpert.example.com`) and `mail_from`. Links in emails use `app_url`, never the request host.
- `off`: sends nothing. In-app notifications still work.

Set `mail_mode` to `smtp` and fill the `smtp_*` settings to send real email (see `docs/DEPLOY.md`). Use the Email Test page in the admin sidebar to check it. Emails go out for password reset, email confirmation, and the notifications for bookings, orders, status changes and ambulance requests.

## Features

- Doctor schedules: an admin sets working days, hours and minutes per appointment. Patients pick a free time. The server checks the time again when booking.
- Cart: a patient can order several medicines at once. Medicines marked as needing a prescription require a photo of the prescription. Only the patient and admins can open the photo, through `prescription.php`.
- Order tracking: each order shows Pending, Processing, Shipped and Delivered with times.
- Notifications: a bell in the menu lists updates.
- Password reset and email confirmation: links work once and expire. Reset links last 60 minutes. Anyone can ask for a new confirmation link at `/resend_verification.php`. It allows 3 requests per email each hour and gives the same reply for every address.
- Admin charts: orders and appointments for the last 14 days.
- Phone tab bar: a bottom bar with Home, Doctors, Pharmacy, Blood and Menu on screens up to 768 pixels wide.
- Bangla: the language button in the menu and on the login page switches the menu, dashboard, orders, appointments, notifications, profile and login text. Text drawn by JavaScript (the doctor, donor and medicine cards) and the admin screens stay in English. Add words to `lang/bn.php`.

## Production features

- Audit log: every admin change, and every time an admin opens a prescription photo, is recorded. Admins read it under Audit Log.
- Privacy: a privacy policy page, a required consent box at sign-up, and a prompt for existing users on their next login. Users can download their data and delete their account from the profile page.
- Security headers: a Content Security Policy that allows only this site, HSTS on HTTPS, and a catch-all error handler that logs and shows a plain message.
- Fonts and icons are served from this site. Nothing loads from Google or Cloudflare.
- Static files carry a version in the address and are cached for a year.

## Correctness rules

- One doctor cannot have two pending or confirmed appointments at the same time. You cannot book two appointments at the same time either.
- Past times cannot be booked. A time must be one of the doctor's slots.
- Users can cancel their own future appointments and their own pending or processing orders. A cancelled order returns its stock.
- A cancelled order cannot be reopened by an admin.

## Production checklist

- Use HTTPS.
- Use a database user with rights on this database only.
- Keep `config/` and `migrations/` closed to the web. The `.htaccess` files do this on Apache. Add equal rules on Nginx.

## Going live

Read `docs/DEPLOY.md`. It covers hosting, HTTPS, email, backups, monitoring and a launch checklist. Run `php scripts/preflight.php` before you share the site.

## Project layout

- `config/bootstrap.php` sets up errors, settings, session, database, CSRF, login guards, flash messages, rate limits and uploads. Every page loads it.
- `src/helpers.php` holds pure helpers: escaping, input cleaning, validation. It needs no database.
- `src/orders.php` holds order logic: place (several medicines), cancel, and the tracker steps.
- `src/schedule.php` holds doctor schedules and slots.
- `src/accounts.php` holds password reset and email confirmation tokens.
- `src/notifications.php`, `src/mail.php`, `src/i18n.php`, `src/charts.php` hold notifications, email, translation and charts.
- `src/audit.php`, `src/privacy.php`, `src/assets.php` hold the audit log, consent, data export and deletion, and asset versions.
- `scripts/` holds `backup.sh`, `restore.sh` and `preflight.php`. `docs/` holds the deploy guide.
- `health.php` is the address for uptime monitors.
- `src/appointments.php` holds booking logic: book and cancel.
- `api/` holds thin endpoints. Each one reads the request, calls a function in `src/`, and returns JSON.
- `admin/` holds the admin screens.
- `tests/` holds the tests.

Put new business rules in `src/` so the tests can reach them.

## Tests

Create an empty test database, import `database.sql` into it, then run:

    MX_DB_HOST=127.0.0.1 MX_DB_USER=root MX_DB_PASS=secret MX_DB_NAME=medxpert_test php tests/run.php

Never point the tests at your live database. The unit tests run without any database. The database tests are skipped when no connection works.

The tests also run on every push through GitHub Actions (`.github/workflows/ci.yml`).
