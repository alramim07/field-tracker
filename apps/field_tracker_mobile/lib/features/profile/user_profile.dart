class UserProfile {
  const UserProfile({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.staffId,
    required this.designation,
    required this.photoUrl,
    required this.dutyStartTime,
    required this.dutyEndTime,
    required this.totalTrackingSeconds,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? staffId;
  final String? designation;
  final String? photoUrl;
  final String? dutyStartTime;
  final String? dutyEndTime;
  final int totalTrackingSeconds;

  factory UserProfile.fromMeJson(Map<String, dynamic> json) {
    final employee = json['employee'] as Map<String, dynamic>? ?? const {};
    return UserProfile(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String?,
      staffId: employee['employee_code'] as String?,
      designation: employee['designation'] as String?,
      photoUrl: json['photo_url'] as String?,
      dutyStartTime: json['duty_start_time'] as String?,
      dutyEndTime: json['duty_end_time'] as String?,
      totalTrackingSeconds: (json['total_tracking_seconds'] as num?)?.toInt() ?? 0,
    );
  }

  UserProfile copyWith({
    String? name,
    String? email,
    String? phone,
    String? staffId,
    String? designation,
    String? photoUrl,
    String? dutyStartTime,
    String? dutyEndTime,
    int? totalTrackingSeconds,
  }) {
    return UserProfile(
      id: id,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      staffId: staffId ?? this.staffId,
      designation: designation ?? this.designation,
      photoUrl: photoUrl ?? this.photoUrl,
      dutyStartTime: dutyStartTime ?? this.dutyStartTime,
      dutyEndTime: dutyEndTime ?? this.dutyEndTime,
      totalTrackingSeconds: totalTrackingSeconds ?? this.totalTrackingSeconds,
    );
  }

  String get trackingTimeLabel {
    final total = totalTrackingSeconds < 0 ? 0 : totalTrackingSeconds;
    final hours = total ~/ 3600;
    final minutes = (total % 3600) ~/ 60;
    final seconds = total % 60;
    return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  String get dutyTimeLabel {
    final start = (dutyStartTime ?? '').trim();
    final end = (dutyEndTime ?? '').trim();
    if (start.isEmpty && end.isEmpty) return '-';
    return '${_hhmm(start)} - ${_hhmm(end)}';
  }

  static String _hhmm(String value) {
    if (value.isEmpty) return '--:--';
    // Accept HH:mm:ss or HH:mm
    final parts = value.split(':');
    if (parts.length >= 2) {
      return '${parts[0].padLeft(2, '0')}:${parts[1].padLeft(2, '0')}';
    }
    return value;
  }
}
