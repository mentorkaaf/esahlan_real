// WEB implementation — only compiled on web platform
// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;
import 'dart:ui_web' as ui;

export 'dart:html' show VideoElement;

typedef VideoElementStub = html.VideoElement;

// ignore: camel_case_types
class _PlatformViewRegistry {
  void registerViewFactory(String viewId, dynamic Function(int) factory) {
    ui.platformViewRegistry.registerViewFactory(viewId, factory);
  }
}

final platformViewRegistry = _PlatformViewRegistry();

html.VideoElement createVideoElement() => html.VideoElement();
