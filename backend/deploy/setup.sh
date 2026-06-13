#!/bin/bash
# ============================================================
# eSahlan - Hostinger Setup Script
# Run this via Hostinger SSH Terminal after uploading files
# ============================================================

# ── 1. Go to project root ────────────────────────────────────
cd ~/esahlan_backend

echo "==> [1/7] Checking PHP version..."
php -v

echo "==> [2/7] Installing Composer dependencies (no dev)..."
composer install --optimize-autoloader --no-dev

echo "==> [3/7] Copying production .env..."
cp .env.production .env

echo "==> [4/7] Generating app key (if needed)..."
# Only run if APP_KEY is still the placeholder
php artisan key:generate --no-interaction

echo "==> [5/7] Running database migrations + seeders..."
php artisan migrate --seed --force

echo "==> [6/7] Setting storage permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "==> [7/7] Creating storage symlink in public_html..."
# public_html/storage → esahlan_backend/storage/app/public
ln -sfn ~/esahlan_backend/storage/app/public ~/public_html/storage

echo "==> [DONE] Optimizing Laravel for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "=============================="
echo " eSahlan is ready on Hostinger"
echo " Visit: https://yourdomain.com"
echo "=============================="
