class CurrentLocationFix {
  const CurrentLocationFix({
    required this.latitude,
    required this.longitude,
    this.accuracy,
    this.capturedAt,
  });

  final double latitude;
  final double longitude;
  final double? accuracy;
  final DateTime? capturedAt;
}

abstract class CurrentLocationProvider {
  Future<CurrentLocationFix?> getCurrentLocation();
}
