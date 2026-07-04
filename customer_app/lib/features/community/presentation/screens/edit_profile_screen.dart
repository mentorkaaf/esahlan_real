import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../../data/models/community_models.dart';
import 'community_shell.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  final CommunityUser user;
  const EditProfileScreen({super.key, required this.user});
  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _usernameCtrl;
  late final TextEditingController _bioCtrl;
  late final TextEditingController _websiteCtrl;
  late final TextEditingController _locationCtrl;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final u = widget.user;
    _nameCtrl = TextEditingController(text: u.name);
    _usernameCtrl = TextEditingController(text: u.username ?? '');
    _bioCtrl = TextEditingController(text: u.bio ?? '');
    _websiteCtrl = TextEditingController(text: u.website ?? '');
    _locationCtrl = TextEditingController(text: u.location ?? '');
  }

  @override
  void dispose() { _nameCtrl.dispose(); _usernameCtrl.dispose(); _bioCtrl.dispose(); _websiteCtrl.dispose(); _locationCtrl.dispose(); super.dispose(); }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ref.read(communityRepoProvider).updateProfile({
        'display_name': _nameCtrl.text.trim(),
        'username': _usernameCtrl.text.trim(),
        'bio': _bioCtrl.text.trim(),
        'website': _websiteCtrl.text.trim(),
      });
      ref.invalidate(communityMyProfileProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Profile updated!'), backgroundColor: Color(0xFF10B981)));
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
    } finally { if (mounted) setState(() => _saving = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Edit profile', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
        actions: [
          TextButton(
            onPressed: _saving ? null : _save,
            child: _saving
                ? SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: kOrange))
                : Text('Save', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700, fontSize: 15)),
          ),
        ],
      ),
      body: ListView(padding: EdgeInsets.all(16), children: [
        _field('Display name', _nameCtrl, Icons.person_rounded, 'How others see you'),
        SizedBox(height: 16),
        _field('Username', _usernameCtrl, Icons.alternate_email_rounded, 'Unique @handle'),
        SizedBox(height: 16),
        _field('Bio', _bioCtrl, Icons.info_outline_rounded, 'Tell people about yourself', maxLines: 4, maxLength: 500),
        SizedBox(height: 16),
        _field('Website', _websiteCtrl, Icons.language_rounded, 'https://your-website.com'),
        SizedBox(height: 16),
        _field('Location', _locationCtrl, Icons.location_on_outlined, 'City, Country'),
      ]),
    );
  }

  Widget _field(String label, TextEditingController ctrl, IconData icon, String hint, {int maxLines = 1, int? maxLength}) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.bodyText)),
      SizedBox(height: 6),
      TextField(
        controller: ctrl, maxLines: maxLines, maxLength: maxLength,
        decoration: InputDecoration(
          hintText: hint, hintStyle: TextStyle(color: Color(0xFFD1D5DB), fontSize: 14),
          prefixIcon: Icon(icon, color: kOrange, size: 20),
          filled: true, fillColor: const Color(0xFFF9FAFB),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: kOrange, width: 1.5)),
          contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        ),
      ),
    ]);
  }
}
