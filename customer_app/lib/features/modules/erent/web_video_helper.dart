// Conditional import — automatically picks the right implementation
export 'web_video_stub.dart'
    if (dart.library.html) 'web_video_web.dart';
