import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/user_model.dart';
import '../../data/repositories/auth_repository.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../../core/router/app_router.dart' show authChangeNotifierProvider;

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository());

// Watches current auth state (null = logged out)
final authStateProvider = FutureProvider<UserModel?>((ref) async {
  final token = await LocalStorage.getToken();
  if (token == null) return null;

  final userJson = await LocalStorage.getString('user_data');
  if (userJson == null) return null;

  try {
    return UserModel.fromJsonString(userJson);
  } catch (_) {
    return null;
  }
});

// Login state
class LoginNotifier extends AsyncNotifier<void> {
  @override
  Future<void> build() async {}

  Future<void> login(String phone, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final repo = ref.read(authRepositoryProvider);
      await repo.login(phone: phone, password: password);
      ref.invalidate(authStateProvider);
    });
  }
}

final loginProvider = AsyncNotifierProvider<LoginNotifier, void>(LoginNotifier.new);

// Register state
class RegisterNotifier extends AsyncNotifier<void> {
  @override
  Future<void> build() async {}

  Future<void> register({
    required String name,
    required String phone,
    required String password,
    int? districtId,
    String? referralCode,
  }) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final repo = ref.read(authRepositoryProvider);
      await repo.register(name: name, phone: phone, password: password, districtId: districtId, referralCode: referralCode);
      ref.invalidate(authStateProvider);
    });
  }
}

final registerProvider = AsyncNotifierProvider<RegisterNotifier, void>(RegisterNotifier.new);

// OTP
class OtpNotifier extends StateNotifier<AsyncValue<void>> {
  OtpNotifier(this._repo) : super(const AsyncData(null));
  final AuthRepository _repo;

  String? lastDevCode;

  Future<String?> sendOtp(String phone, String purpose) async {
    state = const AsyncLoading();
    String? devCode;
    state = await AsyncValue.guard(() async {
      devCode = await _repo.sendOtp(phone: phone, purpose: purpose);
    });
    lastDevCode = devCode;
    return devCode;
  }

  Future<void> verifyOtp(String phone, String code, String purpose) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _repo.verifyOtp(phone: phone, code: code, purpose: purpose));
  }
}

final otpProvider = StateNotifierProvider<OtpNotifier, AsyncValue<void>>(
  (ref) => OtpNotifier(ref.read(authRepositoryProvider)),
);

// Logout
final logoutProvider = Provider<Future<void> Function()>((ref) {
  return () async {
    await ref.read(authRepositoryProvider).logout();
    ref.invalidate(authStateProvider);
    // Trigger GoRouter redirect refresh without recreating the router
    // The notifier is read lazily — only notifies if router is already built
    try {
      ref.read(authChangeNotifierProvider).notify();
    } catch (_) {}
  };
});
