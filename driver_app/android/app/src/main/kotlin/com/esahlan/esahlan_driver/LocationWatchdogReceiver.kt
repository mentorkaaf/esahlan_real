package com.esahlan.esahlan_driver

import android.app.AlarmManager
import android.app.ActivityManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.SystemClock
import android.util.Log

/**
 * LocationWatchdogReceiver
 *
 * Three-layer guardian that keeps location tracking alive forever:
 *
 * Layer 1 — BOOT_COMPLETED / MY_PACKAGE_REPLACED:
 *   On device reboot or app update, re-schedules the watchdog alarm so the
 *   5-minute check loop restarts immediately (supplements flutter_foreground_task's
 *   own BootReceiver as a second line of defence).
 *
 * Layer 2 — AlarmManager every 5 minutes (WATCHDOG action):
 *   Checks if ForegroundService is running. If not AND driver was tracking
 *   (SharedPrefs flag set by Flutter when driver goes online), restarts it.
 *   Uses setExactAndAllowWhileIdle() so the alarm fires even in Doze mode.
 *
 * Layer 3 — Self-perpetuating:
 *   Every time the watchdog fires, it immediately schedules the next alarm.
 *   This creates an unbreakable 5-minute check loop that survives device reboots
 *   (re-registered on BOOT_COMPLETED), OEM kill (AlarmManager is OS-level,
 *   not process-level), and app updates.
 *
 * Note: android:stopWithTask="false" on ForegroundService (AndroidManifest.xml)
 * means the service normally survives user swipes already. This watchdog is the
 * backup for aggressive OEM battery managers (Huawei/Xiaomi/Samsung) that kill
 * foreground services despite START_STICKY + battery optimisation exclusion.
 */
class LocationWatchdogReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG            = "LocationWatchdog"
        private const val WATCHDOG_ACTION = "com.esahlan.driver.WATCHDOG"
        private const val WATCHDOG_REQ   = 7001
        private const val INTERVAL_MS    = 5 * 60 * 1000L   // 5 minutes
        private const val FG_SERVICE     = "com.pravera.flutter_foreground_task.service.ForegroundService"

        /** Call from MainActivity.onCreate() to start the watchdog loop. */
        fun schedule(context: Context) {
            val am  = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
            val pi  = buildPendingIntent(context)
            val triggerAt = SystemClock.elapsedRealtime() + INTERVAL_MS
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    am.setExactAndAllowWhileIdle(AlarmManager.ELAPSED_REALTIME_WAKEUP, triggerAt, pi)
                } else {
                    am.setExact(AlarmManager.ELAPSED_REALTIME_WAKEUP, triggerAt, pi)
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
                false // assume not running → attempt restart
            }
        }

        private fun wasDriverTracking(context: Context): Boolean {
            return try {
                val prefs = context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
                // Flutter LocalStorage.saveBool writes "flutter.<key>"
                prefs.getBoolean("flutter.driver_was_tracking", false)
            } catch (e: Exception) {
                false
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

        // Always reschedule the next watchdog alarm first
        schedule(context)

        // Check and restart if needed (on all actions)
        val tracking = wasDriverTracking(context)
        if (!tracking) {
            Log.d(TAG, "Driver not tracking — watchdog idle")
            return
        }

        val running = isForegroundServiceRunning(context)
        if (!running) {
            Log.w(TAG, "ForegroundService DEAD — restarting now")
            restartForegroundService(context)
        } else {
            Log.d(TAG, "ForegroundService alive ✓")
        }
    }
}
