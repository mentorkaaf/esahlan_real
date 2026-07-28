import 'package:dio/dio.dart';
import '../api/api_client.dart';

class VendorRepository {
  VendorRepository._();
  static final instance = VendorRepository._();
  final _api = ApiClient();

  // Dashboard
  Future<Map<String, dynamic>> dashboard() async {
    final res = await _api.get('/vendor/dashboard');
    return res['data'] as Map<String, dynamic>;
  }

  Future<void> toggleStore() async {
    await _api.post('/vendor/store/toggle');
  }

  // Orders
  Future<Map<String, dynamic>> orders({String? status, String? date, int page = 1}) async {
    final res = await _api.get('/vendor/orders', params: {
      if (status != null) 'status': status,
      if (date != null) 'date': date,
      'page': page,
      'per_page': 20,
    });
    return res;
  }

  Future<Map<String, dynamic>> orderDetail(int id) async {
    final res = await _api.get('/vendor/orders/$id');
    return res['data'] as Map<String, dynamic>;
  }

  Future<void> acceptOrder(int id) async {
    await _api.post('/vendor/orders/$id/accept');
  }

  Future<void> rejectOrder(int id, String reason) async {
    await _api.post('/vendor/orders/$id/reject', data: {'reason': reason});
  }

  Future<void> markReady(int id) async {
    await _api.post('/vendor/orders/$id/ready');
  }

  // Categories
  Future<List<dynamic>> categories() async {
    final res = await _api.get('/vendor/categories');
    return res['data'] as List<dynamic>;
  }

  // Products
  Future<Map<String, dynamic>> products({int? categoryId, String? search, int page = 1}) async {
    final res = await _api.get('/vendor/products', params: {
      if (categoryId != null) 'category_id': categoryId,
      if (search != null && search.isNotEmpty) 'search': search,
      'page': page,
      'per_page': 20,
    });
    return res;
  }

  Future<void> createProduct(FormData data) async {
    await _api.post('/vendor/products', data: data, isMultipart: true);
  }

  Future<void> updateProduct(int id, Map<String, dynamic> data) async {
    await _api.patch('/vendor/products/$id', data: data);
  }

  Future<void> deleteProduct(int id) async {
    await _api.delete('/vendor/products/$id');
  }

  Future<void> toggleProduct(int id) async {
    await _api.post('/vendor/products/$id/toggle');
  }

  // Store
  Future<Map<String, dynamic>> storeProfile() async {
    final res = await _api.get('/vendor/store');
    return res['data'] as Map<String, dynamic>;
  }

  Future<void> updateStore(FormData data) async {
    await _api.post('/vendor/store', data: data, isMultipart: true);
  }

  Future<void> updateSchedule(List<Map<String, dynamic>> schedules) async {
    await _api.post('/vendor/store/schedule', data: {'schedules': schedules});
  }

  // Wallet
  Future<Map<String, dynamic>> wallet() async {
    final res = await _api.get('/vendor/wallet');
    return res['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> walletTransactions({int page = 1}) async {
    final res = await _api.get('/vendor/wallet/transactions', params: {'page': page, 'per_page': 20});
    return res;
  }

  Future<void> withdraw({required double amount, required String method, required String account, required String name}) async {
    final res = await _api.post('/vendor/wallet/withdraw', data: {
      'amount': amount,
      'payment_method': method,
      'account_number': account,
      'account_name': name,
    });
    if (res['success'] != true) throw res['message'] ?? 'Withdrawal failed';
  }
}
