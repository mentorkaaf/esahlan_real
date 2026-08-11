import 'dart:async';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../shared/widgets/app_button.dart';
import '../providers/auth_provider.dart';
import '../../../../core/l10n/app_strings.dart';

class OtpScreen extends ConsumerStatefulWidget {
  final String phone;
  final String purpose;

  const OtpScreen({super.key, required this.phone, required this.purpose});

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  final List<TextEditingController> _ctrs = List.generate(6, (_) => TextEditingController());
  final List<FocusNode> _nodes = List.generate(6, (_) => FocusNode());
  int _secondsLeft = AppConstants.otpResendSeconds;
  Timer? _timer;
  String? _devCode;

  @override
  void initState() {
    super.initState();
    _sendOtp();
  }

  Future<void> _sendOtp() async {
    final result = await ref.read(otpProvider.notifier).sendOtp(widget.phone, widget.purpose);
    // In dev mode, API returns the code — store it for display
    if (result != null && result.isNotEmpty) {
      setState(() => _devCode = result);
    }
    _startTimer();
  }

  void _startTimer() {
    _timer?.cancel();
    setState(() => _secondsLeft = AppConstants.otpResendSeconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_secondsLeft <= 0) { t.cancel(); return; }
      setState(() => _secondsLeft--);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (var c in _ctrs) c.dispose();
    for (var n in _nodes) n.dispose();
    super.dispose();
  }

  String get _otp => _ctrs.map((c) => c.text).join();

  Future<void> _verify() async {
    if (_otp.length < 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppL10n.of(context).enterOtp)),
      );
      return;
    }
    await ref.read(otpProvider.notifier).verifyOtp(widget.phone, _otp, widget.purpose);
    if (!mounted) return;
    final state = ref.read(otpProvider);
    state.whenOrNull(
      data: (_) => context.go('/home'),
      error: (e, _) => AppErrorHandler.snack(context, e),
    );
  }

  @override
  Widget build(BuildContext context) {
    final otpState = ref.watch(otpProvider);
    final isLoading = otpState.isLoading;
    final l = AppL10n.of(context);

    return Scaffold(

      appBar: AppBar(
         elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.textDark, size: 20),
          onPressed: () => context.pop(),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 16),
              Text(l.verifyNumber,
                style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: AppColors.textDark)),
              const SizedBox(height: 8),
              RichText(
                text: TextSpan(
                  style: const TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.5),
                  children: [
                    TextSpan(text: '${l.codeSentTo}\n'),
                    TextSpan(
                      text: widget.phone,
                      style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.textDark),
                    ),
                  ],
                ),
              ),
              if (_devCode != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.developer_mode, color: AppColors.primary, size: 16),
                      const SizedBox(width: 8),
                      Text('Dev mode code: $_devCode',
                        style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600)),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 40),

              // OTP boxes
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: List.generate(6, (i) => _OtpBox(
                  controller: _ctrs[i],
                  focusNode: _nodes[i],
                  onChanged: (v) {
                    if (v.length == 1 && i < 5) {
                      _nodes[i + 1].requestFocus();
                    } else if (v.isEmpty && i > 0) {
                      _nodes[i - 1].requestFocus();
                    }
                    setState(() {});
                  },
                )),
              ),
              const SizedBox(height: 36),

              AppButton(
                label: isLoading ? l.verifying : l.verify,
                onPressed: isLoading ? null : _verify,
                isLoading: isLoading,
              ),
              const SizedBox(height: 24),

              Center(
                child: _secondsLeft > 0
                    ? Text('${l.didntReceive} ${_secondsLeft}s',
                        style: const TextStyle(color: AppColors.textGrey))
                    : GestureDetector(
                        onTap: _sendOtp,
                        child: Text(l.resend,
                          style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700)),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OtpBox extends StatelessWidget {
  final TextEditingController controller;
  final FocusNode focusNode;
  final void Function(String) onChanged;

  const _OtpBox({required this.controller, required this.focusNode, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 46, height: 56,
      child: TextFormField(
        controller: controller,
        focusNode: focusNode,
        textAlign: TextAlign.center,
        keyboardType: TextInputType.number,
        maxLength: 1,
        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        onChanged: onChanged,
        style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.textDark),
        decoration: InputDecoration(
          counterText: '',
          filled: true,
          fillColor: context.colors.cardBg,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.divider)),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.divider)),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
        ),
      ),
    );
  }
}
