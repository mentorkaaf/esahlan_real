import 'dart:io';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import '../../data/repositories/podcast_repository.dart';

const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);
const _kBg     = Color(0xFFF0F2F5);

class CreatorStudioScreen extends StatefulWidget {
  const CreatorStudioScreen({super.key});
  @override
  State<CreatorStudioScreen> createState() => _CreatorStudioScreenState();
}

class _CreatorStudioScreenState extends State<CreatorStudioScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final _myShowsKey = GlobalKey<_MyShowsTabState>();

  @override
  void initState() { super.initState(); _tabs = TabController(length: 2, vsync: this); }
  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  void _onUploadSuccess() {
    _tabs.animateTo(1);
    // Small delay to let the tab switch complete before refreshing
    Future.delayed(const Duration(milliseconds: 300), () {
      _myShowsKey.currentState?._load();
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: _kBg,
    appBar: AppBar(
      backgroundColor: _kNavy,
      title: const Text('Creator Studio',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
      iconTheme: const IconThemeData(color: Colors.white),
      bottom: TabBar(
        controller: _tabs,
        indicatorColor: _kOrange,
        indicatorWeight: 3,
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white54,
        tabs: const [Tab(text: 'Upload Audio'), Tab(text: 'My Shows')],
      ),
    ),
    body: TabBarView(controller: _tabs, children: [
      _UploadTab(
        onShowsTab: () => _tabs.animateTo(1),
        onUploadSuccess: _onUploadSuccess,
      ),
      _MyShowsTab(
        key: _myShowsKey,
        onUploadTab: () => _tabs.animateTo(0),
      ),
    ]),
  );
}

// ─── Upload Tab ───────────────────────────────────────────────────────────────

class _UploadTab extends StatefulWidget {
  final VoidCallback onShowsTab;
  final VoidCallback onUploadSuccess;
  const _UploadTab({required this.onShowsTab, required this.onUploadSuccess});
  @override
  State<_UploadTab> createState() => _UploadTabState();
}

class _UploadTabState extends State<_UploadTab> {
  final _titleCtrl = TextEditingController();
  final _descCtrl  = TextEditingController();
  final _formKey   = GlobalKey<FormState>();
  final _repo      = PodcastRepository();

  String? _selectedCategory;
  String  _privacy   = 'public';
  bool    _uploading = false;
  File?   _audioFile;
  String? _audioName;
  File?   _coverFile;

  final _categories = [
    'Business', 'Education', 'Religion', 'Technology',
    'Health', 'Finance', 'Comedy', 'Sports', 'Politics',
    'Motivation', 'Lifestyle', 'News', 'Entertainment',
    'Science', 'History', 'Kids', 'Audiobooks', 'Languages',
  ];

  @override
  void dispose() { _titleCtrl.dispose(); _descCtrl.dispose(); super.dispose(); }

  Future<void> _pickAudio() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.audio,
      allowMultiple: false,
    );
    if (result != null && result.files.single.path != null) {
      setState(() {
        _audioFile = File(result.files.single.path!);
        _audioName = result.files.single.name;
      });
    }
  }

  Future<void> _pickCover() async {
    final picker = ImagePicker();
    final img = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (img != null) setState(() => _coverFile = File(img.path));
  }

  Future<void> _publish() async {
    if (!_formKey.currentState!.validate()) return;
    if (_audioFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Please select an audio file'), backgroundColor: Colors.red));
      return;
    }
    setState(() => _uploading = true);
    try {
      await _repo.uploadEpisode(
        audioFile: _audioFile!,
        coverFile: _coverFile,
        title: _titleCtrl.text.trim(),
        description: _descCtrl.text.trim(),
        category: _selectedCategory ?? '',
        privacy: _privacy,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Episode uploaded successfully!'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2)));
      _titleCtrl.clear(); _descCtrl.clear();
      setState(() { _audioFile = null; _audioName = null; _coverFile = null; _selectedCategory = null; });
      widget.onUploadSuccess();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Upload failed: $e'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.all(16),
    child: Form(
      key: _formKey,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // ── Audio File Picker ──
        GestureDetector(
          onTap: _pickAudio,
          child: Container(
            height: 110,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                  color: _audioFile != null ? _kOrange : _kOrange.withAlpha(60),
                  width: 1.5),
            ),
            child: _audioFile != null
                ? Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Container(
                      width: 48, height: 48,
                      decoration: const BoxDecoration(color: _kOrange, shape: BoxShape.circle),
                      child: const Icon(Icons.audio_file_rounded, color: Colors.white, size: 24),
                    ),
                    const SizedBox(width: 14),
                    Expanded(child: Column(mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(_audioName ?? '', style: const TextStyle(
                          color: _kNavy, fontWeight: FontWeight.w700, fontSize: 13),
                          maxLines: 2, overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 4),
                      const Text('Tap to change file',
                          style: TextStyle(color: Colors.grey, fontSize: 11)),
                    ])),
                    const SizedBox(width: 12),
                  ])
                : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.cloud_upload_rounded, size: 40, color: _kOrange),
                    const SizedBox(height: 8),
                    const Text('Upload Audio File',
                        style: TextStyle(color: _kNavy, fontWeight: FontWeight.w700, fontSize: 14)),
                    const SizedBox(height: 2),
                    const Text('MP3, WAV, M4A (Max 200MB)',
                        style: TextStyle(color: Colors.grey, fontSize: 11)),
                  ]),
          ),
        ),

        const SizedBox(height: 16),

        // ── Title ──
        _label('Title'),
        TextFormField(
          controller: _titleCtrl,
          validator: (v) => v == null || v.isEmpty ? 'Title is required' : null,
          decoration: _inputDec('Enter audio title'),
        ),

        const SizedBox(height: 12),

        // ── Description ──
        _label('Description'),
        TextFormField(
          controller: _descCtrl,
          maxLines: 4,
          decoration: _inputDec('Tell listeners about this episode...'),
        ),

        const SizedBox(height: 12),

        // ── Category ──
        _label('Category'),
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.grey.shade200)),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              isExpanded: true,
              value: _selectedCategory,
              hint: const Text('Select category', style: TextStyle(color: Colors.grey)),
              onChanged: (v) => setState(() => _selectedCategory = v),
              items: _categories.map((c) =>
                  DropdownMenuItem(value: c, child: Text(c))).toList(),
            ),
          ),
        ),

        const SizedBox(height: 12),

        // ── Cover Image ──
        _label('Cover Image'),
        GestureDetector(
          onTap: _pickCover,
          child: Container(
            height: 70,
            decoration: BoxDecoration(
              color: Colors.white, borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: _coverFile != null
                ? Row(children: [
                    const SizedBox(width: 12),
                    ClipRRect(borderRadius: BorderRadius.circular(8),
                        child: Image.file(_coverFile!, width: 48, height: 48, fit: BoxFit.cover)),
                    const SizedBox(width: 12),
                    const Text('Cover selected — tap to change',
                        style: TextStyle(color: _kNavy, fontWeight: FontWeight.w600, fontSize: 13)),
                  ])
                : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.add_photo_alternate_rounded, color: _kOrange),
                    const SizedBox(width: 8),
                    const Text('Upload cover image',
                        style: TextStyle(color: _kOrange, fontWeight: FontWeight.w600)),
                  ]),
          ),
        ),

        const SizedBox(height: 12),

        // ── Privacy ──
        _label('Privacy'),
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.grey.shade200)),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              isExpanded: true,
              value: _privacy,
              onChanged: (v) => setState(() => _privacy = v!),
              items: const [
                DropdownMenuItem(value: 'public',  child: Text('Public')),
                DropdownMenuItem(value: 'private', child: Text('Private')),
              ],
            ),
          ),
        ),

        const SizedBox(height: 20),

        // ── RSS Import ──
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _kNavy.withAlpha(8),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _kNavy.withAlpha(25)),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Row(children: [
              Icon(Icons.rss_feed_rounded, color: _kNavy, size: 16),
              SizedBox(width: 6),
              Text('RSS Feed Import',
                  style: TextStyle(color: _kNavy, fontWeight: FontWeight.w700, fontSize: 13)),
            ]),
            const SizedBox(height: 4),
            const Text('Import your existing podcast from any RSS feed URL.',
                style: TextStyle(color: Colors.grey, fontSize: 11)),
            const SizedBox(height: 10),
            GestureDetector(
              onTap: () => _showRssDialog(context),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(color: _kNavy, borderRadius: BorderRadius.circular(8)),
                child: const Text('Import from RSS',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
              ),
            ),
          ]),
        ),

        const SizedBox(height: 24),

        // ── Publish ──
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _uploading ? null : _publish,
            style: ElevatedButton.styleFrom(
              backgroundColor: _kOrange, foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: _uploading
                ? const SizedBox(width: 20, height: 20,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Text('Publish',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ),
        ),
        const SizedBox(height: 40),
      ]),
    ),
  );

  void _showRssDialog(BuildContext ctx) {
    final ctrl = TextEditingController();
    showDialog(
      context: ctx,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('RSS Feed Import',
            style: TextStyle(color: _kNavy, fontWeight: FontWeight.w800)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Enter your podcast RSS feed URL:',
              style: TextStyle(color: Colors.grey, fontSize: 13)),
          const SizedBox(height: 12),
          TextField(
            controller: ctrl,
            decoration: const InputDecoration(
              hintText: 'https://your-podcast.com/feed.rss',
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.rss_feed_rounded, color: _kOrange),
            ),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              if (ctrl.text.isEmpty) return;
              Navigator.pop(ctx);
              _showRssPreview(ctx, ctrl.text);
            },
            style: ElevatedButton.styleFrom(
                backgroundColor: _kOrange, foregroundColor: Colors.white),
            child: const Text('Preview'),
          ),
        ],
      ),
    );
  }

  void _showRssPreview(BuildContext ctx, String url) async {
    showDialog(
      context: ctx, barrierDismissible: false,
      builder: (_) => const AlertDialog(
        content: Row(children: [
          CircularProgressIndicator(color: _kOrange),
          SizedBox(width: 16),
          Text('Loading RSS feed...'),
        ]),
      ),
    );
    try {
      final repo = PodcastRepository();
      final data = await repo.getRssPreview(url);
      if (ctx.mounted) Navigator.pop(ctx);
      if (!ctx.mounted) return;
      showDialog(
        context: ctx,
        builder: (_) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Text(data['title'] ?? 'RSS Preview',
              style: const TextStyle(color: _kNavy, fontWeight: FontWeight.w800)),
          content: SingleChildScrollView(child: Column(
              crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (data['description'] != null)
              Text(data['description'],
                  style: const TextStyle(color: Colors.grey, fontSize: 12)),
            const SizedBox(height: 8),
            Text('${(data['episodes'] as List?)?.length ?? 0} episodes found',
                style: const TextStyle(color: _kOrange, fontWeight: FontWeight.w700)),
          ])),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx),
              style: ElevatedButton.styleFrom(
                  backgroundColor: _kOrange, foregroundColor: Colors.white),
              child: const Text('Import'),
            ),
          ],
        ),
      );
    } catch (e) {
      if (ctx.mounted) Navigator.pop(ctx);
      if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red));
    }
  }

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text,
        style: const TextStyle(color: _kNavy, fontWeight: FontWeight.w700, fontSize: 13)),
  );

  InputDecoration _inputDec(String hint) => InputDecoration(
    hintText: hint, hintStyle: const TextStyle(color: Colors.grey),
    filled: true, fillColor: Colors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade200)),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade200)),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: _kOrange)),
  );
}

// ─── My Shows Tab ─────────────────────────────────────────────────────────────

class _MyShowsTab extends StatefulWidget {
  final VoidCallback onUploadTab;
  const _MyShowsTab({super.key, required this.onUploadTab});
  @override
  State<_MyShowsTab> createState() => _MyShowsTabState();
}

class _MyShowsTabState extends State<_MyShowsTab> {
  final _repo = PodcastRepository();
  List<dynamic> _shows = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final d = await _repo.getMyShows();
      if (mounted) setState(() { _shows = d; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator(color: _kOrange));
    if (_error != null) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.error_outline_rounded, color: Colors.red, size: 48),
      const SizedBox(height: 12),
      Text(_error!, style: const TextStyle(color: Colors.grey), textAlign: TextAlign.center),
      const SizedBox(height: 16),
      ElevatedButton(onPressed: _load,
          style: ElevatedButton.styleFrom(backgroundColor: _kOrange, foregroundColor: Colors.white),
          child: const Text('Retry')),
    ]));

    if (_shows.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(
        width: 80, height: 80,
        decoration: BoxDecoration(
            color: _kNavy.withAlpha(10), shape: BoxShape.circle),
        child: const Icon(Icons.podcasts_rounded, size: 40, color: _kNavy),
      ),
      const SizedBox(height: 16),
      const Text('No shows yet',
          style: TextStyle(color: _kNavy, fontSize: 17, fontWeight: FontWeight.w800)),
      const SizedBox(height: 6),
      const Text('Upload your first episode to get started',
          style: TextStyle(color: Colors.grey, fontSize: 13)),
      const SizedBox(height: 20),
      ElevatedButton.icon(
        onPressed: widget.onUploadTab,
        style: ElevatedButton.styleFrom(
          backgroundColor: _kOrange, foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Create Your First Show',
            style: TextStyle(fontWeight: FontWeight.w700)),
      ),
    ]));

    return RefreshIndicator(
      onRefresh: _load, color: _kOrange,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _shows.length,
        itemBuilder: (_, i) {
          final show = _shows[i] as Map<String, dynamic>;
          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)]),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              leading: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: show['cover_image'] != null
                    ? Image.network(show['cover_image'], width: 56, height: 56, fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _showCoverPlaceholder())
                    : _showCoverPlaceholder(),
              ),
              title: Text(show['title'] ?? '',
                  style: const TextStyle(color: _kNavy, fontWeight: FontWeight.w700)),
              subtitle: Text(
                  '${show['total_episodes'] ?? 0} episodes · ${show['total_followers'] ?? 0} followers',
                  style: const TextStyle(color: Colors.grey, fontSize: 11)),
              trailing: PopupMenuButton<String>(
                onSelected: (_) {},
                icon: const Icon(Icons.more_vert_rounded, color: _kNavy),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Edit Show')),
                  PopupMenuItem(value: 'episode', child: Text('Add Episode')),
                  PopupMenuItem(value: 'stats', child: Text('View Stats')),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _showCoverPlaceholder() => Container(
    width: 56, height: 56,
    decoration: BoxDecoration(
        color: _kNavy.withAlpha(15), borderRadius: BorderRadius.circular(10)),
    child: const Icon(Icons.podcasts_rounded, color: _kNavy, size: 28),
  );
}
