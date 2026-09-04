package com.esahlan.esahlan_driver

import android.app.NotificationManager
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {

    companion object {
        private const val INTENT_CHANNEL = "esahlan_intent"
    }

    private var intentChannel: MethodChannel? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        // Register CallPlugin: allows Dart _bgHandler to trigger
        // OrderCallActivity via MethodChannel('esahlan_call')
        flutterEngine.plugins.add(CallPlugin())

        // Intent bridge: exposes order_action from OrderCallActivity to Flutter
        intentChannel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, INTENT_CHANNEL)
        intentChannel!!.setMethodCallHandler { call, result ->
            when (call.method) {

                "getOrderAction" -> {
                    val action = intent?.getStringExtra("order_action")
                    result.success(action)
                }

                // Android 14 (API 34)+: request USE_FULL_SCREEN_INTENT permission
                // Opens system Settings for the user to grant it.
                "requestFullScreenIntent" -> {
                    if (Build.VERSION.SDK_INT >= 34) {
                        val nm = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
                        if (!nm.canUseFullScreenIntent()) {
                            try {
                                val i = Intent("android.settings.MANAGE_APP_USE_FULL_SCREEN_INTENTS").apply {
                                    setData(Uri.parse("package:$packageName"))
                                }
                                startActivity(i)
                            } catch (_: Exception) {}
                        }
                    }
                    result.success(null)
                }

                else -> result.notImplemented()
            }
        }
    }

    // Called when OrderCallActivity starts MainActivity from lock screen
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        val action = intent.getStringExtra("order_action")
        intentChannel?.invokeMethod("onNewIntent", action)
    }
}

