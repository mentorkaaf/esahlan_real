import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'notification_provider.dart';

const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);

class NotificationScreen extends ConsumerWidget {
  const NotificationScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsProvider);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0D0D0D) : const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: _kNavy,
        foregroundColor: Colors.white,
        title: const Text('Notifications', style: TextStyle(fontWeight: FontWeight.w700)),
        actions: [
          TextButton(
            onPressed: () => ref.read(notificationsProvider.notifier).markAllRead(),
            child: const Text('Mark all read', style: TextStyle(color: _kOrange, fontSize: 13)),
          ),
        ],
      ),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (notifications) {
          if (notifications.isEmpty) {
            return const Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.notifications_none_rounded, size: 64, color: Colors.grey),
                SizedBox(height: 12),
                Text('No notifications yet', style: TextStyle(color: Colors.grey, fontSize: 16)),
              ]),
            );
          }
          return RefreshIndicator(
            onRefresh: () => ref.refresh(notificationsProvider.future),
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: notifications.length,
              separatorBuilder: (_, __) => const Divider(height: 1, indent: 56),
              itemBuilder: (context, i) => _NotifTile(n: notifications[i], isDark: isDark),
            ),
          );
        },
      ),
    );
  }
}

class _NotifTile extends ConsumerWidget {
  final AppNotification n;
  final bool isDark;
  const _NotifTile({required this.n, required this.isDark});

  IconData _icon() {
    return switch (n.type) {
      'points_earned'       => Icons.star_rounded,
      'referral_rewarded'   => Icons.people_alt_rounded,
      'affiliate_commission'=> Icons.handshake_rounded,
      'tier_upgrade'        => Icons.military_tech_rounded,
      'points_redeemed'     => Icons.currency_exchange_rounded,
      'payout_approved'     => Icons.check_circle_rounded,
      'payout_rejected'     => Icons.cancel_rounded,
      'points_expiring'     => Icons.timer_rounded,
      _                     => Icons.notifications_rounded,
    };
  }

  Color _iconColor() {
    return switch (n.type) {
      'payout_rejected' => Colors.red,
      'payout_approved' => Colors.green,
      'tier_upgrade'    => const Color(0xFFFFD700),
      _                 => _kOrange,
    };
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bg = n.read
        ? Colors.transparent
        : (isDark ? _kOrange.withValues(alpha: 0.08) : _kOrange.withValues(alpha: 0.05));

    return InkWell(
      onTap: () => ref.read(notificationsProvider.notifier).markRead(n.id),
      child: Container(
        color: bg,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: _iconColor().withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Icon(_icon(), color: _iconColor(), size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(
                child: Text(n.title, style: TextStyle(
                  fontWeight: n.read ? FontWeight.w500 : FontWeight.w700,
                  fontSize: 14,
                  color: isDark ? Colors.white : const Color(0xFF1A1A2E),
                )),
              ),
              if (!n.read)
                Container(
                  width: 8, height: 8,
                  decoration: const BoxDecoration(color: _kOrange, shape: BoxShape.circle),
                ),
            ]),
            if (n.body.isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(n.body, style: TextStyle(
                fontSize: 13,
                color: isDark ? Colors.white70 : Colors.black54,
              )),
            ],
            const SizedBox(height: 4),
            Text(_timeAgo(n.createdAt), style: const TextStyle(fontSize: 11, color: Colors.grey)),
          ])),
        ]),
      ),
    );
  }

  String _timeAgo(String raw) {
    try {
      final dt = DateTime.parse(raw).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 1) return 'Just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      return '${diff.inDays}d ago';
    } catch (_) {
      return '';
    }
  }
}
