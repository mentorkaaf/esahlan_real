package com.esahlan.esahlan_driver

import android.app.Activity
import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.os.Build
import android.os.Bundle
import android.os.CountDownTimer
import android.os.VibrationEffect
import android.os.Vibrator
import android.view.Gravity
import android.view.WindowManager
import android.widget.*

/**
 * OrderCallActivity — lightweight "New Order" alert screen.
 *
 * Role: pop up INSTANTLY over the lock screen (no Flutter boot delay) and
 * inform the driver that a new order has arrived. Shows the module type,
 * order number, and delivery fee. Driver taps "SEE ORDER" → Flutter
 * IncomingOrderScreen opens (the full DoorDash-style screen with Google Maps,
 * customer info, accept/decline buttons).
 *
 * Accept / Decline are NOT here. This is a notification alert, not the decision screen.
 *
 * Killed-state flow:
 *   FCM → EsahlanMessagingService → saves to SharedPrefs + shows notification
 *   → fullScreenIntent → THIS activity (instant, over lock screen)
 *   → driver taps SEE ORDER → MainActivity → _checkPendingOrder() → IncomingOrderScreen
 *
 * Foreground flow:
 *   EsahlanMessagingService.isAppInForeground() = true → super.onMessageReceived()
 *   → Flutter onMessage → IncomingOrderScreen directly (this activity never fires)
 */
class OrderCallActivity : Activity() {

    private var mediaPlayer: MediaPlayer? = null
    private var vibrator:    Vibrator?    = null
    private var timer:       CountDownTimer? = null
    private var timerTv:     TextView?    = null
    private var secondsLeft  = 45
    private var dismissed    = false

    // FCM extras
    private var orderId    = ""
    private var orderNum   = ""
    private var fee        = "0"
    private var moduleSlug = "order"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setupWindowFlags()
        readExtras()
        buildUI()
        startAudio()
        startVibration()
        startCountdown()
    }

    // ── Show over lock screen ─────────────────────────────────────────────────
    private fun setupWindowFlags() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        }
        @Suppress("DEPRECATION")
        window.addFlags(
            WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED  or
            WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON   or
            WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON   or
            WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD
        )
    }

    private fun readExtras() {
        orderId    = intent.getStringExtra("order_id")    ?: ""
        orderNum   = intent.getStringExtra("order_number") ?: ""
        fee        = intent.getStringExtra("delivery_fee") ?: "0"
        moduleSlug = intent.getStringExtra("module_slug")  ?: "order"
    }

    // ── UI — clean alert card ─────────────────────────────────────────────────
    private fun buildUI() {
        val accent = Color.parseColor(accentHex())
        val bg     = Color.parseColor("#060B14")

        // Fullscreen dark root
        val root = FrameLayout(this)
        root.setBackgroundColor(bg)

        // Card container — centered vertically
        val card = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity     = Gravity.CENTER_HORIZONTAL
            setPadding(dp(28), dp(40), dp(28), dp(28))
            background  = GradientDrawable().apply {
                setColor(Color.parseColor("#0E1520"))
                cornerRadius = dp(24).toFloat()
                setStroke(dp(1), Color.parseColor("#1E2D42"))
            }
        }
        val cardParams = FrameLayout.LayoutParams(MATCH, WRAP).apply {
            gravity = Gravity.CENTER
            setMargins(dp(24), 0, dp(24), 0)
        }

        // ── Emoji circle ──────────────────────────────────────────────────────
        val circleFrame = FrameLayout(this).apply {
            layoutParams = LinearLayout.LayoutParams(dp(96), dp(96)).also {
                it.gravity      = Gravity.CENTER_HORIZONTAL
                it.bottomMargin = dp(22)
            }
        }
        circleFrame.addView(android.view.View(this).apply {
            layoutParams = FrameLayout.LayoutParams(MATCH, MATCH)
            background   = GradientDrawable().apply {
                shape = GradientDrawable.OVAL
                setColor(Color.argb(30, Color.red(accent), Color.green(accent), Color.blue(accent)))
                setStroke(dp(2), Color.argb(100, Color.red(accent), Color.green(accent), Color.blue(accent)))
            }
        })
        circleFrame.addView(TextView(this).apply {
            text     = moduleEmoji()
            textSize = 38f
            gravity  = Gravity.CENTER
            layoutParams = FrameLayout.LayoutParams(MATCH, MATCH)
        })
        card.addView(circleFrame)

        // ── "NEW ORDER" eyebrow ───────────────────────────────────────────────
        card.addView(TextView(this).apply {
            text          = "NEW ORDER"
            setTextColor(accent)
            textSize      = 11f
            typeface      = Typeface.DEFAULT_BOLD
            letterSpacing = 0.2f
            gravity       = Gravity.CENTER
            layoutParams  = LinearLayout.LayoutParams(MATCH, WRAP).also { it.bottomMargin = dp(8) }
        })

        // ── Module name ───────────────────────────────────────────────────────
        card.addView(TextView(this).apply {
            text        = moduleLabel()
            setTextColor(Color.WHITE)
            textSize    = 24f
            typeface    = Typeface.DEFAULT_BOLD
            gravity     = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(MATCH, WRAP).also { it.bottomMargin = dp(6) }
        })

        // ── Order number ──────────────────────────────────────────────────────
        if (orderNum.isNotEmpty()) {
            card.addView(TextView(this).apply {
                text        = "Order #$orderNum"
                setTextColor(Color.argb(100, 255, 255, 255))
                textSize    = 13f
                gravity     = Gravity.CENTER
                layoutParams = LinearLayout.LayoutParams(MATCH, WRAP).also { it.bottomMargin = dp(26) }
            })
        } else {
            card.addView(spacer(dp(26)))
        }

        // ── Fee pill ──────────────────────────────────────────────────────────
        val feeVal = fee.toDoubleOrNull() ?: 0.0
        card.addView(TextView(this).apply {
            text        = "💰  \$${String.format("%.2f", feeVal)}  delivery fee"
            setTextColor(Color.parseColor("#22C55E"))
            textSize    = 16f
            typeface    = Typeface.DEFAULT_BOLD
            gravity     = Gravity.CENTER
            setPadding(dp(20), dp(14), dp(20), dp(14))
            background  = GradientDrawable().apply {
                setColor(Color.parseColor("#0D2318"))
                cornerRadius = dp(14).toFloat()
                setStroke(dp(1), Color.parseColor("#1A4A2E"))
            }
            layoutParams = LinearLayout.LayoutParams(MATCH, WRAP).also { it.bottomMargin = dp(30) }
        })

        // ── SEE ORDER button ──────────────────────────────────────────────────
        card.addView(Button(this).apply {
            text        = "SEE ORDER  →"
            setTextColor(Color.parseColor("#060B14"))
            textSize    = 16f
            typeface    = Typeface.DEFAULT_BOLD
            isAllCaps   = false
            elevation   = 0f
            background  = GradientDrawable().apply {
                setColor(accent)
                cornerRadius = dp(16).toFloat()
            }
            layoutParams = LinearLayout.LayoutParams(MATCH, dp(56)).also { it.bottomMargin = dp(14) }
            setOnClickListener { openFlutterRingScreen() }
        })

        // ── Timer + Dismiss row ───────────────────────────────────────────────
        val bottomRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity     = Gravity.CENTER_VERTICAL
            layoutParams = LinearLayout.LayoutParams(MATCH, WRAP)
        }
        timerTv = TextView(this).apply {
            text        = "Expires in ${secondsLeft}s"
            setTextColor(Color.argb(90, 255, 255, 255))
            textSize    = 12f
            layoutParams = LinearLayout.LayoutParams(0, WRAP, 1f)
        }
        val dismissLink = TextView(this).apply {
            text        = "Dismiss"
            setTextColor(Color.argb(70, 255, 255, 255))
            textSize    = 12f
            gravity     = Gravity.CENTER
            setPadding(dp(16), dp(8), dp(16), dp(8))
            setOnClickListener { dismiss() }
        }
        bottomRow.addView(timerTv)
        bottomRow.addView(dismissLink)
        card.addView(bottomRow)

        root.addView(card, cardParams)
        setContentView(root)
    }

    // ── Open Flutter IncomingOrderScreen ──────────────────────────────────────
    private fun openFlutterRingScreen() {
        if (dismissed) return
        dismissed = true
        stopAll()
        // pending_ring_order is still in SharedPrefs — Flutter's _checkPendingOrder()
        // reads it and shows IncomingOrderScreen with full details + Accept/Decline.
        startActivity(Intent(this, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            putExtra("from_ring_alert", true)
        })
        finish()
    }

    private fun dismiss() {
        if (dismissed) return
        dismissed = true
        stopAll()
        finish()
    }

    // ── Countdown ─────────────────────────────────────────────────────────────
    private fun startCountdown() {
        timer = object : CountDownTimer(45_000, 1_000) {
            override fun onTick(remaining: Long) {
                secondsLeft = (remaining / 1_000).toInt()
                timerTv?.text = "Expires in ${secondsLeft}s"
            }
            override fun onFinish() { dismiss() }
        }.start()
    }

    // ── Audio ─────────────────────────────────────────────────────────────────
    private fun startAudio() {
        try {
            val res = resources.getIdentifier("order_ring", "raw", packageName)
            if (res == 0) return
            mediaPlayer = MediaPlayer().apply {
                setAudioAttributes(AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build())
                setDataSource(resources.openRawResourceFd(res))
                isLooping = true; prepare(); start()
            }
        } catch (_: Exception) {}
    }

    @Suppress("DEPRECATION")
    private fun startVibration() {
        vibrator = getSystemService(VIBRATOR_SERVICE) as? Vibrator ?: return
        val pattern = longArrayOf(0, 600, 300, 600, 300, 600, 300, 600)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O)
            vibrator!!.vibrate(VibrationEffect.createWaveform(pattern, 0))
        else
            vibrator!!.vibrate(pattern, 0)
    }

    private fun stopAll() {
        timer?.cancel()
        mediaPlayer?.apply { stop(); release() }
        mediaPlayer = null
        vibrator?.cancel()
    }

    // ── Module info ───────────────────────────────────────────────────────────
    private fun accentHex() = when (moduleSlug) {
        "efood"    -> "#EF4444"; "egrocery" -> "#22C55E"; "eshop"    -> "#8B5CF6"
        "eparcel"  -> "#FF8A00"; "emoving"  -> "#3B82F6"; "elaundry" -> "#06B6D4"
        "erent"    -> "#F59E0B"; else       -> "#FF8A00"
    }
    private fun moduleLabel() = when (moduleSlug) {
        "efood"    -> "eFood Delivery";  "egrocery" -> "eGrocery Delivery"
        "eshop"    -> "eShop Delivery";  "eparcel"  -> "eParcel Delivery"
        "emoving"  -> "eMoving Service"; "elaundry" -> "eLaundry Pickup"
        "erent"    -> "eRent Service";   else       -> "New Delivery"
    }
    private fun moduleEmoji() = when (moduleSlug) {
        "efood" -> "🍔"; "egrocery" -> "🛒"; "eshop"    -> "🛍"
        "eparcel" -> "🚚"; "emoving" -> "📦"; "elaundry" -> "👕"
        "erent" -> "🏠"; else -> "🚴"
    }

    // ── Util ──────────────────────────────────────────────────────────────────
    private val MATCH = LinearLayout.LayoutParams.MATCH_PARENT
    private val WRAP  = LinearLayout.LayoutParams.WRAP_CONTENT
    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()
    private fun spacer(h: Int) = android.view.View(this).apply {
        layoutParams = LinearLayout.LayoutParams(MATCH, h)
    }

    override fun onDestroy() { stopAll(); super.onDestroy() }
    @Deprecated("Deprecated in Java")
    override fun onBackPressed() { dismiss() }
}
