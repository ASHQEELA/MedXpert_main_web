# Going live with MedXpert

This guide covers hosting, HTTPS, email, backups, monitoring and the checks to run before you share the site. Follow it in order.

## 1. Choose a host

You need PHP 8.1 or newer, MySQL or MariaDB, and the ability to use HTTPS. Pick one:

- Shared hosting with cPanel. It is the simplest. Look for PHP 8.1 or newer, MySQL, a free certificate (Let's Encrypt or AutoSSL), cron jobs and email sending.
- A small virtual server (VPS). You install PHP, a web server and the database yourself. You control backups and logs. Choose this if you are comfortable with a command line.

A free host is fine for a demo. Do not use one for real patient data. Health information needs a host where you control backups, access and where the data lives.

## 2. Install

1. Upload the project files so the web root is the project folder.
2. Create a database and a database user that has rights on that database only. Do not use `root`.
3. Import `database.sql`. On an older install run the files in `migrations/` in number order instead.
4. Copy `config/config.example.php` to `config/config.local.php` and fill it in.
5. Make `uploads/` and `logs/` writable by the web server.
6. Log in as the admin and change the password on the Profile page.

## 3. HTTPS

1. Turn on the free certificate in your host panel.
2. Open the site with `https://` and check it loads.
3. In `.htaccess`, remove the `#` from the lines in the "Send visitors to HTTPS" block.
4. Set `app_url` in `config/config.local.php` to your `https://` address.

The site sends the HSTS header on HTTPS, so browsers keep using HTTPS.

If you use Nginx, `.htaccess` does nothing. Add equal rules to the server block:

- Deny `/config`, `/src`, `/lang`, `/migrations`, `/scripts`, `/docs`, `/tests`, `/logs`, `/backups` and `/uploads/prescriptions`.
- Stop PHP from running in `/uploads`.
- Send `Cache-Control: public, max-age=31536000, immutable` for `.css`, `.js` and `.woff2` files.

## 4. Email

Free hosts such as InfinityFree do not deliver mail sent with PHP `mail()`. Use an SMTP account instead. A Gmail account works.

Gmail setup:
- Turn on 2-step verification for the Gmail account.
- Open https://myaccount.google.com/apppasswords and create an App Password named MedXpert.
- Copy the 16 characters. Your normal Gmail password does not work.

Set these in `config/config.local.php`:

    'mail_mode'   => 'smtp',
    'mail_from'   => 'team.yourname@gmail.com',   // the same address as smtp_user
    'app_url'     => 'https://your-domain.com',   // no slash at the end
    'smtp_host'   => 'smtp.gmail.com',
    'smtp_port'   => 587,
    'smtp_secure' => 'tls',
    'smtp_user'   => 'team.yourname@gmail.com',
    'smtp_pass'   => 'abcdefghijklmnop',

Then log in as admin and open Email Test in the sidebar. Send a message to yourself. The page shows the exact reason when sending fails.

Common reasons:
- 535 Username and Password not accepted: the App Password is wrong, or 2-step verification is off.
- Could not connect: the host blocks outgoing SMTP. Try port 465 with `'smtp_secure' => 'ssl'`. If both fail, use a different host or a mail service with an HTTP API.
- app_url is empty: set it.
- The message lands in Spam: mark it as not spam once. Gmail allows about 500 messages a day.

On a host with working mail and your own domain, use `'mail_mode' => 'mail'` and add the SPF and DKIM records your host shows you.

## 5. Backups

`scripts/backup.sh` saves the database and the uploads folder to `backups/`. It keeps the 14 newest by default.

Run it every night from cron:

    30 2 * * * /path/to/medxpert/scripts/backup.sh >> /path/to/medxpert/logs/backup.log 2>&1

Rules that keep backups useful:

- Copy `backups/` to a second place every day, such as another server or cloud storage. A backup on the same disk is lost with that disk.
- Backups hold patient data. Keep the copies private and encrypted where you can.
- Test a restore. Create an empty test database and run `scripts/restore.sh backups/db-....sql.gz` with the test database in your settings.

## 6. Logs

PHP errors go to `logs/php-error.log`. Visitors never see error details. Read the file after each release and when someone reports a problem. Rotate it so it does not grow without end. On a VPS, add a `logrotate` rule for it.

Admin actions are recorded in the Audit Log screen of the admin area.

## 7. Monitoring

`/health.php` returns `{"status":"ok"}` with status 200 when the site, the database and the uploads folder work. It returns 503 when one fails. It starts no session and shows no private data.

Add it to a free uptime monitor such as UptimeRobot, Better Stack or Freshping:

- Address: `https://your-domain.com/health.php`
- Check every 5 minutes
- Alert by email or phone

## 8. Check before launch

Run this from the project folder:

    php scripts/preflight.php

Fix every FAIL. Read every WARN. The checker looks at the PHP version, the database user, the admin password, the email settings, writable folders and the protective `.htaccess` files.

Then test by hand:

- Register, accept the privacy policy, and log in.
- Reset a password with the email link.
- Book an appointment, place an order with a prescription photo, and cancel one.
- Open `https://your-domain.com/config/db.php`, `/backups/` and `/uploads/prescriptions/` in a browser. Each one must show an error page, not content.

## 9. Privacy policy

`privacy.php` is a starting text that matches what the site does. A lawyer who knows the rules for health data in Bangladesh should review it before launch. Set your support email in `config/site.php` so it shows on the policy and in the footer. When you change the policy, change `PRIVACY_VERSION` in `src/privacy.php`. Every user is then asked to accept it again.

## 10. Updates

1. Make a backup.
2. Upload the new files. Keep your `config/config.local.php`.
3. Run any new files in `migrations/`.
4. Run `php scripts/preflight.php`.
