// Web implementation — calls window.showStripeModal() defined in web/index.html.
// Uses a JS callback (window.__onStripeResult) instead of Promise/promiseToFuture
// to avoid dart:js_util compatibility issues in Dart 3.x.
// ignore_for_file: avoid_web_libraries_in_flutter
import 'dart:async';
import 'dart:convert';
import 'dart:js' as js;
// ignore: uri_does_not_exist
import 'dart:js_util' as js_util;
import 'package:flutter/widgets.dart';

Future<Map<String, dynamic>?> showStripeWebModal({
  required BuildContext context,
  required String publicKey,
  required String clientSecret,
  required String totalLabel,
}) async {
  final completer = Completer<Map<String, dynamic>?>();

  // Register the one-shot JS callback that the modal will call when done.
  js.context['__onStripeResult'] = js_util.allowInterop((dynamic jsonStr) {
    js.context['__onStripeResult'] = null; // clean up
    try {
      final map = jsonDecode(jsonStr as String) as Map<String, dynamic>;
      if (!completer.isCompleted) completer.complete(map);
    } catch (e) {
      if (!completer.isCompleted) {
        completer.completeError(Exception('Failed to parse Stripe result: $e'));
      }
    }
  });

  // Launch the JS modal (non-blocking — it will call __onStripeResult when done).
  try {
    js.context.callMethod('showStripeModal', [publicKey, clientSecret, totalLabel]);
  } catch (e) {
    js.context['__onStripeResult'] = null;
    throw Exception('Failed to open Stripe modal: $e');
  }

  return completer.future;
}
