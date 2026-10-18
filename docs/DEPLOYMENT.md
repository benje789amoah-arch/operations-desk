# Deploying Operations Desk

This guide follows Laravel's documented deployment conventions for a single Linux server running Nginx and PHP-FPM. Adapt the paths and service names to your host. **Never** serve the project root or expose `.env`; the web server's document root must be the `public/` directory.

> Status: these steps are documented and the build/caching commands were exercised locally. The application has not been deployed to a live server as part of this work.

---

## 1. Server requirements

| Component | Version / notes |
|---|---|
| OS | Any current Linux (Ubuntu 24.04 LTS assumed below) |
| PHP | 8.3 or newer, with FPM and extensions: `bcmath`, `ctype`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo`, `pdo_mysql` **or** `pdo_pgsql`, `tokenizer`, `xml`, `zip` |
| Composer | 2.x |
| Node.js | 20+ (only needed to build assets; can run in CI instead) |
| Database | MySQL 8.0+ / MariaDB 10.11+ **or** PostgreSQL 14+ |
| Web server | Nginx (or Apache with the document root set to `public/`) |
| TLS | A certificate for your domain (for example Let's Encrypt via certbot) |
| Mail | An SMTP relay. Password-reset and account-invitation emails depend on it. |

No queue worker is required: emails are sent synchronously. A cron entry for the scheduler **is** required (section 6).

---

## 2. Get the code and install dependencies

```bash
sudo mkdir -p /var/www/operations-desk
```

```bash
sudo chown $USER:www-data /var/www/operations-desk
```

```bash
git clone <your-repository-url> /var/www/operations-desk
```

```bash
cd /var/www/operations-desk
```

Production dependencies only, with an optimised autoloader:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

Build the front-end assets (or copy `public/build` from your CI pipeline):

```bash
npm ci
```

```bash
npm run build
```

---

## 3. Environment configuration

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Edit `.env`. The important production values:

```dotenv
APP_NAME="Operations Desk"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ops-desk.example.com
APP_FORCE_HTTPS=true
TRUSTED_PROXIES=            # proxy IPs (comma separated) or * if behind a load balancer

OPERATIONS_TIMEZONE=Africa/Accra
OPERATIONS_CARRY_OVER_DAYS=7
OPERATIONS_SEED_DEMO_DATA=false

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql         # or pgsql
DB_HOST=127.0.0.1
DB_PORT=3306                # 5432 for PostgreSQL
DB_DATABASE=operations_desk
DB_USERNAME=operations_desk
DB_PASSWORD=<strong secret>

SESSION_DRIVER=database
SESSION_LIFETIME=120        # minutes of inactivity before sign-out
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=<smtp host>
MAIL_PORT=587
MAIL_USERNAME=<smtp user>
MAIL_PASSWORD=<smtp secret>
MAIL_FROM_ADDRESS="operations-desk@example.com"
```

Rules:

- `APP_DEBUG` must be `false` in production. Stack traces are then never shown to users; the custom error pages are shown instead.
- Keep `.env` out of version control (it is in `.gitignore`) and readable only by the deploy user and the PHP-FPM group.
- Do not commit production credentials anywhere in the repository.

---

## 4. Database

Create the database and a dedicated user with rights only on that database. MySQL example:

```sql
CREATE DATABASE operations_desk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'operations_desk'@'localhost' IDENTIFIED BY '<strong secret>';
GRANT ALL PRIVILEGES ON operations_desk.* TO 'operations_desk'@'localhost';
```

Run the migrations:

```bash
php artisan migrate --force
```

Seed the reference categories. Demo data is **not** created outside local/testing unless `OPERATIONS_SEED_DEMO_DATA=true`:

```bash
php artisan db:seed --force
```

---

## 5. Safe administrator creation

Create the first administrator interactively. The password is typed at a hidden prompt, validated against the password policy, and never echoed or logged:

```bash
php artisan operations:create-admin
```

All other accounts are then created from **Personnel & access** in the application. New people receive an emailed link to set their own password, so no administrator ever handles a password.

---

## 6. Scheduler

Daily activity instances are created at 00:01 in `OPERATIONS_TIMEZONE` by the Laravel scheduler. Add one cron entry for the web user (`crontab -u www-data -e`):

```cron
* * * * * cd /var/www/operations-desk && php artisan schedule:run >> /dev/null 2>&1
```

Generation is idempotent, so retries or overlapping runs cannot duplicate instances. If the cron is ever missed, opening today's register or dashboard also creates any missing instances for today. To backfill a range explicitly:

```bash
php artisan operations:generate-instances --date=2026-10-18 --days=3
```

---

## 7. File permissions and storage

```bash
sudo chown -R $USER:www-data storage bootstrap/cache
```

```bash
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

```bash
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

```bash
chmod 640 .env
```

The application stores no uploaded files, so `php artisan storage:link` is not required.

---

## 8. Web server (Nginx) and HTTPS

```nginx
server {
    listen 80;
    server_name ops-desk.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ops-desk.example.com;
    root /var/www/operations-desk/public;          # public/ only, never the project root

    ssl_certificate     /etc/letsencrypt/live/ops-desk.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ops-desk.example.com/privkey.pem;

    index index.php;
    charset utf-8;
    client_max_body_size 2m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;                                   # blocks .env, .git and other dotfiles
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Obtain a certificate:

```bash
sudo certbot --nginx -d ops-desk.example.com
```

The application adds `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` and `Cross-Origin-Opener-Policy` headers, and `Strict-Transport-Security` on HTTPS requests. With `APP_FORCE_HTTPS=true`, every generated URL uses https. Behind a load balancer, set `TRUSTED_PROXIES` so HTTPS detection and the IP addresses in the audit log are correct.

---

## 9. Production caches

After every deployment:

```bash
php artisan optimize
```

This caches configuration, routes, events and views. If you change `.env` later, run `php artisan optimize:clear` and then `php artisan optimize` again.

Reload PHP-FPM so OPcache picks up the new code:

```bash
sudo systemctl reload php8.3-fpm
```

A typical zero-surprise deploy sequence:

```bash
php artisan down --retry=30
```

```bash
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
```

```bash
php artisan migrate --force
```

```bash
php artisan optimize && sudo systemctl reload php8.3-fpm
```

```bash
php artisan up
```

---

## 10. Logging and monitoring

- Use `LOG_CHANNEL=daily` (14 days retained by default) or ship logs to your central logging. Application errors are logged with full context; users only see the friendly error pages.
- `/up` is a health-check endpoint for load balancers and uptime monitors.
- Run `php artisan operations:verify-audit-chain` from cron or monitoring (for example daily) and alert on a non-zero exit code.
- Failed sign-ins and lockouts are recorded in the audit log under *Sign-in & security*.

---

## 11. Backup and restore

What to back up:

1. **The database.** This holds all operational history.
2. **`.env`**, stored securely and separately (it contains `APP_KEY` and credentials).

`storage/logs` is optional. There are no uploaded files.

Nightly MySQL backup, compressed, with 30-day retention (`/etc/cron.d/ops-desk-backup`):

```cron
30 1 * * * backup mysqldump --single-transaction --routines operations_desk | gzip > /backups/ops-desk-$(date +\%F).sql.gz && find /backups -name 'ops-desk-*.sql.gz' -mtime +30 -delete
```

For PostgreSQL, use `pg_dump -Fc operations_desk > /backups/ops-desk-$(date +%F).dump`.

Copy backups off the server (object storage or another site) and encrypt them at rest.

**Restore procedure**

1. Put the application in maintenance mode with `php artisan down`.
2. Restore the database: `gunzip -c backup.sql.gz | mysql operations_desk`, or `pg_restore -c -d operations_desk backup.dump`.
3. Ensure `.env` (in particular `APP_KEY`) matches the environment the backup came from.
4. Run `php artisan migrate --force` (only applies migrations newer than the backup), then `php artisan optimize`.
5. Run `php artisan operations:verify-audit-chain` to confirm the audit log is intact.
6. Bring the application back with `php artisan up`.

Test the restore procedure on a staging server at least quarterly.

---

## 12. Deployment verification checklist

- [ ] `https://` loads the sign-in page with a valid certificate, and `http://` redirects to https.
- [ ] `https://<host>/.env` and `https://<host>/.git/config` return 403/404.
- [ ] `APP_DEBUG=false`: visiting a missing URL shows the custom "We could not find that page", not a stack trace.
- [ ] `php artisan about` shows the production environment, cached config and routes, and the correct timezone settings.
- [ ] `/up` returns 200.
- [ ] The administrator created with `operations:create-admin` can sign in; five wrong passwords trigger the lockout message.
- [ ] Creating a personnel account sends the set-password email, and the link works.
- [ ] Creating an activity definition that is due today adds it to today's register immediately.
- [ ] Recording Done and Pending updates shows the correct name and local time; the handover centre lists the pending item.
- [ ] `crontab -l -u www-data` shows the scheduler entry, and `php artisan schedule:list` shows `operations:generate-instances` at 00:01.
- [ ] Next morning: today's register was populated by the scheduler.
- [ ] A report CSV export downloads and the export appears in the audit log.
- [ ] `php artisan operations:verify-audit-chain` reports the chain intact.
- [ ] A backup file was produced and a test restore succeeded on staging.
- [ ] Response headers include `Strict-Transport-Security`, `X-Frame-Options: DENY` and `X-Content-Type-Options: nosniff`.
