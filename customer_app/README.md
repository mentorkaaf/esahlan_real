# eSahlan Customer App — Flutter

Production Flutter app for Android and iOS — the primary customer-facing interface for the eSahlan super-app platform.

## Tech Stack

| Component | Library |
|-----------|---------|
| Framework | Flutter 3.x (Dart) |
| State Management | Riverpod 2.x |
| Navigation | GoRouter |
| HTTP Client | Dio |
| Real-time | Custom WebSocket (Pusher protocol) |
| Video | Custom VideoPool (LRU cache + HLS) |
| Push Notifications | Firebase Messaging |
| Local Storage | Flutter Secure Storage + SharedPreferences |
| Image Cache | cached_network_image |
| Maps | Google Maps Flutter |
| Calls/Streaming | LiveKit |

## Package Name

`com.esahlan.app`

> **Important:** The package name contains dots. All imports must be **relative**, never `package:customer_app/...`.

## Project Structure

```
lib/
├── core/
│   ├── api/
│   │   └── api_client.dart          ← Dio HTTP client (singleton)
│   ├── constants/
│   │   ├── app_constants.dart       ← URLs, timeouts, pagination limits
│   │   └── app_assets.dart          ← Asset paths
│   ├── router/
│   │   └── app_router.dart          ← GoRouter route definitions
│   ├── services/
│   │   ├── realtime_client.dart     ← WebSocket singleton (Reverb)
│   │   └── firebase_service.dart    ← FCM + Analytics
│   ├── theme/
│   │   └── theme_x.dart             ← Theme extensions
│   └── widgets/
│       └── network_image_widget.dart ← NetImage (cached + CORS-safe)
│
└── features/
    ├── auth/                        ← OTP login, registration
    ├── home/
    │   └── presentation/screens/
    │       └── main_shell.dart      ← Bottom navigation shell
    ├── community/                   ← Social network (largest module)
    │   ├── data/
    │   │   ├── models/
    │   │   │   └── community_models.dart   ← All data models
    │   │   └── repositories/
    │   │       └── community_repository.dart
    │   └── presentation/
    │       ├── providers/
    │       │   └── community_provider.dart ← Riverpod providers
    │       ├── screens/
    │       │   ├── community_feed_screen.dart   ← Feed + Reels (~2400 lines)
    │       │   ├── reels_screen.dart
    │       │   ├── emarry_screen.dart           ← Matrimonial / dating
    │       │   ├── community_chat_screen.dart
    │       │   └── community_story_viewer.dart
    │       ├── services/
    │       │   ├── video_pool.dart          ← VideoPool (6-slot LRU)
    │       │   └── ad_video_manager.dart    ← Ad video (separate singleton)
    │       └── widgets/
    │           └── stories_bar.dart
    ├── efood/
    ├── egrocery/
    ├── eshop/
    ├── erent/
    ├── eparcel/
    ├── emoving/
    ├── elearning/
    ├── eexchange/
    └── crypto/
```

## Video Architecture

The app uses a custom video management system optimized for feed performance:

```
VideoPool.feed      ← feed posts (6 in-memory slots, LRU eviction)
VideoPool.reels     ← reels (separate pool)
AdVideoManager      ← ad videos (never mixed with VideoPool)
```

- **Disk cache:** 15 videos, 3-day TTL
- **Preload window:** ±1 behind, ±4 ahead
- **Volume:** all players start at 0; widget sets volume when dominant (>0.5 threshold)
- **Reactivation:** use `reactivate(url)` not `play()` to handle evicted slots

## URL Handling

All media URLs go through `_fixUrl()` in `community_models.dart`:
```
api.esahlan.com → esahlan.com
storage/ URLs   → /api/v1/media?f= (CORS-safe proxy)
HLS URLs        → /optimized.mp4 fallback
```

## Build

### Debug
```bash
flutter run
```

### Release APK
```bash
flutter build apk --release
# Output: build/app/outputs/flutter-apk/app-release.apk
```

### Release iOS
```bash
flutter build ios --release
```

## Environment

Base URL and API configuration in `lib/core/constants/app_constants.dart`.

The app connects to:
- REST API: `https://api.esahlan.com/api/v1`
- WebSocket: `wss://api.esahlan.com/app/{key}`
- Media proxy: `https://api.esahlan.com/api/v1/media?f=`

## Modules Navigation

Bottom navigation:
1. **Home** — eSocial community feed
2. **Reels** — Full-screen vertical video
3. **[+]** — Create post / story
4. **Chat** — Direct messages
5. **Profile** — User profile

Community tabs (top):
- **For You** — Algorithmic feed
- **Podcast** — Podcast platform
- **People** — Discover users
- **Business** — Business pages
- **eMarry** — Matrimonial / dating
