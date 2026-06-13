import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../models/district_model.dart';

class DistrictRepository {
  final Dio _dio = ApiClient.instance;

  Future<List<DistrictModel>> getDistricts() async {
    try {
      final res = await _dio.get('/districts');
      final list = res.data['data'] as List<dynamic>;
      return list.map((e) => DistrictModel.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
