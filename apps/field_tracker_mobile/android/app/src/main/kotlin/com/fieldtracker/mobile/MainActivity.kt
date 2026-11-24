package com.fieldtracker.mobile

import com.fieldtracker.mobile.tracking.TrackingBridge
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine

class MainActivity : FlutterActivity() {
    private var trackingBridge: TrackingBridge? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        trackingBridge = TrackingBridge(this, flutterEngine.dartExecutor.binaryMessenger)
    }

    override fun onDestroy() {
        trackingBridge?.dispose()
        trackingBridge = null
        super.onDestroy()
    }
}

