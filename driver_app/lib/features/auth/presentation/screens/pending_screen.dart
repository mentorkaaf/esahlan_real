import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/driver_colors.dart';
import '../providers/auth_provider.dart';

class PendingScreen extends ConsumerWidget {
  const PendingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(gradient: LinearGradient(
          begin: Alignment.topCenter, end: Alignment.bottomCenter,
          colors: isDark
            ? [const Color(0xFF0A1628), const Color(0xFF162A4A)]
            : [c.navyLight, c.navy],
        )),
        child: SafeArea(child: Center(child: Padding(
          padding: const EdgeInsets.all(36),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: 1),
              duration: const Duration(milliseconds: 800),
              curve: Curves.elasticOut,
              builder: (_, v, child) => Transform.scale(scale: v, child: child),
              child: Container(width: 120, height: 120,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: [DC.orange.withValues(alpha: 0.15), DC.orange.withValues(alpha: 0.05)]),
                  shape: BoxShape.circle, border: Border.all(color: DC.orange.withValues(alpha: 0.3), width: 2)),
                child: const Icon(Icons.hourglass_top_rounded, color: DC.orange, size: 56)),
            ),
            const SizedBox(height: 32),
            Text('Under Review', style: TextStyle(color: c.text, fontSize: 28, fontWeight: FontWeight.w900)),
            const SizedBox(height: 12),
            Text(
              'Your application is being reviewed by our team.\nThis usually takes a few hours.',
              textAlign: TextAlign.center,
              style: TextStyle(color: c.textSec, fontSize: 14, height: 1.7),
            ),
            const SizedBox(height: 12),
            Container(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(color: DC.orange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
              child: const Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.notifications_active_rounded, color: DC.orange, size: 18),
                SizedBox(width: 8),
                Text("You'll be notified when approved", style: TextStyle(color: DC.orange, fontSize: 12, fontWeight: FontWeight.w600)),
              ])),
            const SizedBox(height: 40),
            GestureDetector(
              onTap: () => ref.invalidate(authStateProvider),
              child: Container(width: double.infinity, height: 52,
                decoration: BoxDecoration(border: Border.all(color: DC.orange), borderRadius: BorderRadius.circular(14)),
                child: const Center(child: Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.refresh_rounded, color: DC.orange, size: 20),
                  SizedBox(width: 8),
                  Text('Check Status', style: TextStyle(color: DC.orange, fontWeight: FontWeight.w700, fontSize: 15)),
                ]))),
            ),
            const SizedBox(height: 16),
            TextButton(onPressed: () => ref.read(logoutProvider)(),
              child: const Text('Logout', style: TextStyle(color: DC.error, fontWeight: FontWeight.w600))),
          ]),
        ))),
      ),
    );
  }
}
