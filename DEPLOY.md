# Deploy: globalvisionersint.com/education-system

**Server:** Oracle Cloud VM · Ubuntu 24.04 · **Nginx 1.24** · PHP 8.3-FPM
(`/run/php/php8.3-fpm.sock`) · MySQL 8.0 · Composer · git · no Node · 1 GB RAM.

The existing MLM Laravel site (`/var/www/mlm/public`) stays at
`globalvisionersint.com/` untouched. This app lives in `/var/www/education-system`
and is added to the same Nginx site with one `location ^~ /education-system/` block —
the same idea as the existing `/phpmyadmin` block. Separate folder, database,
`.env` and cookies.

This exact Nginx block was tested locally (Nginx + PHP 8.3, with a dummy "old site"
beside it): routing, CSS/JS, login, reports, CSV export, and that `.env`/dotfiles
are not reachable.

---

## 1. Get the code onto the server

```bash
cd /var/www
sudo git clone https://github.com/Tayyabshahzad/education-system.git
sudo chown -R ubuntu:www-data education-system
cd education-system
```

Private repo: when git asks for a password, paste a GitHub personal access token
(GitHub → Settings → Developer settings → Tokens), not your GitHub password.

The compiled CSS/JS (`public/build`) is in the repo, so the server does not need
Node. After changing styles locally, run `npm run build` and commit `public/build`.

## 2. PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

## 3. Database

```bash
sudo mysql
```
```sql
CREATE DATABASE education_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'education_user'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON education_db.* TO 'education_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

(If MySQL asks for a root password instead, use `sudo mysql -u root -p`.)

## 4. `.env`

```bash
cp .env.example .env
nano .env
```

Set these (leave the rest as in `.env.example`):

```dotenv
APP_NAME="Global Visioner Education"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Karachi
APP_URL=https://globalvisionersint.com/education-system

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=education_db
DB_USERNAME=education_user
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD

# Keep this app's login/CSRF cookies separate from the main site on the same domain.
SESSION_DRIVER=database
SESSION_COOKIE=gve_session
SESSION_PATH=/education-system
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
# Nothing in this app is queued, so no queue worker is needed on the small server.
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_LEVEL=warning

# Password-reset emails (any SMTP provider)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@globalvisionersint.com"
MAIL_FROM_NAME="${APP_NAME}"
```

`APP_ENV=production` matters: demo accounts (password `password`) are only
created when it is `local`. `SESSION_PATH` matters: without it, logging in to one
of the two Laravel apps can break the other's session ("419 Page Expired").

## 5. Set up the app

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force            # creates roles & permissions (no demo data in production)
php artisan app:create-super-admin     # asks name, email, password
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
php artisan config:cache
php artisan view:cache
```

> **Do not run `php artisan route:cache` on this server.** With cached routes,
> Laravel strips the trailing slash from `/education-system/` before matching, which
> breaks the sub-folder base URL and the app's home page returns
> "405 Method Not Allowed". Without the route cache it works; the speed difference
> is negligible for this app. If it was run by mistake: `php artisan route:clear`.
>
> Run `artisan` commands as `ubuntu`, never with `sudo` — root-owned files in
> `storage/` or `.env` cause "Permission denied" / 500 errors.

Uploaded screenshots and avatars are stored in `storage/app/private` (not public)
and served through the app, so no `storage:link` is needed.

## 6. Nginx

Back up the site config first, then open it:

```bash
sudo cp /etc/nginx/sites-available/globalvisionersint ~/globalvisionersint.nginx.backup
sudo nano /etc/nginx/sites-available/globalvisionersint
```

Find the `server { ... }` block with `listen 443 ssl;` and
`root /var/www/mlm/public;`. Paste this **inside it** (for example right above the
existing `location /phpmyadmin` block). Do not change anything else.

```nginx
    # ---------- Education System at /education-system ----------
    location = /education-system {
        return 301 /education-system/;
    }

    location ^~ /education-system/ {
        alias /var/www/education-system/public/;
        index index.php;
        client_max_body_size 16M;
        try_files $uri $uri/ @education_system;

        location ~ /\.(?!well-known) { deny all; }

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $request_filename;
            fastcgi_param PHP_VALUE "upload_max_filesize=12M
post_max_size=16M";
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        }
    }

    location @education_system {
        rewrite ^ /education-system/index.php last;
    }
    # ------------------------------------------------------------
```

Notes on why it looks like this:
- `^~` stops the main site's `location ~ \.php$` from catching this app's requests.
- `alias` and the location both end with `/` — keep them that way (prevents path-traversal tricks).
- `client_max_body_size` / `PHP_VALUE` raise upload limits for this app only
  (the server's PHP default is `post_max_size = 8M`). The `PHP_VALUE` line break is intentional.

If the port 80 `server` block only redirects to HTTPS (`return 301 https://...`,
added by Certbot), leave it alone. If it serves the site itself, paste the same
block there too.

Test and reload (reload keeps the main site online):

```bash
sudo nginx -t                 # must say "syntax is ok" and "test is successful"
sudo systemctl reload nginx
```

If `nginx -t` fails, restore the backup and reload:
`sudo cp ~/globalvisionersint.nginx.backup /etc/nginx/sites-available/globalvisionersint && sudo systemctl reload nginx`.

## 7. Check it

- `https://globalvisionersint.com/` — old site still works.
- `https://globalvisionersint.com/education-system` — redirects to the login page, with styling.
- Log in with the super admin; copy the referral link; open it in a private window → register page.
- Upload a payment screenshot; approve it in Admin → Payments.
- Admin → Reports → Export CSV downloads.
- Log in to the old site and this app in the same browser: neither logs the other out.

If something fails:

```bash
tail -n 50 /var/www/education-system/storage/logs/laravel.log
sudo tail -n 50 /var/log/nginx/error.log
sudo tail -n 50 /var/log/php8.3-fpm.log
```

---

## Updating later

On your PC: make changes, `npm run build` if styles changed, commit, push. Then on the server:

```bash
cd /var/www/education-system
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan route:clear && php artisan config:cache && php artisan view:cache   # never route:cache (see step 5)
php artisan up
```

---

## Server notes (from the checks on 14 Nov 2026)

- Only ~1 GB RAM and swap already in use: don't build assets (`npm`) on the server.
  Uploaded images are capped at 30 megapixels so decoding stays around 120 MB.
- Ubuntu reported "System restart required" — reboot at a quiet time.
