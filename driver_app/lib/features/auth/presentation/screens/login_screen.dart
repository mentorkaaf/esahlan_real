import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/driver_colors.dart';
import '../providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> with SingleTickerProviderStateMixin {
  final _phoneCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _obscure = true;
  late AnimationController _animCtrl;
  late Animation<double> _fadeIn;
  late Animation<Offset> _slideUp;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 800));
    _fadeIn = CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut);
    _slideUp = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _animCtrl, curve: Curves.easeOutCubic));
    _animCtrl.forward();
  }

  @override
  void dispose() { _animCtrl.dispose(); _phoneCtrl.dispose(); _passCtrl.dispose(); super.dispose(); }

  void _login() {
    final phone = _phoneCtrl.text.trim();
    final pass  = _passCtrl.text.trim();
    if (phone.isEmpty || pass.isEmpty) return;
    ref.read(loginProvider.notifier).login(phone, pass);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(loginProvider);
    ref.listen(loginProvider, (_, next) {
      if (next.hasError) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(next.error.toString()), backgroundColor: DC.error, behavior: SnackBarBehavior.floating));
      }
    });

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF0A1628), Color(0xFF0F1E38), Color(0xFF162A4A)],
          ),
        ),
        child: SafeArea(
          child: FadeTransition(
            opacity: _fadeIn,
            child: SlideTransition(
              position: _slideUp,
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 28),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const SizedBox(height: 50),

                  // Logo
                  Center(child: Column(children: [
                    Container(
                      width: 80, height: 80,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)]),
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.4), blurRadius: 30, offset: const Offset(0, 10))],
                      ),
                      child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 42),
                    ),
                    const SizedBox(height: 16),
                    RichText(text: const TextSpan(children: [
                      TextSpan(text: 'eSahlan ', style: TextStyle(color: DC.orange, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
                      TextSpan(text: 'Delivery', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w300)),
                    ])),
                  ])),
                  const SizedBox(height: 50),

                  // Welcome text
                  const Text('Welcome Back!', style: TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
                  const SizedBox(height: 6),
                  const Text('Login to continue delivering', style: TextStyle(color: DC.textSec, fontSize: 15, height: 1.5)),
                  const SizedBox(height: 36),

                  // Phone field
                  _InputField(
                    controller: _phoneCtrl,
                    hint: '+252 61 234 5678',
                    prefix: const Text('🇸🇴 ', style: TextStyle(fontSize: 22)),
                    keyboardType: TextInputType.phone,
                    icon: Icons.phone_outlined,
                  ),
                  const SizedBox(height: 16),

                  // Password field
                  _InputField(
                    controller: _passCtrl,
                    hint: 'Enter your password',
                    obscure: _obscure,
                    icon: Icons.lock_outline_rounded,
                    suffix: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, color: DC.textMuted, size: 20),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  const SizedBox(height: 12),

                  Align(alignment: Alignment.centerRight,
                    child: TextButton(onPressed: () {}, child: const Text('Forgot Password?', style: TextStyle(color: DC.orange, fontSize: 13, fontWeight: FontWeight.w600)))),
                  const SizedBox(height: 24),

                  // Login button
                  _PrimaryButton(
                    label: 'Login',
                    loading: state.isLoading,
                    onPressed: _login,
                  ),
                  const SizedBox(height: 16),

                  // OTP login option
                  SizedBox(width: double.infinity, height: 52, child: OutlinedButton.icon(
                    onPressed: () {},
                    icon: const Icon(Icons.sms_outlined, size: 18),
                    label: const Text('Login with OTP'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: DC.textSec,
                      side: BorderSide(color: DC.border),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  )),
                  const SizedBox(height: 36),

                  // Register link
                  Center(child: GestureDetector(
                    onTap: () => context.go('/register'),
                    child: RichText(text: const TextSpan(style: TextStyle(fontSize: 14), children: [
                      TextSpan(text: "Don't have an account? ", style: TextStyle(color: DC.textSec)),
                      TextSpan(text: 'Register', style: TextStyle(color: DC.orange, fontWeight: FontWeight.w700)),
                    ])),
                  )),
                  const SizedBox(height: 40),
                ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ── Reusable premium input field ──────────────────────────────────────────────

class _InputField extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final IconData? icon;
  final Widget? prefix;
  final Widget? suffix;
  final bool obscure;
  final TextInputType? keyboardType;

  const _InputField({required this.controller, required this.hint, this.icon, this.prefix, this.suffix, this.obscure = false, this.keyboardType});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF162038),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: DC.border),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 8, offset: const Offset(0, 4))],
      ),
      child: TextField(
        controller: controller,
        obscureText: obscure,
        keyboardType: keyboardType,
        style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: DC.textMuted.withValues(alpha: 0.7)),
          prefixIcon: prefix != null
              ? Padding(padding: const EdgeInsets.only(left: 14, right: 4), child: prefix)
              : (icon != null ? Icon(icon, color: DC.textMuted, size: 20) : null),
          prefixIconConstraints: prefix != null ? const BoxConstraints(minWidth: 48) : null,
          suffixIcon: suffix,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        ),
      ),
    );
  }
}

// ── Primary action button ─────────────────────────────────────────────────────

class _PrimaryButton extends StatelessWidget {
  final String label;
  final bool loading;
  final VoidCallback onPressed;

  const _PrimaryButton({required this.label, this.loading = false, required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: loading ? null : onPressed,
      child: Container(
        width: double.infinity,
        height: 56,
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)]),
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.4), blurRadius: 20, offset: const Offset(0, 8))],
        ),
        child: Center(
          child: loading
              ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
              : Text(label, style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800, letterSpacing: 0.3)),
        ),
      ),
    );
  }
}
