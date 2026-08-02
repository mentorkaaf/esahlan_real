# eSahlan Backend — Laravel 11 API

Production Laravel 11 REST API powering the eSahlan super-app platform.

## Tech Stack

| Component | Version |
|-----------|---------|
| PHP | 8.2+ |
| Laravel | 11.x |
| MySQL | 8.0 |
| Redis | 7.x |
| Laravel Reverb | WebSocket server |
| Supervisor | Queue manager |
| FFmpeg | Video processing |

## Directory Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── Auth/           ← OTP login, register, refresh
│   │   │   │   ├── Community/      ← Social feed, posts, stories, chat, eMarry
│   │   │   │   ├── Customer/       ← Orders, wallet, cart, address
│   │   │   │   ├── Delivery/       ← Driver assignment, tracking
│   │   │   │   ├── Live/           ← Live rooms, gifts, battles, VOD
│   │   │   │   ├── Modules/        ← eFood, eGrocery, eShop, eRent, etc.
│   │   │   │   ├── Payment/        ← WaafiPay integration
│   │   │   │   ├── Podcast/        ← Podcast platform
│   │   │   │   ├── Vendor/         ← Vendor dashboard API
│   │   │   │   └── Crypto/         ← Crypto market + wallet
│   │   │   └── Admin/              ← Admin panel web controllers
│   │   └── Middleware/
│   ├── Models/                     ← Eloquent models (100+ tables)
│   ├── Services/                   ← Business logic services
│   └── Jobs/                       ← Queue jobs
├── database/
│   ├── migrations/                 ← Full schema history (90+ migrations)
│   └── seeders/
└── routes/
    ├── api.php                     ← 200+ API endpoints
    └── web.php                     ← Admin panel routes
```

## Key Services

### FeedRankingService
Algorithmic personalized feed with 5 content pools:

```
Score = relationship_bonus
      + log1p(engagement) × 2
      + log1p(velocity) × 3        ← engagement / hours_old
      + interest_match              ← hashtag, content-type, creator signals
      + viral_score × 2
score *= time_decay                 ← half-life: 12 hours
score *= 1.15 (video/reel boost)
```

Pool distribution per page (30 posts):
- **Following** 40% — posts from users the viewer follows
- **Recommended** 30% — creators the user interacted with
- **Trending** 15% — high velocity posts < 3 days old
- **New Creators** 10% — users with < 100 followers
- **Random** 5% — serendipity

Redis TTL: 120s (page 1 always fresh, pages 2+ pre-computed every 5 min).

### VideoProcessingService
FFmpeg pipeline for uploaded videos:
- HLS multi-bitrate (master.m3u8 + segments)
- Optimized MP4 fallback
- Auto-generated thumbnail
- Background queue (`transcoding` queue, Supervisor managed)

### WaafiPayService
Local Somali payment gateway integration:
- Purchase (debit customer)
- Refund
- Webhook validation
- Transaction logging

### RealtimeService
WebSocket event broadcasting via Laravel Reverb:
- `private-user.{id}` — DM inbox, media ready notifications
- `private-chat.{id}` — chat messages, typing
- `community.feed` — new post broadcasts

## API Authentication

All API routes (except auth) require `Authorization: Bearer {token}`.

Tokens are issued via OTP flow:
```
POST /api/v1/auth/send-otp     → send SMS OTP
POST /api/v1/auth/verify-otp   → verify + get token
POST /api/v1/auth/refresh      → refresh token
```

## Deployment

### Auto-deploy (GitHub Actions)
Push to `main` branch triggers automatic deployment:
```
git push origin main
```

### Manual deploy
```bash
ssh root@168.144.117.91
cd /var/www/esahlan/backend/backend
git pull origin main
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

### After config changes
```bash
php artisan config:cache
php artisan route:cache
```

### After queue job changes
```bash
php artisan queue:restart
supervisorctl restart all
```

## Queue Channels

| Queue | Worker | Purpose |
|-------|--------|---------|
| `transcoding` | Dedicated | FFmpeg video processing |
| `default` | General | FCM notifications, emails, interest scoring |

## Environment Variables

```env
APP_URL=https://api.esahlan.com
DB_HOST=127.0.0.1
DB_DATABASE=esahlan
REDIS_HOST=127.0.0.1

# Firebase (FCM push notifications)
FIREBASE_PROJECT_ID=esahlan
FIREBASE_CREDENTIALS=/path/to/service-account.json

# WaafiPay (local payment gateway)
WAAFI_API_USER=
WAAFI_API_KEY=
WAAFI_MERCHANT_ID=

# Reverb WebSocket
REVERB_APP_ID=esahlan
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# FFmpeg
FFMPEG_BINARY=/usr/bin/ffmpeg
FFPROBE_BINARY=/usr/bin/ffprobe
```

## Admin Panel

Accessible at `/admin` — protected by admin role middleware.

Sections:
- Dashboard (platform metrics)
- Users (management, ban, verify)
- Community (post/story moderation, reports)
- Feed Algorithm (top posts, active users, viral content dashboard)
- Orders (all modules: food, grocery, parcel, moving)
- Vendors (approval, management, payout)
- Drivers (management, assignment)
- eMarry (profile approval workflow)
- eRent Agents (agent management, analytics)
- Payments (transaction history, WaafiPay logs)
- Live Streaming (room moderation)
- Settings (global app configuration)
