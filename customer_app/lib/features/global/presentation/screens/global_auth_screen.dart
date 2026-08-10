import 'dart:math' show pi;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';

// Full country list (code, flag+name)
const _kRegCountries = [
  ('US', '🇺🇸 United States'),
  ('GB', '🇬🇧 United Kingdom'),
  ('CA', '🇨🇦 Canada'),
  ('AU', '🇦🇺 Australia'),
  ('DE', '🇩🇪 Germany'),
  ('FR', '🇫🇷 France'),
  ('NL', '🇳🇱 Netherlands'),
  ('SE', '🇸🇪 Sweden'),
  ('NO', '🇳🇴 Norway'),
  ('DK', '🇩🇰 Denmark'),
  ('FI', '🇫🇮 Finland'),
  ('CH', '🇨🇭 Switzerland'),
  ('AT', '🇦🇹 Austria'),
  ('BE', '🇧🇪 Belgium'),
  ('IT', '🇮🇹 Italy'),
  ('ES', '🇪🇸 Spain'),
  ('PT', '🇵🇹 Portugal'),
  ('IE', '🇮🇪 Ireland'),
  ('NZ', '🇳🇿 New Zealand'),
  ('SG', '🇸🇬 Singapore'),
  ('AE', '🇦🇪 UAE'),
  ('SA', '🇸🇦 Saudi Arabia'),
  ('QA', '🇶🇦 Qatar'),
  ('KW', '🇰🇼 Kuwait'),
  ('BH', '🇧🇭 Bahrain'),
  ('OM', '🇴🇲 Oman'),
  ('ET', '🇪🇹 Ethiopia'),
  ('KE', '🇰🇪 Kenya'),
  ('NG', '🇳🇬 Nigeria'),
  ('ZA', '🇿🇦 South Africa'),
  ('EG', '🇪🇬 Egypt'),
  ('SO', '🇸🇴 Somalia'),
  ('DJ', '🇩🇯 Djibouti'),
  ('TR', '🇹🇷 Turkey'),
  ('IN', '🇮🇳 India'),
  ('PK', '🇵🇰 Pakistan'),
  ('JP', '🇯🇵 Japan'),
  ('CN', '🇨🇳 China'),
  ('KR', '🇰🇷 South Korea'),
  ('MY', '🇲🇾 Malaysia'),
  ('ID', '🇮🇩 Indonesia'),
  ('PH', '🇵🇭 Philippines'),
  ('TH', '🇹🇭 Thailand'),
  ('BR', '🇧🇷 Brazil'),
  ('MX', '🇲🇽 Mexico'),
];

class GlobalAuthScreen extends ConsumerStatefulWidget {
  final bool isLogin;
  const GlobalAuthScreen({super.key, this.isLogin = true});

  @override
  ConsumerState<GlobalAuthScreen> createState() => _GlobalAuthScreenState();
}

class _GlobalAuthScreenState extends ConsumerState<GlobalAuthScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;
  final _loginKey    = GlobalKey<FormState>();
  final _registerKey = GlobalKey<FormState>();

  // Login
  final _emailCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _obscurePass = true;

  // Register — personal
  final _nameCtrl           = TextEditingController();
  final _regEmailCtrl       = TextEditingController();
  final _regPassCtrl        = TextEditingController();
  final _regPassConfirmCtrl = TextEditingController();
  final _regPhoneCtrl       = TextEditingController();

  // Register — shipping address (matches checkout form exactly)
  final _addr1Ctrl = TextEditingController();
  final _addr2Ctrl = TextEditingController(); // Apt/Suite — optional
  final _cityCtrl  = TextEditingController();
  final _stateCtrl = TextEditingController(); // State/Province — optional
  final _zipCtrl   = TextEditingController(); // optional
  String _country  = 'US';

  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _tab = TabController(
        length: 2, vsync: this, initialIndex: widget.isLogin ? 0 : 1);
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
    _regPhoneCtrl.dispose();
    _addr1Ctrl.dispose();
    _addr2Ctrl.dispose();
    _cityCtrl.dispose();
    _stateCtrl.dispose();
    _zipCtrl.dispose();
    super.dispose();
  }

  Future<void> _doGoogleLogin() async {
    setState(() => _loading = true);
    final ok = await ref.read(globalAuthProvider.notifier).googleLogin();
    setState(() => _loading = false);
    if (ok && mounted) {
      context.go('/global');
    } else if (mounted) {
      final err = ref.read(globalAuthProvider).error;
      final msg = err?.toString() ?? '';
      if (!msg.contains('cancelled')) {
        _showError(msg.isEmpty ? 'Google sign-in failed.' : msg);
      }
    }
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
      'name':                  _nameCtrl.text.trim(),
      'email':                 _regEmailCtrl.text.trim(),
      'password':              _regPassCtrl.text,
      'password_confirmation': _regPassConfirmCtrl.text,
      'phone':  _regPhoneCtrl.text.trim().isEmpty ? null : _regPhoneCtrl.text.trim(),
      'address_line1': _addr1Ctrl.text.trim(),
      'address_line2': _addr2Ctrl.text.trim().isEmpty ? null : _addr2Ctrl.text.trim(),
      'city':    _cityCtrl.text.trim(),
      'state':   _stateCtrl.text.trim().isEmpty ? null : _stateCtrl.text.trim(),
      'zip':     _zipCtrl.text.trim().isEmpty ? null : _zipCtrl.text.trim(),
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
                labelStyle:
                    const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
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

  // ── Login ──────────────────────────────────────────────────────────────────

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
                onPressed: () =>
                    setState(() => _obscurePass = !_obscurePass),
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
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2))
                  : const Text('Sign In',
                      style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                          color: Colors.white)),
            ),
          ),
          const SizedBox(height: 16),

          // ── OR divider ──────────────────────────────────────────────────
          Row(children: [
            Expanded(child: Divider(color: Colors.grey.shade300)),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text('OR', style: TextStyle(color: Colors.grey.shade500, fontSize: 12, fontWeight: FontWeight.w600)),
            ),
            Expanded(child: Divider(color: Colors.grey.shade300)),
          ]),

          const SizedBox(height: 14),

          // ── Google Sign-In ───────────────────────────────────────────────
          SizedBox(
            width: double.infinity,
            height: 50,
            child: OutlinedButton(
              onPressed: _loading ? null : _doGoogleLogin,
              style: OutlinedButton.styleFrom(
                backgroundColor: Colors.white,
                side: BorderSide(color: Colors.grey.shade300, width: 1.5),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _GoogleLogo(size: 22),
                  const SizedBox(width: 10),
                  const Text(
                    'Continue with Google',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF1A1A2E),
                    ),
                  ),
                ],
              ),
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

  // ── Register ───────────────────────────────────────────────────────────────

  Widget _registerForm() {
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

          // ── Personal Info ─────────────────────────────────────────────────
          _sectionLabel('PERSONAL INFO'),

          _field(_nameCtrl, 'Full name', Icons.person_outline,
              textCapitalization: TextCapitalization.words,
              validator: (v) => v!.trim().isEmpty ? 'Required' : null),
          const SizedBox(height: 12),
          _field(_regEmailCtrl, 'Email address', Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
              validator: (v) => v!.isEmpty
                  ? 'Required'
                  : (!v.contains('@') ? 'Invalid email' : null)),
          const SizedBox(height: 12),
          _field(_regPhoneCtrl, 'Phone (optional)', Icons.phone_outlined,
              keyboardType: TextInputType.phone),
          const SizedBox(height: 12),
          _field(_regPassCtrl, 'Password', Icons.lock_outline,
              obscure: true,
              validator: (v) => v!.length < 8 ? 'Min 8 characters' : null),
          const SizedBox(height: 12),
          _field(_regPassConfirmCtrl, 'Confirm password', Icons.lock_outline,
              obscure: true,
              validator: (v) => v!.isEmpty ? 'Required' : null),
          const SizedBox(height: 24),

          // ── Shipping Address ──────────────────────────────────────────────
          _sectionLabel('SHIPPING ADDRESS'),

          // Street address
          _field(_addr1Ctrl, 'Street Address *', Icons.home_outlined,
              textCapitalization: TextCapitalization.words,
              validator: (v) => v!.trim().isEmpty ? 'Required' : null),
          const SizedBox(height: 12),

          // Apt/Suite (optional)
          _field(_addr2Ctrl, 'Apt / Suite / Floor', Icons.business_outlined,
              hint: 'Optional'),
          const SizedBox(height: 12),

          // City + State row
          Row(children: [
            Expanded(
              flex: 3,
              child: _field(_cityCtrl, 'City *', Icons.location_city_outlined,
                  textCapitalization: TextCapitalization.words,
                  validator: (v) => v!.trim().isEmpty ? 'Required' : null),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 2,
              child: _field(_stateCtrl, 'State / Province',
                  Icons.map_outlined,
                  hint: 'Optional',
                  textCapitalization: TextCapitalization.words),
            ),
          ]),
          const SizedBox(height: 12),

          // ZIP + Country row
          Row(children: [
            SizedBox(
              width: 110,
              child: _field(_zipCtrl, 'ZIP / Postal',
                  Icons.markunread_mailbox_outlined,
                  hint: 'Optional',
                  keyboardType: TextInputType.number,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(
                        RegExp(r'[0-9A-Za-z\- ]'))
                  ]),
            ),
            const SizedBox(width: 12),
            Expanded(child: _CountryDropdown(
              value: _country,
              onChanged: (v) => setState(() => _country = v),
            )),
          ]),
          const SizedBox(height: 28),

          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _loading ? null : _doRegister,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF59E0B),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(
                          color: Color(0xFF1A1A2E), strokeWidth: 2))
                  : const Text('Create Account',
                      style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
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

  // ── Helpers ───────────────────────────────────────────────────────────────

  Widget _sectionLabel(String label) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Text(label,
            style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                color: Colors.grey.shade500,
                letterSpacing: 1.2)),
      );

  Widget _field(
    TextEditingController ctrl,
    String label,
    IconData icon, {
    String? hint,
    TextInputType? keyboardType,
    bool obscure = false,
    TextCapitalization textCapitalization = TextCapitalization.none,
    List<TextInputFormatter>? inputFormatters,
    Widget? suffixIcon,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: ctrl,
      obscureText: obscure,
      keyboardType: keyboardType,
      textCapitalization: textCapitalization,
      inputFormatters: inputFormatters,
      validator: validator,
      style: const TextStyle(fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
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
            borderSide:
                const BorderSide(color: Color(0xFF1A1A2E), width: 2)),
        errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Colors.red)),
        labelStyle: TextStyle(color: Colors.grey.shade600, fontSize: 13),
        hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 12),
      ),
    );
  }
}

// ── Google Logo (canvas-drawn, no external package needed) ────────────────────

class _GoogleLogo extends StatelessWidget {
  final double size;
  const _GoogleLogo({this.size = 24});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size, height: size,
      child: CustomPaint(painter: _GoogleLogoPainter()),
    );
  }
}

class _GoogleLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final r = size.width / 2;
    final center = Offset(r, r);

    // Draw colored arcs (simplified Google G)
    final colors = [
      const Color(0xFF4285F4), // blue  — right
      const Color(0xFF34A853), // green — bottom
      const Color(0xFFFBBC05), // yellow — bottom-left
      const Color(0xFFEA4335), // red   — top-left
    ];
    final sweeps = [pi * 0.5, pi * 0.5, pi * 0.5, pi * 0.5];
    final starts = [
      -pi * 0.25,           // blue starts at top-right
      pi * 0.25,            // green
      pi * 0.75,            // yellow
      -pi * 0.75,           // red
    ];

    for (var i = 0; i < 4; i++) {
      final paint = Paint()
        ..color = colors[i]
        ..strokeWidth = size.width * 0.28
        ..style = PaintingStyle.stroke
        ..strokeCap = StrokeCap.butt;
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: r * 0.72),
        starts[i], sweeps[i], false, paint,
      );
    }

    // White fill center
    canvas.drawCircle(center, r * 0.44, Paint()..color = Colors.white);

    // Blue horizontal bar of the "G"
    final barPaint = Paint()..color = const Color(0xFF4285F4);
    final barRect = RRect.fromRectAndRadius(
      Rect.fromLTWH(r * 0.54, r * 0.72, r * 0.9, r * 0.28),
      Radius.circular(r * 0.14),
    );
    canvas.drawRRect(barRect, barPaint);
  }

  @override
  bool shouldRepaint(_GoogleLogoPainter _) => false;
}

// ── Country dropdown ──────────────────────────────────────────────────────────

class _CountryDropdown extends StatelessWidget {
  final String value;
  final ValueChanged<String> onChanged;
  const _CountryDropdown({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _showSheet(context),
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Row(children: [
          Text(
            _kRegCountries
                    .firstWhere((c) => c.$1 == value,
                        orElse: () => _kRegCountries.first)
                    .$2
                    .split(' ')
                    .first,
            style: const TextStyle(fontSize: 20),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                  fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
          Icon(Icons.expand_more, size: 18, color: Colors.grey.shade400),
        ]),
      ),
    );
  }

  void _showSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CountrySheet(
        selected: value,
        onPick: (code) {
          onChanged(code);
          Navigator.pop(context);
        },
      ),
    );
  }
}

class _CountrySheet extends StatefulWidget {
  final String selected;
  final ValueChanged<String> onPick;
  const _CountrySheet({required this.selected, required this.onPick});
  @override
  State<_CountrySheet> createState() => _CountrySheetState();
}

class _CountrySheetState extends State<_CountrySheet> {
  String _q = '';
  List<(String, String)> get _filtered => _q.isEmpty
      ? _kRegCountries
      : _kRegCountries
          .where((c) =>
              c.$2.toLowerCase().contains(_q.toLowerCase()) ||
              c.$1.toLowerCase().contains(_q.toLowerCase()))
          .toList();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(children: [
        const SizedBox(height: 12),
        Container(
            width: 36,
            height: 4,
            decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2))),
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text('Select Country',
                style:
                    TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: TextField(
            onChanged: (v) => setState(() => _q = v),
            decoration: InputDecoration(
              prefixIcon:
                  const Icon(Icons.search, size: 18, color: Colors.grey),
              hintText: 'Search country...',
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide:
                      BorderSide(color: Colors.grey.shade300)),
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide:
                      BorderSide(color: Colors.grey.shade300)),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Expanded(
          child: ListView.builder(
            itemCount: _filtered.length,
            itemBuilder: (_, i) {
              final c   = _filtered[i];
              final sel = c.$1 == widget.selected;
              return ListTile(
                leading: Text(c.$2.split(' ').first,
                    style: const TextStyle(fontSize: 22)),
                title: Text(c.$2.substring(c.$2.indexOf(' ') + 1),
                    style: TextStyle(
                        fontWeight:
                            sel ? FontWeight.w700 : FontWeight.w500,
                        fontSize: 14)),
                trailing: sel
                    ? const Icon(Icons.check_circle_rounded,
                        color: Color(0xFFF59E0B))
                    : null,
                onTap: () => widget.onPick(c.$1),
              );
            },
          ),
        ),
      ]),
    );
  }
}
