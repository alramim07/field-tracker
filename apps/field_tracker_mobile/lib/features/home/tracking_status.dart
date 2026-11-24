class TrackingStatus {
  const TrackingStatus({
    required this.serverTime,
    required this.canExtend,
    required this.hasUsedExtraToday,
    this.activeSession,
  });

  final DateTime serverTime;
  final bool canExtend;
  final bool hasUsedExtraToday;
  final TrackingSessionStatus? activeSession;

  factory TrackingStatus.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? json;
    final active = data['active_session'] as Map<String, dynamic>?;
    return TrackingStatus(
      serverTime: DateTime.parse(data['server_time'] as String),
      canExtend: data['can_extend'] as bool? ?? false,
      hasUsedExtraToday: data['has_used_extra_today'] as bool? ?? false,
      activeSession: active == null ? null : TrackingSessionStatus.fromJson(active),
    );
  }
}

class TrackingSessionStatus {
  const TrackingSessionStatus({
    required this.sessionUuid,
    required this.status,
    required this.sessionType,
    required this.counted,
    required this.startedAt,
    required this.canExtend,
    required this.hasUsedExtraToday,
    this.expiresAt,
    this.endedAt,
    this.stopReason,
  });

  final String sessionUuid;
  final String status;
  final String? sessionType;
  final bool counted;
  final DateTime startedAt;
  final DateTime? expiresAt;
  final DateTime? endedAt;
  final String? stopReason;
  final bool canExtend;
  final bool hasUsedExtraToday;

  bool get isActive => status == 'active';
  bool get isNormalDuty => sessionType == 'normal_duty';
  bool get isExtraDuty => sessionType == 'extra_duty';

  factory TrackingSessionStatus.fromJson(Map<String, dynamic> json) {
    return TrackingSessionStatus(
      sessionUuid: json['session_uuid'] as String? ?? '',
      status: json['status'] as String? ?? '',
      sessionType: json['session_type'] as String?,
      counted: json['counted'] as bool? ?? true,
      startedAt: DateTime.parse(json['started_at'] as String),
      expiresAt: json['expires_at'] == null
          ? null
          : DateTime.parse(json['expires_at'] as String),
      endedAt: json['ended_at'] == null
          ? null
          : DateTime.parse(json['ended_at'] as String),
      stopReason: json['stop_reason'] as String?,
      canExtend: json['can_extend'] as bool? ?? false,
      hasUsedExtraToday: json['has_used_extra_today'] as bool? ?? false,
    );
  }
}
