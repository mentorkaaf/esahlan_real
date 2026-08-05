import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

extension WebNavX on BuildContext {
  // On web: go() keeps within shell (sidebar stays visible)
  // On mobile: push() adds to stack (native back gesture works)
  void webPush(String location, {Object? extra}) {
    if (kIsWeb) {
      go(location, extra: extra);
    } else {
      push(location, extra: extra);
    }
  }
}
