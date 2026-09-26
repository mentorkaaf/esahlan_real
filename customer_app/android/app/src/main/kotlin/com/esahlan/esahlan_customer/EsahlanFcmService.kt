package com.esahlan.esahlan_customer

import android.content.Context
import android.util.Log
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

/**
 * Native FCM service — captures token rotations at the OS level.
 *
 * onNewToken() fires even when the app is COMPLETELY KILLED. Flutter's
 * onTokenRefresh stream never fires in that state, so without this service
 * the server would keep the stale token until the user opens the app.
 *
 * Flow:
 *   1. Firebase rotates token (reinstall / ~60-day cycle / server-side reset)
 *   2. Android calls onNewToken() — app does NOT need to be running
 *   3. We read the auth token from EncryptedSharedPreferences (flutter_secure_storage)
 *   4. Immediately POST the new FCM token to the backend (fire-and-forget thread)
 *   5. Update the Flutter SharedPreferences cache so Flutter's bg handler
 *      doesn't see a stale diff on the next wake
 *
 * If the upload fails (no network), the Flutter _bgHandler will retry on the
 * next FCM wake (our daily token_check ping at 02:00).
 */
class EsahlanFcmService : FlutterFirebaseMessagingService() {

    companion object {
        private const val TAG = "EsahlanFcm"
        private const val BASE_URL = "https://esahlan.com/api/v1"
        private const val ENDPOINT = "/auth/fcm-token"
        // flutter_secure_storage preference file name
        private const val SECURE_PREFS = "FlutterSecureStorage"
        private const val AUTH_KEY = "auth_token"
        // Flutter SharedPreferences cache key (flutter. prefix added by Flutter SDK)
        private const val FCM_CACHE_KEY = "flutter.fcm_token"
        private const val FLUTTER_PREFS = "FlutterSharedPreferences"
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(TAG, "onNewToken — uploading immediately")
        Thread { uploadToken(token) }.start()
    }

    private fun uploadToken(fcmToken: String) {
        try {
            val authToken = readAuthToken() ?: run {
                Log.d(TAG, "No auth token — skipping upload (user not logged in yet)")
                return
            }

            val url = URL("$BASE_URL$ENDPOINT")
            val conn = url.openConnection() as HttpURLConnection
            conn.requestMethod = "POST"
            conn.setRequestProperty("Content-Type", "application/json")
            conn.setRequestProperty("Accept", "application/json")
            conn.setRequestProperty("Authorization", "Bearer $authToken")
            conn.doOutput = true
            conn.connectTimeout = 12_000
            conn.readTimeout = 12_000

            val body = """{"fcm_token":"$fcmToken"}"""
            OutputStreamWriter(conn.outputStream).use { it.write(body) }

            val code = conn.responseCode
            conn.disconnect()

            if (code in 200..299) {
                // Update Flutter's cached token so bg handler doesn't re-upload
                applicationContext
                    .getSharedPreferences(FLUTTER_PREFS, Context.MODE_PRIVATE)
                    .edit()
                    .putString(FCM_CACHE_KEY, fcmToken)
                    .apply()
                Log.d(TAG, "Token uploaded ✓ (HTTP $code)")
            } else {
                Log.w(TAG, "Upload failed — HTTP $code (bg handler will retry)")
            }
        } catch (e: Exception) {
            Log.w(TAG, "Upload exception: $e (bg handler will retry)")
        }
    }

    private fun readAuthToken(): String? {
        return try {
            val masterKey = MasterKey.Builder(applicationContext)
                .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                .build()
            val prefs = EncryptedSharedPreferences.create(
                applicationContext,
                SECURE_PREFS,
                masterKey,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
            prefs.getString(AUTH_KEY, null)
        } catch (e: Exception) {
            Log.w(TAG, "Could not read auth token: $e")
            null
        }
    }
}
