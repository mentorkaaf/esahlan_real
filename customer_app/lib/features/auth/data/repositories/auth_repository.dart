import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/services/firebase_service.dart';
import '../../../../core/services/realtime_client.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../community/presentation/providers/community_provider.dart';
import '../models/user_model.dart';

class AuthRepository {
  final Dio _dio = ApiClient.instance;

  Future<({UserModel user, String token})> login({
    String? phone,
    String? email,
    required String password,
  }) async {
    assert(phone != null || email != null, 'phone or email required');
    try {
      final res = await _dio.post('/auth/login', data: {
        if (email != null && email.isNotEmpty) 'email': email
        else 'phone': phone,
        'password': password,
      });
      final data  = res.data['data'];
      final token = data['token'] as String;
      await LocalStorage.saveToken(token);
      final user = await getMe();
      FirebaseService().registerTokenAfterLogin();
      RealtimeClient.instance.connect();
      MessagesNotifier.setMyId(user.id);
      return (user: user, token: token);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<({UserModel user, String token})> register({
    required String name,
    required String phone,
    required String password,
    String? email,
    int? districtId,
    String? referralCode,
  }) async {
    try {
      final res = await _dio.post('/auth/register', data: {
        'name': name,
        'phone': phone,
        'password': password,
        'password_confirmation': password,
        if (email != null && email.isNotEmpty) 'email': email,
        if (districtId != null) 'district_id': districtId,
        if (referralCode != null) 'referral_code': referralCode,
      });
      final data  = res.data['data'];
      final token = data['token'] as String;
      await LocalStorage.saveToken(token);
      final user = await getMe();
      FirebaseService().registerTokenAfterLogin();
      RealtimeClient.instance.connect();
      MessagesNotifier.setMyId(user.id);
      return (user: user, token: token);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<String> sendOtp({required String phone, required String purpose}) async {
    try {
      final res = await _dio.post('/auth/send-otp', data: {
        'phone': phone,
        'purpose': purpose,
      });
      // In local/dev mode, API returns the code
      return res.data['code']?.toString() ?? '';
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> verifyOtp({
    required String phone,
    required String code,
    required String purpose,
  }) async {
    try {
      await _dio.post('/auth/verify-otp', data: {
        'phone': phone,
        'code': code,
        'purpose': purpose,
      });
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<UserModel> getMe() async {
    try {
      final res  = await _dio.get('/auth/me');
      final user = UserModel.fromJson(res.data['data']);
      await LocalStorage.saveString('user_data', user.toJsonString());
      return user;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateProfile(Map<String, dynamic> data) async {
    try {
      await _dio.post('/auth/update-profile', data: data);
      final user = await getMe();
      await LocalStorage.saveString('user_data', user.toJsonString());
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } catch (_) {}
    await FirebaseService().deleteToken();
    await RealtimeClient.instance.disconnect();
    await LocalStorage.clear();
  }

  Future<void> updateLocation(double lat, double lng) async {
    try {
      await _dio.post('/auth/location', data: {'latitude': lat, 'longitude': lng});
    } catch (_) {}
  }

  UserModel? getCachedUser() {
    return null;
  }
}
