import 'package:flutter_test/flutter_test.dart';
import 'package:field_tracker_mobile/features/history/history_grouping_service.dart';

void main() {
  test('groups stationary pings into stopped period', () {
    final base = DateTime.utc(2026, 11, 24, 8, 0);
    final points = [
      HistoryPoint(latitude: 23.7808, longitude: 90.2792, recordedAt: base),
      HistoryPoint(
        latitude: 23.78081,
        longitude: 90.27921,
        recordedAt: base.add(const Duration(minutes: 5)),
      ),
      HistoryPoint(
        latitude: 23.78082,
        longitude: 90.27919,
        recordedAt: base.add(const Duration(minutes: 10)),
      ),
    ];

    final segments = HistoryGroupingService().group(points);

    expect(segments, isNotEmpty);
    expect(segments.first.type, SegmentType.stop);
  });
}
