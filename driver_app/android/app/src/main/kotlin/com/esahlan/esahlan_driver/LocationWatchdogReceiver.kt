package com.esahlan.esahlan_driver

import android.app.AlarmManager
import android.app.ActivityManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.Looper
import android.os.SystemClock
import android.util.Log
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

/**
 * LocationWatchdogReceiver — 5-layer always-on location guardian.
 *
 * Fires on:
 *   1. BOOT_COMPLETED / MY_PACKAGE_REPLACED  — reschedules alarm loop on reboot/update
 *   2. WATCHDOG alarm (every 5 min)           — main heartbeat
 *   3. CONNECTIVITY_CHANGE                   — fires the moment internet comes back
 *
 * On every fire:
 *   a. Reschedule next alarm (self-perpetuating loop)
 *   b. If auth token present: post GPS location directly to backend
 *   c. Restart ForegroundService if it's dead
 *
 * NO LONGER checks wasDriverTracking flag — location is always posted as long
 * as the driver is logged in (has auth token) and GPS permission is granted.
 * The backend updateLocation() always restores is_online=true on any ping.
 */
class LocationWatchdogReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG             = "LocationWatchdog"
        private const val WATCHDOG_ACTION = "com.esahlan.driver.WATCHDOG"
        private const val WATCHDOG_REQ    = 7001
        private const val INTERVAL_MS     = 5 * 60 * 1000L   // 5 minutes
        private const val FG_SERVICE      = "com.pravera.flutter_foreground_task.service.ForegroundService"
        private const val BASE_URL        = "https://esahlan.com/api/v1"
        private const val SECURE_PREFS    = "FlutterSecureStorage"
        private const val AUTH_KEY        = "auth_token"

        /** Schedule next watchdog alarm. Call from MainActivity.onCreate() to start the loop. */
        fun schedule(context: Context) {
            val am  = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
            val pi  = buildPendingIntent(context)
            val at  = SystemClock.elapsedRealtime() + INTERVAL_MS
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    am.setExactAndAllowWhileIdle(AlarmManager.ELAPSED_REALTIME_WAKEUP, at, pi)
                } else {
                    am.setExact(AlarmManager.ELAPSED_REALTIME_WAKEUP, at, pi)
                }
                Log.d(TAG, "Watchdog scheduled — next check in 5 min")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to schedule watchdog: ${e.message}")
            }
        }

        private fun buildPendingIntent(context: Context): PendingIntent {
            val intent = Intent(WATCHDOG_ACTION).apply {
                setClass(context, LocationWatchdogReceiver::class.java)
            }
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or
                    (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0)
            return PendingIntent.getBroadcast(context, WATCHDOG_REQ, intent, flags)
        }

        private fun isForegroundServiceRunning(context: Context): Boolean {
            return try {
                val am = context.getSystemService(Context.ACTIVITY_SERVICE) as ActivityManager
                @Suppress("DEPRECATION")
                am.getRunningServices(Integer.MAX_VALUE).any { svc ->
                    svc.service.className == FG_SERVICE &&
                            svc.service.packageName == context.packageName
                }
            } catch (e: Exception) {
                Log.e(TAG, "isRunning check failed: ${e.message}")
                false
            }
        }

        private fun readAuthToken(context: Context): String? {
            return try {
                val masterKey = MasterKey.Builder(context)
                    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                    .build()
                val prefs = EncryptedSharedPreferences.create(
                    context, SECURE_PREFS, masterKey,
                    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
                )
                prefs.getString(AUTH_KEY, null)
            } catch (e: Exception) {
                Log.w(TAG, "Could not read auth token: $e")
                null
            }
        }

        private fun postLocationToServer(context: Context, lat: Double, lng: Double, authToken: String) {
            try {
                val url  = URL("$BASE_URL/delivery/location")
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.setRequestProperty("Content-Type", "application/json")
                conn.setRequestProperty("Accept", "application/json")
                conn.setRequestProperty("Authorization", "Bearer $authToken")
                conn.doOutput    = true
                conn.connectTimeout = 12_000
                conn.readTimeout    = 12_000

                val ts   = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", java.util.Locale.US).apply {
                    timeZone = java.util.TimeZone.getTimeZone("UTC")
                }.format(java.util.Date())
                val body = """{"latitude":$lat,"longitude":$lng,"accuracy":null,"speed":null,"heading":null,"timestamp":"$ts","battery_level":null}"""
                OutputStreamWriter(conn.outputStream).use { it.write(body) }

                val code = conn.responseCode
                conn.disconnect()
                Log.i(TAG, "Location posted ✓ ($lat,$lng) HTTP $code")
            } catch (e: Exception) {
                Log.w(TAG, "Location post failed: $e")
            }
        }

        private fun restartForegroundService(context: Context) {
            try {
                val intent = Intent().apply {
                    setClassName(context.packageName, FG_SERVICE)
                }
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(intent)
                } else {
                    context.startService(intent)
                }
                Log.i(TAG, "ForegroundService restarted by watchdog")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to restart ForegroundService: ${e.message}")
            }
        }
    }

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: ""
        Log.d(TAG, "onReceive: $action")

        // Always reschedule next alarm (perpetuating loop)
        schedule(context)

        // Read auth token — if missing, driver is not logged in, nothing to do
        val authToken = readAuthToken(context) ?: run {
            Log.d(TAG, "No auth token — watchdog idle (not logged in)")
            return
        }

        // Restart ForegroundService if it's dead (it will post location via Flutter)
        if (!isForegroundServiceRunning(context)) {
            Log.w(TAG, "ForegroundService DEAD — restarting now")
            restartForegroundService(context)
        } else {
            Log.d(TAG, "ForegroundService alive ✓")
        }

        // ALSO directly post last-known GPS from native — no Flutter startup delay.
        // This covers the gap between service restart and first Flutter tick.
        // Uses getLastLocation() — instant, no GPS wait time.
        try {
            val fusedClient = LocationServices.getFusedLocationProviderClient(context)
            fusedClient.lastLocation
                .addOnSuccessListener { location ->
                    if (location != null) {
                        Thread {
                            postLocationToServer(context, location.latitude, location.longitude, authToken)
                        }.start()
                    } else {
                        // lastLocation is null (cold device) — request one fresh fix
                        requestFreshLocation(context, authToken)
                    }
                }
                .addOnFailureListener { e ->
                    Log.w(TAG, "lastLocation failed: $e")
                }
        } catch (e: SecurityException) {
            Log.w(TAG, "Location permission not granted: $e")
        } catch (e: Exception) {
            Log.w(TAG, "FusedLocationClient error: $e")
        }
    }

    private fun requestFreshLocation(context: Context, authToken: String) {
        try {
            val fusedClient = LocationServices.getFusedLocationProviderClient(context)
            val req = LocationRequest.Builder(Priority.PRIORITY_BALANCED_POWER_ACCURACY, 5000L)
                .setMaxUpdates(1)
                .build()

            fusedClient.requestLocationUpdates(req, object : LocationCallback() {
                override fun onLocationResult(result: LocationResult) {
                    fusedClient.removeLocationUpdates(this)
                    val loc = result.lastLocation ?: return
                    Thread {
                        postLocationToServer(context, loc.latitude, loc.longitude, authToken)
                    }.start()
                }
            }, Looper.getMainLooper())
        } catch (e: Exception) {
            Log.w(TAG, "Fresh location request failed: $e")
        }
    }
}
