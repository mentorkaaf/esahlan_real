import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/realtime_provider.dart';
import '../services/realtime_client.dart';

/// Thin banner that only appears when the realtime connection isn't healthy
/// — silent when connected (the common case), so it never adds visual noise.
/// Drop it once near the root of any shell (e.g. above a TabBarView).
class RealtimeStatusBanner extends ConsumerWidget {
  const RealtimeStatusBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(realtimeConnectionProvider).valueOrNull;

    if (state == null || state == RealtimeConnectionState.connected) {
      return const SizedBox.shrink();
    }

    final (text, color) = switch (state) {
      RealtimeConnectionState.connecting => ('Connecting…', Colors.orange),
      RealtimeConnectionState.reconnecting => ('Reconnecting…', Colors.orange),
      RealtimeConnectionState.failed => ('Live updates unavailable', Colors.red),
      RealtimeConnectionState.disconnected => ('Live updates unavailable', Colors.grey),
      RealtimeConnectionState.connected => ('', Colors.transparent),
    };

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      width: double.infinity,
      color: color.withValues(alpha: 0.12),
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Center(
        child: Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
      ),
    );
  }
}
