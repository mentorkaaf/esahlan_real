import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:livekit_client/livekit_client.dart';
import 'live_filter_service.dart';

/// LiveKit TrackProcessor that hooks into flutter_webrtc's ExternalVideoFrameProcessing
/// pipeline so the ColorMatrix filter is applied BEFORE WebRTC encoding.
/// Viewers see the filtered video stream on Android.
///
/// On non-Android platforms it's a no-op — the BeautyFilterWidget handles
/// local preview only.
class LiveNativeFilterProcessor implements TrackProcessor<VideoProcessorOptions> {
  LiveNativeFilterProcessor(String filterName) : _filterName = filterName;

  String _filterName;
  MediaStreamTrack? _processedTrack;

  @override
  String get name => 'live_native_filter';

  @override
  MediaStreamTrack? get processedTrack => _processedTrack;

  @override
  Future<void> init(VideoProcessorOptions options) async {
    // Always publish the original track — native side modifies frames in-place
    // via LocalVideoTrack.addProcessor() before they reach the WebRTC encoder.
    _processedTrack = options.track;

    if (!defaultTargetPlatform.isAndroid) return;

    final trackId = options.track.id;
    if (trackId == null || _filterName == 'none') return;

    try {
      await LiveFilterService.instance.startFilter(trackId, _filterName);
    } catch (e) {
      debugPrint('[LiveFilter] TrackProcessor.init failed: $e');
    }
  }

  @override
  Future<void> restart(VideoProcessorOptions options) async {
    _processedTrack = options.track;
    if (!defaultTargetPlatform.isAndroid || _filterName == 'none') return;
    final trackId = options.track.id;
    if (trackId == null) return;
    try {
      await LiveFilterService.instance.startFilter(trackId, _filterName);
    } catch (e) {
      debugPrint('[LiveFilter] TrackProcessor.restart failed: $e');
    }
  }

  @override
  Future<void> destroy() async {
    await LiveFilterService.instance.stopFilter();
    _processedTrack = null;
  }

  @override
  Future<void> onPublish(Room room) async {}

  @override
  Future<void> onUnpublish() async {}

  /// Switch filter without restarting camera.
  Future<void> updateFilter(String filterName) async {
    _filterName = filterName;
    if (!defaultTargetPlatform.isAndroid) return;
    try {
      await LiveFilterService.instance.setFilter(filterName);
    } catch (_) {}
  }
}

extension on TargetPlatform {
  bool get isAndroid => this == TargetPlatform.android;
}
