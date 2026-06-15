import 'package:chewie/chewie.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';
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

  final _svc = ELearningApiService.create();
  late TabController _tabs;

  Map<String, dynamic>? _lesson;
  bool    _loadingLesson = true;
  String? _lessonError;

  // Direct video (mp4 / hls)
  VideoPlayerController? _vpc;
  ChewieController?      _chewie;
  bool _directVideoError = false;

  // YouTube (in-app)
  YoutubePlayerController? _ytCtrl;

  // Notes
  List<ELearningNote> _notes    = [];
  final _noteCtrl  = TextEditingController();
  bool  _savingNote = false;

  // Completion
  bool _completed  = false;
  bool _completing = false;

  @override
  void initState() {
    super.initState();
    // 3 tabs: Lessons | Notes | Resources
    _tabs = TabController(length: 3, vsync: this);
    _loadLesson();
  }

  @override
  void dispose() {
    _tabs.dispose();
    _noteCtrl.dispose();
    _vpc?.dispose();
    _chewie?.dispose();
    _ytCtrl?.close();
    super.dispose();
  }

  // ── Load ─────────────────────────────────────────────────────────────────────

  Future<void> _loadLesson() async {
    try {
      final data = await _svc.getLesson(widget.lessonId);
      if (!mounted) return;
      setState(() {
        _lesson        = data;
        _completed     = data['is_completed'] == true;
        _loadingLesson = false;
      });
      _initPlayer(data['video_url'] as String?);
      _loadNotes();
    } catch (e) {
      if (mounted) setState(() { _lessonError = e.toString(); _loadingLesson = false; });
    }
  }

  // ── Player init ───────────────────────────────────────────────────────────────

  void _initPlayer(String? url) {
    if (url == null || url.isEmpty) return;

    final ytId = _extractYouTubeId(url);
    if (ytId != null) {
      final ctrl = YoutubePlayerController(
        params: const YoutubePlayerParams(
          showControls:         true,
          showFullscreenButton: true,
          mute:                 false,
          playsInline:          true,
          enableCaption:        false,
        ),
      );
      ctrl.loadVideoById(videoId: ytId);
      setState(() => _ytCtrl = ctrl);
      return;
    }

    _initDirectVideo(url);
  }

  String? _extractYouTubeId(String url) {
    final patterns = [
      RegExp(r'[?&]v=([a-zA-Z0-9_-]{11})'),
      RegExp(r'youtu\.be/([a-zA-Z0-9_-]{11})'),
      RegExp(r'youtube\.com/embed/([a-zA-Z0-9_-]{11})'),
      RegExp(r'youtube\.com/shorts/([a-zA-Z0-9_-]{11})'),
    ];
    for (final re in patterns) {
      final m = re.firstMatch(url);
      if (m != null) return m.group(1);
    }
    return null;
  }

  Future<void> _initDirectVideo(String url) async {
    try {
      final vpc = VideoPlayerController.networkUrl(Uri.parse(url));
      await vpc.initialize();
      if (!mounted) { vpc.dispose(); return; }
      final chewie = ChewieController(
        videoPlayerController: vpc,
        autoPlay:        false,
        looping:         false,
        allowFullScreen: true,
        allowMuting:     true,
        showOptions:     false,
        deviceOrientationsAfterFullScreen: [DeviceOrientation.portraitUp],
        placeholder: Container(color: AppColors.secondary),
        errorBuilder: (_, msg) => _ErrBox(msg),
      );
      setState(() { _vpc = vpc; _chewie = chewie; });
    } catch (_) {
      if (mounted) setState(() => _directVideoError = true);
    }
  }

  // ── Notes ─────────────────────────────────────────────────────────────────────

  Future<void> _loadNotes() async {
    try {
      final notes = await _svc.getNotes(widget.lessonId);
      if (mounted) setState(() => _notes = notes);
    } catch (_) {}
  }

  Future<void> _saveNote() async {
    if (_noteCtrl.text.trim().isEmpty) return;
    setState(() => _savingNote = true);
    try {
      final pos = _vpc?.value.position.inSeconds ?? 0;
      final note = await _svc.saveNote(widget.lessonId, _noteCtrl.text.trim(), pos);
      setState(() { _notes.insert(0, note); _noteCtrl.clear(); });
    } catch (e) {
      if (mounted) _snack(e.toString(), Colors.red);
    } finally {
      if (mounted) setState(() => _savingNote = false);
    }
  }

  // ── Complete ──────────────────────────────────────────────────────────────────

  Future<void> _markComplete() async {
    setState(() => _completing = true);
    try {
      final result = await _svc.completeLesson(widget.lessonId);
      if (!mounted) return;
      setState(() { _completed = true; _completing = false; });
      if (result['course_done'] == true) {
        _showCertDialog(result['certificate_number'] as String?);
      } else {
        _snack('Lesson completed! ${result['progress_percent']}% done', Colors.green);
      }
    } catch (e) {
      if (mounted) setState(() => _completing = false);
      if (mounted) _snack(e.toString(), Colors.red);
    }
  }

  void _showCertDialog(String? cert) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.workspace_premium_rounded, size: 72, color: Colors.amber),
          const SizedBox(height: 12),
          const Text('Course Completed!',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800,
                  color: AppColors.secondary)),
          const SizedBox(height: 8),
          const Text('Congratulations! You have finished this course.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey)),
          if (cert != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                  color: Colors.amber[50], borderRadius: BorderRadius.circular(10)),
              child: Text('Certificate: $cert',
                  style: const TextStyle(fontWeight: FontWeight.w700,
                      fontFamily: 'monospace', fontSize: 12)),
            ),
          ],
        ]),
        actions: [
          TextButton(
              onPressed: () { Navigator.pop(context); context.pop(); },
              child: const Text('Close')),
          FilledButton(
            onPressed: () {
              Navigator.pop(context);
              context.push('/elearning/my-learning');
            },
            style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
            child: const Text('My Learning'),
          ),
        ],
      ),
    );
  }

  void _snack(String msg, [Color? bg]) =>
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: bg));

  // ── Build ─────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    if (_loadingLesson) {
      return const Scaffold(
        backgroundColor: AppColors.secondary,
        body: Center(child: CircularProgressIndicator(color: Colors.white)),
      );
    }
    if (_lessonError != null) {
      return Scaffold(
        appBar: AppBar(
          backgroundColor: Colors.white,
          leading: IconButton(
              icon: const Icon(Icons.arrow_back_ios_new_rounded,
                  color: AppColors.secondary),
              onPressed: () => context.pop()),
          title: const Text('Lesson Player',
              style: TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w700)),
        ),
        body: Center(
            child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(_lessonError!, style: const TextStyle(color: Colors.red)),
        )),
      );
    }

    final title      = _lesson!['title']         as String? ?? '';
    final type       = _lesson!['type']          as String? ?? 'video';
    final content    = _lesson!['content']       as String?;
    final courseTitle = _lesson!['course_title'] as String?;
    final sectionTitle = _lesson!['section_title'] as String?;

    return _buildScaffold(
        title: title,
        type: type,
        content: content,
        courseTitle: courseTitle,
        sectionTitle: sectionTitle);
  }

  Scaffold _buildScaffold({
    required String  title,
    required String  type,
    required String? content,
    required String? courseTitle,
    required String? sectionTitle,
  }) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded,
              color: AppColors.secondary, size: 20),
          onPressed: () => context.pop(),
        ),
        title: const Text(
          'Lesson Player',
          style: TextStyle(
              color: AppColors.secondary, fontWeight: FontWeight.w700, fontSize: 16),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.more_vert_rounded, color: AppColors.secondary),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(children: [

        // ── Media ─────────────────────────────────────────────────────────
        _buildMedia(type, content),

        // ── Course title + section info ───────────────────────────────────
        Container(
          width: double.infinity,
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (courseTitle != null)
              Text(
                courseTitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.secondary),
              ),
            if (sectionTitle != null) ...[
              const SizedBox(height: 2),
              Text(
                sectionTitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 12, color: Colors.grey[500]),
              ),
            ],
          ]),
        ),

        // ── Complete button ───────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: SizedBox(
            width: double.infinity,
            height: 46,
            child: FilledButton.icon(
              onPressed: (_completed || _completing) ? null : _markComplete,
              style: FilledButton.styleFrom(
                backgroundColor: _completed ? Colors.green : AppColors.primary,
                disabledBackgroundColor:
                    _completed ? Colors.green : Colors.grey[300],
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              icon: _completing
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2))
                  : Icon(_completed
                      ? Icons.check_circle_rounded
                      : Icons.check_circle_outline_rounded),
              label: Text(
                  _completed ? 'Completed' : 'Mark as Complete',
                  style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ),

        // ── Tabs: Lessons | Notes | Resources ────────────────────────────
        Container(
          color: Colors.white,
          child: TabBar(
            controller: _tabs,
            labelColor: AppColors.primary,
            unselectedLabelColor: Colors.grey,
            indicatorColor: AppColors.primary,
            indicatorWeight: 2.5,
            labelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            tabs: const [
              Tab(text: 'Lessons'),
              Tab(text: 'Notes'),
              Tab(text: 'Resources'),
            ],
          ),
        ),
        Expanded(
            child: TabBarView(controller: _tabs, children: [
          _LessonsTab(lesson: _lesson!),
          _NotesTab(
              notes: _notes,
              ctrl: _noteCtrl,
              saving: _savingNote,
              onSend: _saveNote),
          const _ResourcesTab(),
        ])),
      ]),
    );
  }

  Widget _buildMedia(String type, String? content) {
    // YouTube in-app
    if (_ytCtrl != null) {
      return YoutubePlayerControllerProvider(
        controller: _ytCtrl!,
        child: YoutubePlayer(controller: _ytCtrl!),
      );
    }

    // Chewie direct video
    if (_chewie != null) {
      return AspectRatio(
        aspectRatio: 16 / 9,
        child: Chewie(controller: _chewie!),
      );
    }

    // Still initialising direct video
    if (type == 'video' && !_directVideoError && _vpc == null) {
      return AspectRatio(
        aspectRatio: 16 / 9,
        child: Container(
          color: AppColors.secondary,
          child: const Center(
              child: CircularProgressIndicator(color: Colors.white)),
        ),
      );
    }

    // Direct video failed
    if (_directVideoError) return const _ErrBox('Could not load video.');

    // Text / document content
    if (content != null && content.isNotEmpty) {
      return Container(
        width: double.infinity,
        constraints: const BoxConstraints(maxHeight: 200),
        color: AppColors.secondary,
        padding: const EdgeInsets.all(16),
        child: SingleChildScrollView(
          child: Text(content,
              style: const TextStyle(color: Colors.white70, fontSize: 13, height: 1.6)),
        ),
      );
    }

    // No media
    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Container(
        color: AppColors.secondary,
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(_typeIcon(type), size: 48, color: Colors.white38),
          const SizedBox(height: 8),
          Text(type.toUpperCase(),
              style: const TextStyle(
                  color: Colors.white38, fontSize: 12, letterSpacing: 1)),
        ]),
      ),
    );
  }

  IconData _typeIcon(String t) => switch (t) {
    'pdf'        => Icons.picture_as_pdf_rounded,
    'quiz'       => Icons.quiz_rounded,
    'assignment' => Icons.assignment_rounded,
    'live'       => Icons.videocam_rounded,
    _            => Icons.play_circle_rounded,
  };
}

// ─── Error box ────────────────────────────────────────────────────────────────

class _ErrBox extends StatelessWidget {
  final String message;
  const _ErrBox(this.message);

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Container(
        color: const Color(0xFF1A1A2E),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 40),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Text(message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white60, fontSize: 12)),
          ),
        ]),
      ),
    );
  }
}

// ─── Lessons tab — shows current lesson info + link to course curriculum ──────

class _LessonsTab extends StatelessWidget {
  final Map<String, dynamic> lesson;
  const _LessonsTab({required this.lesson});

  @override
  Widget build(BuildContext context) {
    final title    = lesson['title']         as String? ?? '';
    final type     = lesson['type']          as String? ?? '';
    final dur      = (lesson['video_duration_seconds'] as num?)?.toInt() ?? 0;
    final course   = lesson['course_title']  as String?;
    final section  = lesson['section_title'] as String?;
    final isCompleted = lesson['is_completed'] == true;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Current lesson highlighted card
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFE8F4FF),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.blue[200]!),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.secondary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  isCompleted
                      ? Icons.check_circle_rounded
                      : Icons.play_arrow_rounded,
                  color: isCompleted ? Colors.greenAccent : Colors.white,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Now Playing',
                      style: TextStyle(
                          fontSize: 11,
                          color: Colors.blue,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.3)),
                  const SizedBox(height: 2),
                  Text(title,
                      style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.secondary),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis),
                  if (dur > 0) ...[
                    const SizedBox(height: 3),
                    Text(_fmt(dur),
                        style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                  ],
                ]),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Lesson info rows
        const Text('Lesson Details',
            style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 15,
                color: AppColors.secondary)),
        const SizedBox(height: 10),
        if (course != null) _infoRow('Course', course),
        if (section != null) _infoRow('Section', section),
        _infoRow('Type', type.toUpperCase()),
        if (dur > 0) _infoRow('Duration', _fmt(dur)),
        const SizedBox(height: 24),

        // CTA to full curriculum
        OutlinedButton.icon(
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.primary,
            side: const BorderSide(color: AppColors.primary),
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onPressed: () => context.pop(),
          icon: const Icon(Icons.list_rounded, size: 18),
          label: const Text('See Full Curriculum',
              style: TextStyle(fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }

  Widget _infoRow(String label, String value) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(
              width: 80,
              child: Text(label,
                  style: TextStyle(color: Colors.grey[500], fontSize: 13))),
          Expanded(
              child: Text(value,
                  style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      color: AppColors.secondary))),
        ]),
      );

  String _fmt(int s) {
    final m = s ~/ 60, r = s % 60;
    return '${m.toString().padLeft(2, '0')}:${r.toString().padLeft(2, '0')}';
  }
}

// ─── Notes tab ────────────────────────────────────────────────────────────────

class _NotesTab extends StatelessWidget {
  final List<ELearningNote> notes;
  final TextEditingController ctrl;
  final bool saving;
  final VoidCallback onSend;
  const _NotesTab(
      {required this.notes,
      required this.ctrl,
      required this.saving,
      required this.onSend});

  @override
  Widget build(BuildContext context) => Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
          child: Row(children: [
            Expanded(
                child: TextField(
              controller: ctrl,
              maxLines: 2,
              decoration: InputDecoration(
                hintText: 'Add a note…',
                filled: true,
                fillColor: Colors.white,
                contentPadding: const EdgeInsets.all(10),
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: BorderSide(color: Colors.grey[300]!)),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: BorderSide(color: Colors.grey[300]!)),
              ),
            )),
            const SizedBox(width: 8),
            FilledButton(
              onPressed: saving ? null : onSend,
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10)),
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              ),
              child: saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2))
                  : const Icon(Icons.send_rounded),
            ),
          ]),
        ),
        Expanded(
          child: notes.isEmpty
              ? Center(
                  child: Text('No notes yet',
                      style: TextStyle(color: Colors.grey[400])))
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  itemCount: notes.length,
                  itemBuilder: (_, i) {
                    final n = notes[i];
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(10)),
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(n.note, style: const TextStyle(fontSize: 13)),
                            const SizedBox(height: 4),
                            Text(n.createdAt,
                                style: TextStyle(
                                    fontSize: 11, color: Colors.grey[400])),
                          ]),
                    );
                  }),
        ),
      ]);
}

// ─── Resources tab ────────────────────────────────────────────────────────────

class _ResourcesTab extends StatelessWidget {
  const _ResourcesTab();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.folder_open_rounded, size: 56, color: Colors.grey[300]),
          const SizedBox(height: 12),
          Text('No resources available',
              style: TextStyle(color: Colors.grey[400], fontSize: 15)),
          const SizedBox(height: 6),
          Text('Resources for this lesson will appear here.',
              style: TextStyle(color: Colors.grey[350], fontSize: 12)),
        ],
      ),
    );
  }
}
