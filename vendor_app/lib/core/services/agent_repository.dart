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

  // ── Phase 2+3 ──
  Future<Map<String, dynamic>> recommendProperty(int requestId, int propertyId, String? message, {double? offeredPrice}) =>
      _api.post('/agent/house-requests/$requestId/recommend', data: {
        'property_id': propertyId,
        if (message != null && message.isNotEmpty) 'message': message,
        if (offeredPrice != null) 'offered_price': offeredPrice,
      });

  Future<Map<String, dynamic>> getRecommendations(int requestId) =>
      _api.get('/agent/house-requests/$requestId/recommendations');

  Future<Map<String, dynamic>> scheduleViewing(int requestId, Map<String, dynamic> data) =>
      _api.post('/agent/house-requests/$requestId/viewings', data: data);

  Future<Map<String, dynamic>> updateViewingStatus(int viewId, String status) =>
      _api.post('/agent/house-requests/viewings/$viewId/status', data: {'status': status});

  Future<Map<String, dynamic>> getMessages(int requestId) =>
      _api.get('/agent/house-requests/$requestId/messages');

  Future<Map<String, dynamic>> sendMessage(int requestId, String message) =>
      _api.post('/agent/house-requests/$requestId/messages', data: {'message': message});

  // ── House Requests (original) ──
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
