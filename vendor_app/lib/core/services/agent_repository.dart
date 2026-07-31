import 'dart:io';
import 'package:dio/dio.dart';
import '../api/api_client.dart';

class AgentRepository {
  AgentRepository._();
  static final instance = AgentRepository._();
  final _api = ApiClient();

  Future<Map<String, dynamic>> dashboard() =>
      _api.get('/agent/dashboard');

  Future<Map<String, dynamic>> districts() =>
      _api.get('/districts');

  Future<Map<String, dynamic>> properties({String? status, int page = 1}) =>
      _api.get('/agent/properties', queryParameters: {
        if (status != null) 'status': status,
        'page': page,
      });

  Future<Map<String, dynamic>> createProperty(Map<String, dynamic> data, List<File> images) async {
    final formData = FormData.fromMap(data);
    for (final img in images) {
      formData.files.add(MapEntry(
        'images[]',
        await MultipartFile.fromFile(img.path, filename: img.path.split('/').last),
      ));
    }
    return _api.postForm('/agent/properties', formData);
  }

  Future<Map<String, dynamic>> updateProperty(int id, Map<String, dynamic> data) =>
      _api.put('/agent/properties/$id', data: data);

  Future<Map<String, dynamic>> markRented(int id) =>
      _api.post('/agent/properties/$id/rented', data: {});

  Future<Map<String, dynamic>> markAvailable(int id) =>
      _api.post('/agent/properties/$id/available', data: {});

  Future<Map<String, dynamic>> deleteProperty(int id) =>
      _api.delete('/agent/properties/$id');

  Future<Map<String, dynamic>> wallet() =>
      _api.get('/agent/wallet');

  Future<Map<String, dynamic>> houseRequests({int page = 1}) =>
      _api.get('/agent/house-requests', queryParameters: {'page': page});

  Future<Map<String, dynamic>> assignRequest(int id) =>
      _api.post('/agent/house-requests/$id/assign', data: {});

  Future<Map<String, dynamic>> updateRequestStatus(int id, String status) =>
      _api.post('/agent/house-requests/$id/update-status', data: {'status': status});

  Future<Map<String, dynamic>> contactRequest(int id) =>
      _api.post('/agent/house-requests/$id/contact', data: {});

  Future<Map<String, dynamic>> closeRequest(int id) =>
      _api.post('/agent/house-requests/$id/close', data: {});
}
