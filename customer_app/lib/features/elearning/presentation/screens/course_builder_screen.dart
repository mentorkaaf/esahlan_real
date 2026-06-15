import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/elearning_provider.dart';

// ─── Constants ─────────────────────────────────────────────────────────────────
const _navy   = AppColors.secondary;
const _orange = AppColors.primary;
const _bg     = Color(0xFFF5F6FA);

// ─── Main Screen ───────────────────────────────────────────────────────────────

class CourseBuilderScreen extends ConsumerStatefulWidget {
  final int    courseId;
  final String? courseTitle;
  const CourseBuilderScreen({super.key, required this.courseId, this.courseTitle});

  @override
  ConsumerState<CourseBuilderScreen> createState() => _CourseBuilderScreenState();
}

class _CourseBuilderScreenState extends ConsumerState<CourseBuilderScreen> {
  int get courseId => widget.courseId;

  void _refresh() {
    ref.invalidate(courseStructureProvider(courseId));
    if (mounted) setState(() {});
  }

  // ── Add Section ─────────────────────────────────────────────────────────────

  Future<void> _addSection() async {
    final title = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddSectionSheet(),
    );
    if (title == null || title.trim().isEmpty) return;
    try {
      await ref.read(elearningServiceProvider).addSection(
          courseId: courseId, title: title.trim());
      _refresh();
      if (mounted) _snack('Section ku daratay ✓', Colors.green);
    } on DioException catch (e) {
      if (mounted) _snack(e.response?.data?['message']?.toString() ?? 'Failed to add section', Colors.red);
    } catch (e) {
      if (mounted) _snack(e.toString(), Colors.red);
    }
  }

  // ── Submit for review ───────────────────────────────────────────────────────

  Future<void> _submitForReview() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => _ConfirmSheet(
        icon: Icons.send_rounded,
        iconColor: _navy,
        title: 'Review-ka u dir?',
        body: 'Admin-ka ayaa course-kaaga eegi doona. Markaad u dirto xaalad "Pending" ayuu noqonayaa.',
        confirmLabel: 'U dir',
        confirmColor: _navy,
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(elearningServiceProvider).submitCourseForReview(courseId);
      ref.invalidate(instructorCoursesProvider);
      _refresh();
      if (mounted) _snack('Course review-ka loo diray! ✓', Colors.green);
    } on DioException catch (e) {
      if (mounted) _snack(e.response?.data?['message']?.toString() ?? 'Failed to submit', Colors.red);
    }
  }

  // ── Delete course ───────────────────────────────────────────────────────────

  Future<void> _deleteCourse(String status) async {
    if (status == 'published') {
      _snack('Published course-ka delete gareyn kartid', Colors.red); return;
    }
    final ok = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => _ConfirmSheet(
        icon: Icons.delete_forever_rounded,
        iconColor: Colors.red,
        title: 'Course-ka delete gareeyo?',
        body: 'Tan waa ficil aan dib loo celin karin. Sections iyo lessons-ku dhammaantood tirtirmi doonaan.',
        confirmLabel: 'Delete',
        confirmColor: Colors.red,
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(elearningServiceProvider).deleteCourse(courseId);
      ref.invalidate(instructorCoursesProvider);
      if (mounted) { context.pop(); _snack('Course tirtirtay', Colors.red); }
    } catch (e) {
      if (mounted) _snack(e.toString(), Colors.red);
    }
  }

  // ── Edit details ────────────────────────────────────────────────────────────

  Future<void> _editDetails(Map<String, dynamic> data) async {
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditCourseSheet(
        courseId: courseId,
        data: data,
        onSaved: _refresh,
      ),
    );
  }

  void _snack(String msg, [Color? bg]) =>
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: bg));

  // ── Build ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final structAsync = ref.watch(courseStructureProvider(courseId));

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.courseTitle ?? 'Course Builder',
            maxLines: 1, overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: _navy, fontWeight: FontWeight.w800, fontSize: 17)),
        actions: [
          structAsync.maybeWhen(
            data: (data) => PopupMenuButton<String>(
              icon: const Icon(Icons.more_vert_rounded, color: _navy),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              onSelected: (v) {
                if (v == 'edit')   _editDetails(data);
                if (v == 'delete') _deleteCourse(data['status'] as String? ?? 'draft');
              },
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'edit',
                  child: Row(children: [
                    Icon(Icons.edit_rounded, size: 18, color: _orange),
                    SizedBox(width: 10), Text('Wax ka beddel'),
                  ])),
                const PopupMenuItem(value: 'delete',
                  child: Row(children: [
                    Icon(Icons.delete_rounded, size: 18, color: Colors.red),
                    SizedBox(width: 10),
                    Text('Delete course', style: TextStyle(color: Colors.red)),
                  ])),
              ],
            ),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: structAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: _orange)),
        error:   (e, _) => _ErrorView(
          message: e.toString(),
          onRetry: () => ref.invalidate(courseStructureProvider(courseId)),
        ),
        data: (data) => _BuilderBody(
          courseId:     courseId,
          data:         data,
          onRefresh:    _refresh,
          onAddSection: _addSection,
          onSubmit:     _submitForReview,
        ),
      ),
      bottomNavigationBar: structAsync.maybeWhen(
        data: (data) {
          final status       = data['status'] as String? ?? 'draft';
          final totalLessons = (data['total_lessons'] as num?)?.toInt() ?? 0;
          final canSubmit    = totalLessons > 0 && status == 'draft';
          if (status == 'published') return const SizedBox.shrink();
          return _SubmitBar(canSubmit: canSubmit, onSubmit: _submitForReview);
        },
        orElse: () => const SizedBox.shrink(),
      ),
    );
  }
}

// ─── Builder body ──────────────────────────────────────────────────────────────

class _BuilderBody extends StatelessWidget {
  final int courseId;
  final Map<String, dynamic> data;
  final VoidCallback onRefresh, onAddSection, onSubmit;
  const _BuilderBody({required this.courseId, required this.data,
      required this.onRefresh, required this.onAddSection, required this.onSubmit});

  @override
  Widget build(BuildContext context) {
    final sections     = (data['sections']      as List? ?? []).cast<Map<String, dynamic>>();
    final status       = data['status']          as String? ?? 'draft';
    final totalLessons = (data['total_lessons']  as num?)?.toInt() ?? 0;
    final thumbnail    = data['thumbnail']       as String?;
    final title        = data['title']           as String? ?? '';
    final category     = (data['category']       as Map?)?['name'] as String? ?? '';

    return RefreshIndicator(
      color: _orange,
      onRefresh: () async => onRefresh(),
      child: ListView(
        padding: EdgeInsets.only(
          left: 16, right: 16, top: 16,
          bottom: 120 + MediaQuery.of(context).padding.bottom,
        ),
        children: [
          // Course header card
          _CourseHeaderCard(
              thumbnail: thumbnail, title: title,
              category: category, status: status,
              totalLessons: totalLessons),
          const SizedBox(height: 16),

          // Sections
          if (sections.isEmpty)
            _EmptyContent(onAdd: onAddSection)
          else ...[
            ...sections.asMap().entries.map((e) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _SectionTile(
                index:   e.key,
                section: e.value,
                onChanged: onRefresh,
              ),
            )),
          ],

          // Add Section button
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: _navy,
              side: const BorderSide(color: _navy),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            onPressed: onAddSection,
            icon: const Icon(Icons.add_rounded),
            label: const Text('Section ku dar', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }
}

// ─── Course header card ────────────────────────────────────────────────────────

class _CourseHeaderCard extends StatelessWidget {
  final String? thumbnail;
  final String  title, category, status;
  final int     totalLessons;
  const _CourseHeaderCard({required this.thumbnail, required this.title,
      required this.category, required this.status, required this.totalLessons});

  @override
  Widget build(BuildContext context) {
    final (color, label) = switch (status) {
      'published' => (Colors.green, 'Published'),
      'pending'   => (Colors.orange, 'In Review'),
      'rejected'  => (Colors.red, 'Rejected'),
      _           => (Colors.blueGrey, 'Draft'),
    };
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Thumbnail
        ClipRRect(
          borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
          child: thumbnail != null
              ? CachedNetworkImage(
                  imageUrl: thumbnail!,
                  width: 90, height: 90, fit: BoxFit.cover,
                  errorWidget: (_, __, ___) => _ThumbPlaceholder())
              : _ThumbPlaceholder(),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, maxLines: 2, overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w800,
                      fontSize: 14, color: _navy)),
              if (category.isNotEmpty) ...[
                const SizedBox(height: 3),
                Text(category, style: TextStyle(color: Colors.grey[500], fontSize: 11)),
              ],
              const SizedBox(height: 8),
              Row(children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(label, style: TextStyle(color: color,
                      fontSize: 11, fontWeight: FontWeight.w700)),
                ),
                const SizedBox(width: 8),
                Icon(Icons.play_lesson_rounded, size: 13, color: Colors.grey[400]),
                const SizedBox(width: 3),
                Text('$totalLessons lessons',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ]),
            ]),
          ),
        ),
      ]),
    );
  }
}

class _ThumbPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width: 90, height: 90,
    color: _navy.withValues(alpha: 0.08),
    child: const Icon(Icons.school_rounded, color: _navy, size: 32),
  );
}

// ─── Empty state ───────────────────────────────────────────────────────────────

class _EmptyContent extends StatelessWidget {
  final VoidCallback onAdd;
  const _EmptyContent({required this.onAdd});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 32),
    child: Column(children: [
      Container(
        width: 80, height: 80,
        decoration: BoxDecoration(
          color: _orange.withValues(alpha: 0.08), shape: BoxShape.circle),
        child: const Icon(Icons.library_books_rounded, color: _orange, size: 38),
      ),
      const SizedBox(height: 16),
      const Text('Section ma jiro', style: TextStyle(fontWeight: FontWeight.w800,
          fontSize: 16, color: _navy)),
      const SizedBox(height: 6),
      Text('Marka hore section ku dar, ka dib lesson-ka ku dar',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey[500], fontSize: 13, height: 1.5)),
      const SizedBox(height: 20),
      FilledButton.icon(
        style: FilledButton.styleFrom(
          backgroundColor: _orange,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        onPressed: onAdd,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Section ku dar', style: TextStyle(fontWeight: FontWeight.w700)),
      ),
    ]),
  );
}

// ─── Section tile ──────────────────────────────────────────────────────────────

class _SectionTile extends StatefulWidget {
  final int index;
  final Map<String, dynamic> section;
  final VoidCallback onChanged;
  const _SectionTile({required this.index, required this.section, required this.onChanged});

  @override
  State<_SectionTile> createState() => _SectionTileState();
}

class _SectionTileState extends State<_SectionTile> {
  bool _expanded = true;

  @override
  Widget build(BuildContext context) {
    final lessons = (widget.section['lessons'] as List? ?? []).cast<Map<String, dynamic>>();
    final sectionId = widget.section['id'] as int;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 6, offset: const Offset(0, 2))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Section header
        InkWell(
          onTap: () => setState(() => _expanded = !_expanded),
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
            child: Row(children: [
              Container(
                width: 30, height: 30,
                decoration: const BoxDecoration(color: _navy, shape: BoxShape.circle),
                child: Center(child: Text('${widget.index + 1}',
                    style: const TextStyle(color: Colors.white,
                        fontSize: 12, fontWeight: FontWeight.w800))),
              ),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(widget.section['title'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w800,
                        fontSize: 14, color: _navy)),
                Text('${lessons.length} lesson${lessons.length == 1 ? '' : 's'}',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ])),
              Icon(_expanded ? Icons.expand_less_rounded : Icons.expand_more_rounded,
                  color: Colors.grey[400]),
            ]),
          ),
        ),

        // Lessons
        if (_expanded) ...[
          if (lessons.isNotEmpty)
            const Divider(height: 1, indent: 14, endIndent: 14),
          ...lessons.asMap().entries.map((e) => _LessonRow(
            lesson: e.value,
            number: e.key + 1,
          )),
          const Divider(height: 1),
          // Add Lesson
          InkWell(
            onTap: () => _addLesson(context, sectionId),
            borderRadius: const BorderRadius.vertical(bottom: Radius.circular(14)),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 14),
              child: Row(children: [
                const SizedBox(width: 40),
                Icon(Icons.add_circle_outline_rounded, color: _orange, size: 18),
                const SizedBox(width: 8),
                const Text('Lesson ku dar',
                    style: TextStyle(color: _orange, fontWeight: FontWeight.w700,
                        fontSize: 13)),
              ]),
            ),
          ),
        ],
      ]),
    );
  }

  Future<void> _addLesson(BuildContext context, int sectionId) async {
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddLessonSheet(sectionId: sectionId, onAdded: widget.onChanged),
    );
  }
}

// ─── Lesson row ────────────────────────────────────────────────────────────────

class _LessonRow extends StatelessWidget {
  final Map<String, dynamic> lesson;
  final int number;
  const _LessonRow({required this.lesson, required this.number});

  IconData get _icon => switch (lesson['type'] as String? ?? 'video') {
    'video'      => Icons.play_circle_rounded,
    'pdf'        => Icons.picture_as_pdf_rounded,
    'document'   => Icons.description_rounded,
    'quiz'       => Icons.quiz_rounded,
    'assignment' => Icons.assignment_rounded,
    'live'       => Icons.videocam_rounded,
    _            => Icons.article_rounded,
  };

  Color get _iconColor => switch (lesson['type'] as String? ?? 'video') {
    'video'      => Colors.blue,
    'pdf'        => Colors.red,
    'quiz'       => Colors.purple,
    'live'       => Colors.green,
    _            => Colors.grey,
  };

  @override
  Widget build(BuildContext context) {
    final isPreview = lesson['is_free_preview'] == true;
    final dur = lesson['video_duration_seconds'] as int?;
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      child: Row(children: [
        SizedBox(width: 40,
          child: Center(child: Icon(_icon, color: _iconColor, size: 20))),
        const SizedBox(width: 6),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(lesson['title'] as String? ?? '',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: _navy),
              maxLines: 2, overflow: TextOverflow.ellipsis),
          if (dur != null && dur > 0)
            Text(_formatDuration(dur),
                style: TextStyle(fontSize: 11, color: Colors.grey[500])),
        ])),
        if (isPreview)
          Container(
            margin: const EdgeInsets.only(left: 6),
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.green.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(4)),
            child: const Text('FREE', style: TextStyle(fontSize: 9,
                fontWeight: FontWeight.w800, color: Colors.green)),
          ),
      ]),
    );
  }

  String _formatDuration(int seconds) {
    final m = seconds ~/ 60;
    final s = seconds % 60;
    return m > 0 ? '${m}m ${s}s' : '${s}s';
  }
}

// ─── Submit review bottom bar ─────────────────────────────────────────────────

class _SubmitBar extends StatelessWidget {
  final bool canSubmit;
  final VoidCallback onSubmit;
  const _SubmitBar({required this.canSubmit, required this.onSubmit});

  @override
  Widget build(BuildContext context) => Container(
    color: Colors.white,
    padding: EdgeInsets.fromLTRB(20, 12, 20,
        12 + MediaQuery.of(context).padding.bottom),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      if (!canSubmit)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Text('Lesson hal ugu yaraan ku dar ka hor inta aadan u dirin',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Colors.grey[500])),
        ),
      FilledButton.icon(
        style: FilledButton.styleFrom(
          backgroundColor: canSubmit ? _navy : Colors.grey[300],
          foregroundColor: canSubmit ? Colors.white : Colors.grey[500],
          minimumSize: const Size.fromHeight(50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
        onPressed: canSubmit ? onSubmit : null,
        icon: const Icon(Icons.send_rounded, size: 18),
        label: const Text('Review-ka u dir',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
      ),
    ]),
  );
}

// ─── Error view ────────────────────────────────────────────────────────────────

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        const Icon(Icons.cloud_off_rounded, size: 56, color: Colors.grey),
        const SizedBox(height: 16),
        const Text('Course-ka load gareyn karin',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: _navy)),
        const SizedBox(height: 8),
        Text(message, textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey[500], fontSize: 12)),
        const SizedBox(height: 20),
        FilledButton.icon(
          style: FilledButton.styleFrom(
            backgroundColor: _navy,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onPressed: onRetry,
          icon: const Icon(Icons.refresh_rounded, size: 18),
          label: const Text('Dib u isku day'),
        ),
      ]),
    ),
  );
}

// ─── Add Section bottom sheet ──────────────────────────────────────────────────

class _AddSectionSheet extends StatefulWidget {
  @override
  State<_AddSectionSheet> createState() => _AddSectionSheetState();
}

class _AddSectionSheetState extends State<_AddSectionSheet> {
  final _ctrl = TextEditingController();
  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
    child: Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: const EdgeInsets.all(20),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2))),
        const Text('Section ku dar',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: _navy)),
        const SizedBox(height: 16),
        TextField(
          controller: _ctrl,
          autofocus: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (v) { if (v.trim().isNotEmpty) Navigator.pop(context, v.trim()); },
          decoration: InputDecoration(
            hintText: 'Tusaale: Introduction, Chapter 1...',
            hintStyle: TextStyle(color: Colors.grey[400]),
            filled: true, fillColor: _bg,
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none),
          ),
        ),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: OutlinedButton(
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: _navy), foregroundColor: _navy,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              padding: const EdgeInsets.symmetric(vertical: 13),
            ),
            onPressed: () => Navigator.pop(context, null),
            child: const Text('Jooji'),
          )),
          const SizedBox(width: 12),
          Expanded(child: FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: _orange,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              padding: const EdgeInsets.symmetric(vertical: 13),
            ),
            onPressed: () {
              final v = _ctrl.text.trim();
              if (v.isNotEmpty) Navigator.pop(context, v);
            },
            child: const Text('Ku dar', style: TextStyle(fontWeight: FontWeight.w800)),
          )),
        ]),
        const SizedBox(height: 8),
      ]),
    ),
  );
}

// ─── Add Lesson bottom sheet ───────────────────────────────────────────────────

class _AddLessonSheet extends ConsumerStatefulWidget {
  final int sectionId;
  final VoidCallback onAdded;
  const _AddLessonSheet({required this.sectionId, required this.onAdded});

  @override
  ConsumerState<_AddLessonSheet> createState() => _AddLessonSheetState();
}

class _AddLessonSheetState extends ConsumerState<_AddLessonSheet> {
  final _titleCtrl    = TextEditingController();
  final _urlCtrl      = TextEditingController();
  final _durCtrl      = TextEditingController();
  final _contentCtrl  = TextEditingController();
  String _type        = 'video';
  bool   _freePreview = false;
  bool   _saving      = false;
  XFile? _videoFile;

  static const _types = {
    'video':      ('🎬', 'Video'),
    'pdf':        ('📄', 'PDF'),
    'document':   ('📝', 'Document'),
    'quiz':       ('❓', 'Quiz'),
    'assignment': ('📋', 'Assignment'),
    'live':       ('🔴', 'Live'),
  };

  @override
  void dispose() {
    _titleCtrl.dispose(); _urlCtrl.dispose();
    _durCtrl.dispose();   _contentCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickVideo() async {
    final f = await ImagePicker().pickVideo(source: ImageSource.gallery);
    if (f != null) setState(() => _videoFile = f);
  }

  Future<void> _save() async {
    if (_titleCtrl.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      MultipartFile? vFile;
      if (_videoFile != null) {
        vFile = await MultipartFile.fromFile(_videoFile!.path, filename: _videoFile!.name);
      }
      await ref.read(elearningServiceProvider).addLesson(
        sectionId:            widget.sectionId,
        title:                _titleCtrl.text.trim(),
        type:                 _type,
        videoUrl:             _urlCtrl.text.trim().isEmpty ? null : _urlCtrl.text.trim(),
        videoDurationSeconds: int.tryParse(_durCtrl.text.trim()),
        content:              _contentCtrl.text.trim(),
        isFreePreview:        _freePreview,
        videoFile:            vFile,
      );
      widget.onAdded();
      if (mounted) Navigator.pop(context);
    } on DioException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.response?.data?['message']?.toString() ?? 'Lesson ku darin karin'),
          backgroundColor: Colors.red));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isVideo = _type == 'video';
    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize:     0.5,
      maxChildSize:     0.95,
      builder: (_, ctrl) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(children: [
          // Handle + header
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [_navy, Color(0xFF3D2B8E)]),
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            padding: const EdgeInsets.fromLTRB(20, 12, 16, 16),
            child: Column(children: [
              Container(width: 40, height: 4,
                  decoration: BoxDecoration(color: Colors.white30,
                      borderRadius: BorderRadius.circular(2))),
              const SizedBox(height: 12),
              Row(children: [
                const Expanded(child: Text('Lesson ku dar',
                    style: TextStyle(color: Colors.white,
                        fontSize: 18, fontWeight: FontWeight.w800))),
                IconButton(onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded, color: Colors.white70)),
              ]),
            ]),
          ),

          // Content
          Expanded(
            child: SingleChildScrollView(
              controller: ctrl,
              padding: const EdgeInsets.all(20),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

                // Lesson title
                _Lbl('Magaca lesson-ka *'),
                const SizedBox(height: 6),
                _TF(ctrl: _titleCtrl, hint: 'Tusaale: Lesson 1 – Variables'),
                const SizedBox(height: 16),

                // Type selector
                _Lbl('Nooca lesson-ka'),
                const SizedBox(height: 8),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final e in _types.entries)
                    GestureDetector(
                      onTap: () => setState(() { _type = e.key; _videoFile = null; }),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: _type == e.key ? _navy : _bg,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: _type == e.key ? _navy : Colors.grey[300]!),
                        ),
                        child: Text('${e.value.$1} ${e.value.$2}',
                            style: TextStyle(
                              color: _type == e.key ? Colors.white : Colors.grey[600],
                              fontWeight: _type == e.key
                                  ? FontWeight.w700 : FontWeight.normal,
                              fontSize: 12,
                            )),
                      ),
                    ),
                ]),
                const SizedBox(height: 16),

                if (isVideo) ...[
                  _Lbl('Video URL (YouTube / Vimeo / direct)'),
                  const SizedBox(height: 6),
                  _TF(ctrl: _urlCtrl, hint: 'https://...'),
                  const SizedBox(height: 10),
                  Row(children: [
                    Expanded(child: Divider(color: Colors.grey[200])),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text('ama', style: TextStyle(color: Colors.grey[400], fontSize: 12)),
                    ),
                    Expanded(child: Divider(color: Colors.grey[200])),
                  ]),
                  const SizedBox(height: 10),
                  GestureDetector(
                    onTap: _pickVideo,
                    child: Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: _videoFile != null
                            ? Colors.green.withValues(alpha: 0.07) : _bg,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: _videoFile != null ? Colors.green : Colors.grey[300]!,
                        ),
                      ),
                      child: Row(children: [
                        Icon(_videoFile != null
                            ? Icons.check_circle_rounded
                            : Icons.video_library_rounded,
                          color: _videoFile != null ? Colors.green : Colors.grey[400],
                          size: 22),
                        const SizedBox(width: 10),
                        Expanded(child: Text(
                          _videoFile != null
                              ? _videoFile!.name : 'Gallery-ga video upload gareey',
                          style: TextStyle(
                            color: _videoFile != null ? Colors.green[700] : Colors.grey[600],
                            fontWeight: _videoFile != null ? FontWeight.w600 : FontWeight.normal,
                          ), overflow: TextOverflow.ellipsis)),
                        if (_videoFile != null)
                          GestureDetector(
                            onTap: () => setState(() => _videoFile = null),
                            child: Icon(Icons.close_rounded, size: 16, color: Colors.grey[400])),
                      ]),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _Lbl('Muddada (seconds) — ikhtiyaari'),
                  const SizedBox(height: 6),
                  _TF(ctrl: _durCtrl, hint: '600',
                      keyboardType: TextInputType.number),
                ] else ...[
                  _Lbl('Content / Notes'),
                  const SizedBox(height: 6),
                  _TF(ctrl: _contentCtrl,
                      hint: 'Lesson-ka waxa ku jira...', maxLines: 5),
                ],
                const SizedBox(height: 16),

                // Free preview toggle
                Container(
                  decoration: BoxDecoration(
                    color: _bg, borderRadius: BorderRadius.circular(12)),
                  child: SwitchListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14),
                    activeColor: _orange,
                    title: const Text('Free Preview',
                        style: TextStyle(fontWeight: FontWeight.w700,
                            color: _navy, fontSize: 14)),
                    subtitle: Text('Ardayda aan is-diiwaangelinin ayaa arki karta',
                        style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                    value: _freePreview,
                    onChanged: (v) => setState(() => _freePreview = v),
                  ),
                ),
                const SizedBox(height: 20),

                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: _orange,
                    minimumSize: const Size.fromHeight(52),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(width: 22, height: 22,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                      : const Text('Lesson ku dar',
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
                ),
                const SizedBox(height: 16),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _Lbl(String t) => Text(t,
      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: _navy));

  Widget _TF({required TextEditingController ctrl, required String hint,
      int maxLines = 1, TextInputType? keyboardType}) =>
      TextField(
        controller: ctrl, maxLines: maxLines, keyboardType: keyboardType,
        decoration: InputDecoration(
          hintText: hint, hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
          filled: true, fillColor: _bg,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide.none),
        ),
      );
}

// ─── Confirm sheet (generic) ───────────────────────────────────────────────────

class _ConfirmSheet extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String title, body, confirmLabel;
  final Color confirmColor;
  const _ConfirmSheet({required this.icon, required this.iconColor,
      required this.title, required this.body,
      required this.confirmLabel, required this.confirmColor});

  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    padding: const EdgeInsets.all(24),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 40, height: 4,
          decoration: BoxDecoration(color: Colors.grey[300],
              borderRadius: BorderRadius.circular(2))),
      const SizedBox(height: 20),
      Container(
        width: 64, height: 64,
        decoration: BoxDecoration(
          color: iconColor.withValues(alpha: 0.1), shape: BoxShape.circle),
        child: Icon(icon, color: iconColor, size: 32),
      ),
      const SizedBox(height: 14),
      Text(title, style: const TextStyle(fontSize: 18,
          fontWeight: FontWeight.w800, color: _navy)),
      const SizedBox(height: 8),
      Text(body, textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey[600], height: 1.5, fontSize: 13)),
      const SizedBox(height: 24),
      Row(children: [
        Expanded(child: OutlinedButton(
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: Colors.grey[300]!),
            foregroundColor: Colors.grey[600],
            padding: const EdgeInsets.symmetric(vertical: 13),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onPressed: () => Navigator.pop(context, false),
          child: const Text('Jooji'),
        )),
        const SizedBox(width: 12),
        Expanded(child: FilledButton(
          style: FilledButton.styleFrom(
            backgroundColor: confirmColor,
            padding: const EdgeInsets.symmetric(vertical: 13),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onPressed: () => Navigator.pop(context, true),
          child: Text(confirmLabel,
              style: const TextStyle(fontWeight: FontWeight.w800)),
        )),
      ]),
      SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
    ]),
  );
}

// ─── Edit Course Sheet ─────────────────────────────────────────────────────────

class _EditCourseSheet extends ConsumerStatefulWidget {
  final int courseId;
  final Map<String, dynamic> data;
  final VoidCallback onSaved;
  const _EditCourseSheet({required this.courseId, required this.data, required this.onSaved});

  @override
  ConsumerState<_EditCourseSheet> createState() => _EditCourseSheetState();
}

class _EditCourseSheetState extends ConsumerState<_EditCourseSheet> {
  late final TextEditingController _titleCtrl;
  late final TextEditingController _subtitleCtrl;
  late final TextEditingController _descCtrl;
  late final TextEditingController _priceCtrl;
  late final TextEditingController _outcomesCtrl;
  late final TextEditingController _reqCtrl;
  int?   _categoryId;
  String _level    = 'all';
  String _language = 'so';
  bool   _isFree   = false;
  MultipartFile? _thumb;
  String?        _thumbName;
  bool _saving = false;

  static const _levels = {'all': 'Dhammaan', 'beginner': 'Bilowga',
      'intermediate': 'Dhexe', 'advanced': 'Sare'};
  static const _langs  = {'so': '🇸🇴 Somali', 'en': '🇬🇧 English', 'ar': '🇸🇦 Arabic'};

  @override
  void initState() {
    super.initState();
    final d = widget.data;
    _titleCtrl    = TextEditingController(text: d['title'] as String? ?? '');
    _subtitleCtrl = TextEditingController(text: d['subtitle'] as String? ?? '');
    _descCtrl     = TextEditingController(text: d['description'] as String? ?? '');
    _priceCtrl    = TextEditingController(text: (d['price'] ?? '').toString());
    _outcomesCtrl = TextEditingController(
        text: (d['learning_outcomes'] as List? ?? []).join('\n'));
    _reqCtrl      = TextEditingController(
        text: (d['requirements'] as List? ?? []).join('\n'));
    _isFree   = d['is_free'] == true;
    _level    = d['level']    as String? ?? 'all';
    _language = d['language'] as String? ?? 'so';
    final cat = d['category'];
    if (cat is Map) _categoryId = cat['id'] as int?;
  }

  @override
  void dispose() {
    _titleCtrl.dispose(); _subtitleCtrl.dispose(); _descCtrl.dispose();
    _priceCtrl.dispose(); _outcomesCtrl.dispose(); _reqCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickThumb() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null) return;
    final bytes = await f.readAsBytes();
    setState(() {
      _thumb     = MultipartFile.fromBytes(bytes, filename: f.name);
      _thumbName = f.name;
    });
  }

  List<String> _lines(String s) =>
      s.split('\n').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();

  Future<void> _save() async {
    if (_titleCtrl.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(elearningServiceProvider).updateCourse(
        widget.courseId,
        title:           _titleCtrl.text.trim(),
        subtitle:        _subtitleCtrl.text.trim(),
        description:     _descCtrl.text.trim(),
        categoryId:      _categoryId,
        level:           _level,
        language:        _language,
        price:           _isFree ? 0 : (double.tryParse(_priceCtrl.text.trim()) ?? 0),
        isFree:          _isFree,
        learningOutcomes: _lines(_outcomesCtrl.text),
        requirements:    _lines(_reqCtrl.text),
        thumbnail:       _thumb,
      );
      widget.onSaved();
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Course updated ✓'), backgroundColor: Colors.green));
      }
    } on DioException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.response?.data?['message']?.toString() ?? 'Update failed'),
          backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cats = ref.watch(elearningCategoriesProvider);
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            padding: const EdgeInsets.fromLTRB(20, 16, 12, 16),
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [_navy, Color(0xFF3D2B8E)]),
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: Row(children: [
              const Expanded(child: Text('Wax ka beddel',
                  style: TextStyle(color: Colors.white,
                      fontSize: 18, fontWeight: FontWeight.w800))),
              IconButton(onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded, color: Colors.white70)),
            ]),
          ),
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

                // Thumbnail
                GestureDetector(
                  onTap: _pickThumb,
                  child: Container(
                    height: 72,
                    decoration: BoxDecoration(
                      color: _bg, borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: _thumb != null ? _orange : Colors.grey[300]!)),
                    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(_thumb != null
                          ? Icons.check_circle_rounded : Icons.add_photo_alternate_rounded,
                        color: _thumb != null ? Colors.green : Colors.grey[400], size: 22),
                      const SizedBox(width: 10),
                      Text(_thumb != null ? _thumbName! : 'Sawirka bedel',
                          style: TextStyle(color: _thumb != null
                              ? Colors.green[700] : Colors.grey[500])),
                    ]),
                  ),
                ),
                const SizedBox(height: 16),

                _lbl('Magaca course-ka'),
                const SizedBox(height: 6),
                _tf(_titleCtrl, 'Course title'),
                const SizedBox(height: 14),

                _lbl('Subtitle'),
                const SizedBox(height: 6),
                _tf(_subtitleCtrl, 'Tagline gaaban'),
                const SizedBox(height: 14),

                _lbl('Sharaxaad'),
                const SizedBox(height: 6),
                _tf(_descCtrl, 'Maxay ardayda baranayaan?', lines: 4),
                const SizedBox(height: 14),

                _lbl('Category'),
                const SizedBox(height: 6),
                cats.when(
                  loading: () => const LinearProgressIndicator(color: _orange),
                  error: (_, __) => const SizedBox.shrink(),
                  data: (list) => _drop<int?>(
                    value: _categoryId,
                    items: list.map((c) => DropdownMenuItem(
                        value: c.id, child: Text(c.name))).toList(),
                    hint: 'Dooro category',
                    onChanged: (v) => setState(() => _categoryId = v),
                  ),
                ),
                const SizedBox(height: 14),

                Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _lbl('Level'),
                    const SizedBox(height: 6),
                    _drop<String>(value: _level,
                      items: _levels.entries.map((e) => DropdownMenuItem(
                          value: e.key, child: Text(e.value))).toList(),
                      onChanged: (v) => setState(() => _level = v!)),
                  ])),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _lbl('Luqadda'),
                    const SizedBox(height: 6),
                    _drop<String>(value: _language,
                      items: _langs.entries.map((e) => DropdownMenuItem(
                          value: e.key, child: Text(e.value))).toList(),
                      onChanged: (v) => setState(() => _language = v!)),
                  ])),
                ]),
                const SizedBox(height: 14),

                Container(
                  decoration: BoxDecoration(
                    color: _bg, borderRadius: BorderRadius.circular(12)),
                  child: SwitchListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14),
                    activeColor: _orange,
                    title: const Text('Bilaash (Free)',
                        style: TextStyle(fontWeight: FontWeight.w700,
                            color: _navy, fontSize: 14)),
                    value: _isFree,
                    onChanged: (v) => setState(() => _isFree = v),
                  ),
                ),
                if (!_isFree) ...[
                  const SizedBox(height: 14),
                  _lbl('Qiimaha (\$)'),
                  const SizedBox(height: 6),
                  _tf(_priceCtrl, 'Tusaale: 19.99',
                      type: TextInputType.number),
                ],
                const SizedBox(height: 14),

                _lbl('Learning Outcomes (hal line kasta)'),
                const SizedBox(height: 6),
                _tf(_outcomesCtrl, 'Real apps ku sameey\nRiverpod baro\n...', lines: 3),
                const SizedBox(height: 14),

                _lbl('Requirements (hal line kasta)'),
                const SizedBox(height: 6),
                _tf(_reqCtrl, 'Computer\nBasic Dart\n...', lines: 2),
                const SizedBox(height: 20),

                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: _orange,
                    minimumSize: const Size.fromHeight(52),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(width: 20, height: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Kaydi', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                ),
                const SizedBox(height: 20),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _lbl(String t) => Text(t,
      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: _navy));

  Widget _tf(TextEditingController c, String hint,
      {int lines = 1, TextInputType? type}) =>
      TextField(
        controller: c, maxLines: lines, keyboardType: type,
        decoration: InputDecoration(
          hintText: hint, hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
          filled: true, fillColor: _bg,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide.none),
        ),
      );

  Widget _drop<T>({required T? value, required List<DropdownMenuItem<T>> items,
      required ValueChanged<T?> onChanged, String? hint}) =>
      Container(
        decoration: BoxDecoration(color: _bg, borderRadius: BorderRadius.circular(10)),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<T>(
            isExpanded: true, value: value,
            hint: hint != null ? Text(hint, style: TextStyle(color: Colors.grey[400])) : null,
            items: items, onChanged: onChanged,
          ),
        ),
      );
}
