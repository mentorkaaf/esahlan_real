import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class GiftSheet extends StatefulWidget {
  final List<GiftModel> gifts;
  final int coinBalance;
  final int roomId;
  final LiveRepository repo;

  const GiftSheet({
    super.key,
    required this.gifts,
    required this.coinBalance,
    required this.roomId,
    required this.repo,
  });

  @override
  State<GiftSheet> createState() => _GiftSheetState();
}

class _GiftSheetState extends State<GiftSheet> {
  GiftModel? _selected;
  int _qty = 1;
  bool _sending = false;
  late int _balance;

  @override
  void initState() {
    super.initState();
    _balance = widget.coinBalance;
  }

  Future<void> _send() async {
    if (_selected == null || _sending) return;
    final total = _selected!.coins * _qty;
    if (total > _balance) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Not enough coins 🪙'), backgroundColor: Colors.red),
      );
      return;
    }
    setState(() => _sending = true);
    try {
      final result = await widget.repo.sendGift(
        roomId: widget.roomId,
        giftId: _selected!.id,
        quantity: _qty,
      );
      if (mounted) {
        Navigator.of(context).pop({
          'gift_id': _selected!.id,
          'quantity': _qty,
          'new_balance': result['new_balance'],
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = (_selected?.coins ?? 0) * _qty;
    final canAfford = total <= _balance;

    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFF1A1A2E),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Handle
          Container(
            width: 40, height: 4,
            decoration: BoxDecoration(
              color: Colors.white24,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(height: 16),

          // Header
          Row(
            children: [
              const Text('🎁 Send a Gift',
                  style: TextStyle(
                      color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              const Spacer(),
              // Coin balance
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.orange.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.orange.withOpacity(0.4)),
                ),
                child: Row(
                  children: [
                    const Text('🪙', style: TextStyle(fontSize: 14)),
                    const SizedBox(width: 4),
                    Text('$_balance',
                        style: const TextStyle(
                            color: Colors.orange, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Gift grid
          SizedBox(
            height: 160,
            child: GridView.count(
              crossAxisCount: 4,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
              children: widget.gifts.map((g) {
                final selected = _selected?.id == g.id;
                return GestureDetector(
                  onTap: () => setState(() => _selected = g),
                  child: Container(
                    decoration: BoxDecoration(
                      color: selected
                          ? Colors.orange.withOpacity(0.2)
                          : Colors.white.withOpacity(0.05),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: selected ? Colors.orange : Colors.transparent,
                        width: 2,
                      ),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(g.emoji, style: const TextStyle(fontSize: 28)),
                        const SizedBox(height: 2),
                        Text('${g.coins}',
                            style: const TextStyle(
                                color: Colors.orange, fontSize: 11, fontWeight: FontWeight.bold)),
                        Text(g.name,
                            style: const TextStyle(color: Colors.white54, fontSize: 9),
                            overflow: TextOverflow.ellipsis),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
          ),

          if (_selected != null) ...[
            const SizedBox(height: 12),
            // Quantity selector
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text('Quantity: ', style: TextStyle(color: Colors.white70)),
                for (final q in [1, 5, 10, 50])
                  GestureDetector(
                    onTap: () => setState(() => _qty = q),
                    child: Container(
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: _qty == q ? Colors.orange : Colors.white12,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text('×$q',
                          style: TextStyle(
                              color: _qty == q ? Colors.white : Colors.white54,
                              fontWeight: FontWeight.bold)),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            // Send button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: canAfford && !_sending ? _send : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.orange,
                  disabledBackgroundColor: Colors.white12,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25)),
                ),
                child: _sending
                    ? const SizedBox(
                        width: 20, height: 20,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : Text(
                        canAfford
                            ? 'Send ${_selected!.emoji} for $total 🪙'
                            : 'Not enough coins',
                        style: const TextStyle(
                            color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                      ),
              ),
            ),
          ],
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
