class AppConstants {
  static const String appName    = 'eSahlan Driver';
  static const String baseUrl    = 'https://esahlan.com/api/v1';
  static const double defaultLat = 2.0469;
  static const double defaultLng = 45.3182;
  static const String googleMapsKey = 'AIzaSyC1pxwcaFZxDXwqDpxK_gDfPAdpFM8bTnc';

  // Reverb (same server as customer app)
  static const String reverbAppKey  = 'eogm1qscup3wck2rwpkb';
  static const String reverbHost    = 'esahlan.com';
  static const int    reverbPort    = 443;
  static const bool   reverbUseTLS  = true;
  static const String reverbAuthUrl = '$baseUrl/broadcasting/auth';
}
