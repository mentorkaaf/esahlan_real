import 'package:dio/dio.dart';
import 'api_client.dart'; // also exports ApiException

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

  // ═══════════════════════════════════════════════════════════════════
  // eFOOD
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getFoodBanners()    => _get('/efood/banners');
  Future<dynamic> getFoodCategories() => _get('/efood/categories');
  Future<dynamic> getRestaurants({String? search, String? category, bool? featured, bool? topRated}) =>
      _get('/efood/restaurants', params: {
        if (search    != null) 'search':     search,
        if (category  != null) 'category':   category,
        if (featured  != null) 'featured':   featured ? 1 : 0,
        if (topRated  != null) 'top_rated':  topRated  ? 1 : 0,
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

  // ═══════════════════════════════════════════════════════════════════
  // eEXCHANGE
  // ═══════════════════════════════════════════════════════════════════

  Future<dynamic> getExchangeRates() => _get('/eexchange/rates');
  Future<dynamic> calculateExchange(Map<String, dynamic> data) => _post('/eexchange/calculate', data);
  Future<dynamic> transferExchange(Map<String, dynamic> data) => _post('/eexchange/transfer', data);
  Future<dynamic> previewExchange(Map<String, dynamic> data) => _post('/eexchange/preview', data);
  Future<dynamic> confirmExchange(Map<String, dynamic> data) => _post('/eexchange/confirm', data);

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
  Future<dynamic> checkTopupStatus(String reference) => _get('/wallet/topup/status/$reference');
  Future<dynamic> walletSend(Map<String, dynamic> data) => _post('/wallet/send', data);
  Future<dynamic> walletWithdraw(Map<String, dynamic> data) => _post('/wallet/withdraw', data);
  Future<dynamic> verifyWalletPin(String pin) => _post('/wallet/verify-pin', {'pin': pin});
}
