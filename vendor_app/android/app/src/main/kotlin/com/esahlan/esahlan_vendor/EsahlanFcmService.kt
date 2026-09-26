package com.esahlan.esahlan_vendor

import android.content.Context
import android.util.Log
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

/**
 * Native FCM service — captures token rotations at the OS level.
 * Vendor auth token is stored in Flutter SharedPreferences as 'flutter.vendor_token'.
 */
class EsahlanFcmService : FlutterFirebaseMessagingService() {

    companion object {
        private const val TAG = "EsahlanVendorFcm"
        private const val BASE_URL = "https://esahlan.com/api/v1"
        private const val ENDPOINT = "/vendor/fcm-token"
        private const val FLUTTER_PREFS = "FlutterSharedPreferences"
        private const val AUTH_KEY = "flutter.vendor_token"
        private const val FCM_CACHE_KEY = "flutter.vendor_fcm_token"
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(TAG, "onNewToken — uploading immediately")
        Thread { uploadToken(token) }.start()
    }

    private fun uploadToken(fcmToken: String) {
        try {
            val prefs = applicationContext
                .getSharedPreferences(FLUTTER_PREFS, Context.MODE_PRIVATE)
            val authToken = prefs.getString(AUTH_KEY, null) ?: run {
                Log.d(TAG, "No auth token — skipping (user not logged in yet)")
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
                prefs.edit().putString(FCM_CACHE_KEY, fcmToken).apply()
                Log.d(TAG, "Vendor token uploaded ✓ (HTTP $code)")
            } else {
                Log.w(TAG, "Upload failed — HTTP $code (bg handler will retry)")
            }
        } catch (e: Exception) {
            Log.w(TAG, "Upload exception: $e (bg handler will retry)")
        }
    }
}
