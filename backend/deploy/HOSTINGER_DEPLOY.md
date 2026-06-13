# eSahlan — Hostinger Deployment Guide

## Folder structure on Hostinger server

```
/home/u123456789/
├── public_html/              ← domain root (what visitors see)
│   ├── index.php             ← from deploy/hostinger_public_html/index.php
│   ├── .htaccess             ← from deploy/hostinger_public_html/.htaccess
│   └── storage/ (symlink)   ← created by setup.sh
└── esahlan_backend/          ← all other Laravel files
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env                  ← copy from .env.production (edit DB creds)
    └── ...
```

---

## Step 1 — Create MySQL Database on Hostinger

1. Login to Hostinger hPanel
2. Go to **Hosting → Manage → Databases → MySQL Databases**
3. Create a new database, e.g. `u123456789_esahlan`
4. Create a user and assign it to the database with **All Privileges**
5. Note down: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST`

---

## Step 2 — Upload Files

### Upload via File Manager or FTP (FileZilla):

**A) Upload backend files to `esahlan_backend/`**
- Upload everything EXCEPT the `public/` folder
- Upload to: `/home/u123456789/esahlan_backend/`

**B) Upload public files to `public_html/`**
- Upload `deploy/hostinger_public_html/index.php` → `public_html/index.php`
- Upload `deploy/hostinger_public_html/.htaccess` → `public_html/.htaccess`
- Upload `public/storage/` folder if it exists → `public_html/storage/`

---

## Step 3 — Configure .env

1. In `esahlan_backend/`, rename `.env.production` to `.env`
2. Edit `.env` and fill in your real values:

```env
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_esahlan
DB_USERNAME=u123456789_esahlan
DB_PASSWORD=YourRealPassword
```

---

## Step 4 (Easy) — Use the Installer Wizard (Recommended)

Instead of SSH, you can use the web installer:

1. Upload the `install/` folder to `public_html/install/`
2. Open: `https://yourdomain.com/install/`
3. Follow the 5-step wizard:
   - Step 1: Server requirements check
   - Step 2: Database credentials
   - Step 3: App URL + admin account
   - Step 4: One-click install (migrations + seeders)
   - Step 5: Done!
4. **Delete** `public_html/install/` folder after finishing

---

## Step 4 (Manual) — Run Setup via SSH Terminal

1. In hPanel go to **Advanced → SSH Access** and enable it
2. Connect: `ssh u123456789@yourdomain.com -p 65002`
3. Run the setup script:

```bash
chmod +x ~/esahlan_backend/deploy/setup.sh
bash ~/esahlan_backend/deploy/setup.sh
```

Or run commands manually:

```bash
cd ~/esahlan_backend

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Run migrations and seed the database
php artisan migrate --seed --force

# Fix permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Link storage to public_html
ln -sfn ~/esahlan_backend/storage/app/public ~/public_html/storage

# Cache configs for speed
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 5 — Verify

Open your browser:
- `https://yourdomain.com` → Should show the admin panel
- `https://yourdomain.com/api/modules` → Should return JSON

---

## Flutter App — Update API URL

In the customer app, change the base URL to your domain:

**File:** `customer_app/lib/core/network/api_client.dart` (or similar)
```dart
static const String baseUrl = 'https://yourdomain.com/api/';
```

In the admin app, update the same.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| 500 Error | Check `esahlan_backend/storage/logs/laravel.log` |
| 403 Forbidden | Make sure `.htaccess` is in `public_html/` |
| DB Connection refused | Check DB_HOST is `127.0.0.1` not `localhost` |
| Storage files not loading | Re-run the `ln -sfn` symlink command |
| Composer missing | Run: `php -r "copy('https://getcomposer.org/installer', 'ci.php');" && php ci.php && php composer.phar install` |
