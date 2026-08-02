# eSahlan — System Architecture

## High-Level Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                              │
│                                                             │
│  ┌──────────────────┐  ┌───────────┐  ┌─────────────────┐  │
│  │  Customer App    │  │ Driver App│  │  Vendor App     │  │
│  │  (Flutter/Dart)  │  │ (Flutter) │  │  (Flutter)      │  │
│  │  Android + iOS   │  │           │  │                 │  │
│  └────────┬─────────┘  └─────┬─────┘  └────────┬────────┘  │
└───────────┼──────────────────┼─────────────────┼────────────┘
            │ HTTPS REST       │ HTTPS REST       │
            │ WebSocket (WSS)  │                  │
            ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────┐
│                    API LAYER (Nginx)                         │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              Laravel 11 Application                  │   │
│  │                                                      │   │
│  │  REST API (/api/v1/*)  │  Admin Panel (/admin/*)     │   │
│  │  Sanctum Auth          │  Session Auth               │   │
│  │                        │                             │   │
│  │  Controllers → Services → Models                     │   │
│  │                                                      │   │
│  │  FeedRankingService  VideoProcessingService          │   │
│  │  WaafiPayService     FcmService                      │   │
│  │  RealtimeService     ContentModerationService        │   │
│  └──────────┬──────────────────────────┬───────────────┘   │
│             │                          │                    │
│  ┌──────────▼──────────┐  ┌────────────▼───────────────┐   │
│  │  Laravel Reverb     │  │  Queue Workers (Supervisor) │   │
│  │  (WebSocket Server) │  │                            │   │
│  │  Port 8080          │  │  - TranscodeVideoJob        │   │
│  │                     │  │  - ProcessStoryVideoJob     │   │
│  └──────────────────────┘  │  - UpdateUserInterestsJob  │   │
│                            │  - FcmNotificationJob      │   │
│                            └────────────────────────────┘   │
└───────────────────────────────────────────────────────────-─┘
            │                          │
            ▼                          ▼
┌───────────────────────┐  ┌───────────────────────────────┐
│      DATA LAYER       │  │       EXTERNAL SERVICES       │
│                       │  │                               │
│  MySQL (primary DB)   │  │  Firebase (FCM push notifs)   │
│  Redis (cache+queue)  │  │  WaafiPay (local payments)    │
│  Storage (media files)│  │  Google Maps API              │
│                       │  │  LiveKit (WebRTC calls)       │
└───────────────────────┘  └───────────────────────────────┘
```

---

## Flutter App Architecture

```
┌─────────────────────────────────────────────────┐
│  Presentation Layer                             │
│  ┌────────────────┐  ┌────────────────────────┐ │
│  │  Screens       │  │  Widgets               │ │
│  │  (UI + State)  │  │  (Reusable components) │ │
│  └───────┬────────┘  └────────────────────────┘ │
│          │ watch / read                          │
│  ┌───────▼────────────────────────────────────┐ │
│  │  Riverpod Providers                        │ │
│  │  (StateNotifier, AsyncNotifier,            │ │
│  │   FutureProvider, StreamProvider)          │ │
│  └───────┬────────────────────────────────────┘ │
│          │ call                                  │
│  ┌───────▼────────────────────────────────────┐ │
│  │  Data Layer                                │ │
│  │  ┌─────────────────┐  ┌─────────────────┐ │ │
│  │  │  Repositories   │  │  Models         │ │ │
│  │  │  (API calls via │  │  (Dart classes) │ │ │
│  │  │   Dio)          │  │                 │ │ │
│  │  └────────┬────────┘  └─────────────────┘ │ │
│  └───────────┼──────────────────────────────┘ │
└──────────────┼────────────────────────────────┘
               │
  ┌────────────▼──────────────────────────────────┐
  │  Core Services                                │
  │  ApiClient (Dio)  │  RealtimeClient (WS)      │
  │  FirebaseService  │  VideoPool (LRU cache)    │
  └───────────────────────────────────────────────┘
```

---

## Video Pipeline

```
User uploads video
        │
        ▼
POST /community/posts (multipart)
        │
        ▼
CommunityPostController
  - Saves raw file to storage
  - Creates DB record (video_ready = false)
  - Dispatches TranscodeVideoJob → Redis queue
        │
        ▼
[Background Queue Worker]
TranscodeVideoJob
  - FFmpeg → HLS segments + master.m3u8
  - FFmpeg → optimized MP4 (mobile fallback)
  - FFmpeg → thumbnail (first frame)
  - Updates DB: hls_url, mp4_url, thumbnail
  - Sets video_ready = true
  - Broadcasts: private-user.{id} → post.media_ready
        │
        ▼
Flutter app receives WebSocket event
  - Updates post card with real video
  - VideoPool preloads HLS stream
```

---

## Feed Algorithm

```
Request: GET /community/feed?page=1
        │
        ▼
FeedRankingService::getFeed($userId, $page)
        │
        ├── Check Redis: feed:v2:{userId}:p{page}
        │   └── Cache HIT (TTL 120s) → return cached
        │
        └── Cache MISS:
              │
              ▼
         Fetch 5 pools (parallel queries):
         ┌──────────────────────────────┐
         │  Following    (40% of 30)    │ posts from followed users
         │  Recommended  (30% of 30)    │ creators user interacted with
         │  Trending     (15% of 30)    │ high velocity, < 3 days
         │  New Creators (10% of 30)    │ < 100 followers, < 2 days
         │  Random        (5% of 30)    │ any public post, < 7 days
         └──────────────────────────────┘
              │
              ▼
         scorePost() per post:
           score = relationship_bonus
                 + log1p(engagement) × 2
                 + log1p(velocity) × 3
                 + interest_match
                 + viral_score × 2
           score *= time_decay (half-life: 12h)
           score *= 1.15 (video/reel bonus)
           score *= 0.1 (if seen < 24h ago)
              │
              ▼
         applyDiversity():
           - max 2 posts per creator per page
           - max 3 same hashtag per page
           - max 2 consecutive same content type
              │
              ▼
         Cache result in Redis (TTL 120s)
         Return 30 posts
```

---

## Real-time Events

```
Event Source → Laravel Reverb → Flutter Client
```

| Event | Channel | Trigger |
|-------|---------|---------|
| `chat.inbox_update` | `private-user.{id}` | New DM received |
| `new_message` | `private-chat.{id}` | Message in open chat |
| `typing` | `private-chat.{id}` | User is typing |
| `post.media_ready` | `private-user.{id}` | Video transcoding done |
| `feed.new_post` | `community.feed` | New public post |
| `member_added` | `presence-chat-presence.{id}` | User comes online |

---

## Database Schema Map

```
Users & Auth
  users, personal_access_tokens, password_reset_tokens, otp_codes

Community
  community_profiles, community_posts, community_post_media,
  community_post_reactions, community_post_saves, community_post_shares,
  community_comments, community_follows, community_stories,
  community_story_views, community_chats, community_messages,
  community_notifications, community_hashtags, community_hashtag_posts,
  community_ads, community_business_pages, community_groups,
  community_reports, community_highlights

Feed Intelligence
  feed_seen_posts, feed_interactions, user_interests, post_scores

eMarry
  emarry_profiles, emarry_interests, emarry_passes

eRent
  properties, house_requests, viewings, property_offers,
  erent_agents, agent_analytics

E-Commerce (shared)
  vendors, products, orders, order_items, carts, cart_items,
  categories, addresses, reviews, coupons

Payments
  wallet_transactions, payment_transactions, mobile_pay_accounts

Live Streaming
  live_rooms, live_gifts, live_coins, live_battles,
  live_guests, live_subscriptions

Gamification
  user_points, user_tiers, achievements, affiliate_links,
  affiliate_conversions

Crypto
  crypto_wallets, crypto_transactions, p2p_listings

System
  settings, banners, ads, districts, email_templates
```

---

## Infrastructure

```
DigitalOcean VPS
  ├── Nginx (web server + reverse proxy)
  │   ├── :80/:443 → PHP-FPM (Laravel app)
  │   └── :8080 → Laravel Reverb (WebSocket)
  │
  ├── PHP-FPM 8.3
  │   └── /var/www/esahlan/backend/backend
  │
  ├── MySQL 8 (primary database)
  ├── Redis 7 (cache + queue backend)
  │
  ├── Supervisor
  │   ├── esahlan-worker (queue:work default)
  │   ├── esahlan-transcoder (queue:work transcoding)
  │   └── esahlan-reverb (reverb:start)
  │
  └── GitHub Actions CI/CD
      └── On push to main:
          git pull → migrate → config:cache → route:cache
```
