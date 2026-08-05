# ── global.esahlan.com — eSahlan Global Store (Flutter PWA) ─────────────────
# Flutter web files: /var/www/esahlan/global/
# SEO/Sitemap: proxied to api.esahlan.com (Laravel backend)

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

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/javascript application/javascript
               application/json application/wasm font/woff2 font/woff
               image/svg+xml application/xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    # ── SEO pages — proxy to Laravel backend ─────────────────────────────────
    location ^~ /seo/ {
        proxy_pass https://api.esahlan.com;
        proxy_set_header Host api.esahlan.com;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_ssl_server_name on;
    }

    # Sitemap XML — proxy to Laravel
    location = /sitemap.xml {
        proxy_pass https://api.esahlan.com/sitemap-global.xml;
        proxy_set_header Host api.esahlan.com;
        proxy_ssl_server_name on;
    }
    location = /sitemap-global.xml {
        proxy_pass https://api.esahlan.com/sitemap-global.xml;
        proxy_set_header Host api.esahlan.com;
        proxy_ssl_server_name on;
    }

    # robots.txt
    location = /robots.txt {
        add_header Content-Type text/plain;
        return 200 "User-agent: *\nAllow: /\nSitemap: https://global.esahlan.com/sitemap.xml\n";
    }

    # Service worker — never cache
    location = /flutter_service_worker.js {
        expires off;
        add_header Cache-Control "no-store, no-cache, must-revalidate";
    }

    # Hashed static assets — long-term cache
    location ~* \.(js|css|wasm|png|jpg|jpeg|ico|woff2|woff|ttf|otf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        gzip_static on;
    }

    # JSON manifests — short cache
    location ~* \.(json)$ {
        expires 1h;
        add_header Cache-Control "public";
    }

    # Flutter SPA routing — everything else → index.html
    location / {
        try_files $uri $uri/ /index.html;
    }
}
