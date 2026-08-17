import 'package:dio/dio.dart';
import 'api_client.dart'; // also exports ApiException
import '../providers/payment_methods_provider.dart' show PaymentMethodsData;

/// Centralized service for all 12 module API calls
class ModuleApiService {
  final Dio _dio;

  ModuleApiService(this._dio);

  static ModuleApiService create() => ModuleApiService(ApiClient.instance);

  // ═══════════════════════════════════════════════════════════════════
  // HELPERS
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> _get(String path, {Map<String, dynamic>? params}) async {
    try {
      final r = await _dio.get(path, queryParameters: params);
      return r.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<dynamic> _post(String path, Map<String, dynamic> data) async {
    try {
      final r = await _dio.post(path, data: data);
      return r.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<dynamic> _delete(String path) async {
    try {
      final r = await _dio.delete(path);
      return r.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  // ═══════════════════════════════════════════════════════════════════
  // eFOOD
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getFoodBanners()    => _get('/efood/banners');
  Future<dynamic> getFoodCategories() => _get('/efood/categories');
  Future<dynamic> getRestaurants({String? search, String? category, bool? featured, bool? topRated, double? lat, double? lng}) =>
      _get('/efood/restaurants', params: {
        if (search    != null) 'search':     search,
        if (category  != null) 'category':   category,
        if (featured  != null) 'featured':   featured ? 1 : 0,
        if (topRated  != null) 'top_rated':  topRated  ? 1 : 0,
        if (lat != null && lng != null) 'lat': lat,
        if (lat != null && lng != null) 'lng': lng,
        if (lat != null && lng != null) 'radius': 50, // 50km — wide enough to cover the city
      });

  /// Near You — GPS mode: sorted by real distance from user's coords.
  /// Near You — district mode: restaurants in the user's registered district.
  Future<dynamic> getNearbyRestaurants({
    double? lat, double? lng, double radius = 10,
    int? nearDistrictId,
  }) =>
      _get('/efood/restaurants', params: {
        if (lat != null && lng != null) 'lat': lat,
        if (lat != null && lng != null) 'lng': lng,
        if (lat != null && lng != null) 'radius': radius,
        if (lat == null && nearDistrictId != null) 'near_district_id': nearDistrictId,
      });
  Future<dynamic> getRestaurant(int id) => _get('/efood/restaurants/$id');
  Future<dynamic> getRestaurantMenu(int restaurantId, {int? categoryId, String? search}) =>
      _get('/efood/restaurants/$restaurantId/menu', params: {
        if (categoryId != null) 'category_id': categoryId,
        if (search     != null) 'search': search,
      });
  Future<dynamic> getRestaurantProducts(int restaurantId, {int? categoryId, String? search}) =>
      _get('/efood/restaurants/$restaurantId/products', params: {
        if (categoryId != null) 'category_id': categoryId,
        if (search     != null) 'search': search,
      });
  Future<dynamic> getFoodItem(int id) => _get('/efood/items/$id');
  Future<dynamic> getFoodOrders()     => _get('/efood/orders');
  Future<dynamic> getFoodOrder(int id) => _get('/efood/orders/$id');
  Future<dynamic> trackFoodOrder(int id) => _get('/efood/orders/$id/track');
  Future<dynamic> getFoodFavorites()  => _get('/efood/favorites');
  Future<dynamic> toggleFoodFavorite(int restaurantId) => _post('/efood/favorites/toggle', {'restaurant_id': restaurantId});
  Future<dynamic> getRestaurantCoupons(int id)   => _get('/efood/restaurants/$id/coupons');
  Future<dynamic> getRestaurantCampaigns(int id) => _get('/efood/restaurants/$id/campaigns');
  Future<dynamic> applyFoodCoupon(Map<String, dynamic> data) => _post('/efood/coupon/apply', data);
  Future<dynamic> placeFoodOrder(Map<String, dynamic> data) => _post('/efood/order', data);

  // ═══════════════════════════════════════════════════════════════════
  // eLAUNDRY
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getLaundryItems() => _get('/elaundry/items');
  Future<dynamic> estimateLaundry(Map<String, dynamic> data) => _post('/elaundry/estimate', data);
  Future<dynamic> placeLaundryOrder(Map<String, dynamic> data) => _post('/elaundry/order', data);

  // ═══════════════════════════════════════════════════════════════════
  // eMOVING
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getMovingMoveTypes() => _get('/emoving/move-types');
  Future<dynamic> getMovingDistricts() => _get('/emoving/districts');
  Future<dynamic> getMovingExtraServices() => _get('/emoving/extra-services');
  Future<dynamic> getMovingPackages(String type) => _get('/emoving/packages/$type');
  Future<dynamic> calculateMoving(Map<String, dynamic> data) => _post('/emoving/calculate', data);
  Future<dynamic> placeMovingOrder(Map<String, dynamic> data) => _post('/emoving/order', data);
  Future<dynamic> getMovingMyOrders() => _get('/emoving/my-orders');

  // ═══════════════════════════════════════════════════════════════════
  // ePARCEL
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getParcelTypes() => _get('/eparcel/types');
  Future<dynamic> getParcelDistricts() => _get('/eparcel/districts');
  Future<dynamic> getParcelZones() => _get('/eparcel/zones');
  Future<dynamic> calculateParcel(Map<String, dynamic> data) => _post('/eparcel/calculate', data);
  Future<dynamic> placeParcelOrder(Map<String, dynamic> data) => _post('/eparcel/order', data);

  // ═══════════════════════════════════════════════════════════════════
  // eDATA
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getDataProviders() => _get('/edata/providers');
  Future<dynamic> getDataAll() => _get('/edata/all');
  Future<dynamic> getDataPackages(int providerId) => _get('/edata/providers/$providerId/packages');
  Future<dynamic> getDataBundles(int providerId) => _get('/edata/providers/$providerId/bundles');
  Future<dynamic> getDataPackageBundles(int packageId) => _get('/edata/packages/$packageId/bundles');
  Future<dynamic> purchaseData(Map<String, dynamic> data) => _post('/edata/purchase', data);
  Future<dynamic> getDataHistory() => _get('/edata/history');
  Future<dynamic> getDataFavorites() => _get('/edata/favorites');
  Future<dynamic> toggleDataFavorite(Map<String, dynamic> data) => _post('/edata/favorites/toggle', data);
  Future<dynamic> getEdataPhones(int providerId) => _get('/edata/my-phones/$providerId');
  Future<dynamic> saveEdataPhones(int providerId, String paymentPhone, String dataPhone) =>
      _post('/edata/my-phones', {'provider_id': providerId, 'payment_phone': paymentPhone, 'data_phone': dataPhone});
  Future<dynamic> getAllEdataPhones() => _get('/edata/my-phones');

  // ═══════════════════════════════════════════════════════════════════
  // eEXCHANGE
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getExchangeRates() => _get('/eexchange/rates');
  Future<dynamic> calculateExchange(Map<String, dynamic> data) => _post('/eexchange/calculate', data);
  Future<dynamic> transferExchange(Map<String, dynamic> data) => _post('/eexchange/transfer', data);
  Future<dynamic> previewExchange(Map<String, dynamic> data) => _post('/eexchange/preview', data);
  Future<dynamic> confirmExchange(Map<String, dynamic> data) => _post('/eexchange/confirm', data);
  // Saved wallet accounts
  Future<dynamic> getExchangeAccounts() => _get('/eexchange/accounts');
  Future<dynamic> addExchangeAccount(Map<String, dynamic> data) => _post('/eexchange/accounts', data);
  Future<dynamic> removeExchangeAccount(int id) => _delete('/eexchange/accounts/$id');

  // ═══════════════════════════════════════════════════════════════════
  // eHEALTH
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getHealthCategories() => _get('/ehealth/categories');
  Future<dynamic> getDoctors({String? specialization, String? search}) =>
      _get('/ehealth/doctors', params: {
        if (specialization != null) 'specialization': specialization,
        if (search != null) 'search': search,
      });
  Future<dynamic> getDoctor(int id) => _get('/ehealth/doctors/$id');
  Future<dynamic> requestAmbulance(Map<String, dynamic> data) => _post('/ehealth/ambulance', data);
  Future<dynamic> bookAppointment(Map<String, dynamic> data) => _post('/ehealth/book', data);

  // ═══════════════════════════════════════════════════════════════════
  // eRENT
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getRentDistricts() => _get('/erent/districts');
  Future<dynamic> getRentReels() => _get('/erent/reels');
  Future<dynamic> getProperties({int? districtId, String? type, double? minPrice, double? maxPrice, int? bedrooms}) =>
      _get('/erent/properties', params: {
        if (districtId != null) 'district_id': districtId,
        if (type != null && type.isNotEmpty) 'type': type,
        if (minPrice != null) 'min_price': minPrice,
        if (maxPrice != null) 'max_price': maxPrice,
        if (bedrooms != null) 'bedrooms': bedrooms,
      });
  Future<dynamic> getProperty(int id) => _get('/erent/properties/$id');
  Future<dynamic> bookProperty(Map<String, dynamic> data) => _post('/erent/book', data);
  Future<dynamic> getRentMyBookings() => _get('/erent/my-bookings');
  Future<dynamic> cancelRentBooking(int id, {String? reason}) => _post('/erent/bookings/$id/cancel', {'reason': reason ?? ''});
  Future<dynamic> payRemainingRent(int id, String paymentMethod) => _post('/erent/bookings/$id/pay-remaining', {'payment_method': paymentMethod});
  Future<dynamic> requestRentRefund(int id, String reason) => _post('/erent/bookings/$id/request-refund', {'reason': reason});
  Future<dynamic> submitHouseRequest(Map<String, dynamic> data) => _post('/erent/house-requests', data);
  Future<dynamic> myHouseRequests() => _get('/erent/house-requests/mine');
  Future<dynamic> getHouseRequest(int id) => _get('/erent/house-requests/$id');
  Future<dynamic> getRequestRecommendations(int requestId) => _get('/erent/house-requests/$requestId/recommendations');
  Future<dynamic> respondRecommendation(int requestId, int recId, String action, {double? counterPrice, String? counterMessage}) =>
      _post('/erent/house-requests/$requestId/recommendations/$recId/respond', {
        'action': action,
        if (counterPrice != null) 'counter_price': counterPrice,
        if (counterMessage != null) 'counter_message': counterMessage,
      });
  Future<dynamic> getRequestViewings(int requestId) => _get('/erent/house-requests/$requestId/viewings');
  Future<dynamic> confirmViewing(int requestId, int viewId, String action) =>
      _post('/erent/house-requests/$requestId/viewings/$viewId/confirm', {'action': action});
  Future<dynamic> getRequestMessages(int requestId) => _get('/erent/house-requests/$requestId/messages');
  Future<dynamic> sendRequestMessage(int requestId, String message) =>
      _post('/erent/house-requests/$requestId/messages', {'message': message});

  // ═══════════════════════════════════════════════════════════════════
  // eSHOP
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getShopBanners() => _get('/eshop/banners');
  Future<dynamic> getShopCategories() => _get('/eshop/categories');
  Future<dynamic> getShopHome() => _get('/eshop/home');
  Future<dynamic> getShopFlashDeals() => _get('/eshop/flash-deals');
  Future<dynamic> getShopDealsOfDay() => _get('/eshop/deals-of-day');
  Future<dynamic> getShopCampaigns() => _get('/eshop/campaigns');
  Future<dynamic> getShopProducts({int? categoryId, String? search, String? sort, int? page, bool? featured}) =>
      _get('/eshop/products', params: {
        if (categoryId != null) 'category_id': categoryId,
        if (search != null) 'search': search,
        if (sort != null) 'sort': sort,
        if (page != null) 'page': page,
        if (featured != null) 'featured': featured ? 1 : 0,
      });
  Future<dynamic> getShopProduct(int id) => _get('/eshop/products/$id');
  Future<dynamic> placeShopOrder(Map<String, dynamic> data) => _post('/eshop/order', data);
  Future<dynamic> placeShopOrderV2(Map<String, dynamic> body) => _post('/eshop/order', body);
  Future<dynamic> validateShopCoupon({required String code, required double orderAmount}) =>
      _post('/eshop/coupon/validate', {'code': code, 'order_amount': orderAmount});
  Future<dynamic> getShopStores({String? search, bool? featured, int? page}) =>
      _get('/eshop/stores', params: {
        if (search != null) 'search': search,
        if (featured == true) 'featured': 1,
        if (page != null) 'page': page,
      });
  Future<dynamic> getShopStoreDetail(int id) => _get('/eshop/stores/$id');
  Future<dynamic> getShopPopular() => _get('/eshop/popular');
  Future<dynamic> getShopProductReviews(int productId, {int page = 1}) =>
      _get('/eshop/products/$productId/reviews', params: {'page': page});
  Future<dynamic> submitShopReview(int productId, {required int rating, String? comment}) =>
      _post('/eshop/products/$productId/reviews', {'rating': rating, if (comment != null) 'comment': comment});
  Future<dynamic> trackShopView(int productId) async {
    try { await _dio.patch('/eshop/products/$productId/view'); } catch (_) {}
  }

  Future<dynamic> getEshopDeliveryFee(int districtId) =>
      _get('/eshop/delivery-fee', params: {'district_id': districtId});

  // ═══════════════════════════════════════════════════════════════════
  // eWHOLESALE
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getWholesaleCategories() => _get('/ewholesale/categories');
  Future<dynamic> getWholesaleProducts({int? categoryId, String? search}) =>
      _get('/ewholesale/products', params: {
        if (categoryId != null) 'category_id': categoryId,
        if (search != null) 'search': search,
      });
  Future<dynamic> placeWholesaleOrder(Map<String, dynamic> data) => _post('/ewholesale/order', data);
  Future<dynamic> inquireWholesale(Map<String, dynamic> data) => _post('/ewholesale/order', data);

  // ═══════════════════════════════════════════════════════════════════
  // eGROCERY
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getGroceryCategories() => _get('/egrocery/categories');
  Future<dynamic> getGroceryProducts({int? categoryId, String? search, bool? featured}) =>
      _get('/egrocery/products', params: {
        if (categoryId != null) 'category_id': categoryId,
        if (search != null) 'search': search,
        if (featured == true) 'featured': 1,
      });
  Future<dynamic> getGroceryProductDetail(int id) => _get('/egrocery/products/$id');
  Future<dynamic> placeGroceryOrder(Map<String, dynamic> data) => _post('/egrocery/order', data);

  // ═══════════════════════════════════════════════════════════════════
  // eTICKET
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getFlightAirlines() => _get('/eticket/airlines');
  Future<dynamic> getFlightRoutes() => _get('/eticket/routes');
  Future<dynamic> getFlightCities() => _get('/eticket/cities');
  Future<dynamic> getTicketHome() => _get('/eticket/home');
  Future<dynamic> getFlightDetail(int id) => _get('/eticket/flights/$id');
  Future<dynamic> getMyFlightBookingDetail(int orderId) => _get('/eticket/my-bookings/$orderId');
  Future<dynamic> searchFlights({
    required String from, required String to, required String date,
    String? returnDate, String tripType = 'one_way',
    int adults = 1, int children = 0, int infants = 0,
    String seatClass = 'economy',
  }) => _get('/eticket/search', params: {
        'from': from, 'to': to, 'date': date,
        if (returnDate != null) 'return_date': returnDate,
        'trip_type': tripType,
        'adults': adults, 'children': children, 'infants': infants,
        'passengers': adults + children + infants,
        'seat_class': seatClass,
      });
  Future<dynamic> bookFlight(Map<String, dynamic> data) => _post('/eticket/book', data);
  Future<dynamic> getMyFlightBookings() => _get('/eticket/my-bookings');
  Future<dynamic> getFlightActiveDates({required String from, required String to}) =>
      _get('/eticket/active-dates', params: {'from': from, 'to': to});

  // ═══════════════════════════════════════════════════════════════════
  // SHARED
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getDistricts() => _get('/districts');

  // ═══════════════════════════════════════════════════════════════════
  // PAYMENT METHODS (enabled by admin)
  // ═══════════════════════════════════════════════════════════════════
  Future<List<String>> getEnabledPaymentMethods() async {
    try {
      final res = await _get('/payment/methods');
      return List<String>.from(res['enabled'] ?? ['cod', 'waafi_pay', 'wallet', 'mobile_pay']);
    } catch (_) {
      return ['cod', 'waafi_pay', 'wallet', 'mobile_pay'];
    }
  }

  Future<PaymentMethodsData> getPaymentMethodsData() async {
    try {
      final res = await _get('/payment/methods');
      final enabled = List<String>.from(res['enabled'] ?? ['cod', 'waafi_pay', 'wallet', 'mobile_pay']);
      final logosRaw = res['logos'] as Map<String, dynamic>? ?? {};
      final logos = logosRaw.map((k, v) => MapEntry(k, v.toString()));
      return PaymentMethodsData(enabled: enabled, logos: logos);
    } catch (_) {
      return PaymentMethodsData.fallback;
    }
  }

  // ═══════════════════════════════════════════════════════════════════
  // PAYMENT (Waafi Pay)
  // ═══════════════════════════════════════════════════════════════════
  Future<dynamic> initiatePayment(Map<String, dynamic> data) => _post('/payment/initiate', data);
  Future<dynamic> checkPaymentStatus(String reference) => _get('/payment/status/$reference');

  // ═══════════════════════════════════════════════════════════════════
  // WALLET
  // ═══════════════════════════════════════════════════════════════════
  Future<dynamic> getWallet() => _get('/wallet');
  Future<dynamic> getWalletTransactions() => _get('/wallet/transactions');
  Future<dynamic> walletTopup(Map<String, dynamic> data) => _post('/wallet/topup', data);

  // ── Gamification ──────────────────────────────────────────────────────────
  Future<dynamic> getGamificationProfile() => _get('/gamification');
  Future<dynamic> getLeaderboard({String period = 'weekly'}) => _get('/gamification/leaderboard?period=$period');

  // ── Affiliate ─────────────────────────────────────────────────────────────
  Future<dynamic> getAffiliateDashboard() => _get('/affiliate');
  Future<dynamic> applyAffiliate() => _post('/affiliate/apply', {});
  Future<dynamic> requestAffiliatePayout(Map<String, dynamic> data) => _post('/affiliate/payout', data);

  // ── Rewards & Referral ────────────────────────────────────────────────────
  Future<dynamic> getReferral() => _get('/wallet/referral');
  Future<dynamic> getRewards() => _get('/rewards');
  Future<dynamic> getRewardsHistory() => _get('/rewards/history');
  Future<dynamic> validatePointsRedeem(Map<String, dynamic> data) => _post('/rewards/validate-redeem', data);
  Future<dynamic> redeemPointsToWallet(int points) => _post('/rewards/redeem-to-wallet', {'points': points});
  Future<dynamic> getNotifications({int page = 1, int perPage = 20}) => _get('/notifications?page=$page&per_page=$perPage');
  Future<dynamic> getUnreadNotificationCount() => _get('/notifications/unread');
  Future<dynamic> markNotificationRead({String? id, bool all = false}) =>
      _post('/notifications/read', all ? {'all': true} : {'id': id});
  Future<dynamic> getEarnPreview({required double amount, required String module}) =>
      _get('/rewards/earn-preview?amount=$amount&module=$module');
  Future<dynamic> walletTopupMobilePay(Map<String, dynamic> data) => _post('/wallet/topup/mobile-pay', data);
  Future<dynamic> checkTopupStatus(String reference) => _get('/wallet/topup/status/$reference');
  Future<dynamic> walletSend(Map<String, dynamic> data) => _post('/wallet/send', data);
  Future<dynamic> walletWithdraw(Map<String, dynamic> data) => _post('/wallet/withdraw', data);
  Future<dynamic> verifyWalletPin(String pin) => _post('/wallet/verify-pin', {'pin': pin});
  Future<dynamic> setWalletPin(String pin) => _post('/wallet/set-pin', {'pin': pin});

  // MOBILE PAY
  // ═══════════════════════════════════════════════════════════════════
  Future<dynamic> getMobilePayAccounts() => _get('/mobile-pay/accounts');

  Future<String> submitMobilePayProof({
    required String phone,
    required int accountId,
    required double amount,
    required List<int> imageBytes,
  }) async {
    try {
      final formData = FormData.fromMap({
        'phone':      phone,
        'account_id': accountId,
        'amount':     amount,
        'image':      MultipartFile.fromBytes(imageBytes, filename: 'proof.jpg'),
      });
      final r = await _dio.post('/mobile-pay/submit-proof', data: formData);
      return r.data['proof_token'] as String;
    } on DioException catch (e) {
      throw ApiException(e.response?.data?['message'] ?? 'Upload failed');
    }
  }

  Future<void> attachMobilePayProof(String orderNumber, String proofToken) async {
    try {
      await _dio.post('/mobile-pay/attach-proof', data: {
        'order_number': orderNumber,
        'proof_token':  proofToken,
      });
    } catch (_) {}  // best-effort — don't block the user
  }
}
