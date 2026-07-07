# eSahlan — Claude Development Guide

> **Akhri marka hore.** Dokumigani waa xeerarka iyo naqshada project-ga oo dhan. Mar walba ka bilow halkan ka hor intaadan wax beddelin.

---

## 1. Naqshada Project-ga (Architecture)

### Qaab guud
```
esahlan-project/
├── backend/          ← Laravel 11 API + Admin Panel (PHP)
├── customer_app/     ← Flutter app (Android/iOS) — ugu weyn
├── driver_app/       ← Flutter app — driver-yada
├── vendor_app/       ← Flutter app — dukaamaha
├── docs/             ← API Postman collection
└── tmp/              ← Fayl kumeelgaar ah (wallet controllers aan active ahayn)
```

### Server (DigitalOcean VPS)
- **IP:** 168.144.117.91 | **User:** root
- **Backend path:** `/var/www/esahlan/backend/backend`
- **Web server:** Nginx + PHP-FPM
- **Queue:** Supervisor (Laravel Horizon/queue:work)
- **Realtime:** Laravel Reverb (WebSocket) + Redis
- **Deploy:** `cd /var/www/esahlan/backend/backend && git pull && php artisan migrate --force && php artisan config:cache && php artisan route:cache`

---

## 2. Backend Structure (Laravel)

### Routes
- `backend/routes/api.php` — Dhammaan API routes (customer, community, delivery, payment)
- `backend/routes/web.php` — Admin panel routes

### Key Controllers
| Controller | Hawsha |
|-----------|--------|
| `Api/Community/CommunityFeedController` | Feed API + Reels + Algorithm |
| `Api/Community/CommunityPostController` | Post CRUD + media upload + TranscodeVideoJob dispatch |
| `Api/Community/CommunityStoryController` | Story CRUD + ProcessStoryVideoJob dispatch |
| `Api/Community/CommunityChatController` | Chat + realtime inbox broadcast |
| `Admin/AdminCommunityController` | Algorithm dashboard (active users, top posts, top users) |
| `Api/Auth/AuthController` | Login/OTP/Register |
| `Api/Customer/OrderController` | Order lifecycle |
| `Api/Payment/PaymentController` | WaafiPay integration |

### Key Services
| Service | Hawsha |
|---------|--------|
| `FeedRankingService` | Feed algorithm — scoring + pools + deduplication |
| `VideoProcessingService` | FFmpeg — HLS + optimized MP4 + thumbnail |
| `RealtimeService` | Laravel Reverb broadcast helper |
| `FcmService` | Firebase push notifications |
| `ContentModerationService` | Post/story content check |
| `WaafiPayService` | Payment gateway |

### Key Jobs (Queue: transcoding)
| Job | Hawsha |
|----|--------|
| `TranscodeVideoJob` | Post video — HLS + MP4 + thumbnail (background) |
| `ProcessStoryVideoJob` | Story video compression (background) |
| `UpdateUserInterestsJob` | User interest scoring update |

### Key Models (Community)
`CommunityPost`, `CommunityPostMedia`, `CommunityProfile`, `CommunityFollow`,
`CommunityStory`, `CommunityChat`, `CommunityMessage`, `CommunityHashtag`,
`CommunityNotification`, `CommunityAd`, `CommunityBusinessPage`

### Database Tables (Community)
- `community_posts` — posts (video_ready flag marka transcoding dhammaato)
- `community_post_media` — media files, hls_url, transcoding_status/progress
- `feed_seen_posts` — deduplication (24h window)
- `feed_interactions` — engagement tracking (like/save/share/skip)
- `user_interests` — personalization scores (14-day decay)
- `community_profiles` — user bio/followers/following

---

## 3. Flutter App Structure (customer_app)

### Package name: `com.esahlan.app`
> ⚠️ Package name-ku wuxuu leeyahay dots — **MARNABA** isticmaal `package:customer_app/...` imports. Wax kastoo import ah waa in uu RELATIVE yahay: `../../../../core/...`

### App Constants — hal meelood oo keliya
`lib/core/constants/app_constants.dart` — URL-yada, timeouts, pagination, community settings
`lib/core/constants/app_assets.dart` — Asset paths

### Navigation
`lib/core/router/app_router.dart` — GoRouter routes
`lib/features/home/presentation/screens/main_shell.dart` — Bottom nav shell

### Core Services
| File | Hawsha |
|------|--------|
| `core/api/api_client.dart` | Dio HTTP client |
| `core/services/realtime_client.dart` | Reverb WebSocket (singleton) |
| `core/services/firebase_service.dart` | FCM + Analytics |

### Community Module (ugu weyn)
```
features/community/
├── data/
│   ├── models/community_models.dart     ← Dhammaan models (Post, Story, Chat...)
│   └── repositories/community_repository.dart ← API calls
├── presentation/
│   ├── providers/community_provider.dart ← Riverpod providers
│   ├── screens/
│   │   ├── community_feed_screen.dart    ← Feed + Reels tab (ugu weyn, ~2400 line)
│   │   ├── reels_screen.dart             ← Reels full-screen
│   │   ├── community_chat_screen.dart    ← DM + online status
│   │   ├── community_chat_list_screen.dart ← Chat list + realtime inbox
│   │   ├── community_story_viewer.dart   ← Story player
│   │   └── ...
│   ├── services/
│   │   ├── video_pool.dart               ← VideoPool (feed + reels, 8 slots, disk cache)
│   │   └── ad_video_manager.dart         ← AdVideoManager (ads, separate singleton)
│   └── widgets/
│       └── stories_bar.dart              ← Stories bar + Create story screen
```

### Video Architecture (MUHIIM)
- **`VideoPool.feed`** — feed videos (regular posts)
- **`VideoPool.reels`** — reel videos
- **`AdVideoManager.instance`** — ad videos (marnaba VideoPool la isku darin)
- **Disk cache:** `esahlan_video_cache` — 15 videos, 3 days stale
- **Pool size:** 6 in-memory slots (`_maxSlots=6`), evict distance 7 (`_evictDist=7`)
- **Preload:** `setWindow(urls, index)` → loads ±1 behind, ±4 ahead (`_preloadAhead=4`)
- **`_initStarted` guard:** prevents multiple concurrent `_initVideo()` calls
- **`initState` NO preload:** marnaba `initState` ka preload garaynin — `setWindow` ayaa masuul
- **Volume rule:** Pool wuxuu dhammaan players-ka ku bilaabaa vol=0. Widget-ku waa in uu `setVolume(100)` ku wacaa marka dominant threshold (>0.5) la gaaro ama unpause marka. `reactivate(url)` isticmaal (maaha `play()`) marka widget active noqoto — wuxuu check gareeyaa isReady oo re-preload gareeyaa haddii evict la sameeyay.

### URL Fix Rules (`_fixUrl` in community_models.dart)
```dart
// api.esahlan.com → esahlan.com
// storage/ URLs → /api/v1/media?f= proxy
// HLS mp4DirectUrl: hls_url ka /hls/master.m3u8 → /optimized.mp4
```

### State Management: Riverpod
- Providers waxay ku yaalaan `presentation/providers/community_provider.dart`
- Feed: `communityFeedProvider` (AsyncNotifier, pagination)
- Chats: `communityChatsProvider`
- My profile: `communityMyProfileProvider`

---

## 4. Feed Algorithm (FeedRankingService)

### Scoring Formula (dhabta ah — `scorePost()`)
```
score = relationship_bonus          # W_FOLLOW=5.0 + closeness×0.5 (max 5)
      + log1p(engScore) × 2         # likes×1 + comments×2 + shares×3 + saves×2.5 + views×0.1
      + log1p(velocity) × 3         # engScore / hoursOld
      + interest_match               # hashtag×3.0 + type×1.5 + creator×2.0
      + viral_score × 2             # from post_scores table (pre-computed)
score *= time_decay                 # pow(0.5, hoursOld / 12)  — half-life 12h
score *= 1.15 if video/reel/image
score *= 0.1  if already seen
score *= penalty if skipped/negative signals
```

### 5 Pools (MIX percentages — `applyDiversity()`)
| Pool | % | Source |
|------|---|--------|
| Following | 40% | Posts from users the viewer follows |
| Recommended | 30% | Creators the user interacted with but doesn't follow |
| Trending | 15% | High velocity posts < 3 days old |
| New Creators | 10% | Users with < 100 followers, posted < 2 days ago |
| Random | 5% | Serendipity — any public post < 7 days |

**Cold-start boost:** haddii following=0 iyo interactions=0, trending/new-creator limits ×2.5 si page uu buuxsamo.

### Viral Score (pre-computed, `recomputePostScores()`)
```php
// COUNT(DISTINCT user_id) — hal user multi-view kuma inflate gareeyo
$viral = ($olderEngagement > 0)
    ? ($recentEngagement / $olderEngagement) * min($recentEngagement, 20)
    : $recentEngagement * 0.5;
// recent = last 2h, older = 2-4h window
```

### Anti-flood / Diversity Rules
- Max 2 posts per creator per page (backfill: 3, last-resort: unlimited)
- Max 3 posts same hashtag per page
- Max 2 consecutive same content-type

### Caching
- Redis key: `feed:v2:{userId}:p{page}` — TTL 120s
- Page 1: never cached (always fresh)
- Page 2+: precomputed every 5 min (`PrecomputeUserFeeds` command)

### N+1 Fix (muhiim — marnaba la dadin)
`transformPost()` iyo `transformUser()` waxay `$followingIds` iyo `$savedPostIds` ku helaan batch arrays, COD DB query mid walba. Haddii transformPost cusub la daro, **waa in arrays-ka lagu gudbiyo** — kuma darin query cusub oo per-post ah.

### Per-Page: 30 posts (controller passes 30 explicitly)

---

## 5. Realtime (Reverb + WebSocket)

### Channels
| Channel | Events |
|---------|--------|
| `private-user.{id}` | `chat.inbox_update`, `post.media_ready` |
| `private-chat.{id}` | `new_message`, `typing` |
| `presence-chat-presence.{id}` | Online status |
| `community.feed` | `feed.new_post` |

### Flutter Client
`core/services/realtime_client.dart` — RealtimeClient singleton
`core/providers/realtime_provider.dart` — Riverpod provider

---

## 6. Xeerarka Ammaan (Safety Rules)

### ❌ MARNABA samaysanin
1. `.env` commit-garaynin — weligood secrets ma leh git
2. `firebase-service-account.json` commit-garaynin
3. `package:customer_app/` import isticmaalin — package name-ku dots leeyahay, relative imports kaliya
4. `initState` ka `_pool.preload()` wacin — pool flooding sababi karta
5. `TranscodeVideoJob` iyo `AdVideoManager` isku darin — kala duwan yihiin
6. Direct server command ku samayso deploy la'aantis: git pull + migrate + cache

### ✅ Mar walba samayso
1. **Flutter bedel** → `flutter analyze` → build APK → test
2. **Backend bedel** → syntax check → deploy server → test endpoint
3. **Model cusub** → migration samayso → model update → factory (haddii jirto)
4. **URL cusub** → `_fixUrl()` + `mp4DirectUrl` test
5. **Community module** → `community_models.dart` relative imports hubin

### 🔄 Deploy Process
```bash
# Server-ka
ssh root@168.144.117.91
cd /var/www/esahlan/backend/backend
git pull origin main
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart   # haddii jobs beddeshay
supervisorctl restart all   # haddii supervisor config beddeshay
```

### APK Build
```bash
cd customer_app
flutter build apk --release
# Output: build/app/outputs/flutter-apk/app-release.apk
```

---

## 7. Modules (E-Commerce)

| Module | Flutter Screen | Backend Controller |
|--------|---------------|-------------------|
| eFood | `efood_screen.dart` | `EFoodController` |
| eGrocery | `egrocery_screen.dart` | `EGroceryController` |
| eShop | `eshop_screen.dart` | `EShopController` |
| eParcel | `eparcel_screen.dart` | `EParcelController` |
| eMoving | `emoving_screen.dart` | `EMovingController` |
| eLearning | `elearning/` | `ELearning*Controller` |
| eExchange | `eexchange_screen.dart` | `EExchangeController` |
| eRent | `erent/` | `ERentController` |

---

## 8. Waxaa Jira Laakiin Active Ma Ahayn
- `tmp/` folder — wallet controllers (WaafiPay integration halway)
- `driver_app/` — basic, ma dhamaystirna
- `vendor_app/` — basic, ma dhamaystirna

---

## 9. Xaaladda Hadda (Nidaamka Waxaa Shaqeynaya)

### Shaqeynaya ✓
- Community feed (posts, videos, ads, reels)
- Chat (DMs, realtime inbox, typing, online status)
- Stories (image/text/video)
- Feed algorithm (ranking, personalization, Redis cache)
- Video transcoding (HLS + MP4 background job)
- Push notifications (FCM)
- WaafiPay payment
- All e-commerce modules

### Xasuusnow
- Story video: `CachedVideoPlayerPlus` isticmaala (compression background)
- Feed videos: `initState` preload la saaray — `setActiveUrl` + `setWindow` oo scroll-based ah
- Reels ads: `seekTo(0)` marka active noqoto
- Chat inbox: realtime via `private-user.{id}` channel

---

## 10. Habka Shaqada (Workflow Protocol)

### Marka feature cusub la daro
1. Backend: migration → model → controller → route → test
2. Flutter: model update → repository → provider → screen → test
3. Commit → push → deploy server

### Marka bug la xaliyo
1. Bug-ga si fiican u sharax (mesha ku jirta, sababta)
2. Root cause hubin — codeka akhri ka hor
3. Fix minimal ah — waxaan loo baahnayn ha bedelin
4. `flutter analyze` ku run
5. APK build + test
6. Commit message faahfaahsan

### Marka server la deploy gareeyo
1. Git push ka hor — dhamaan tests pass
2. Migration haddii jirto — `--force` isticmaal (production-ka)
3. Config/route cache — marka routes/env beddeshay
4. Queue restart — marka jobs beddeshay
