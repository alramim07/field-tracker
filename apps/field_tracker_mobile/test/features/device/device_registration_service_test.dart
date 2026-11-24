import 'package:flutter_test/flutter_test.dart';
import 'package:field_tracker_mobile/features/device/device_registration_service.dart';

class FakeDeviceStorage implements DeviceIdentityStorage {
  FakeDeviceStorage({this.uuid});

  String? uuid;
  int readCalls = 0;
  int writeCalls = 0;

  @override
  Future<String?> readDeviceUuid() async {
    readCalls++;
    return uuid;
  }

  @override
  Future<void> writeDeviceUuid(String value) async {
    writeCalls++;
    uuid = value;
  }
}

class FakeDeviceApi implements DeviceRegistrationApi {
  final List<String> registeredUuids = [];

  @override
  Future<void> registerDevice({required String deviceUuid}) async {
    registeredUuids.add(deviceUuid);
  }
}

void main() {
  test('device registration reuses persisted uuid', () async {
    final storage = FakeDeviceStorage(uuid: 'existing-uuid');
    final api = FakeDeviceApi();
    final service = DeviceRegistrationService(storage: storage, api: api);

    final first = await service.ensureRegistered();
    final second = await service.ensureRegistered();

    expect(first, 'existing-uuid');
    expect(second, 'existing-uuid');
    expect(api.registeredUuids, ['existing-uuid', 'existing-uuid']);
    expect(storage.writeCalls, 0);
  });
}
