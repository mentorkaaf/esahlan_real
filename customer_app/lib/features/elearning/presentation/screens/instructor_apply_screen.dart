import 'package:dio/dio.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/elearning_provider.dart';

class InstructorApplyScreen extends ConsumerStatefulWidget {
  const InstructorApplyScreen({super.key});

  @override
  ConsumerState<InstructorApplyScreen> createState() => _InstructorApplyScreenState();
}

class _InstructorApplyScreenState extends ConsumerState<InstructorApplyScreen> {
  final _formKey = GlobalKey<FormState>();
  final _bioCtrl = TextEditingController();
  final _expertiseCtrl = TextEditingController();
  final _qualificationsCtrl = TextEditingController();
  final _experienceCtrl = TextEditingController();

  MultipartFile? _photo;
  String? _photoName;
  bool _submitting = false;

  @override
  void dispose() {
    _bioCtrl.dispose();
    _expertiseCtrl.dispose();
    _qualificationsCtrl.dispose();
    _experienceCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto() async {
    final picker = ImagePicker();
    final f = await picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f != null) {
      final bytes = await f.readAsBytes();
      setState(() {
        _photo = MultipartFile.fromBytes(bytes, filename: f.name);
        _photoName = f.name;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final svc = ref.read(elearningServiceProvider);
      await svc.applyInstructor(
        bio: _bioCtrl.text.trim(),
        expertise: _expertiseCtrl.text.trim(),
        qualifications: _qualificationsCtrl.text.trim(),
        experienceYears: int.tryParse(_experienceCtrl.text.trim()),
        profilePhoto: _photo,
      );
      ref.invalidate(instructorStatusProvider);
      if (!mounted) return;
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.check_circle_rounded, color: Colors.green, size: 64),
              SizedBox(height: 16),
              Text('Application Submitted!',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: context.colors.navyText)),
              const SizedBox(height: 8),
              Text('Admin will review your application within 24 hours.',
                  textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600])),
            ],
          ),
          actions: [
            Center(
              child: FilledButton(
                style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
                onPressed: () {
                  Navigator.pop(context);
                  context.pop();
                },
                child: const Text('Done'),
              ),
            ),
          ],
        ),
      );
    } on DioException catch (e) {
      final msg = e.response?.data?['message']?.toString() ?? 'Something went wrong. Try again.';
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        title: Text('Become an Instructor',
            style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: context.colors.navyText),
          onPressed: () => context.pop(),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Hero
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.secondary, Color(0xFF3D2B8E)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Row(
                children: [
                  const Icon(Icons.cast_for_education_rounded, color: Colors.white, size: 40),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: const [
                        Text('Share your knowledge',
                            style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
                        SizedBox(height: 4),
                        Text('Create courses, reach students, and earn.',
                            style: TextStyle(color: Colors.white70, fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Photo
            Center(
              child: GestureDetector(
                onTap: _pickPhoto,
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 44,
                      backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                      child: _photoName != null
                          ? const Icon(Icons.check_circle, color: Colors.green, size: 40)
                          : const Icon(Icons.add_a_photo_rounded, color: AppColors.primary, size: 32),
                    ),
                    const SizedBox(height: 8),
                    Text(_photoName ?? 'Add profile photo (optional)',
                        style: TextStyle(fontSize: 12, color: Colors.grey[600])),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            _label('Area of Expertise *'),
            _field(_expertiseCtrl, 'e.g. Web Development, Graphic Design',
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null),
            const SizedBox(height: 16),

            _label('Bio *'),
            _field(_bioCtrl, 'Tell students about yourself...', maxLines: 4,
                validator: (v) => (v == null || v.trim().length < 20) ? 'At least 20 characters' : null),
            const SizedBox(height: 16),

            _label('Qualifications'),
            _field(_qualificationsCtrl, 'Degrees, certifications, achievements...', maxLines: 3),
            const SizedBox(height: 16),

            _label('Years of Experience'),
            _field(_experienceCtrl, 'e.g. 5', keyboardType: TextInputType.number),
            const SizedBox(height: 28),

            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : const Text('Submit Application',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            ),
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _label(String t) => Padding(
        padding: const EdgeInsets.only(bottom: 6, left: 2),
        child: Text(t, style: TextStyle(fontWeight: FontWeight.w700, color: context.colors.navyText, fontSize: 13)),
      );

  Widget _field(TextEditingController c, String hint,
      {int maxLines = 1, TextInputType? keyboardType, String? Function(String?)? validator}) {
    return TextFormField(
      controller: c,
      maxLines: maxLines,
      keyboardType: keyboardType,
      validator: validator,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
        filled: true,
        fillColor: context.colors.cardBg,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[200]!)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[200]!)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary, width: 1.5)),
      ),
    );
  }
}
