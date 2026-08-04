import 'dart:io' show Platform;
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/theme_x.dart';

// ─── USSD Method Channel ──────────────────────────────────────────────────────

const _ussdChannel = MethodChannel('com.esahlan.app/ussd');

Future<_UssdResult> _dialUssdInApp(String code) async {
  final encoded = code.replaceAll('#', '%23');
  final uri = Uri.parse('tel:$encoded');

  // Web and iOS: use tel: URL scheme — browser/OS opens the dialer
  if (kIsWeb || Platform.isIOS) {
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      return _UssdResult(launched: true, response: null);
    } catch (_) {
      return _UssdResult(launched: false, response: null);
    }
  }
  // Android: ACTION_CALL intent — carrier USSD dialog appears on top of app.
  try {
    final res = await _ussdChannel.invokeMethod<Map>('dialUssd', {'code': code});
    final success = res?['success'] as bool? ?? false;
    return _UssdResult(launched: success, success: success, response: null);
  } on PlatformException catch (e) {
    if (e.code == 'PERMISSION_DENIED') {
      return _UssdResult(launched: false, response: 'Call permission denied');
    }
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      return _UssdResult(launched: true, response: null);
    } catch (_) {}
    return _UssdResult(launched: false, response: e.message);
  }
}

class _UssdResult {
  final bool launched;
  final bool success;
  final String? response;
  const _UssdResult({required this.launched, this.success = false, this.response});
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
    // EVC USSD format: whole amounts → *712*MERCHANT*6#
    // Decimal amounts → *712*MERCHANT*6*49# (* replaces decimal point)
    // Cents are zero-padded to 2 digits so 6.05 → 6*05 not 6*5
    final whole = amount.truncate();
    final cents = ((amount - whole) * 100).round();
    final amtStr = cents == 0 ? '$whole' : '$whole*${cents.toString().padLeft(2, '0')}';
    return ussdTemplate.replaceAll('{amount}', amtStr);
  }
}

// ─── Result ──────────────────────────────────────────────────────────────────

class MobilePayResult {
  final bool success;
  final MobilePayAccount? account;
  final String? proofToken;   // uploaded proof reference
  const MobilePayResult({required this.success, this.account, this.proofToken});
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

  // Proof confirmation state (shown after USSD is dialed)
  MobilePayAccount? _dialedAccount;
  bool _showProof = false;

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
          action: SnackBarAction(label: 'Copy', textColor: Colors.white, onPressed: () => Clipboard.setData(ClipboardData(text: ussd))),
        ),
      );
      return;
    }

    // Carrier PIN dialog has appeared — wait a moment then show proof step
    await Future.delayed(const Duration(seconds: 2));
    if (!mounted) return;

    setState(() {
      _dialedAccount = acc;
      _showProof = true;
    });
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
      child: _showProof
          ? _ProofStep(
              account: _dialedAccount!,
              amount: widget.amount,
              svc: _svc,
              onSuccess: (token) {
                Navigator.of(context).pop(MobilePayResult(
                  success: true,
                  account: _dialedAccount,
                  proofToken: token,
                ));
              },
              onCancel: () => setState(() => _showProof = false),
            )
          : _AccountListStep(
              accounts: _accounts,
              loading: _loading,
              dialing: _dialing,
              error: _error,
              amount: widget.amount,
              description: widget.description,
              onSelect: _onSelect,
            ),
    );
  }
}

// ─── Step 1: Account list ─────────────────────────────────────────────────────

class _AccountListStep extends StatelessWidget {
  final List<MobilePayAccount> accounts;
  final bool loading, dialing;
  final String? error, description;
  final double amount;
  final void Function(MobilePayAccount) onSelect;

  const _AccountListStep({
    required this.accounts, required this.loading, required this.dialing,
    required this.error, required this.amount, required this.description,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return Column(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
      const SizedBox(height: 20),
      Row(children: [
        Container(
          width: 48, height: 48,
          decoration: BoxDecoration(gradient: const LinearGradient(colors: [_kGreen, _kGreenLight]), borderRadius: BorderRadius.circular(14)),
          child: const Icon(Icons.phone_in_talk_rounded, color: Colors.white, size: 24),
        ),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Mobile Pay', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: context.colors.navyText)),
          Text('\$${amount.toStringAsFixed(2)}${description != null ? ' · $description' : ''}',
              style: const TextStyle(color: _kGreenLight, fontWeight: FontWeight.w700, fontSize: 13)),
        ])),
        IconButton(icon: const Icon(Icons.close_rounded, color: AppColors.textGrey), onPressed: () => Navigator.of(context).pop()),
      ]),
      const SizedBox(height: 20),
      if (loading || dialing)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const CircularProgressIndicator(color: _kGreenLight, strokeWidth: 2),
            if (dialing) ...[const SizedBox(height: 12), const Text('Dialing...', style: TextStyle(color: AppColors.textGrey, fontSize: 13))],
          ]),
        )
      else if (error != null)
        Padding(padding: const EdgeInsets.symmetric(vertical: 24), child: Text(error!, style: const TextStyle(color: AppColors.error), textAlign: TextAlign.center))
      else if (accounts.isEmpty)
        const Padding(padding: EdgeInsets.symmetric(vertical: 24), child: Text('No mobile pay accounts configured.\nContact admin.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.textGrey, height: 1.5)))
      else ...[
        Align(alignment: Alignment.centerLeft, child: Text('Select Provider', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText))),
        const SizedBox(height: 12),
        ...accounts.map((acc) => _AccountTile(account: acc, amount: amount, onTap: () => onSelect(acc))),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(10)),
          child: Row(children: const [
            Icon(Icons.lock_outline_rounded, size: 15, color: _kGreen),
            SizedBox(width: 8),
            Expanded(child: Text('Payment goes directly through your mobile carrier. eSahlan never sees your PIN.',
                style: TextStyle(fontSize: 11, color: _kGreen, height: 1.4))),
          ]),
        ),
      ],
      const SizedBox(height: 8),
    ]);
  }
}

// ─── Step 2: Proof submission ─────────────────────────────────────────────────

class _ProofStep extends StatefulWidget {
  final MobilePayAccount account;
  final double amount;
  final ModuleApiService svc;
  final void Function(String proofToken) onSuccess;
  final VoidCallback onCancel;

  const _ProofStep({
    required this.account, required this.amount, required this.svc,
    required this.onSuccess, required this.onCancel,
  });

  @override
  State<_ProofStep> createState() => _ProofStepState();
}

class _ProofStepState extends State<_ProofStep> {
  final _phoneCtrl = TextEditingController();
  XFile? _screenshotFile;
  Uint8List? _screenshotBytes;
  bool _uploading = false;
  String? _error;

  Future<void> _pickScreenshot() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked != null && mounted) {
      final bytes = await picked.readAsBytes();
      setState(() { _screenshotFile = picked; _screenshotBytes = bytes; _error = null; });
    }
  }

  Future<void> _submit() async {
    final phone = _phoneCtrl.text.trim();
    if (phone.isEmpty) {
      setState(() => _error = 'Please enter the phone number you paid from');
      return;
    }
    if (_screenshotFile == null || _screenshotBytes == null) {
      setState(() => _error = 'Please upload a screenshot of the payment');
      return;
    }
    setState(() { _uploading = true; _error = null; });
    try {
      final token = await widget.svc.submitMobilePayProof(
        phone: phone,
        accountId: widget.account.id,
        amount: widget.amount,
        imageBytes: _screenshotBytes!,
      );
      widget.onSuccess(token);
    } catch (e) {
      if (mounted) setState(() { _uploading = false; _error = e.toString(); });
    }
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 20), decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),

      // Header
      Row(children: [
        Container(
          width: 44, height: 44,
          decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(12), border: Border.all(color: _kGreenLight)),
          child: const Icon(Icons.verified_rounded, color: _kGreen, size: 22),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Confirm Payment', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17, color: context.colors.navyText)),
          Text('Provide proof that you sent \$${widget.amount.toStringAsFixed(2)} via ${widget.account.name}',
              style: const TextStyle(fontSize: 12, color: AppColors.textGrey, height: 1.3)),
        ])),
      ]),
      const SizedBox(height: 20),

      // Phone field
      Text('Phone Number You Paid From', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: context.colors.navyText)),
      const SizedBox(height: 8),
      TextField(
        controller: _phoneCtrl,
        keyboardType: TextInputType.phone,
        decoration: InputDecoration(
          hintText: 'e.g. 0614333365',
          prefixIcon: const Icon(Icons.phone_rounded, color: _kGreen, size: 20),
          filled: true,
          fillColor: context.isDark ? const Color(0xFF0D1F12) : const Color(0xFFF0FDF4),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: _kGreenLight.withValues(alpha: 0.3))),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: _kGreen, width: 1.5)),
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        ),
      ),
      const SizedBox(height: 16),

      // Screenshot picker
      Text('Payment Screenshot', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: context.colors.navyText)),
      const SizedBox(height: 8),
      GestureDetector(
        onTap: _uploading ? null : _pickScreenshot,
        child: Container(
          width: double.infinity,
          constraints: const BoxConstraints(minHeight: 120),
          decoration: BoxDecoration(
            color: context.isDark ? const Color(0xFF0D1F12) : const Color(0xFFF0FDF4),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _kGreenLight.withValues(alpha: 0.4), width: 1.5),
          ),
          child: _screenshotFile != null
              ? Stack(children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(11),
                    child: Image.memory(_screenshotBytes!, width: double.infinity, height: 200, fit: BoxFit.cover),
                  ),
                  Positioned(top: 8, right: 8, child: GestureDetector(
                    onTap: () => setState(() => _screenshotFile = null),
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(20)),
                      child: const Icon(Icons.close, color: Colors.white, size: 16),
                    ),
                  )),
                ])
              : Padding(
                  padding: const EdgeInsets.symmetric(vertical: 28),
                  child: Column(mainAxisSize: MainAxisSize.min, children: const [
                    Icon(Icons.upload_rounded, color: _kGreenLight, size: 32),
                    SizedBox(height: 8),
                    Text('Tap to upload screenshot', style: TextStyle(color: _kGreen, fontWeight: FontWeight.w700, fontSize: 13)),
                    SizedBox(height: 4),
                    Text('Select from your gallery', style: TextStyle(color: AppColors.textGrey, fontSize: 11)),
                  ]),
                ),
        ),
      ),

      if (_error != null) ...[
        const SizedBox(height: 10),
        Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 12)),
      ],

      const SizedBox(height: 20),

      // Actions
      Row(children: [
        Expanded(child: OutlinedButton(
          onPressed: _uploading ? null : widget.onCancel,
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: _kGreenLight.withValues(alpha: 0.5)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
          child: const Text('Back', style: TextStyle(color: AppColors.textGrey, fontWeight: FontWeight.w700)),
        )),
        const SizedBox(width: 12),
        Expanded(flex: 2, child: ElevatedButton.icon(
          onPressed: _uploading ? null : _submit,
          icon: _uploading
              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Icon(Icons.check_circle_rounded, size: 18),
          label: Text(_uploading ? 'Uploading...' : 'Confirm & Order', style: const TextStyle(fontWeight: FontWeight.w800)),
          style: ElevatedButton.styleFrom(
            backgroundColor: _kGreen,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
        )),
      ]),
      const SizedBox(height: 8),
    ]);
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
