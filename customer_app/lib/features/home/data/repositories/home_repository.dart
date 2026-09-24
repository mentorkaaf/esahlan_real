import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../models/home_models.dart';

class HomeRepository {
  final Dio _dio = ApiClient.instance;

  Future<List<ModuleModel>> getModules() async {
    try {
      final res = await _dio.get('/modules');
      final list = res.data['data'] as List;
      return list.map((e) => ModuleModel.fromJson(e)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> getHomeData() async {
    try {
      final res = await _dio.get('/home');
      return res.data['data'] as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<BannerModel>> getBanners() async {
    final data = await getHomeData();
    final list = data['banners'] as List? ?? [];
    return list.map((e) => BannerModel.fromJson(e)).toList();
  }

  /// Public endpoint — no auth required. Fetches home-screen banners
  /// (position = home_top or home_middle) directly.
  Future<List<BannerModel>> getHomeBanners() async {
    try {
      final res = await _dio.get('/banners');
      final list = res.data['data'] as List? ?? [];
      return list.map((e) => BannerModel.fromJson(e)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<VendorModel>> getFeaturedVendors() async {
    final data = await getHomeData();
    final list = data['featured_vendors'] as List? ?? [];
    return list.map((e) => VendorModel.fromJson(e)).toList();
  }

  Future<List<VendorModel>> getVendorsByModule(String moduleSlug, {int page = 1}) async {
    try {
      final res = await _dio.get('/vendors', queryParameters: {
        'module': moduleSlug,
        'page': page,
      });
      final list = res.data['data'] as List;
      return list.map((e) => VendorModel.fromJson(e)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<dynamic>> getLiveOffers() async {
    try {
      final res = await _dio.get('/home/live-offers');
      return res.data['data'] as List? ?? [];
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<dynamic>> getNearYou({double? lat, double? lng, int? districtId, double radius = 1.0}) async {
    try {
      final res = await _dio.get('/home/near-you', queryParameters: {
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (districtId != null) 'district_id': districtId,
        if (lat != null) 'radius': radius, // 1km for GPS mode
      });
      return res.data['data'] as List? ?? [];
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<dynamic>> getBestSellers({int limit = 10}) async {
    try {
      final res = await _dio.get('/home/best-sellers', queryParameters: {'limit': limit});
      return res.data['data'] as List? ?? [];
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<dynamic>> getRentHomes({int limit = 8}) async {
    try {
      final res = await _dio.get('/home/rent-homes', queryParameters: {'limit': limit});
      return res.data['data'] as List? ?? [];
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<dynamic>> getUpcomingFlights({int limit = 6}) async {
    try {
      final res = await _dio.get('/home/flights', queryParameters: {'limit': limit});
      return res.data['data'] as List? ?? [];
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<VendorModel> getVendor(int id) async {
    try {
      final res = await _dio.get('/vendors/$id');
      return VendorModel.fromJson(res.data['data']);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<ProductModel>> getVendorProducts(int vendorId) async {
    try {
      final res = await _dio.get('/vendors/$vendorId/products');
      final list = res.data['data'] as List;
      return list.map((e) => ProductModel.fromJson(e)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> searchAll(String query) async {
    try {
      final res = await _dio.get('/search', queryParameters: {'q': query});
      return res.data['data'] as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
