import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../../core/constants/app_assets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/phone_input_field.dart';
import '../providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _phoneCtrl = TextEditingController();
  CountryCode _country = kDefaultCountry;
  final List<TextEditingController> _pinCtrls = List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _pinFocus = List.generate(4, (_) => FocusNode());
  late AnimationController _animCtrl;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _fadeAnim  = CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(begin: const Offset(0, 0.06), end: Offset.zero)
        .animate(CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut));
    _animCtrl.forward();
  }

  @override
  void dispose() {
    _animCtrl.dispose();
    _phoneCtrl.dispose();
    for (final c in _pinCtrls) c.dispose();
    for (final f in _pinFocus) f.dispose();
    super.dispose();
  }

  String get _pin       => _pinCtrls.map((c) => c.text).join();
  String get _fullPhone => '${_country.dialCode}${_phoneCtrl.text.trim()}';

  Future<void> _login() async {
    if (_phoneCtrl.text.trim().isEmpty) { _err('Enter your phone number'); return; }
    if (_pin.length < 4)               { _err('Enter your 4-digit PIN');   return; }

    await ref.read(loginProvider.notifier).login(_fullPhone, _pin);
    if (!mounted) return;
    ref.read(loginProvider).whenOrNull(
      error: (e, _) => _err(e.toString()),
    );
  }

  void _err(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: AppColors.error,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = ref.watch(loginProvider).isLoading;

    return Scaffold(
      backgroundColor: const Color(0xFF07003B),
      body: Stack(
        children: [
          Positioned(top: -80, right: -60, child: _Blob(size: 260, opacity: 0.12)),
          Positioned(bottom: -40, left: -40, child: _Blob(size: 200, opacity: 0.04, white: true)),
          SafeArea(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                  child: Center(
                    child: Image.asset(AppAssets.appLogo, height: 36, fit: BoxFit.contain),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 36, 24, 0),
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: SlideTransition(
                      position: _slideAnim,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Welcome back 👋', style: TextStyle(
                          fontSize: 28, fontWeight: FontWeight.w900,
                          color: Colors.white, letterSpacing: -0.5,
                        )),
                        const SizedBox(height: 8),
                        Text('Sign in to your eSahlan account',
                          style: TextStyle(fontSize: 15, color: Colors.white.withOpacity(0.55),
                              fontWeight: FontWeight.w500)),
                      ]),
                    ),
                  ),
                ),
                Expanded(
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: SlideTransition(
                      position: Tween<Offset>(begin: const Offset(0, 0.1), end: Offset.zero)
                          .animate(CurvedAnimation(parent: _animCtrl,
                              curve: const Interval(0.2, 1.0, curve: Curves.easeOut))),
                      child: Container(
                        margin: const EdgeInsets.only(top: 32),
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
                        ),
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(24, 32, 24, 32),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            const _Label('Phone Number'),
                            const SizedBox(height: 8),
                            PhoneInputField(
                              controller: _phoneCtrl,
                              onCountryChanged: (c) => setState(() => _country = c),
                            ),
                            const SizedBox(height: 28),
                            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                              const _Label('PIN Code'),
                              Text('4 digits', style: TextStyle(fontSize: 12, color: Colors.grey.shade400)),
                            ]),
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: List.generate(4, (i) => _PinBox(
                                controller: _pinCtrls[i],
                                focusNode: _pinFocus[i],
                                onChanged: (v) {
                                  if (v.isNotEmpty && i < 3) FocusScope.of(context).requestFocus(_pinFocus[i + 1]);
                                  else if (v.isEmpty && i > 0) FocusScope.of(context).requestFocus(_pinFocus[i - 1]);
                                  setState(() {});
                                },
                                onSubmit: i == 3 ? (_) => _login() : null,
                              )),
                            ),
                            const SizedBox(height: 36),
                            _ActionButton(
                              label: 'Sign In',
                              icon: Icons.arrow_forward_rounded,
                              isLoading: isLoading,
                              onTap: isLoading ? null : _login,
                            ),
                            const SizedBox(height: 28),
                            Center(child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Text("Don't have an account? ",
                                style: TextStyle(color: Colors.grey.shade500, fontSize: 14)),
                              GestureDetector(
                                onTap: () => context.go('/auth/register'),
                                child: Text('Sign Up', style: TextStyle(
                                  color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 14)),
                              ),
                            ])),
                          ]),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Shared Widgets (used by both auth screens) ───────────────────────────────


class _Blob extends StatelessWidget {
  final double size, opacity;
  final bool white;
  const _Blob({required this.size, required this.opacity, this.white = false});
  @override
  Widget build(BuildContext context) => Container(
    width: size, height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: (white ? Colors.white : AppColors.primary).withOpacity(opacity),
    ),
  );
}

class _Label extends StatelessWidget {
  final String text;
  const _Label(this.text);
  @override
  Widget build(BuildContext context) => Text(text,
    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF374151)));
}

class _PinBox extends StatelessWidget {
  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;
  final ValueChanged<String>? onSubmit;
  const _PinBox({required this.controller, required this.focusNode,
      required this.onChanged, this.onSubmit});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 68, height: 68,
      decoration: BoxDecoration(
        color: focusNode.hasFocus ? AppColors.primary.withOpacity(0.06) : const Color(0xFFF8F9FF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: focusNode.hasFocus ? AppColors.primary : const Color(0xFFE8EAF0),
          width: focusNode.hasFocus ? 2 : 1.5,
        ),
        boxShadow: focusNode.hasFocus
            ? [BoxShadow(color: AppColors.primary.withOpacity(0.15), blurRadius: 12, offset: const Offset(0, 4))]
            : null,
      ),
      child: TextField(
        controller: controller,
        focusNode: focusNode,
        keyboardType: TextInputType.number,
        textAlign: TextAlign.center,
        maxLength: 1,
        obscureText: true,
        obscuringCharacter: '●',
        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Color(0xFF07003B)),
        decoration: const InputDecoration(counterText: '', border: InputBorder.none),
        onChanged: onChanged,
        onSubmitted: onSubmit,
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback? onTap;
  final bool isLoading;
  const _ActionButton({required this.label, required this.icon, this.onTap, this.isLoading = false});
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity, height: 56,
      child: ElevatedButton(
        onPressed: onTap,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF07003B), foregroundColor: Colors.white,
          elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
        child: isLoading
            ? const SizedBox(width: 22, height: 22,
                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
            : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                Text(label, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                const SizedBox(width: 10),
                Container(
                  width: 28, height: 28,
                  decoration: BoxDecoration(color: Colors.white.withOpacity(0.15), borderRadius: BorderRadius.circular(8)),
                  child: Icon(icon, size: 16),
                ),
              ]),
      ),
    );
  }
}
