/// Cold-start deep link storage.
///
/// When the app is launched by tapping an FCM notification (killed state),
/// [FirebaseMessaging.getInitialMessage] returns the notification data before
/// runApp() is called. We store the deep_link here so the SplashScreen can
/// navigate directly to the destination instead of going to /home first.
///
/// The SplashScreen reads and clears this in [_destinationForCode] so it is
/// consumed exactly once. main.dart sets it during Firebase init.
String? pendingColdStartDeepLink;
