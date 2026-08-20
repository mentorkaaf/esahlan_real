import '../../../../core/api/api_client.dart';
import '../models/ew_models.dart';

class EwholesaleRepository {
  static const _base = '/ewholesale';

  // ── Home ──────────────────────────────────────────────────────────────────

  Future<EwHomePayload> getHome() async {
    final r = await ApiClient.instance.get('$_base/home');
    return EwHomePayload.fromJson(r.data['data']);
  }

  // ── Catalog ───────────────────────────────────────────────────────────────

  Future<List<EwCategory>> getCategories() async {
    final r = await ApiClient.instance.get('$_base/categories');
    return _list(r.data['data'], EwCategory.fromJson);
  }

  Future<EwCategory> getCategory(int id) async {
    final r = await ApiClient.instance.get('$_base/categories/$id');
    return EwCategory.fromJson(r.data['data']);
  }

  Future<({List<EwProduct> products, int total, int lastPage})> getProducts({
    int? categoryId,
    String? supplierId,
    double? minPrice,
    double? maxPrice,
    double? maxMoq,
    bool? verifiedOnly,
    bool? goldOnly,
    String? search,
    String? sort,
    int page = 1,
  }) async {
    final r = await ApiClient.instance.get('$_base/products', queryParameters: {
      if (categoryId != null) 'category': categoryId,
      if (supplierId != null) 'supplier': supplierId,
      if (minPrice != null) 'min_price': minPrice,
      if (maxPrice != null) 'max_price': maxPrice,
      if (maxMoq != null) 'max_moq': maxMoq,
      if (verifiedOnly == true) 'verified': 1,
      if (goldOnly == true) 'gold': 1,
      if (search != null && search.isNotEmpty) 'search': search,
      if (sort != null) 'sort': sort,
      'page': page,
    });
    return (
      products:  _list(r.data['data'], EwProduct.fromJson),
      total:     (r.data['meta']?['total'] ?? 0) as int,
      lastPage:  (r.data['meta']?['last_page'] ?? 1) as int,
    );
  }

  Future<EwProduct> getProduct(String slug) async {
    final r = await ApiClient.instance.get('$_base/products/$slug');
    return EwProduct.fromJson(r.data['data']);
  }

  Future<EwSupplierCard> getSupplier(int id) async {
    final r = await ApiClient.instance.get('$_base/suppliers/$id');
    return EwSupplierCard.fromJson(r.data['data']);
  }

  Future<({List<EwProduct> products, EwSupplierCard supplier})> getSupplierStorefront(int id) async {
    final r = await ApiClient.instance.get('$_base/suppliers/$id');
    return (
      supplier: EwSupplierCard.fromJson(r.data['data']),
      products: _list(r.data['data']['products'], EwProduct.fromJson),
    );
  }

  Future<List<String>> searchSuggest(String q) async {
    final r = await ApiClient.instance.get('$_base/search/suggest', queryParameters: {'q': q});
    return (r.data['data'] as List? ?? []).map((e) => e.toString()).toList();
  }

  Future<Map<String, dynamic>> search(String q, {String type = 'all', int? categoryId, int page = 1}) async {
    final params = <String, dynamic>{'q': q, 'type': type, 'page': page};
    if (categoryId != null) params['category_id'] = categoryId;
    final r = await ApiClient.instance.get('$_base/search', queryParameters: params);
    final d = r.data['data'] as Map<String, dynamic>;
    final prodData = d['products'];
    return {
      'products': prodData is Map
          ? _list(prodData['data'], EwProduct.fromJson)
          : <EwProduct>[],
      'products_meta': prodData is Map ? prodData['meta'] : null,
      'suppliers': _list(d['suppliers'], EwSupplierCard.fromJson),
    };
  }

  // ── Cart ──────────────────────────────────────────────────────────────────

  Future<EwCartValidateResult> cartValidate(List<EwCartLine> lines, {int? districtId}) async {
    final r = await ApiClient.instance.post('$_base/cart/validate', data: {
      'lines': lines.map((l) => l.toJson()).toList(),
      if (districtId != null) 'district_id': districtId,
    });
    return EwCartValidateResult.fromJson(r.data['data']);
  }

  // ── Buyer ─────────────────────────────────────────────────────────────────

  Future<EwBuyerProfile> registerBuyer({
    required String businessName,
    required String businessType,
    String? licenseNo,
    String? taxId,
  }) async {
    final r = await ApiClient.instance.post('$_base/buyer/register', data: {
      'business_name': businessName,
      'business_type': businessType,
      if (licenseNo != null) 'license_no': licenseNo,
      if (taxId != null) 'tax_id': taxId,
    });
    return EwBuyerProfile.fromJson(r.data['data']);
  }

  Future<EwBuyerProfile> getBuyerProfile() async {
    final r = await ApiClient.instance.get('$_base/buyer/me');
    return EwBuyerProfile.fromJson(r.data['data']);
  }

  // ── Inquiries ─────────────────────────────────────────────────────────────

  Future<void> createInquiry({required int productId, required double qty, required String message}) async {
    await ApiClient.instance.post('$_base/products/$productId/inquiry', data: {
      'qty': qty,
      'message': message,
    });
  }

  Future<List<EwQuote>> getQuotes() async {
    final r = await ApiClient.instance.get('$_base/quotes');
    return _list(r.data['data'], EwQuote.fromJson);
  }

  Future<EwQuote> getQuote(int id) async {
    final r = await ApiClient.instance.get('$_base/quotes/$id');
    return EwQuote.fromJson(r.data['data']);
  }

  Future<void> counterQuote(int id, {required List<Map<String, dynamic>> lines, String? note}) async {
    await ApiClient.instance.post('$_base/quotes/$id/counter', data: {
      'lines': lines,
      if (note != null) 'note': note,
    });
  }

  Future<Map<String, dynamic>> acceptQuote(int id) async {
    final r = await ApiClient.instance.post('$_base/quotes/$id/accept');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<void> declineQuote(int id) async {
    await ApiClient.instance.post('$_base/quotes/$id/decline');
  }

  // ── RFQs ──────────────────────────────────────────────────────────────────

  /// Returns the newly created RFQ's ID.
  Future<int> createRfq({
    required int categoryId,
    required String title,
    required double qty,
    required String unit,
    double? targetPrice,
    DateTime? neededBy,
  }) async {
    final r = await ApiClient.instance.post('$_base/rfqs', data: {
      'category_id': categoryId,
      'title':       title,
      'qty':         qty,
      'unit':        unit,
      if (targetPrice != null) 'target_price': targetPrice,
      if (neededBy != null) 'needed_by': neededBy.toIso8601String().split('T').first,
    });
    return (r.data['data']['rfq_id'] as num).toInt();
  }

  Future<List<EwRfq>> getMyRfqs() async {
    final r = await ApiClient.instance.get('$_base/rfqs/mine');
    return _list(r.data['data'], EwRfq.fromJson);
  }

  Future<EwRfq> getRfq(int id) async {
    final r = await ApiClient.instance.get('$_base/rfqs/$id');
    return EwRfq.fromJson(r.data['data']);
  }

  Future<void> acceptRfqQuote(int quoteId) async {
    await ApiClient.instance.post('$_base/rfq-quotes/$quoteId/accept');
  }

  Future<void> shortlistRfqQuote(int quoteId) async {
    await ApiClient.instance.post('$_base/rfq-quotes/$quoteId/shortlist');
  }

  Future<void> rejectRfqQuote(int quoteId) async {
    await ApiClient.instance.post('$_base/rfq-quotes/$quoteId/reject');
  }

  // ── Orders ────────────────────────────────────────────────────────────────

  Future<List<EwOrder>> checkout(List<Map<String, dynamic>> groups) async {
    final r = await ApiClient.instance.post('$_base/orders', data: {'groups': groups});
    return _list(r.data['data']['orders'], EwOrder.fromJson);
  }

  Future<List<EwOrder>> getOrders({String? status, int page = 1}) async {
    final r = await ApiClient.instance.get('$_base/orders', queryParameters: {
      if (status != null) 'status': status,
      'page': page,
    });
    return _list(r.data['data'], EwOrder.fromJson);
  }

  Future<EwOrder> getOrder(int id) async {
    final r = await ApiClient.instance.get('$_base/orders/$id');
    return EwOrder.fromJson(r.data['data']);
  }

  Future<void> payBalance(int id, {required String method}) async {
    await ApiClient.instance.post('$_base/orders/$id/pay-balance', data: {'method': method});
  }

  Future<void> cancelOrder(int id) async {
    await ApiClient.instance.post('$_base/orders/$id/cancel');
  }

  Future<void> disputeOrder(int id, {required String reason, required String description}) async {
    await ApiClient.instance.post('$_base/orders/$id/dispute', data: {
      'reason': reason,
      'description': description,
    });
  }

  Future<void> reviewOrder(int id, {required int rating, String? comment}) async {
    await ApiClient.instance.post('$_base/orders/$id/review', data: {
      'rating': rating,
      if (comment != null) 'comment': comment,
    });
  }

  Future<EwCartValidateResult> reorder(int id) async {
    final r = await ApiClient.instance.post('$_base/orders/$id/reorder');
    return EwCartValidateResult.fromJson(r.data['data']);
  }

  // ── Saved Lists ───────────────────────────────────────────────────────────

  Future<List<EwSavedList>> getSavedLists() async {
    final r = await ApiClient.instance.get('$_base/lists');
    return _list(r.data['data'], EwSavedList.fromJson);
  }

  // Aliases used by screens
  Future<void> payOrderBalance(int id) => payBalance(id, method: 'wallet');
  Future<void> openDispute(int id, String description) => disputeOrder(id, reason: 'other', description: description);
  Future<void> submitReview(int id, {required int rating, String? comment}) => reviewOrder(id, rating: rating, comment: comment);

  Future<void> submitKyb({required String businessName, required String businessType, required String address, required String taxId, String? website}) async {
    await ApiClient.instance.post('$_base/buyer/kyb', data: {
      'business_name': businessName,
      'business_type': businessType,
      'address':       address,
      'tax_id':        taxId,
      if (website != null) 'website': website,
    });
  }

  Future<EwSavedList> createSavedList(String name) async {
    final r = await ApiClient.instance.post('$_base/lists', data: {'name': name});
    return EwSavedList.fromJson(r.data['data']);
  }

  Future<void> deleteSavedList(int id) async {
    await ApiClient.instance.delete('$_base/lists/$id');
  }

  Future<void> addToSavedList(int listId, {required int productId, int? variantId, required double qty}) async {
    await ApiClient.instance.post('$_base/lists/$listId/items', data: {
      'product_id': productId,
      if (variantId != null) 'variant_id': variantId,
      'qty': qty,
    });
  }

  Future<void> removeFromSavedList(int listId, int itemId) async {
    await ApiClient.instance.delete('$_base/lists/$listId/items/$itemId');
  }

  Future<EwCartValidateResult> savedListToCart(int listId) async {
    final r = await ApiClient.instance.post('$_base/lists/$listId/to-cart');
    return EwCartValidateResult.fromJson(r.data['data']);
  }
}

List<T> _list<T>(dynamic raw, T Function(Map<String, dynamic>) f) {
  if (raw == null) return [];
  return (raw as List).map((e) => f(e as Map<String, dynamic>)).toList();
}
