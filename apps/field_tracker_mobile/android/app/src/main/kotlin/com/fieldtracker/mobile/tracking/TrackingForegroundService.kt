package com.fieldtracker.mobile.tracking

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.Handler
import android.os.HandlerThread
import android.os.IBinder
import android.os.Looper
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedOutputStream
import java.io.BufferedReader
import java.net.HttpURLConnection
import java.net.URL
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.UUID
import java.util.concurrent.ConcurrentLinkedQueue
import java.util.concurrent.atomic.AtomicInteger
import kotlin.math.max

class TrackingForegroundService : Service(), LocationListener {

    private val mainHandler = Handler(Looper.getMainLooper())
    private var workerThread: HandlerThread? = null
    private var workerHandler: Handler? = null
    private var locationManager: LocationManager? = null

    private var sessionUuid: String? = null
    private var sessionType: String? = null
    private var expiresAt: String? = null
    private var deviceUuid: String? = null
    private var authToken: String? = null
    private var baseUrl: String? = null
    private var clientMidnightAt: String? = null

    private val buffer = ConcurrentLinkedQueue<Map<String, Any?>>()
    private val seq = AtomicInteger(0)
    private var flushRunnable: Runnable? = null
    private var expiryRunnable: Runnable? = null
    private var midnightRunnable: Runnable? = null
    private var consecutiveFailures: Int = 0
    private var nextRetryAtMillis: Long = 0L

    override fun onCreate() {
        super.onCreate()
        locationManager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
        workerThread = HandlerThread("tracking-worker").also { it.start() }
        workerHandler = Handler(workerThread!!.looper)
        createNotificationChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_START -> handleStart(intent)
            ACTION_STOP -> handleStop(intent.getStringExtra(EXTRA_STOP_REASON) ?: "stopped")
            ACTION_SYNC -> {
                if (intent.hasExtra(EXTRA_AUTH_TOKEN)) {
                    authToken = intent.getStringExtra(EXTRA_AUTH_TOKEN) ?: authToken
                }
                if (intent.hasExtra(EXTRA_BASE_URL)) {
                    baseUrl = intent.getStringExtra(EXTRA_BASE_URL) ?: baseUrl
                }
                if (intent.hasExtra(EXTRA_DEVICE_UUID)) {
                    deviceUuid = intent.getStringExtra(EXTRA_DEVICE_UUID) ?: deviceUuid
                }
                TrackingRuntime.publishState()
            }
            else -> {
                // Resume/reconcile hook: if service restarted by system, try to restore.
                restoreAndReconcile()
            }
        }
        return START_STICKY
    }

    private fun handleStart(intent: Intent) {
        sessionUuid = intent.getStringExtra(EXTRA_SESSION_UUID) ?: sessionUuid
        sessionType = intent.getStringExtra(EXTRA_SESSION_TYPE) ?: sessionType
        expiresAt = intent.getStringExtra(EXTRA_EXPIRES_AT) ?: expiresAt
        deviceUuid = intent.getStringExtra(EXTRA_DEVICE_UUID) ?: deviceUuid
        authToken = intent.getStringExtra(EXTRA_AUTH_TOKEN) ?: authToken
        baseUrl = intent.getStringExtra(EXTRA_BASE_URL) ?: baseUrl
        clientMidnightAt = intent.getStringExtra(EXTRA_CLIENT_MIDNIGHT_AT) ?: clientMidnightAt

        consecutiveFailures = 0
        nextRetryAtMillis = 0L
        persistState()
        startForeground(NOTIFICATION_ID, buildNotification("Tracking active"))
        TrackingRuntime.setRunning(
            isRunning = true,
            sessionUuid = sessionUuid,
            sessionType = sessionType,
            expiresAt = expiresAt,
            stopReason = null,
        )

        startLocationUpdates()
        scheduleFlush()
        scheduleExpiryStop()
        scheduleMidnightStop()
    }

    private fun handleStop(reason: String) {
        flushRemainingAsync {
            stopLocationUpdates()
            cancelSchedules()
            TrackingRuntime.clear(reason)
            clearPersistedState()
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
        }
    }

    private fun startLocationUpdates() {
        if (!hasLocationPermission()) {
            TrackingRuntime.clear("missing_location_permission")
            clearPersistedState()
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return
        }
        stopLocationUpdates()
        try {
            val lm = locationManager ?: return
            val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
                .filter { lm.isProviderEnabled(it) }
            if (providers.isEmpty()) {
                // Try anyway; emulator/device may still report.
                if (lm.allProviders.contains(LocationManager.GPS_PROVIDER)) {
                    lm.requestLocationUpdates(LocationManager.GPS_PROVIDER, MIN_INTERVAL_MS, MIN_DISTANCE_M, this, Looper.getMainLooper())
                }
                return
            }
            providers.forEach { provider ->
                lm.requestLocationUpdates(provider, MIN_INTERVAL_MS, MIN_DISTANCE_M, this, Looper.getMainLooper())
            }
        } catch (_: SecurityException) {
            TrackingRuntime.setRunning(isRunning = false, stopReason = "missing_location_permission")
            stopSelf()
        } catch (_: Exception) {
            // Keep service alive; sync/retry can recover.
        }
    }

    private fun stopLocationUpdates() {
        try {
            locationManager?.removeUpdates(this)
        } catch (_: Exception) {
        }
    }

    override fun onLocationChanged(location: Location) {
        val sid = sessionUuid ?: return
        val point = mapOf<String, Any?>(
            "event_uuid" to UUID.randomUUID().toString(),
            "session_uuid" to sid,
            "sequence_number" to seq.incrementAndGet(),
            "latitude" to location.latitude,
            "longitude" to location.longitude,
            "accuracy" to if (location.hasAccuracy()) location.accuracy.toDouble() else null,
            "speed" to if (location.hasSpeed()) location.speed.toDouble() else null,
            "bearing" to if (location.hasBearing()) location.bearing.toDouble() else null,
            "altitude" to if (location.hasAltitude()) location.altitude else null,
            "battery_level" to null,
            "recorded_at" to isoUtc(location.time),
        )
        buffer.add(point)
        if (buffer.size >= BATCH_SIZE) {
            flushAsync()
        }
    }

    private fun scheduleFlush() {
        flushRunnable?.let { workerHandler?.removeCallbacks(it) }
        val r = object : Runnable {
            override fun run() {
                flushBuffer()
                workerHandler?.postDelayed(this, FLUSH_INTERVAL_MS)
            }
        }
        flushRunnable = r
        workerHandler?.postDelayed(r, FLUSH_INTERVAL_MS)
    }

    private fun scheduleExpiryStop() {
        expiryRunnable?.let { mainHandler.removeCallbacks(it) }
        val exp = expiresAt ?: return
        val delay = millisUntil(exp)
        if (delay == null) return
        val r = Runnable {
            if (TrackingRuntime.isRunning()) {
                handleStop("duty_end_expired")
            }
        }
        expiryRunnable = r
        if (delay <= 0) {
            mainHandler.post(r)
        } else {
            mainHandler.postDelayed(r, delay)
        }
    }

    private fun scheduleMidnightStop() {
        midnightRunnable?.let { mainHandler.removeCallbacks(it) }
        if (sessionType != "extra_duty") return
        val midnightIso = clientMidnightAt ?: nextLocalMidnightIso()
        val delay = millisUntil(midnightIso) ?: return
        val r = Runnable {
            if (TrackingRuntime.isRunning() && sessionType == "extra_duty") {
                handleStop("midnight_auto_stopped")
            }
        }
        midnightRunnable = r
        if (delay <= 0) {
            mainHandler.post(r)
        } else {
            mainHandler.postDelayed(r, delay)
        }
    }

    private fun millisUntil(iso: String): Long? {
        return try {
            val target = parseIsoMillis(iso) ?: return null
            max(0L, target - System.currentTimeMillis())
        } catch (_: Exception) {
            null
        }
    }

    private fun nextLocalMidnightIso(): String {
        val cal = Calendar.getInstance().apply {
            add(Calendar.DAY_OF_YEAR, 1)
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        return isoUtc(cal.timeInMillis)
    }

    private fun isoUtc(millis: Long): String {
        val fmt = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.US)
        fmt.timeZone = TimeZone.getTimeZone("UTC")
        return fmt.format(Date(millis))
    }

    private fun parseIsoMillis(iso: String): Long? {
        val patterns = listOf(
            "yyyy-MM-dd'T'HH:mm:ss.SSSXXX",
            "yyyy-MM-dd'T'HH:mm:ssXXX",
            "yyyy-MM-dd'T'HH:mm:ss.SSS'Z'",
            "yyyy-MM-dd'T'HH:mm:ss'Z'",
        )
        for (pattern in patterns) {
            try {
                val fmt = SimpleDateFormat(pattern, Locale.US)
                fmt.timeZone = TimeZone.getTimeZone("UTC")
                val parsed = fmt.parse(iso)
                if (parsed != null) return parsed.time
            } catch (_: Exception) {
            }
        }
        return null
    }

    private fun flushAsync() {
        workerHandler?.post { flushBuffer() }
    }

    private fun flushRemainingAsync(onDone: () -> Unit) {
        val handler = workerHandler
        if (handler == null) {
            onDone()
            return
        }
        handler.post {
            flushBuffer()
            mainHandler.post(onDone)
        }
    }

    private enum class BatchPostResult { SUCCESS, TRANSIENT_FAILURE, TERMINAL_AUTH_FAILURE }

    private fun flushBuffer() {
        val token = authToken
        val base = baseUrl
        val devUuid = deviceUuid
        if (token.isNullOrBlank() || base.isNullOrBlank() || devUuid.isNullOrBlank()) return
        if (buffer.isEmpty()) return
        if (System.currentTimeMillis() < nextRetryAtMillis) return

        val batch = mutableListOf<Map<String, Any?>>()
        while (batch.size < MAX_BATCH && !buffer.isEmpty()) {
            val p = buffer.poll() ?: break
            batch.add(p)
        }
        if (batch.isEmpty()) return

        when (postBatch(base, token, devUuid, batch)) {
            BatchPostResult.SUCCESS -> {
                consecutiveFailures = 0
                nextRetryAtMillis = 0L
            }
            BatchPostResult.TERMINAL_AUTH_FAILURE -> {
                // Token revoked/invalid: stop tracking and wait for app resync.
                buffer.clear()
                mainHandler.post { handleTerminalAuthFailure() }
            }
            BatchPostResult.TRANSIENT_FAILURE -> {
                // Requeue at end best-effort; order may degrade but data preserved.
                batch.forEach { buffer.add(it) }
                consecutiveFailures += 1
                val exp = minOf(consecutiveFailures - 1, 6)
                val backoff = minOf(RETRY_MAX_MS, RETRY_BASE_MS * (1L shl exp))
                val jitter = kotlin.random.Random.nextLong(0L, max(1L, backoff / 4))
                nextRetryAtMillis = System.currentTimeMillis() + backoff + jitter
            }
        }
    }

    private fun handleTerminalAuthFailure() {
        if (!TrackingRuntime.isRunning()) return
        cancelSchedules()
        stopLocationUpdates()
        TrackingRuntime.clear("auth_revoked")
        clearPersistedState()
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    private fun postBatch(base: String, token: String, devUuid: String, batch: List<Map<String, Any?>>): BatchPostResult {
        return try {
            val url = URL(base.trimEnd('/') + "/locations/batch")
            val conn = (url.openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                connectTimeout = 10000
                readTimeout = 15000
                doOutput = true
                setRequestProperty("Accept", "application/json")
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Authorization", "Bearer $token")
            }
            val payload = JSONObject().apply {
                put("device_uuid", devUuid)
                put("locations", JSONArray(batch.map { JSONObject(it) }))
            }
            BufferedOutputStream(conn.outputStream).use { os ->
                os.write(payload.toString().toByteArray(Charsets.UTF_8))
            }
            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            stream?.bufferedReader()?.use(BufferedReader::readText)
            conn.disconnect()
            when {
                code in 200..299 -> BatchPostResult.SUCCESS
                code == 401 || code == 403 -> BatchPostResult.TERMINAL_AUTH_FAILURE
                else -> BatchPostResult.TRANSIENT_FAILURE
            }
        } catch (_: Exception) {
            BatchPostResult.TRANSIENT_FAILURE
        }
    }

    private fun buildNotification(text: String): Notification {
        val intent = packageManager.getLaunchIntentForPackage(packageName)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or pendingFlagImmutable(),
        )
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Field Tracker")
            .setContentText(text)
            .setSmallIcon(applicationInfo.icon)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setContentIntent(pendingIntent)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .build()
    }

    private fun pendingFlagImmutable(): Int {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val mgr = getSystemService(NotificationManager::class.java)
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Tracking",
                NotificationManager.IMPORTANCE_LOW,
            ).apply {
                description = "Field Tracker location tracking"
            }
            mgr.createNotificationChannel(channel)
        }
    }

    private fun hasLocationPermission(): Boolean {
        val fine = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val coarse = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        return fine || coarse
    }

    private fun persistState() {
        val prefs = getSharedPreferences(PREFS, MODE_PRIVATE)
        // Do not persist raw auth token in plaintext. Token is kept in-memory and
        // must be re-supplied by Flutter via startTracking/syncState.
        prefs.edit()
            .putBoolean(KEY_RUNNING, true)
            .putString(KEY_SESSION_UUID, sessionUuid)
            .putString(KEY_SESSION_TYPE, sessionType)
            .putString(KEY_EXPIRES_AT, expiresAt)
            .putString(KEY_DEVICE_UUID, deviceUuid)
            .putString(KEY_BASE_URL, baseUrl)
            .putString(KEY_CLIENT_MIDNIGHT_AT, clientMidnightAt)
            .remove(KEY_AUTH_TOKEN)
            .apply()
    }

    private fun clearPersistedState() {
        getSharedPreferences(PREFS, MODE_PRIVATE).edit().clear().apply()
    }

    private fun restoreAndReconcile() {
        val prefs = getSharedPreferences(PREFS, MODE_PRIVATE)
        val running = prefs.getBoolean(KEY_RUNNING, false)
        if (!running) {
            stopSelf()
            return
        }
        sessionUuid = prefs.getString(KEY_SESSION_UUID, null)
        sessionType = prefs.getString(KEY_SESSION_TYPE, null)
        expiresAt = prefs.getString(KEY_EXPIRES_AT, null)
        deviceUuid = prefs.getString(KEY_DEVICE_UUID, null)
        baseUrl = prefs.getString(KEY_BASE_URL, null)
        clientMidnightAt = prefs.getString(KEY_CLIENT_MIDNIGHT_AT, null)

        // Token is intentionally not persisted in plaintext. After process restart
        // the app must resync and call startTracking/syncState with a fresh token.
        if (authToken.isNullOrBlank()) {
            TrackingRuntime.clear("awaiting_resync")
            clearPersistedState()
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return
        }

        // Reconcile expired/midnight state.
        val exp = expiresAt
        if (exp != null && millisUntil(exp) == 0L) {
            handleStop("duty_end_expired")
            return
        }
        if (sessionType == "extra_duty") {
            val midnight = clientMidnightAt ?: nextLocalMidnightIso()
            if (millisUntil(midnight) == 0L) {
                handleStop("midnight_auto_stopped")
                return
            }
        }
        handleStart(Intent(this, TrackingForegroundService::class.java).apply {
            action = ACTION_START
            putExtra(EXTRA_SESSION_UUID, sessionUuid)
            putExtra(EXTRA_SESSION_TYPE, sessionType)
            putExtra(EXTRA_EXPIRES_AT, expiresAt)
            putExtra(EXTRA_DEVICE_UUID, deviceUuid)
            putExtra(EXTRA_AUTH_TOKEN, authToken)
            putExtra(EXTRA_BASE_URL, baseUrl)
            putExtra(EXTRA_CLIENT_MIDNIGHT_AT, clientMidnightAt)
        })
    }

    private fun cancelSchedules() {
        flushRunnable?.let { workerHandler?.removeCallbacks(it) }
        expiryRunnable?.let { mainHandler.removeCallbacks(it) }
        midnightRunnable?.let { mainHandler.removeCallbacks(it) }
        flushRunnable = null
        expiryRunnable = null
        midnightRunnable = null
    }

    override fun onDestroy() {
        stopLocationUpdates()
        cancelSchedules()
        workerThread?.quitSafely()
        workerThread = null
        workerHandler = null
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    companion object {
        const val ACTION_START = "com.fieldtracker.mobile.tracking.START"
        const val ACTION_STOP = "com.fieldtracker.mobile.tracking.STOP"
        const val ACTION_SYNC = "com.fieldtracker.mobile.tracking.SYNC"

        const val EXTRA_SESSION_UUID = "session_uuid"
        const val EXTRA_SESSION_TYPE = "session_type"
        const val EXTRA_EXPIRES_AT = "expires_at"
        const val EXTRA_DEVICE_UUID = "device_uuid"
        const val EXTRA_AUTH_TOKEN = "token"
        const val EXTRA_BASE_URL = "base_url"
        const val EXTRA_CLIENT_MIDNIGHT_AT = "client_midnight_at"
        const val EXTRA_STOP_REASON = "stop_reason"

        private const val CHANNEL_ID = "tracking_channel"
        private const val NOTIFICATION_ID = 1001
        private const val MIN_INTERVAL_MS = 15_000L
        private const val MIN_DISTANCE_M = 10f
        private const val BATCH_SIZE = 10
        private const val MAX_BATCH = 100
        private const val FLUSH_INTERVAL_MS = 60_000L
        private const val RETRY_BASE_MS = 5_000L
        private const val RETRY_MAX_MS = 300_000L

        private const val PREFS = "tracking_runtime"
        private const val KEY_RUNNING = "running"
        private const val KEY_SESSION_UUID = "session_uuid"
        private const val KEY_SESSION_TYPE = "session_type"
        private const val KEY_EXPIRES_AT = "expires_at"
        private const val KEY_DEVICE_UUID = "device_uuid"
        private const val KEY_AUTH_TOKEN = "auth_token"
        private const val KEY_BASE_URL = "base_url"
        private const val KEY_CLIENT_MIDNIGHT_AT = "client_midnight_at"
    }
}
