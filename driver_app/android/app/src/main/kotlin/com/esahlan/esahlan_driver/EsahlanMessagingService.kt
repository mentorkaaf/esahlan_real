package com.esahlan.esahlan_driver

import android.app.ActivityManager
import android.content.Context
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService
import org.json.JSONObject

/**
 * Native FCM service — intercepts new_order messages and launches
 * OrderCallActivity via fullScreenIntent, even when the app is KILLED.
 *
 * Why this is needed:
 *   Flutter's _bgHandler runs in a background Dart isolate. When the app is
 *   killed, CallPlugin is not registered on that isolate, so MethodChannel
 *   calls fail silently. The flutter_local_notifications fallback only opens
 *   MainActivity (cold start), not OrderCallActivity.
 *
 * What this service does for new_order:
 *   1. Saves order JSON to SharedPreferences (key Flutter reads on launch)
 *   2. Calls CallPlugin.showCallNotificationStatic() → fullScreenIntent → OrderCallActivity
 *   Does NOT call super (avoids double notification from Flutter _bgHandler).
 *
 * For all other message types (request_location, etc.):
 *   Calls super → FlutterFirebaseMessagingService → Dart _bgHandler handles them.
 *
 * When app is FOREGROUND:
 *   Flutter's FirebaseMessaging.onMessage stream handles new_order (shows in-app
 *   ring screen). We detect foreground state and call super instead, so the
 *   native notification doesn't pop over the already-visible Flutter UI.
 */
class EsahlanMessagingService : FlutterFirebaseMessagingService() {

    override fun onMessageReceived(message: RemoteMessage) {
        val type = message.data["type"] ?: ""

        if (type == "new_order" && !isAppInForeground()) {
            // ── App is KILLED or BACKGROUND — handle natively ──────────────

            // 1. Persist order so Flutter reads it after launch/resume
            try {
                @Suppress("UNCHECKED_CAST")
                val json = JSONObject(message.data as Map<*, *>).toString()
                getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
                    .edit()
                    .putString("flutter.pending_ring_order", json)
                    .apply()
            } catch (_: Exception) {}

            // 2. Show fullScreenIntent notification → OrderCallActivity
            //    Works on ALL Android versions (API 21+) without Flutter engine.
            CallPlugin.showCallNotificationStatic(
                applicationContext,
                message.data.mapValues { it.value }
            )

            // Do NOT call super — Flutter _bgHandler would show a second notification
            return
        }

        // ── App is FOREGROUND, or non-new_order message ────────────────────
        // Let FlutterFirebaseMessagingService dispatch to Dart _bgHandler.
        super.onMessageReceived(message)
    }

    // ── Check if our app is currently in the foreground ──────────────────────
    private fun isAppInForeground(): Boolean {
        return try {
            val am = getSystemService(Context.ACTIVITY_SERVICE) as ActivityManager
            val procs = am.runningAppProcesses ?: return false
            procs.any {
                it.importance == ActivityManager.RunningAppProcessInfo.IMPORTANCE_FOREGROUND &&
                it.processName == packageName
            }
        } catch (_: Exception) {
            false // assume background/killed on error → show native UI
        }
    }
}
