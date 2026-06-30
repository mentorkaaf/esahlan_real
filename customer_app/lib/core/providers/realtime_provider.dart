import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/realtime_client.dart';

/// Live connection state — drive a small banner/indicator anywhere in the
/// app with `ref.watch(realtimeConnectionProvider)`.
final realtimeConnectionProvider = StreamProvider<RealtimeConnectionState>((ref) {
  final client = RealtimeClient.instance;
  // Emit the current state immediately, then follow the stream.
  return Stream.multi((controller) {
    controller.add(client.state);
    final sub = client.connectionState.listen(controller.add);
    ref.onDispose(sub.cancel);
  });
});

/// Connect once, app-wide. Call from wherever the user session becomes
/// authenticated (after login / on app start if already logged in).
Future<void> connectRealtime() => RealtimeClient.instance.connect();

/// Call on logout — closes the socket and clears all channel subscriptions
/// so a different user's session never receives stale listeners.
Future<void> disconnectRealtime() => RealtimeClient.instance.disconnect();
