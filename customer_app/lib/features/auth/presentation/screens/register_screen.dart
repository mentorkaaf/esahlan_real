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

// Private provider scoped to this file
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
  final _nameCtrl  = TextEditingController();
  final _phoneCtrl = TextEditingController();
  CountryCode _country = kDefaultCountry;
  final List<TextEditingController> _pinCtrls = List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _pinFocus = List.generate(4, (_) => FocusNode());
  int? _districtId;
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
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    for (final c in _pinCtrls) c.dispose();
    for (final f in _pinFocus) f.dispose();
    super.dispose();
  }

  String get _pin       => _pinCtrls.map((c) => c.text).join();
  String get _fullPhone => '${_country.dialCode}${_phoneCtrl.text.trim()}';

  Future<void> _register() async {
    if (_nameCtrl.text.trim().isEmpty)   { _err('Enter your full name');          return; }
    if (_phoneCtrl.text.trim().isEmpty)  { _err('Enter your phone number');        return; }
    if (_districtId == null)             { _err('Select your district');           return; }
    if (_pin.length < 4)                 { _err('Create a 4-digit PIN');           return; }

    await ref.read(registerProvider.notifier).register(
      name: _nameCtrl.text.trim(),
      phone: _fullPhone,
      password: _pin,
      districtId: _districtId,
    );
    if (!mounted) return;
    final state = ref.read(registerProvider);
    state.whenOrNull(
      error: (e, _) => _err(e.toString()),
      data: (_) => _postSavedLocationToBackend(),
    );
  }

  // Called after successful registration — token now exists, so the API call works
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
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) return;
      final pos = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high));
      await LocalStorage.saveDouble('saved_lat', pos.latitude);
      await LocalStorage.saveDouble('saved_lng', pos.longitude);
      // Backend POST happens after registration completes (token required)
    } catch (_) {}
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
    final isLoading  = ref.watch(registerProvider).isLoading;
    final distAsync  = ref.watch(_districtsProvider);

    return Scaffold(
      backgroundColor: const Color(0xFF07003B),
      body: Stack(
        children: [
          Positioned(top: -60, left: -80,  child: _Blob(size: 240, opacity: 0.10)),
          Positioned(bottom: -60, right: -40, child: _Blob(size: 200, opacity: 0.04, white: true)),
          SafeArea(
            child: Column(
              children: [
                // Top bar
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
                // Hero
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 28, 24, 0),
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: SlideTransition(
                      position: _slideAnim,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Create Account 🚀', style: TextStyle(
                          fontSize: 26, fontWeight: FontWeight.w900,
                          color: Colors.white, letterSpacing: -0.5,
                        )),
                        const SizedBox(height: 6),
                        Text('Join eSahlan in seconds',
                          style: TextStyle(fontSize: 14, color: Colors.white.withOpacity(0.5),
                              fontWeight: FontWeight.w500)),
                      ]),
                    ),
                  ),
                ),
                // White card
                Expanded(
                  child: FadeTransition(
                    opacity: _fadeAnim,
                    child: SlideTransition(
                      position: Tween<Offset>(begin: const Offset(0, 0.1), end: Offset.zero)
                          .animate(CurvedAnimation(parent: _animCtrl,
                              curve: const Interval(0.2, 1.0, curve: Curves.easeOut))),
                      child: Container(
                        margin: const EdgeInsets.only(top: 24),
                        decoration: BoxDecoration(
                          color: context.colors.elevatedBg,
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(32)),
                        ),
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(24, 28, 24, 40),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            // Full Name
                            const _Label('Full Name'),
                            const SizedBox(height: 8),
                            _InputField(ctrl: _nameCtrl, hint: 'Mohamed Omar',
                                icon: Icons.person_outline_rounded, cap: TextCapitalization.words),

                            const SizedBox(height: 20),

                            // Phone
                            const _Label('Phone Number'),
                            const SizedBox(height: 8),
                            PhoneInputField(
                              controller: _phoneCtrl,
                              onCountryChanged: (c) => setState(() => _country = c),
                            ),

                            const SizedBox(height: 20),

                            // District
                            const _Label('District'),
                            const SizedBox(height: 8),
                            distAsync.when(
                              loading: () => Builder(builder: (ctx) => Container(
                                height: 54,
                                decoration: BoxDecoration(
                                  color: ctx.colors.inputFill,
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(color: ctx.colors.borderColor, width: 1.5),
                                ),
                                child: const Center(child: SizedBox(width: 20, height: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2))),
                              )),
                              error: (e, _) => Text('Failed to load districts',
                                  style: TextStyle(color: AppColors.error, fontSize: 13)),
                              data: (list) => Builder(builder: (ctx) => Container(
                                decoration: BoxDecoration(
                                  color: ctx.colors.inputFill,
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(color: ctx.colors.borderColor, width: 1.5),
                                ),
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton<int>(
                                    value: _districtId,
                                    isExpanded: true,
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
                                      if (v != null) {
                                        setState(() => _districtId = v);
                                        _saveLocationSilently();
                                      }
                                    },
                                  ),
                                ),
                              )),
                            ),

                            const SizedBox(height: 20),

                            // PIN
                            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                              const _Label('Create PIN'),
                              Text('4 digits', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
                            ]),
                            const SizedBox(height: 8),
                            Text('You will use this PIN to sign in',
                              style: TextStyle(fontSize: 12, color: context.colors.mutedText, height: 1.4)),
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
                                onSubmit: i == 3 ? (_) => _register() : null,
                              )),
                            ),

                            const SizedBox(height: 32),

                            _ActionButton(
                              label: 'Create Account',
                              icon: Icons.check_rounded,
                              isLoading: isLoading,
                              onTap: isLoading ? null : _register,
                            ),

                            const SizedBox(height: 22),

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
}

// ─── Shared helpers (duplicated from login_screen — kept local) ───────────────

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
  final TextCapitalization cap;
  const _InputField({required this.ctrl, required this.hint, required this.icon,
      this.cap = TextCapitalization.none});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      decoration: BoxDecoration(
        color: c.inputFill,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: c.borderColor, width: 1.5),
      ),
      child: Row(children: [
        Padding(
          padding: const EdgeInsets.only(left: 14),
          child: Icon(icon, size: 18, color: c.subtleText),
        ),
        Expanded(
          child: TextField(
            controller: ctrl,
            textCapitalization: cap,
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.bodyText),
            decoration: InputDecoration(
              hintText: hint,
              hintStyle: TextStyle(color: c.subtleText, fontSize: 14, fontWeight: FontWeight.w400),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
            ),
          ),
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
    return Container(
      width: 68, height: 68,
      decoration: BoxDecoration(
        color: focused
            ? AppColors.primary.withOpacity(0.1)
            : filled
                ? c.surfaceBg
                : c.inputFill,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: focused ? AppColors.primary : filled ? AppColors.primary.withOpacity(0.4) : c.borderColor,
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
                  decoration: BoxDecoration(color: Colors.white.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(8)),
                  child: Icon(icon, size: 16),
                ),
              ]),
      ),
    );
  }
}
