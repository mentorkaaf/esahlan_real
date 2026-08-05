#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Setup script for global.esahlan.com
# Run once on the VPS: bash setup_global_subdomain.sh
# ─────────────────────────────────────────────────────────────────────────────
set -e

DOMAIN="global.esahlan.com"
WEB_ROOT="/var/www/esahlan/global"
NGINX_CONF="/etc/nginx/sites-available/$DOMAIN"
NGINX_LINK="/etc/nginx/sites-enabled/$DOMAIN"

echo "==> [1/5] Creating web root..."
mkdir -p "$WEB_ROOT"
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"

# Placeholder index.html (will be overwritten by GitHub Actions deploy)
cat > "$WEB_ROOT/index.html" <<'EOF'
<!DOCTYPE html>
<html><head><title>eSahlan Global Store — Coming Soon</title></head>
<body style="font-family:sans-serif;text-align:center;padding:60px">
  <h1>🌍 eSahlan Global Store</h1>
  <p>Deploying... check back in a few minutes.</p>
</body></html>
EOF

echo "==> [2/5] Installing Nginx config..."
cat > "$NGINX_CONF" <<'NGINXEOF'
server {
    listen 80;
    server_name global.esahlan.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name global.esahlan.com;

    root /var/www/esahlan/global;
    index index.html;

    ssl_certificate     /etc/letsencrypt/live/global.esahlan.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/global.esahlan.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/javascript application/javascript
               application/json application/wasm font/woff2 font/woff
               image/svg+xml application/xml;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location ^~ /seo/ {
        proxy_pass https://api.esahlan.com;
        proxy_set_header Host api.esahlan.com;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_ssl_server_name on;
    }

    location = /sitemap.xml {
        proxy_pass https://api.esahlan.com/sitemap-global.xml;
        proxy_set_header Host api.esahlan.com;
        proxy_ssl_server_name on;
    }

    location = /robots.txt {
        add_header Content-Type text/plain;
        return 200 "User-agent: *\nAllow: /\nSitemap: https://global.esahlan.com/sitemap.xml\n";
    }

    location = /flutter_service_worker.js {
        expires off;
        add_header Cache-Control "no-store, no-cache, must-revalidate";
    }

    location ~* \.(js|css|wasm|png|jpg|jpeg|ico|woff2|woff|ttf|otf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        gzip_static on;
    }

    location ~* \.(json)$ {
        expires 1h;
        add_header Cache-Control "public";
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
NGINXEOF

echo "==> [3/5] Enabling Nginx site..."
ln -sf "$NGINX_CONF" "$NGINX_LINK"
nginx -t && echo "Nginx config OK"

echo "==> [4/5] Obtaining SSL certificate..."
# Temporarily serve on port 80 for certbot HTTP challenge
# The 80→443 redirect above blocks certbot if 443 SSL certs don't exist yet
# Use certbot standalone mode first

# Comment out the 443 block temporarily for initial cert issuance
systemctl stop nginx

certbot certonly --standalone \
  -d "$DOMAIN" \
  --non-interactive \
  --agree-tos \
  --email mentorkaafi@gmail.com \
  --expand

systemctl start nginx

echo "==> [5/5] Reloading Nginx..."
nginx -t && systemctl reload nginx

echo ""
echo "✅ global.esahlan.com setup complete!"
echo "   Web root: $WEB_ROOT"
echo "   Nginx: $NGINX_CONF"
echo "   SSL: /etc/letsencrypt/live/$DOMAIN/"
echo ""
echo "Next steps:"
echo "  1. Add DNS A record: global.esahlan.com → 168.144.117.91"
echo "  2. Push to main branch → GitHub Actions deploys Flutter web"
echo "  3. Test: https://global.esahlan.com"
