// Conditional export — picks the web implementation on Flutter web,
// the stub on all other platforms.
export 'stripe_web_modal_stub.dart'
    if (dart.library.js) 'stripe_web_modal_web.dart';
