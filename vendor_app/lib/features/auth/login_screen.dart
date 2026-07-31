import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/fcm_service.dart';
import '../../core/theme/vc.dart';
import '../shell/main_shell.dart';
import '../agent/agent_shell.dart';
import 'register_screen.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _loginCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  Future<void> _submit() async {
    final login = _loginCtrl.text.trim();
    final pass  = _passCtrl.text.trim();
    if (login.isEmpty || pass.isEmpty) {
      setState(() => _error = 'Fill in all fields');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final res = await AuthService.instance.login(login, pass);
      if (!mounted) return;
      if (res['success'] == true) {
        final user = res['data']['user'] as Map<String, dynamic>;
        final role = (user['role']?['slug'] ?? '') as String;
        const allowedRoles = ['vendor_owner', 'vendor_employee', 'rent_agent'];
        if (!allowedRoles.contains(role)) {
          await AuthService.instance.logout();
          setState(() { _error = 'This account does not have vendor or agent access'; _loading = false; });
          return;
        }
        VendorFcmService.forceRegisterToken().catchError((_) {});
        final isAgent = role == 'rent_agent';
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => isAgent ? const AgentShell() : const MainShell()),
          (_) => false,
        );
      } else {
        setState(() { _error = res['message'] ?? 'Login failed'; _loading = false; });
      }
    } catch (e) {
      setState(() { _error = 'Connection error. Try again.'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(28),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SizedBox(height: 48),
            // Logo
            Container(
              width: 64, height: 64,
              decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(18)),
              child: const Icon(Icons.storefront_rounded, color: VC.orange, size: 34),
            ),
            const SizedBox(height: 24),
            Text('Vendor Login', style: TextStyle(color: context.vcText, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
            const SizedBox(height: 6),
            Text('Manage your store, orders & earnings', style: TextStyle(color: context.vcTextSec, fontSize: 14)),
            const SizedBox(height: 40),

            if (_error != null) ...[
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(color: VC.redDim, borderRadius: BorderRadius.circular(12), border: Border.all(color: VC.red.withValues(alpha: 0.3))),
                child: Row(children: [
                  const Icon(Icons.error_outline_rounded, color: VC.red, size: 18),
                  const SizedBox(width: 10),
                  Expanded(child: Text(_error!, style: const TextStyle(color: VC.red, fontSize: 13))),
                ]),
              ),
              const SizedBox(height: 16),
            ],

            _label('Phone or Email'),
            _field(_loginCtrl, 'e.g. 252615000000', keyboardType: TextInputType.emailAddress),
            const SizedBox(height: 14),
            _label('Password'),
            TextField(
              controller: _passCtrl,
              obscureText: _obscure,
              style: TextStyle(color: context.vcText),
              decoration: _inputDec('••••••••', suffixIcon: IconButton(
                icon: Icon(_obscure ? Icons.visibility_rounded : Icons.visibility_off_rounded, color: context.vcTextMute, size: 20),
                onPressed: () => setState(() => _obscure = !_obscure),
              )),
            ),
            const SizedBox(height: 32),

            SizedBox(width: double.infinity, height: 54,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: VC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
                onPressed: _loading ? null : _submit,
                child: _loading
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : const Text('Sign In', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
              ),
            ),
            const SizedBox(height: 20),
            Center(
              child: GestureDetector(
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())),
                child: RichText(text: TextSpan(children: [
                  TextSpan(text: "Don't have an account? ", style: TextStyle(color: context.vcTextMute, fontSize: 13)),
                  const TextSpan(text: 'Register', style: TextStyle(color: VC.orange, fontSize: 13, fontWeight: FontWeight.w800)),
                ])),
              ),
            ),
            const SizedBox(height: 16),
            Center(child: Text('eSahlan Vendor Portal v1.0', style: TextStyle(color: context.vcTextMute, fontSize: 12))),
          ]),
        ),
      ),
    );
  }

  Widget _label(String t) => Padding(padding: const EdgeInsets.only(bottom: 6), child: Text(t, style: TextStyle(color: context.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)));

  Widget _field(TextEditingController c, String hint, {TextInputType? keyboardType}) => TextField(
    controller: c, keyboardType: keyboardType,
    style: TextStyle(color: context.vcText),
    decoration: _inputDec(hint),
  );

  InputDecoration _inputDec(String hint, {Widget? suffixIcon}) => InputDecoration(
    hintText: hint,
    hintStyle: TextStyle(color: context.vcTextMute),
    suffixIcon: suffixIcon,
    filled: true, fillColor: context.vcInputFill,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: context.vcBorder)),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: context.vcBorder)),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: VC.orange, width: 1.5)),
  );
}
