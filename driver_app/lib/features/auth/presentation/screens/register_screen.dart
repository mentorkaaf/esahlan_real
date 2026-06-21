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

  @override
  void dispose() { _nameCtrl.dispose(); _phoneCtrl.dispose(); _passCtrl.dispose(); _plateCtrl.dispose(); super.dispose(); }

  void _register() {
    if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty || _passCtrl.text.trim().isEmpty) return;
    ref.read(registerProvider.notifier).register(
      name: _nameCtrl.text.trim(),
      phone: _phoneCtrl.text.trim(),
      password: _passCtrl.text.trim(),
      vehicleType: _vehicleType,
      plateNumber: _plateCtrl.text.trim().isEmpty ? null : _plateCtrl.text.trim(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final regState = ref.watch(registerProvider);

    ref.listen(registerProvider, (_, next) {
      if (next.hasError) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(next.error.toString()), backgroundColor: DC.error));
      }
    });

    return Scaffold(
      backgroundColor: DC.navy,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SizedBox(height: 32),
            Row(children: [
              Container(width: 44, height: 44, decoration: BoxDecoration(color: DC.orange, borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 24)),
              const SizedBox(width: 12),
              const Text('eSahlan', style: TextStyle(color: DC.orange, fontSize: 20, fontWeight: FontWeight.w900)),
              const Text(' Driver', style: TextStyle(color: DC.textSec, fontSize: 20, fontWeight: FontWeight.w500)),
            ]),
            const SizedBox(height: 32),
            const Text('Create Account', style: TextStyle(color: DC.text, fontSize: 26, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            const Text('Join our delivery team', style: TextStyle(color: DC.textSec, fontSize: 14)),
            const SizedBox(height: 28),

            _label('Full Name'),
            TextField(controller: _nameCtrl, style: const TextStyle(color: DC.text),
              decoration: const InputDecoration(hintText: 'Enter your name', prefixIcon: Icon(Icons.person_outline_rounded, color: DC.textMuted))),
            const SizedBox(height: 14),

            _label('Phone Number'),
            TextField(controller: _phoneCtrl, keyboardType: TextInputType.phone, style: const TextStyle(color: DC.text),
              decoration: const InputDecoration(hintText: '+252 61 234 5678', prefixIcon: Icon(Icons.phone_outlined, color: DC.textMuted))),
            const SizedBox(height: 14),

            _label('Password'),
            TextField(controller: _passCtrl, obscureText: _obscure, style: const TextStyle(color: DC.text),
              decoration: InputDecoration(hintText: 'Create a password', prefixIcon: const Icon(Icons.lock_outline_rounded, color: DC.textMuted),
                suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility, color: DC.textMuted), onPressed: () => setState(() => _obscure = !_obscure)))),
            const SizedBox(height: 14),

            _label('Vehicle Type'),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(color: DC.inputFill, borderRadius: BorderRadius.circular(12), border: Border.all(color: DC.border)),
              child: DropdownButton<String>(
                value: _vehicleType,
                isExpanded: true,
                dropdownColor: DC.card,
                underline: const SizedBox.shrink(),
                style: const TextStyle(color: DC.text, fontSize: 14),
                items: const [
                  DropdownMenuItem(value: 'motorcycle', child: Text('🏍️ Motorcycle')),
                  DropdownMenuItem(value: 'bajaj', child: Text('🛺 Bajaj')),
                  DropdownMenuItem(value: 'car', child: Text('🚗 Car')),
                  DropdownMenuItem(value: 'van', child: Text('🚐 Van')),
                  DropdownMenuItem(value: 'truck', child: Text('🚛 Truck')),
                  DropdownMenuItem(value: 'bicycle', child: Text('🚲 Bicycle')),
                ],
                onChanged: (v) => setState(() => _vehicleType = v!),
              ),
            ),
            const SizedBox(height: 14),

            _label('Plate Number (Optional)'),
            TextField(controller: _plateCtrl, style: const TextStyle(color: DC.text),
              decoration: const InputDecoration(hintText: 'e.g. MG-1234', prefixIcon: Icon(Icons.directions_car_outlined, color: DC.textMuted))),
            const SizedBox(height: 28),

            SizedBox(width: double.infinity, height: 54, child: ElevatedButton(
              onPressed: regState.isLoading ? null : _register,
              child: regState.isLoading
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : const Text('Register', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            )),
            const SizedBox(height: 20),
            Center(child: GestureDetector(
              onTap: () => context.go('/login'),
              child: RichText(text: const TextSpan(style: TextStyle(color: DC.textSec, fontSize: 14), children: [
                TextSpan(text: 'Already have an account? '),
                TextSpan(text: 'Login', style: TextStyle(color: DC.orange, fontWeight: FontWeight.w700)),
              ])),
            )),
          ]),
        ),
      ),
    );
  }

  Widget _label(String t) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(t, style: const TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
  );
}
