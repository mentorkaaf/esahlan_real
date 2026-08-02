# eSahlan — Super-App Platform

> **Everything You Need, Simplified.**
> A comprehensive super-app platform for the Somali market — combining social networking, e-commerce, delivery, payments, live streaming, dating, and real estate into a single ecosystem.

---

## Platform Overview

eSahlan is a full-stack, production-ready super-app built for the East African/Somali market. It is a direct competitor to platforms like Jumia, TikTok, Tinder, and Airbnb — all unified in a single app with local payment integration (WaafiPay).

### Key Metrics at a Glance

| Component | Technology | Status |
|-----------|-----------|--------|
| Backend API | Laravel 11 + PHP 8.3 | ✅ Production |
| Customer App | Flutter 3 (Android/iOS) | ✅ Production |
| Driver App | Flutter 3 | ✅ Beta |
| Vendor App | Flutter 3 | ✅ Beta |
| Admin Panel | Laravel Blade + Vanilla JS | ✅ Production |
| Real-time Engine | Laravel Reverb (WebSocket) + Redis | ✅ Production |
| Video Pipeline | FFmpeg + HLS Transcoding | ✅ Production |
| Push Notifications | Firebase Cloud Messaging | ✅ Production |
| Payment Gateway | WaafiPay (local Somali payment) | ✅ Production |
| Infrastructure | DigitalOcean VPS + Nginx + Supervisor | ✅ Production |

---

## Repository Structure

```
esahlan-project/
├── backend/              ← Laravel 11 — REST API + Admin Panel
│   ├── app/
│   │   ├── Http/Controllers/Api/     ← All API controllers
│   │   ├── Http/Controllers/Admin/   ← Admin panel controllers
│   │   ├── Models/                   ← Eloquent models
│   │   ├── Services/                 ← Business logic layer
│   │   └── Jobs/                     ← Queue jobs (video transcoding, etc.)
│   ├── database/migrations/          ← Full DB schema history
│   └── routes/
│       ├── api.php                   ← 200+ API endpoints
│       └── web.php                   ← Admin panel routes
│
├── customer_app/         ← Flutter customer-facing app
│   └── lib/
│       ├── core/         ← Shared services, theme, routing
│       └── features/     ← Feature modules (community, e-commerce, etc.)
│
├── driver_app/           ← Flutter delivery driver app
├── vendor_app/           ← Flutter vendor/merchant app
└── docs/                 ← Postman API collection
```

---

## Feature Modules

### 🌐 eSocial — Community & Social Network
A full-featured social platform comparable to Instagram + TikTok.

- **Feed** — Personalized algorithmic feed (posts, images, videos)
- **Reels** — Short-form vertical video with HLS streaming
- **Stories** — 24-hour ephemeral content (image, text, video)
- **Direct Messaging** — Real-time DMs with online presence, typing indicators
- **Follow System** — Follow/unfollow, mutual follows, close friends
- **Business Pages** — Brand/business profiles with verification
- **Live Streaming** — Multi-guest live rooms with virtual gifts, coins, battles
- **Podcast** — Full podcast platform (episodes, categories, library, comments)
- **Community Groups** — Public/private groups with moderation
- **Hashtags & Discovery** — Trending topics, search
- **Content Moderation** — Automated + manual moderation tools

### 🛍️ eFood — Food Delivery
- Restaurant listings with categories and menus
- Real-time order tracking
- Driver assignment and routing

### 🛒 eGrocery — Grocery Delivery
- Supermarket & store listings
- Cart management with real-time availability
- Scheduled deliveries

### 🏪 eShop — Multi-vendor Marketplace
- Multi-vendor product listings
- Cart, wishlist, checkout flow
- Vendor dashboard with analytics

### 📦 eParcel — Parcel Delivery
- Send and track parcels
- Real-time driver tracking
- Proof of delivery

### 🏠 eRent — Real Estate
- Property listings (buy/rent)
- AI-powered property recommendations
- Viewing scheduling system
- Agent management with performance analytics
- Offer/negotiation workflow

### 💱 eExchange — Currency Exchange
- Real-time exchange rates
- Multi-currency wallet

### 🎓 eLearning — Online Education
- Course listings and enrollment
- Video lessons

### 💎 eMarry — Matrimonial / Dating
- Tinder-style swipe card UI
- Mutual matching system
- Interest/pass system with 7-day cooldown
- Profile approval workflow (admin moderated)
- Photo upload (max 4 per profile)
- Matches inbox + chat integration

### 🚚 eMoving — Moving Services
- Booking moving services
- Driver assignment

### 🎫 eTicket — Event Tickets
- Event listings
- Ticket purchase and management

### 🪙 Crypto — Cryptocurrency
- Market data (live prices)
- Wallet management
- Buy/sell
- P2P trading

---

## Technical Architecture

### Backend (Laravel 11)

```
REST API → Sanctum Auth → Controllers → Services → Models → MySQL
                                                         ↓
                                              Redis Cache (feed, sessions)
                                                         ↓
                                         Laravel Reverb (WebSocket broadcast)
                                                         ↓
                                         Queue Worker → Jobs (FFmpeg, FCM, etc.)
```

**Key Services:**
| Service | Responsibility |
|---------|---------------|
| `FeedRankingService` | Algorithmic feed scoring with 5 content pools |
| `VideoProcessingService` | FFmpeg HLS transcoding + MP4 optimization |
| `RealtimeService` | WebSocket event broadcasting |
| `FcmService` | Firebase push notifications |
| `WaafiPayService` | Local payment gateway integration |
| `ContentModerationService` | Automated content moderation |

**Feed Algorithm** — 5-pool system with machine-learning-style scoring:
- Following (40%), Recommended (30%), Trending (15%), New Creators (10%), Random (5%)
- Scoring: engagement velocity, interest matching, relationship strength, viral score
- Redis-cached per user, refreshed every 2 minutes

### Flutter App Architecture

- **State Management:** Riverpod (providers, AsyncNotifier)
- **Navigation:** GoRouter
- **Networking:** Dio with interceptors
- **Real-time:** Custom WebSocket client (Pusher protocol over Reverb)
- **Video:** Custom VideoPool with 6-slot LRU cache + disk persistence
- **Local Storage:** Flutter Secure Storage + SharedPreferences

### Infrastructure

| Component | Technology |
|-----------|-----------|
| Server | DigitalOcean VPS |
| Web Server | Nginx + PHP-FPM |
| Database | MySQL |
| Cache/Queue | Redis |
| Queue Manager | Supervisor |
| WebSocket | Laravel Reverb |
| Video Processing | FFmpeg (background queue) |
| CDN/Storage | DigitalOcean Spaces / local storage |
| CI/CD | GitHub Actions (auto-deploy on push) |

---

## Database Schema Highlights

The platform has 100+ database tables covering:

- Users, authentication, OTP, sessions
- Community (posts, media, stories, chats, follows, reactions, hashtags)
- Feed (seen posts, interactions, user interests, viral scores)
- E-commerce (orders, carts, products, vendors, categories)
- Payments (transactions, wallets, WaafiPay records)
- Delivery (assignments, tracking, driver locations)
- Real estate (properties, viewings, offers, agents)
- Matrimonial (profiles, interests, matches, passes)
- Crypto (wallets, transactions, P2P trades)
- Live streaming (rooms, gifts, coins, battles)
- Gamification (points, tiers, achievements, affiliates)

---

## API Overview

**200+ REST endpoints** organized by module:

| Module | Base Path | Auth |
|--------|----------|------|
| Authentication | `/api/v1/auth/*` | Public |
| Community Feed | `/api/v1/community/*` | Bearer token |
| eMarry | `/api/v1/emarry/*` | Bearer token |
| eFood | `/api/v1/efood/*` | Bearer token |
| eShop | `/api/v1/eshop/*` | Bearer token |
| eRent | `/api/v1/erent/*` | Bearer token |
| Crypto | `/api/v1/crypto/*` | Bearer token |
| Live Streaming | `/api/v1/live/*` | Bearer token |
| Podcast | `/api/v1/podcast/*` | Bearer token |
| Payments | `/api/v1/payment/*` | Bearer token |
| Admin | `/admin/*` | Admin session |

Full API collection available in `/docs/` (Postman format).

---

## Getting Started

### Requirements

- PHP 8.2+
- Composer
- MySQL 8+
- Redis
- Node.js 18+ (for admin assets)
- FFmpeg (for video processing)
- Flutter 3.x (for mobile apps)

### Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Start queue worker
php artisan queue:work --queue=transcoding,default

# Start WebSocket server
php artisan reverb:start
```

### Flutter App Setup

```bash
cd customer_app
flutter pub get
flutter run
```

### Environment Variables

Key `.env` values to configure:

```env
APP_URL=https://your-domain.com
DB_DATABASE=esahlan
REDIS_HOST=127.0.0.1

# Firebase
FIREBASE_PROJECT_ID=...
FIREBASE_CREDENTIALS=...

# WaafiPay
WAAFI_API_USER=...
WAAFI_API_KEY=...
WAAFI_MERCHANT_ID=...

# Reverb WebSocket
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
```

---

## Security

- API authentication via Laravel Sanctum (Bearer tokens)
- OTP-based registration (no plain passwords for mobile)
- Rate limiting on auth endpoints
- Content moderation (automated + admin review)
- Admin-only routes protected by middleware
- File upload validation (type, size, content)

---

## Admin Panel

Full web-based admin dashboard at `/admin`:

- User management (ban, verify, view activity)
- Content moderation (post/story review, reports)
- Order management across all modules
- Vendor approval and management
- Driver management
- Community algorithm dashboard (top posts, active users, viral content)
- eMarry profile approval
- Payment transaction history
- eRent agent management
- Live streaming moderation

---

## Target Market

**Primary:** Somali diaspora and East African markets (Somalia, Ethiopia, Kenya, Djibouti)

**Addressable Market:**
- 10M+ Somali diaspora worldwide
- $2B+ annual remittance flows
- Rapidly growing mobile-first internet population
- Underserved by global tech platforms (TikTok, Uber, Airbnb)

---

## License

Proprietary. All rights reserved. Contact for acquisition inquiries.

---

*Built with Laravel 11, Flutter 3, Redis, WebSocket, FFmpeg, Firebase, and WaafiPay.*
