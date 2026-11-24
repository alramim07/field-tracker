abstract class DayStateStore {
  Future<DateTime?> readLastActiveDay();
  Future<void> writeLastActiveDay(DateTime day);
  Future<void> clear();
}

class MemoryDayStateStore implements DayStateStore {
  DateTime? _day;

  @override
  Future<DateTime?> readLastActiveDay() async => _day;

  @override
  Future<void> writeLastActiveDay(DateTime day) async {
    _day = DateTime(day.year, day.month, day.day);
  }

  @override
  Future<void> clear() async {
    _day = null;
  }
}

