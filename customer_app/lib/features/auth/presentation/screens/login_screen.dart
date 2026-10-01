import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../../core/constants/app_assets.dart';
import '../../../../core/l10n/app_strings.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/phone_input_field.dart';
import '../providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  // Mode: 0 = Phone+PIN, 1 = Email+Password
  int _mode = 0;

  // Phone+PIN
  final _phoneCtrl = TextEditingController();
  CountryCode _country = kDefaultCountry;
  final List<TextEditingController> _pinCtrls = List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _pinFocus = List.generate(4, (_) => FocusNode());

  // Email+Password
  final _emailCtrl    = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _showPassword  = false;

  late AnimationController _animCtrl;
  late Animation<double>   _fadeAnim;
  late Animation<Offset>   _slideAnim;

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
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    for (final c in _pinCtrls) c.dispose();
    for (final f in _pinFocus) f.dispose();
    super.dispose();
  }

  String get _pin       => _pinCtrls.map((c) => c.text).join();
  String get _fullPhone => '${_country.dialCode}${_phoneCtrl.text.trim()}';

  Future<void> _googleLogin() async {
    await ref.read(googleLoginProvider.notifier).login();
    if (!mounted) return;
    ref.read(googleLoginProvider).whenOrNull(
      error: (e, _) {
        final msg = e.toString().replaceFirst('Exception: ', '');
        if (msg != 'cancelled') _err(msg);
      },
    );
  }

  Future<void> _login() async {
    final l = AppL10n.of(context);
    if (_mode == 0) {
      if (_phoneCtrl.text.trim().isEmpty) { _err(l.enterPhone); return; }
      if (_pin.length < 4)               { _err(l.enterPin);   return; }
      await ref.read(loginProvider.notifier).login(phone: _fullPhone, password: _pin);
    } else {
      if (_emailCtrl.text.trim().isEmpty)    { _err(l.enterEmailAddr); return; }
      if (_passwordCtrl.text.trim().isEmpty) { _err(l.enterPassword);  return; }
      await ref.read(loginProvider.notifier)
          .login(email: _emailCtrl.text.trim(), password: _passwordCtrl.text);
    }
    if (!mounted) return;
    ref.read(loginProvider).whenOrNull(error: (e, _) => _err(e.toString()));
  }

  void _err(String msg) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(msg), backgroundColor: AppColors.error,
    behavior: SnackBarBehavior.floating,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
  ));

  @override
  Widget build(BuildContext context) {
    final isLoading       = ref.watch(loginProvider).isLoading;
    final isGoogleLoading = ref.watch(googleLoginProvider).isLoading;
    final isDesktop = kIsWeb && MediaQuery.sizeOf(context).width >= 900;

    if (isDesktop) return _buildDesktop(context, isLoading, isGoogleLoading);
    return _buildMobile(context, isLoading, isGoogleLoading);
  }

  Widget _buildDesktop(BuildContext context, bool isLoading, bool isGoogleLoading) {
    return Scaffold(
      body: Row(children: [
        const SizedBox(width: 400, child: _AuthLeftPanel()),
        Expanded(
          child: Container(
            color: context.colors.elevatedBg,
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 56, vertical: 48),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 460),
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: _desktopForm(context, isLoading, isGoogleLoading),
                  ),
                ),
              ),
            ),
          ),
        ),
      ]),
    );
  }

  Column _desktopForm(BuildContext context, bool isLoading, bool isGoogleLoading) {
    final l = AppL10n.of(context);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(l.welcomeBack, style: TextStyle(
        fontSize: 32, fontWeight: FontWeight.w900,
        color: context.colors.navyText, letterSpacing: -0.5)),
      const SizedBox(height: 6),
      Text(l.signInSubtitle,
        style: TextStyle(fontSize: 15, color: context.colors.mutedText, fontWeight: FontWeight.w500)),
      const SizedBox(height: 36),
      _ModeToggle(selected: _mode, onChanged: (v) => setState(() => _mode = v)),
      const SizedBox(height: 28),
      if (_mode == 0) ...[
        _Label(l.phoneNumber),
        const SizedBox(height: 8),
        PhoneInputField(controller: _phoneCtrl, onCountryChanged: (c) => setState(() => _country = c)),
        const SizedBox(height: 24),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          _Label(l.pinCode),
          Text(l.fourDigits, style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
        ]),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(4, (i) => _PinBox(
            controller: _pinCtrls[i], focusNode: _pinFocus[i],
            onChanged: (v) {
              if (v.isNotEmpty && i < 3) FocusScope.of(context).requestFocus(_pinFocus[i + 1]);
              else if (v.isEmpty && i > 0) FocusScope.of(context).requestFocus(_pinFocus[i - 1]);
            },
            onSubmit: i == 3 ? (_) => _login() : null,
          )),
        ),
      ],
      if (_mode == 1) ...[
        _Label(l.emailAddress),
        const SizedBox(height: 8),
        _InputField(ctrl: _emailCtrl, hint: 'you@example.com',
            icon: Icons.email_outlined, type: TextInputType.emailAddress),
        const SizedBox(height: 20),
        _Label(l.password),
        const SizedBox(height: 8),
        _PasswordField(ctrl: _passwordCtrl, show: _showPassword,
            onToggle: () => setState(() => _showPassword = !_showPassword),
            onSubmit: (_) => _login()),
      ],
      const SizedBox(height: 32),
      _ActionButton(label: l.signIn, icon: Icons.arrow_forward_rounded,
          isLoading: isLoading, onTap: isLoading ? null : _login),
      const SizedBox(height: 20),
      _OrDivider(),
      const SizedBox(height: 20),
      _GoogleButton(isLoading: isGoogleLoading, onTap: isGoogleLoading ? null : _googleLogin),
      const SizedBox(height: 24),
      Center(child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        Text('${l.dontHaveAccount} ',
          style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
        GestureDetector(
          onTap: () => context.go('/auth/register'),
          child: Text(l.signUp, style: TextStyle(
            color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 14)),
        ),
      ])),
    ]);
  }

  Widget _buildMobile(BuildContext context, bool isLoading, bool isGoogleLoading) {
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
                  padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
                  child: Center(child: Image.asset(AppAssets.appLogo, height: 30, fit: BoxFit.contain)),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 14, 24, 0),
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: SlideTransition(
                      position: _slideAnim,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(AppL10n.of(context).welcomeBack, style: const TextStyle(
                          fontSize: 22, fontWeight: FontWeight.w900,
                          color: Colors.white, letterSpacing: -0.5)),
                        const SizedBox(height: 4),
                        Text(AppL10n.of(context).signInSubtitle,
                          style: TextStyle(fontSize: 14, color: Colors.white.withOpacity(0.55),
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
                        margin: const EdgeInsets.only(top: 16),
                        decoration: BoxDecoration(
                          color: context.colors.elevatedBg,
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(32)),
                        ),
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
                          child: Builder(builder: (ctx) {
                            final l = AppL10n.of(ctx);
                            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            _ModeToggle(selected: _mode, onChanged: (v) => setState(() => _mode = v)),
                            const SizedBox(height: 18),
                            if (_mode == 0) ...[
                              _Label(l.phoneNumber),
                              const SizedBox(height: 6),
                              PhoneInputField(controller: _phoneCtrl,
                                  onCountryChanged: (c) => setState(() => _country = c)),
                              const SizedBox(height: 16),
                              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                                _Label(l.pinCode),
                                Text(l.fourDigits, style: TextStyle(
                                    fontSize: 12, color: ctx.colors.mutedText)),
                              ]),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: List.generate(4, (i) => _PinBox(
                                  controller: _pinCtrls[i], focusNode: _pinFocus[i],
                                  onChanged: (v) {
                                    if (v.isNotEmpty && i < 3) FocusScope.of(ctx).requestFocus(_pinFocus[i + 1]);
                                    else if (v.isEmpty && i > 0) FocusScope.of(ctx).requestFocus(_pinFocus[i - 1]);
                                  },
                                  onSubmit: i == 3 ? (_) => _login() : null,
                                )),
                              ),
                            ],
                            if (_mode == 1) ...[
                              _Label(l.emailAddress),
                              const SizedBox(height: 6),
                              _InputField(ctrl: _emailCtrl, hint: 'you@example.com',
                                  icon: Icons.email_outlined, type: TextInputType.emailAddress),
                              const SizedBox(height: 14),
                              _Label(l.password),
                              const SizedBox(height: 6),
                              _PasswordField(ctrl: _passwordCtrl, show: _showPassword,
                                  onToggle: () => setState(() => _showPassword = !_showPassword),
                                  onSubmit: (_) => _login()),
                            ],
                            const SizedBox(height: 20),
                            _ActionButton(label: l.signIn, icon: Icons.arrow_forward_rounded,
                                isLoading: isLoading, onTap: isLoading ? null : _login),
                            const SizedBox(height: 14),
                            _OrDivider(),
                            const SizedBox(height: 14),
                            _GoogleButton(isLoading: isGoogleLoading, onTap: isGoogleLoading ? null : _googleLogin),
                            const SizedBox(height: 16),
                            Center(child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Text('${l.dontHaveAccount} ',
                                style: TextStyle(color: ctx.colors.mutedText, fontSize: 14)),
                              GestureDetector(
                                onTap: () => ctx.go('/auth/register'),
                                child: Text(l.signUp, style: TextStyle(
                                  color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 14)),
                              ),
                            ])),
                          ]);}),
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

// ─── Mode Toggle ──────────────────────────────────────────────────────────────

class _ModeToggle extends StatelessWidget {
  final int selected;
  final ValueChanged<int> onChanged;
  const _ModeToggle({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      decoration: BoxDecoration(
        color: c.inputFill,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.primary.withOpacity(0.2), width: 1),
      ),
      padding: const EdgeInsets.all(4),
      child: Builder(builder: (context) {
        final l = AppL10n.of(context);
        return Row(children: [
          _Tab(label: l.phoneAndPin, active: selected == 0, onTap: () => onChanged(0)),
          const SizedBox(width: 4),
          _Tab(label: l.emailAndPass, active: selected == 1, onTap: () => onChanged(1)),
        ]);
      }),
    );
  }
}

class _Tab extends StatelessWidget {
  final String label;
  final bool active;
  final VoidCallback onTap;
  const _Tab({required this.label, required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: active ? AppColors.primary : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Text(label,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 12, fontWeight: FontWeight.w700,
              color: active ? Colors.white : context.colors.mutedText,
            ),
          ),
        ),
      ),
    );
  }
}

// ─── Shared Widgets ───────────────────────────────────────────────────────────

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
    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.bodyText));
}

class _InputField extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final IconData icon;
  final TextInputType type;
  const _InputField({required this.ctrl, required this.hint,
      required this.icon, this.type = TextInputType.text});
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
        Padding(
          padding: const EdgeInsets.only(left: 14),
          child: Icon(icon, size: 18, color: c.subtleText),
        ),
        Expanded(
          child: TextField(
            controller: ctrl,
            keyboardType: type,
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.bodyText),
            decoration: InputDecoration(
              hintText: hint,
              hintStyle: TextStyle(color: c.subtleText, fontSize: 14, fontWeight: FontWeight.w400),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
          ),
        ),
      ]),
    );
  }
}

class _PasswordField extends StatelessWidget {
  final TextEditingController ctrl;
  final bool show;
  final VoidCallback onToggle;
  final ValueChanged<String>? onSubmit;
  const _PasswordField({required this.ctrl, required this.show,
      required this.onToggle, this.onSubmit});
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
        Padding(
          padding: const EdgeInsets.only(left: 14),
          child: Icon(Icons.lock_outline_rounded, size: 18, color: c.subtleText),
        ),
        Expanded(
          child: TextField(
            controller: ctrl,
            obscureText: !show,
            onSubmitted: onSubmit,
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.bodyText),
            decoration: InputDecoration(
              hintText: AppL10n.of(context).yourPassword,
              hintStyle: TextStyle(color: c.subtleText, fontSize: 14, fontWeight: FontWeight.w400),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
          ),
        ),
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
    final focused = focusNode.hasFocus;
    final filled  = controller.text.isNotEmpty;
    final isDark  = context.isDark;
    return ListenableBuilder(
      listenable: focusNode,
      builder: (_, __) => Container(
        width: 58, height: 58,
        decoration: BoxDecoration(
          color: focused
              ? AppColors.primary.withOpacity(0.12)
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
          controller: controller,
          focusNode: focusNode,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          maxLength: 1,
          obscureText: true,
          obscuringCharacter: '●',
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: c.navyText),
          decoration: const InputDecoration(counterText: '', border: InputBorder.none),
          onChanged: onChanged,
          onSubmitted: onSubmit,
        ),
      ),
    );
  }
}

class _OrDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Row(children: [
      Expanded(child: Divider(color: context.colors.mutedText.withOpacity(0.25), thickness: 1)),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14),
        child: Text(l.orDivider, style: TextStyle(
          fontSize: 13, fontWeight: FontWeight.w600, color: context.colors.mutedText)),
      ),
      Expanded(child: Divider(color: context.colors.mutedText.withOpacity(0.25), thickness: 1)),
    ]);
  }
}

class _GoogleButton extends StatelessWidget {
  final VoidCallback? onTap;
  final bool isLoading;
  const _GoogleButton({this.onTap, this.isLoading = false});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity, height: 50,
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          side: BorderSide(color: AppColors.primary.withOpacity(0.4), width: 1.5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          backgroundColor: context.colors.elevatedBg,
        ),
        child: isLoading
            ? SizedBox(width: 22, height: 22,
                child: CircularProgressIndicator(
                  color: AppColors.primary, strokeWidth: 2.5))
            : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                // Google "G" icon using colored text
                Container(
                  width: 26, height: 26,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.grey.withOpacity(0.3)),
                  ),
                  child: const Center(
                    child: Text('G', style: TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w800,
                      color: Color(0xFF4285F4),
                      fontFamily: 'Arial',
                    )),
                  ),
                ),
                const SizedBox(width: 12),
                Text(AppL10n.of(context).continueGoogle,
                  style: TextStyle(
                    fontSize: 15, fontWeight: FontWeight.w700,
                    color: context.colors.bodyText,
                  )),
              ]),
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
      width: double.infinity, height: 50,
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
                  decoration: BoxDecoration(color: Colors.white.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(8)),
                  child: Icon(icon, size: 16),
                ),
              ]),
      ),
    );
  }
}
