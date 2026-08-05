#!/bin/bash
# Run this AFTER DNS is set + certbot cert is obtained
# Usage: bash activate_global_ssl.sh

set -e

DOMAIN="global.esahlan.com"

echo "==> Getting SSL cert..."
certbot certonly --nginx -d "$DOMAIN" \
  --non-interactive --agree-tos \
  --email mentorkaafi@gmail.com

echo "==> Activating full SSL Nginx config..."
cp /tmp/global.ssl.nginx /etc/nginx/sites-available/$DOMAIN
nginx -t && nginx -s reload

echo "✅ global.esahlan.com is now live with HTTPS!"
echo "   Deploy Flutter web via GitHub Actions (push to main)"
echo "   Or manually: rsync build/web/ root@168.144.117.91:/var/www/esahlan/global/"
