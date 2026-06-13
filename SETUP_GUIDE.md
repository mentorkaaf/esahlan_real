# eSahlan Backend — Local Testing Guide

## Prerequisites: Install Laragon
Download: https://laragon.dev/download/ → Laragon Full (PHP 8.2 + MySQL + Composer)

---

## STEP 1: Start Laragon
- Open Laragon
- Click **"Start All"** (starts Apache/Nginx + MySQL)
- Laragon tray icon → right-click → **MySQL → Open HeidiSQL** (or phpMyAdmin)

## STEP 2: Create Database
In HeidiSQL or phpMyAdmin, run:
```sql
CREATE DATABASE esahlan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## STEP 3: Open Terminal in Project
In Laragon → right-click tray → **"Terminal"**
Then navigate to project:
```bash
cd C:\Users\CITYSIGN\Desktop\esahlan-project\backend
```

## STEP 4: Install Dependencies
```bash
composer install
```
(this downloads all Laravel packages — takes 2-3 minutes)

## STEP 5: Generate App Key
```bash
php artisan key:generate
```

## STEP 6: Run Migrations + Seeders
```bash
php artisan migrate --seed
```
Expected output:
- ✓ 9 migrations run
- ✓ 11 seeders complete
- Admin: admin@esahlan.com / Admin@eSahlan2024!

## STEP 7: Create Storage Link
```bash
php artisan storage:link
```

## STEP 8: Start Development Server
```bash
php artisan serve
```
Server runs at: http://localhost:8000

---

## Testing URLs

### Admin Panel
http://localhost:8000/admin/login
- Email: admin@esahlan.com
- Password: Admin@eSahlan2024!

### API Base URL
http://localhost:8000/api/v1/

### Quick API Tests (use Postman or browser):

**App Info / Health:**
GET http://localhost:8000/up

**Modules List:**
GET http://localhost:8000/api/v1/modules

**Register Customer:**
POST http://localhost:8000/api/v1/auth/register
Body (JSON):
{
  "name": "Test User",
  "phone": "+252617123456",
  "password": "Test@1234",
  "password_confirmation": "Test@1234",
  "referral_code": ""
}

**Login:**
POST http://localhost:8000/api/v1/auth/login
Body:
{
  "phone": "+252617123456",
  "password": "Test@1234"
}

**Home Screen Data:**
GET http://localhost:8000/api/v1/home
Headers: Authorization: Bearer {token}

---

## Testing with Postman
1. Import collection: Create new collection "eSahlan API"
2. Set base URL variable: {{base_url}} = http://localhost:8000/api/v1
3. After login, copy token → set as Bearer token

---

## Common Issues & Fixes

### "No application encryption key"
Run: php artisan key:generate

### "SQLSTATE: Access denied for user 'root'"
In .env: DB_PASSWORD= (leave empty for Laragon default)

### "Class not found" errors
Run: composer dump-autoload

### "Storage not writable"
Run: chmod -R 775 storage bootstrap/cache
(On Windows, right-click folder → Properties → Security → Allow write)

### Migration error: "Table already exists"
Run: php artisan migrate:fresh --seed
(WARNING: deletes all data)

---

## Postman Collection — Key Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /auth/register | No | Register user |
| POST | /auth/send-otp | No | Send OTP |
| POST | /auth/verify-otp | No | Verify phone |
| POST | /auth/login | No | Login |
| GET | /home | Bearer | Home screen |
| GET | /modules | No | List modules |
| GET | /districts | No | List districts |
| GET | /vendors | Bearer | Vendor list |
| POST | /cart/add | Bearer | Add to cart |
| POST | /orders | Bearer | Place order |
| GET | /orders | Bearer | My orders |
| GET | /wallet | Bearer | Wallet info |
| POST | /vendor/auth/register | No | Vendor register |
| GET | /vendor/dashboard | Bearer | Vendor stats |
| GET | /delivery/dashboard | Bearer | Rider stats |
| POST | /delivery/location | Bearer | Update location |
