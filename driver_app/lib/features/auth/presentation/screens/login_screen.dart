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

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _phoneCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() { _phoneCtrl.dispose(); _passCtrl.dispose(); super.dispose(); }

  void _login() {
    final phone = _phoneCtrl.text.trim();
    final pass  = _passCtrl.text.trim();
    if (phone.isEmpty || pass.isEmpty) return;
    ref.read(loginProvider.notifier).login(phone, pass);
  }

  @override
  Widget build(BuildContext context) {
    final loginState = ref.watch(loginProvider);

    ref.listen(loginProvider, (_, next) {
      if (next.hasError) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(next.error.toString()),
          backgroundColor: DC.error,
        ));
      }
    });

    return Scaffold(
      backgroundColor: DC.navy,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SizedBox(height: 60),
            // Logo
            Row(children: [
              Container(
                width: 56, height: 56,
                decoration: BoxDecoration(color: DC.orange, borderRadius: BorderRadius.circular(16)),
                child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 30),
              ),
              const SizedBox(width: 14),
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('eSahlan', style: TextStyle(color: DC.orange, fontSize: 24, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
                const Text('Delivery', style: TextStyle(color: DC.textSec, fontSize: 14, fontWeight: FontWeight.w500)),
              ]),
            ]),
            const SizedBox(height: 48),
            const Text('Welcome Back!', style: TextStyle(color: DC.text, fontSize: 28, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            const Text('Login to continue delivering', style: TextStyle(color: DC.textSec, fontSize: 14)),
            const SizedBox(height: 36),

            // Phone
            TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              style: const TextStyle(color: DC.text),
              decoration: InputDecoration(
                hintText: '+252 61 234 5678',
                prefixIcon: Container(
                  width: 48, alignment: Alignment.center,
                  child: const Text('🇸🇴', style: TextStyle(fontSize: 20)),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Password
            TextField(
              controller: _passCtrl,
              obscureText: _obscure,
              style: const TextStyle(color: DC.text),
              decoration: InputDecoration(
                hintText: 'Enter your password',
                prefixIcon: const Icon(Icons.lock_outline_rounded, color: DC.textMuted),
                suffixIcon: IconButton(
                  icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, color: DC.textMuted),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
            ),
            const SizedBox(height: 32),

            // Login button
            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton(
                onPressed: loginState.isLoading ? null : _login,
                child: loginState.isLoading
                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                    : const Text('Login', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
              ),
            ),
            const SizedBox(height: 24),

            // Register link
            Center(child: GestureDetector(
              onTap: () => context.go('/register'),
              child: RichText(text: TextSpan(
                style: const TextStyle(color: DC.textSec, fontSize: 14),
                children: [
                  const TextSpan(text: "Don't have an account? "),
                  TextSpan(text: 'Register', style: TextStyle(color: DC.orange, fontWeight: FontWeight.w700)),
                ],
              )),
            )),
          ]),
        ),
      ),
    );
  }
}
