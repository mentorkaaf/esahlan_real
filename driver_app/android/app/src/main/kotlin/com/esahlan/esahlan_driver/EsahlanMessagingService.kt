package com.esahlan.esahlan_driver

import android.app.ActivityManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.google.firebase.messaging.RemoteMessage
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

/**
 * Native FCM service — intercepts new_order messages and launches
 * OrderCallActivity via fullScreenIntent, even when the app is KILLED.
 *
 * Also handles force_online_reminder natively:
 *   - Shows a high-priority notification (tap → opens app)
 *   - Saves pending_force_online flag to SharedPreferences
 *   - Does NOT call super so Dart _bgHandler doesn't double-handle it
 */
class EsahlanMessagingService : FlutterFirebaseMessagingService() {

    companion object {
        private const val TAG = "EsahlanDriverFcm"
        private const val FORCE_ONLINE_CH = "esahlan_admin_alerts"
        private const val FORCE_ONLINE_ID = 99902
        private const val BASE_URL = "https://esahlan.com/api/v1"
        private const val SECURE_PREFS = "FlutterSecureStorage"
        private const val AUTH_KEY = "auth_token"
        private const val FLUTTER_PREFS = "FlutterSharedPreferences"
        private const val FCM_CACHE_KEY = "flutter.driver_fcm_token_cached"
    }

    /**
     * Fires even when app is KILLED — captures token rotations immediately.
     * Reads auth token from flutter_secure_storage (EncryptedSharedPreferences)
     * and POSTs the new FCM token to the backend on a background thread.
     */
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(TAG, "onNewToken — uploading immediately")
        Thread { uploadDriverToken(token) }.start()
    }

    private fun uploadDriverToken(fcmToken: String) {
        try {
            val authToken = readDriverAuthToken() ?: run {
                Log.d(TAG, "No auth token — skipping (user not logged in yet)")
                return
            }
            val url = URL("$BASE_URL/delivery/fcm-token")
            val conn = url.openConnection() as HttpURLConnection
            conn.requestMethod = "POST"
            conn.setRequestProperty("Content-Type", "application/json")
            conn.setRequestProperty("Accept", "application/json")
            conn.setRequestProperty("Authorization", "Bearer $authToken")
            conn.doOutput = true
            conn.connectTimeout = 12_000
            conn.readTimeout = 12_000
            val body = """{"token":"$fcmToken"}"""
            OutputStreamWriter(conn.outputStream).use { it.write(body) }
            val code = conn.responseCode
            conn.disconnect()
            if (code in 200..299) {
                applicationContext
                    .getSharedPreferences(FLUTTER_PREFS, Context.MODE_PRIVATE)
                    .edit().putString(FCM_CACHE_KEY, fcmToken).apply()
                Log.d(TAG, "Driver token uploaded ✓ (HTTP $code)")
            } else {
                Log.w(TAG, "Upload failed — HTTP $code (bg handler will retry)")
            }
        } catch (e: Exception) {
            Log.w(TAG, "Upload exception: $e (bg handler will retry)")
        }
    }

    private fun readDriverAuthToken(): String? {
        return try {
            val masterKey = MasterKey.Builder(applicationContext)
                .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                .build()
            val prefs = EncryptedSharedPreferences.create(
                applicationContext, SECURE_PREFS, masterKey,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
            prefs.getString(AUTH_KEY, null)
        } catch (e: Exception) {
            Log.w(TAG, "Could not read auth token: $e")
            null
        }
    }

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
            CallPlugin.showCallNotificationStatic(
                applicationContext,
                message.data.mapValues { it.value }
            )

            // Do NOT call super — Flutter _bgHandler would show a second notification
            return
        }

        if (type == "force_online_reminder") {
            // ── Admin forced this driver online ────────────────────────────
            // 1. Save flag so MainShell picks it up on resume / after open
            getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
                .edit()
                .putBoolean("flutter.pending_force_online", true)
                .apply()

            // 2. Show notification so driver knows + can tap to open app
            showForceOnlineNotification()

            // 3. Let Dart _bgHandler also handle it (posts location immediately)
            super.onMessageReceived(message)
            return
        }

        // ── All other message types ────────────────────────────────────────
        super.onMessageReceived(message)
    }

    private fun showForceOnlineNotification() {
        val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val ch = NotificationChannel(
                FORCE_ONLINE_CH,
                "Admin Alerts",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Admin actions affecting your driver status"
                enableVibration(true)
            }
            nm.createNotificationChannel(ch)
        }

        val openIntent = Intent(applicationContext, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            putExtra("from_force_online", true)
        }
        val pi = PendingIntent.getActivity(
            applicationContext, FORCE_ONLINE_ID, openIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0
        )

        val notif = NotificationCompat.Builder(applicationContext, FORCE_ONLINE_CH)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("⚡ Admin Set You Online")
            .setContentText("You have been set online by admin. Tap to open the app.")
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pi)
            .build()

        nm.notify(FORCE_ONLINE_ID, notif)
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
            false
        }
    }
}
