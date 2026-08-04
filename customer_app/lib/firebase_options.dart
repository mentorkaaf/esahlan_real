// Generated Firebase options for eSahlan
// Web: esahlan-19f40 | Android/iOS: esahlan-817dc
// To regenerate: flutterfire configure

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) return web;
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        throw UnsupportedError(
          'DefaultFirebaseOptions have not been configured for this platform.',
        );
    }
  }

  // ── Web ──────────────────────────────────────────────────────────────────
  static const FirebaseOptions web = FirebaseOptions(
    apiKey:            'AIzaSyDxrEp_w7lmy2vLRmOmL55yYkcOErp3-V0',
    appId:             '1:855727793462:web:475eb2f5d77db9380cbf97',
    messagingSenderId: '855727793462',
    projectId:         'esahlan-19f40',
    authDomain:        'esahlan-19f40.firebaseapp.com',
    storageBucket:     'esahlan-19f40.firebasestorage.app',
  );

  // ── Android ──────────────────────────────────────────────────────────────
  // To get the correct appId:
  //   Firebase Console → Project Settings → Add Android App
  //   Package: com.esahlan.user → Download google-services.json
  static const FirebaseOptions android = FirebaseOptions(
    apiKey:            'AIzaSyCI7zqtvEj8kFhgZ-HJZCODI2kBpAGaMCI',
    appId:             '1:30724696826:android:77745e8bce52b8810d7743',
    messagingSenderId: '30724696826',
    projectId:         'esahlan-817dc',
    storageBucket:     'esahlan-817dc.firebasestorage.app',
  );

  // ── iOS ──────────────────────────────────────────────────────────────────
  static const FirebaseOptions ios = FirebaseOptions(
    apiKey:            'AIzaSyCI7zqtvEj8kFhgZ-HJZCODI2kBpAGaMCI',
    appId:             '1:30724696826:android:77745e8bce52b8810d7743',
    messagingSenderId: '30724696826',
    projectId:         'esahlan-817dc',
    storageBucket:     'esahlan-817dc.firebasestorage.app',
    iosBundleId:       'com.esahlan.user',
  );
}
