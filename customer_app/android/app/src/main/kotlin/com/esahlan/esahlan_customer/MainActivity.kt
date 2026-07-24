package com.esahlan.user

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.core.app.ActivityCompat
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {

    private val USSD_CHANNEL = "com.esahlan.app/ussd"
    private val CALL_PERMISSION_CODE = 1001
    private var pendingUssdCode: String? = null
    private var pendingResult: MethodChannel.Result? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        flutterEngine.plugins.add(LiveVideoFilterPlugin())

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, USSD_CHANNEL).setMethodCallHandler { call, result ->
            if (call.method == "dialUssd") {
                val code = call.argument<String>("code") ?: run {
                    result.error("INVALID", "No USSD code", null); return@setMethodCallHandler
                }
                if (ActivityCompat.checkSelfPermission(this, Manifest.permission.CALL_PHONE) == PackageManager.PERMISSION_GRANTED) {
                    dialUssdViaIntent(code, result)
                } else {
                    pendingUssdCode = code
                    pendingResult = result
                    ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.CALL_PHONE), CALL_PERMISSION_CODE)
                }
            } else {
                result.notImplemented()
            }
        }
    }

    // Use ACTION_CALL intent so the system places the USSD silently and shows
    // the carrier's multi-session USSD dialog on top of the app for PIN entry.
    private fun dialUssdViaIntent(code: String, result: MethodChannel.Result) {
        try {
            val encoded = code.replace("#", Uri.encode("#"))
            val intent = Intent(Intent.ACTION_CALL, Uri.parse("tel:$encoded"))
            startActivity(intent)
            result.success(mapOf("success" to true, "response" to ""))
        } catch (e: Exception) {
            result.error("ERROR", e.message, null)
        }
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == CALL_PERMISSION_CODE) {
            val code = pendingUssdCode
            val res  = pendingResult
            pendingUssdCode = null
            pendingResult   = null
            if (code != null && res != null) {
                if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                    dialUssdViaIntent(code, res)
                } else {
                    res.error("PERMISSION_DENIED", "Call permission denied", null)
                }
            }
        }
    }
}
