package com.esahlan.user

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.telephony.TelephonyManager
import androidx.annotation.RequiresApi
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
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    if (ActivityCompat.checkSelfPermission(this, Manifest.permission.CALL_PHONE) == PackageManager.PERMISSION_GRANTED) {
                        sendUssd(code, result)
                    } else {
                        pendingUssdCode = code
                        pendingResult = result
                        ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.CALL_PHONE), CALL_PERMISSION_CODE)
                    }
                } else {
                    // Android < 8: not supported in-app, tell Flutter to fallback
                    result.error("UNSUPPORTED", "Android < 8 not supported", null)
                }
            } else {
                result.notImplemented()
            }
        }
    }

    @RequiresApi(Build.VERSION_CODES.O)
    private fun sendUssd(code: String, result: MethodChannel.Result) {
        try {
            val tm = getSystemService(TELEPHONY_SERVICE) as TelephonyManager
            tm.sendUssdRequest(code, object : TelephonyManager.UssdResponseCallback() {
                override fun onReceiveUssdResponse(tm: TelephonyManager, request: String, response: CharSequence) {
                    Handler(Looper.getMainLooper()).post {
                        result.success(mapOf("success" to true, "response" to response.toString()))
                    }
                }
                override fun onReceiveUssdResponseFailed(tm: TelephonyManager, request: String, failureCode: Int) {
                    Handler(Looper.getMainLooper()).post {
                        result.success(mapOf("success" to false, "response" to "USSD failed (code $failureCode)"))
                    }
                }
            }, Handler(Looper.getMainLooper()))
        } catch (e: Exception) {
            result.error("ERROR", e.message, null)
        }
    }

    @RequiresApi(Build.VERSION_CODES.O)
    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == CALL_PERMISSION_CODE) {
            val code = pendingUssdCode
            val res  = pendingResult
            pendingUssdCode = null
            pendingResult   = null
            if (code != null && res != null) {
                if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                    sendUssd(code, res)
                } else {
                    res.error("PERMISSION_DENIED", "Call permission denied", null)
                }
            }
        }
    }
}
