import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../../data/services/elearning_api_service.dart';

class LessonPlayerScreen extends ConsumerStatefulWidget {
  final int lessonId;
  const LessonPlayerScreen({super.key, required this.lessonId});

  @override
  ConsumerState<LessonPlayerScreen> createState() => _LessonPlayerScreenState();
}

class _LessonPlayerScreenState extends ConsumerState<LessonPlayerScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  final _svc = ELearningApiService.create();
  bool _completed = false;
  bool _completing = false;
  List<ELearningNote> _notes = [];
  final _noteCtrl = TextEditingController();
  bool _savingNote = false;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
    _loadNotes();
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadNotes() async {
    try {
      final notes = await _svc.getNotes(widget.lessonId);
      if (mounted) setState(() => _notes = notes);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to load notes: $e'), backgroundColor: Colors.red));
    }
  }

  Future<void> _markComplete() async {
    setState(() => _completing = true);
    try {
      final result = await _svc.completeLesson(widget.lessonId);
      setState(() { _completed = true; _completing = false; });
      if (mounted) {
        if (result['course_done'] == true) {
          _showCourseCompletedDialog(result['certificate_number']);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Lesson completed! Progress: ${result['progress_percent']}%'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
    } catch (e) {
      setState(() => _completing = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
        );
      }
    }
  }

  void _showCourseCompletedDialog(String? certNumber) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.workspace_premium_rounded, size: 72, color: Colors.amber),
          const SizedBox(height: 12),
          const Text('Course Completed!', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 8),
          const Text('Congratulations! You have completed this course.', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey)),
          if (certNumber != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: Colors.amber[50], borderRadius: BorderRadius.circular(10)),
              child: Text('Certificate: $certNumber', style: const TextStyle(fontWeight: FontWeight.w700, fontFamily: 'monospace', fontSize: 12)),
            ),
          ],
        ]),
        actions: [
          TextButton(onPressed: () { Navigator.pop(context); context.pop(); }, child: const Text('Close')),
          FilledButton(
            onPressed: () { Navigator.pop(context); context.push('/elearning/my-learning'); },
            style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
            child: const Text('View Certificate'),
          ),
        ],
      ),
    );
  }

  Future<void> _saveNote() async {
    if (_noteCtrl.text.trim().isEmpty) return;
    setState(() => _savingNote = true);
    try {
      final note = await _svc.saveNote(widget.lessonId, _noteCtrl.text.trim(), 0);
      setState(() { _notes.insert(0, note); _noteCtrl.clear(); _savingNote = false; });
    } catch (e) {
      setState(() => _savingNote = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text(
          'Lesson #${widget.lessonId}',
          style: const TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w700, fontSize: 16),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
      ),
      body: Column(
        children: [
          // ── Video Player Placeholder ─────────────────────────────────────
          Container(
            width: double.infinity,
            height: 220,
            color: AppColors.secondary,
            child: const Center(
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Icon(Icons.play_circle_fill_rounded, size: 64, color: Colors.white54),
                SizedBox(height: 8),
                Text('Video Player', style: TextStyle(color: Colors.white54, fontSize: 14)),
                Text('(chewie/video_player integration)', style: TextStyle(color: Colors.white38, fontSize: 11)),
              ]),
            ),
          ),

          // ── Complete Button ──────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.all(12),
            child: SizedBox(
              width: double.infinity,
              height: 44,
              child: FilledButton.icon(
                onPressed: _completed || _completing ? null : _markComplete,
                icon: _completing
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : Icon(_completed ? Icons.check_circle_rounded : Icons.check_circle_outline_rounded),
                label: Text(_completed ? 'Completed!' : 'Mark as Complete'),
                style: FilledButton.styleFrom(
                  backgroundColor: _completed ? Colors.green : AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ),

          // ── Tabs ─────────────────────────────────────────────────────────
          Container(
            color: Colors.white,
            child: TabBar(
              controller: _tabCtrl,
              labelColor: AppColors.primary,
              unselectedLabelColor: Colors.grey,
              indicatorColor: AppColors.primary,
              tabs: const [Tab(text: 'Notes'), Tab(text: 'Info')],
            ),
          ),

          Expanded(
            child: TabBarView(
              controller: _tabCtrl,
              children: [
                // Notes tab
                Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _noteCtrl,
                              maxLines: 2,
                              decoration: InputDecoration(
                                hintText: 'Add a note…',
                                filled: true,
                                fillColor: Colors.white,
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey[300]!)),
                                contentPadding: const EdgeInsets.all(10),
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          FilledButton(
                            onPressed: _savingNote ? null : _saveNote,
                            style: FilledButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: _savingNote
                                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                : const Icon(Icons.send_rounded),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: _notes.isEmpty
                          ? Center(child: Text('No notes yet', style: TextStyle(color: Colors.grey[400])))
                          : ListView.builder(
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              itemCount: _notes.length,
                              itemBuilder: (_, i) {
                                final note = _notes[i];
                                return Container(
                                  margin: const EdgeInsets.only(bottom: 8),
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10)),
                                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text(note.note, style: const TextStyle(fontSize: 13)),
                                    const SizedBox(height: 4),
                                    Text(note.createdAt, style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                                  ]),
                                );
                              },
                            ),
                    ),
                  ],
                ),

                // Info tab
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Text('Lesson Information', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: AppColors.secondary)),
                    const SizedBox(height: 12),
                    _InfoRow(label: 'Lesson ID', value: '${widget.lessonId}'),
                    const _InfoRow(label: 'Status', value: 'In Progress'),
                  ]),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        SizedBox(width: 100, child: Text(label, style: TextStyle(color: Colors.grey[500], fontSize: 13))),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppColors.secondary)),
      ]),
    );
  }
}
