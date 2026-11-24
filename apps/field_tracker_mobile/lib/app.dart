import 'package:material_ui/material_ui.dart';
import 'package:go_router/go_router.dart';

import 'core/network/api_client.dart';
import 'core/storage/token_storage.dart';
import 'features/auth/api_auth_repository.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/login_screen.dart';
import 'features/device/api_device_registration_api.dart';
import 'features/device/device_registration_service.dart';
import 'features/history/api_location_history_repository.dart';
import 'features/home/api_tracking_status_repository.dart';
import 'features/home/day_state_store.dart';
import 'features/home/geolocator_current_location_provider.dart';
import 'features/home/home_screen.dart';
import 'features/sync/app_sync_service.dart';

class FieldTrackerApp extends StatefulWidget {
  const FieldTrackerApp({super.key});

  @override
  State<FieldTrackerApp> createState() => _FieldTrackerAppState();
}

class _FieldTrackerAppState extends State<FieldTrackerApp> {
  late final TokenStorage _tokenStorage;
  late final ApiClient _apiClient;
  late final ApiAuthRepository _authRepository;
  late final DeviceRegistrationService _deviceRegistrationService;
  late final AuthController _authController;
  late final ApiTrackingStatusRepository _trackingStatusRepository;
  late final ApiLocationHistoryRepository _locationHistoryRepository;
  late final GeolocatorCurrentLocationProvider _locationProvider;
  late final DayStateStore _dayStateStore;
  late final AppSyncService _syncService;
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    _tokenStorage = TokenStorage();
    _apiClient = ApiClient(tokenStorage: _tokenStorage);
    _authRepository = ApiAuthRepository(
      apiClient: _apiClient,
      tokenStorage: _tokenStorage,
    );
    _deviceRegistrationService = DeviceRegistrationService(
      storage: SecureDeviceIdentityStorage(),
      api: ApiDeviceRegistrationApi(apiClient: _apiClient),
    );
    _trackingStatusRepository = ApiTrackingStatusRepository(apiClient: _apiClient);
    _locationHistoryRepository = ApiLocationHistoryRepository(apiClient: _apiClient);
    _locationProvider = GeolocatorCurrentLocationProvider();
    _dayStateStore = MemoryDayStateStore();
    _syncService = AppSyncService(tasks: [
      () async {
        await _apiClient.dio.get('/me');
      },
      () async {
        await _trackingStatusRepository.fetchStatus();
      },
      () async {
        await _locationHistoryRepository.fetchCurrentDay();
      },
    ]);
    _authController = AuthController(
      authRepository: _authRepository,
      deviceRegistrationService: _deviceRegistrationService,
    );
    _router = GoRouter(
      initialLocation: '/login',
      refreshListenable: _authController,
      redirect: (context, state) {
        final loggedIn = _authController.isAuthenticated;
        final loggingIn = state.matchedLocation == '/login';
        if (_authController.status == AuthStatus.unknown) return null;
        if (!loggedIn) return loggingIn ? null : '/login';
        if (loggingIn) return '/';
        return null;
      },
      routes: [
        GoRoute(
          path: '/login',
          builder: (context, state) => LoginScreen(
            authRepository: _authRepository,
            onAuthenticated: _authController.handleAuthenticated,
          ),
        ),
        GoRoute(
          path: '/',
          builder: (context, state) => HomeScreen(
            trackingStatusRepository: _trackingStatusRepository,
            syncService: _syncService,
            dayStateStore: _dayStateStore,
            locationHistoryRepository: _locationHistoryRepository,
            currentLocationProvider: _locationProvider,
            userName: _authController.user?.name ?? 'Employee',
            onLogout: _authController.logout,
          ),
        ),
      ],
    );
    _authController.restoreSession();
  }

  @override
  void dispose() {
    _authController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Field Tracker',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF1F6FEB),
          brightness: Brightness.light,
        ),
        useMaterial3: true,
        inputDecorationTheme: const InputDecorationTheme(
          filled: true,
        ),
      ),
      routerConfig: _router,
    );
  }
}
