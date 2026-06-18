import 'package:dio/dio.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/elearning_provider.dart';

// ─── Constants ────────────────────────────────────────────────────────────────
const _navy   = AppColors.secondary;
const _orange = AppColors.primary;
const _bg     = Color(0xFFF5F6FA);

// ─── Wizard steps ─────────────────────────────────────────────────────────────
enum _Step { basics, details, pricing }

class CreateCourseScreen extends ConsumerStatefulWidget {
  const CreateCourseScreen({super.key});

  @override
  ConsumerState<CreateCourseScreen> createState() => _CreateCourseScreenState();
}

class _CreateCourseScreenState extends ConsumerState<CreateCourseScreen> {
  final _pageCtrl = PageController();
  _Step _step = _Step.basics;

  // Step 1 – Basics
  final _titleCtrl        = TextEditingController();
  final _trailerUrlCtrl   = TextEditingController();
  int? _categoryId;
  MultipartFile? _thumb;
  String?        _thumbPath;
  String?        _thumbName;
  // Trailer video
  XFile?         _trailerFile;
  MultipartFile? _trailerMultipart;

  // Step 2 – Details
  final _subtitleCtrl = TextEditingController();
  final _descCtrl     = TextEditingController();
  String _level    = 'all';
  String _language = 'so';

  // Step 3 – Pricing
  bool _isFree = true;
  final _priceCtrl = TextEditingController();

  bool _submitting = false;

  @override
  void dispose() {
    _pageCtrl.dispose();
    _titleCtrl.dispose();
    _trailerUrlCtrl.dispose();
    _subtitleCtrl.dispose();
    _descCtrl.dispose();
    _priceCtrl.dispose();
    super.dispose();
  }

  // ── Navigation ──────────────────────────────────────────────────────────────

  void _next() {
    if (_step == _Step.basics) {
      if (_titleCtrl.text.trim().isEmpty) {
        _snack('Course title is required'); return;
      }
      if (_categoryId == null) {
        _snack('Please select a category'); return;
      }
      _goTo(_Step.details);
    } else if (_step == _Step.details) {
      _goTo(_Step.pricing);
    } else {
      _submit();
    }
  }

  void _back() {
    if (_step == _Step.details) _goTo(_Step.basics);
    else if (_step == _Step.pricing) _goTo(_Step.details);
    else context.pop();
  }

  void _goTo(_Step s) {
    setState(() => _step = s);
    _pageCtrl.animateToPage(
      s.index,
      duration: const Duration(milliseconds: 350),
      curve: Curves.easeInOut,
    );
  }

  // ── Pickers ─────────────────────────────────────────────────────────────────

  Future<void> _pickThumb() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null) return;
    final bytes = await f.readAsBytes();
    setState(() {
      _thumb     = MultipartFile.fromBytes(bytes, filename: f.name);
      _thumbPath = f.path;
      _thumbName = f.name;
    });
  }

  Future<void> _pickTrailerVideo() async {
    final f = await ImagePicker().pickVideo(source: ImageSource.gallery);
    if (f == null) return;
    final mp = await MultipartFile.fromFile(f.path, filename: f.name);
    setState(() {
      _trailerFile       = f;
      _trailerMultipart  = mp;
      _trailerUrlCtrl.clear(); // clear URL when file picked
    });
  }

  // ── Submit ──────────────────────────────────────────────────────────────────

  Future<void> _submit() async {
    if (!_isFree) {
      final p = double.tryParse(_priceCtrl.text.trim());
      if (p == null || p <= 0) { _snack('Enter a valid price'); return; }
    }
    setState(() => _submitting = true);
    try {
      final svc = ref.read(elearningServiceProvider);
      final res = await svc.createCourse(
        title:            _titleCtrl.text.trim(),
        subtitle:         _subtitleCtrl.text.trim(),
        description:      _descCtrl.text.trim(),
        categoryId:       _categoryId!,
        level:            _level,
        language:         _language,
        price:            _isFree ? 0 : (double.tryParse(_priceCtrl.text.trim()) ?? 0),
        isFree:           _isFree,
        thumbnail:        _thumb,
        trailerVideoUrl:  _trailerUrlCtrl.text.trim().isNotEmpty
                              ? _trailerUrlCtrl.text.trim() : null,
        trailerVideoFile: _trailerMultipart,
      );
      final courseId = (res['data'] as Map<String, dynamic>)['id'] as int;
      ref.invalidate(instructorCoursesProvider);
      if (!mounted) return;
      context.pushReplacement(
        '/elearning/instructor/course-builder/$courseId',
        extra: _titleCtrl.text.trim(),
      );
    } on DioException catch (e) {
      _snack(e.response?.data?['message']?.toString() ?? 'Could not create course');
    } catch (e) {
      _snack(e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _snack(String msg) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));

  // ── Build ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy),
          onPressed: _back,
        ),
        title: const Text('Abuur Course',
            style: TextStyle(color: _navy, fontWeight: FontWeight.w800, fontSize: 17)),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: _StepIndicator(current: _step),
        ),
      ),
      body: PageView(
        controller: _pageCtrl,
        physics: const NeverScrollableScrollPhysics(),
        children: [
          _BasicsPage(
            titleCtrl:       _titleCtrl,
            trailerUrlCtrl:  _trailerUrlCtrl,
            categoryId:      _categoryId,
            thumbPath:       _thumbPath,
            thumbName:       _thumbName,
            onPickThumb:     _pickThumb,
            onPickTrailer:   _pickTrailerVideo,
            trailerFileName: _trailerFile?.name,
            onCategory:      (v) => setState(() => _categoryId = v),
          ),
          _DetailsPage(
            subtitleCtrl: _subtitleCtrl,
            descCtrl:     _descCtrl,
            level:        _level,
            language:     _language,
            onLevel:      (v) => setState(() => _level = v),
            onLanguage:   (v) => setState(() => _language = v),
          ),
          _PricingPage(
            isFree:       _isFree,
            priceCtrl:    _priceCtrl,
            onToggle:     (v) => setState(() => _isFree = v),
          ),
        ],
      ),
      bottomNavigationBar: _BottomBar(
        step:        _step,
        submitting:  _submitting,
        onBack:      _back,
        onNext:      _next,
      ),
    );
  }
}

// ─── Step indicator ───────────────────────────────────────────────────────────

class _StepIndicator extends StatelessWidget {
  final _Step current;
  const _StepIndicator({required this.current});

  @override
  Widget build(BuildContext context) {
    final labels = ['Aasaas', 'Faahfaahin', 'Lacag'];
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 12),
      child: Row(
        children: List.generate(3, (i) {
          final done   = i < current.index;
          final active = i == current.index;
          return Expanded(
            child: Row(children: [
              if (i > 0)
                Expanded(child: Container(height: 2,
                    color: done ? _orange : Colors.grey[200])),
              Column(mainAxisSize: MainAxisSize.min, children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  width: active ? 32 : 26, height: active ? 32 : 26,
                  decoration: BoxDecoration(
                    color: done ? _orange : (active ? _navy : Colors.grey[200]),
                    shape: BoxShape.circle,
                  ),
                  child: Center(child: done
                      ? const Icon(Icons.check_rounded, color: Colors.white, size: 14)
                      : Text('${i + 1}',
                          style: TextStyle(
                            color: active ? Colors.white : Colors.grey[500],
                            fontSize: 12, fontWeight: FontWeight.w700))),
                ),
                const SizedBox(height: 3),
                Text(labels[i],
                    style: TextStyle(
                      fontSize: 10, fontWeight: FontWeight.w600,
                      color: active ? _navy : (done ? _orange : Colors.grey[400]))),
              ]),
              if (i < 2)
                Expanded(child: Container(height: 2,
                    color: i < current.index ? _orange : Colors.grey[200])),
            ]),
          );
        }),
      ),
    );
  }
}

// ─── Bottom bar ───────────────────────────────────────────────────────────────

class _BottomBar extends StatelessWidget {
  final _Step step;
  final bool submitting;
  final VoidCallback onBack, onNext;
  const _BottomBar({required this.step, required this.submitting,
      required this.onBack, required this.onNext});

  @override
  Widget build(BuildContext context) {
    final isLast = step == _Step.pricing;
    return Container(
      color: Colors.white,
      padding: EdgeInsets.fromLTRB(20, 12, 20,
          12 + MediaQuery.of(context).padding.bottom),
      child: Row(children: [
        if (step != _Step.basics)
          Expanded(
            child: OutlinedButton(
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 15),
                side: const BorderSide(color: _navy),
                foregroundColor: _navy,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
              ),
              onPressed: onBack,
              child: const Text('Dib', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        if (step != _Step.basics) const SizedBox(width: 12),
        Expanded(
          flex: 2,
          child: FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: isLast ? _orange : _navy,
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            onPressed: submitting ? null : onNext,
            child: submitting
                ? const SizedBox(width: 20, height: 20,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text(isLast ? 'Abuur Course' : 'Xiga →',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
          ),
        ),
      ]),
    );
  }
}

// ─── Page 1: Basics ──────────────────────────────────────────────────────────

class _BasicsPage extends ConsumerWidget {
  final TextEditingController titleCtrl;
  final TextEditingController trailerUrlCtrl;
  final int? categoryId;
  final String? thumbPath, thumbName, trailerFileName;
  final VoidCallback onPickThumb, onPickTrailer;
  final ValueChanged<int?> onCategory;
  const _BasicsPage({
    required this.titleCtrl, required this.trailerUrlCtrl,
    required this.categoryId,
    required this.thumbPath, required this.thumbName,
    required this.onPickThumb, required this.onPickTrailer,
    this.trailerFileName,
    required this.onCategory,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catsAsync = ref.watch(elearningCategoriesProvider);
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      children: [
        _SectionHeader(icon: Icons.auto_awesome_rounded,
            title: 'Course-kaaga ku saabsan', subtitle: 'Magac iyo qaybta dooro'),
        const SizedBox(height: 20),

        // Thumbnail picker
        GestureDetector(
          onTap: onPickThumb,
          child: Container(
            height: 150,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: thumbPath != null ? _orange : Colors.grey[300]!,
                width: thumbPath != null ? 2 : 1,
              ),
            ),
            child: thumbPath != null
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(15),
                    child: Stack(fit: StackFit.expand, children: [
                      Image.asset(thumbPath!, fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const SizedBox()),
                      Container(color: Colors.black38),
                      const Center(child: Icon(Icons.edit_rounded,
                          color: Colors.white, size: 32)),
                      Positioned(bottom: 8, left: 0, right: 0,
                        child: Text(thumbName ?? '',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white,
                                fontSize: 11, fontWeight: FontWeight.w600))),
                    ]),
                  )
                : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Container(
                      width: 56, height: 56,
                      decoration: BoxDecoration(
                        color: _orange.withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.add_photo_alternate_rounded,
                          color: _orange, size: 28),
                    ),
                    const SizedBox(height: 10),
                    const Text('Sawirka course-ka ku dar',
                        style: TextStyle(fontWeight: FontWeight.w700,
                            color: _navy, fontSize: 14)),
                    const SizedBox(height: 4),
                    Text('(Ikhtiyaari — PNG, JPG)',
                        style: TextStyle(color: Colors.grey[500], fontSize: 12)),
                  ]),
          ),
        ),
        const SizedBox(height: 20),

        // Title
        _FieldLabel('Magaca Course-ka *'),
        const SizedBox(height: 6),
        _InputField(ctrl: titleCtrl, hint: 'Tusaale: Complete Flutter Course',
            action: TextInputAction.next),
        const SizedBox(height: 20),

        // Category
        _FieldLabel('Qaybta (Category) *'),
        const SizedBox(height: 6),
        catsAsync.when(
          loading: () => const LinearProgressIndicator(color: _orange),
          error: (_, __) => const Text('Category load failed',
              style: TextStyle(color: Colors.red)),
          data: (cats) => _DropField<int>(
            value: categoryId,
            hint: 'Dooro qaybta',
            items: cats.map((c) => DropdownMenuItem(
                value: c.id, child: Text(c.name))).toList(),
            onChanged: onCategory,
          ),
        ),
        const SizedBox(height: 24),

        // Trailer video section
        _SectionHeader(icon: Icons.play_circle_outline_rounded,
            title: 'Trailer Video (Ikhtiyaari)',
            subtitle: 'URL geli ama video soo upload'),
        const SizedBox(height: 12),
        _InputField(
          ctrl: trailerUrlCtrl,
          hint: 'https://youtube.com/watch?v=...',
          action: TextInputAction.done,
        ),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: Divider(color: Colors.grey[300])),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Text('ama', style: TextStyle(color: Colors.grey[500], fontSize: 12)),
          ),
          Expanded(child: Divider(color: Colors.grey[300])),
        ]),
        const SizedBox(height: 10),
        GestureDetector(
          onTap: onPickTrailer,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: trailerFileName != null ? _orange : Colors.grey[300]!,
                width: trailerFileName != null ? 2 : 1,
              ),
            ),
            child: Row(children: [
              Icon(
                trailerFileName != null
                    ? Icons.videocam_rounded
                    : Icons.video_library_outlined,
                color: trailerFileName != null ? _orange : Colors.grey[500],
                size: 22,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  trailerFileName ?? 'Dooro video file-ka...',
                  style: TextStyle(
                    color: trailerFileName != null ? _navy : Colors.grey[500],
                    fontSize: 13,
                    fontWeight: trailerFileName != null
                        ? FontWeight.w600 : FontWeight.normal,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (trailerFileName != null)
                const Icon(Icons.check_circle_rounded, color: _orange, size: 18),
            ]),
          ),
        ),
        const SizedBox(height: 40),
      ],
    );
  }
}

// ─── Page 2: Details ─────────────────────────────────────────────────────────

class _DetailsPage extends StatelessWidget {
  final TextEditingController subtitleCtrl, descCtrl;
  final String level, language;
  final ValueChanged<String> onLevel, onLanguage;
  const _DetailsPage({
    required this.subtitleCtrl, required this.descCtrl,
    required this.level, required this.language,
    required this.onLevel, required this.onLanguage,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      children: [
        _SectionHeader(icon: Icons.tune_rounded,
            title: 'Faahfaahinta', subtitle: 'Heerka iyo luqadda course-ka'),
        const SizedBox(height: 20),

        // Level chips
        _FieldLabel('Heerka (Level)'),
        const SizedBox(height: 8),
        _ChipGroup<String>(
          options: const {'all': 'Dhammaan', 'beginner': 'Bilowga', 'intermediate': 'Dhexe', 'advanced': 'Sare'},
          selected: level,
          onSelected: onLevel,
        ),
        const SizedBox(height: 20),

        // Language chips
        _FieldLabel('Luqadda'),
        const SizedBox(height: 8),
        _ChipGroup<String>(
          options: const {'so': '🇸🇴 Somali', 'en': '🇬🇧 English', 'ar': '🇸🇦 Arabic'},
          selected: language,
          onSelected: onLanguage,
        ),
        const SizedBox(height: 20),

        // Subtitle (optional)
        _FieldLabel('Subtitle (Ikhtiyaari)'),
        const SizedBox(height: 6),
        _InputField(ctrl: subtitleCtrl,
            hint: 'Eray gaaban oo qeexaya — tusaale: Learn from scratch'),
        const SizedBox(height: 20),

        // Description (optional)
        _FieldLabel('Sharaxaad (Ikhtiyaari)'),
        const SizedBox(height: 6),
        _InputField(ctrl: descCtrl,
            hint: 'Waxa ardayda ay baranayaan, sababta ay u qaadan karaan...', maxLines: 5),
        const SizedBox(height: 40),
      ],
    );
  }
}

// ─── Page 3: Pricing ─────────────────────────────────────────────────────────

class _PricingPage extends StatelessWidget {
  final bool isFree;
  final TextEditingController priceCtrl;
  final ValueChanged<bool> onToggle;
  const _PricingPage({required this.isFree, required this.priceCtrl, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      children: [
        _SectionHeader(icon: Icons.sell_rounded,
            title: 'Qiimaha', subtitle: 'Bilaash mise lacag bixinta'),
        const SizedBox(height: 24),

        // Free option
        _PriceOption(
          selected: isFree,
          icon: Icons.card_giftcard_rounded,
          iconColor: Colors.green,
          title: 'Bilaash (Free)',
          subtitle: 'Ardayda oo dhan course-ka helaan karaan',
          onTap: () => onToggle(true),
        ),
        const SizedBox(height: 12),

        // Paid option
        _PriceOption(
          selected: !isFree,
          icon: Icons.monetization_on_rounded,
          iconColor: _orange,
          title: 'Lacag leh (Paid)',
          subtitle: 'Qiime ku kobi karo aqoontaada',
          onTap: () => onToggle(false),
        ),

        if (!isFree) ...[
          const SizedBox(height: 20),
          _FieldLabel('Qiimaha (\$)'),
          const SizedBox(height: 6),
          _InputField(ctrl: priceCtrl,
              hint: 'Tusaale: 19.99',
              keyboardType: const TextInputType.numberWithOptions(decimal: true)),
        ],
        const SizedBox(height: 32),

        // Final note
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: _navy.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Icon(Icons.info_outline_rounded, color: _navy, size: 18),
            const SizedBox(width: 10),
            const Expanded(
              child: Text(
                'Course-kaaga waxaa lagu abuurayaa draft ahaan. Lessions ku dar ka dib, admin-ka u dir review.',
                style: TextStyle(fontSize: 12, color: _navy, height: 1.5),
              ),
            ),
          ]),
        ),
        const SizedBox(height: 40),
      ],
    );
  }
}

class _PriceOption extends StatelessWidget {
  final bool selected;
  final IconData icon;
  final Color iconColor;
  final String title, subtitle;
  final VoidCallback onTap;
  const _PriceOption({required this.selected, required this.icon,
      required this.iconColor, required this.title,
      required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? _orange : Colors.grey[200]!,
            width: selected ? 2 : 1,
          ),
          boxShadow: selected ? [BoxShadow(
            color: _orange.withValues(alpha: 0.15), blurRadius: 8, offset: const Offset(0, 2))] : null,
        ),
        child: Row(children: [
          Container(
            width: 46, height: 46,
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: Icon(icon, color: iconColor, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w800,
                fontSize: 15, color: _navy)),
            const SizedBox(height: 2),
            Text(subtitle, style: TextStyle(color: Colors.grey[500], fontSize: 12)),
          ])),
          if (selected)
            const Icon(Icons.check_circle_rounded, color: _orange),
        ]),
      ),
    );
  }
}

// ─── Shared widgets ───────────────────────────────────────────────────────────

class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  const _SectionHeader({required this.icon, required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) => Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Container(
      width: 44, height: 44,
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [_navy, Color(0xFF3D2B8E)]),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Icon(icon, color: Colors.white, size: 22),
    ),
    const SizedBox(width: 12),
    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.w900,
          fontSize: 17, color: _navy)),
      Text(subtitle, style: TextStyle(color: Colors.grey[500], fontSize: 12)),
    ]),
  ]);
}

class _FieldLabel extends StatelessWidget {
  final String text;
  const _FieldLabel(this.text);
  @override
  Widget build(BuildContext context) => Text(text,
      style: const TextStyle(fontWeight: FontWeight.w700,
          fontSize: 13, color: _navy));
}

class _InputField extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final int maxLines;
  final TextInputType? keyboardType;
  final TextInputAction? action;
  const _InputField({required this.ctrl, required this.hint,
      this.maxLines = 1, this.keyboardType, this.action});

  @override
  Widget build(BuildContext context) => TextField(
    controller: ctrl,
    maxLines: maxLines,
    keyboardType: keyboardType,
    textInputAction: action,
    decoration: InputDecoration(
      hintText: hint,
      hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
      filled: true, fillColor: context.colors.cardBg,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.grey[200]!)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.grey[200]!)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: _orange, width: 1.5)),
    ),
  );
}

class _DropField<T> extends StatelessWidget {
  final T? value;
  final String? hint;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;
  const _DropField({required this.value, required this.items,
      required this.onChanged, this.hint});

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey[200]!)),
    padding: const EdgeInsets.symmetric(horizontal: 14),
    child: DropdownButtonHideUnderline(
      child: DropdownButton<T>(
        isExpanded: true, value: value,
        hint: hint != null ? Text(hint!, style: TextStyle(color: Colors.grey[400])) : null,
        items: items, onChanged: onChanged,
      ),
    ),
  );
}

class _ChipGroup<T> extends StatelessWidget {
  final Map<T, String> options;
  final T selected;
  final ValueChanged<T> onSelected;
  const _ChipGroup({required this.options, required this.selected, required this.onSelected});

  @override
  Widget build(BuildContext context) => Wrap(spacing: 8, runSpacing: 8, children: [
    for (final e in options.entries)
      GestureDetector(
        onTap: () => onSelected(e.key),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: selected == e.key ? _navy : Colors.white,
            borderRadius: BorderRadius.circular(30),
            border: Border.all(
              color: selected == e.key ? _navy : Colors.grey[300]!),
          ),
          child: Text(e.value,
              style: TextStyle(
                color: selected == e.key ? Colors.white : Colors.grey[600],
                fontWeight: selected == e.key ? FontWeight.w700 : FontWeight.normal,
                fontSize: 13,
              )),
        ),
      ),
  ]);
}
