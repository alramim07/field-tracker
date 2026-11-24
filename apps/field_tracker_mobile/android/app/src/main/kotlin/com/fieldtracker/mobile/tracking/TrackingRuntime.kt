package com.fieldtracker.mobile.tracking

object TrackingRuntime {
    @Volatile
    private var running: Boolean = false

    @Volatile
    private var sessionUuid: String? = null

    @Volatile
    private var sessionType: String? = null

    @Volatile
    private var expiresAt: String? = null

    @Volatile
    private var stopReason: String? = null

    @Volatile
    var stateListener: ((Map<String, Any?>) -> Unit)? = null

    @Synchronized
    fun setRunning(
        isRunning: Boolean,
        sessionUuid: String? = this.sessionUuid,
        sessionType: String? = this.sessionType,
        expiresAt: String? = this.expiresAt,
        stopReason: String? = this.stopReason,
    ) {
        running = isRunning
        this.sessionUuid = sessionUuid
        this.sessionType = sessionType
        this.expiresAt = expiresAt
        this.stopReason = stopReason
        publishState()
    }

    @Synchronized
    fun clear(reason: String? = null) {
        running = false
        stopReason = reason
        sessionUuid = null
        sessionType = null
        expiresAt = null
        publishState()
    }

    fun isRunning(): Boolean = running

    fun currentState(): Map<String, Any?> {
        return mapOf(
            "is_running" to running,
            "session_uuid" to sessionUuid,
            "session_type" to sessionType,
            "expires_at" to expiresAt,
            "stop_reason" to stopReason,
        )
    }

    fun publishState() {
        stateListener?.invoke(currentState())
    }
}
