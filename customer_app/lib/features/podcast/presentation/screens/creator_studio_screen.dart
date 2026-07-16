import 'package:flutter/material.dart';
import '../../data/repositories/podcast_repository.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class CreatorStudioScreen extends StatefulWidget {
  const CreatorStudioScreen({super.key});
  @override
  State<CreatorStudioScreen> createState() => _CreatorStudioScreenState();
}

class _CreatorStudioScreenState extends State<CreatorStudioScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;
  @override
  void initState() { super.initState(); _tabs = TabController(length: 2, vsync: this); }
  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF0F2F5),
    appBar: AppBar(
      backgroundColor: kNavy,
      title: const Text('Creator Studio', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
      iconTheme: const IconThemeData(color: Colors.white),
      bottom: TabBar(
        controller: _tabs,
        indicatorColor: kOrange,
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white54,
        tabs: const [Tab(text: 'Upload Audio'), Tab(text: 'My Shows')],
      ),
    ),
    body: TabBarView(controller: _tabs, children: [
      const _UploadTab(),
      _MyShowsTab(),
    ]),
  );
}

// ─── Upload Tab ──────────────────────────────────────────────────────────────

class _UploadTab extends StatefulWidget {
  const _UploadTab();
  @override
  State<_UploadTab> createState() => _UploadTabState();
}

class _UploadTabState extends State<_UploadTab> {
  final _titleCtrl  = TextEditingController();
  final _descCtrl   = TextEditingController();
  final _formKey    = GlobalKey<FormState>();
  String? _selectedCategory;
  String  _privacy  = 'public';
  bool    _uploading = false;

  final _categories = [
    'Business', 'Education', 'Religion', 'Technology',
    'Health', 'News', 'Science', 'Finance', 'Music',
    'Sports', 'Stories', 'Motivation', 'Entertainment', 'Lifestyle',
  ];

  @override
  void dispose() {
    _titleCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  void _publish() {
    if (!_formKey.currentState!.validate()) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Uploading from device is coming soon — use RSS import or API'),
        backgroundColor: kOrange,
      ),
    );
  }

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.all(16),
    child: Form(
      key: _formKey,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // Upload audio box
        GestureDetector(
          onTap: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('File picker coming soon'), backgroundColor: kNavy)),
          child: Container(
            height: 120,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: kOrange.withAlpha(80), style: BorderStyle.solid, width: 1.5),
            ),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.cloud_upload_rounded, size: 44, color: kOrange),
              const SizedBox(height: 8),
              const Text('Upload Audio', style: TextStyle(color: kNavy, fontWeight: FontWeight.w700, fontSize: 14)),
              const Text('MP3, WAV, M4A (Max 200MB)', style: TextStyle(color: Colors.grey, fontSize: 11)),
            ]),
          ),
        ),

        const SizedBox(height: 16),

        // Title
        _label('Title'),
        _field(controller: _titleCtrl, hint: 'Enter audio title',
            validator: (v) => v == null || v.isEmpty ? 'Title is required' : null),

        const SizedBox(height: 12),

        // Description
        _label('Description'),
        _field(controller: _descCtrl, hint: 'Tell us about your audio',
            maxLines: 4, validator: null),

        const SizedBox(height: 12),

        // Category
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
              items: _categories.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
            ),
          ),
        ),

        const SizedBox(height: 12),

        // Cover Image
        _label('Cover Image'),
        GestureDetector(
          onTap: () {},
          child: Container(
            height: 60,
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.grey.shade200)),
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.add_photo_alternate_rounded, color: kOrange),
              const SizedBox(width: 8),
              const Text('Upload cover image', style: TextStyle(color: kOrange, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),

        const SizedBox(height: 12),

        // Privacy
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
                DropdownMenuItem(value: 'public', child: Text('Public')),
                DropdownMenuItem(value: 'private', child: Text('Private')),
              ],
            ),
          ),
        ),

        const SizedBox(height: 24),

        // RSS Import section
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: kNavy.withAlpha(10),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: kNavy.withAlpha(30)),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Row(children: [
              Icon(Icons.rss_feed_rounded, color: kNavy, size: 18),
              SizedBox(width: 6),
              Text('RSS Feed Import', style: TextStyle(color: kNavy, fontWeight: FontWeight.w700, fontSize: 13)),
            ]),
            const SizedBox(height: 6),
            const Text('Import your existing podcast from any RSS feed URL.',
                style: TextStyle(color: Colors.grey, fontSize: 11)),
            const SizedBox(height: 10),
            GestureDetector(
              onTap: () => _showRssDialog(context),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(color: kNavy, borderRadius: BorderRadius.circular(8)),
                child: const Text('Import from RSS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
              ),
            ),
          ]),
        ),

        const SizedBox(height: 24),

        // Publish button
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _uploading ? null : _publish,
            style: ElevatedButton.styleFrom(
              backgroundColor: kOrange, foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: _uploading
                ? const SizedBox(width: 20, height: 20,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Text('Publish', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
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
        title: const Text('RSS Feed Import', style: TextStyle(color: kNavy, fontWeight: FontWeight.w800)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Enter your podcast RSS feed URL:', style: TextStyle(color: Colors.grey)),
          const SizedBox(height: 12),
          TextField(
            controller: ctrl,
            decoration: const InputDecoration(
              hintText: 'https://your-podcast.com/feed.rss',
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.rss_feed_rounded, color: kOrange),
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
            style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white),
            child: const Text('Preview'),
          ),
        ],
      ),
    );
  }

  void _showRssPreview(BuildContext ctx, String url) async {
    showDialog(
      context: ctx,
      barrierDismissible: false,
      builder: (_) => const AlertDialog(
        content: Row(children: [
          CircularProgressIndicator(color: kOrange),
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
          title: Text(data['title'] ?? 'RSS Preview',
              style: const TextStyle(color: kNavy, fontWeight: FontWeight.w800)),
          content: SingleChildScrollView(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (data['description'] != null)
                Text(data['description'], style: const TextStyle(color: Colors.grey, fontSize: 12)),
              const SizedBox(height: 8),
              Text('${(data['episodes'] as List?)?.length ?? 0} episodes found',
                  style: const TextStyle(color: kOrange, fontWeight: FontWeight.w700)),
            ]),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx),
              style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white),
              child: const Text('Import'),
            ),
          ],
        ),
      );
    } catch (e) {
      if (ctx.mounted) { Navigator.pop(ctx); }
      if (ctx.mounted) {
        ScaffoldMessenger.of(ctx).showSnackBar(
          SnackBar(content: Text('Failed to load RSS: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text, style: const TextStyle(color: kNavy, fontWeight: FontWeight.w700, fontSize: 13)),
  );

  Widget _field({required TextEditingController controller, required String hint,
      int maxLines = 1, String? Function(String?)? validator}) {
    return TextFormField(
      controller: controller,
      maxLines: maxLines,
      validator: validator,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: Colors.grey),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey.shade200),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey.shade200),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: kOrange),
        ),
      ),
    );
  }
}

// ─── My Shows Tab ─────────────────────────────────────────────────────────────

class _MyShowsTab extends StatefulWidget {
  @override
  State<_MyShowsTab> createState() => _MyShowsTabState();
}

class _MyShowsTabState extends State<_MyShowsTab> {
  final _repo = PodcastRepository();
  List<dynamic> _shows = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    try {
      final d = await _repo.getMyShows();
      if (mounted) setState(() { _shows = d; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator(color: kOrange));
    if (_shows.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.podcasts_rounded, size: 60, color: Colors.grey),
      const SizedBox(height: 12),
      const Text('No shows yet', style: TextStyle(color: Colors.grey, fontSize: 15)),
      const SizedBox(height: 16),
      ElevatedButton(
        onPressed: () {},
        style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
        child: const Text('Create Your First Show'),
      ),
    ]));

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _shows.length,
      itemBuilder: (_, i) {
        final show = _shows[i] as Map<String, dynamic>;
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
              boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)]),
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            leading: ClipRRect(borderRadius: BorderRadius.circular(8),
                child: Container(width: 52, height: 52,
                    color: kNavy.withAlpha(20),
                    child: const Icon(Icons.podcasts_rounded, color: kNavy))),
            title: Text(show['title'] ?? '', style: const TextStyle(color: kNavy, fontWeight: FontWeight.w700)),
            subtitle: Text('${show['total_episodes'] ?? 0} episodes · ${show['total_followers'] ?? 0} followers',
                style: const TextStyle(color: Colors.grey, fontSize: 11)),
            trailing: PopupMenuButton<String>(
              onSelected: (_) {},
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'edit', child: Text('Edit Show')),
                const PopupMenuItem(value: 'episode', child: Text('Add Episode')),
                const PopupMenuItem(value: 'stats', child: Text('View Stats')),
              ],
            ),
          ),
        );
      },
    );
  }
}
