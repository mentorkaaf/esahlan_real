import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../data/repositories/podcast_repository.dart';

class CreatePodcastScreen extends StatefulWidget {
  const CreatePodcastScreen({super.key});
  @override
  State<CreatePodcastScreen> createState() => _CreatePodcastScreenState();
}

class _CreatePodcastScreenState extends State<CreatePodcastScreen> {
  final _repo      = PodcastRepository();
  final _titleCtrl = TextEditingController();
  final _descCtrl  = TextEditingController();
  final _rssCtrl   = TextEditingController();
  int?   _categoryId;
  File?  _coverFile;
  bool   _submitting = false;
  bool   _showRss    = false;
  bool   _rssLoading = false;
  Map<String, dynamic>? _rssPreview;

  final _categories = <Map<String, dynamic>>[]; // loaded from API

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  Future<void> _loadCategories() async {
    try {
      final cats = await _repo.getCategories();
      if (mounted) setState(() {
        _categories.addAll(cats.map((c) => {'id': c.id, 'name': c.name, 'icon': c.icon}));
      });
    } catch (_) {}
  }

  Future<void> _pickCover() async {
    final p = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (p != null) setState(() => _coverFile = File(p.path));
  }

  Future<void> _previewRss() async {
    final url = _rssCtrl.text.trim();
    if (url.isEmpty) return;
    setState(() { _rssLoading = true; _rssPreview = null; });
    try {
      final data = await _repo.getRssPreview(url);
      if (mounted) {
        setState(() => _rssPreview = data['preview']);
        if (_rssPreview != null) {
          _titleCtrl.text = _rssPreview!['title'] ?? '';
          _descCtrl.text  = _rssPreview!['description'] ?? '';
        }
      }
    } catch (e) {
      if (mounted) _showError('RSS URL khalad buu yahay');
    } finally {
      if (mounted) setState(() => _rssLoading = false);
    }
  }

  Future<void> _submit() async {
    if (_titleCtrl.text.trim().isEmpty || _categoryId == null) {
      _showError('Title iyo Category waa waajib');
      return;
    }
    setState(() => _submitting = true);
    try {
      await _repo.createShow({
        'title': _titleCtrl.text.trim(),
        'description': _descCtrl.text.trim(),
        'category_id': _categoryId,
        'rss_url': _rssCtrl.text.trim().isEmpty ? null : _rssCtrl.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('🎙 Podcast-kaagu wuu abuurmay!'), backgroundColor: Color(0xFF7C3AED)),
        );
        Navigator.of(context).pop(true);
      }
    } catch (e) {
      _showError('Khalad dhacay, isku day');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: Colors.red.shade700),
    );
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _descCtrl.dispose();
    _rssCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0A0F),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0A0A0F),
        leading: const BackButton(color: Colors.white70),
        title: const Text('Podcast Cusub', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          TextButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF7C3AED)))
                : const Text('Abuuro', style: TextStyle(color: Color(0xFF7C3AED), fontWeight: FontWeight.w700)),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Cover picker
            Center(
              child: GestureDetector(
                onTap: _pickCover,
                child: Container(
                  width: 140, height: 140,
                  decoration: BoxDecoration(
                    color: const Color(0xFF1C1C28),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFF2A2A3A)),
                  ),
                  child: _coverFile != null
                      ? ClipRRect(borderRadius: BorderRadius.circular(16), child: Image.file(_coverFile!, fit: BoxFit.cover))
                      : const Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Icon(Icons.add_photo_alternate_rounded, color: Color(0xFF6B6B80), size: 40),
                          SizedBox(height: 8),
                          Text('Sawir ku dar', style: TextStyle(color: Color(0xFF6B6B80), fontSize: 12)),
                        ]),
                ),
              ),
            ),
            const SizedBox(height: 28),

            // RSS toggle
            GestureDetector(
              onTap: () => setState(() => _showRss = !_showRss),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFF1C1C28),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _showRss ? const Color(0xFF7C3AED) : Colors.transparent),
                ),
                child: Row(children: [
                  const Icon(Icons.rss_feed_rounded, color: Color(0xFF7C3AED), size: 22),
                  const SizedBox(width: 12),
                  const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('RSS Feed-ka ka soo dir', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600)),
                    Text('Podcast URL paste gareey', style: TextStyle(color: Color(0xFF6B6B80), fontSize: 11)),
                  ])),
                  Icon(_showRss ? Icons.expand_less : Icons.expand_more, color: Colors.white54),
                ]),
              ),
            ),

            if (_showRss) ...[
              const SizedBox(height: 12),
              _Field(ctrl: _rssCtrl, hint: 'https://example.com/feed.rss', label: 'RSS URL'),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _rssLoading ? null : _previewRss,
                  icon: _rssLoading
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.preview_rounded, size: 18),
                  label: const Text('Preview'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF7C3AED),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
              if (_rssPreview != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: const Color(0xFF1C1C28), borderRadius: BorderRadius.circular(10)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('✅ ${_rssPreview!['episode_count']} episodes la helay', style: const TextStyle(color: Color(0xFF7C3AED), fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(_rssPreview!['title'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 13)),
                  ]),
                ),
              ],
            ],

            const SizedBox(height: 20),

            _Field(ctrl: _titleCtrl, hint: 'Podcast-kaaga magac', label: 'Magaca *'),
            const SizedBox(height: 16),
            _Field(ctrl: _descCtrl, hint: 'Waxa ku saabsan...', label: 'Sharaxaad', maxLines: 4),
            const SizedBox(height: 16),

            const _Label('Qayb *'),
            const SizedBox(height: 8),
            if (_categories.isEmpty)
              const Center(child: CircularProgressIndicator(color: Color(0xFF7C3AED), strokeWidth: 2))
            else
              Wrap(
                spacing: 8, runSpacing: 8,
                children: _categories.map((c) {
                  final sel = _categoryId == c['id'];
                  return GestureDetector(
                    onTap: () => setState(() => _categoryId = c['id']),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        color: sel ? const Color(0xFF7C3AED) : const Color(0xFF1C1C28),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: sel ? const Color(0xFF7C3AED) : const Color(0xFF2A2A3A)),
                      ),
                      child: Text('${c['icon']} ${c['name']}',
                          style: TextStyle(color: sel ? Colors.white : Colors.white60, fontSize: 12, fontWeight: FontWeight.w600)),
                    ),
                  );
                }).toList(),
              ),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }
}

class _Field extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final String label;
  final int maxLines;
  const _Field({required this.ctrl, required this.hint, required this.label, this.maxLines = 1});

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      _Label(label),
      const SizedBox(height: 6),
      TextField(
        controller: ctrl,
        maxLines: maxLines,
        style: const TextStyle(color: Colors.white, fontSize: 14),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: Color(0xFF6B6B80)),
          filled: true,
          fillColor: const Color(0xFF1C1C28),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF7C3AED))),
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        ),
      ),
    ],
  );
}

class _Label extends StatelessWidget {
  final String text;
  const _Label(this.text);
  @override
  Widget build(BuildContext context) => Text(text, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12, fontWeight: FontWeight.w600));
}
