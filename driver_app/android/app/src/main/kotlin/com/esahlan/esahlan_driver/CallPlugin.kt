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
        const val CHANNEL_ID  = "esahlan_order_call_v1"
        const val NOTIF_ID    = 77701
        private const val METHOD_CH   = "esahlan_call"

        // ── Static helpers — callable from EsahlanMessagingService (no Flutter needed) ──

        fun ensureNotificationChannelStatic(context: Context) {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
            val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            if (nm.getNotificationChannel(CHANNEL_ID) != null) return
            val ch = NotificationChannel(
                CHANNEL_ID,
                "Incoming Order Call",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description          = "Shows a full-screen alarm when a new delivery order arrives."
                enableLights(true)
                lightColor           = Color.parseColor("#FF8A00")
                enableVibration(true)
                vibrationPattern     = longArrayOf(0, 500, 200, 500, 200, 500, 200, 500)
                lockscreenVisibility = NotificationCompat.VISIBILITY_PUBLIC
            }
            nm.createNotificationChannel(ch)
        }

        fun showCallNotificationStatic(context: Context, data: Map<String, String>) {
            ensureNotificationChannelStatic(context)
            val orderNum = data["order_number"] ?: ""
            val fee      = data["delivery_fee"]  ?: "0"

            // Launch MainActivity (Flutter) directly — no intermediate OrderCallActivity.
            // Flutter starts, main_shell._checkPendingOrder() reads the SharedPrefs order
            // and navigates to IncomingOrderScreen (the new DoorDash-style Flutter screen).
            val callIntent = Intent(context, MainActivity::class.java).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
                putExtra("ring_order", true)   // signal to MainActivity that this is a ring launch
            }
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M)
                        PendingIntent.FLAG_IMMUTABLE else 0

            val fullScreenPI = PendingIntent.getActivity(context, NOTIF_ID,     callIntent, flags)
            val contentPI    = PendingIntent.getActivity(context, NOTIF_ID + 1, callIntent, flags)

            val notif = NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_dialer)
                .setContentTitle("🚴 New Order — Tap to accept")
                .setContentText(
                    buildString {
                        if (orderNum.isNotEmpty()) append("Order #$orderNum  •  ")
                        append("\$$fee delivery fee")
                    }
                )
                .setPriority(NotificationCompat.PRIORITY_MAX)
                .setCategory(NotificationCompat.CATEGORY_CALL)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setFullScreenIntent(fullScreenPI, true)
                .setContentIntent(contentPI)
                .setOngoing(true)
                .setAutoCancel(false)
                .setTimeoutAfter(55_000)
                .setColor(Color.parseColor("#FF8A00"))
                .build()

            val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            nm.notify(NOTIF_ID, notif)
        }

        fun cancelCallNotificationStatic(context: Context) {
            val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            nm.cancel(NOTIF_ID)
        }
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
        ensureNotificationChannelStatic(appContext)
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

    // ── Show / cancel — delegate to static companion methods ─────────────────
    private fun showCallNotification(data: Map<String, String>) =
        showCallNotificationStatic(appContext, data)

    private fun cancelCallNotification() =
        cancelCallNotificationStatic(appContext)
}
