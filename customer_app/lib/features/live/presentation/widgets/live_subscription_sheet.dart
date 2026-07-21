import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class LiveSubscriptionSheet extends StatefulWidget {
  final int hostId;
  final String hostName;
  final void Function(LiveSubscription) onSubscribed;

  const LiveSubscriptionSheet({
    super.key,
    required this.hostId,
    required this.hostName,
    required this.onSubscribed,
  });

  @override
  State<LiveSubscriptionSheet> createState() => _LiveSubscriptionSheetState();
}

class _LiveSubscriptionSheetState extends State<LiveSubscriptionSheet> {
  final _repo = LiveRepository();
  List<LiveSubscriptionTier> _tiers = [];
  bool _loading = true;
  String? _subscribing;

  // Default tiers if host hasn't configured custom ones
  static const _defaultTiers = [
    LiveSubscriptionTier(
        tier: 'basic', priceUsd: 4.99, badgeEmoji: '⭐',
        badgeLabel: 'Subscriber', perks: ['Subscriber badge', 'Support the creator']),
    LiveSubscriptionTier(
        tier: 'supporter', priceUsd: 9.99, badgeEmoji: '💎',
        badgeLabel: 'Supporter', perks: ['💎 Diamond badge', 'Priority Q&A', 'Name highlighted']),
    LiveSubscriptionTier(
        tier: 'superfan', priceUsd: 24.99, badgeEmoji: '👑',
        badgeLabel: 'Super Fan', perks: ['👑 Crown badge', 'All Supporter perks', 'VIP status']),
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final t = await _repo.getSubscriptionTiers(widget.hostId);
      if (mounted) setState(() { _tiers = t.isEmpty ? _defaultTiers : t; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _tiers = _defaultTiers; _loading = false; });
    }
  }

  Future<void> _subscribe(String tier) async {
    setState(() => _subscribing = tier);
    try {
      final res = await _repo.subscribe(widget.hostId, tier);
      if (mounted) {
        Navigator.pop(context);
        widget.onSubscribed(LiveSubscription(
          tier: tier,
          expiresAt: DateTime.tryParse(res['expires_at'] ?? '') ?? DateTime.now().add(const Duration(days: 30)),
        ));
      }
    } catch (e) {
      setState(() => _subscribing = null);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: const BoxDecoration(
        color: Color(0xFF1A1A2E),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          Text(
            'Subscribe to ${widget.hostName}',
            style: const TextStyle(color: Colors.white, fontSize: 18,
                fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          const Text(
            'Support your favorite creator each month',
            style: TextStyle(color: Colors.white54, fontSize: 12),
          ),
          const SizedBox(height: 20),
          if (_loading)
            const CircularProgressIndicator(color: Colors.orange)
          else
            ..._tiers.map((t) => _TierCard(
                  tier: t,
                  loading: _subscribing == t.tier,
                  onTap: () => _subscribe(t.tier),
                )),
          const SizedBox(height: 12),
          const Text(
            '1 USD = 100 coins • Renews monthly',
            style: TextStyle(color: Colors.white24, fontSize: 10),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}

class _TierCard extends StatelessWidget {
  final LiveSubscriptionTier tier;
  final bool loading;
  final VoidCallback onTap;

  const _TierCard({required this.tier, required this.loading, required this.onTap});

  Color get _color {
    switch (tier.tier) {
      case 'supporter': return const Color(0xFF4FC3F7);
      case 'superfan':  return Colors.amber;
      default:          return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _color.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          Text(tier.badgeEmoji, style: const TextStyle(fontSize: 28)),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(tier.badgeLabel,
                        style: TextStyle(color: _color, fontWeight: FontWeight.bold,
                            fontSize: 14)),
                    const SizedBox(width: 8),
                    Text('\$${tier.priceUsd.toStringAsFixed(2)}/mo',
                        style: const TextStyle(color: Colors.white70, fontSize: 12)),
                  ],
                ),
                const SizedBox(height: 4),
                ...tier.perks.take(2).map((p) => Text('• $p',
                    style: const TextStyle(color: Colors.white54, fontSize: 11))),
              ],
            ),
          ),
          const SizedBox(width: 10),
          ElevatedButton(
            onPressed: loading ? null : onTap,
            style: ElevatedButton.styleFrom(
              backgroundColor: _color,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            ),
            child: loading
                ? const SizedBox(width: 16, height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Sub', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }
}
