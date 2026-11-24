import 'dart:convert';

class NotificationItem {
  const NotificationItem({
    required this.id,
    required this.title,
    required this.body,
    required this.createdAt,
    required this.isRead,
    this.sessionId,
    this.stopReason,
    this.dutyEndTime,
  });

  final String id;
  final String title;
  final String body;
  final DateTime createdAt;
  final bool isRead;
  final String? sessionId;
  final String? stopReason;
  final String? dutyEndTime;

  NotificationItem copyWith({bool? isRead}) {
    return NotificationItem(
      id: id,
      title: title,
      body: body,
      createdAt: createdAt,
      isRead: isRead ?? this.isRead,
      sessionId: sessionId,
      stopReason: stopReason,
      dutyEndTime: dutyEndTime,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'body': body,
      'createdAt': createdAt.toUtc().toIso8601String(),
      'isRead': isRead,
      'sessionId': sessionId,
      'stopReason': stopReason,
      'dutyEndTime': dutyEndTime,
    };
  }

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    return NotificationItem(
      id: json['id'] as String? ?? '',
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      createdAt: DateTime.tryParse(json['createdAt'] as String? ?? '')?.toUtc() ??
          DateTime.fromMillisecondsSinceEpoch(0, isUtc: true),
      isRead: json['isRead'] as bool? ?? false,
      sessionId: json['sessionId'] as String?,
      stopReason: json['stopReason'] as String?,
      dutyEndTime: json['dutyEndTime'] as String?,
    );
  }

  static String encodeList(List<NotificationItem> items) {
    return jsonEncode(items.map((e) => e.toJson()).toList());
  }

  static List<NotificationItem> decodeList(String? raw) {
    if (raw == null || raw.isEmpty) return <NotificationItem>[];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return <NotificationItem>[];
    return decoded
        .whereType<Map>()
        .map((e) => NotificationItem.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }
}
