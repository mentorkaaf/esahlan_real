import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/constants/app_constants.dart';
import '../models/global_models.dart';

const _kGlobalToken = 'global_token';

class GlobalRepository {
  static GlobalRepository? _instance;
  static GlobalRepository get instance => _instance ??= GlobalRepository._();
  GlobalRepository._();

  final Dio _dio = Dio(BaseOptions(
    baseUrl: AppConstants.baseUrl,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 30),
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
  ));

  static const String _base = '/global';

  String? _token;

  Future<String?> get token async {
    _token ??= (await SharedPreferences.getInstance()).getString(_kGlobalToken);
    return _token;
  }

  Future<Options> get _authOpts async {
    final t = await token;
    return Options(headers: t != null ? {'Authorization': 'Bearer $t'} : {});
  }

  Future<void> saveToken(String t) async {
    _token = t;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kGlobalToken, t);
  }

  Future<void> clearToken() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kGlobalToken);
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> data,
      {bool auth = false}) async {
    try {
      final opts = auth ? await _authOpts : null;
      final res = await _dio.post(path, data: data, options: opts);
      return res.data;
    } on DioException catch (e) {
      throw _parseError(e);
    }
  }

  Future<Map<String, dynamic>> _get(String path,
      {Map<String, dynamic>? params, bool auth = false}) async {
    try {
      final opts = auth ? await _authOpts : null;
      final res =
          await _dio.get(path, queryParameters: params, options: opts);
      return res.data;
    } on DioException catch (e) {
      throw _parseError(e);
    }
  }

  Future<Map<String, dynamic>> _put(String path, Map<String, dynamic> data) async {
    try {
      final opts = await _authOpts;
      final res = await _dio.put(path, data: data, options: opts);
      return res.data;
    } on DioException catch (e) {
      throw _parseError(e);
    }
  }

  Future<Map<String, dynamic>> _delete(String path) async {
    try {
      final opts = await _authOpts;
      final res = await _dio.delete(path, options: opts);
      return res.data;
    } on DioException catch (e) {
      throw _parseError(e);
    }
  }

  Exception _parseError(DioException e) {
    final data = e.response?.data;
    String msg = 'Something went wrong.';
    if (data is Map) {
      msg = data['message'] ?? data['error'] ?? msg;
    }
    if (kDebugMode) debugPrint('GlobalRepo error: $msg (${e.response?.statusCode})');
    return Exception(msg);
  }

  // ── Auth ─────────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> register(Map<String, dynamic> data) =>
      _post('$_base/auth/register', data);

  Future<Map<String, dynamic>> login(String email, String password,
      {String? fcmToken}) =>
      _post('$_base/auth/login', {
        'email': email,
        'password': password,
        if (fcmToken != null) 'fcm_token': fcmToken,
      });

  Future<void> logout() async {
    await _post('$_base/auth/logout', {}, auth: true);
  }

  Future<GlobalUser> getMe() async {
    final res = await _get('$_base/auth/me', auth: true);
    return GlobalUser.fromJson(res['user']);
  }

  Future<GlobalUser> updateProfile(Map<String, dynamic> data) async {
    final res = await _put('$_base/auth/profile', data);
    return GlobalUser.fromJson(res['user']);
  }

  Future<GlobalAddress> addAddress(Map<String, dynamic> data) async {
    final res = await _post('$_base/auth/addresses', data, auth: true);
    return GlobalAddress.fromJson(res['address']);
  }

  Future<GlobalUser> updateAddress(int addressId, Map<String, dynamic> data) async {
    final res = await _put('$_base/auth/addresses/$addressId', data);
    return GlobalUser.fromJson(res['user']);
  }

  // ── Products ─────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getProducts({
    String? q,
    int? categoryId,
    double? minPrice,
    double? maxPrice,
    String? sort,
    int page = 1,
  }) =>
      _get('$_base/products', params: {
        if (q != null && q.isNotEmpty) 'q': q,
        if (categoryId != null) 'category_id': categoryId,
        if (minPrice != null) 'min_price': minPrice,
        if (maxPrice != null) 'max_price': maxPrice,
        if (sort != null) 'sort': sort,
        'page': page,
      });

  Future<GlobalProduct> getProduct(int id) async {
    final res = await _get('$_base/products/$id');
    return GlobalProduct.fromJson(res['product']);
  }

  Future<List<GlobalSlider>> getSliders() async {
    final res = await _get('$_base/sliders');
    return (res['sliders'] as List).map((s) => GlobalSlider.fromJson(s)).toList();
  }

  Future<List<GlobalProduct>> getFeatured() async {
    final res = await _get('$_base/products/featured');
    return (res['products'] as List).map((p) => GlobalProduct.fromJson(p)).toList();
  }

  Future<List<GlobalProduct>> getFlashDeals() async {
    final res = await _get('$_base/products/flash');
    return (res['products'] as List).map((p) => GlobalProduct.fromJson(p)).toList();
  }

  Future<List<GlobalCategory>> getCategories() async {
    final res = await _get('$_base/categories');
    return (res['categories'] as List).map((c) => GlobalCategory.fromJson(c)).toList();
  }

  // ── Cart ─────────────────────────────────────────────────────────────────────

  Future<GlobalCart> getCart() async {
    final res = await _get('$_base/cart', auth: true);
    return GlobalCart.fromJson(res);
  }

  Future<GlobalCart> addToCart(int productId, int qty, {String? variant}) async {
    final res = await _post('$_base/cart', {
      'product_id': productId,
      'quantity': qty,
      if (variant != null) 'variant': variant,
    }, auth: true);
    return GlobalCart.fromJson(res);
  }

  Future<GlobalCart> updateCartItem(int itemId, int qty) async {
    final res = await _put('$_base/cart/$itemId', {'quantity': qty});
    return GlobalCart.fromJson(res);
  }

  Future<GlobalCart> removeCartItem(int itemId) async {
    final res = await _delete('$_base/cart/$itemId');
    return GlobalCart.fromJson(res);
  }

  Future<void> clearCart() => _delete('$_base/cart');

  // ── Checkout ─────────────────────────────────────────────────────────────────

  Future<GlobalCheckoutSummary> getCheckoutSummary(String country) async {
    final res = await _get('$_base/checkout/summary',
        params: {'country': country}, auth: true);
    return GlobalCheckoutSummary.fromJson(res);
  }

  /// Returns {order_id, order_number, total, client_secret, public_key}
  Future<Map<String, dynamic>> createStripeCheckout(Map<String, dynamic> data) async {
    return await _post('$_base/checkout/stripe', data, auth: true);
  }

  /// Web-only: creates a Stripe Checkout Session, returns {order_id, session_url}
  Future<Map<String, dynamic>> createStripeWebSession(Map<String, dynamic> data) async {
    return await _post('$_base/checkout/stripe-web-session', data, auth: true);
  }

  /// Returns {order_id, order_number, total, approval_url, paypal_id}
  Future<Map<String, dynamic>> createPayPalCheckout(Map<String, dynamic> data) async {
    return await _post('$_base/checkout/paypal', data, auth: true);
  }

  /// Call after Stripe Payment Sheet completes successfully
  Future<void> confirmStripePayment(int orderId, String paymentIntentId) async {
    await _post('$_base/checkout/stripe/confirm',
        {'order_id': orderId, 'payment_intent_id': paymentIntentId}, auth: true);
  }

  /// Call after PayPal redirect returns with success
  Future<void> capturePaypalPayment(int orderId) async {
    await _post('$_base/checkout/paypal/capture', {'order_id': orderId}, auth: true);
  }

  // ── Orders ───────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getOrders({int page = 1}) =>
      _get('$_base/orders', params: {'page': page}, auth: true);

  Future<GlobalOrder> getOrder(int id) async {
    final res = await _get('$_base/orders/$id', auth: true);
    return GlobalOrder.fromJson(res['order']);
  }

  // ── Reviews ──────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getReviews(int productId) =>
      _get('$_base/products/$productId/reviews');

  Future<void> submitReview(
      int productId, int rating, String? title, String? body,
      {List<File>? images}) async {
    final token = await this.token;
    final formData = FormData.fromMap({
      'rating': rating,
      if (title != null && title.isNotEmpty) 'title': title,
      if (body != null && body.isNotEmpty) 'body': body,
      if (images != null && images.isNotEmpty)
        'images': await Future.wait(images.map((f) async =>
            await MultipartFile.fromFile(f.path,
                filename: f.path.split('/').last))),
    });

    try {
      await _dio.post(
        '$_base/products/$productId/reviews',
        data: formData,
        options: Options(
          contentType: 'multipart/form-data',
          headers: {
            if (token != null) 'Authorization': 'Bearer $token',
          },
        ),
      );
    } on DioException catch (e) {
      final msg = e.response?.data?['message'] ?? e.message ?? 'Failed to submit review';
      throw Exception(msg);
    }
  }
}
