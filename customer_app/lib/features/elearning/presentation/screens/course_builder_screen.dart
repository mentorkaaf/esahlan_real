import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../providers/elearning_provider.dart';

class CourseBuilderScreen extends ConsumerStatefulWidget {
  final int courseId;
  final String? courseTitle;
  const CourseBuilderScreen({super.key, required this.courseId, this.courseTitle});

  @override
  ConsumerState<CourseBuilderScreen> createState() => _CourseBuilderScreenState();
}

class _CourseBuilderScreenState extends ConsumerState<CourseBuilderScreen> {
  int get courseId => widget.courseId;

  Future<void> _showEditSheet(Map<String, dynamic> courseData) async {
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditCourseSheet(courseId: courseId, data: courseData,
          onSaved: () => ref.invalidate(courseStructureProvider(courseId))),
    );
  }

  Future<void> _confirmDelete(String status) async {
    if (status == 'published') {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Published courses cannot be deleted')));
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete Course?'),
        content: const Text('This will permanently delete the course and all its sections and lessons. This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogCtx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(dialogCtx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok == true) {
      try {
        await ref.read(elearningServiceProvider).deleteCourse(courseId);
        ref.invalidate(instructorCoursesProvider);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Course deleted'), backgroundColor: Colors.red));
          context.pop();
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final structAsync = ref.watch(courseStructureProvider(courseId));

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text(widget.courseTitle ?? 'Course Content',
            maxLines: 1, overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
        actions: [
          structAsync.when(
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
            data: (data) => PopupMenuButton<String>(
              icon: const Icon(Icons.more_vert_rounded, color: AppColors.secondary),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              onSelected: (v) {
                if (v == 'edit') _showEditSheet(data);
                if (v == 'delete') _confirmDelete(data['status'] as String? ?? 'draft');
              },
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'edit', child: Row(children: [
                  Icon(Icons.edit_rounded, size: 18, color: AppColors.primary),
                  SizedBox(width: 10),
                  Text('Edit Details'),
                ])),
                const PopupMenuItem(value: 'delete', child: Row(children: [
                  Icon(Icons.delete_rounded, size: 18, color: Colors.red),
                  SizedBox(width: 10),
                  Text('Delete Course', style: TextStyle(color: Colors.red)),
                ])),
              ],
            ),
          ),
        ],
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
                  onPressed: () => _addSection(context),
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
                    onPressed: totalLessons > 0 ? () => _submitForReview(context) : null,
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

  Future<void> _addSection(BuildContext context) async {
    final title = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _AddSectionSheet(),
    );
    if (title == null || title.trim().isEmpty) return;
    try {
      await ref.read(elearningServiceProvider).addSection(courseId: courseId, title: title.trim());
      // Invalidate + setState: provider marks stale, build() re-watches and fetches fresh data
      ref.invalidate(courseStructureProvider(courseId));
      if (mounted) setState(() {});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Section added'), backgroundColor: Colors.green));
      }
    } on DioException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(e.response?.data?['message']?.toString() ?? 'Failed to add section'),
            backgroundColor: Colors.red));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text('Error: $e'), backgroundColor: Colors.red));
      }
    }
  }

  Future<void> _submitForReview(BuildContext context) async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetCtx) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          const Icon(Icons.send_rounded, color: AppColors.secondary, size: 40),
          const SizedBox(height: 12),
          const Text('Submit for Review?',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 8),
          Text('Admin ayaa course-kaaga dib u eegi doona ka hor inta uusan nool noqon.',
              textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600], height: 1.5)),
          const SizedBox(height: 24),
          Row(children: [
            Expanded(child: OutlinedButton(
              onPressed: () => Navigator.pop(sheetCtx, false),
              child: const Text('Cancel'),
            )),
            const SizedBox(width: 12),
            Expanded(child: FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppColors.secondary),
              onPressed: () => Navigator.pop(sheetCtx, true),
              child: const Text('Submit'),
            )),
          ]),
          const SizedBox(height: 8),
        ]),
      ),
    );
    if (ok == true) {
      try {
        await ref.read(elearningServiceProvider).submitCourseForReview(courseId);
        ref.invalidate(courseStructureProvider(courseId));
        ref.invalidate(instructorCoursesProvider);
        if (mounted) setState(() {});
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

// ── Add Section bottom sheet (avoids GoRouter ShellRoute navigator bug) ───────
class _AddSectionSheet extends StatefulWidget {
  const _AddSectionSheet();

  @override
  State<_AddSectionSheet> createState() => _AddSectionSheetState();
}

class _AddSectionSheetState extends State<_AddSectionSheet> {
  final _ctrl = TextEditingController();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(20),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          const Text('Add Section',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 16),
          TextField(
            controller: _ctrl,
            autofocus: true,
            textInputAction: TextInputAction.done,
            onSubmitted: (v) {
              if (v.trim().isNotEmpty) Navigator.pop(context, v.trim());
            },
            decoration: InputDecoration(
              hintText: 'Tusaale: Introduction, Chapter 1...',
              hintStyle: TextStyle(color: Colors.grey[400]),
              filled: true,
              fillColor: const Color(0xFFF5F6FA),
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
            ),
          ),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: OutlinedButton(
              onPressed: () => Navigator.pop(context, null),
              child: const Text('Cancel'),
            )),
            const SizedBox(width: 12),
            Expanded(child: FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
              onPressed: () {
                final v = _ctrl.text.trim();
                if (v.isNotEmpty) Navigator.pop(context, v);
              },
              child: const Text('Add', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
          ]),
          const SizedBox(height: 8),
        ]),
      ),
    );
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
  XFile? _videoFile;

  @override
  void dispose() {
    _titleCtrl.dispose();
    _videoUrlCtrl.dispose();
    _durationCtrl.dispose();
    _contentCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickVideo() async {
    final picked = await ImagePicker().pickVideo(source: ImageSource.gallery);
    if (picked != null) setState(() => _videoFile = picked);
  }

  Future<void> _save() async {
    if (_titleCtrl.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      MultipartFile? videoMultipart;
      if (_videoFile != null) {
        videoMultipart = await MultipartFile.fromFile(
          _videoFile!.path,
          filename: _videoFile!.name,
        );
      }
      await ref.read(elearningServiceProvider).addLesson(
            sectionId: widget.sectionId,
            title: _titleCtrl.text.trim(),
            type: _type,
            videoUrl: _videoUrlCtrl.text.trim().isEmpty ? null : _videoUrlCtrl.text.trim(),
            videoDurationSeconds: int.tryParse(_durationCtrl.text.trim()),
            content: _contentCtrl.text.trim(),
            isFreePreview: _freePreview,
            videoFile: videoMultipart,
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
                  onChanged: (v) => setState(() { _type = v ?? 'video'; _videoFile = null; }),
                ),
              ),
            ),
            const SizedBox(height: 12),

            if (isVideo) ...[
              // Video: URL or file upload
              _field(_videoUrlCtrl, 'Video URL (YouTube / Vimeo / direct link)'),
              const SizedBox(height: 8),
              const Center(child: Text('— OR —', style: TextStyle(color: Colors.grey, fontSize: 12))),
              const SizedBox(height: 8),
              GestureDetector(
                onTap: _pickVideo,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
                  decoration: BoxDecoration(
                    color: _videoFile != null
                        ? Colors.green.withValues(alpha: 0.08)
                        : const Color(0xFFF5F6FA),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: _videoFile != null ? Colors.green : Colors.grey[300]!,
                      style: BorderStyle.solid,
                    ),
                  ),
                  child: Row(children: [
                    Icon(
                      _videoFile != null ? Icons.check_circle_rounded : Icons.video_library_rounded,
                      color: _videoFile != null ? Colors.green : Colors.grey[500],
                      size: 22,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _videoFile != null
                            ? _videoFile!.name
                            : 'Upload video from gallery',
                        style: TextStyle(
                          color: _videoFile != null ? Colors.green[700] : Colors.grey[600],
                          fontWeight: _videoFile != null ? FontWeight.w600 : FontWeight.normal,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (_videoFile != null)
                      GestureDetector(
                        onTap: () => setState(() => _videoFile = null),
                        child: Icon(Icons.close, size: 16, color: Colors.grey[400]),
                      ),
                  ]),
                ),
              ),
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

// ── Edit Course Details Sheet ─────────────────────────────────────────────────
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
  late final TextEditingController _requirementsCtrl;
  int? _categoryId;
  String _level = 'all';
  String _language = 'so';
  bool _isFree = false;
  MultipartFile? _thumb;
  String? _thumbName;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final d = widget.data;
    _titleCtrl = TextEditingController(text: d['title'] as String? ?? '');
    _subtitleCtrl = TextEditingController(text: d['subtitle'] as String? ?? '');
    _descCtrl = TextEditingController(text: d['description'] as String? ?? '');
    _priceCtrl = TextEditingController(text: (d['price'] ?? '').toString());
    final outcomes = (d['learning_outcomes'] as List<dynamic>? ?? []).join('\n');
    _outcomesCtrl = TextEditingController(text: outcomes);
    final reqs = (d['requirements'] as List<dynamic>? ?? []).join('\n');
    _requirementsCtrl = TextEditingController(text: reqs);
    _isFree = d['is_free'] == true;
    _level = d['level'] as String? ?? 'all';
    _language = d['language'] as String? ?? 'so';
    final cat = d['category'];
    if (cat is Map) _categoryId = cat['id'] as int?;
  }

  @override
  void dispose() {
    _titleCtrl.dispose(); _subtitleCtrl.dispose(); _descCtrl.dispose();
    _priceCtrl.dispose(); _outcomesCtrl.dispose(); _requirementsCtrl.dispose();
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

  List<String> _lines(String s) =>
      s.split('\n').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();

  Future<void> _save() async {
    if (_titleCtrl.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(elearningServiceProvider).updateCourse(
        widget.courseId,
        title: _titleCtrl.text.trim(),
        subtitle: _subtitleCtrl.text.trim(),
        description: _descCtrl.text.trim(),
        categoryId: _categoryId,
        level: _level,
        language: _language,
        price: _isFree ? 0 : (double.tryParse(_priceCtrl.text.trim()) ?? 0),
        isFree: _isFree,
        learningOutcomes: _lines(_outcomesCtrl.text),
        requirements: _lines(_requirementsCtrl.text),
        thumbnail: _thumb,
      );
      widget.onSaved();
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Course updated!'), backgroundColor: Colors.green));
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
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          // Header
          Container(
            padding: const EdgeInsets.fromLTRB(20, 16, 8, 12),
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [AppColors.secondary, Color(0xFF3D2B8E)]),
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: Row(children: [
              const Expanded(child: Text('Edit Course Details',
                  style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800))),
              IconButton(onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close, color: Colors.white70)),
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
                    height: 90,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF5F6FA),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: _thumb != null ? AppColors.primary : Colors.grey[300]!),
                    ),
                    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(_thumb != null ? Icons.check_circle_rounded : Icons.add_photo_alternate_rounded,
                          color: _thumb != null ? Colors.green : Colors.grey[400], size: 24),
                      const SizedBox(width: 10),
                      Text(_thumb != null ? _thumbName! : 'Change thumbnail',
                          style: TextStyle(color: _thumb != null ? Colors.green[700] : Colors.grey[500])),
                    ]),
                  ),
                ),
                const SizedBox(height: 14),

                _lbl('Title'), _tf(_titleCtrl, 'Course title'),
                const SizedBox(height: 12),
                _lbl('Subtitle'), _tf(_subtitleCtrl, 'Short tagline'),
                const SizedBox(height: 12),
                _lbl('Description'), _tf(_descCtrl, 'What will students learn?', lines: 3),
                const SizedBox(height: 12),

                _lbl('Category'),
                cats.when(
                  loading: () => const LinearProgressIndicator(color: AppColors.primary),
                  error: (_, __) => const SizedBox.shrink(),
                  data: (list) => _drop<int?>(
                    value: _categoryId,
                    items: list.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                    hint: 'Select category',
                    onChanged: (v) => setState(() => _categoryId = v),
                  ),
                ),
                const SizedBox(height: 12),

                Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _lbl('Level'),
                    _drop<String>(value: _level, items: const [
                      DropdownMenuItem(value: 'all', child: Text('All Levels')),
                      DropdownMenuItem(value: 'beginner', child: Text('Beginner')),
                      DropdownMenuItem(value: 'intermediate', child: Text('Intermediate')),
                      DropdownMenuItem(value: 'advanced', child: Text('Advanced')),
                    ], onChanged: (v) => setState(() => _level = v!)),
                  ])),
                  const SizedBox(width: 10),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _lbl('Language'),
                    _drop<String>(value: _language, items: const [
                      DropdownMenuItem(value: 'so', child: Text('Somali')),
                      DropdownMenuItem(value: 'en', child: Text('English')),
                      DropdownMenuItem(value: 'ar', child: Text('Arabic')),
                    ], onChanged: (v) => setState(() => _language = v!)),
                  ])),
                ]),
                const SizedBox(height: 12),

                // Free toggle
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  activeColor: AppColors.primary,
                  title: const Text('Bilaash (Free)',
                      style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.secondary, fontSize: 14)),
                  value: _isFree,
                  onChanged: (v) => setState(() => _isFree = v),
                ),
                if (!_isFree) ...[
                  _lbl('Price (\$)'),
                  _tf(_priceCtrl, 'e.g. 19.99', type: TextInputType.number),
                  const SizedBox(height: 12),
                ],

                _lbl('Learning Outcomes (mid kasta mid line ah)'),
                _tf(_outcomesCtrl, 'Build real apps\nMaster Riverpod\n...', lines: 3),
                const SizedBox(height: 12),
                _lbl('Requirements (mid kasta mid line ah)'),
                _tf(_requirementsCtrl, 'Computer\nBasic Dart\n...', lines: 2),
                const SizedBox(height: 20),

                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    minimumSize: const Size.fromHeight(50),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(height: 20, width: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Save Changes', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                ),
                const SizedBox(height: 16),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _lbl(String t) => Padding(
      padding: const EdgeInsets.only(bottom: 5, left: 2),
      child: Text(t, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.secondary, fontSize: 12)));

  Widget _tf(TextEditingController c, String hint, {int lines = 1, TextInputType? type}) =>
      TextField(
        controller: c,
        maxLines: lines,
        keyboardType: type,
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
          filled: true,
          fillColor: const Color(0xFFF5F6FA),
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
        ),
      );

  Widget _drop<T>({required T value, required List<DropdownMenuItem<T>> items,
      required ValueChanged<T?> onChanged, String? hint}) =>
      Container(
        decoration: BoxDecoration(color: const Color(0xFFF5F6FA), borderRadius: BorderRadius.circular(10)),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<T>(
            isExpanded: true,
            value: value,
            hint: hint != null ? Text(hint) : null,
            items: items,
            onChanged: onChanged,
          ),
        ),
      );
}
