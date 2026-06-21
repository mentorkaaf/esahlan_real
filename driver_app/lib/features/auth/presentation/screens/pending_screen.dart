import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/driver_colors.dart';
import '../providers/auth_provider.dart';

class PendingScreen extends ConsumerWidget {
  const PendingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: DC.navy,
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 100, height: 100,
              decoration: BoxDecoration(color: DC.orangeDim, shape: BoxShape.circle),
              child: const Icon(Icons.hourglass_top_rounded, color: DC.orange, size: 48),
            ),
            const SizedBox(height: 28),
            const Text('Under Review', style: TextStyle(color: DC.text, fontSize: 24, fontWeight: FontWeight.w900)),
            const SizedBox(height: 12),
            const Text(
              'Your application is being reviewed by our team.\nYou will be notified once approved.',
              textAlign: TextAlign.center,
              style: TextStyle(color: DC.textSec, fontSize: 14, height: 1.6),
            ),
            const SizedBox(height: 36),
            SizedBox(width: double.infinity, height: 50, child: OutlinedButton.icon(
              onPressed: () => ref.invalidate(authStateProvider),
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Check Status'),
            )),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => ref.read(logoutProvider)(),
              child: const Text('Logout', style: TextStyle(color: DC.error)),
            ),
          ]),
        ),
      ),
    );
  }
}
