package com.esahlan.esahlan_driver

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.os.Build
import androidx.core.app.NotificationCompat
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * CallPlugin — Flutter plugin that bridges Dart's _bgHandler to the native
 * OrderCallActivity via a fullScreenIntent notification.
 *
 * Channel: "esahlan_call"
 * Methods:
 *   showCallScreen(Map<String,String> data) → null
 *     Shows a MAX-importance notification whose fullScreenIntent launches
 *     OrderCallActivity immediately, even over the lock screen.
 *
 *   cancelCallScreen() → null
 *     Cancels the ongoing call notification (called on accept/decline/timeout).
 *
 * Registration:
 *   MainActivity.configureFlutterEngine() → works for foreground + background-state.
 *   For killed state: BackgroundIsolateBinaryMessenger + RootIsolateToken (Flutter 3.7+).
 */
class CallPlugin : FlutterPlugin, MethodChannel.MethodCallHandler {

    private lateinit var channel: MethodChannel
    private lateinit var appContext: Context

    companion object {
        private const val CHANNEL_ID  = "esahlan_order_call_v1"
        private const val NOTIF_ID    = 77701
        private const val METHOD_CH   = "esahlan_call"
    }

    // ── Plugin lifecycle ──────────────────────────────────────────────────────
    override fun onAttachedToEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        appContext = binding.applicationContext
        channel   = MethodChannel(binding.binaryMessenger, METHOD_CH)
        channel.setMethodCallHandler(this)
        ensureNotificationChannel()
    }

    override fun onDetachedFromEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel.setMethodCallHandler(null)
    }

    // ── Notification channel ──────────────────────────────────────────────────
    private fun ensureNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = appContext.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (nm.getNotificationChannel(CHANNEL_ID) != null) return
        val ch = NotificationChannel(
            CHANNEL_ID,
            "Incoming Order Call",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description     = "Shows a full-screen alarm when a new delivery order arrives."
            enableLights(true)
            lightColor      = Color.parseColor("#FF8A00")
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 500, 200, 500, 200, 500, 200, 500)
            lockscreenVisibility = NotificationCompat.VISIBILITY_PUBLIC
        }
        nm.createNotificationChannel(ch)
    }

    // ── Method handler ────────────────────────────────────────────────────────
    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {

            "showCallScreen" -> {
                try {
                    @Suppress("UNCHECKED_CAST")
                    val data = (call.arguments as? Map<*, *>)
                        ?.mapKeys { it.key.toString() }
                        ?.mapValues { it.value?.toString() ?: "" }
                        ?: emptyMap()

                    showCallNotification(data)
                    result.success(null)
                } catch (e: Exception) {
                    result.error("CALL_ERROR", e.message, null)
                }
            }

            "cancelCallScreen" -> {
                cancelCallNotification()
                result.success(null)
            }

            else -> result.notImplemented()
        }
    }

    // ── Show fullScreenIntent notification → OrderCallActivity ────────────────
    private fun showCallNotification(data: Map<String, String>) {
        val orderNum  = data["order_number"] ?: ""
        val fee       = data["delivery_fee"]  ?: "0"

        // Intent pointing directly to OrderCallActivity
        val callIntent = Intent(appContext, OrderCallActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            data.forEach { (k, v) -> putExtra(k, v) }
        }

        val flags = PendingIntent.FLAG_UPDATE_CURRENT or
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M)
                        PendingIntent.FLAG_IMMUTABLE else 0

        val fullScreenPI = PendingIntent.getActivity(appContext, NOTIF_ID,     callIntent, flags)
        val contentPI    = PendingIntent.getActivity(appContext, NOTIF_ID + 1, callIntent, flags)

        val notif = NotificationCompat.Builder(appContext, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_dialer)   // phone icon
            .setContentTitle("🚴 New Order — Tap to accept")
            .setContentText(
                buildString {
                    if (orderNum.isNotEmpty()) append("Order #$orderNum  •  ")
                    append("\$$fee delivery fee")
                }
            )
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)       // call category = highest priority
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)  // show on lock screen
            .setFullScreenIntent(fullScreenPI, true)              // ← the key: pops over lock screen
            .setContentIntent(contentPI)
            .setOngoing(true)         // stays in tray until dismissed
            .setAutoCancel(false)
            .setTimeoutAfter(55_000)  // auto-dismiss after 55 s (safety net)
            .setColor(Color.parseColor("#FF8A00"))
            .build()

        val nm = appContext.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        nm.notify(NOTIF_ID, notif)
    }

    private fun cancelCallNotification() {
        val nm = appContext.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        nm.cancel(NOTIF_ID)
    }
}
