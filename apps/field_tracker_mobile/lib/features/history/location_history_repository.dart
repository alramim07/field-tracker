import 'history_grouping_service.dart';

abstract class LocationHistoryRepository {
  Future<List<HistoryPoint>> fetchCurrentDay({DateTime? day});
}
