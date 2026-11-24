import 'dart:math' as math;

enum SegmentType { movement, stop }

class HistoryPoint {
  const HistoryPoint({
    required this.latitude,
    required this.longitude,
    required this.recordedAt,
    this.accuracy,
  });

  final double latitude;
  final double longitude;
  final DateTime recordedAt;
  final double? accuracy;
}

class HistorySegment {
  const HistorySegment({
    required this.type,
    required this.points,
    required this.startAt,
    required this.endAt,
    this.distanceMeters = 0,
    this.startLabel = '',
    this.endLabel = '',
  });

  final SegmentType type;
  final List<HistoryPoint> points;
  final DateTime startAt;
  final DateTime endAt;
  final double distanceMeters;
  final String startLabel;
  final String endLabel;

  Duration get duration {
    final d = endAt.difference(startAt);
    return d.isNegative ? Duration.zero : d;
  }
}

class HistoryGroupingService {
  const HistoryGroupingService({this.stopRadiusMeters = 50});

  final double stopRadiusMeters;

  List<HistorySegment> group(List<HistoryPoint> points) {
    if (points.isEmpty) return const [];
    final sorted = [...points]..sort((a, b) => a.recordedAt.compareTo(b.recordedAt));

    final segments = <HistorySegment>[];
    var anchor = sorted.first;
    var cluster = <HistoryPoint>[sorted.first];

    for (var i = 1; i < sorted.length; i++) {
      final p = sorted[i];
      final distance = _distanceMeters(anchor, p);
      if (distance <= stopRadiusMeters) {
        cluster.add(p);
      } else {
        _flushCluster(segments, cluster, next: p);
        anchor = p;
        cluster = [p];
      }
    }
    _flushCluster(segments, cluster, next: null);

    return _mergeAdjacentMovements(segments);
  }

  void _flushCluster(
    List<HistorySegment> segments,
    List<HistoryPoint> cluster, {
    required HistoryPoint? next,
  }) {
    if (cluster.isEmpty) return;
    if (cluster.length > 1) {
      segments.add(HistorySegment(
        type: SegmentType.stop,
        points: List.unmodifiable(cluster),
        startAt: cluster.first.recordedAt,
        endAt: cluster.last.recordedAt,
        distanceMeters: 0,
        startLabel: _coordLabel(cluster.first),
        endLabel: _coordLabel(cluster.last),
      ));
      return;
    }

    final point = cluster.first;
    if (next != null && _distanceMeters(point, next) > stopRadiusMeters) {
      final distance = _distanceMeters(point, next);
      segments.add(HistorySegment(
        type: SegmentType.movement,
        points: List.unmodifiable([point, next]),
        startAt: point.recordedAt,
        endAt: next.recordedAt,
        distanceMeters: distance,
        startLabel: _coordLabel(point),
        endLabel: _coordLabel(next),
      ));
    } else {
      // Single trailing point with no next movement - treat as stop.
      segments.add(HistorySegment(
        type: SegmentType.stop,
        points: List.unmodifiable(cluster),
        startAt: point.recordedAt,
        endAt: point.recordedAt,
        distanceMeters: 0,
        startLabel: _coordLabel(point),
        endLabel: _coordLabel(point),
      ));
    }
  }

  List<HistorySegment> _mergeAdjacentMovements(List<HistorySegment> segments) {
    if (segments.isEmpty) return segments;
    final merged = <HistorySegment>[];
    for (final seg in segments) {
      if (merged.isNotEmpty &&
          merged.last.type == SegmentType.movement &&
          seg.type == SegmentType.movement) {
        final last = merged.removeLast();
        final pts = <HistoryPoint>[...last.points];
        for (final p in seg.points) {
          if (pts.isEmpty || !_samePoint(pts.last, p)) {
            pts.add(p);
          }
        }
        merged.add(HistorySegment(
          type: SegmentType.movement,
          points: List.unmodifiable(pts),
          startAt: last.startAt,
          endAt: seg.endAt,
          distanceMeters: last.distanceMeters + seg.distanceMeters,
          startLabel: last.startLabel,
          endLabel: seg.endLabel,
        ));
      } else {
        merged.add(seg);
      }
    }
    return merged;
  }

  bool _samePoint(HistoryPoint a, HistoryPoint b) {
    return a.latitude == b.latitude &&
        a.longitude == b.longitude &&
        a.recordedAt == b.recordedAt;
  }

  String _coordLabel(HistoryPoint p) {
    return '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}';
  }

  double _distanceMeters(HistoryPoint a, HistoryPoint b) {
    const earthRadius = 6371000.0;
    final dLat = _toRadians(b.latitude - a.latitude);
    final dLng = _toRadians(b.longitude - a.longitude);
    final lat1 = _toRadians(a.latitude);
    final lat2 = _toRadians(b.latitude);
    final h = math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(lat1) * math.cos(lat2) * math.sin(dLng / 2) * math.sin(dLng / 2);
    return 2 * earthRadius * math.asin(math.sqrt(h));
  }

  double _toRadians(double degrees) => degrees * math.pi / 180;
}
