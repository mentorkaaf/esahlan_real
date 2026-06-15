import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/elearning_provider.dart';

class CreateCourseScreen extends ConsumerStatefulWidget {
  const CreateCourseScreen({super.key});

  @override
  ConsumerState<CreateCourseScreen> createState() => _CreateCourseScreenState();
}

class _CreateCourseScreenState extends ConsumerState<CreateCourseScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _subtitleCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _priceCtrl = TextEditingController();
  final _outcomesCtrl = TextEditingController();
  final _requirementsCtrl = TextEditingController();

  int? _categoryId;
  String _level = 'all';
  String _language = 'so';
  bool _isFree = false;
  MultipartFile? _thumb;
  String? _thumbName;
  bool _submitting = false;

  @override
  void dispose() {
    _titleCtrl.dispose();
    _subtitleCtrl.dispose();
    _descCtrl.dispose();
    _priceCtrl.dispose();
    _outcomesCtrl.dispose();
    _requirementsCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickThumb() async {
    final picker = ImagePicker();
    final f = await picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f != null) {
      final bytes = await f.readAsBytes();
      setState(() {
        _thumb = MultipartFile.fromBytes(bytes, filename: f.name);
        _thumbName = f.name;
      });
    }
  }

  List<String> _splitLines(String s) =>
      s.split('\n').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select a category')));
      return;
    }
    setState(() => _submitting = true);
    try {
      final svc = ref.read(elearningServiceProvider);
      final res = await svc.createCourse(
        title: _titleCtrl.text.trim(),
        subtitle: _subtitleCtrl.text.trim(),
        description: _descCtrl.text.trim(),
        categoryId: _categoryId!,
        level: _level,
        language: _language,
        price: _isFree ? 0 : (double.tryParse(_priceCtrl.text.trim()) ?? 0),
        isFree: _isFree,
        learningOutcomes: _splitLines(_outcomesCtrl.text),
        requirements: _splitLines(_requirementsCtrl.text),
        thumbnail: _thumb,
      );
      final data = res['data'] as Map<String, dynamic>;
      final courseId = data['id'] as int;
      ref.invalidate(instructorCoursesProvider);
      if (!mounted) return;
      // Go straight to the content builder
      context.pushReplacement('/elearning/instructor/course-builder/$courseId', extra: _titleCtrl.text.trim());
    } on DioException catch (e) {
      final msg = e.response?.data?['message']?.toString() ?? 'Could not create course.';
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categoriesAsync = ref.watch(elearningCategoriesProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('Create Course', style: TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Thumbnail
            GestureDetector(
              onTap: _pickThumb,
              child: Container(
                height: 160,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.grey[300]!, style: BorderStyle.solid),
                ),
                child: _thumbName != null
                    ? Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const Icon(Icons.check_circle, color: Colors.green, size: 40),
                        const SizedBox(height: 8),
                        Padding(padding: const EdgeInsets.symmetric(horizontal: 20), child: Text(_thumbName!, maxLines: 1, overflow: TextOverflow.ellipsis)),
                      ])
                    : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const Icon(Icons.add_photo_alternate_rounded, color: AppColors.primary, size: 40),
                        const SizedBox(height: 8),
                        Text('Add course thumbnail', style: TextStyle(color: Colors.grey[600])),
                      ]),
              ),
            ),
            const SizedBox(height: 20),

            _label('Course Title *'),
            _field(_titleCtrl, 'e.g. Complete Flutter Development',
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null),
            const SizedBox(height: 16),

            _label('Subtitle'),
            _field(_subtitleCtrl, 'Short tagline for the course'),
            const SizedBox(height: 16),

            _label('Description *'),
            _field(_descCtrl, 'What will students learn in this course?', maxLines: 4,
                validator: (v) => (v == null || v.trim().length < 20) ? 'At least 20 characters' : null),
            const SizedBox(height: 16),

            _label('Category *'),
            categoriesAsync.when(
              loading: () => const LinearProgressIndicator(),
              error: (e, _) => const Text('Failed to load categories'),
              data: (cats) => Container(
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey[200]!)),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    isExpanded: true,
                    value: _categoryId,
                    hint: const Text('Select category'),
                    items: cats.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                    onChanged: (v) => setState(() => _categoryId = v),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),

            Row(children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Level'),
                _dropdown<String>(_level, const {
                  'all': 'All Levels', 'beginner': 'Beginner', 'intermediate': 'Intermediate', 'advanced': 'Advanced',
                }, (v) => setState(() => _level = v!)),
              ])),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Language'),
                _dropdown<String>(_language, const {'so': 'Somali', 'en': 'English', 'ar': 'Arabic'},
                    (v) => setState(() => _language = v!)),
              ])),
            ]),
            const SizedBox(height: 16),

            // Free toggle + price
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey[200]!)),
              child: SwitchListTile(
                contentPadding: EdgeInsets.zero,
                activeColor: AppColors.primary,
                title: const Text('Free Course', style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.secondary)),
                value: _isFree,
                onChanged: (v) => setState(() => _isFree = v),
              ),
            ),
            if (!_isFree) ...[
              const SizedBox(height: 16),
              _label('Price (\$) *'),
              _field(_priceCtrl, 'e.g. 19.99', keyboardType: TextInputType.number,
                  validator: (v) => (!_isFree && (v == null || double.tryParse(v.trim()) == null)) ? 'Enter a valid price' : null),
            ],
            const SizedBox(height: 16),

            _label('Learning Outcomes (one per line)'),
            _field(_outcomesCtrl, 'Build real apps\nMaster state management\n...', maxLines: 3),
            const SizedBox(height: 16),

            _label('Requirements (one per line)'),
            _field(_requirementsCtrl, 'A computer\nBasic programming knowledge\n...', maxLines: 3),
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
                  : const Text('Create & Add Content', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            ),
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _label(String t) => Padding(
        padding: const EdgeInsets.only(bottom: 6, left: 2),
        child: Text(t, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.secondary, fontSize: 13)),
      );

  Widget _dropdown<T>(T value, Map<T, String> items, ValueChanged<T?> onChanged) => Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey[200]!)),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<T>(
            isExpanded: true,
            value: value,
            items: items.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
            onChanged: onChanged,
          ),
        ),
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
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[200]!)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[200]!)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary, width: 1.5)),
      ),
    );
  }
}
