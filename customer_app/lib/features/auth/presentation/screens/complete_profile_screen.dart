import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/phone_input_field.dart';
import '../../data/models/district_model.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/district_repository.dart';

// ignore_for_file: unused_import

final _districtsProvider2 = FutureProvider<List<DistrictModel>>((ref) {
  return DistrictRepository().getDistricts();
});

class CompleteProfileScreen extends ConsumerStatefulWidget {
  const CompleteProfileScreen({super.key});

  @override
  ConsumerState<CompleteProfileScreen> createState() => _CompleteProfileScreenState();
}

class _CompleteProfileScreenState extends ConsumerState<CompleteProfileScreen> {
  final _phoneCtrl  = TextEditingController();
  CountryCode _country = const CountryCode(name: 'Somalia', dialCode: '+252', flag: '🇸🇴', iso: 'SO');
  DistrictModel? _district;
  bool _loading     = false;
  String? _error;

  String get _fullPhone => '${_country.dialCode}${_phoneCtrl.text.trim()}';

  Future<void> _submit() async {
    if (_phoneCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Please enter your phone number');
      return;
    }
    if (_district == null) {
      setState(() => _error = 'Please select your district');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      await AuthRepository().completeProfile(
        phone: _fullPhone,
        districtId: _district!.id,
      );
      if (mounted) context.go('/home');
    } catch (e) {
      if (mounted) setState(() { _error = e.toString().replaceFirst('Exception: ', ''); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c       = context.colors;
    final distAsync = ref.watch(_districtsProvider2);

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SizedBox(height: 24),

            // Header
            Center(
              child: Container(
                width: 72, height: 72,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.person_add_rounded, color: AppColors.primary, size: 36),
              ),
            ),
            const SizedBox(height: 20),
            Center(
              child: Text('Complete Your Profile',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: c.navyText)),
            ),
            const SizedBox(height: 8),
            Center(
              child: Text('Please add your phone number and district\nto continue using eSahlan.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: c.mutedText, height: 1.5)),
            ),
            const SizedBox(height: 32),

            // Phone
            Text('Phone Number', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: c.navyText)),
            const SizedBox(height: 8),
            PhoneInputField(
              controller: _phoneCtrl,
              initialCountry: _country,
              onCountryChanged: (v) => setState(() => _country = v),
            ),
            const SizedBox(height: 20),

            // District
            Text('Your District', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: c.navyText)),
            const SizedBox(height: 8),
            distAsync.when(
              loading: () => const LinearProgressIndicator(minHeight: 2),
              error: (e, _) => Text('Failed to load districts', style: TextStyle(color: c.mutedText, fontSize: 12)),
              data: (districts) => Container(
                height: 48,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: c.inputFill,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: c.borderColor),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<DistrictModel>(
                    value: _district,
                    isExpanded: true,
                    hint: Text('Select district', style: TextStyle(color: c.mutedText, fontSize: 14)),
                    items: districts.map((d) => DropdownMenuItem(
                      value: d,
                      child: Text(d.name, style: TextStyle(color: c.navyText, fontSize: 14)),
                    )).toList(),
                    onChanged: (v) => setState(() => _district = v),
                  ),
                ),
              ),
            ),

            if (_error != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(_error!, style: const TextStyle(color: AppColors.error, fontSize: 13)),
              ),
            ],

            const SizedBox(height: 32),

            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: _loading ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _loading
                    ? const SizedBox(width: 22, height: 22,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                    : const Text('Continue', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Colors.white)),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}
