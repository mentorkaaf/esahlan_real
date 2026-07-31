import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/api/api_client.dart';
import '../../core/theme/vc.dart';

// ─── Role definition ─────────────────────────────────────────────────────────

class _RoleDef {
  final String key;
  final String label;
  final String subtitle;
  final IconData icon;
  final Color color;
  final bool isAgent;
  const _RoleDef({
    required this.key, required this.label, required this.subtitle,
    required this.icon, required this.color, this.isAgent = false,
  });
}

const _roles = [
  _RoleDef(
    key: 'efood', label: 'eFood Vendor',
    subtitle: 'Restaurants, cafés & food delivery',
    icon: Icons.restaurant_rounded, color: Color(0xFFEF4444),
  ),
  _RoleDef(
    key: 'eshop', label: 'eShop Vendor',
    subtitle: 'Retail stores, products & marketplace',
    icon: Icons.storefront_rounded, color: Color(0xFFF97316),
  ),
  _RoleDef(
    key: 'erent_agent', label: 'eRent Agent',
    subtitle: 'Property listings & rental brokerage',
    icon: Icons.real_estate_agent_rounded, color: Color(0xFF0EA5E9),
    isAgent: true,
  ),
];

// ─── Screen ──────────────────────────────────────────────────────────────────

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _api = ApiClient();

  // step 0 = role pick, 1 = personal, 2 = business/agent info
  int _step = 0;
  _RoleDef? _role;

  // step 1 controllers
  final _nameCtrl  = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _obscure = true;

  // step 2 — vendor
  final _storeNameCtrl = TextEditingController();
  final _descCtrl      = TextEditingController();
  final _addrCtrl      = TextEditingController();
  File? _licenseFile;

  // step 2 — shared
  List<Map> _districts = [];
  int? _districtId;

  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _nameCtrl.dispose(); _emailCtrl.dispose(); _phoneCtrl.dispose();
    _passCtrl.dispose(); _storeNameCtrl.dispose(); _descCtrl.dispose();
    _addrCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadDistricts() async {
    try {
      final res = await _api.get('/vendor/register/districts');
      if (mounted) {
        setState(() => _districts = List<Map>.from(res['data'] ?? []));
      }
    } catch (_) {}
  }

  void _pickRole(_RoleDef r) {
    setState(() { _role = r; _step = 1; _error = null; });
  }

  void _nextToStep2() {
    final name  = _nameCtrl.text.trim();
    final email = _emailCtrl.text.trim();
    final phone = _phoneCtrl.text.trim();
    final pass  = _passCtrl.text;
    if (name.isEmpty) { setState(() => _error = 'Enter your full name'); return; }
    if (email.isEmpty && phone.isEmpty) { setState(() => _error = 'Enter email or phone'); return; }
    if (pass.length < 8) { setState(() => _error = 'Password must be at least 8 characters'); return; }
    setState(() { _step = 2; _error = null; });
    if (_districts.isEmpty) _loadDistricts();
  }

  Future<void> _pickLicense() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked != null) setState(() => _licenseFile = File(picked.path));
  }

  Future<void> _submit() async {
    if (_districtId == null) { setState(() => _error = 'Select your district'); return; }
    if (!_role!.isAgent && _storeNameCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Enter your store name'); return;
    }

    setState(() { _loading = true; _error = null; });
    try {
      final form = FormData.fromMap({
        'role_type':   _role!.key,
        'name':        _nameCtrl.text.trim(),
        'email':       _emailCtrl.text.trim().isEmpty ? null : _emailCtrl.text.trim(),
        'phone':       _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
        'password':    _passCtrl.text,
        'district_id': _districtId,
        if (!_role!.isAgent) 'store_name':        _storeNameCtrl.text.trim(),
        if (!_role!.isAgent) 'store_description': _descCtrl.text.trim(),
        if (!_role!.isAgent) 'store_address':     _addrCtrl.text.trim(),
        if (_licenseFile != null)
          'business_license': await MultipartFile.fromFile(
            _licenseFile!.path,
            filename: 'license.jpg',
          ),
      });
      final res = await _api.postForm('/vendor/register', form);
      if (!mounted) return;
      if (res['success'] == true) {
        _showSuccess(res['message'] ?? 'Application submitted!');
      } else {
        setState(() { _error = res['message'] ?? 'Registration failed'; _loading = false; });
      }
    } catch (e) {
      String msg = 'Connection error. Try again.';
      if (e is DioException && e.response?.data != null) {
        final d = e.response!.data;
        if (d is Map && d['message'] != null) msg = d['message'].toString();
      }
      if (mounted) setState(() { _error = msg; _loading = false; });
    }
  }

  void _showSuccess(String msg) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 72, height: 72,
            decoration: BoxDecoration(color: Colors.green.shade50, shape: BoxShape.circle),
            child: Icon(Icons.check_circle_rounded, color: Colors.green.shade600, size: 44),
          ),
          const SizedBox(height: 16),
          const Text('Application Submitted!', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900), textAlign: TextAlign.center),
          const SizedBox(height: 8),
          Text(msg, style: const TextStyle(fontSize: 13, color: Colors.grey), textAlign: TextAlign.center),
        ]),
        actions: [
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: VC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              onPressed: () { Navigator.pop(context); Navigator.pop(context); },
              child: const Text('Go to Login', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? VC.navy : VC.lightBg,
      appBar: AppBar(
        backgroundColor: isDark ? VC.navyLight : VC.lightSurface,
        title: Text(_step == 0 ? 'Create Account' : _step == 1 ? 'Personal Info' : 'Business Info'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (_step == 0) Navigator.pop(context);
            else setState(() { _step--; _error = null; });
          },
        ),
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 250),
        child: _step == 0
            ? _StepRole(roles: _roles, onPick: _pickRole, key: const ValueKey(0))
            : _step == 1
                ? _StepPersonal(
                    key: const ValueKey(1),
                    nameCtrl: _nameCtrl, emailCtrl: _emailCtrl,
                    phoneCtrl: _phoneCtrl, passCtrl: _passCtrl,
                    obscure: _obscure,
                    onToggleObscure: () => setState(() => _obscure = !_obscure),
                    error: _error,
                    onNext: _nextToStep2,
                    role: _role!,
                  )
                : _StepBusiness(
                    key: const ValueKey(2),
                    role: _role!,
                    districts: _districts,
                    districtId: _districtId,
                    storeNameCtrl: _storeNameCtrl,
                    descCtrl: _descCtrl,
                    addrCtrl: _addrCtrl,
                    licenseFile: _licenseFile,
                    error: _error,
                    loading: _loading,
                    onDistrictChanged: (v) => setState(() => _districtId = v),
                    onPickLicense: _pickLicense,
                    onSubmit: _submit,
                  ),
      ),
    );
  }
}

// ─── Step 0: Role picker ─────────────────────────────────────────────────────

class _StepRole extends StatelessWidget {
  final List<_RoleDef> roles;
  final void Function(_RoleDef) onPick;
  const _StepRole({super.key, required this.roles, required this.onPick});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final txt = isDark ? VC.text : const Color(0xFF1A2340);
    final sec = isDark ? VC.textSec : const Color(0xFF5A6B82);
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const SizedBox(height: 8),
        Text('What describes you?', style: TextStyle(color: txt, fontSize: 22, fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        Text('Choose your role to get started', style: TextStyle(color: sec, fontSize: 14)),
        const SizedBox(height: 28),
        ...roles.map((r) => _RoleCard(role: r, onTap: () => onPick(r))),
      ]),
    );
  }
}

class _RoleCard extends StatelessWidget {
  final _RoleDef role;
  final VoidCallback onTap;
  const _RoleCard({super.key, required this.role, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final card = isDark ? VC.navyCard : VC.lightSurface;
    final txt  = isDark ? VC.text : const Color(0xFF1A2340);
    final sec  = isDark ? VC.textSec : const Color(0xFF5A6B82);
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: card,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: role.color.withValues(alpha: 0.25), width: 1.5),
        ),
        child: Row(children: [
          Container(
            width: 52, height: 52,
            decoration: BoxDecoration(
              color: role.color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(role.icon, color: role.color, size: 28),
          ),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(role.label, style: TextStyle(color: txt, fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text(role.subtitle, style: TextStyle(color: sec, fontSize: 13)),
          ])),
          Icon(Icons.chevron_right_rounded, color: role.color),
        ]),
      ),
    );
  }
}

// ─── Step 1: Personal info ────────────────────────────────────────────────────

class _StepPersonal extends StatelessWidget {
  final _RoleDef role;
  final TextEditingController nameCtrl, emailCtrl, phoneCtrl, passCtrl;
  final bool obscure;
  final VoidCallback onToggleObscure;
  final String? error;
  final VoidCallback onNext;

  const _StepPersonal({
    super.key, required this.role,
    required this.nameCtrl, required this.emailCtrl,
    required this.phoneCtrl, required this.passCtrl,
    required this.obscure, required this.onToggleObscure,
    this.error, required this.onNext,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final txt = isDark ? VC.text : const Color(0xFF1A2340);
    final sec = isDark ? VC.textSec : const Color(0xFF5A6B82);
    final accent = role.color;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // role badge
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: accent.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(role.icon, color: accent, size: 16),
            const SizedBox(width: 6),
            Text(role.label, style: TextStyle(color: accent, fontWeight: FontWeight.w700, fontSize: 13)),
          ]),
        ),
        const SizedBox(height: 20),
        Text('Your Details', style: TextStyle(color: txt, fontSize: 20, fontWeight: FontWeight.w900)),
        const SizedBox(height: 20),

        if (error != null) ...[
          _ErrorBox(error!),
          const SizedBox(height: 14),
        ],

        _Label('Full Name', sec),
        _Field(nameCtrl, 'e.g. Ahmed Hassan', accent: accent),
        const SizedBox(height: 14),
        _Label('Email Address', sec),
        _Field(emailCtrl, 'e.g. you@email.com', type: TextInputType.emailAddress, accent: accent),
        const SizedBox(height: 14),
        _Label('Phone Number', sec),
        _Field(phoneCtrl, 'e.g. 615123456', type: TextInputType.phone, accent: accent),
        const SizedBox(height: 4),
        Text('Enter without country code (61XXXXXXX)', style: TextStyle(color: sec, fontSize: 11)),
        const SizedBox(height: 14),
        _Label('Password', sec),
        TextField(
          controller: passCtrl,
          obscureText: obscure,
          style: TextStyle(color: isDark ? VC.text : const Color(0xFF1A2340)),
          decoration: _inputDec(context, '8+ characters', accent: accent, suffixIcon: IconButton(
            icon: Icon(obscure ? Icons.visibility_rounded : Icons.visibility_off_rounded,
                color: isDark ? VC.textMuted : Colors.grey, size: 20),
            onPressed: onToggleObscure,
          )),
        ),
        const SizedBox(height: 28),

        SizedBox(
          width: double.infinity, height: 54,
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: accent,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            ),
            onPressed: onNext,
            child: const Text('Continue', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
          ),
        ),
      ]),
    );
  }
}

// ─── Step 2: Business / Agent info ───────────────────────────────────────────

class _StepBusiness extends StatelessWidget {
  final _RoleDef role;
  final List<Map> districts;
  final int? districtId;
  final TextEditingController storeNameCtrl, descCtrl, addrCtrl;
  final File? licenseFile;
  final String? error;
  final bool loading;
  final void Function(int?) onDistrictChanged;
  final VoidCallback onPickLicense;
  final VoidCallback onSubmit;

  const _StepBusiness({
    super.key, required this.role, required this.districts,
    required this.districtId, required this.storeNameCtrl,
    required this.descCtrl, required this.addrCtrl,
    this.licenseFile, this.error, required this.loading,
    required this.onDistrictChanged, required this.onPickLicense,
    required this.onSubmit,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final txt    = isDark ? VC.text : const Color(0xFF1A2340);
    final sec    = isDark ? VC.textSec : const Color(0xFF5A6B82);
    final card   = isDark ? VC.navyCard : VC.lightSurface;
    final accent = role.color;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(color: accent.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(role.icon, color: accent, size: 16),
            const SizedBox(width: 6),
            Text(role.label, style: TextStyle(color: accent, fontWeight: FontWeight.w700, fontSize: 13)),
          ]),
        ),
        const SizedBox(height: 20),
        Text(role.isAgent ? 'Agent Info' : 'Store Info', style: TextStyle(color: txt, fontSize: 20, fontWeight: FontWeight.w900)),
        const SizedBox(height: 20),

        if (error != null) ...[_ErrorBox(error!), const SizedBox(height: 14)],

        // District
        _Label('District', sec),
        Container(
          decoration: BoxDecoration(
            color: isDark ? VC.navyCard : VC.lightSurface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: isDark ? const Color(0xFF2D3A55) : const Color(0xFFDDE3EE)),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<int>(
              value: districtId,
              isExpanded: true,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              hint: Text('Select district', style: TextStyle(color: sec)),
              dropdownColor: card,
              style: TextStyle(color: txt, fontSize: 14),
              items: districts.map((d) => DropdownMenuItem<int>(
                value: d['id'] as int,
                child: Text(d['name'] as String),
              )).toList(),
              onChanged: onDistrictChanged,
            ),
          ),
        ),

        if (!role.isAgent) ...[
          const SizedBox(height: 14),
          _Label('Store Name', sec),
          _Field(storeNameCtrl, 'e.g. My Restaurant', accent: accent),
          const SizedBox(height: 14),
          _Label('Description (optional)', sec),
          _Field(descCtrl, 'Brief about your store...', maxLines: 3, accent: accent),
          const SizedBox(height: 14),
          _Label('Address (optional)', sec),
          _Field(addrCtrl, 'Street, building...', accent: accent),
          const SizedBox(height: 14),

          // Business license
          _Label('Business License / Permit', sec),
          GestureDetector(
            onTap: onPickLicense,
            child: Container(
              height: 100,
              decoration: BoxDecoration(
                color: card,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: licenseFile != null ? accent : (isDark ? const Color(0xFF2D3A55) : const Color(0xFFDDE3EE)),
                  width: licenseFile != null ? 2 : 1,
                ),
              ),
              child: licenseFile != null
                  ? Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.check_circle_rounded, color: accent, size: 24),
                      const SizedBox(width: 8),
                      Text('License attached', style: TextStyle(color: accent, fontWeight: FontWeight.w700)),
                    ])
                  : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.upload_file_rounded, color: sec, size: 28),
                      const SizedBox(height: 6),
                      Text('Tap to upload license (JPG/PNG/PDF)', style: TextStyle(color: sec, fontSize: 12)),
                    ]),
            ),
          ),
        ] else ...[
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: accent.withValues(alpha: 0.2)),
            ),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(Icons.info_outline_rounded, color: accent, size: 18),
              const SizedBox(width: 10),
              Expanded(child: Text(
                'As an eRent Agent you will earn 10% commission on brokerage fees from each successful rental booking.',
                style: TextStyle(color: txt, fontSize: 12, height: 1.5),
              )),
            ]),
          ),
        ],

        const SizedBox(height: 32),
        SizedBox(
          width: double.infinity, height: 54,
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: accent,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            ),
            onPressed: loading ? null : onSubmit,
            child: loading
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                : const Text('Submit Application', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
          ),
        ),
        const SizedBox(height: 24),
      ]),
    );
  }
}

// ─── Shared helpers ───────────────────────────────────────────────────────────

class _ErrorBox extends StatelessWidget {
  final String msg;
  const _ErrorBox(this.msg);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(
      color: VC.redDim,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: VC.red.withValues(alpha: 0.3)),
    ),
    child: Row(children: [
      const Icon(Icons.error_outline_rounded, color: VC.red, size: 18),
      const SizedBox(width: 10),
      Expanded(child: Text(msg, style: const TextStyle(color: VC.red, fontSize: 13))),
    ]),
  );
}

class _Label extends StatelessWidget {
  final String text;
  final Color color;
  const _Label(this.text, this.color);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
  );
}

class _Field extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final TextInputType? type;
  final int maxLines;
  final Color accent;
  const _Field(this.ctrl, this.hint, {this.type, this.maxLines = 1, required this.accent});

  @override
  Widget build(BuildContext context) => TextField(
    controller: ctrl,
    keyboardType: type,
    maxLines: maxLines,
    style: TextStyle(color: Theme.of(context).brightness == Brightness.dark ? VC.text : const Color(0xFF1A2340)),
    decoration: _inputDec(context, hint, accent: accent),
  );
}

InputDecoration _inputDec(BuildContext context, String hint, {Color? accent, Widget? suffixIcon}) {
  final isDark = Theme.of(context).brightness == Brightness.dark;
  return InputDecoration(
    hintText: hint,
    hintStyle: TextStyle(color: isDark ? VC.textMuted : Colors.grey),
    suffixIcon: suffixIcon,
    filled: true,
    fillColor: isDark ? VC.navyCard : VC.lightSurface,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: isDark ? const Color(0xFF2D3A55) : const Color(0xFFDDE3EE))),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: isDark ? const Color(0xFF2D3A55) : const Color(0xFFDDE3EE))),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: accent ?? VC.orange, width: 1.5)),
  );
}
