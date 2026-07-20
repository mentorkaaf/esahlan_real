import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class CoinPurchaseSheet extends StatefulWidget {
  final int currentBalance;
  final Function(int newBalance) onPurchased;

  const CoinPurchaseSheet({
    super.key,
    required this.currentBalance,
    required this.onPurchased,
  });

  @override
  State<CoinPurchaseSheet> createState() => _CoinPurchaseSheetState();
}

class _CoinPurchaseSheetState extends State<CoinPurchaseSheet> {
  final _repo = LiveRepository();
  List<CoinPackage> _packages = [];
  bool _loading = true;
  CoinPackage? _selected;
  bool _buying = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final packages = await _repo.getCoinPackages();
      if (mounted) setState(() { _packages = packages; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _buy() async {
    final pkg = _selected;
    if (pkg == null) return;
    setState(() => _buying = true);
    try {
      // Show payment method picker
      final method = await _showPaymentPicker();
      if (method == null || !mounted) {
        setState(() => _buying = false);
        return;
      }
      // In production: integrate WaafiPay/ePay SDK here
      // For now: use a placeholder reference (real integration sends the SDK-returned reference)
      final ref = 'ref_${DateTime.now().millisecondsSinceEpoch}_${pkg.id}';
      final result = await _repo.buyCoins(
        packageId: pkg.id,
        paymentMethod: method,
        paymentReference: ref,
      );
      final newBalance = result['new_balance'] as int? ?? widget.currentBalance;
      if (mounted) {
        widget.onPurchased(newBalance);
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: Colors.green[800],
            content: Text('${result['coins_received']} coins added to your wallet!'),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Purchase failed: $e'), backgroundColor: Colors.red),
        );
        setState(() => _buying = false);
      }
    }
  }

  Future<String?> _showPaymentPicker() => showDialog<String>(
    context: context,
    builder: (_) => AlertDialog(
      backgroundColor: const Color(0xFF1A1A2E),
      title: const Text('Choose payment method', style: TextStyle(color: Colors.white)),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _PaymentOption(label: 'WaafiPay', icon: '📱', value: 'waafi_pay'),
          const SizedBox(height: 8),
          _PaymentOption(label: 'ePay', icon: '💳', value: 'epay'),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFF121218),
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color: Colors.white24,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),
          // Header
          Row(
            children: [
              const Text('🪙', style: TextStyle(fontSize: 22)),
              const SizedBox(width: 10),
              const Text('Buy Coins',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.orange.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${widget.currentBalance} coins',
                  style: const TextStyle(color: Colors.orange, fontSize: 13, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          if (_loading)
            const Center(child: CircularProgressIndicator(color: Colors.orange))
          else if (_packages.isEmpty)
            const Center(
              child: Text('No packages available', style: TextStyle(color: Colors.white54)),
            )
          else ...[
            // Package grid
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                childAspectRatio: 0.9,
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
              ),
              itemCount: _packages.length,
              itemBuilder: (_, i) => _PackageCard(
                package: _packages[i],
                selected: _selected?.id == _packages[i].id,
                onTap: () => setState(() => _selected = _packages[i]),
              ),
            ),
            const SizedBox(height: 20),
            // Buy button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: _selected == null || _buying ? null : _buy,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.orange,
                  disabledBackgroundColor: Colors.orange.withValues(alpha: 0.3),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _buying
                    ? const SizedBox(
                        width: 20, height: 20,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                      )
                    : Text(
                        _selected != null
                            ? 'Buy ${_selected!.totalCoins} coins — \$${_selected!.price.toStringAsFixed(2)}'
                            : 'Select a package',
                        style: const TextStyle(
                            color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                      ),
              ),
            ),
            const SizedBox(height: 8),
            const Center(
              child: Text(
                'Payments are processed securely via WaafiPay / ePay',
                style: TextStyle(color: Colors.white38, fontSize: 11),
              ),
            ),
          ],
          SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
        ],
      ),
    );
  }
}

class _PackageCard extends StatelessWidget {
  final CoinPackage package;
  final bool selected;
  final VoidCallback onTap;

  const _PackageCard({required this.package, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        decoration: BoxDecoration(
          color: selected ? Colors.orange.withValues(alpha: 0.18) : const Color(0xFF1E1E2A),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? Colors.orange : Colors.white12,
            width: selected ? 2 : 1,
          ),
        ),
        child: Stack(
          children: [
            if (package.badgeLabel != null)
              Positioned(
                top: 6, right: 6,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: package.isFeatured ? Colors.orange : Colors.red,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    package.badgeLabel!,
                    style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text('🪙', style: TextStyle(fontSize: 28)),
                  const SizedBox(height: 4),
                  Text(
                    '${package.totalCoins}',
                    style: const TextStyle(
                        color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  if (package.bonusCoins > 0)
                    Text(
                      '+${package.bonusCoins} bonus',
                      style: const TextStyle(color: Colors.orange, fontSize: 10),
                    ),
                  const SizedBox(height: 4),
                  Text(
                    '\$${package.price.toStringAsFixed(2)}',
                    style: const TextStyle(color: Colors.white54, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PaymentOption extends StatelessWidget {
  final String label;
  final String icon;
  final String value;

  const _PaymentOption({required this.label, required this.icon, required this.value});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.of(context).pop(value),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white10,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            Text(icon, style: const TextStyle(fontSize: 20)),
            const SizedBox(width: 12),
            Text(label, style: const TextStyle(color: Colors.white, fontSize: 15)),
            const Spacer(),
            const Icon(Icons.chevron_right, color: Colors.white38),
          ],
        ),
      ),
    );
  }
}
