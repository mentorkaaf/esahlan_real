import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/auth_repository.dart';
import '../../../../core/storage/local_storage.dart';

final authRepoProvider = Provider((_) => AuthRepository());

final authStateProvider = FutureProvider<({bool loggedIn, bool approved})>((ref) async {
  final token = await LocalStorage.getToken();
  if (token == null) return (loggedIn: false, approved: false);
  final approved = await LocalStorage.getBool('is_approved');
  return (loggedIn: true, approved: approved);
});

class LoginNotifier extends AsyncNotifier<void> {
  @override
  Future<void> build() async {}

  Future<void> login(String phone, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      await ref.read(authRepoProvider).login(phone: phone, password: password);
      ref.invalidate(authStateProvider);
    });
  }
}

final loginProvider = AsyncNotifierProvider<LoginNotifier, void>(LoginNotifier.new);

class RegisterNotifier extends AsyncNotifier<void> {
  @override
  Future<void> build() async {}

  Future<void> register({
    required String name, required String phone, required String password,
    required String vehicleType, String? plateNumber,
  }) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      await ref.read(authRepoProvider).register(
        name: name, phone: phone, password: password,
        vehicleType: vehicleType, plateNumber: plateNumber,
      );
      ref.invalidate(authStateProvider);
    });
  }
}

final registerProvider = AsyncNotifierProvider<RegisterNotifier, void>(RegisterNotifier.new);

final logoutProvider = Provider<Future<void> Function()>((ref) {
  return () async {
    await ref.read(authRepoProvider).logout();
    ref.invalidate(authStateProvider);
  };
});
