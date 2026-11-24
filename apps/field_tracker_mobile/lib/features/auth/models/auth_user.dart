class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.phone,
    this.photoUrl,
    this.staffId,
    this.designation,
    this.dutyStartTime,
    this.dutyEndTime,
    this.totalTrackingSeconds = 0,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final String? phone;
  final String? photoUrl;
  final String? staffId;
  final String? designation;
  final String? dutyStartTime;
  final String? dutyEndTime;
  final int totalTrackingSeconds;

  bool get isEmployee => role == 'employee';

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final employee = json['employee'] as Map<String, dynamic>? ?? const {};
    return AuthUser(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? '',
      phone: json['phone'] as String?,
      photoUrl: json['photo_url'] as String?,
      staffId: employee['employee_code'] as String?,
      designation: employee['designation'] as String?,
      dutyStartTime: json['duty_start_time'] as String?,
      dutyEndTime: json['duty_end_time'] as String?,
      totalTrackingSeconds: (json['total_tracking_seconds'] as num?)?.toInt() ?? 0,
    );
  }
}
