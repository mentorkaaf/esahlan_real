import 'package:flutter/services.dart';

/// Platform channel client for native Android video filter.
/// The Kotlin side intercepts WebRTC frames and applies ColorMatrix filters
/// before they're sent to LiveKit — so viewers see the filtered stream.
class LiveFilterService {
  static const _channel = MethodChannel('com.esahlan.live/filter');

  static final LiveFilterService instance = LiveFilterService._();
  LiveFilterService._();

  String? _filteredTrackId;

  /// Call after LiveKit camera track is created.
  /// [originalTrackId] is the flutter_webrtc track ID from the local video track.
  /// Returns the new filtered track ID (register this with LiveKit instead).
  Future<String?> startFilter(String originalTrackId, String filterName) async {
    try {
      final newId = await _channel.invokeMethod<String>('startFilter', {
        'trackId': originalTrackId,
        'filter': filterName,
      });
      _filteredTrackId = newId;
      return newId;
    } catch (e) {
      // Native filter unavailable (e.g. iOS, or reflection failed) — graceful fallback
      return null;
    }
  }

  /// Switch to a different filter without interrupting the stream.
  Future<void> setFilter(String filterName) async {
    try {
      await _channel.invokeMethod('setFilter', {'filter': filterName});
    } catch (_) {}
  }

  /// Stop filtering and release resources.
  Future<void> stopFilter() async {
    try {
      await _channel.invokeMethod('stopFilter');
      _filteredTrackId = null;
    } catch (_) {}
  }

  bool get isActive => _filteredTrackId != null;
}
