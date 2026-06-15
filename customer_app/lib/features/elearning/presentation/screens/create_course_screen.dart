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
  final _titleCtrl = TextEditingController();
  final _priceCtrl = TextEditingController();

  int? _categoryId;
  bool _isFree = false;
  MultipartFile? _thumb;
  String? _thumbName;
  bool _submitting = false;

  @override
  void dispose() {
    _titleCtrl.dispose();
    _priceCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickThumb() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f != null) {
      final bytes = await f.readAsBytes();
      setState(() {
        _thumb = MultipartFile.fromBytes(bytes, filename: f.name);
        _thumbName = f.name;
      });
    }
  }

  Future<void> _submit() async {
    final title = _titleCtrl.text.trim();
    if (title.isEmpty) {
      _snack('Course title is required');
      return;
    }
    if (_categoryId == null) {
      _snack('Please select a category');
      return;
    }
    if (!_isFree) {
      final p = double.tryParse(_priceCtrl.text.trim());
      if (p == null || p <= 0) {
        _snack('Enter a valid price');
        return;
      }
    }
    setState(() => _submitting = true);
    try {
      final svc = ref.read(elearningServiceProvider);
      final res = await svc.createCourse(
        title: title,
        subtitle: '',
        description: '',
        categoryId: _categoryId!,
        level: 'all',
        language: 'so',
        price: _isFree ? 0 : (double.tryParse(_priceCtrl.text.trim()) ?? 0),
        isFree: _isFree,
        learningOutcomes: [],
        requirements: [],
        thumbnail: _thumb,
      );
      final courseId = (res['data'] as Map<String, dynamic>)['id'] as int;
      ref.invalidate(instructorCoursesProvider);
      if (!mounted) return;
      context.pushReplacement(
        '/elearning/instructor/course-builder/$courseId',
        extra: title,
      );
    } on DioException catch (e) {
      _snack(e.response?.data?['message']?.toString() ?? 'Could not create course.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _snack(String msg) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));

  @override
  Widget build(BuildContext context) {
    final categoriesAsync = ref.watch(elearningCategoriesProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('New Course',
            style: TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.secondary, Color(0xFF3D2B8E)],
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(Icons.rocket_launch_rounded, color: Colors.white70, size: 28),
              SizedBox(height: 10),
              Text('Quick Start',
                  style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
              SizedBox(height: 4),
              Text('3 xog oo kaliya — goor dambe wax badan ku dar',
                  style: TextStyle(color: Colors.white70, fontSize: 13)),
            ]),
          ),
          const SizedBox(height: 24),

          // Thumbnail (optional)
          GestureDetector(
            onTap: _pickThumb,
            child: Container(
              height: 130,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: _thumb != null ? AppColors.primary : Colors.grey[300]!,
                  width: _thumb != null ? 2 : 1,
                ),
              ),
              child: _thumb != null
                  ? Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Icon(Icons.check_circle_rounded, color: Colors.green, size: 30),
                      const SizedBox(width: 10),
                      Flexible(
                        child: Text(_thumbName!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w600)),
                      ),
                    ])
                  : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.add_photo_alternate_rounded,
                          color: Colors.grey[400], size: 36),
                      const SizedBox(height: 8),
                      Text('Sawir ku dar (ikhtiyaari)',
                          style: TextStyle(color: Colors.grey[500], fontSize: 13)),
                    ]),
            ),
          ),
          const SizedBox(height: 20),

          // 1. Title
          _Step(number: '1', label: 'Magaca course-ka *'),
          const SizedBox(height: 8),
          TextField(
            controller: _titleCtrl,
            decoration: _inputDeco('Tusaale: Complete Flutter Development'),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 20),

          // 2. Category
          _Step(number: '2', label: 'Category *'),
          const SizedBox(height: 8),
          categoriesAsync.when(
            loading: () => const LinearProgressIndicator(color: AppColors.primary),
            error: (_, __) => const Text('Category load failed'),
            data: (cats) => Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey[200]!),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<int>(
                  isExpanded: true,
                  value: _categoryId,
                  hint: const Text('Dooro category'),
                  items: cats
                      .map((c) => DropdownMenuItem(value: c.id, child: Text(c.name)))
                      .toList(),
                  onChanged: (v) => setState(() => _categoryId = v),
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // 3. Lacag
          _Step(number: '3', label: 'Lacagta'),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.grey[200]!),
            ),
            child: SwitchListTile(
              contentPadding: EdgeInsets.zero,
              activeColor: AppColors.primary,
              title: const Text('Bilaash (Free)',
                  style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.secondary)),
              value: _isFree,
              onChanged: (v) => setState(() => _isFree = v),
            ),
          ),
          if (!_isFree) ...[
            const SizedBox(height: 10),
            TextField(
              controller: _priceCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: _inputDeco('Qiimaha (\$) — tusaale: 19.99'),
            ),
          ],
          const SizedBox(height: 32),

          // Submit
          FilledButton.icon(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.primary,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            onPressed: _submitting ? null : _submit,
            icon: _submitting
                ? const SizedBox(
                    width: 18, height: 18,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Icon(Icons.arrow_forward_rounded),
            label: Text(
              _submitting ? 'Creating…' : 'Create & Add Lessons',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(height: 16),
          Center(
            child: Text(
              'Details (subtitle, description, outcomes…)\nwaxaad ku dari kartaa builder-ka kadib',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Colors.grey[400], height: 1.5),
            ),
          ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  InputDecoration _inputDeco(String hint) => InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: Colors.grey[200]!)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: Colors.grey[200]!)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.primary, width: 1.5)),
      );
}

class _Step extends StatelessWidget {
  final String number, label;
  const _Step({required this.number, required this.label});

  @override
  Widget build(BuildContext context) => Row(children: [
        Container(
          width: 24, height: 24,
          decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
          child: Center(
            child: Text(number,
                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w800)),
          ),
        ),
        const SizedBox(width: 8),
        Text(label,
            style: const TextStyle(
                fontWeight: FontWeight.w700, color: AppColors.secondary, fontSize: 14)),
      ]);
}
