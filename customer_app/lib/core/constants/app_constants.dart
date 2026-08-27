class AppConstants {
  // ── API Base URL ──────────────────────────────────────────────────────────
  static const String baseUrl = 'https://esahlan.com/api/v1';

  // Derived domain — used wherever a raw host is needed (e.g. media URL rewriting)
  static const String baseDomain = 'esahlan.com';
  static const String apiDomain  = 'api.esahlan.com';

  // ── Realtime (Laravel Reverb) ────────────────────────────────────────────
  // Public app key — safe to embed client-side (the secret stays server-only).
  static const String reverbAppKey  = 'eogm1qscup3wck2rwpkb';
  static const String reverbHost    = 'esahlan.com';
  static const int    reverbPort    = 443;
  static const bool   reverbUseTLS  = true;
  static const String reverbAuthUrl = '$baseUrl/broadcasting/auth';

  // ── API Timeouts ─────────────────────────────────────────────────────────
  static const Duration apiConnectTimeout = Duration(minutes: 2);
  static const Duration apiReceiveTimeout = Duration(minutes: 30);
  static const Duration apiSendTimeout    = Duration(minutes: 30);

  // ── Storage Keys ─────────────────────────────────────────────────────────
  static const String tokenKey      = 'auth_token';
  static const String userKey       = 'user_data';
  static const String languageKey   = 'app_language';
  static const String onboardingKey = 'onboarding_done';
  static const String fcmTokenKey   = 'fcm_token';

  // ── App Info ─────────────────────────────────────────────────────────────
  static const String appName        = 'eSahlan';
  static const String appTagline     = 'Everything You Need, Simplified';
  static const String currency       = 'USD';
  static const String currencySymbol = '\$';

  // ── Pagination ───────────────────────────────────────────────────────────
  static const int pageSize = 20;

  // ── Google Maps ──────────────────────────────────────────────────────────
  static const String googleMapsApiKey = 'AIzaSyC1pxwcaFZxDXwqDpxK_gDfPAdpFM8bTnc';
  static const double defaultLat = 2.0469;
  static const double defaultLng = 45.3182;

  // ── Firebase ─────────────────────────────────────────────────────────────
  static const String fcmChannelId   = 'esahlan_high_v3';
  static const String fcmChannelName = 'eSahlan Notifications';
  static const String fcmChannelDesc = 'Order updates, promotions and delivery notifications';

  // Web Push VAPID key — get from Firebase Console → Project Settings →
  // Cloud Messaging → Web configuration → Generate key pair (starts with "B")
  static const String webVapidKey = 'BLiDrmgVP0i1y1b9MjCEGsEV4vqqbQC6nJu0d9YL_SBB_Ppq7HUAuR09vDMCeEHlgSLNmk2VEDMhP4oYdYHLoGw';

  // ── Delivery Fees ────────────────────────────────────────────────────────
  static const double eshopDeliveryFee   = 2.00;
  static const double groceryDeliveryFee = 1.50;
  static const double foodDeliveryFee    = 1.99;

  // ── Wallet ───────────────────────────────────────────────────────────────
  static const double        walletMinTopup      = 1.0;
  static const double        walletMinSend       = 0.01;
  static const double        walletMinWithdrawal = 1.0;
  static const List<int>     walletTopupPresets  = [5, 10, 20, 50];

  // ── OTP ──────────────────────────────────────────────────────────────────
  static const int otpResendSeconds = 60;

  // ── Ads ──────────────────────────────────────────────────────────────────
  static const int      adSkipCountdownSeconds  = 10;
  static const Duration adDisplayDelay          = Duration(seconds: 5);
  static const Duration adBannerScrollInterval  = Duration(seconds: 5);
  static const Duration adRequestTimeout        = Duration(seconds: 8);
  static const Duration adVideoMaxDuration      = Duration(minutes: 2);

  // ── Community ────────────────────────────────────────────────────────────
  static const Duration storyVideoDuration      = Duration(seconds: 15);
  static const Duration storyImageDuration      = Duration(seconds: 5);
  static const Duration feedPollInterval        = Duration(seconds: 90);
  static const Duration feedHeartbeatInterval   = Duration(seconds: 30);
  static const Duration presenceAwayThreshold   = Duration(minutes: 2);
  static const Duration postVideoMaxDuration    = Duration(hours: 1);
  static const int      postVideoMaxFileSizeMB  = 100;
  static const int      profileBioMaxLength     = 500;

  // ── Video Cache ──────────────────────────────────────────────────────────
  static const Duration videoCacheStalePeriod      = Duration(days: 3);
  static const Duration adVideoCacheStalePeriod    = Duration(days: 14);
  static const Duration adPreloadCacheStalePeriod  = Duration(days: 7);

  // ── Realtime / Network ───────────────────────────────────────────────────
  static const Duration realtimePingInterval   = Duration(seconds: 25);
  static const Duration locationTimeout        = Duration(seconds: 20);
  static const Duration locationPostInterval   = Duration(minutes: 5);
  static const Duration uploadSendTimeout      = Duration(minutes: 30);
  static const Duration paymentPollInterval    = Duration(seconds: 3);
  static const Duration orderRefreshInterval   = Duration(seconds: 30);
  static const Duration bannerScrollInterval   = Duration(seconds: 4);

  // ── External URLs ────────────────────────────────────────────────────────
  static const String tiktokOembedUrl = 'https://www.tiktok.com/oembed';
  static String tiktokPlayerUrl(String videoId) =>
      'https://www.tiktok.com/player/v1/$videoId?autoplay=1&loop=0&rel=0';
}
