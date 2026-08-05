import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';

class GlobalAuthScreen extends ConsumerStatefulWidget {
  final bool isLogin;
  const GlobalAuthScreen({super.key, this.isLogin = true});

  @override
  ConsumerState<GlobalAuthScreen> createState() => _GlobalAuthScreenState();
}

class _GlobalAuthScreenState extends ConsumerState<GlobalAuthScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;
  final _loginKey = GlobalKey<FormState>();
  final _registerKey = GlobalKey<FormState>();

  // Login fields
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();

  // Register fields
  final _nameCtrl = TextEditingController();
  final _regEmailCtrl = TextEditingController();
  final _regPassCtrl = TextEditingController();
  final _regPassConfirmCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addr1Ctrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  final _zipCtrl = TextEditingController();
  String _country = 'US';
  bool _obscurePass = true;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this, initialIndex: widget.isLogin ? 0 : 1);
  }

  @override
  void dispose() {
    _tab.dispose();
    _emailCtrl.dispose();
    _passCtrl.dispose();
    _nameCtrl.dispose();
    _regEmailCtrl.dispose();
    _regPassCtrl.dispose();
    _regPassConfirmCtrl.dispose();
    _phoneCtrl.dispose();
    _addr1Ctrl.dispose();
    _cityCtrl.dispose();
    _zipCtrl.dispose();
    super.dispose();
  }

  Future<void> _doLogin() async {
    if (!_loginKey.currentState!.validate()) return;
    setState(() => _loading = true);
    final ok = await ref
        .read(globalAuthProvider.notifier)
        .login(_emailCtrl.text.trim(), _passCtrl.text);
    setState(() => _loading = false);
    if (ok && mounted) {
      context.go('/global');
    } else if (mounted) {
      final err = ref.read(globalAuthProvider).error;
      _showError(err?.toString() ?? 'Login failed.');
    }
  }

  Future<void> _doRegister() async {
    if (!_registerKey.currentState!.validate()) return;
    if (_regPassCtrl.text != _regPassConfirmCtrl.text) {
      _showError('Passwords do not match.');
      return;
    }
    setState(() => _loading = true);
    final ok = await ref.read(globalAuthProvider.notifier).register({
      'name': _nameCtrl.text.trim(),
      'email': _regEmailCtrl.text.trim(),
      'password': _regPassCtrl.text,
      'password_confirmation': _regPassConfirmCtrl.text,
      'phone': _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
      'address_line1': _addr1Ctrl.text.trim(),
      'city': _cityCtrl.text.trim(),
      'zip': _zipCtrl.text.trim(),
      'country': _country,
    });
    setState(() => _loading = false);
    if (ok && mounted) {
      context.go('/global');
    } else if (mounted) {
      final err = ref.read(globalAuthProvider).error;
      _showError(err?.toString() ?? 'Registration failed.');
    }
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: Colors.red.shade700),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      body: SafeArea(
        child: Column(children: [
          // Header
          Container(
            color: const Color(0xFF1A1A2E),
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
            child: Column(children: [
              Row(children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.white70),
                  onPressed: () => context.pop(),
                ),
                const Spacer(),
                Image.asset('assets/images/logo.png',
                    height: 32,
                    errorBuilder: (_, __, ___) => const Text(
                          'eSahlan Global',
                          style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w800),
                        )),
                const Spacer(),
                const SizedBox(width: 40),
              ]),
              const SizedBox(height: 16),
              TabBar(
                controller: _tab,
                tabs: const [Tab(text: 'Sign In'), Tab(text: 'Create Account')],
                indicatorColor: const Color(0xFFF59E0B),
                labelColor: Colors.white,
                unselectedLabelColor: Colors.white54,
                labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
              ),
            ]),
          ),

          Expanded(
            child: TabBarView(
              controller: _tab,
              children: [_loginForm(), _registerForm()],
            ),
          ),
        ]),
      ),
    );
  }

  Widget _loginForm() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Form(
        key: _loginKey,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SizedBox(height: 8),
          const Text('Welcome back',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text('Sign in to your eSahlan Global account',
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
          const SizedBox(height: 28),
          _field(_emailCtrl, 'Email address', Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
              validator: (v) => v!.isEmpty ? 'Required' : null),
          const SizedBox(height: 14),
          _field(_passCtrl, 'Password', Icons.lock_outline,
              obscure: _obscurePass,
              suffixIcon: IconButton(
                icon: Icon(
                    _obscurePass ? Icons.visibility_off : Icons.visibility,
                    size: 20),
                onPressed: () => setState(() => _obscurePass = !_obscurePass),
              ),
              validator: (v) => v!.isEmpty ? 'Required' : null),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _loading ? null : _doLogin,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF1A1A2E),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2))
                  : const Text('Sign In',
                      style: TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 15, color: Colors.white)),
            ),
          ),
          const SizedBox(height: 16),
          Center(
            child: GestureDetector(
              onTap: () => _tab.animateTo(1),
              child: RichText(
                text: const TextSpan(
                  text: "Don't have an account? ",
                  style: TextStyle(color: Colors.grey, fontSize: 13),
                  children: [
                    TextSpan(
                        text: 'Create one',
                        style: TextStyle(
                            color: Color(0xFF1A1A2E),
                            fontWeight: FontWeight.w700))
                  ],
                ),
              ),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _registerForm() {
    final countries = const [
      ('US', '🇺🇸 United States'),
      ('GB', '🇬🇧 United Kingdom'),
      ('DE', '🇩🇪 Germany'),
      ('FR', '🇫🇷 France'),
      ('CA', '🇨🇦 Canada'),
      ('AU', '🇦🇺 Australia'),
      ('NL', '🇳🇱 Netherlands'),
      ('NO', '🇳🇴 Norway'),
      ('SE', '🇸🇪 Sweden'),
      ('DK', '🇩🇰 Denmark'),
      ('AE', '🇦🇪 UAE'),
      ('SA', '🇸🇦 Saudi Arabia'),
    ];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Form(
        key: _registerKey,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SizedBox(height: 8),
          const Text('Create Account',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text('Shop globally from Somalia with ease',
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
          const SizedBox(height: 24),

          _sectionLabel('Personal Info'),
          _field(_nameCtrl, 'Full name', Icons.person_outline,
              validator: (v) => v!.isEmpty ? 'Required' : null),
          const SizedBox(height: 12),
          _field(_regEmailCtrl, 'Email address', Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
              validator: (v) =>
                  v!.isEmpty ? 'Required' : (!v.contains('@') ? 'Invalid email' : null)),
          const SizedBox(height: 12),
          _field(_phoneCtrl, 'Phone (optional)', Icons.phone_outlined,
              keyboardType: TextInputType.phone),
          const SizedBox(height: 12),
          _field(_regPassCtrl, 'Password', Icons.lock_outline,
              obscure: true,
              validator: (v) =>
                  v!.length < 8 ? 'Min 8 characters' : null),
          const SizedBox(height: 12),
          _field(_regPassConfirmCtrl, 'Confirm password', Icons.lock_outline,
              obscure: true,
              validator: (v) => v!.isEmpty ? 'Required' : null),
          const SizedBox(height: 20),

          _sectionLabel('Shipping Address'),
          _field(_addr1Ctrl, 'Street address', Icons.home_outlined,
              validator: (v) => v!.isEmpty ? 'Required' : null),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(
              child: _field(_cityCtrl, 'City', Icons.location_city_outlined,
                  validator: (v) => v!.isEmpty ? 'Required' : null),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _field(_zipCtrl, 'ZIP / Postal', Icons.markunread_mailbox_outlined,
                  validator: (v) => v!.isEmpty ? 'Required' : null),
            ),
          ]),
          const SizedBox(height: 12),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.grey.shade200),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: _country,
                isExpanded: true,
                items: countries
                    .map((c) => DropdownMenuItem(
                          value: c.$1,
                          child: Text(c.$2,
                              style: const TextStyle(fontSize: 14)),
                        ))
                    .toList(),
                onChanged: (v) => setState(() => _country = v!),
              ),
            ),
          ),
          const SizedBox(height: 24),

          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _loading ? null : _doRegister,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF59E0B),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2))
                  : const Text('Create Account',
                      style: TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 15,
                          color: Color(0xFF1A1A2E))),
            ),
          ),
          const SizedBox(height: 16),
          Center(
            child: Text(
              'By creating an account you agree to our Terms of Service.',
              style: TextStyle(color: Colors.grey.shade500, fontSize: 11),
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(height: 24),
        ]),
      ),
    );
  }

  Widget _sectionLabel(String label) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Text(label.toUpperCase(),
            style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                color: Colors.grey.shade500,
                letterSpacing: 1.2)),
      );

  Widget _field(
    TextEditingController ctrl,
    String hint,
    IconData icon, {
    TextInputType? keyboardType,
    bool obscure = false,
    Widget? suffixIcon,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: ctrl,
      obscureText: obscure,
      keyboardType: keyboardType,
      validator: validator,
      style: const TextStyle(fontSize: 14),
      decoration: InputDecoration(
        hintText: hint,
        prefixIcon: Icon(icon, size: 18, color: Colors.grey.shade500),
        suffixIcon: suffixIcon,
        filled: true,
        fillColor: Colors.white,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: Colors.grey.shade200)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: Colors.grey.shade200)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFF1A1A2E), width: 2)),
        errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Colors.red)),
      ),
    );
  }
}
