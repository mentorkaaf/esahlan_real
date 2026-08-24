import 'dart:convert';
import 'package:dio/dio.dart';
import '../../../core/api/api_client.dart';
import '../../../core/services/firebase_service.dart';
import '../../../core/services/location_service.dart';
import '../../../core/storage/local_storage.dart';

class AuthRepository {
  final Dio _dio = ApiClient.instance;

  Future<Map<String, dynamic>> login({required String phone, required String password}) async {
    try {
      final res = await _dio.post('/delivery/auth/login', data: {'phone': phone, 'password': password});
      final data = res.data['data'] as Map<String, dynamic>;
      await LocalStorage.saveToken(data['token'] as String);
      await LocalStorage.saveString('driver_data', jsonEncode(data['deliveryman']));
      await LocalStorage.saveBool('is_approved', data['is_approved'] == true);
      return data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> register({
    required String name, required String phone, required String password,
    required String vehicleType, String? plateNumber,
  }) async {
    try {
      final res = await _dio.post('/delivery/auth/register', data: {
        'name': name, 'phone': phone, 'password': password,
        'vehicle_type': vehicleType, 'plate_number': plateNumber,
      });
      final data = res.data['data'] as Map<String, dynamic>;
      await LocalStorage.saveToken(data['token'] as String);
      await LocalStorage.saveBool('is_approved', false);
      return data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> logout() async {
    DriverLocationService.stopTracking();
    await FirebaseService().deleteToken();
    try { await _dio.post('/auth/logout'); } catch (_) {}
    await LocalStorage.clear();
  }

  Future<Map<String, dynamic>> dashboard() async {
    final res = await _dio.get('/delivery/dashboard');
    return res.data['data'] as Map<String, dynamic>;
  }

  Future<void> toggleStatus() async {
    await _dio.post('/delivery/toggle-status');
  }

  Future<List<dynamic>> availableOrders() async {
    final res = await _dio.get('/delivery/orders/available');
    return res.data['data'] as List;
  }

  Future<List<dynamic>> activeOrders() async {
    final res = await _dio.get('/delivery/orders/active');
    return res.data['data'] as List;
  }

  Future<void> acceptOrder(int orderId) async {
    await _dio.post('/delivery/orders/$orderId/accept');
  }

  Future<void> rejectOrder(int orderId) async {
    await _dio.post('/delivery/orders/$orderId/reject');
  }

  Future<void> updateOrderStatus(int orderId, String status) async {
    await _dio.post('/delivery/orders/$orderId/status', data: {'status': status});
  }

  Future<void> updateLocation(double lat, double lng, {int? orderId}) async {
    await _dio.post('/delivery/location', data: {
      'latitude': lat, 'longitude': lng,
      if (orderId != null) 'order_id': orderId,
    });
  }

  Future<Map<String, dynamic>> earnings() async {
    final res = await _dio.get('/delivery/earnings');
    return res.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> wallet() async {
    final res = await _dio.get('/delivery/wallet');
    return res.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> walletTransactions({int page = 1}) async {
    final res = await _dio.get('/delivery/wallet/transactions', queryParameters: {'page': page});
    return res.data['data'] as Map<String, dynamic>;
  }

  Future<void> withdrawRequest({required double amount, required String method, required String accountNumber, required String accountName}) async {
    final res = await _dio.post('/delivery/wallet/withdraw', data: {
      'amount': amount,
      'payment_method': method,
      'account_number': accountNumber,
      'account_name': accountName,
    });
    if (res.data['success'] != true) throw res.data['message'] ?? 'Failed';
  }

  Future<Map<String, dynamic>> profile() async {
    final res = await _dio.get('/delivery/profile');
    return res.data['data'] as Map<String, dynamic>;
  }
}
