import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../widgets/country_city_picker.dart';
import 'community_shell.dart';

class CommunityOnboardingScreen extends ConsumerStatefulWidget {
  final VoidCallback onComplete;
  const CommunityOnboardingScreen({super.key, required this.onComplete});
  @override
  ConsumerState<CommunityOnboardingScreen> createState() => _State();
}

class _State extends ConsumerState<CommunityOnboardingScreen> {
  int _step = 0;
  String? _country;
  String _city = '';
  String? _gender;
  DateTime? _dob;
  final Set<String> _interests = {};
  bool _saving = false;

  static const _countries = ['Somalia', 'Kenya', 'Ethiopia', 'Djibouti', 'Uganda', 'Tanzania', 'Saudi Arabia', 'UAE', 'Qatar', 'Turkey', 'UK', 'USA', 'Canada', 'Sweden', 'Norway', 'Finland', 'Other'];

  static const _allInterests = [
    'Technology', 'Business', 'Education', 'Health', 'Sports', 'Fashion',
    'Food & Cooking', 'Travel', 'Music', 'Photography', 'Art & Design',
    'Gaming', 'Fitness', 'Real Estate', 'Cars & Motors', 'News & Politics',
    'Religion', 'Science', 'Entertainment', 'Beauty', 'Parenting',
    'Finance & Investment', 'Agriculture', 'Construction', 'Telecom',
    'E-commerce', 'Marketing', 'Freelancing', 'Coding', 'Startups',
  ];

  Future<void> _save() async {
    if (_country == null || _city.isEmpty || _gender == null || _dob == null || _interests.isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(communityRepoProvider).submitOnboarding({
        'country': _country,
        'city': _city,
        'gender': _gender,
        'date_of_birth': '${_dob!.year}-${_dob!.month.toString().padLeft(2,'0')}-${_dob!.day.toString().padLeft(2,'0')}',
        'interests': _interests.toList(),
      });
      widget.onComplete();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
    } finally { if (mounted) setState(() => _saving = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(child: Column(children: [
        // Progress
        Padding(padding: const EdgeInsets.fromLTRB(20, 16, 20, 0), child: Row(children: [
          Expanded(child: Container(height: 4, decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(2)))),
          const SizedBox(width: 4),
          Expanded(child: Container(height: 4, decoration: BoxDecoration(color: _step >= 1 ? kOrange : const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
          const SizedBox(width: 4),
          Expanded(child: Container(height: 4, decoration: BoxDecoration(color: _step >= 2 ? kOrange : const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
        ])),

        Expanded(child: _step == 0 ? _buildStep1() : _step == 1 ? _buildStep2() : _buildStep3()),

        // Bottom buttons
        Padding(padding: const EdgeInsets.all(20), child: Row(children: [
          if (_step > 0) Expanded(child: OutlinedButton(
            onPressed: () => setState(() => _step--),
            style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            child: const Text('Back', style: TextStyle(fontWeight: FontWeight.w700)),
          )),
          if (_step > 0) const SizedBox(width: 12),
          Expanded(child: ElevatedButton(
            onPressed: _canProceed() ? (_step == 2 ? _save : () => setState(() => _step++)) : null,
            style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            child: _saving
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text(_step == 2 ? 'Get Started' : 'Continue', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          )),
        ])),
      ])),
    );
  }

  bool _canProceed() {
    if (_step == 0) return _country != null && _city.isNotEmpty && _gender != null;
    if (_step == 1) return _dob != null;
    return _interests.length >= 3;
  }

  // Step 1: Country, City, Gender
  Widget _buildStep1() => ListView(padding: const EdgeInsets.all(20), children: [
    const SizedBox(height: 20),
    const Text('Tell us about you', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 26, color: Color(0xFF1A1B2E))),
    const SizedBox(height: 6),
    const Text('This helps us personalize your experience', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 15)),
    const SizedBox(height: 28),

    CountryCityPicker(
      initialCountry: _country,
      initialCity: _city,
      onChanged: (country, code, city) => setState(() { _country = country; _city = city; }),
    ),
    const SizedBox(height: 20),

    const Text('Gender', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF374151))),
    const SizedBox(height: 8),
    Row(children: [
      _genderBtn('male', 'Male', Icons.male_rounded),
      const SizedBox(width: 12),
      _genderBtn('female', 'Female', Icons.female_rounded),
    ]),
  ]);

  Widget _genderBtn(String val, String label, IconData icon) => Expanded(child: GestureDetector(
    onTap: () => setState(() => _gender = val),
    child: AnimatedContainer(duration: const Duration(milliseconds: 200), padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(color: _gender == val ? kOrange.withValues(alpha: 0.1) : const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(14), border: Border.all(color: _gender == val ? kOrange : const Color(0xFFE5E7EB), width: _gender == val ? 2 : 1)),
      child: Column(children: [
        Icon(icon, size: 32, color: _gender == val ? kOrange : const Color(0xFF9CA3AF)),
        const SizedBox(height: 6),
        Text(label, style: TextStyle(fontWeight: FontWeight.w700, color: _gender == val ? kOrange : const Color(0xFF6B7280))),
      ])),
  ));

  // Step 2: Date of Birth
  Widget _buildStep2() => ListView(padding: const EdgeInsets.all(20), children: [
    const SizedBox(height: 20),
    const Text('When is your birthday?', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 26, color: Color(0xFF1A1B2E))),
    const SizedBox(height: 6),
    const Text('We use this to show age-appropriate content', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 15)),
    const SizedBox(height: 28),
    GestureDetector(
      onTap: () async {
        final d = await showDatePicker(context: context, initialDate: DateTime(2000), firstDate: DateTime(1940), lastDate: DateTime.now().subtract(const Duration(days: 365 * 13)));
        if (d != null) setState(() => _dob = d);
      },
      child: Container(padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(16),
          border: Border.all(color: _dob != null ? kOrange : const Color(0xFFE5E7EB), width: _dob != null ? 2 : 1)),
        child: Row(children: [
          Icon(Icons.cake_rounded, size: 28, color: _dob != null ? kOrange : const Color(0xFF9CA3AF)),
          const SizedBox(width: 14),
          Text(_dob != null ? '${_dob!.day}/${_dob!.month}/${_dob!.year}' : 'Select your birthday',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: _dob != null ? const Color(0xFF1A1B2E) : const Color(0xFF9CA3AF))),
        ])),
    ),
  ]);

  // Step 3: Interests
  Widget _buildStep3() => ListView(padding: const EdgeInsets.all(20), children: [
    const SizedBox(height: 20),
    const Text('What are you interested in?', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 26, color: Color(0xFF1A1B2E))),
    const SizedBox(height: 6),
    Text('Choose at least 3 (${_interests.length} selected)', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 15)),
    const SizedBox(height: 28),
    Wrap(spacing: 8, runSpacing: 10, children: _allInterests.map((i) {
      final sel = _interests.contains(i);
      return GestureDetector(
        onTap: () => setState(() { sel ? _interests.remove(i) : _interests.add(i); }),
        child: AnimatedContainer(duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(color: sel ? kOrange : const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(24),
            border: Border.all(color: sel ? kOrange : const Color(0xFFE5E7EB))),
          child: Text(i, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: sel ? Colors.white : const Color(0xFF6B7280)))),
      );
    }).toList()),
  ]);
}
