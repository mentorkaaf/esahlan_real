# Flutter
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

# Firebase
-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**

# Google Maps
-keep class com.google.android.gms.maps.** { *; }

# Gson
-keepattributes Signature
-keepattributes *Annotation*
-keep class com.google.gson.** { *; }

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**

# Play Core
-dontwarn com.google.android.play.core.**

# Keep app models
-keep class com.esahlan.user.** { *; }

# flutter_stripe — PushProvisioning is optional (requires Stripe Issuing)
-dontwarn com.stripe.android.pushProvisioning.**
-keep class com.stripe.android.pushProvisioning.** { *; }
