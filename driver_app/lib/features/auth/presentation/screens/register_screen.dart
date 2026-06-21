import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/driver_colors.dart';
import '../providers/auth_provider.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});
  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _nameCtrl  = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  final _plateCtrl = TextEditingController();
  String _vehicleType = 'motorcycle';
  bool _obscure = true;
  int _step = 0;

  @override
  void dispose() { _nameCtrl.dispose(); _phoneCtrl.dispose(); _passCtrl.dispose(); _plateCtrl.dispose(); super.dispose(); }

  void _register() {
    ref.read(registerProvider.notifier).register(
      name: _nameCtrl.text.trim(), phone: _phoneCtrl.text.trim(), password: _passCtrl.text.trim(),
      vehicleType: _vehicleType, plateNumber: _plateCtrl.text.trim().isEmpty ? null : _plateCtrl.text.trim(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(registerProvider);
    ref.listen(registerProvider, (_, next) {
      if (next.hasError) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(next.error.toString()), backgroundColor: DC.error, behavior: SnackBarBehavior.floating));
    });

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight,
          colors: [Color(0xFF0A1628), Color(0xFF0F1E38), Color(0xFF162A4A)])),
        child: SafeArea(child: Column(children: [
          // Top bar
          Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(children: [
              if (_step > 0) IconButton(icon: const Icon(Icons.arrow_back_rounded, color: Colors.white), onPressed: () => setState(() => _step = 0)),
              const Spacer(),
              RichText(text: const TextSpan(children: [
                TextSpan(text: 'eSahlan ', style: TextStyle(color: DC.orange, fontSize: 18, fontWeight: FontWeight.w900)),
                TextSpan(text: 'Driver', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w300)),
              ])),
              const Spacer(),
              const SizedBox(width: 48),
            ])),

          // Steps indicator
          Padding(padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 8),
            child: Row(children: [
              Expanded(child: Container(height: 4, decoration: BoxDecoration(color: DC.orange, borderRadius: BorderRadius.circular(2)))),
              const SizedBox(width: 6),
              Expanded(child: Container(height: 4, decoration: BoxDecoration(color: _step >= 1 ? DC.orange : DC.border, borderRadius: BorderRadius.circular(2)))),
            ])),

          Expanded(child: SingleChildScrollView(padding: const EdgeInsets.symmetric(horizontal: 28),
            child: _step == 0 ? _stepPersonal() : _stepVehicle(),
          )),
        ])),
      ),
    );
  }

  Widget _stepPersonal() => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    const SizedBox(height: 24),
    const Text('Personal Info', style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900)),
    const SizedBox(height: 4),
    const Text('Join our delivery team', style: TextStyle(color: DC.textSec, fontSize: 14)),
    const SizedBox(height: 28),
    _label('Full Name'),
    _field(_nameCtrl, 'Enter your full name', Icons.person_outline_rounded),
    const SizedBox(height: 14),
    _label('Phone Number'),
    _field(_phoneCtrl, '+252 61 234 5678', Icons.phone_outlined, keyboard: TextInputType.phone),
    const SizedBox(height: 14),
    _label('Password'),
    _field(_passCtrl, 'Create a strong password', Icons.lock_outline_rounded, obscure: _obscure, suffix: IconButton(
      icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, color: DC.textMuted, size: 20),
      onPressed: () => setState(() => _obscure = !_obscure))),
    const SizedBox(height: 32),
    _orangeBtn('Continue →', () {
      if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty || _passCtrl.text.length < 4) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill all fields (min 4 chars password)'), backgroundColor: DC.error, behavior: SnackBarBehavior.floating));
        return;
      }
      setState(() => _step = 1);
    }),
    const SizedBox(height: 24),
    Center(child: GestureDetector(onTap: () => context.go('/login'),
      child: RichText(text: const TextSpan(style: TextStyle(fontSize: 14), children: [
        TextSpan(text: 'Already have an account? ', style: TextStyle(color: DC.textSec)),
        TextSpan(text: 'Login', style: TextStyle(color: DC.orange, fontWeight: FontWeight.w700)),
      ])))),
    const SizedBox(height: 30),
  ]);

  Widget _stepVehicle() {
    final state = ref.watch(registerProvider);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 24),
      const Text('Vehicle Info', style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900)),
      const SizedBox(height: 4),
      const Text('Tell us about your vehicle', style: TextStyle(color: DC.textSec, fontSize: 14)),
      const SizedBox(height: 28),
      _label('Vehicle Type'),
      Wrap(spacing: 10, runSpacing: 10, children: [
        _vehicleChip('motorcycle', '🏍️', 'Motorcycle'),
        _vehicleChip('bajaj', '🛺', 'Bajaj'),
        _vehicleChip('car', '🚗', 'Car'),
        _vehicleChip('van', '🚐', 'Van'),
        _vehicleChip('truck', '🚛', 'Truck'),
        _vehicleChip('bicycle', '🚲', 'Bicycle'),
      ]),
      const SizedBox(height: 20),
      _label('Plate Number (Optional)'),
      _field(_plateCtrl, 'e.g. MG-1234', Icons.directions_car_outlined),
      const SizedBox(height: 36),
      _orangeBtn(state.isLoading ? null : 'Register', state.isLoading ? () {} : _register, loading: state.isLoading),
      const SizedBox(height: 30),
    ]);
  }

  Widget _vehicleChip(String value, String emoji, String label) {
    final sel = _vehicleType == value;
    return GestureDetector(
      onTap: () => setState(() => _vehicleType = value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: sel ? DC.orangeDim : DC.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: sel ? DC.orange : DC.border, width: sel ? 2 : 1),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Text(emoji, style: const TextStyle(fontSize: 20)),
          const SizedBox(width: 8),
          Text(label, style: TextStyle(color: sel ? DC.orange : DC.textSec, fontWeight: sel ? FontWeight.w700 : FontWeight.w500, fontSize: 13)),
        ]),
      ),
    );
  }

  Widget _label(String t) => Padding(padding: const EdgeInsets.only(bottom: 8),
    child: Text(t, style: const TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 0.3)));

  Widget _field(TextEditingController ctrl, String hint, IconData icon, {bool obscure = false, Widget? suffix, TextInputType? keyboard}) =>
    Container(
      decoration: BoxDecoration(color: DC.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.border),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 6, offset: const Offset(0, 3))]),
      child: TextField(controller: ctrl, obscureText: obscure, keyboardType: keyboard,
        style: const TextStyle(color: Colors.white, fontSize: 15),
        decoration: InputDecoration(hintText: hint, hintStyle: TextStyle(color: DC.textMuted.withValues(alpha: 0.7)),
          prefixIcon: Icon(icon, color: DC.textMuted, size: 20), suffixIcon: suffix,
          border: InputBorder.none, contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16))),
    );

  Widget _orangeBtn(String? label, VoidCallback onTap, {bool loading = false}) => GestureDetector(
    onTap: loading ? null : onTap,
    child: Container(width: double.infinity, height: 56,
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)]),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.4), blurRadius: 20, offset: const Offset(0, 8))]),
      child: Center(child: loading
        ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
        : Text(label ?? '', style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)))),
  );
}
