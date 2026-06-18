import 'dart:io';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:video_player/video_player.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/elearning_provider.dart';

const _navy   = AppColors.secondary;
const _orange = AppColors.primary;
const _bg     = Color(0xFFF5F6FA);

// ─── Screen ────────────────────────────────────────────────────────────────────

class CourseBuilderScreen extends ConsumerStatefulWidget {
  final int     courseId;
  final String? courseTitle;
  const CourseBuilderScreen(
      {super.key, required this.courseId, this.courseTitle});

  @override
  ConsumerState<CourseBuilderScreen> createState() =>
      _CourseBuilderScreenState();
}

class _CourseBuilderScreenState extends ConsumerState<CourseBuilderScreen> {
  // Local state — bypasses Riverpod cache entirely so data is always fresh.
  Map<String, dynamic>? _data;
  bool   _loading = true;
  String? _error;

  int get _id => widget.courseId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  // ── Load / reload ────────────────────────────────────────────────────────────

  Future<void> _load() async {
    if (!mounted) return;
    setState(() { _loading = true; _error = null; });
    try {
      final data =
          await ref.read(elearningServiceProvider).getCourseStructure(_id);
      if (mounted) setState(() { _data = data; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  // ── Add section ──────────────────────────────────────────────────────────────

  Future<void> _addSection() async {
    final title = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _AddSectionSheet(),
    );
    if (title == null || title.isEmpty) return;
    try {
      await ref.read(elearningServiceProvider)
          .addSection(courseId: _id, title: title);
      await _load();                          // always fetches fresh from server
      if (mounted) _snack('Section added ✓', Colors.green);
    } on DioException catch (e) {
      if (mounted) _snack(e.response?.data?['message']?.toString() ?? 'Failed to add section', Colors.red);
    } catch (e) {
      if (mounted) _snack(e.toString(), Colors.red);
    }
  }

  // ── Submit for review ────────────────────────────────────────────────────────

  Future<void> _submit() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => const _ConfirmSheet(
        icon: Icons.rocket_launch_rounded,
        iconColor: _navy,
        title: 'Submit for Review?',
        body: 'An admin will review your course before it goes live.',
        confirmLabel: 'Submit',
        confirmColor: _navy,
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(elearningServiceProvider).submitCourseForReview(_id);
      ref.invalidate(instructorCoursesProvider);
      await _load();
      if (mounted) _snack('Submitted for review! ✓', Colors.green);
    } on DioException catch (e) {
      if (mounted) _snack(e.response?.data?['message']?.toString() ?? 'Failed to submit', Colors.red);
    }
  }

  // ── Delete ───────────────────────────────────────────────────────────────────

  Future<void> _delete(String status) async {
    if (status == 'published') {
      _snack('Cannot delete a published course.', Colors.red);
      return;
    }
    final ok = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => const _ConfirmSheet(
        icon: Icons.delete_forever_rounded,
        iconColor: Colors.red,
        title: 'Delete Course?',
        body: 'This cannot be undone. All sections and lessons will be permanently deleted.',
        confirmLabel: 'Delete',
        confirmColor: Colors.red,
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(elearningServiceProvider).deleteCourse(_id);
      ref.invalidate(instructorCoursesProvider);
      if (mounted) { context.pop(); _snack('Course deleted.', Colors.red); }
    } catch (e) {
      if (mounted) _snack(e.toString(), Colors.red);
    }
  }

  // ── Edit details ─────────────────────────────────────────────────────────────

  Future<void> _edit() async {
    if (_data == null) return;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditSheet(
        courseId: _id,
        data: _data!,
        onSaved: _load,
      ),
    );
  }

  void _snack(String msg, [Color? bg]) =>
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: bg));

  // ── Build ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final status   = _data?['status']        as String? ?? 'draft';
    final total    = (_data?['total_lessons'] as num?)?.toInt() ?? 0;
    final sections = (_data?['sections']      as List? ?? []).cast<Map<String, dynamic>>();
    final thumb    = _data?['thumbnail']      as String?;
    final title    = _data?['title']          as String? ?? '';
    final cat      = (_data?['category']      as Map?)?['name'] as String? ?? '';

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.courseTitle ?? 'Course Builder',
            maxLines: 1, overflow: TextOverflow.ellipsis,
            style: const TextStyle(
                color: _navy, fontWeight: FontWeight.w800, fontSize: 17)),
        actions: [
          if (_data != null)
            PopupMenuButton<String>(
              icon: const Icon(Icons.more_vert_rounded, color: _navy),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
              onSelected: (v) {
                if (v == 'edit')   _edit();
                if (v == 'delete') _delete(status);
              },
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'edit',
                    child: Row(children: [
                      Icon(Icons.edit_rounded, size: 18, color: _orange),
                      SizedBox(width: 10), Text('Edit Details'),
                    ])),
                PopupMenuItem(value: 'delete',
                    child: Row(children: [
                      Icon(Icons.delete_rounded, size: 18, color: Colors.red),
                      SizedBox(width: 10),
                      Text('Delete Course',
                          style: TextStyle(color: Colors.red)),
                    ])),
              ],
            ),
        ],
      ),

      body: _loading
          ? const Center(child: CircularProgressIndicator(color: _orange))
          : _error != null
              ? _ErrorView(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  color: _orange,
                  onRefresh: _load,
                  child: ListView(
                    padding: EdgeInsets.only(
                      left: 16, right: 16, top: 16,
                      bottom: 110 + MediaQuery.of(context).padding.bottom,
                    ),
                    children: [
                      _HeaderCard(
                          thumb: thumb, title: title,
                          category: cat, status: status,
                          totalLessons: total),
                      const SizedBox(height: 20),

                      if (sections.isEmpty)
                        _EmptyState(onAdd: _addSection)
                      else ...[
                        ...sections.asMap().entries.map((e) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _SectionCard(
                            index:     e.key,
                            section:   e.value,
                            onChanged: _load,
                          ),
                        )),
                        _AddSectionButton(onTap: _addSection),
                      ],
                    ],
                  ),
                ),

      bottomNavigationBar: _data == null
          ? const SizedBox.shrink()
          : status == 'published'
              ? const SizedBox.shrink()
              : status == 'pending'
                  ? _StatusBar(
                      label: 'Under Review — waiting for admin approval',
                      color: Colors.orange)
                  : status == 'rejected'
                      ? _StatusBar(
                          label: 'Rejected — edit your course and resubmit',
                          color: Colors.red)
                      : _SubmitBar(hasLessons: total > 0, onSubmit: _submit),
    );
  }
}

// ─── Header card ───────────────────────────────────────────────────────────────

class _HeaderCard extends StatelessWidget {
  final String? thumb;
  final String  title, category, status;
  final int     totalLessons;
  const _HeaderCard({required this.thumb, required this.title,
      required this.category, required this.status, required this.totalLessons});

  @override
  Widget build(BuildContext context) {
    final (statusColor, statusLabel) = switch (status) {
      'published' => (Colors.green, 'Published'),
      'pending'   => (Colors.orange, 'Under Review'),
      'rejected'  => (Colors.red, 'Rejected'),
      _           => (Colors.blueGrey, 'Draft'),
    };
    return Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        ClipRRect(
          borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
          child: thumb != null
              ? CachedNetworkImage(imageUrl: thumb!,
                  width: 88, height: 88, fit: BoxFit.cover,
                  errorWidget: (_, __, ___) => const _ThumbPlaceholder())
              : const _ThumbPlaceholder(),
        ),
        Expanded(child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w800,
                    fontSize: 14, color: _navy)),
            if (category.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(category,
                  style: TextStyle(fontSize: 11, color: Colors.grey[500])),
            ],
            const SizedBox(height: 8),
            Row(children: [
              _Chip(label: statusLabel, color: statusColor),
              const SizedBox(width: 8),
              Icon(Icons.play_lesson_rounded, size: 12, color: Colors.grey[400]),
              const SizedBox(width: 3),
              Text('$totalLessons lesson${totalLessons == 1 ? '' : 's'}',
                  style: TextStyle(fontSize: 11, color: Colors.grey[500])),
            ]),
          ]),
        )),
      ]),
    );
  }
}

class _ThumbPlaceholder extends StatelessWidget {
  const _ThumbPlaceholder();
  @override
  Widget build(BuildContext context) => Container(
    width: 88, height: 88,
    color: _navy.withValues(alpha: 0.08),
    child: const Icon(Icons.school_rounded, color: _navy, size: 32),
  );
}

class _Chip extends StatelessWidget {
  final String label;
  final Color color;
  const _Chip({required this.label, required this.color});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(5),
    ),
    child: Text(label, style: TextStyle(color: color,
        fontSize: 10, fontWeight: FontWeight.w700)),
  );
}

// ─── Empty state ───────────────────────────────────────────────────────────────

class _EmptyState extends StatelessWidget {
  final VoidCallback onAdd;
  const _EmptyState({required this.onAdd});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 40),
    child: Column(children: [
      Container(
        width: 72, height: 72,
        decoration: BoxDecoration(color: _orange.withValues(alpha: 0.09),
            shape: BoxShape.circle),
        child: const Icon(Icons.library_books_rounded, color: _orange, size: 34),
      ),
      const SizedBox(height: 14),
      const Text('No sections yet',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: _navy)),
      const SizedBox(height: 6),
      Text('Add your first section to get started.',
          style: TextStyle(color: Colors.grey[500], fontSize: 13)),
      const SizedBox(height: 20),
      FilledButton.icon(
        style: FilledButton.styleFrom(
          backgroundColor: _orange,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 13),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        onPressed: onAdd,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Add Section',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
      ),
    ]),
  );
}

// ─── Add section button (below existing list) ──────────────────────────────────

class _AddSectionButton extends StatelessWidget {
  final VoidCallback onTap;
  const _AddSectionButton({required this.onTap});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 4),
    child: OutlinedButton.icon(
      style: OutlinedButton.styleFrom(
        foregroundColor: _navy,
        side: const BorderSide(color: _navy, width: 1.5),
        padding: const EdgeInsets.symmetric(vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      onPressed: onTap,
      icon: const Icon(Icons.add_rounded, size: 20),
      label: const Text('Add Section',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
    ),
  );
}

// ─── Section card ──────────────────────────────────────────────────────────────

class _SectionCard extends StatefulWidget {
  final int index;
  final Map<String, dynamic> section;
  final VoidCallback onChanged;
  const _SectionCard({required this.index, required this.section,
      required this.onChanged});

  @override
  State<_SectionCard> createState() => _SectionCardState();
}

class _SectionCardState extends State<_SectionCard> {
  bool _open = true;

  @override
  Widget build(BuildContext context) {
    final lessons   = (widget.section['lessons'] as List? ?? []).cast<Map<String, dynamic>>();
    final sectionId = widget.section['id'] as int;

    return Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 6, offset: const Offset(0, 2))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // ── Section header ────────────────────────────────────────────────────
        InkWell(
          onTap: () => setState(() => _open = !_open),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
            child: Row(children: [
              Container(
                width: 28, height: 28,
                decoration: BoxDecoration(color: _navy, shape: BoxShape.circle),
                child: Center(child: Text('${widget.index + 1}',
                    style: const TextStyle(color: Colors.white,
                        fontSize: 12, fontWeight: FontWeight.w800))),
              ),
              const SizedBox(width: 10),
              Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(widget.section['title'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w700,
                        fontSize: 14, color: _navy)),
                Text('${lessons.length} lesson${lessons.length == 1 ? '' : 's'}',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ])),
              Icon(_open
                  ? Icons.keyboard_arrow_up_rounded
                  : Icons.keyboard_arrow_down_rounded,
                  color: Colors.grey[400], size: 22),
            ]),
          ),
        ),

        // ── Lessons ───────────────────────────────────────────────────────────
        if (_open) ...[
          if (lessons.isNotEmpty) ...[
            const Divider(height: 1, indent: 14, endIndent: 14),
            ...lessons.asMap().entries.map((e) =>
                _LessonTile(number: e.key + 1, lesson: e.value)),
          ],
          const Divider(height: 1),
          // Add lesson row
          InkWell(
            onTap: () => _openAddLesson(context, sectionId),
            borderRadius: const BorderRadius.vertical(bottom: Radius.circular(14)),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(52, 12, 14, 12),
              child: Row(children: [
                Icon(Icons.add_circle_outline_rounded, color: _orange, size: 18),
                const SizedBox(width: 8),
                const Text('Add Lesson',
                    style: TextStyle(color: _orange,
                        fontWeight: FontWeight.w700, fontSize: 13)),
              ]),
            ),
          ),
        ],
      ]),
    );
  }

  Future<void> _openAddLesson(BuildContext ctx, int sectionId) async {
    await showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddLessonSheet(
          sectionId: sectionId, onAdded: widget.onChanged),
    );
  }
}

// ─── Lesson tile ───────────────────────────────────────────────────────────────

class _LessonTile extends StatelessWidget {
  final int number;
  final Map<String, dynamic> lesson;
  const _LessonTile({required this.number, required this.lesson});

  IconData get _icon => switch (lesson['type'] as String? ?? 'video') {
    'pdf'        => Icons.picture_as_pdf_rounded,
    'quiz'       => Icons.quiz_rounded,
    'assignment' => Icons.assignment_rounded,
    'live'       => Icons.videocam_rounded,
    'document'   => Icons.description_rounded,
    _            => Icons.play_circle_rounded,
  };

  Color get _color => switch (lesson['type'] as String? ?? 'video') {
    'pdf'  => Colors.red[400]!,
    'quiz' => Colors.purple[400]!,
    'live' => Colors.green[500]!,
    _      => Colors.blue[500]!,
  };

  @override
  Widget build(BuildContext context) {
    final isFree = lesson['is_free_preview'] == true;
    final dur    = lesson['video_duration_seconds'] as int?;
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      child: Row(children: [
        SizedBox(width: 28, child: Icon(_icon, color: _color, size: 20)),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(lesson['title'] as String? ?? '',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600,
                  color: _navy),
              maxLines: 2, overflow: TextOverflow.ellipsis),
          if (dur != null && dur > 0)
            Text(_fmt(dur),
                style: TextStyle(fontSize: 11, color: Colors.grey[500])),
        ])),
        if (isFree) ...[
          const SizedBox(width: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.green.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(4)),
            child: const Text('FREE', style: TextStyle(fontSize: 9,
                fontWeight: FontWeight.w800, color: Colors.green)),
          ),
        ],
      ]),
    );
  }

  String _fmt(int s) {
    final m = s ~/ 60, r = s % 60;
    return m > 0 ? '${m}m ${r}s' : '${r}s';
  }
}

// ─── Submit bar ────────────────────────────────────────────────────────────────

class _SubmitBar extends StatelessWidget {
  final bool hasLessons;
  final VoidCallback onSubmit;
  const _SubmitBar({required this.hasLessons, required this.onSubmit});

  @override
  Widget build(BuildContext context) => Container(
    color: Colors.white,
    padding: EdgeInsets.fromLTRB(16, 12, 16,
        12 + MediaQuery.of(context).padding.bottom),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      if (!hasLessons)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Text('Add at least one lesson before submitting for review.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Colors.grey[500])),
        ),
      FilledButton.icon(
        style: FilledButton.styleFrom(
          backgroundColor: hasLessons ? _navy : Colors.grey[300],
          foregroundColor: hasLessons ? Colors.white : Colors.grey[500],
          minimumSize: const Size.fromHeight(50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
        ),
        onPressed: hasLessons ? onSubmit : null,
        icon: const Icon(Icons.rocket_launch_rounded, size: 18),
        label: const Text('Submit for Review',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
      ),
    ]),
  );
}

// ─── Status bar (pending / rejected) ─────────────────────────────────────────

class _StatusBar extends StatelessWidget {
  final String label;
  final Color color;
  const _StatusBar({required this.label, required this.color});

  @override
  Widget build(BuildContext context) => Container(
    color: color.withValues(alpha: 0.08),
    padding: EdgeInsets.fromLTRB(16, 12, 16,
        12 + MediaQuery.of(context).padding.bottom),
    child: Row(children: [
      Icon(Icons.info_outline_rounded, color: color, size: 18),
      const SizedBox(width: 8),
      Expanded(child: Text(label,
          style: TextStyle(color: color, fontWeight: FontWeight.w600,
              fontSize: 13))),
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
        const Icon(Icons.cloud_off_rounded, size: 54, color: Colors.grey),
        const SizedBox(height: 14),
        const Text('Could not load course',
            style: TextStyle(fontWeight: FontWeight.w700,
                fontSize: 16, color: _navy)),
        const SizedBox(height: 8),
        Text(message, textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey[500], fontSize: 12)),
        const SizedBox(height: 20),
        FilledButton.icon(
          style: FilledButton.styleFrom(
              backgroundColor: _navy,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12))),
          onPressed: onRetry,
          icon: const Icon(Icons.refresh_rounded, size: 18),
          label: const Text('Try Again'),
        ),
      ]),
    ),
  );
}

// ─── Add Section sheet ─────────────────────────────────────────────────────────

class _AddSectionSheet extends StatefulWidget {
  const _AddSectionSheet();
  @override
  State<_AddSectionSheet> createState() => _AddSectionSheetState();
}

class _AddSectionSheetState extends State<_AddSectionSheet> {
  final _ctrl = TextEditingController();
  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  void _done() {
    final v = _ctrl.text.trim();
    if (v.isNotEmpty) Navigator.pop(context, v);
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom),
    child: Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 18),
            decoration: BoxDecoration(color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2))),
        const Align(alignment: Alignment.centerLeft,
          child: Text('New Section',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800,
                  color: _navy))),
        const SizedBox(height: 14),
        TextField(
          controller: _ctrl,
          autofocus: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _done(),
          decoration: InputDecoration(
            hintText: 'e.g. Introduction, Chapter 1, Setup...',
            hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
            filled: true, fillColor: _bg,
            contentPadding: const EdgeInsets.symmetric(
                horizontal: 14, vertical: 14),
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none),
            focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: _orange, width: 1.5)),
          ),
        ),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: OutlinedButton(
            style: OutlinedButton.styleFrom(
              side: BorderSide(color: Colors.grey[300]!),
              foregroundColor: Colors.grey[600],
              padding: const EdgeInsets.symmetric(vertical: 13),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
            onPressed: () => Navigator.pop(context, null),
            child: const Text('Cancel'),
          )),
          const SizedBox(width: 12),
          Expanded(child: FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: _orange,
              padding: const EdgeInsets.symmetric(vertical: 13),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
            onPressed: _done,
            child: const Text('Add',
                style: TextStyle(fontWeight: FontWeight.w800)),
          )),
        ]),
      ]),
    ),
  );
}

// ─── Add Lesson sheet ──────────────────────────────────────────────────────────

class _AddLessonSheet extends ConsumerStatefulWidget {
  final int sectionId;
  final VoidCallback onAdded;
  const _AddLessonSheet({required this.sectionId, required this.onAdded});

  @override
  ConsumerState<_AddLessonSheet> createState() => _AddLessonSheetState();
}

class _AddLessonSheetState extends ConsumerState<_AddLessonSheet> {
  final _titleCtrl   = TextEditingController();
  final _urlCtrl     = TextEditingController();
  final _durCtrl     = TextEditingController();
  final _contentCtrl = TextEditingController();
  String _type        = 'video';
  bool   _freePreview = false;
  bool   _saving      = false;
  XFile? _videoFile;

  static const _types = <String, String>{
    'video': '🎬 Video', 'pdf': '📄 PDF',
    'document': '📝 Document', 'quiz': '❓ Quiz',
    'assignment': '📋 Assignment', 'live': '🔴 Live',
  };

  @override
  void dispose() {
    _titleCtrl.dispose(); _urlCtrl.dispose();
    _durCtrl.dispose(); _contentCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickVideo() async {
    final f = await ImagePicker().pickVideo(source: ImageSource.gallery);
    if (f == null) return;
    setState(() => _videoFile = f);
    // Auto-detect duration
    try {
      final vpc = VideoPlayerController.file(File(f.path));
      await vpc.initialize();
      final secs = vpc.value.duration.inSeconds;
      await vpc.dispose();
      if (secs > 0) setState(() => _durCtrl.text = secs.toString());
    } catch (_) {}
  }

  Future<void> _save() async {
    if (_titleCtrl.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      MultipartFile? vf;
      if (_videoFile != null) {
        vf = await MultipartFile.fromFile(_videoFile!.path,
            filename: _videoFile!.name);
      }
      await ref.read(elearningServiceProvider).addLesson(
        sectionId: widget.sectionId,
        title:     _titleCtrl.text.trim(),
        type:      _type,
        videoUrl:  _urlCtrl.text.trim().isEmpty ? null : _urlCtrl.text.trim(),
        videoDurationSeconds: int.tryParse(_durCtrl.text.trim()),
        content:  _contentCtrl.text.trim(),
        isFreePreview: _freePreview,
        videoFile: vf,
      );
      widget.onAdded();
      if (mounted) Navigator.pop(context);
    } on DioException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.response?.data?['message']?.toString()
              ?? 'Failed to add lesson'),
          backgroundColor: Colors.red));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => DraggableScrollableSheet(
    initialChildSize: 0.82, minChildSize: 0.5, maxChildSize: 0.95,
    builder: (_, ctrl) => Container(
      decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      child: Column(children: [

        // Header
        Container(
          decoration: BoxDecoration(
            color: _navy,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 14, 12, 16),
          child: Column(children: [
            Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(color: Colors.white30,
                    borderRadius: BorderRadius.circular(2))),
            Row(children: [
              const Expanded(child: Text('Add Lesson',
                  style: TextStyle(color: Colors.white,
                      fontSize: 18, fontWeight: FontWeight.w800))),
              IconButton(icon: const Icon(Icons.close_rounded,
                  color: Colors.white70, size: 22),
                  onPressed: () => Navigator.pop(context)),
            ]),
          ]),
        ),

        Expanded(child: ListView(
          controller: ctrl,
          padding: const EdgeInsets.all(20),
          children: [

            // Title
            _label('Lesson Title *'),
            const SizedBox(height: 6),
            _field(_titleCtrl, 'e.g. What is Flutter?'),
            const SizedBox(height: 16),

            // Type
            _label('Lesson Type'),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8,
              children: _types.entries.map((e) => GestureDetector(
                onTap: () => setState(() { _type = e.key; _videoFile = null; }),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: _type == e.key ? _navy : _bg,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: _type == e.key ? _navy : Colors.grey[300]!),
                  ),
                  child: Text(e.value,
                    style: TextStyle(fontSize: 12,
                      color: _type == e.key ? Colors.white : Colors.grey[600],
                      fontWeight: _type == e.key
                          ? FontWeight.w700 : FontWeight.normal)),
                ),
              )).toList()),
            const SizedBox(height: 16),

            if (_type == 'video') ...[
              _label('Video URL (YouTube / Vimeo / direct link)'),
              const SizedBox(height: 6),
              _field(_urlCtrl, 'https://...'),
              const SizedBox(height: 10),
              // OR divider
              Row(children: [
                Expanded(child: Divider(color: Colors.grey[200])),
                Padding(padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text('or upload from device',
                      style: TextStyle(color: Colors.grey[400], fontSize: 12))),
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
                    border: Border.all(color: _videoFile != null
                        ? Colors.green : Colors.grey[300]!),
                  ),
                  child: Row(children: [
                    Icon(_videoFile != null
                        ? Icons.check_circle_rounded
                        : Icons.video_library_rounded,
                      color: _videoFile != null
                          ? Colors.green : Colors.grey[400], size: 22),
                    const SizedBox(width: 10),
                    Expanded(child: Text(_videoFile != null
                        ? _videoFile!.name : 'Pick video from gallery',
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: _videoFile != null
                            ? Colors.green[700] : Colors.grey[600],
                        fontWeight: _videoFile != null
                            ? FontWeight.w600 : FontWeight.normal))),
                    if (_videoFile != null)
                      GestureDetector(onTap: () => setState(() => _videoFile = null),
                          child: Icon(Icons.close_rounded,
                              size: 16, color: Colors.grey[400])),
                  ]),
                ),
              ),
              const SizedBox(height: 12),
              _label('Duration in seconds (optional)'),
              const SizedBox(height: 6),
              _field(_durCtrl, 'e.g. 600',
                  type: TextInputType.number),
            ] else ...[
              _label('Content / Notes'),
              const SizedBox(height: 6),
              _field(_contentCtrl, 'Lesson content...', lines: 4),
            ],

            const SizedBox(height: 16),
            Container(
              decoration: BoxDecoration(color: _bg,
                  borderRadius: BorderRadius.circular(12)),
              child: SwitchListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 14),
                activeThumbColor: _orange,
                title: const Text('Free Preview',
                    style: TextStyle(fontWeight: FontWeight.w700,
                        color: _navy, fontSize: 14)),
                subtitle: Text('Anyone can watch this lesson for free',
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
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(13)),
              ),
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(width: 22, height: 22,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2.5))
                  : const Text('Add Lesson',
                      style: TextStyle(fontSize: 15,
                          fontWeight: FontWeight.w800)),
            ),
            const SizedBox(height: 20),
          ],
        )),
      ]),
    ),
  );

  Widget _label(String t) => Text(t,
      style: const TextStyle(fontWeight: FontWeight.w700,
          fontSize: 13, color: _navy));

  Widget _field(TextEditingController c, String hint,
      {int lines = 1, TextInputType? type}) =>
      TextField(
        controller: c, maxLines: lines, keyboardType: type,
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
          filled: true, fillColor: _bg,
          contentPadding: const EdgeInsets.symmetric(
              horizontal: 14, vertical: 13),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide.none),
          focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: _orange, width: 1.5)),
        ),
      );
}

// ─── Confirm sheet ─────────────────────────────────────────────────────────────

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
    decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    padding: const EdgeInsets.all(24),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 20),
          decoration: BoxDecoration(color: Colors.grey[300],
              borderRadius: BorderRadius.circular(2))),
      Container(width: 64, height: 64,
          decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.1),
              shape: BoxShape.circle),
          child: Icon(icon, color: iconColor, size: 30)),
      const SizedBox(height: 14),
      Text(title, style: const TextStyle(fontSize: 18,
          fontWeight: FontWeight.w800, color: _navy)),
      const SizedBox(height: 8),
      Text(body, textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey[600],
              height: 1.5, fontSize: 13)),
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
          child: const Text('Cancel'),
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

// ─── Edit Course sheet ─────────────────────────────────────────────────────────

class _EditSheet extends ConsumerStatefulWidget {
  final int courseId;
  final Map<String, dynamic> data;
  final VoidCallback onSaved;
  const _EditSheet({required this.courseId, required this.data,
      required this.onSaved});

  @override
  ConsumerState<_EditSheet> createState() => _EditSheetState();
}

class _EditSheetState extends ConsumerState<_EditSheet> {
  late final TextEditingController _title, _subtitle, _desc, _price, _trailerUrl;
  int?   _catId;
  String _level = 'all', _lang = 'so';
  bool   _free  = false;
  bool   _saving = false;
  MultipartFile? _thumb;
  String?        _thumbName;
  XFile?         _trailerFile;
  MultipartFile? _trailerMultipart;

  static const _levels = {'all': 'All Levels', 'beginner': 'Beginner',
      'intermediate': 'Intermediate', 'advanced': 'Advanced'};
  static const _langs  = {'so': '🇸🇴 Somali', 'en': '🇬🇧 English', 'ar': '🇸🇦 Arabic'};

  @override
  void initState() {
    super.initState();
    final d = widget.data;
    _title      = TextEditingController(text: d['title']         as String? ?? '');
    _subtitle   = TextEditingController(text: d['subtitle']      as String? ?? '');
    _desc       = TextEditingController(text: d['description']   as String? ?? '');
    _price      = TextEditingController(text: (d['price'] ?? '').toString());
    _trailerUrl = TextEditingController(text: d['trailer_video'] as String? ?? '');
    _free       = d['is_free'] == true;
    _level    = d['level']    as String? ?? 'all';
    _lang     = d['language'] as String? ?? 'so';
    final cat = d['category'];
    if (cat is Map) _catId = cat['id'] as int?;
  }

  @override
  void dispose() {
    _title.dispose(); _subtitle.dispose();
    _desc.dispose(); _price.dispose(); _trailerUrl.dispose();
    super.dispose();
  }

  Future<void> _pickTrailer() async {
    final f = await ImagePicker().pickVideo(source: ImageSource.gallery);
    if (f == null) return;
    final mp = await MultipartFile.fromFile(f.path, filename: f.name);
    setState(() {
      _trailerFile      = f;
      _trailerMultipart = mp;
      _trailerUrl.clear();
    });
  }

  Future<void> _pickThumb() async {
    final f = await ImagePicker()
        .pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null) return;
    final bytes = await f.readAsBytes();
    setState(() {
      _thumb     = MultipartFile.fromBytes(bytes, filename: f.name);
      _thumbName = f.name;
    });
  }

  Future<void> _save() async {
    if (_title.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(elearningServiceProvider).updateCourse(widget.courseId,
        title: _title.text.trim(), subtitle: _subtitle.text.trim(),
        description: _desc.text.trim(), categoryId: _catId,
        level: _level, language: _lang,
        price: _free ? 0 : (double.tryParse(_price.text.trim()) ?? 0),
        isFree: _free, thumbnail: _thumb,
        trailerVideoUrl:  _trailerMultipart == null && _trailerUrl.text.trim().isNotEmpty
                              ? _trailerUrl.text.trim() : null,
        trailerVideoFile: _trailerMultipart,
      );
      widget.onSaved();
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Course updated ✓'),
            backgroundColor: Colors.green));
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
        decoration: BoxDecoration(color: context.colors.cardBg,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            padding: const EdgeInsets.fromLTRB(20, 14, 12, 16),
            decoration: BoxDecoration(color: _navy,
                borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
            child: Row(children: [
              const Expanded(child: Text('Edit Course Details',
                  style: TextStyle(color: Colors.white,
                      fontSize: 18, fontWeight: FontWeight.w800))),
              IconButton(icon: const Icon(Icons.close_rounded,
                  color: Colors.white70),
                  onPressed: () => Navigator.pop(context)),
            ]),
          ),
          Flexible(child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

              GestureDetector(onTap: _pickThumb,
                child: Container(height: 60,
                  decoration: BoxDecoration(
                    color: _bg, borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: _thumb != null ? _orange : Colors.grey[300]!)),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(_thumb != null ? Icons.check_circle_rounded
                        : Icons.add_photo_alternate_rounded,
                      color: _thumb != null ? Colors.green : Colors.grey[400], size: 20),
                    const SizedBox(width: 8),
                    Text(_thumb != null ? _thumbName! : 'Change Thumbnail',
                        style: TextStyle(color: _thumb != null
                            ? Colors.green[700] : Colors.grey[500])),
                  ]),
                ),
              ),
              const SizedBox(height: 10),

              // Trailer video
              _lbl('Trailer Video URL (YouTube ama MP4)'),
              const SizedBox(height: 5),
              _tf(_trailerUrl, 'https://youtube.com/watch?v=...'),
              const SizedBox(height: 8),
              GestureDetector(
                onTap: _pickTrailer,
                child: Container(
                  height: 44,
                  decoration: BoxDecoration(
                    color: _bg,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: _trailerFile != null ? _orange : Colors.grey[300]!,
                    ),
                  ),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(
                      _trailerFile != null ? Icons.videocam_rounded : Icons.video_library_outlined,
                      color: _trailerFile != null ? _orange : Colors.grey[400], size: 20,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _trailerFile != null ? _trailerFile!.name : 'Upload Trailer Video',
                      style: TextStyle(
                        color: _trailerFile != null ? _navy : Colors.grey[500],
                        fontWeight: _trailerFile != null ? FontWeight.w600 : FontWeight.normal,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ]),
                ),
              ),
              const SizedBox(height: 14),

              _lbl('Title'), const SizedBox(height: 5),
              _tf(_title, 'Course title'),
              const SizedBox(height: 12),

              _lbl('Subtitle'), const SizedBox(height: 5),
              _tf(_subtitle, 'Short tagline'),
              const SizedBox(height: 12),

              _lbl('Description'), const SizedBox(height: 5),
              _tf(_desc, 'What will students learn?', lines: 3),
              const SizedBox(height: 12),

              _lbl('Category'), const SizedBox(height: 5),
              cats.when(
                loading: () => const LinearProgressIndicator(color: _orange),
                error: (_, __) => const SizedBox.shrink(),
                data: (list) => _drop<int?>(
                  value: _catId,
                  hint: 'Select category',
                  items: list.map((c) => DropdownMenuItem(
                      value: c.id, child: Text(c.name))).toList(),
                  onChanged: (v) => setState(() => _catId = v),
                ),
              ),
              const SizedBox(height: 12),

              Row(children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  _lbl('Level'), const SizedBox(height: 5),
                  _drop<String>(value: _level,
                    items: _levels.entries.map((e) => DropdownMenuItem(
                        value: e.key, child: Text(e.value))).toList(),
                    onChanged: (v) => setState(() => _level = v!)),
                ])),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  _lbl('Language'), const SizedBox(height: 5),
                  _drop<String>(value: _lang,
                    items: _langs.entries.map((e) => DropdownMenuItem(
                        value: e.key, child: Text(e.value))).toList(),
                    onChanged: (v) => setState(() => _lang = v!)),
                ])),
              ]),
              const SizedBox(height: 12),

              Container(
                decoration: BoxDecoration(color: _bg,
                    borderRadius: BorderRadius.circular(12)),
                child: SwitchListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14),
                  activeThumbColor: _orange,
                  title: const Text('Free Course',
                      style: TextStyle(fontWeight: FontWeight.w700,
                          color: _navy, fontSize: 14)),
                  value: _free,
                  onChanged: (v) => setState(() => _free = v),
                ),
              ),
              if (!_free) ...[
                const SizedBox(height: 12),
                _lbl('Price (\$)'), const SizedBox(height: 5),
                _tf(_price, 'e.g. 19.99',
                    type: TextInputType.number),
              ],
              const SizedBox(height: 20),

              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: _orange,
                  minimumSize: const Size.fromHeight(50),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(13)),
                ),
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(width: 20, height: 20,
                        child: CircularProgressIndicator(
                            color: Colors.white, strokeWidth: 2))
                    : const Text('Save Changes',
                        style: TextStyle(fontWeight: FontWeight.w800,
                            fontSize: 15)),
              ),
              const SizedBox(height: 16),
            ]),
          )),
        ]),
      ),
    );
  }

  Widget _lbl(String t) => Text(t,
      style: const TextStyle(fontWeight: FontWeight.w700,
          fontSize: 13, color: _navy));

  Widget _tf(TextEditingController c, String hint,
      {int lines = 1, TextInputType? type}) =>
      TextField(
        controller: c, maxLines: lines, keyboardType: type,
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
          filled: true, fillColor: _bg,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide.none),
          focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: _orange, width: 1.5)),
        ),
      );

  Widget _drop<T>({required T? value, required List<DropdownMenuItem<T>> items,
      required ValueChanged<T?> onChanged, String? hint}) =>
      Container(
        decoration: BoxDecoration(color: _bg,
            borderRadius: BorderRadius.circular(10)),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<T>(
            isExpanded: true, value: value,
            hint: hint != null
                ? Text(hint, style: TextStyle(color: Colors.grey[400]))
                : null,
            items: items, onChanged: onChanged,
          ),
        ),
      );
}
