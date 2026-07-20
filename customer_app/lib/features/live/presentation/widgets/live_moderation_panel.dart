import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class LiveModerationPanel extends StatefulWidget {
  final int roomId;
  final LiveRoomSettings initial;
  final void Function(LiveRoomSettings) onUpdated;

  const LiveModerationPanel({
    super.key,
    required this.roomId,
    required this.initial,
    required this.onUpdated,
  });

  @override
  State<LiveModerationPanel> createState() => _LiveModerationPanelState();
}

class _LiveModerationPanelState extends State<LiveModerationPanel> {
  final _repo = LiveRepository();
  final _wordsCtrl = TextEditingController();
  late bool _slowMode;
  late int _slowSecs;
  late bool _followersOnly;
  late bool _commentsDisabled;
  late List<String> _blocked;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final s = widget.initial;
    _slowMode         = s.slowMode;
    _slowSecs         = s.slowModeSeconds;
    _followersOnly    = s.followersOnly;
    _commentsDisabled = s.commentsDisabled;
    _blocked          = List.from(s.blockedWords);
  }

  @override
  void dispose() {
    _wordsCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final updated = await _repo.updateSettings(widget.roomId, {
        'slow_mode':          _slowMode,
        'slow_mode_seconds':  _slowSecs,
        'followers_only':     _followersOnly,
        'comments_disabled':  _commentsDisabled,
        'blocked_words':      _blocked,
      });
      widget.onUpdated(updated);
      if (mounted) Navigator.pop(context);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to save settings')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _addWord() {
    final w = _wordsCtrl.text.trim();
    if (w.isEmpty || _blocked.contains(w)) return;
    setState(() { _blocked.add(w); _wordsCtrl.clear(); });
  }

  void _removeWord(String w) => setState(() => _blocked.remove(w));

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.75,
      decoration: const BoxDecoration(
        color: Color(0xFF0F0F1A),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          // Handle
          const SizedBox(height: 10),
          Center(
            child: Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2))),
          ),
          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Row(
              children: [
                const Text('🛡️', style: TextStyle(fontSize: 20)),
                const SizedBox(width: 8),
                const Text('Moderation', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold)),
                const Spacer(),
                TextButton(
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(width: 16, height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.orange))
                      : const Text('Save', style: TextStyle(color: Colors.orange, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          ),
          const Divider(color: Colors.white12, height: 1),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Slow mode
                  _Section(
                    title: 'Slow Mode',
                    icon: '⏱',
                    trailing: Switch(
                      value: _slowMode,
                      onChanged: (v) => setState(() => _slowMode = v),
                      activeColor: Colors.orange,
                    ),
                    child: _slowMode
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const SizedBox(height: 8),
                              Text('Seconds between messages: $_slowSecs',
                                  style: const TextStyle(color: Colors.white54, fontSize: 12)),
                              Slider(
                                value: _slowSecs.toDouble(),
                                min: 3, max: 300,
                                divisions: 14,
                                activeColor: Colors.orange,
                                inactiveColor: Colors.white12,
                                onChanged: (v) => setState(() => _slowSecs = v.round()),
                              ),
                              Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 4),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: const [
                                    Text('3s', style: TextStyle(color: Colors.white38, fontSize: 10)),
                                    Text('5m', style: TextStyle(color: Colors.white38, fontSize: 10)),
                                  ],
                                ),
                              ),
                            ],
                          )
                        : null,
                  ),
                  const SizedBox(height: 12),
                  // Followers only
                  _Section(
                    title: 'Followers Only',
                    icon: '👥',
                    subtitle: 'Only your followers can send messages',
                    trailing: Switch(
                      value: _followersOnly,
                      onChanged: (v) => setState(() => _followersOnly = v),
                      activeColor: Colors.blue,
                    ),
                  ),
                  const SizedBox(height: 12),
                  // Disable comments
                  _Section(
                    title: 'Disable Chat',
                    icon: '🔇',
                    subtitle: 'No one can send messages',
                    trailing: Switch(
                      value: _commentsDisabled,
                      onChanged: (v) => setState(() => _commentsDisabled = v),
                      activeColor: Colors.red,
                    ),
                  ),
                  const SizedBox(height: 12),
                  // Blocked words
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.04),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.white12),
                    ),
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Text('🚫', style: TextStyle(fontSize: 16)),
                            SizedBox(width: 8),
                            Text('Blocked Words', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                          ],
                        ),
                        const SizedBox(height: 6),
                        const Text('Messages containing these words will be filtered',
                            style: TextStyle(color: Colors.white38, fontSize: 11)),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _wordsCtrl,
                                style: const TextStyle(color: Colors.white, fontSize: 13),
                                decoration: InputDecoration(
                                  hintText: 'Add word...',
                                  hintStyle: const TextStyle(color: Colors.white38),
                                  filled: true,
                                  fillColor: Colors.white.withValues(alpha: 0.06),
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                  border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(8),
                                      borderSide: BorderSide.none),
                                ),
                                onSubmitted: (_) => _addWord(),
                              ),
                            ),
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: _addWord,
                              child: Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: Colors.orange,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Icon(Icons.add, color: Colors.white, size: 18),
                              ),
                            ),
                          ],
                        ),
                        if (_blocked.isNotEmpty) ...[
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: _blocked.map((w) => Chip(
                              label: Text(w, style: const TextStyle(color: Colors.white, fontSize: 11)),
                              backgroundColor: Colors.red.withValues(alpha: 0.2),
                              side: const BorderSide(color: Colors.red, width: 0.5),
                              deleteIcon: const Icon(Icons.close, size: 13, color: Colors.red),
                              onDeleted: () => _removeWord(w),
                              visualDensity: VisualDensity.compact,
                              padding: EdgeInsets.zero,
                              materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            )).toList(),
                          ),
                        ],
                      ],
                    ),
                  ),
                  SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String title;
  final String icon;
  final String? subtitle;
  final Widget? trailing;
  final Widget? child;

  const _Section({
    required this.title,
    required this.icon,
    this.subtitle,
    this.trailing,
    this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white12),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(icon, style: const TextStyle(fontSize: 16)),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                    if (subtitle != null)
                      Text(subtitle!, style: const TextStyle(color: Colors.white38, fontSize: 11)),
                  ],
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          if (child != null) child!,
        ],
      ),
    );
  }
}
