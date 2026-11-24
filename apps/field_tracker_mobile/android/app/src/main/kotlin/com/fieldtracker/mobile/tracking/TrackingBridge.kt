package com.fieldtracker.mobile.tracking

import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.Handler
import android.os.Looper
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

class TrackingBridge(
    private val context: Context,
    messenger: BinaryMessenger,
) : MethodChannel.MethodCallHandler, EventChannel.StreamHandler {

    private val methodChannel = MethodChannel(messenger, CHANNEL_NAME)
    private val eventChannel = EventChannel(messenger, EVENT_CHANNEL_NAME)
    private val mainHandler = Handler(Looper.getMainLooper())
    private var eventSink: EventChannel.EventSink? = null

    init {
        methodChannel.setMethodCallHandler(this)
        eventChannel.setStreamHandler(this)
        TrackingRuntime.stateListener = { state ->
            mainHandler.post { eventSink?.success(state) }
        }
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "startTracking" -> {
                val args = call.arguments as? Map<*, *> ?: emptyMap<Any, Any>()
                startTracking(args)
                result.success(null)
            }

            "stopTracking" -> {
                stopTrackingService("manual_stop")
                result.success(null)
            }

            "stopExtraDuty" -> {
                stopTrackingService("manual_extra_stop")
                result.success(null)
            }

            "syncState" -> {
                val args = call.arguments as? Map<*, *> ?: emptyMap<Any, Any>()
                syncState(args)
                result.success(null)
            }

            "getState" -> {
                result.success(TrackingRuntime.currentState())
            }

            "isRunning" -> {
                result.success(TrackingRuntime.isRunning())
            }

            else -> result.notImplemented()
        }
    }

    override fun onListen(arguments: Any?, events: EventChannel.EventSink?) {
        eventSink = events
        events?.success(TrackingRuntime.currentState())
    }

    override fun onCancel(arguments: Any?) {
        eventSink = null
    }

    private fun startTracking(args: Map<*, *>) {
        val intent = Intent(context, TrackingForegroundService::class.java).apply {
            action = TrackingForegroundService.ACTION_START
            putExtra(TrackingForegroundService.EXTRA_SESSION_UUID, args["session_uuid"]?.toString())
            putExtra(TrackingForegroundService.EXTRA_SESSION_TYPE, args["session_type"]?.toString())
            putExtra(TrackingForegroundService.EXTRA_EXPIRES_AT, args["expires_at"]?.toString())
            putExtra(TrackingForegroundService.EXTRA_DEVICE_UUID, args["device_uuid"]?.toString())
            putExtra(TrackingForegroundService.EXTRA_AUTH_TOKEN, args["token"]?.toString())
            putExtra(TrackingForegroundService.EXTRA_BASE_URL, args["base_url"]?.toString())
            putExtra(TrackingForegroundService.EXTRA_CLIENT_MIDNIGHT_AT, args["client_midnight_at"]?.toString())
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.startForegroundService(intent)
        } else {
            context.startService(intent)
        }
    }

    private fun syncState(args: Map<*, *>) {
        val intent = Intent(context, TrackingForegroundService::class.java).apply {
            action = TrackingForegroundService.ACTION_SYNC
            args["token"]?.toString()?.let { putExtra(TrackingForegroundService.EXTRA_AUTH_TOKEN, it) }
            args["base_url"]?.toString()?.let { putExtra(TrackingForegroundService.EXTRA_BASE_URL, it) }
            args["device_uuid"]?.toString()?.let { putExtra(TrackingForegroundService.EXTRA_DEVICE_UUID, it) }
        }
        context.startService(intent)
    }

    private fun stopTrackingService(reason: String) {
        val intent = Intent(context, TrackingForegroundService::class.java).apply {
            action = TrackingForegroundService.ACTION_STOP
            putExtra(TrackingForegroundService.EXTRA_STOP_REASON, reason)
        }
        context.startService(intent)
    }

    fun dispose() {
        methodChannel.setMethodCallHandler(null)
        eventChannel.setStreamHandler(null)
        if (TrackingRuntime.stateListener != null) {
            TrackingRuntime.stateListener = null
        }
        eventSink = null
    }

    companion object {
        const val CHANNEL_NAME = "field_tracker/tracking"
        const val EVENT_CHANNEL_NAME = "field_tracker/tracking/events"
    }
}
