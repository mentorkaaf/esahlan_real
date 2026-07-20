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

class _GiftSheetState extends State<GiftSheet> with SingleTickerProviderStateMixin {
  GiftModel? _selected;
  int _qty = 1;
  bool _sending = false;
  late int _balance;
  late TabController _tabCtrl;

  static const _categories = ['All', 'Normal', 'Premium', 'Epic', 'Legendary'];

  @override
  void initState() {
    super.initState();
    _balance = widget.coinBalance;
    _tabCtrl = TabController(length: _categories.length, vsync: this);
    _tabCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  List<GiftModel> get _filtered {
    final cat = _categories[_tabCtrl.index].toLowerCase();
    if (cat == 'all') return widget.gifts;
    return widget.gifts.where((g) {
      if (cat == 'legendary') return g.rarity == GiftRarity.legendary;
      if (cat == 'epic')      return g.rarity == GiftRarity.epic;
      if (cat == 'premium')   return g.category == 'premium' || g.rarity == GiftRarity.rare;
      return g.category == 'normal' && g.rarity == GiftRarity.normal;
    }).toList();
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
        setState(() => _sending = false);
      }
    }
  }

  Color _rarityColor(GiftRarity r) {
    switch (r) {
      case GiftRarity.legendary: return const Color(0xFFFFD700);
      case GiftRarity.epic:      return const Color(0xFFAA00FF);
      case GiftRarity.rare:      return const Color(0xFF4FC3F7);
      default:                   return Colors.white54;
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = (_selected?.coins ?? 0) * _qty;
    final canAfford = total <= _balance;
    final filtered = _filtered;

    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFF0F0F1A),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const SizedBox(height: 14),
          // Header
          Row(
            children: [
              const Text('🎁 Gifts',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.orange.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.orange.withValues(alpha: 0.4)),
                ),
                child: Row(
                  children: [
                    const Text('🪙', style: TextStyle(fontSize: 14)),
                    const SizedBox(width: 4),
                    Text('$_balance',
                        style: const TextStyle(color: Colors.orange, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Category tabs
          TabBar(
            controller: _tabCtrl,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            indicatorColor: Colors.orange,
            labelColor: Colors.orange,
            unselectedLabelColor: Colors.white38,
            dividerColor: Colors.transparent,
            labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            tabs: _categories.map((c) => Tab(text: c)).toList(),
          ),
          const SizedBox(height: 10),
          // Gift grid
          SizedBox(
            height: 170,
            child: filtered.isEmpty
                ? const Center(child: Text('No gifts in this category', style: TextStyle(color: Colors.white38)))
                : GridView.count(
                    crossAxisCount: 4,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 8,
                    children: filtered.map((g) {
                      final sel = _selected?.id == g.id;
                      final rc = _rarityColor(g.rarity);
                      return GestureDetector(
                        onTap: () => setState(() { _selected = g; _qty = 1; }),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          decoration: BoxDecoration(
                            color: sel
                                ? rc.withValues(alpha: 0.18)
                                : Colors.white.withValues(alpha: 0.05),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: sel ? rc : (g.rarity != GiftRarity.normal ? rc.withValues(alpha: 0.3) : Colors.transparent),
                              width: sel ? 2 : 1,
                            ),
                          ),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(g.emoji, style: const TextStyle(fontSize: 26)),
                              const SizedBox(height: 2),
                              Text('${g.coins} 🪙',
                                  style: TextStyle(color: rc, fontSize: 10, fontWeight: FontWeight.bold)),
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
            const SizedBox(height: 10),
            // Selected gift info
            Row(
              children: [
                Text(_selected!.emoji, style: const TextStyle(fontSize: 22)),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_selected!.name,
                        style: TextStyle(
                            color: _rarityColor(_selected!.rarity),
                            fontWeight: FontWeight.bold,
                            fontSize: 13)),
                    Text(_selected!.rarity.name.toUpperCase(),
                        style: TextStyle(
                            color: _rarityColor(_selected!.rarity).withValues(alpha: 0.7),
                            fontSize: 10,
                            letterSpacing: 1)),
                  ],
                ),
                const Spacer(),
                // Quantity buttons
                for (final q in [1, 5, 10, 50])
                  GestureDetector(
                    onTap: () => setState(() => _qty = q),
                    child: Container(
                      margin: const EdgeInsets.only(left: 6),
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: _qty == q ? Colors.orange : Colors.white10,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text('×$q',
                          style: TextStyle(
                              color: _qty == q ? Colors.white : Colors.white38,
                              fontSize: 12,
                              fontWeight: FontWeight.bold)),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            // Send button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: canAfford && !_sending ? _send : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.orange,
                  disabledBackgroundColor: Colors.white10,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _sending
                    ? const SizedBox(
                        width: 20, height: 20,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : Text(
                        canAfford
                            ? 'Send ${_selected!.emoji}  ×$_qty  — $total 🪙'
                            : 'Not enough coins — tap balance to buy',
                        style: const TextStyle(
                            color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                      ),
              ),
            ),
          ],
          SizedBox(height: MediaQuery.of(context).padding.bottom),
        ],
      ),
    );
  }
}
