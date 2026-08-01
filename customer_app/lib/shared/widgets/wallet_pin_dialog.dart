import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/theme_x.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/storage/local_storage.dart';
import '../../features/auth/data/models/user_model.dart';
import '../../features/auth/presentation/providers/auth_provider.dart';

/// Shows PIN verify dialog (or setup dialog if user has no PIN yet).
/// Returns true if PIN verified/set successfully, false if cancelled.
Future<bool> showWalletPinDialog(BuildContext context) async {
  // Read directly from local storage — always up-to-date regardless of
  // provider async state (avoids AsyncLoading→valueOrNull==null false negative).
  final userJson = await LocalStorage.getString('user_data');
  final user = userJson != null ? UserModel.fromJsonString(userJson) : null;
  final hasPin = user?.hasWalletPin ?? false;

  if (!hasPin) {
    // Show setup flow first
    final created = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => const _WalletPinSetupDialog(),
    ) ?? false;

    if (!created) return false;

    // Refresh provider so other widgets (profile, etc.) see the updated state
    if (context.mounted) {
      ProviderScope.containerOf(context, listen: false).invalidate(authStateProvider);
    }
    return true;
  }

  return await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => const _WalletPinVerifyDialog(),
  ) ?? false;
}

// ─── Setup Dialog ──────────────────────────────────────────────────────────────

class _WalletPinSetupDialog extends StatefulWidget {
  const _WalletPinSetupDialog();

  @override
  State<_WalletPinSetupDialog> createState() => _WalletPinSetupDialogState();
}

class _WalletPinSetupDialogState extends State<_WalletPinSetupDialog> {
  // Step: 'create' or 'confirm'
  String _step = 'create';
  String _firstPin = '';

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

  void _clearPin() {
    for (final c in _ctrls) c.clear();
    _focus[0].requestFocus();
  }

  void _next() {
    if (_pin.length < 4) return;
    if (_step == 'create') {
      _firstPin = _pin;
      setState(() { _step = 'confirm'; _error = null; });
      _clearPin();
    } else {
      _confirmAndSave();
    }
  }

  Future<void> _confirmAndSave() async {
    if (_pin != _firstPin) {
      setState(() { _error = 'PINs do not match. Please try again.'; });
      _clearPin();
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final svc = ModuleApiService.create();
      final res = await svc.setWalletPin(_pin);
      if (res['success'] == true) {
        // Update local storage so authStateProvider reads hasWalletPin=true immediately
        final userJson = await LocalStorage.getString('user_data');
        if (userJson != null) {
          final user = UserModel.fromJsonString(userJson);
          await LocalStorage.saveString('user_data', user.copyWith(hasWalletPin: true).toJsonString());
        }
        if (mounted) Navigator.of(context).pop(true);
      } else {
        setState(() { _loading = false; _error = res['message'] ?? 'Failed to set PIN'; });
        _clearPin();
      }
    } catch (e) {
      setState(() { _loading = false; _error = 'Failed to set PIN. Please try again.'; });
      _clearPin();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isConfirm = _step == 'confirm';
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      contentPadding: const EdgeInsets.fromLTRB(24, 28, 24, 20),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 64, height: 64,
          decoration: BoxDecoration(
            color: AppColors.primary.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.shield_rounded, color: AppColors.primary, size: 32),
        ),
        const SizedBox(height: 16),
        Text(
          isConfirm ? 'Confirm Your PIN' : 'Create Wallet PIN',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: context.colors.navyText),
        ),
        const SizedBox(height: 8),
        Text(
          isConfirm
              ? 'Re-enter your 4-digit PIN to confirm'
              : 'Create a 4-digit PIN to secure your ePay wallet',
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 13, color: AppColors.textGrey),
        ),
        const SizedBox(height: 24),
        _PinRow(ctrls: _ctrls, focus: _focus, error: _error, onFilled: _next),
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
                onPressed: () {
                  if (isConfirm) {
                    setState(() { _step = 'create'; _error = null; });
                    _clearPin();
                  } else {
                    Navigator.of(context).pop(false);
                  }
                },
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(isConfirm ? 'Back' : 'Cancel', style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton(
                onPressed: _pin.length == 4 ? _next : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(isConfirm ? 'Confirm' : 'Next', style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
          ]),
      ]),
    );
  }
}

// ─── Verify Dialog ─────────────────────────────────────────────────────────────

class _WalletPinVerifyDialog extends StatefulWidget {
  const _WalletPinVerifyDialog();

  @override
  State<_WalletPinVerifyDialog> createState() => _WalletPinVerifyDialogState();
}

class _WalletPinVerifyDialogState extends State<_WalletPinVerifyDialog> {
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
    if (_pin.length < 4) return;
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
      final match = RegExp(r'"message":"([^"]+)"').firstMatch(e.toString());
      if (match != null) msg = match.group(1)!;
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
        const SizedBox(height: 16),
        Text('Enter ePay PIN', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: context.colors.navyText)),
        const SizedBox(height: 6),
        const Text('Enter your 4-digit PIN to confirm payment', textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
        const SizedBox(height: 24),
        _PinRow(ctrls: _ctrls, focus: _focus, error: _error, onFilled: _verify),
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

// ─── Shared PIN Row Widget ─────────────────────────────────────────────────────

class _PinRow extends StatelessWidget {
  final List<TextEditingController> ctrls;
  final List<FocusNode> focus;
  final String? error;
  final VoidCallback onFilled;

  const _PinRow({required this.ctrls, required this.focus, required this.error, required this.onFilled});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(4, (i) => Container(
        width: 54, height: 54,
        margin: const EdgeInsets.symmetric(horizontal: 5),
        child: TextField(
          controller: ctrls[i],
          focusNode: focus[i],
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
              borderSide: BorderSide(color: error != null ? Colors.red : Colors.grey.shade300, width: 1.5),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: error != null ? Colors.red : AppColors.primary, width: 2),
            ),
          ),
          onChanged: (v) {
            if (v.isNotEmpty && i < 3) {
              focus[i + 1].requestFocus();
            } else if (v.isEmpty && i > 0) {
              focus[i - 1].requestFocus();
            }
            final pin = ctrls.map((c) => c.text).join();
            if (pin.length == 4) onFilled();
          },
        ),
      )),
    );
  }
}
