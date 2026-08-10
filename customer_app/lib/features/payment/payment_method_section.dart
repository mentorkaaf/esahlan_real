import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers/payment_methods_provider.dart';

/// A shared payment method picker that auto-hides methods disabled by admin.
/// [selected] is one of: 'wallet' | 'waafi_pay' | 'mobile_pay' | 'cod'
class PaymentMethodSection extends ConsumerWidget {
  final String selected;
  final ValueChanged<String> onChanged;
  final List<_MethodDef>? available;
  final bool showCod;

  const PaymentMethodSection({
    super.key,
    required this.selected,
    required this.onChanged,
    this.available,
    this.showCod = false,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dataAsync = ref.watch(enabledPaymentMethodsProvider);
    final data = dataAsync.valueOrNull ?? PaymentMethodsData.fallback;
    final logos = data.logos;

    final methods = available ??
        [
          if (showCod) _MethodDef('cod',        '💵', 'Cash on Delivery', 'Pay when delivered', const Color(0xFF607D8B)),
          _MethodDef('mobile_pay', '📲', 'Mobile Pay',     'EVC Plus, Waafi, Sahal — USSD', const Color(0xFF4CAF50)),
          _MethodDef('wallet',     '👛', 'ePay Balance',   'Deducted from your eSahlan ePay', const Color(0xFF1565C0)),
          _MethodDef('waafi_pay',  '📱', 'Waafi Pay',      'EVC / eDahab / Jeep / Premier', const Color(0xFFFF8A00)),
        ];

    final visible = methods.where((m) => isMethodEnabled(data.enabled, m.key)).toList();

    if (visible.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFFFFF3E0), borderRadius: BorderRadius.circular(12)),
        child: const Text('No payment methods available. Contact support.', style: TextStyle(color: Color(0xFFE65100), fontWeight: FontWeight.w600, fontSize: 13)),
      );
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!visible.any((m) => m.key == selected)) {
        onChanged(visible.first.key);
      }
    });

    return Column(
      children: visible.asMap().entries.map((e) {
        final m = e.value;
        return Padding(
          padding: EdgeInsets.only(bottom: e.key < visible.length - 1 ? 10 : 0),
          child: _PayMethodTile(
            label: m.label,
            sub: m.sub,
            emoji: m.emoji,
            color: m.color,
            logoUrl: logos[m.key],
            selected: selected == m.key,
            onTap: () => onChanged(m.key),
          ),
        );
      }).toList(),
    );
  }
}

class _MethodDef {
  final String key, emoji, label, sub;
  final Color color;
  const _MethodDef(this.key, this.emoji, this.label, this.sub, this.color);
}

class _PayMethodTile extends StatelessWidget {
  final String label, sub, emoji;
  final String? logoUrl;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _PayMethodTile({
    required this.label, required this.sub, required this.emoji,
    required this.color, required this.selected, required this.onTap,
    this.logoUrl,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.08) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? color : const Color(0xFFEEEEEE), width: selected ? 2 : 1.5),
        ),
        child: Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
            child: logoUrl != null
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Image.network(
                      logoUrl!,
                      width: 40, height: 40,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => Center(child: Text(emoji, style: const TextStyle(fontSize: 20))),
                    ),
                  )
                : Center(child: Text(emoji, style: const TextStyle(fontSize: 20))),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: selected ? color : const Color(0xFF1A1A2E))),
            const SizedBox(height: 2),
            Text(sub, style: const TextStyle(fontSize: 11, color: Color(0xFF9E9E9E))),
          ])),
          if (selected) Icon(Icons.check_circle_rounded, color: color, size: 20),
        ]),
      ),
    );
  }
}
