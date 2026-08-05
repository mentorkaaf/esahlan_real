import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../constants/app_constants.dart';
import '../services/realtime_client.dart';

class CommunityFeatureNotifier extends StateNotifier<bool> {
  final _dio = Dio(BaseOptions(
    baseUrl: AppConstants.baseUrl,
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 10),
  ));

  CommunityFeatureNotifier() : super(!kIsWeb) {
    if (!kIsWeb) _init();
  }

  Future<void> _init() async {
    await _fetch();
    RealtimeClient.instance.listen('app.settings', 'community.status_changed', _onEvent);
  }

  Future<void> _fetch() async {
    try {
      final res = await _dio.get('/app-config');
      final data = res.data?['data'];
      if (data is Map) {
        state = data['community_enabled'] != false;
      }
    } catch (e) {
      if (kDebugMode) debugPrint('[CommunityFeature] fetch failed: $e');
    }
  }

  void _onEvent(dynamic data) {
    if (data is Map) {
      final enabled = data['enabled'];
      state = enabled == true || enabled == 1;
      if (kDebugMode) debugPrint('[CommunityFeature] realtime update: enabled=$state');
    }
  }

  @override
  void dispose() {
    RealtimeClient.instance.removeListener('app.settings', 'community.status_changed', _onEvent);
    super.dispose();
  }
}

final communityFeatureProvider =
    StateNotifierProvider<CommunityFeatureNotifier, bool>((ref) {
  return CommunityFeatureNotifier();
});
