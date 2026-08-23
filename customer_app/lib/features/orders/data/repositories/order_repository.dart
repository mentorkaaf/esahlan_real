import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../models/order_model.dart';

class OrderRepository {
  final Dio _dio = ApiClient.instance;

  Future<List<OrderModel>> getOrders({String? status, int page = 1}) async {
    try {
      final res = await _dio.get('/orders', queryParameters: {
        if (status != null) 'status': status,
        'page': page,
      });
      final list = res.data['data'] as List;
      return list.map((e) => OrderModel.fromJson(e)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<OrderModel> getOrder(int id) async {
    try {
      final res = await _dio.get('/orders/$id');
      return OrderModel.fromJson(res.data['data']);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<OrderModel> cancelOrder(int id) async {
    try {
      final res = await _dio.post('/orders/$id/cancel');
      return OrderModel.fromJson(res.data['data']);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> getAnalytics() async {
    try {
      final res = await _dio.get('/orders/analytics');
      return Map<String, dynamic>.from(res.data['data']);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> getTracking(int id) async {
    try {
      final res = await _dio.get('/orders/$id/tracking');
      return res.data['data'] as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
