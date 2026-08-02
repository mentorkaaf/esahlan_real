# eSahlan API Reference

**Base URL:** `https://api.esahlan.com/api/v1`

**Authentication:** `Authorization: Bearer {token}` (Laravel Sanctum)

**Response format:**
```json
{
  "success": true,
  "data": { ... },
  "message": "..."
}
```

---

## Authentication

All auth routes are rate-limited. OTP routes have stricter limits.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register` | Public | Register with phone number |
| POST | `/auth/send-otp` | Public | Send OTP SMS |
| POST | `/auth/verify-otp` | Public | Verify OTP → returns Bearer token |
| POST | `/auth/login` | Public | Login with phone + password |
| POST | `/auth/forgot-password` | Public | Request password reset |
| POST | `/auth/reset-password` | Public | Reset password with OTP |

### Example: OTP Login Flow
```bash
# 1. Send OTP
POST /api/v1/auth/send-otp
Body: { "phone": "+252612345678" }

# 2. Verify OTP → token issued
POST /api/v1/auth/verify-otp
Body: { "phone": "+252612345678", "otp": "1234" }
Response: { "success": true, "data": { "token": "...", "user": {...} } }
```

---

## App Config

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/app-config` | Public | FCM status, currency, feature flags |

---

## Community — Social Network

### Feed

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/feed` | Bearer | Personalized algorithmic feed |
| GET | `/community/feed/cold-start` | Bearer | Feed for new users (no history) |
| GET | `/community/reels` | Bearer | Reels feed |

**Feed query params:** `?page=1` (30 posts/page)

### Posts

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/posts/{id}` | Bearer | Single post detail |
| POST | `/community/posts` | Bearer | Create post (multipart: text, media files) |
| PUT | `/community/posts/{id}` | Bearer | Update post |
| DELETE | `/community/posts/{id}` | Bearer | Delete post |
| POST | `/community/posts/{id}/react` | Bearer | Like / react to post |
| POST | `/community/posts/{id}/save` | Bearer | Save post |
| POST | `/community/posts/{id}/share` | Bearer | Share post |
| POST | `/community/posts/{id}/view` | Bearer | Track view (feed analytics) |
| GET | `/community/posts/{id}/comments` | Bearer | Post comments |
| POST | `/community/posts/{id}/comments` | Bearer | Add comment |

### Stories

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/stories` | Bearer | Stories from followed users |
| POST | `/community/stories` | Bearer | Create story (multipart) |
| DELETE | `/community/stories/{id}` | Bearer | Delete story |
| POST | `/community/stories/{id}/view` | Bearer | Mark story as viewed |

### Profiles & Follow

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/profile/{userId}` | Bearer | User profile + posts |
| GET | `/community/profile/me` | Bearer | My profile |
| PUT | `/community/profile` | Bearer | Update my profile |
| POST | `/community/follow/{userId}` | Bearer | Follow user |
| DELETE | `/community/follow/{userId}` | Bearer | Unfollow user |
| GET | `/community/followers` | Bearer | My followers |
| GET | `/community/following` | Bearer | Users I follow |

### Chat (Direct Messages)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/chats` | Bearer | Chat list (inbox) |
| GET | `/community/chats/{chatId}/messages` | Bearer | Messages in a chat |
| POST | `/community/chats/{userId}` | Bearer | Start or get chat with user |
| POST | `/community/chats/{chatId}/messages` | Bearer | Send message |

**Real-time events** (WebSocket):
- `private-user.{id}` → `chat.inbox_update`
- `private-chat.{id}` → `new_message`, `typing`

### Notifications

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/notifications` | Bearer | Notifications list |
| POST | `/community/notifications/read` | Bearer | Mark all as read |
| POST | `/community/notifications/{id}/read` | Bearer | Mark single as read |

### Search & Discovery

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/community/search` | Bearer | Search posts, users, hashtags |
| GET | `/community/hashtags/trending` | Bearer | Trending hashtags |
| GET | `/community/hashtags/{tag}/posts` | Bearer | Posts by hashtag |
| GET | `/community/suggested-users` | Bearer | Suggested users to follow |

---

## eMarry — Matrimonial / Dating

All eMarry routes require profile approval (admin-moderated).

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/emarry/profiles` | Bearer | Browse profiles (swipe queue) |
| GET | `/emarry/profile/me` | Bearer | My eMarry profile |
| POST | `/emarry/profile` | Bearer | Create / update my profile |
| POST | `/emarry/photo` | Bearer | Upload profile photo (multipart: `photo`) |
| POST | `/emarry/interest/{userId}` | Bearer | Like / send interest → returns `is_match` |
| POST | `/emarry/pass/{userId}` | Bearer | Pass / skip profile (7-day cooldown) |
| POST | `/emarry/interest/{senderId}/respond` | Bearer | Accept or reject received interest |
| GET | `/emarry/interests/received` | Bearer | Interests received from others |
| GET | `/emarry/interests/sent` | Bearer | Interests I sent |
| GET | `/emarry/matches` | Bearer | Mutual matches |

**Match detection:**
```json
POST /emarry/interest/42
Response: { "success": true, "is_match": true, "message": "It's a match!" }
```

---

## eFood — Food Delivery

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/efood/restaurants` | Public | Restaurant listings |
| GET | `/efood/restaurants/{id}` | Public | Restaurant detail |
| GET | `/efood/restaurants/{id}/menu` | Public | Menu items |
| GET | `/efood/categories` | Public | Food categories |
| POST | `/efood/orders` | Bearer | Place order |
| GET | `/efood/orders` | Bearer | My orders |
| GET | `/efood/orders/{id}/track` | Bearer | Real-time order tracking |

---

## eGrocery — Grocery Delivery

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/egrocery/categories` | Public | Grocery categories |
| GET | `/egrocery/products` | Public | Products list |
| GET | `/egrocery/products/{id}` | Public | Product detail |

---

## eShop — Marketplace

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/eshop/home` | Public | Home sections (banners, featured) |
| GET | `/eshop/products` | Public | Products (filter: category, vendor, search) |
| GET | `/eshop/products/{id}` | Public | Product detail |
| GET | `/eshop/stores` | Public | Vendor stores |
| GET | `/eshop/flash-deals` | Public | Flash deals |
| POST | `/eshop/cart` | Bearer | Add to cart |
| POST | `/eshop/checkout` | Bearer | Checkout |

---

## eRent — Real Estate

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/erent/properties` | Public | Property listings |
| GET | `/erent/properties/{id}` | Public | Property detail |
| GET | `/erent/reels` | Public | Property video reels |
| POST | `/erent/properties/{id}/request-viewing` | Bearer | Request viewing |
| POST | `/erent/properties/{id}/offer` | Bearer | Make an offer |

---

## eParcel — Parcel Delivery

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/eparcel/types` | Public | Parcel types |
| POST | `/eparcel/calculate` | Public | Calculate delivery fee |
| POST | `/eparcel/orders` | Bearer | Place parcel order |
| GET | `/eparcel/orders` | Bearer | My parcel orders |

---

## Live Streaming

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/live/rooms` | Bearer | Active live rooms |
| POST | `/live/rooms` | Bearer | Start a live room |
| GET | `/live/rooms/{id}` | Bearer | Room detail + join token |
| POST | `/live/rooms/{id}/end` | Bearer | End live room |
| POST | `/live/gifts/send` | Bearer | Send virtual gift |
| GET | `/live/leaderboard` | Bearer | Top gifters / streamers |

---

## Podcast

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/podcast/home` | Bearer | Featured podcasts + categories |
| GET | `/podcast/categories` | Bearer | All categories |
| GET | `/podcast/{id}` | Bearer | Podcast detail |
| GET | `/podcast/{id}/episodes` | Bearer | Episodes list |
| POST | `/podcast/{id}/subscribe` | Bearer | Subscribe to podcast |

---

## Payments

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/payment/methods` | Public | Available payment methods |
| POST | `/payment/waafi` | Bearer | Pay with WaafiPay |
| GET | `/payment/history` | Bearer | Payment history |
| GET | `/wallet/balance` | Bearer | Wallet balance |
| POST | `/wallet/topup` | Bearer | Add funds to wallet |

---

## Crypto

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/crypto/market` | Bearer | Live crypto prices |
| GET | `/crypto/wallet` | Bearer | Crypto wallet balances |
| POST | `/crypto/buy` | Bearer | Buy crypto |
| POST | `/crypto/sell` | Bearer | Sell crypto |
| GET | `/crypto/p2p` | Bearer | P2P listings |

---

## Real-time WebSocket

**Server:** `wss://api.esahlan.com/app/{REVERB_APP_KEY}`

**Protocol:** Pusher-compatible (JSON over WebSocket)

### Channels

| Channel | Events | Description |
|---------|--------|-------------|
| `private-user.{userId}` | `chat.inbox_update`, `post.media_ready` | Per-user private events |
| `private-chat.{chatId}` | `new_message`, `typing` | Chat room events |
| `presence-chat-presence.{chatId}` | `pusher:member_added`, `pusher:member_removed` | Online presence |
| `community.feed` | `feed.new_post` | Public feed broadcast |

### Authentication

Private channels require auth via:
```
POST /broadcasting/auth
Authorization: Bearer {token}
Body: channel_name, socket_id
```

---

## Error Responses

| Status | Meaning |
|--------|---------|
| 401 | Unauthenticated — missing or invalid token |
| 403 | Forbidden — insufficient permissions |
| 404 | Resource not found |
| 422 | Validation error — check `errors` field |
| 429 | Rate limit exceeded |
| 500 | Server error |

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": { "phone": ["The phone field is required."] }
}
```

---

*For interactive testing, see the Postman collection in `/docs/`.*
