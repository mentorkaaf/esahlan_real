// STUB — used on non-web platforms (Android / iOS)
// Provides empty implementations of web-only APIs

// ignore_for_file: avoid_classes_with_only_static_members

class VideoElementStub {
  String src = '';
  bool paused = true;
  final _style = _StyleStub();
  _StyleStub get style => _style;
  bool autoplay = false;
  bool loop = false;
  bool muted = false;
  String playsInline = '';
  void play() {}
  void pause() {}
  void remove() {}
}

class _StyleStub {
  String width = '';
  String height = '';
  String objectFit = '';
}

// ignore: camel_case_types
class _PlatformViewRegistry {
  void registerViewFactory(String viewId, dynamic Function(int) factory) {}
}

final platformViewRegistry = _PlatformViewRegistry();

VideoElementStub createVideoElement() => VideoElementStub();
