import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../../core/constants/app_assets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/phone_input_field.dart';
import '../providers/auth_provider.dart';
import '../../data/models/district_model.dart';
import '../../data/repositories/district_repository.dart';

final _districtsProvider = FutureProvider<List<DistrictModel>>((ref) {
  return DistrictRepository().getDistricts();
});

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});
  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen>
    with SingleTickerProviderStateMixin {
  // Credential mode: 0 = PIN, 1 = Email+Password
  int _credMode = 0;

  final _nameCtrl     = TextEditingController();
  final _phoneCtrl    = TextEditingController();
  final _referralCtrl = TextEditingController();
  CountryCode _country = kDefaultCountry;
  int? _districtId;

  // PIN
  final List<TextEditingController> _pinCtrls = List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _pinFocus = List.generate(4, (_) => FocusNode());

  // Email+Password
  final _emailCtrl    = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _confirmCtrl  = TextEditingController();
  bool _showPass      = false;
  bool _showConfirm   = false;

  // Password strength
  bool get _hasUpper   => _passwordCtrl.text.contains(RegExp(r'[A-Z]'));
  bool get _hasLower   => _passwordCtrl.text.contains(RegExp(r'[a-z]'));
  bool get _hasDigit   => _passwordCtrl.text.contains(RegExp(r'[0-9]'));
  bool get _hasSpecial => _passwordCtrl.text.contains(RegExp(r'[@$!%*#?&^_\-]'));
  bool get _hasLength  => _passwordCtrl.text.length >= 8;

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
    _passwordCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _animCtrl.dispose();
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _referralCtrl.dispose();
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmCtrl.dispose();
    for (final c in _pinCtrls) c.dispose();
    for (final f in _pinFocus) f.dispose();
    super.dispose();
  }

  String get _pin       => _pinCtrls.map((c) => c.text).join();
  String get _fullPhone => '${_country.dialCode}${_phoneCtrl.text.trim()}';

  Future<void> _register() async {
    if (_nameCtrl.text.trim().isEmpty)  { _err('Enter your full name');   return; }
    if (_phoneCtrl.text.trim().isEmpty) { _err('Enter your phone number'); return; }
    if (_districtId == null)            { _err('Select your district');    return; }

    String? email;
    String password;

    if (_credMode == 0) {
      if (_pin.length < 4) { _err('Create a 4-digit PIN'); return; }
      password = _pin;
    } else {
      if (_emailCtrl.text.trim().isEmpty)  { _err('Enter your email address'); return; }
      if (!RegExp(r'^[\w\.\-]+@[\w\-]+\.\w+$').hasMatch(_emailCtrl.text.trim())) {
        _err('Enter a valid email address'); return;
      }
      if (!_hasLength)  { _err('Password must be at least 8 characters'); return; }
      if (!_hasUpper)   { _err('Password needs an uppercase letter');     return; }
      if (!_hasLower)   { _err('Password needs a lowercase letter');      return; }
      if (!_hasDigit)   { _err('Password needs a number');                return; }
      if (!_hasSpecial) { _err('Password needs a special character (@\$!%*#?&)'); return; }
      if (_passwordCtrl.text != _confirmCtrl.text) { _err('Passwords do not match'); return; }
      email    = _emailCtrl.text.trim();
      password = _passwordCtrl.text;
    }

    await ref.read(registerProvider.notifier).register(
      name: _nameCtrl.text.trim(),
      phone: _fullPhone,
      password: password,
      email: email,
      districtId: _districtId,
      referralCode: _referralCtrl.text.trim().isEmpty ? null : _referralCtrl.text.trim().toUpperCase(),
    );
    if (!mounted) return;
    ref.read(registerProvider).whenOrNull(
      error: (e, _) => _err(e.toString()),
      data: (_) => _postSavedLocationToBackend(),
    );
  }

  Future<void> _postSavedLocationToBackend() async {
    try {
      final lat = await LocalStorage.getDouble('saved_lat');
      final lng = await LocalStorage.getDouble('saved_lng');
      if (lat != null && lng != null) {
        await ref.read(authRepositoryProvider).updateLocation(lat, lng);
      }
    } catch (_) {}
  }

  Future<void> _saveLocationSilently() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) return;
      final pos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.high));
      await LocalStorage.saveDouble('saved_lat', pos.latitude);
      await LocalStorage.saveDouble('saved_lng', pos.longitude);
    } catch (_) {}
  }

  void _err(String msg) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(msg), backgroundColor: AppColors.error,
    behavior: SnackBarBehavior.floating,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
  ));

  @override
  Widget build(BuildContext context) {
    final isLoading = ref.watch(registerProvider).isLoading;
    final distAsync = ref.watch(_districtsProvider);
    final isDesktop = kIsWeb && MediaQuery.sizeOf(context).width >= 900;

    if (isDesktop) return _buildDesktop(context, isLoading, distAsync);
    return _buildMobile(context, isLoading, distAsync);
  }

  Widget _buildDesktop(BuildContext context, bool isLoading, AsyncValue<List<DistrictModel>> distAsync) {
    return Scaffold(
      body: Row(children: [
        const SizedBox(width: 400, child: _AuthLeftPanel()),
        Expanded(
          child: Container(
            color: context.colors.elevatedBg,
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 56, vertical: 48),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: _desktopForm(context, isLoading, distAsync),
                  ),
                ),
              ),
            ),
          ),
        ),
      ]),
    );
  }

  Column _desktopForm(BuildContext context, bool isLoading, AsyncValue<List<DistrictModel>> distAsync) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        GestureDetector(
          onTap: () => context.go('/auth/login'),
          child: Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.08),
              borderRadius: BorderRadius.circular(11),
              border: Border.all(color: AppColors.primary.withOpacity(0.2)),
            ),
            child: Icon(Icons.arrow_back_rounded, color: AppColors.primary, size: 18),
          ),
        ),
        const SizedBox(width: 16),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Create Account 🚀', style: TextStyle(
            fontSize: 28, fontWeight: FontWeight.w900,
            color: context.colors.navyText, letterSpacing: -0.5)),
          const SizedBox(height: 2),
          Text('Join eSahlan in seconds',
            style: TextStyle(fontSize: 14, color: context.colors.mutedText, fontWeight: FontWeight.w500)),
        ]),
      ]),
      const SizedBox(height: 32),
      Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const _Label('Full Name'),
          const SizedBox(height: 8),
          _InputField(ctrl: _nameCtrl, hint: 'Mohamed Omar',
              icon: Icons.person_outline_rounded, cap: TextCapitalization.words),
        ])),
        const SizedBox(width: 16),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const _Label('Phone Number'),
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.12),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text('Required', style: TextStyle(
                  fontSize: 10, fontWeight: FontWeight.w700, color: AppColors.primary)),
            ),
          ]),
          const SizedBox(height: 8),
          PhoneInputField(controller: _phoneCtrl,
              onCountryChanged: (c) => setState(() => _country = c)),
        ])),
      ]),
      const SizedBox(height: 16),
      Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const _Label('District'),
          const SizedBox(height: 8),
          distAsync.when(
            loading: () => Builder(builder: (ctx) => _distContainer(ctx,
              child: const Center(child: SizedBox(width: 20, height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2))))),
            error: (e, _) => Text('Failed to load districts',
                style: TextStyle(color: AppColors.error, fontSize: 13)),
            data: (list) => Builder(builder: (ctx) => _distContainer(ctx,
              child: DropdownButtonHideUnderline(
                child: DropdownButton<int>(
                  value: _districtId, isExpanded: true,
                  dropdownColor: ctx.colors.elevatedBg,
                  icon: Icon(Icons.keyboard_arrow_down_rounded, color: ctx.colors.bodyText),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                  hint: Text('Select your district',
                      style: TextStyle(color: ctx.colors.subtleText, fontSize: 14)),
                  style: TextStyle(color: ctx.colors.bodyText, fontSize: 15,
                      fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
                  borderRadius: BorderRadius.circular(14),
                  items: list.map((d) => DropdownMenuItem(value: d.id, child: Text(d.name))).toList(),
                  onChanged: (v) {
                    if (v != null) { setState(() => _districtId = v); _saveLocationSilently(); }
                  },
                ),
              ),
            )),
          ),
        ])),
        const SizedBox(width: 16),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const _Label('Referral Code (optional)'),
          const SizedBox(height: 8),
          _InputField(ctrl: _referralCtrl, hint: 'e.g. ABC12345',
              icon: Icons.card_giftcard_rounded, cap: TextCapitalization.characters),
        ])),
      ]),
      const SizedBox(height: 20),
      const _Label('Security Method'),
      const SizedBox(height: 10),
      _CredToggle(selected: _credMode, onChanged: (v) => setState(() => _credMode = v)),
      const SizedBox(height: 20),
      if (_credMode == 0) ...[
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const _Label('Create PIN'),
          Text('4 digits', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
        ]),
        const SizedBox(height: 4),
        Text('Quick & easy sign in every time',
          style: TextStyle(fontSize: 12, color: context.colors.mutedText, height: 1.4)),
        const SizedBox(height: 12),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(4, (i) => _PinBox(
            controller: _pinCtrls[i], focusNode: _pinFocus[i],
            onChanged: (v) {
              if (v.isNotEmpty && i < 3) FocusScope.of(context).requestFocus(_pinFocus[i + 1]);
              else if (v.isEmpty && i > 0) FocusScope.of(context).requestFocus(_pinFocus[i - 1]);
            },
            onSubmit: i == 3 ? (_) => _register() : null,
          ))),
      ],
      if (_credMode == 1) ...[
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const _Label('Email Address'),
            const SizedBox(height: 8),
            _InputField(ctrl: _emailCtrl, hint: 'you@example.com',
                icon: Icons.email_outlined, type: TextInputType.emailAddress),
          ])),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const _Label('Password'),
            const SizedBox(height: 8),
            _PasswordField(ctrl: _passwordCtrl, show: _showPass,
                onToggle: () => setState(() => _showPass = !_showPass)),
          ])),
        ]),
        const SizedBox(height: 10),
        _PasswordStrength(hasLength: _hasLength, hasUpper: _hasUpper,
            hasLower: _hasLower, hasDigit: _hasDigit, hasSpecial: _hasSpecial),
        const SizedBox(height: 14),
        const _Label('Confirm Password'),
        const SizedBox(height: 8),
        _PasswordField(ctrl: _confirmCtrl, show: _showConfirm, hint: 'Re-enter password',
            onToggle: () => setState(() => _showConfirm = !_showConfirm),
            onSubmit: (_) => _register()),
      ],
      const SizedBox(height: 28),
      _ActionButton(label: 'Create Account', icon: Icons.check_rounded,
          isLoading: isLoading, onTap: isLoading ? null : _register),
      const SizedBox(height: 20),
      Center(child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        Text('Already have an account? ',
          style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
        GestureDetector(
          onTap: () => context.go('/auth/login'),
          child: Text('Sign In', style: TextStyle(
            color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 14)),
        ),
      ])),
    ]);
  }

  Widget _buildMobile(BuildContext context, bool isLoading, AsyncValue<List<DistrictModel>> distAsync) {
    return Scaffold(
      backgroundColor: const Color(0xFF07003B),
      body: Stack(
        children: [
          Positioned(top: -60, left: -80,    child: _Blob(size: 240, opacity: 0.10)),
          Positioned(bottom: -60, right: -40, child: _Blob(size: 200, opacity: 0.04, white: true)),
          SafeArea(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                  child: Row(children: [
                    GestureDetector(
                      onTap: () => context.go('/auth/login'),
                      child: Container(
                        width: 40, height: 40,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(11),
                        ),
                        child: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 18),
                      ),
                    ),
                    const Spacer(),
                    Image.asset(AppAssets.appLogo, height: 36, fit: BoxFit.contain),
                    const Spacer(),
                    const SizedBox(width: 40),
                  ]),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 22, 24, 0),
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: SlideTransition(
                      position: _slideAnim,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Create Account 🚀', style: TextStyle(
                          fontSize: 26, fontWeight: FontWeight.w900,
                          color: Colors.white, letterSpacing: -0.5)),
                        const SizedBox(height: 6),
                        Text('Join eSahlan in seconds',
                          style: TextStyle(fontSize: 14, color: Colors.white.withOpacity(0.5),
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
                        margin: const EdgeInsets.only(top: 20),
                        decoration: BoxDecoration(
                          color: context.colors.elevatedBg,
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(32)),
                        ),
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(24, 24, 24, 40),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            const _Label('Full Name'),
                            const SizedBox(height: 8),
                            _InputField(ctrl: _nameCtrl, hint: 'Mohamed Omar',
                                icon: Icons.person_outline_rounded, cap: TextCapitalization.words),
                            const SizedBox(height: 16),
                            Row(children: [
                              const _Label('Phone Number'),
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                decoration: BoxDecoration(
                                  color: AppColors.primary.withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text('Required', style: TextStyle(
                                    fontSize: 10, fontWeight: FontWeight.w700, color: AppColors.primary)),
                              ),
                            ]),
                            const SizedBox(height: 8),
                            PhoneInputField(controller: _phoneCtrl,
                                onCountryChanged: (c) => setState(() => _country = c)),
                            const SizedBox(height: 16),
                            const _Label('District'),
                            const SizedBox(height: 8),
                            distAsync.when(
                              loading: () => Builder(builder: (ctx) => _distContainer(ctx,
                                child: const Center(child: SizedBox(width: 20, height: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2))))),
                              error: (e, _) => Text('Failed to load districts',
                                  style: TextStyle(color: AppColors.error, fontSize: 13)),
                              data: (list) => Builder(builder: (ctx) => _distContainer(ctx,
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton<int>(
                                    value: _districtId, isExpanded: true,
                                    dropdownColor: ctx.colors.elevatedBg,
                                    icon: Icon(Icons.keyboard_arrow_down_rounded, color: ctx.colors.bodyText),
                                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                                    hint: Text('Select your district',
                                        style: TextStyle(color: ctx.colors.subtleText, fontSize: 14)),
                                    style: TextStyle(color: ctx.colors.bodyText, fontSize: 15,
                                        fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
                                    borderRadius: BorderRadius.circular(14),
                                    items: list.map((d) => DropdownMenuItem(
                                        value: d.id, child: Text(d.name))).toList(),
                                    onChanged: (v) {
                                      if (v != null) { setState(() => _districtId = v); _saveLocationSilently(); }
                                    },
                                  ),
                                ),
                              )),
                            ),
                            const SizedBox(height: 16),
                            const _Label('Referral Code (optional)'),
                            const SizedBox(height: 8),
                            _InputField(ctrl: _referralCtrl, hint: 'e.g. ABC12345',
                                icon: Icons.card_giftcard_rounded, cap: TextCapitalization.characters),
                            const SizedBox(height: 20),
                            const _Label('Security Method'),
                            const SizedBox(height: 10),
                            _CredToggle(selected: _credMode,
                                onChanged: (v) => setState(() => _credMode = v)),
                            const SizedBox(height: 20),
                            if (_credMode == 0) ...[
                              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                                const _Label('Create PIN'),
                                Text('4 digits', style: TextStyle(
                                    fontSize: 12, color: context.colors.mutedText)),
                              ]),
                              const SizedBox(height: 4),
                              Text('Quick & easy sign in every time',
                                style: TextStyle(fontSize: 12, color: context.colors.mutedText, height: 1.4)),
                              const SizedBox(height: 12),
                              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: List.generate(4, (i) => _PinBox(
                                  controller: _pinCtrls[i], focusNode: _pinFocus[i],
                                  onChanged: (v) {
                                    if (v.isNotEmpty && i < 3) FocusScope.of(context).requestFocus(_pinFocus[i + 1]);
                                    else if (v.isEmpty && i > 0) FocusScope.of(context).requestFocus(_pinFocus[i - 1]);
                                  },
                                  onSubmit: i == 3 ? (_) => _register() : null,
                                ))),
                            ],
                            if (_credMode == 1) ...[
                              const _Label('Email Address'),
                              const SizedBox(height: 8),
                              _InputField(ctrl: _emailCtrl, hint: 'you@example.com',
                                  icon: Icons.email_outlined, type: TextInputType.emailAddress),
                              const SizedBox(height: 16),
                              const _Label('Password'),
                              const SizedBox(height: 8),
                              _PasswordField(ctrl: _passwordCtrl, show: _showPass,
                                  onToggle: () => setState(() => _showPass = !_showPass)),
                              const SizedBox(height: 10),
                              _PasswordStrength(hasLength: _hasLength, hasUpper: _hasUpper,
                                  hasLower: _hasLower, hasDigit: _hasDigit, hasSpecial: _hasSpecial),
                              const SizedBox(height: 14),
                              const _Label('Confirm Password'),
                              const SizedBox(height: 8),
                              _PasswordField(ctrl: _confirmCtrl, show: _showConfirm,
                                  hint: 'Re-enter password',
                                  onToggle: () => setState(() => _showConfirm = !_showConfirm),
                                  onSubmit: (_) => _register()),
                            ],
                            const SizedBox(height: 28),
                            _ActionButton(label: 'Create Account', icon: Icons.check_rounded,
                                isLoading: isLoading, onTap: isLoading ? null : _register),
                            const SizedBox(height: 20),
                            Center(child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Text('Already have an account? ',
                                style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
                              GestureDetector(
                                onTap: () => context.go('/auth/login'),
                                child: Text('Sign In', style: TextStyle(
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

  Widget _distContainer(BuildContext ctx, {required Widget child}) => Container(
    height: 54,
    decoration: BoxDecoration(
      color: ctx.colors.inputFill,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: AppColors.primary.withOpacity(0.35), width: 1.5),
    ),
    child: child,
  );
}

// ─── Desktop Left Branding Panel ─────────────────────────────────────────────

class _AuthLeftPanel extends StatelessWidget {
  const _AuthLeftPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(color: Color(0xFF07003B)),
      child: Stack(children: [
        Positioned(top: -80, right: -60,   child: _Blob(size: 300, opacity: 0.13)),
        Positioned(bottom: -80, left: -60, child: _Blob(size: 260, opacity: 0.06, white: true)),
        Positioned(top: 240, left: -50,    child: _Blob(size: 180, opacity: 0.07)),
        SafeArea(
          child: Column(children: [
            const SizedBox(height: 52),
            Image.asset(AppAssets.appLogo, height: 52, fit: BoxFit.contain),
            const SizedBox(height: 10),
            Text('Everything You Need', style: TextStyle(
              color: Colors.white.withOpacity(0.45),
              fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 2.5,
            )),
            const Spacer(),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 36),
              child: Wrap(
                spacing: 10, runSpacing: 10,
                alignment: WrapAlignment.center,
                children: const [
                  _ServiceChip(icon: Icons.restaurant_rounded,       label: 'eFood'),
                  _ServiceChip(icon: Icons.local_grocery_store_rounded, label: 'eGrocery'),
                  _ServiceChip(icon: Icons.shopping_bag_rounded,     label: 'eShop'),
                  _ServiceChip(icon: Icons.local_shipping_rounded,   label: 'eParcel'),
                  _ServiceChip(icon: Icons.school_rounded,           label: 'eLearning'),
                  _ServiceChip(icon: Icons.home_rounded,             label: 'eRent'),
                  _ServiceChip(icon: Icons.sim_card_rounded,         label: 'eData'),
                  _ServiceChip(icon: Icons.confirmation_num_rounded, label: 'eTicket'),
                ],
              ),
            ),
            const Spacer(),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 40),
              child: Column(children: [
                Text("Somalia's Super App", style: TextStyle(
                  color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900,
                  letterSpacing: -0.4,
                )),
                SizedBox(height: 10),
                Text(
                  'Order food, shop online, send parcels,\nlearn new skills — all in one place.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white54, fontSize: 13, height: 1.7),
                ),
              ]),
            ),
            const Spacer(),
            Text('© 2025 eSahlan · All rights reserved.',
              style: TextStyle(color: Colors.white.withOpacity(0.22), fontSize: 11)),
            const SizedBox(height: 36),
          ]),
        ),
      ]),
    );
  }
}

class _ServiceChip extends StatelessWidget {
  final IconData icon;
  final String label;
  const _ServiceChip({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    decoration: BoxDecoration(
      color: Colors.white.withOpacity(0.07),
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: Colors.white.withOpacity(0.13)),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 14, color: AppColors.primary),
      const SizedBox(width: 7),
      Text(label, style: const TextStyle(
        color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  );
}

// ─── Credential Mode Toggle ───────────────────────────────────────────────────

class _CredToggle extends StatelessWidget {
  final int selected;
  final ValueChanged<int> onChanged;
  const _CredToggle({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      _CredOption(
        index: 0, selected: selected,
        icon: Icons.pin_outlined,
        title: '4-Digit PIN',
        subtitle: 'Fast & easy — sign in with your phone + PIN',
        onTap: () => onChanged(0),
      ),
      const SizedBox(height: 10),
      _CredOption(
        index: 1, selected: selected,
        icon: Icons.security_rounded,
        title: 'Email & Strong Password',
        subtitle: 'Higher security — uppercase, numbers & symbols required',
        onTap: () => onChanged(1),
      ),
    ]);
  }
}

class _CredOption extends StatelessWidget {
  final int index, selected;
  final IconData icon;
  final String title, subtitle;
  final VoidCallback onTap;
  const _CredOption({required this.index, required this.selected, required this.icon,
      required this.title, required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = index == selected;
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: active ? AppColors.primary.withOpacity(0.08) : context.colors.inputFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: active ? AppColors.primary : AppColors.primary.withOpacity(0.2),
            width: active ? 2 : 1,
          ),
        ),
        child: Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: active ? AppColors.primary : AppColors.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 20,
                color: active ? Colors.white : AppColors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: TextStyle(
              fontSize: 13, fontWeight: FontWeight.w700,
              color: active ? AppColors.primary : context.colors.bodyText,
            )),
            const SizedBox(height: 2),
            Text(subtitle, style: TextStyle(
                fontSize: 11, color: context.colors.mutedText, height: 1.3)),
          ])),
          Container(
            width: 20, height: 20,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: active ? AppColors.primary : Colors.transparent,
              border: Border.all(
                color: active ? AppColors.primary : AppColors.primary.withOpacity(0.35),
                width: 2,
              ),
            ),
            child: active ? const Icon(Icons.check, size: 12, color: Colors.white) : null,
          ),
        ]),
      ),
    );
  }
}

// ─── Password Strength Indicator ──────────────────────────────────────────────

class _PasswordStrength extends StatelessWidget {
  final bool hasLength, hasUpper, hasLower, hasDigit, hasSpecial;
  const _PasswordStrength({required this.hasLength, required this.hasUpper,
      required this.hasLower, required this.hasDigit, required this.hasSpecial});

  @override
  Widget build(BuildContext context) {
    final checks = [
      (hasLength,  '8+ characters'),
      (hasUpper,   'Uppercase (A-Z)'),
      (hasLower,   'Lowercase (a-z)'),
      (hasDigit,   'Number (0-9)'),
      (hasSpecial, 'Symbol (@\$!%*#?&)'),
    ];
    final passed = checks.where((c) => c.$1).length;
    final color  = passed <= 1 ? const Color(0xFFEF4444)
        : passed <= 3 ? const Color(0xFFF97316)
        : const Color(0xFF10B981);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Expanded(child: ClipRRect(
          borderRadius: BorderRadius.circular(3),
          child: LinearProgressIndicator(
            value: passed / 5,
            backgroundColor: AppColors.primary.withOpacity(0.1),
            valueColor: AlwaysStoppedAnimation<Color>(color),
            minHeight: 5,
          ),
        )),
        const SizedBox(width: 8),
        Text(['Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Strong'][passed],
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color)),
      ]),
      const SizedBox(height: 8),
      Wrap(spacing: 6, runSpacing: 6, children: checks.map((c) => _CheckChip(
        label: c.$2, passed: c.$1)).toList()),
    ]);
  }
}

class _CheckChip extends StatelessWidget {
  final String label;
  final bool passed;
  const _CheckChip({required this.label, required this.passed});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: passed ? const Color(0xFF10B981).withOpacity(0.12) : AppColors.primary.withOpacity(0.06),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(
        color: passed ? const Color(0xFF10B981).withOpacity(0.4) : AppColors.primary.withOpacity(0.15),
      ),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(passed ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
          size: 12, color: passed ? const Color(0xFF10B981) : context.colors.mutedText),
      const SizedBox(width: 4),
      Text(label, style: TextStyle(
          fontSize: 10.5, fontWeight: FontWeight.w600,
          color: passed ? const Color(0xFF10B981) : context.colors.mutedText)),
    ]),
  );
}

// ─── Shared Widgets ───────────────────────────────────────────────────────────

class _Blob extends StatelessWidget {
  final double size, opacity;
  final bool white;
  const _Blob({required this.size, required this.opacity, this.white = false});
  @override
  Widget build(BuildContext context) => Container(
    width: size, height: size,
    decoration: BoxDecoration(shape: BoxShape.circle,
      color: (white ? Colors.white : AppColors.primary).withOpacity(opacity)));
}

class _Label extends StatelessWidget {
  final String text;
  const _Label(this.text);
  @override
  Widget build(BuildContext context) => Text(text,
    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.bodyText));
}

class _InputField extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final IconData icon;
  final TextCapitalization cap;
  final TextInputType type;
  const _InputField({required this.ctrl, required this.hint, required this.icon,
      this.cap = TextCapitalization.none, this.type = TextInputType.text});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      decoration: BoxDecoration(
        color: context.isDark ? c.scaffoldBg : c.inputFill,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.primary.withOpacity(0.35), width: 1.5),
      ),
      child: Row(children: [
        Padding(padding: const EdgeInsets.only(left: 14),
            child: Icon(icon, size: 18, color: c.subtleText)),
        Expanded(child: TextField(
          controller: ctrl,
          textCapitalization: cap,
          keyboardType: type,
          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.bodyText),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(color: c.subtleText, fontSize: 14, fontWeight: FontWeight.w400),
            border: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
          ),
        )),
      ]),
    );
  }
}

class _PasswordField extends StatelessWidget {
  final TextEditingController ctrl;
  final bool show;
  final String hint;
  final VoidCallback onToggle;
  final ValueChanged<String>? onSubmit;
  const _PasswordField({required this.ctrl, required this.show,
      required this.onToggle, this.hint = 'Create a strong password', this.onSubmit});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      decoration: BoxDecoration(
        color: context.isDark ? c.scaffoldBg : c.inputFill,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.primary.withOpacity(0.35), width: 1.5),
      ),
      child: Row(children: [
        Padding(padding: const EdgeInsets.only(left: 14),
            child: Icon(Icons.lock_outline_rounded, size: 18, color: c.subtleText)),
        Expanded(child: TextField(
          controller: ctrl,
          obscureText: !show,
          onSubmitted: onSubmit,
          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.bodyText),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(color: c.subtleText, fontSize: 14, fontWeight: FontWeight.w400),
            border: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
          ),
        )),
        IconButton(
          onPressed: onToggle,
          icon: Icon(show ? Icons.visibility_off_outlined : Icons.visibility_outlined,
              size: 18, color: c.subtleText),
        ),
      ]),
    );
  }
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
    final c = context.colors;
    final isDark = context.isDark;
    return ListenableBuilder(
      listenable: focusNode,
      builder: (_, __) {
        final focused = focusNode.hasFocus;
        final filled  = controller.text.isNotEmpty;
        return Container(
          width: 68, height: 68,
          decoration: BoxDecoration(
            color: focused ? AppColors.primary.withOpacity(0.12)
                : filled ? c.surfaceBg : isDark ? c.scaffoldBg : c.inputFill,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: focused ? AppColors.primary
                  : filled ? AppColors.primary.withOpacity(0.4)
                  : AppColors.primary.withOpacity(0.3),
              width: focused ? 2.5 : 1.5,
            ),
            boxShadow: focused
                ? [BoxShadow(color: AppColors.primary.withOpacity(0.2), blurRadius: 12, offset: const Offset(0, 4))]
                : null,
          ),
          child: TextField(
            controller: controller, focusNode: focusNode,
            keyboardType: TextInputType.number,
            textAlign: TextAlign.center, maxLength: 1,
            obscureText: true, obscuringCharacter: '●',
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: c.navyText),
            decoration: const InputDecoration(counterText: '', border: InputBorder.none),
            onChanged: onChanged, onSubmitted: onSubmit,
          ),
        );
      },
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
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity, height: 56,
    child: ElevatedButton(
      onPressed: onTap,
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFF07003B), foregroundColor: Colors.white,
        elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
      child: isLoading
          ? const SizedBox(width: 22, height: 22,
              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
          : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              Text(label, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
              const SizedBox(width: 10),
              Container(
                width: 28, height: 28,
                decoration: BoxDecoration(color: Colors.white.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(8)),
                child: Icon(icon, size: 16),
              ),
            ]),
    ),
  );
}
