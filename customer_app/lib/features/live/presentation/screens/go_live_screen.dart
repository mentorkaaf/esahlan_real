import 'package:flutter/material.dart';
import '../../data/repositories/live_repository.dart';
import 'live_host_screen.dart';

const _kCategories = [
  ('general',   '🌐', 'General'),
  ('gaming',    '🎮', 'Gaming'),
  ('music',     '🎵', 'Music'),
  ('cooking',   '🍳', 'Cooking'),
  ('education', '📚', 'Education'),
  ('sports',    '⚽', 'Sports'),
  ('beauty',    '💄', 'Beauty'),
  ('fitness',   '💪', 'Fitness'),
  ('travel',    '✈️', 'Travel'),
  ('comedy',    '😂', 'Comedy'),
  ('art',       '🎨', 'Art'),
  ('technology','💻', 'Tech'),
];

class GoLiveScreen extends StatefulWidget {
  const GoLiveScreen({super.key});

  @override
  State<GoLiveScreen> createState() => _GoLiveScreenState();
}

class _GoLiveScreenState extends State<GoLiveScreen> {
  final _titleCtrl = TextEditingController();
  String _category = 'general';
  bool _loading = false;
  final _repo = LiveRepository();

  Future<void> _start() async {
    final title = _titleCtrl.text.trim();
    if (title.isEmpty) return;
    setState(() => _loading = true);
    try {
      final session = await _repo.createRoom(title: title, category: _category);
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => LiveHostScreen(session: session)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to start live: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        title: const Text('Go Live', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icon
              Center(
                child: Container(
                  width: 90, height: 90,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(colors: [Colors.orange, Colors.red]),
                  ),
                  child: const Icon(Icons.live_tv, color: Colors.white, size: 44),
                ),
              ),
              const SizedBox(height: 28),

              // Title
              const Text('Live Title',
                  style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              TextField(
                controller: _titleCtrl,
                style: const TextStyle(color: Colors.white),
                maxLength: 80,
                decoration: InputDecoration(
                  hintText: 'What are you going to talk about?',
                  hintStyle: const TextStyle(color: Colors.white30),
                  filled: true,
                  fillColor: Colors.white10,
                  border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  counterStyle: const TextStyle(color: Colors.white30),
                ),
              ),
              const SizedBox(height: 4),

              // Category picker
              const Text('Category',
                  style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _kCategories.map((c) {
                  final selected = _category == c.$1;
                  return GestureDetector(
                    onTap: () => setState(() => _category = c.$1),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 150),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                      decoration: BoxDecoration(
                        color: selected
                            ? Colors.orange.withValues(alpha: 0.25)
                            : Colors.white.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: selected ? Colors.orange : Colors.white12,
                          width: selected ? 1.5 : 1,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(c.$2, style: const TextStyle(fontSize: 13)),
                          const SizedBox(width: 5),
                          Text(c.$3,
                              style: TextStyle(
                                  color: selected ? Colors.orange : Colors.white60,
                                  fontSize: 12,
                                  fontWeight: selected ? FontWeight.bold : FontWeight.normal)),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              ),
              const SizedBox(height: 20),

              // Tips
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.orange.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.orange.withValues(alpha: 0.25)),
                ),
                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('💡 Tips', style: TextStyle(color: Colors.orange, fontWeight: FontWeight.bold)),
                    SizedBox(height: 8),
                    Text('• Good lighting makes a big difference', style: TextStyle(color: Colors.white60, fontSize: 12)),
                    Text('• Stable internet gives your viewers a smooth experience', style: TextStyle(color: Colors.white60, fontSize: 12)),
                    Text('• Viewers can send gifts 🎁 and coins 🪙', style: TextStyle(color: Colors.white60, fontSize: 12)),
                  ],
                ),
              ),
              const SizedBox(height: 28),

              // Start button
              SizedBox(
                width: double.infinity,
                height: 54,
                child: ElevatedButton(
                  onPressed: _loading ? null : _start,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
                  ),
                  child: _loading
                      ? const SizedBox(
                          width: 22, height: 22,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('🔴  Start Live',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}
