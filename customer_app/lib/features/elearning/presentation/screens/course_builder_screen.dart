import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/elearning_provider.dart';

class CourseBuilderScreen extends ConsumerWidget {
  final int courseId;
  final String? courseTitle;
  const CourseBuilderScreen({super.key, required this.courseId, this.courseTitle});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final structAsync = ref.watch(courseStructureProvider(courseId));

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text(courseTitle ?? 'Course Content',
            maxLines: 1, overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
      ),
      body: structAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          const Text('Could not load course content'),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: () => ref.invalidate(courseStructureProvider(courseId)), child: const Text('Retry')),
        ])),
        data: (data) {
          final sections = (data['sections'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
          final status = data['status'] as String? ?? 'draft';
          final totalLessons = (data['total_lessons'] as num?)?.toInt() ?? 0;

          return RefreshIndicator(
            color: AppColors.primary,
            onRefresh: () async => ref.invalidate(courseStructureProvider(courseId)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Status banner
                _StatusBanner(status: status),
                const SizedBox(height: 16),

                if (sections.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 30),
                    child: Column(children: [
                      Icon(Icons.playlist_add_rounded, size: 60, color: Colors.grey[300]),
                      const SizedBox(height: 12),
                      Text('No sections yet.\nAdd a section to start building.',
                          textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[500])),
                    ]),
                  )
                else
                  ...sections.asMap().entries.map((e) => _SectionCard(
                        index: e.key,
                        section: e.value,
                        courseId: courseId,
                        onChanged: () => ref.invalidate(courseStructureProvider(courseId)),
                      )),

                const SizedBox(height: 8),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.primary,
                    side: const BorderSide(color: AppColors.primary),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: () => _addSection(context, ref),
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('Add Section'),
                ),
                const SizedBox(height: 24),

                // Submit for review
                if (status == 'draft')
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: totalLessons > 0 ? AppColors.secondary : Colors.grey,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    onPressed: totalLessons > 0 ? () => _submitForReview(context, ref) : null,
                    icon: const Icon(Icons.send_rounded),
                    label: const Text('Submit for Review', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                  ),
                if (status == 'draft' && totalLessons == 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text('Add at least one lesson before submitting.',
                        textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                  ),
                const SizedBox(height: 40),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _addSection(BuildContext context, WidgetRef ref) async {
    final ctrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Add Section'),
        content: TextField(
          controller: ctrl,
          autofocus: true,
          decoration: const InputDecoration(hintText: 'Section title, e.g. Introduction'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogCtx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
            onPressed: () => Navigator.pop(dialogCtx, true),
            child: const Text('Add'),
          ),
        ],
      ),
    );
    if (ok == true && ctrl.text.trim().isNotEmpty) {
      try {
        await ref.read(elearningServiceProvider).addSection(courseId: courseId, title: ctrl.text.trim());
        ref.invalidate(courseStructureProvider(courseId));
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
              content: Text('Section added'), backgroundColor: Colors.green));
        }
      } on DioException catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(e.response?.data?['message']?.toString() ?? 'Failed to add section'),
              backgroundColor: Colors.red));
        }
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text('Failed to add section: $e'), backgroundColor: Colors.red));
        }
      }
    }
  }

  Future<void> _submitForReview(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Submit for Review?'),
        content: const Text('Once submitted, admin will review your course before it goes live. You can still edit while it\'s in review.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogCtx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.secondary),
            onPressed: () => Navigator.pop(dialogCtx, true),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    if (ok == true) {
      try {
        await ref.read(elearningServiceProvider).submitCourseForReview(courseId);
        ref.invalidate(courseStructureProvider(courseId));
        ref.invalidate(instructorCoursesProvider);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
              content: Text('Course submitted for review!'), backgroundColor: Colors.green));
        }
      } on DioException catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(e.response?.data?['message']?.toString() ?? 'Failed to submit'),
              backgroundColor: Colors.red));
        }
      }
    }
  }
}

class _StatusBanner extends StatelessWidget {
  final String status;
  const _StatusBanner({required this.status});

  @override
  Widget build(BuildContext context) {
    final (color, icon, text) = switch (status) {
      'published' => (Colors.green, Icons.check_circle_rounded, 'Published — live for students'),
      'pending'   => (Colors.orange, Icons.hourglass_top_rounded, 'In review — admin is checking your course'),
      'rejected'  => (Colors.red, Icons.cancel_rounded, 'Rejected — please review and resubmit'),
      'archived'  => (Colors.grey, Icons.archive_rounded, 'Archived'),
      _           => (Colors.blueGrey, Icons.edit_note_rounded, 'Draft — not visible to students yet'),
    };
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
      child: Row(children: [
        Icon(icon, color: color, size: 22),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 13))),
      ]),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final int index;
  final Map<String, dynamic> section;
  final int courseId;
  final VoidCallback onChanged;
  const _SectionCard({required this.index, required this.section, required this.courseId, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final lessons = (section['lessons'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Section header
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
          child: Row(children: [
            CircleAvatar(radius: 14, backgroundColor: AppColors.secondary,
                child: Text('${index + 1}', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700))),
            const SizedBox(width: 10),
            Expanded(child: Text(section['title'] as String? ?? '',
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          ]),
        ),
        const Divider(height: 1),
        // Lessons
        ...lessons.map((l) => _LessonRow(lesson: l)),
        // Add lesson
        InkWell(
          onTap: () => _addLesson(context),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            child: Row(children: const [
              Icon(Icons.add_circle_outline_rounded, color: AppColors.primary, size: 20),
              SizedBox(width: 8),
              Text('Add Lesson', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      ]),
    );
  }

  Future<void> _addLesson(BuildContext context) async {
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddLessonSheet(sectionId: section['id'] as int, onAdded: onChanged),
    );
  }
}

class _LessonRow extends StatelessWidget {
  final Map<String, dynamic> lesson;
  const _LessonRow({required this.lesson});

  IconData _typeIcon(String t) => switch (t) {
        'video'      => Icons.play_circle_outline_rounded,
        'pdf'        => Icons.picture_as_pdf_rounded,
        'document'   => Icons.description_rounded,
        'quiz'       => Icons.quiz_rounded,
        'assignment' => Icons.assignment_rounded,
        'live'       => Icons.videocam_rounded,
        _            => Icons.article_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final isPreview = lesson['is_free_preview'] == true;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      child: Row(children: [
        Icon(_typeIcon(lesson['type'] as String? ?? 'video'), size: 18, color: Colors.grey[500]),
        const SizedBox(width: 10),
        Expanded(child: Text(lesson['title'] as String? ?? '', style: const TextStyle(fontSize: 13.5))),
        if (isPreview)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(5)),
            child: const Text('FREE', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: Colors.green)),
          ),
      ]),
    );
  }
}

class _AddLessonSheet extends ConsumerStatefulWidget {
  final int sectionId;
  final VoidCallback onAdded;
  const _AddLessonSheet({required this.sectionId, required this.onAdded});

  @override
  ConsumerState<_AddLessonSheet> createState() => _AddLessonSheetState();
}

class _AddLessonSheetState extends ConsumerState<_AddLessonSheet> {
  final _titleCtrl = TextEditingController();
  final _videoUrlCtrl = TextEditingController();
  final _durationCtrl = TextEditingController();
  final _contentCtrl = TextEditingController();
  String _type = 'video';
  bool _freePreview = false;
  bool _saving = false;

  @override
  void dispose() {
    _titleCtrl.dispose();
    _videoUrlCtrl.dispose();
    _durationCtrl.dispose();
    _contentCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_titleCtrl.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(elearningServiceProvider).addLesson(
            sectionId: widget.sectionId,
            title: _titleCtrl.text.trim(),
            type: _type,
            videoUrl: _videoUrlCtrl.text.trim(),
            videoDurationSeconds: int.tryParse(_durationCtrl.text.trim()),
            content: _contentCtrl.text.trim(),
            isFreePreview: _freePreview,
          );
      widget.onAdded();
      if (mounted) Navigator.pop(context);
    } on DioException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(e.response?.data?['message']?.toString() ?? 'Failed to add lesson'),
            backgroundColor: Colors.red));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isVideo = _type == 'video';
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(20),
        child: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('Add Lesson', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.secondary)),
            const SizedBox(height: 16),

            _field(_titleCtrl, 'Lesson title *'),
            const SizedBox(height: 12),

            // Type
            Container(
              decoration: BoxDecoration(color: const Color(0xFFF5F6FA), borderRadius: BorderRadius.circular(12)),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  isExpanded: true,
                  value: _type,
                  items: const [
                    DropdownMenuItem(value: 'video', child: Text('🎬 Video')),
                    DropdownMenuItem(value: 'pdf', child: Text('📄 PDF')),
                    DropdownMenuItem(value: 'document', child: Text('📝 Document / Text')),
                    DropdownMenuItem(value: 'quiz', child: Text('❓ Quiz')),
                    DropdownMenuItem(value: 'assignment', child: Text('📋 Assignment')),
                    DropdownMenuItem(value: 'live', child: Text('🔴 Live Session')),
                  ],
                  onChanged: (v) => setState(() => _type = v ?? 'video'),
                ),
              ),
            ),
            const SizedBox(height: 12),

            if (isVideo) ...[
              _field(_videoUrlCtrl, 'Video URL (YouTube / Vimeo / direct)'),
              const SizedBox(height: 12),
              _field(_durationCtrl, 'Duration in seconds (e.g. 600)', keyboardType: TextInputType.number),
              const SizedBox(height: 12),
            ] else ...[
              _field(_contentCtrl, 'Content / notes', maxLines: 4),
              const SizedBox(height: 12),
            ],

            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              activeColor: AppColors.primary,
              title: const Text('Free Preview', style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.secondary, fontSize: 14)),
              subtitle: const Text('Let non-enrolled students view this lesson', style: TextStyle(fontSize: 12)),
              value: _freePreview,
              onChanged: (v) => setState(() => _freePreview = v),
            ),
            const SizedBox(height: 12),

            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary,
                minimumSize: const Size.fromHeight(50),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : const Text('Add Lesson', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ),
            const SizedBox(height: 12),
          ]),
        ),
      ),
    );
  }

  Widget _field(TextEditingController c, String hint, {int maxLines = 1, TextInputType? keyboardType}) {
    return TextField(
      controller: c,
      maxLines: maxLines,
      keyboardType: keyboardType,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
        filled: true,
        fillColor: const Color(0xFFF5F6FA),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
      ),
    );
  }
}
