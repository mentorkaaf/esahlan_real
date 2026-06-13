class AppConstants {
  // ── API Base URL ──────────────────────────────────────────────────────────
  // Switch comment to change environment:
  //
  // LOCAL (Flutter Web / Chrome):
  //   static const String baseUrl = 'http://backend.test/api/v1';
  // LOCAL (Android emulator):
  //   static const String baseUrl = 'http://10.0.2.2:8000/api/v1';
  // LOCAL (iOS simulator):
  //   static const String baseUrl = 'http://127.0.0.1:8000/api/v1';
  // PRODUCTION (Hostinger):
  //   static const String baseUrl = 'https://yourdomain.com/api/v1';

  static const String baseUrl = 'https://esahlan.com/api/v1';

  static const int connectTimeout = 30000;
  static const int receiveTimeout = 30000;

  // Storage Keys
  static const String tokenKey       = 'auth_token';
  static const String userKey        = 'user_data';
  static const String languageKey    = 'app_language';
  static const String onboardingKey  = 'onboarding_done';
  static const String fcmTokenKey    = 'fcm_token';

  // App Info
  static const String appName       = 'eSahlan';
  static const String appTagline    = 'Everything You Need, Simplified';
  static const String currency      = 'USD';
  static const String currencySymbol = '\$';

  // Pagination
  static const int pageSize = 20;

  // ── Google Maps ──────────────────────────────────────────────────────────
  // Replace with your real Google Maps API key from:
  // https://console.cloud.google.com/google/maps-apis/credentials
  static const String googleMapsApiKey = 'AIzaSyA9J4TSypPZv3cr8Zlabn0BSDICD_Ibp-A';

  // Default map center (Mogadishu, Somalia)
  static const double defaultLat = 2.0469;
  static const double defaultLng = 45.3182;

  // ── Firebase ─────────────────────────────────────────────────────────────
  // FCM Notification channel
  static const String fcmChannelId   = 'esahlan_high_v3';
  static const String fcmChannelName = 'eSahlan Notifications';
  static const String fcmChannelDesc = 'Order updates, promotions and delivery notifications';
}
