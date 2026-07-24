package com.esahlan.user

import android.graphics.*
import android.util.Log
import com.cloudwebrtc.webrtc.FlutterWebRTCPlugin
import com.cloudwebrtc.webrtc.video.LocalVideoTrack
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import org.webrtc.VideoFrame
import java.nio.ByteBuffer

class LiveVideoFilterPlugin : FlutterPlugin, MethodChannel.MethodCallHandler {

    private lateinit var channel: MethodChannel
    private var processor: ColorMatrixFrameProcessor? = null
    private var currentTrack: LocalVideoTrack? = null

    override fun onAttachedToEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel = MethodChannel(binding.binaryMessenger, "com.esahlan.live/filter")
        channel.setMethodCallHandler(this)
    }

    override fun onDetachedFromEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel.setMethodCallHandler(null)
        stopFilter()
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "startFilter" -> {
                val trackId = call.argument<String>("trackId") ?: run {
                    result.error("INVALID", "trackId required", null); return
                }
                val filterName = call.argument<String>("filter") ?: "none"
                try {
                    startFilter(trackId, filterName)
                    result.success(trackId) // same track, modified in-place
                } catch (e: Exception) {
                    Log.e("LiveFilter", "startFilter error: ${e.message}", e)
                    result.success(trackId) // fallback: unfiltered track
                }
            }
            "setFilter" -> {
                val filterName = call.argument<String>("filter") ?: "none"
                processor?.setFilter(filterName)
                result.success(null)
            }
            "stopFilter" -> {
                stopFilter()
                result.success(null)
            }
            else -> result.notImplemented()
        }
    }

    private fun startFilter(trackId: String, filterName: String) {
        val webRTCPlugin = FlutterWebRTCPlugin.sharedSingleton
            ?: throw IllegalStateException("FlutterWebRTCPlugin not initialized")

        // getLocalTrack is public on FlutterWebRTCPlugin — no reflection needed
        val localTrack = webRTCPlugin.getLocalTrack(trackId) as? LocalVideoTrack
            ?: throw IllegalStateException("Track $trackId is not a LocalVideoTrack")

        // Remove any existing processor
        processor?.let { localTrack.removeProcessor(it) }

        // Add our color matrix processor — runs BEFORE encoder, viewers see it
        val newProcessor = ColorMatrixFrameProcessor(filterName)
        localTrack.addProcessor(newProcessor)

        processor = newProcessor
        currentTrack = localTrack

        Log.d("LiveFilter", "Filter '$filterName' applied to track $trackId (viewers will see it)")
    }

    private fun stopFilter() {
        val p = processor ?: return
        currentTrack?.removeProcessor(p)
        processor = null
        currentTrack = null
    }
}

// ── Frame processor: applies ColorMatrix to each frame ───────────────────────

class ColorMatrixFrameProcessor(filterName: String) : LocalVideoTrack.ExternalVideoFrameProcessing {

    @Volatile private var matrix: FloatArray? = getMatrix(filterName)

    fun setFilter(name: String) {
        matrix = getMatrix(name)
    }

    override fun onFrame(frame: VideoFrame): VideoFrame {
        val m = matrix ?: return frame // null = no filter (pass-through)

        return try {
            applyMatrix(frame, m)
        } catch (e: Exception) {
            Log.w("LiveFilter", "Frame processing error: ${e.message}")
            frame
        }
    }

    private fun applyMatrix(frame: VideoFrame, m: FloatArray): VideoFrame {
        val i420 = frame.buffer.toI420() ?: return frame

        return try {
            val w = i420.width
            val h = i420.height

            // I420 → ARGB Bitmap
            val argb = IntArray(w * h)
            yuv420ToArgb(i420, argb, w, h)
            val bmp = Bitmap.createBitmap(argb, w, h, Bitmap.Config.ARGB_8888)

            // Apply ColorMatrix
            val out = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
            Canvas(out).drawBitmap(bmp, 0f, 0f, Paint().apply {
                colorFilter = ColorMatrixColorFilter(android.graphics.ColorMatrix(m))
            })
            bmp.recycle()

            // ARGB Bitmap → I420 buffer
            val pixels = IntArray(w * h)
            out.getPixels(pixels, 0, w, 0, 0, w, h)
            out.recycle()

            val buf = argbToJavaI420(pixels, w, h)
            VideoFrame(buf, frame.rotation, frame.timestampNs)
        } finally {
            i420.release()
        }
    }

    // ── YUV ↔ RGB conversion ─────────────────────────────────────────────────

    private fun yuv420ToArgb(i420: org.webrtc.VideoFrame.I420Buffer, argb: IntArray, w: Int, h: Int) {
        val yb = ByteArray(i420.strideY * h)
        val ub = ByteArray(i420.strideU * ((h + 1) / 2))
        val vb = ByteArray(i420.strideV * ((h + 1) / 2))
        i420.dataY.get(yb); i420.dataU.get(ub); i420.dataV.get(vb)

        for (j in 0 until h) {
            for (i in 0 until w) {
                val y = yb[j * i420.strideY + i].toInt() and 0xFF
                val u = (ub[(j / 2) * i420.strideU + (i / 2)].toInt() and 0xFF) - 128
                val v = (vb[(j / 2) * i420.strideV + (i / 2)].toInt() and 0xFF) - 128
                val r = (y + 1.402 * v).toInt().coerceIn(0, 255)
                val g = (y - 0.344136 * u - 0.714136 * v).toInt().coerceIn(0, 255)
                val b = (y + 1.772 * u).toInt().coerceIn(0, 255)
                argb[j * w + i] = (0xFF shl 24) or (r shl 16) or (g shl 8) or b
            }
        }
    }

    private fun argbToJavaI420(argb: IntArray, w: Int, h: Int): org.webrtc.VideoFrame.Buffer {
        val yArr = ByteArray(w * h)
        val uArr = ByteArray((w / 2) * (h / 2))
        val vArr = ByteArray((w / 2) * (h / 2))

        for (j in 0 until h) {
            for (i in 0 until w) {
                val px = argb[j * w + i]
                val r = (px shr 16) and 0xFF
                val g = (px shr 8) and 0xFF
                val b = px and 0xFF
                val y = (0.299 * r + 0.587 * g + 0.114 * b).toInt().coerceIn(0, 255)
                yArr[j * w + i] = y.toByte()
                if (j % 2 == 0 && i % 2 == 0) {
                    uArr[(j / 2) * (w / 2) + (i / 2)] = (-0.14713 * r - 0.28886 * g + 0.436 * b + 128).toInt().coerceIn(0, 255).toByte()
                    vArr[(j / 2) * (w / 2) + (i / 2)] = (0.615 * r - 0.51499 * g - 0.10001 * b + 128).toInt().coerceIn(0, 255).toByte()
                }
            }
        }

        return org.webrtc.JavaI420Buffer.wrap(
            w, h,
            ByteBuffer.wrap(yArr), w,
            ByteBuffer.wrap(uArr), w / 2,
            ByteBuffer.wrap(vArr), w / 2,
        ) {}
    }

    companion object {
        fun getMatrix(name: String): FloatArray? = when (name) {
            "none"    -> null
            "beauty"  -> floatArrayOf(1.05f,0f,0f,0f,18f, 0f,1.05f,0f,0f,14f, 0f,0f,1.05f,0f,12f, 0f,0f,0f,1f,0f)
            "warm"    -> floatArrayOf(1.25f,0f,0f,0f,20f, 0f,1.08f,0f,0f,8f, 0f,0f,0.80f,0f,-15f, 0f,0f,0f,1f,0f)
            "cool"    -> floatArrayOf(0.82f,0f,0f,0f,-12f, 0f,1.02f,0f,0f,8f, 0f,0f,1.25f,0f,20f, 0f,0f,0f,1f,0f)
            "vivid"   -> floatArrayOf(1.40f,-0.15f,-0.15f,0f,5f, -0.10f,1.40f,-0.10f,0f,5f, -0.15f,-0.15f,1.40f,0f,5f, 0f,0f,0f,1f,0f)
            "vintage" -> floatArrayOf(0.85f,0.15f,0.08f,0f,12f, 0.06f,0.82f,0.06f,0f,10f, 0.02f,0.04f,0.70f,0f,6f, 0f,0f,0f,1f,0f)
            "rose"    -> floatArrayOf(1.15f,0.05f,0.05f,0f,18f, 0f,0.92f,0.08f,0f,8f, 0f,0f,0.88f,0f,-5f, 0f,0f,0f,1f,0f)
            "drama"   -> floatArrayOf(1.30f,-0.10f,-0.10f,0f,-20f, -0.10f,1.20f,-0.10f,0f,-15f, -0.10f,-0.10f,1.20f,0f,-15f, 0f,0f,0f,1f,0f)
            else      -> null
        }
    }
}
