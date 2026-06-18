import 'package:flutter/material.dart';
import '../../core/theme/theme_x.dart';
import 'package:flutter/services.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';

/// Shows a PIN verification dialog. Returns true if PIN is correct, false if cancelled or wrong.
Future<bool> showWalletPinDialog(BuildContext context) async {
  return await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => const _WalletPinDialog(),
  ) ?? false;
}

class _WalletPinDialog extends StatefulWidget {
  const _WalletPinDialog();

  @override
  State<_WalletPinDialog> createState() => _WalletPinDialogState();
}

class _WalletPinDialogState extends State<_WalletPinDialog> {
  final List<TextEditingController> _ctrls = List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _focus = List.generate(4, (_) => FocusNode());
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    for (final c in _ctrls) c.dispose();
    for (final f in _focus) f.dispose();
    super.dispose();
  }

  String get _pin => _ctrls.map((c) => c.text).join();

  Future<void> _verify() async {
    if (_pin.length < 4) {
      setState(() => _error = 'Enter your 4-digit PIN');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final svc = ModuleApiService.create();
      final res = await svc.verifyWalletPin(_pin);
      if (res['success'] == true) {
        if (mounted) Navigator.of(context).pop(true);
      } else {
        setState(() { _loading = false; _error = res['message'] ?? 'Incorrect PIN'; });
        _clearPin();
      }
    } catch (e) {
      String msg = 'Incorrect PIN. Please try again.';
      if (e is Exception) {
        final str = e.toString();
        if (str.contains('message')) {
          final match = RegExp(r'"message":"([^"]+)"').firstMatch(str);
          if (match != null) msg = match.group(1)!;
        }
      }
      setState(() { _loading = false; _error = msg; });
      _clearPin();
    }
  }

  void _clearPin() {
    for (final c in _ctrls) c.clear();
    _focus[0].requestFocus();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      contentPadding: const EdgeInsets.fromLTRB(24, 24, 24, 20),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 64, height: 64,
          decoration: BoxDecoration(
            color: AppColors.primary.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.lock_rounded, color: AppColors.primary, size: 32),
        ),
        SizedBox(height: 16),
        Text('Enter Wallet PIN', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: context.colors.navyText)),
        const SizedBox(height: 6),
        const Text('Enter your 4-digit PIN to confirm payment', textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
        const SizedBox(height: 24),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(4, (i) => Container(
            width: 54, height: 54,
            margin: const EdgeInsets.symmetric(horizontal: 5),
            child: TextField(
              controller: _ctrls[i],
              focusNode: _focus[i],
              obscureText: true,
              textAlign: TextAlign.center,
              maxLength: 1,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
              decoration: InputDecoration(
                counterText: '',
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: _error != null ? Colors.red : Colors.grey.shade300, width: 1.5),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: _error != null ? Colors.red : AppColors.primary, width: 2),
                ),
              ),
              onChanged: (v) {
                if (v.isNotEmpty && i < 3) {
                  _focus[i + 1].requestFocus();
                } else if (v.isEmpty && i > 0) {
                  _focus[i - 1].requestFocus();
                }
                if (_pin.length == 4) _verify();
              },
            ),
          )),
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Icon(Icons.error_outline, color: Colors.red, size: 16),
            const SizedBox(width: 6),
            Flexible(child: Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13, fontWeight: FontWeight.w600))),
          ]),
        ],
        const SizedBox(height: 20),
        if (_loading)
          const CircularProgressIndicator(color: AppColors.primary)
        else
          Row(children: [
            Expanded(
              child: OutlinedButton(
                onPressed: () => Navigator.of(context).pop(false),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton(
                onPressed: _pin.length == 4 ? _verify : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Confirm', style: TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
          ]),
      ]),
    );
  }
}
