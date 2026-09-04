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
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.TextView
import androidx.annotation.RequiresApi

/**
 * OrderCallActivity — native full-screen incoming-call UI for new delivery orders.
 *
 * Shown via fullScreenIntent from a high-importance notification. Appears over
 * the lock screen INSTANTLY (no Flutter startup delay) when:
 *   • App is in background (process alive)
 *   • App is killed (via fullScreenIntent from CallPlugin notification)
 *   • Screen is locked
 *
 * Accept → starts MainActivity with order_action=accept in intent extras.
 * Decline → starts MainActivity with order_action=decline (auto-rejects via Flutter).
 *
 * On both actions, MainShell.checkPendingOrder() fires and reads the pre-saved
 * SharedPreferences order, then processes the chosen action.
 */
class OrderCallActivity : Activity() {

    private var mediaPlayer: MediaPlayer? = null
    private var vibrator: Vibrator? = null
    private var timer: CountDownTimer? = null
    private var secondsLeft = 45
    private var actionTaken = false

    // ── Data from intent ──────────────────────────────────────────────────────
    private var orderId    = ""
    private var orderNum   = ""
    private var fee        = "0"
    private var pickupD    = ""
    private var delivD     = ""
    private var pickupAddr = ""
    private var delivAddr  = ""
    private var moduleSlug = "order"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setupWindowFlags()
        readIntentExtras()
        buildUI()
        startAudio()
        startVibration()
    }

    // ── Window flags: show over lock screen ───────────────────────────────────
    private fun setupWindowFlags() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        }
        @Suppress("DEPRECATION")
        window.addFlags(
            WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
            WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON  or
            WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON  or
            WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD
        )
    }

    private fun readIntentExtras() {
        orderId    = intent.getStringExtra("order_id")           ?: ""
        orderNum   = intent.getStringExtra("order_number")       ?: ""
        fee        = intent.getStringExtra("delivery_fee")       ?: "0"
        pickupD    = intent.getStringExtra("pickup_district")    ?: ""
        delivD     = intent.getStringExtra("delivery_district")  ?: ""
        pickupAddr = intent.getStringExtra("pickup_address")     ?: ""
        delivAddr  = intent.getStringExtra("delivery_address")   ?: ""
        moduleSlug = intent.getStringExtra("module_slug")        ?: "order"
    }

    // ── Build the full-screen UI programmatically ─────────────────────────────
    private fun buildUI() {
        val moduleEmoji = when (moduleSlug) {
            "efood"    -> "🍔"; "egrocery" -> "🛒"; "eshop" -> "🛍"
            "eparcel"  -> "📦"; "emoving"  -> "🚚"; "elaundry" -> "👕"
            "erent"    -> "🏠"; else       -> "🚴"
        }
        val moduleLabel = when (moduleSlug) {
            "efood"    -> "eFood Delivery";   "egrocery" -> "eGrocery Delivery"
            "eshop"    -> "eShop Delivery";   "eparcel"  -> "eParcel Delivery"
            "emoving"  -> "eMoving Service";  "elaundry" -> "eLaundry Pickup"
            "erent"    -> "eRent Service";    else       -> "New Delivery"
        }
        val accentHex = when (moduleSlug) {
            "efood"    -> "#EF4444"; "egrocery" -> "#22C55E"; "eshop"    -> "#8B5CF6"
            "eparcel"  -> "#FF8A00"; "emoving"  -> "#3B82F6"; "elaundry" -> "#06B6D4"
            "erent"    -> "#F59E0B"; else       -> "#FF8A00"
        }
        val accent = Color.parseColor(accentHex)

        // Root: dark background, vertical layout
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity     = Gravity.CENTER_HORIZONTAL
            setBackgroundColor(Color.parseColor("#060B14"))
            setPadding(dp(24), dp(56), dp(24), dp(32))
        }

        // ── "NEW ORDER" chip ─────────────────────────────────────────────────
        root.addView(chip("NEW ORDER", accent))
        root.addView(spacer(dp(24)))

        // ── Module emoji ─────────────────────────────────────────────────────
        root.addView(TextView(this).apply {
            text     = moduleEmoji
            textSize = 72f
            gravity  = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(WRAP, WRAP).also { it.gravity = Gravity.CENTER_HORIZONTAL }
        })
        root.addView(spacer(dp(12)))

        // ── Module label ─────────────────────────────────────────────────────
        root.addView(label(moduleLabel, 22f, Color.WHITE, true))
        root.addView(spacer(dp(6)))

        // ── Order number ─────────────────────────────────────────────────────
        if (orderNum.isNotEmpty()) {
            root.addView(label("Order #$orderNum", 13f, Color.parseColor("#88FFFFFF"), false))
        }
        root.addView(spacer(dp(28)))

        // ── Earnings card ─────────────────────────────────────────────────────
        val green = Color.parseColor("#22C55E")
        root.addView(feeCard("\$$fee", green))
        root.addView(spacer(dp(16)))

        // ── Route ─────────────────────────────────────────────────────────────
        val pickupText  = if (pickupD.isNotEmpty()) pickupD else (if (pickupAddr.isNotEmpty()) pickupAddr else "")
        val delivText   = if (delivD.isNotEmpty())  delivD  else (if (delivAddr.isNotEmpty())  delivAddr  else "")
        if (pickupText.isNotEmpty() || delivText.isNotEmpty()) {
            root.addView(routeView(pickupText, delivText, accent, green))
            root.addView(spacer(dp(16)))
        }

        // ── Flexible spacer ───────────────────────────────────────────────────
        root.addView(View(this).apply {
            layoutParams = LinearLayout.LayoutParams(MATCH, 0, 1f)
        })

        // ── Countdown ─────────────────────────────────────────────────────────
        val countdownView = TextView(this).apply {
            text = "Expires in ${secondsLeft}s"
            setTextColor(Color.parseColor("#22C55E"))
            textSize = 13f
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(MATCH, WRAP)
        }
        root.addView(countdownView)
        root.addView(spacer(dp(16)))

        // ── Buttons ────────────────────────────────────────────────────────────
        val btnRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity     = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(MATCH, dp(60))
        }
        btnRow.addView(actionBtn("✕  Decline", Color.parseColor("#1C2234"), Color.parseColor("#AAFFFFFF"), weight=2f, right=dp(10)) { decline() })
        btnRow.addView(actionBtn("✓  ACCEPT",  Color.parseColor("#22C55E"), Color.WHITE,                  weight=3f, right=0) { accept() })
        root.addView(btnRow)

        setContentView(root)

        // Start countdown now that views exist
        startCountdown(countdownView)
    }

    // ── UI helpers ─────────────────────────────────────────────────────────────
    private val WRAP  = LinearLayout.LayoutParams.WRAP_CONTENT
    private val MATCH = LinearLayout.LayoutParams.MATCH_PARENT

    private fun dp(v: Int): Int = (v * resources.displayMetrics.density).toInt()

    private fun chip(text: String, accent: Int) = TextView(this).apply {
        this.text = text
        setTextColor(accent)
        textSize = 11f
        letterSpacing = 0.14f
        typeface = Typeface.DEFAULT_BOLD
        gravity = Gravity.CENTER
        setPadding(dp(20), dp(8), dp(20), dp(8))
        background = GradientDrawable().apply {
            setColor(Color.argb(26, Color.red(accent), Color.green(accent), Color.blue(accent)))
            cornerRadius = dp(40).toFloat()
            setStroke(dp(1), Color.argb(136, Color.red(accent), Color.green(accent), Color.blue(accent)))
        }
        layoutParams = LinearLayout.LayoutParams(WRAP, WRAP).also { it.gravity = Gravity.CENTER_HORIZONTAL }
    }

    private fun label(text: String, size: Float, color: Int, bold: Boolean) = TextView(this).apply {
        this.text = text
        textSize = size
        setTextColor(color)
        if (bold) typeface = Typeface.DEFAULT_BOLD
        gravity = Gravity.CENTER
        layoutParams = LinearLayout.LayoutParams(MATCH, WRAP)
    }

    private fun spacer(h: Int) = View(this).apply {
        layoutParams = LinearLayout.LayoutParams(MATCH, h)
    }

    private fun feeCard(feeText: String, green: Int) = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        gravity     = Gravity.CENTER
        setPadding(dp(40), dp(20), dp(40), dp(20))
        background = GradientDrawable().apply {
            setColor(Color.argb(26, Color.red(green), Color.green(green), Color.blue(green)))
            cornerRadius = dp(20).toFloat()
            setStroke(dp(1), Color.argb(51, Color.red(green), Color.green(green), Color.blue(green)))
        }
        layoutParams = LinearLayout.LayoutParams(WRAP, WRAP).also { it.gravity = Gravity.CENTER_HORIZONTAL }
        addView(TextView(context).apply {
            text = feeText
            setTextColor(green)
            textSize = 32f
            typeface = Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER
        })
        addView(TextView(context).apply {
            text = "EARNINGS"
            setTextColor(Color.argb(150, Color.red(green), Color.green(green), Color.blue(green)))
            textSize = 10f
            letterSpacing = 0.12f
            gravity = Gravity.CENTER
        })
    }

    private fun routeView(pickup: String, deliv: String, pickupColor: Int, delivColor: Int) = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        setPadding(dp(16), dp(14), dp(16), dp(14))
        background = GradientDrawable().apply {
            setColor(Color.parseColor("#0DFFFFFF"))
            cornerRadius = dp(16).toFloat()
            setStroke(dp(1), Color.parseColor("#15FFFFFF"))
        }
        if (pickup.isNotEmpty()) {
            addView(routeRow("📍 PICKUP", pickup, pickupColor))
            addView(spacer(dp(8)))
        }
        if (deliv.isNotEmpty()) {
            addView(routeRow("🏁 DELIVER", deliv, delivColor))
        }
    }

    private fun routeRow(label: String, address: String, color: Int) = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        addView(TextView(context).apply {
            text = label
            setTextColor(color)
            textSize = 10f
            letterSpacing = 0.12f
            typeface = Typeface.DEFAULT_BOLD
        })
        addView(TextView(context).apply {
            text = address
            setTextColor(Color.parseColor("#CCFFFFFF"))
            textSize = 13f
        })
    }

    private fun actionBtn(text: String, bg: Int, textColor: Int, weight: Float, right: Int, onClick: () -> Unit) =
        Button(this).apply {
            this.text = text
            setTextColor(textColor)
            textSize = if (weight > 2f) 16f else 14f
            typeface = Typeface.DEFAULT_BOLD
            background = GradientDrawable().apply {
                setColor(bg)
                cornerRadius = dp(18).toFloat()
            }
            isAllCaps = false
            layoutParams = LinearLayout.LayoutParams(0, MATCH, weight).also {
                it.marginEnd = right
            }
            setOnClickListener { onClick() }
        }

    // ── Audio & vibration ─────────────────────────────────────────────────────
    private fun startAudio() {
        try {
            val res = resources.getIdentifier("order_ring", "raw", packageName)
            if (res == 0) return
            mediaPlayer = MediaPlayer().apply {
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build()
                )
                setDataSource(resources.openRawResourceFd(res))
                isLooping = true
                prepare()
                start()
            }
        } catch (_: Exception) {}
    }

    @Suppress("DEPRECATION")
    private fun startVibration() {
        vibrator = getSystemService(VIBRATOR_SERVICE) as? Vibrator ?: return
        val pattern = longArrayOf(0, 600, 300, 600, 300, 600, 300, 600)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            vibrator!!.vibrate(VibrationEffect.createWaveform(pattern, 0))
        } else {
            vibrator!!.vibrate(pattern, 0)
        }
    }

    // ── Countdown ─────────────────────────────────────────────────────────────
    private fun startCountdown(view: TextView) {
        timer = object : CountDownTimer(45_000, 1_000) {
            override fun onTick(remaining: Long) {
                secondsLeft = (remaining / 1_000).toInt()
                view.text = "Expires in ${secondsLeft}s"
                if (secondsLeft <= 10) view.setTextColor(Color.parseColor("#EF4444"))
            }
            override fun onFinish() { decline() }
        }.start()
    }

    // ── Accept / Decline ──────────────────────────────────────────────────────
    private fun accept() {
        if (actionTaken) return
        actionTaken = true
        stopAll()
        startMainWithAction("accept")
    }

    private fun decline() {
        if (actionTaken) return
        actionTaken = true
        stopAll()
        startMainWithAction("decline")
    }

    /**
     * Start MainActivity with an action flag. Flutter's MainShell reads this via
     * checkPendingOrder() + the 'pending_ring_action' SharedPreferences key and
     * auto-accepts or auto-rejects without showing the ring screen.
     */
    private fun startMainWithAction(action: String) {
        // Write action to SharedPreferences so Flutter reads it on resume
        getSharedPreferences("FlutterSharedPreferences", MODE_PRIVATE).edit()
            .putString("flutter.pending_ring_action", action)
            .apply()

        startActivity(Intent(this, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            putExtra("order_action", action)
        })
        finish()
    }

    private fun stopAll() {
        timer?.cancel()
        mediaPlayer?.apply { stop(); release() }
        mediaPlayer = null
        vibrator?.cancel()
    }

    override fun onDestroy() { stopAll(); super.onDestroy() }

    @Deprecated("Deprecated in Java")
    override fun onBackPressed() { decline() }
}
