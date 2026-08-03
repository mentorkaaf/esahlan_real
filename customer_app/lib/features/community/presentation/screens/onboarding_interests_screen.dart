import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../auth/data/repositories/auth_repository.dart';

const _kOrange = Color(0xFFFF6B35);

const _topics = [
  ('⚽', 'Football'),     ('🏀', 'Basketball'),   ('🎵', 'Music'),
  ('🎬', 'Movies'),       ('📸', 'Photography'),  ('🍕', 'Food'),
  ('✈️', 'Travel'),       ('💻', 'Technology'),   ('💄', 'Beauty'),
  ('💪', 'Fitness'),      ('📚', 'Education'),    ('🎮', 'Gaming'),
  ('🌿', 'Nature'),       ('🐾', 'Pets'),         ('💼', 'Business'),
  ('🎨', 'Art'),          ('🧘', 'Wellness'),     ('🏠', 'Home'),
  ('👗', 'Fashion'),      ('🔬', 'Science'),
];

class OnboardingInterestsScreen extends ConsumerStatefulWidget {
  final VoidCallback onDone;
  const OnboardingInterestsScreen({required this.onDone, super.key});

  @override
  ConsumerState<OnboardingInterestsScreen> createState() => _State();
}

class _State extends ConsumerState<OnboardingInterestsScreen> {
  final Set<String> _selected = {};
  bool _saving = false;

  Future<void> _save() async {
    if (_selected.isEmpty) { widget.onDone(); return; }
    setState(() => _saving = true);
    try {
      await ApiClient.instance.post('/community/feed/onboarding-interests', data: {
        'topics': _selected.toList(),
      });
    } catch (_) {}
    widget.onDone();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      body: SafeArea(
        child: Column(children: [
          const SizedBox(height: 32),
          Text('What are you into?',
              style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: c.navyText)),
          const SizedBox(height: 8),
          Text('Pick topics you love — your feed will be personalized',
              style: TextStyle(fontSize: 14, color: c.mutedText), textAlign: TextAlign.center),
          const SizedBox(height: 24),

          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Wrap(
                spacing: 10, runSpacing: 10,
                children: _topics.map((t) {
                  final (emoji, name) = t;
                  final sel = _selected.contains(name);
                  return GestureDetector(
                    onTap: () => setState(() => sel ? _selected.remove(name) : _selected.add(name)),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      decoration: BoxDecoration(
                        color: sel ? _kOrange : c.cardBg,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: sel ? _kOrange : c.borderColor, width: 1.5),
                      ),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Text(emoji, style: const TextStyle(fontSize: 18)),
                        const SizedBox(width: 6),
                        Text(name,
                          style: TextStyle(
                            fontWeight: FontWeight.w600, fontSize: 14,
                            color: sel ? Colors.white : c.navyText,
                          )),
                      ]),
                    ),
                  );
                }).toList(),
              ),
            ),
          ),

          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            child: Column(children: [
              if (_selected.isNotEmpty)
                Text('${_selected.length} selected',
                    style: TextStyle(color: _kOrange, fontWeight: FontWeight.w600, fontSize: 13)),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _saving ? null : _save,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _kOrange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: _saving
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : Text(_selected.isEmpty ? 'Skip' : 'Continue', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                ),
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}
