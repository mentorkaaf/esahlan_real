import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:dio/dio.dart';
import 'dart:io';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/storage/local_storage.dart';
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
  String _driverType = 'normal';
  String _vehicleType = 'motorcycle';
  bool _obscure = true;
  int _step = 0;
  bool _loading = false;

  File? _nationalId;
  File? _driverLicense;
  File? _vehicleReg;

  final _picker = ImagePicker();

  @override
  void dispose() { _nameCtrl.dispose(); _phoneCtrl.dispose(); _passCtrl.dispose(); _plateCtrl.dispose(); super.dispose(); }

  Future<void> _pickDoc(String type) async {
    final picked = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (picked == null) return;
    setState(() {
      switch (type) {
        case 'national_id':    _nationalId    = File(picked.path);
        case 'driver_license': _driverLicense = File(picked.path);
        case 'vehicle_reg':    _vehicleReg    = File(picked.path);
      }
    });
  }

  Future<void> _register() async {
    if (_nationalId == null || _driverLicense == null || _vehicleReg == null) {
      _snack('Please upload all required documents');
      return;
    }
    setState(() => _loading = true);
    try {
      final formData = FormData.fromMap({
        'name': _nameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        'password': _passCtrl.text.trim(),
        'driver_type': _driverType,
        'vehicle_type': _vehicleType,
        'plate_number': _plateCtrl.text.trim(),
        'national_id': await MultipartFile.fromFile(_nationalId!.path, filename: 'national_id.jpg'),
        'driver_license': await MultipartFile.fromFile(_driverLicense!.path, filename: 'driver_license.jpg'),
        'vehicle_reg': await MultipartFile.fromFile(_vehicleReg!.path, filename: 'vehicle_reg.jpg'),
      });

      final res = await ApiClient.instance.post('/delivery/auth/register', data: formData,
        options: Options(contentType: 'multipart/form-data'));

      final data = res.data['data'];
      await LocalStorage.saveToken(data['token']);
      await LocalStorage.saveBool('is_approved', false);
      await LocalStorage.saveString('driver_type', _driverType);
      ref.invalidate(authStateProvider);
    } on DioException catch (e) {
      _snack(ApiException.fromDio(e).message);
    } catch (e) {
      _snack('$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  void _snack(String msg) => ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(msg), backgroundColor: DC.error, behavior: SnackBarBehavior.floating));

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(gradient: LinearGradient(
          begin: Alignment.topLeft, end: Alignment.bottomRight,
          colors: isDark
            ? [const Color(0xFF0A1628), const Color(0xFF0F1E38), const Color(0xFF162A4A)]
            : [c.navyLight, c.navy, const Color(0xFFE0E8F4)],
        )),
        child: SafeArea(child: Column(children: [
          Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(children: [
              if (_step > 0) IconButton(icon: Icon(Icons.arrow_back_rounded, color: c.text), onPressed: () => setState(() => _step--)),
              const Spacer(),
              RichText(text: const TextSpan(children: [
                TextSpan(text: 'eSahlan ', style: TextStyle(color: DC.orange, fontSize: 18, fontWeight: FontWeight.w900)),
                TextSpan(text: 'Driver', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w300)),
              ])),
              const Spacer(), const SizedBox(width: 48),
            ])),
          // Progress
          Padding(padding: const EdgeInsets.symmetric(horizontal: 50, vertical: 8),
            child: Row(children: List.generate(3, (i) => Expanded(child: Container(
              height: 4, margin: const EdgeInsets.symmetric(horizontal: 3),
              decoration: BoxDecoration(color: _step >= i ? DC.orange : c.border, borderRadius: BorderRadius.circular(2))))))),
          Expanded(child: SingleChildScrollView(padding: const EdgeInsets.symmetric(horizontal: 28),
            child: [_stepType, _stepPersonal, _stepDocuments][_step]())),
        ])),
      ),
    );
  }

  Widget _stepType() {
    final c = context.dc;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 24),
      Text('Choose Your Role', style: TextStyle(color: c.text, fontSize: 26, fontWeight: FontWeight.w900)),
      const SizedBox(height: 4),
      Text('What type of deliveries will you do?', style: TextStyle(color: c.textSec, fontSize: 14)),
      const SizedBox(height: 28),

      _TypeCard(
        selected: _driverType == 'normal',
        emoji: '🏍️',
        title: 'Normal Delivery',
        desc: 'Food, parcels, shop orders, grocery, laundry',
        modules: 'eFood · eShop · eParcel · eGrocery · eLaundry',
        onTap: () => setState(() { _driverType = 'normal'; _vehicleType = 'motorcycle'; }),
      ),
      const SizedBox(height: 12),
      _TypeCard(
        selected: _driverType == 'truck',
        emoji: '🚛',
        title: 'Truck / Moving',
        desc: 'Furniture, house moving, large items',
        modules: 'eMoving',
        onTap: () => setState(() { _driverType = 'truck'; _vehicleType = 'truck'; }),
      ),
      const SizedBox(height: 32),
      _orangeBtn('Continue →', () => setState(() => _step = 1)),
      const SizedBox(height: 24),
      Center(child: GestureDetector(onTap: () => context.go('/login'),
        child: RichText(text: TextSpan(style: const TextStyle(fontSize: 14), children: [
          TextSpan(text: 'Already have an account? ', style: TextStyle(color: c.textSec)),
          const TextSpan(text: 'Login', style: TextStyle(color: DC.orange, fontWeight: FontWeight.w700)),
        ])))),
      const SizedBox(height: 30),
    ]);
  }

  Widget _stepPersonal() {
    final c = context.dc;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 24),
      Text('Personal Info', style: TextStyle(color: c.text, fontSize: 26, fontWeight: FontWeight.w900)),
      const SizedBox(height: 28),
      _label('Full Name'),
      _field(_nameCtrl, 'Enter your name', Icons.person_outline_rounded),
      const SizedBox(height: 14),
      _label('Phone Number'),
      _field(_phoneCtrl, '+252 61 234 5678', Icons.phone_outlined, keyboard: TextInputType.phone),
      const SizedBox(height: 14),
      _label('Password'),
      _field(_passCtrl, 'Min 4 characters', Icons.lock_outline_rounded, obscure: _obscure,
        suffix: IconButton(icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, color: c.textMuted, size: 20),
          onPressed: () => setState(() => _obscure = !_obscure))),
      const SizedBox(height: 14),
      _label('Vehicle Type'),
      Wrap(spacing: 8, runSpacing: 8, children: _driverType == 'normal'
        ? [_chip('motorcycle', '🏍️'), _chip('bajaj', '🛺'), _chip('car', '🚗'), _chip('bicycle', '🚲')]
        : [_chip('truck', '🚛'), _chip('van', '🚐'), _chip('pickup', '🚛')]),
      const SizedBox(height: 14),
      _label('Plate Number'),
      _field(_plateCtrl, 'e.g. MG-1234', Icons.directions_car_outlined),
      const SizedBox(height: 32),
      _orangeBtn('Continue →', () {
        if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty || _passCtrl.text.length < 4) {
          _snack('Fill all fields (min 4 chars password)');
          return;
        }
        setState(() => _step = 2);
      }),
      const SizedBox(height: 30),
    ]);
  }

  Widget _stepDocuments() {
    final c = context.dc;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 24),
      Text('Documents', style: TextStyle(color: c.text, fontSize: 26, fontWeight: FontWeight.w900)),
      const SizedBox(height: 4),
      Text('Upload required documents for verification', style: TextStyle(color: c.textSec, fontSize: 14)),
      const SizedBox(height: 28),
      _docUpload('National ID (NIRA)', 'national_id', _nationalId),
      const SizedBox(height: 12),
      _docUpload('Driver License', 'driver_license', _driverLicense),
      const SizedBox(height: 12),
      _docUpload('Vehicle Registration', 'vehicle_reg', _vehicleReg),
      const SizedBox(height: 32),
      _orangeBtn(_loading ? null : 'Submit Application', _loading ? () {} : _register, loading: _loading),
      const SizedBox(height: 12),
      Center(child: Text('Your application will be reviewed by admin',
        style: TextStyle(color: c.textMuted, fontSize: 12))),
      const SizedBox(height: 30),
    ]);
  }

  Widget _docUpload(String label, String type, File? file) {
    final c = context.dc;
    return GestureDetector(
      onTap: () => _pickDoc(type),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: file != null ? DC.success.withValues(alpha: 0.08) : c.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: file != null ? DC.success.withValues(alpha: 0.4) : c.border),
        ),
        child: Row(children: [
          Container(width: 44, height: 44,
            decoration: BoxDecoration(
              color: file != null ? DC.success.withValues(alpha: 0.15) : c.border.withValues(alpha: 0.3),
              borderRadius: BorderRadius.circular(12)),
            child: Icon(file != null ? Icons.check_circle_rounded : Icons.upload_file_rounded,
              color: file != null ? DC.success : c.textMuted, size: 22)),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: TextStyle(color: file != null ? DC.success : c.text, fontWeight: FontWeight.w700, fontSize: 14)),
            Text(file != null ? 'Uploaded ✓' : 'Tap to upload photo',
              style: TextStyle(color: file != null ? DC.success.withValues(alpha: 0.7) : c.textMuted, fontSize: 12)),
          ])),
          Icon(Icons.camera_alt_rounded, color: file != null ? DC.success : c.textMuted),
        ]),
      ),
    );
  }

  Widget _TypeCard({required bool selected, required String emoji, required String title, required String desc, required String modules, required VoidCallback onTap}) {
    final c = context.dc;
    return GestureDetector(onTap: onTap, child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: selected ? DC.orange.withValues(alpha: 0.08) : c.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: selected ? DC.orange : c.border, width: selected ? 2 : 1),
      ),
      child: Row(children: [
        Text(emoji, style: const TextStyle(fontSize: 36)),
        const SizedBox(width: 16),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: TextStyle(color: selected ? DC.orange : c.text, fontSize: 16, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text(desc, style: TextStyle(color: c.textSec, fontSize: 12)),
          const SizedBox(height: 6),
          Text(modules, style: TextStyle(color: c.textMuted, fontSize: 10, fontWeight: FontWeight.w600)),
        ])),
        if (selected) const Icon(Icons.check_circle_rounded, color: DC.orange, size: 24),
      ]),
    ));
  }

  Widget _chip(String value, String emoji) {
    final c = context.dc;
    final sel = _vehicleType == value;
    return GestureDetector(onTap: () => setState(() => _vehicleType = value),
      child: Container(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(color: sel ? DC.orangeDim : c.surface, borderRadius: BorderRadius.circular(10),
          border: Border.all(color: sel ? DC.orange : c.border, width: sel ? 2 : 1)),
        child: Text('$emoji ${value[0].toUpperCase()}${value.substring(1)}',
          style: TextStyle(color: sel ? DC.orange : c.textSec, fontWeight: sel ? FontWeight.w700 : FontWeight.w500, fontSize: 13))));
  }

  Widget _label(String t) {
    return Padding(padding: const EdgeInsets.only(bottom: 8),
      child: Text(t, style: TextStyle(color: context.dc.textSec, fontSize: 12, fontWeight: FontWeight.w600)));
  }

  Widget _field(TextEditingController ctrl, String hint, IconData icon, {bool obscure = false, Widget? suffix, TextInputType? keyboard}) {
    final c = context.dc;
    return Container(
      decoration: BoxDecoration(color: c.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border)),
      child: TextField(controller: ctrl, obscureText: obscure, keyboardType: keyboard,
        style: TextStyle(color: c.text, fontSize: 15),
        decoration: InputDecoration(hintText: hint, hintStyle: TextStyle(color: c.textMuted.withValues(alpha: 0.7)),
          prefixIcon: Icon(icon, color: c.textMuted, size: 20), suffixIcon: suffix,
          border: InputBorder.none, contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16))));
  }

  Widget _orangeBtn(String? label, VoidCallback onTap, {bool loading = false}) => GestureDetector(
    onTap: loading ? null : onTap,
    child: Container(width: double.infinity, height: 56,
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)]),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.4), blurRadius: 20, offset: const Offset(0, 8))]),
      child: Center(child: loading
        ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
        : Text(label ?? '', style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)))));
}
