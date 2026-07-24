import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/theme_x.dart';
import '../../shared/widgets/wallet_pin_dialog.dart';
import 'mobile_pay_sheet.dart';
import 'waafi_pay_sheet.dart';

// ─── Result ───────────────────────────────────────────────────────────────────

class OrderPaymentResult {
  final bool success;
  final String paymentMethod; // 'mobile_pay' | 'waafi_pay' | 'wallet'
  final String? reference;

  const OrderPaymentResult({
    required this.success,
    required this.paymentMethod,
    this.reference,
  });
}

// ─── Entry point ─────────────────────────────────────────────────────────────

/// Show payment method picker (Mobile Pay, Waafi Pay, ePay Wallet).
/// Handles the full payment flow and returns the result.
Future<OrderPaymentResult?> showOrderPaymentSheet(
  BuildContext context, {
  required double amount,
  String? description,
}) {
  return showModalBottomSheet<OrderPaymentResult>(
    context: context,
    isScrollControlled: true,
    useRootNavigator: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _OrderPaymentPickerSheet(amount: amount, description: description),
  );
}

// ─── Sheet ───────────────────────────────────────────────────────────────────

class _OrderPaymentPickerSheet extends StatelessWidget {
  final double amount;
  final String? description;
  const _OrderPaymentPickerSheet({required this.amount, this.description});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(
        left: 20, right: 20, top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 28,
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 20),

        // Header
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: context.colors.navyText)),
            Text(
              '\$${amount.toStringAsFixed(2)}${description != null ? ' · $description' : ''}',
              style: const TextStyle(color: AppColors.textGrey, fontSize: 13),
            ),
          ])),
          IconButton(
            icon: const Icon(Icons.close_rounded, color: AppColors.textGrey),
            onPressed: () => Navigator.of(context).pop(),
          ),
        ]),

        const SizedBox(height: 20),

        // Mobile Pay
        _MethodTile(
          icon: Icons.phone_in_talk_rounded,
          color: const Color(0xFF4CAF50),
          title: 'Mobile Pay',
          subtitle: 'EVC Plus, Waafi, Sahal — in-app USSD dial',
          onTap: () => _pickMobile(context),
        ),
        const SizedBox(height: 10),

        // Waafi Pay
        _MethodTile(
          icon: Icons.account_balance_wallet_rounded,
          color: AppColors.primary,
          title: 'Waafi Pay',
          subtitle: 'Receive a confirmation prompt on your phone',
          onTap: () => _pickWaafi(context),
        ),
        const SizedBox(height: 10),

        // ePay Wallet
        _MethodTile(
          icon: Icons.wallet_rounded,
          color: const Color(0xFF7B1FA2),
          title: 'ePay Wallet',
          subtitle: 'Pay from your ePay balance',
          onTap: () => _pickWallet(context),
        ),

        const SizedBox(height: 8),
      ]),
    );
  }

  Future<void> _pickMobile(BuildContext context) async {
    Navigator.of(context).pop(); // close picker
    if (!context.mounted) return;
    final res = await showMobilePaySheet(context, amount: amount, description: description);
    if (!context.mounted) return;
    if (res?.success == true) {
      Navigator.of(context, rootNavigator: true).pop(
        OrderPaymentResult(
          success: true,
          paymentMethod: 'mobile_pay',
          reference: 'mobile_pay_${res!.account?.id}',
        ),
      );
    }
  }

  Future<void> _pickWaafi(BuildContext context) async {
    Navigator.of(context).pop(); // close picker
    if (!context.mounted) return;
    final res = await showWaafiPaySheet(context, amount: amount, type: 'order', description: description);
    if (!context.mounted) return;
    if (res?.success == true) {
      Navigator.of(context, rootNavigator: true).pop(
        OrderPaymentResult(
          success: true,
          paymentMethod: 'waafi_pay',
          reference: res!.reference,
        ),
      );
    }
  }

  Future<void> _pickWallet(BuildContext context) async {
    Navigator.of(context).pop(); // close picker
    if (!context.mounted) return;
    final ok = await showWalletPinDialog(context);
    if (!context.mounted) return;
    if (ok) {
      Navigator.of(context, rootNavigator: true).pop(
        const OrderPaymentResult(success: true, paymentMethod: 'wallet'),
      );
    }
  }
}

class _MethodTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  const _MethodTile({required this.icon, required this.color, required this.title, required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: context.isDark ? color.withValues(alpha: 0.08) : color.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: color.withValues(alpha: 0.25)),
        ),
        child: Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
            Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
          ])),
          Icon(Icons.arrow_forward_ios_rounded, size: 16, color: color.withValues(alpha: 0.7)),
        ]),
      ),
    );
  }
}
