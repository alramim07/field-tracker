import 'package:uuid/uuid.dart';

abstract class DeviceIdentityStorage {
  Future<String?> readDeviceUuid();
  Future<void> writeDeviceUuid(String value);
}

abstract class DeviceRegistrationApi {
  Future<void> registerDevice({required String deviceUuid});
}

class DeviceRegistrationService {
  DeviceRegistrationService({
    required DeviceIdentityStorage storage,
    required DeviceRegistrationApi api,
    Uuid? uuid,
  })  : _storage = storage,
        _api = api,
        _uuid = uuid ?? const Uuid();

  final DeviceIdentityStorage _storage;
  final DeviceRegistrationApi _api;
  final Uuid _uuid;

  Future<String> ensureRegistered() async {
    var deviceUuid = await _storage.readDeviceUuid();
    if (deviceUuid == null || deviceUuid.isEmpty) {
      deviceUuid = _uuid.v4();
      await _storage.writeDeviceUuid(deviceUuid);
    }

    await _api.registerDevice(deviceUuid: deviceUuid);
    return deviceUuid;
  }
}
