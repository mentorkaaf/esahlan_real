import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/theme_x.dart';

// ─── USSD Method Channel ──────────────────────────────────────────────────────

const _ussdChannel = MethodChannel('com.esahlan.app/ussd');

Future<_UssdResult> _dialUssdInApp(String code) async {
  if (Platform.isIOS) {
    // iOS: cannot do in-app USSD — open phone dialer
    final encoded = code.replaceAll('#', '%23');
    try {
      await launchUrl(Uri.parse('tel:$encoded'), mode: LaunchMode.externalApplication);
      return _UssdResult(launched: true, response: null);
    } catch (_) {
      return _UssdResult(launched: false, response: null);
    }
  }
  // Android API 26+ in-app USSD
  try {
    final res = await _ussdChannel.invokeMethod<Map>('dialUssd', {'code': code});
    final success = res?['success'] as bool? ?? false;
    final response = res?['response'] as String?;
    return _UssdResult(launched: true, inApp: true, success: success, response: response);
  } on PlatformException catch (e) {
    if (e.code == 'UNSUPPORTED' || e.code == 'PERMISSION_DENIED') {
      // Fallback: open dialer
      final encoded = code.replaceAll('#', '%23');
      try {
        await launchUrl(Uri.parse('tel:$encoded'), mode: LaunchMode.externalApplication);
        return _UssdResult(launched: true, response: null);
      } catch (_) {}
    }
    return _UssdResult(launched: false, response: e.message);
  }
}

class _UssdResult {
  final bool launched;
  final bool inApp;
  final bool success;
  final String? response;
  const _UssdResult({required this.launched, this.inApp = false, this.success = false, this.response});
}

// ─── Model ───────────────────────────────────────────────────────────────────

class MobilePayAccount {
  final int id;
  final String name;
  final String accountNumber;
  final String ussdTemplate;
  final String? instructions;
  final String? icon;

  const MobilePayAccount({
    required this.id,
    required this.name,
    required this.accountNumber,
    required this.ussdTemplate,
    this.instructions,
    this.icon,
  });

  factory MobilePayAccount.fromJson(Map<String, dynamic> j) => MobilePayAccount(
    id:            (j['id'] as num).toInt(),
    name:          j['name'] as String,
    accountNumber: j['account_number'] as String,
    ussdTemplate:  j['ussd_template'] as String,
    instructions:  j['instructions'] as String?,
    icon:          j['icon'] as String?,
  );

  String buildUssd(double amount) {
    final amtStr = amount == amount.truncateToDouble()
        ? amount.toInt().toString()
        : amount.toStringAsFixed(2);
    return ussdTemplate.replaceAll('{amount}', amtStr);
  }
}

// ─── Result ──────────────────────────────────────────────────────────────────

class MobilePayResult {
  final bool success;    // USSD dialed / launched
  final MobilePayAccount? account;
  final String? ussdResponse; // carrier response (Android only)
  const MobilePayResult({required this.success, this.account, this.ussdResponse});
}

// ─── Entry point ─────────────────────────────────────────────────────────────

Future<MobilePayResult?> showMobilePaySheet(
  BuildContext context, {
  required double amount,
  String? description,
}) {
  return showModalBottomSheet<MobilePayResult>(
    context: context,
    isScrollControlled: true,
    useRootNavigator: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _MobilePaySheet(amount: amount, description: description),
  );
}

// ─── Sheet widget ─────────────────────────────────────────────────────────────

const _kGreen = Color(0xFF2E7D32);
const _kGreenLight = Color(0xFF4CAF50);

class _MobilePaySheet extends StatefulWidget {
  final double amount;
  final String? description;
  const _MobilePaySheet({required this.amount, this.description});

  @override
  State<_MobilePaySheet> createState() => _MobilePaySheetState();
}

class _MobilePaySheetState extends State<_MobilePaySheet> {
  final _svc = ModuleApiService.create();
  List<MobilePayAccount> _accounts = [];
  bool _loading = true;
  bool _dialing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await _svc.getMobilePayAccounts();
      final list = res['data'] as List? ?? [];
      if (mounted) {
        setState(() {
          _accounts = list.map((e) => MobilePayAccount.fromJson(e as Map<String, dynamic>)).toList();
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() { _error = 'Could not load accounts'; _loading = false; });
    }
  }

  Future<void> _onSelect(MobilePayAccount acc) async {
    final ussd = acc.buildUssd(widget.amount);

    // Confirm dialog
    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(children: [
          Text(acc.icon ?? '📱', style: const TextStyle(fontSize: 22)),
          const SizedBox(width: 8),
          Text('${acc.name} Payment', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ]),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          RichText(text: TextSpan(style: const TextStyle(fontSize: 13, color: Colors.black87, height: 1.5), children: [
            const TextSpan(text: 'Amount: '),
            TextSpan(text: '\$${widget.amount.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, color: _kGreen)),
          ])),
          const SizedBox(height: 6),
          Text('USSD: $ussd', style: const TextStyle(fontFamily: 'monospace', fontSize: 12, color: Colors.black54)),
          if (acc.instructions != null && acc.instructions!.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(8)),
              child: Text(acc.instructions!, style: const TextStyle(fontSize: 12, color: _kGreen, height: 1.5)),
            ),
          ],
          if (Platform.isIOS) ...[
            const SizedBox(height: 10),
            const Text('Tapping "Dial Now" will open your Phone app to complete payment.', style: TextStyle(fontSize: 11, color: AppColors.textGrey)),
          ],
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel', style: TextStyle(color: AppColors.textGrey))),
          ElevatedButton.icon(
            onPressed: () => Navigator.pop(ctx, true),
            icon: const Icon(Icons.phone_in_talk_rounded, size: 16),
            label: const Text('Dial Now', style: TextStyle(fontWeight: FontWeight.w800)),
            style: ElevatedButton.styleFrom(backgroundColor: _kGreen, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _dialing = true);

    // In-app USSD (Android) or phone app (iOS)
    final result = await _dialUssdInApp(ussd);

    if (!mounted) return;
    setState(() => _dialing = false);

    if (!result.launched) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Could not dial. Please dial manually: $ussd'),
          backgroundColor: Colors.red,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 6),
          action: SnackBarAction(
            label: 'Copy',
            textColor: Colors.white,
            onPressed: () => Clipboard.setData(ClipboardData(text: ussd)),
          ),
        ),
      );
      return;
    }

    // Show carrier response if in-app (Android)
    if (result.inApp && result.response != null && result.response!.isNotEmpty) {
      await showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(children: [
            Icon(result.success ? Icons.check_circle_rounded : Icons.info_rounded,
                color: result.success ? _kGreenLight : Colors.orange),
            const SizedBox(width: 8),
            const Text('Payment Response', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ]),
          content: Text(result.response!, style: const TextStyle(fontSize: 14, height: 1.5)),
          actions: [
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx),
              style: ElevatedButton.styleFrom(backgroundColor: _kGreen, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
              child: const Text('OK', style: TextStyle(fontWeight: FontWeight.w800)),
            ),
          ],
        ),
      );
    }

    if (mounted) {
      Navigator.of(context).pop(MobilePayResult(
        success: result.launched,
        account: acc,
        ussdResponse: result.response,
      ));
    }
  }

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
          Container(
            width: 48, height: 48,
            decoration: BoxDecoration(gradient: const LinearGradient(colors: [_kGreen, _kGreenLight]), borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.phone_in_talk_rounded, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Mobile Pay', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: context.colors.navyText)),
            Text('\$${widget.amount.toStringAsFixed(2)}${widget.description != null ? ' · ${widget.description}' : ''}',
                style: const TextStyle(color: _kGreenLight, fontWeight: FontWeight.w700, fontSize: 13)),
          ])),
          IconButton(icon: const Icon(Icons.close_rounded, color: AppColors.textGrey), onPressed: () => Navigator.of(context).pop()),
        ]),

        const SizedBox(height: 20),

        if (_loading || _dialing)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 32),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const CircularProgressIndicator(color: _kGreenLight, strokeWidth: 2),
              if (_dialing) ...[
                const SizedBox(height: 12),
                const Text('Dialing...', style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
              ],
            ]),
          )
        else if (_error != null)
          Padding(padding: const EdgeInsets.symmetric(vertical: 24),
              child: Text(_error!, style: const TextStyle(color: AppColors.error), textAlign: TextAlign.center))
        else if (_accounts.isEmpty)
          const Padding(padding: EdgeInsets.symmetric(vertical: 24),
              child: Text('No mobile pay accounts configured.\nContact admin.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.textGrey, height: 1.5)))
        else ...[
          Align(alignment: Alignment.centerLeft,
              child: Text('Select Provider', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText))),
          const SizedBox(height: 12),
          ..._accounts.map((acc) => _AccountTile(account: acc, amount: widget.amount, onTap: () => _onSelect(acc))),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(10)),
            child: Row(children: const [
              Icon(Icons.lock_outline_rounded, size: 15, color: _kGreen),
              SizedBox(width: 8),
              Expanded(child: Text(
                'Payment goes directly through your mobile carrier. eSahlan never sees your PIN.',
                style: TextStyle(fontSize: 11, color: _kGreen, height: 1.4),
              )),
            ]),
          ),
        ],

        const SizedBox(height: 8),
      ]),
    );
  }
}

// ─── Account tile ─────────────────────────────────────────────────────────────

class _AccountTile extends StatelessWidget {
  final MobilePayAccount account;
  final double amount;
  final VoidCallback onTap;
  const _AccountTile({required this.account, required this.amount, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final ussd = account.buildUssd(amount);
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: BoxDecoration(
          color: context.isDark ? const Color(0xFF0D1F12) : const Color(0xFFF0FDF4),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _kGreenLight.withValues(alpha: 0.3)),
        ),
        child: Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: _kGreenLight.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
            child: Center(child: Text(account.icon ?? '📱', style: const TextStyle(fontSize: 22))),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(account.name, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
            const SizedBox(height: 2),
            Text(ussd, style: const TextStyle(fontFamily: 'monospace', fontSize: 11, color: AppColors.textGrey)),
          ])),
          const Icon(Icons.phone_forwarded_rounded, color: _kGreenLight, size: 20),
        ]),
      ),
    );
  }
}
