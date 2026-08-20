import '../../core/api/api_client.dart';

class WholesaleRepository {
  WholesaleRepository._();
  static final instance = WholesaleRepository._();
  final _api = ApiClient();
  static const _base = '/ewholesale/supplier';

  // ── Supplier Profile ──────────────────────────────────────────────────────
  Future<Map<String, dynamic>?> me() async {
    try {
      final res = await _api.get('$_base/me');
      return res['data'] as Map<String, dynamic>?;
    } catch (_) { return null; }
  }

  Future<Map<String, dynamic>> register(Map<String, dynamic> data) async {
    final res = await _api.post('$_base/register', data: data);
    return res;
  }

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final res = await _api.put('$_base/me', data: data);
    return res;
  }

  // ── Dashboard ─────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> dashboard() async {
    final res = await _api.get('$_base/dashboard');
    return res['data'] as Map<String, dynamic>;
  }

  // ── Products ──────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> products({String? status, int page = 1}) async {
    final res = await _api.get('$_base/products', params: {
      if (status != null) 'status': status,
      'page': page,
    });
    return ((res['data'] as List?) ?? []).cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> createProduct(Map<String, dynamic> data) async {
    final res = await _api.post('$_base/products', data: data);
    return res;
  }

  Future<Map<String, dynamic>> updateProduct(int id, Map<String, dynamic> data) async {
    final res = await _api.put('$_base/products/$id', data: data);
    return res;
  }

  Future<void> toggleProduct(int id) async {
    await _api.post('$_base/products/$id/toggle');
  }

  // ── Orders ───────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> orders({String? status, int page = 1}) async {
    final res = await _api.get('$_base/orders', params: {
      if (status != null) 'status': status,
      'page': page,
    });
    return ((res['data'] as List?) ?? []).cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> orderDetail(int id) async {
    final res = await _api.get('$_base/orders/$id');
    return res['data'] as Map<String, dynamic>;
  }

  Future<void> confirmOrder(int id) async {
    await _api.post('$_base/orders/$id/confirm');
  }

  Future<void> updateOrderStatus(int id, String status, {String? note}) async {
    await _api.post('$_base/orders/$id/status', data: {'status': status, if (note != null) 'note': note});
  }

  // ── Inquiries ─────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> inquiries({String? status}) async {
    final res = await _api.get('$_base/inquiries', params: {if (status != null) 'status': status});
    return ((res['data'] as List?) ?? []).cast<Map<String, dynamic>>();
  }

  Future<void> sendQuote(int inquiryId, Map<String, dynamic> data) async {
    await _api.post('$_base/inquiries/$inquiryId/quote', data: data);
  }

  // ── RFQs ─────────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> rfqs() async {
    final res = await _api.get('$_base/rfqs');
    return ((res['data'] as List?) ?? []).cast<Map<String, dynamic>>();
  }

  Future<void> submitRfqQuote(int rfqId, Map<String, dynamic> data) async {
    await _api.post('$_base/rfqs/$rfqId/quote', data: data);
  }
}
