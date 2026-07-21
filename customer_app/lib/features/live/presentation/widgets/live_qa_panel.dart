import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

// ── Host: Q&A Panel ───────────────────────────────────────────────────────────

class LiveQAPanel extends StatefulWidget {
  final int roomId;

  const LiveQAPanel({super.key, required this.roomId});

  @override
  State<LiveQAPanel> createState() => _LiveQAPanelState();
}

class _LiveQAPanelState extends State<LiveQAPanel> {
  final _repo = LiveRepository();
  List<LiveQuestion> _questions = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final qs = await _repo.getQuestions(widget.roomId);
      if (mounted) setState(() { _questions = qs; _loading = false; });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _activate(LiveQuestion q) async {
    try {
      await _repo.activateQuestion(widget.roomId, q.id);
      if (!mounted) return;
      setState(() => _questions.remove(q));
      Navigator.pop(context);
    } catch (_) {}
  }

  Future<void> _dismiss(LiveQuestion q) async {
    try {
      await _repo.dismissQuestion(widget.roomId, q.id);
      setState(() => _questions.remove(q));
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.6,
      decoration: const BoxDecoration(
        color: Color(0xFF1A1A2E),
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 12),
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Padding(
                padding: EdgeInsets.only(left: 20),
                child: Text('❓ Q&A Questions',
                    style: TextStyle(color: Colors.white, fontSize: 16,
                        fontWeight: FontWeight.bold)),
              ),
              IconButton(
                onPressed: _load,
                icon: const Icon(Icons.refresh, color: Colors.white54),
              ),
            ],
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: Colors.orange))
                : _questions.isEmpty
                    ? const Center(
                        child: Text('No questions yet',
                            style: TextStyle(color: Colors.white38)))
                    : ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        itemCount: _questions.length,
                        separatorBuilder: (_, __) =>
                            const Divider(color: Colors.white10, height: 1),
                        itemBuilder: (_, i) {
                          final q = _questions[i];
                          return Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                CircleAvatar(
                                  radius: 16,
                                  backgroundImage: q.avatar.isNotEmpty
                                      ? NetworkImage(q.avatar)
                                      : null,
                                  backgroundColor: Colors.purple.withValues(alpha: 0.3),
                                  child: q.avatar.isEmpty
                                      ? Text(q.username.isNotEmpty
                                            ? q.username[0].toUpperCase()
                                            : '?',
                                          style: const TextStyle(
                                              color: Colors.white, fontSize: 11))
                                      : null,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('@${q.username}',
                                          style: const TextStyle(
                                              color: Colors.white54, fontSize: 11)),
                                      const SizedBox(height: 3),
                                      Text(q.question,
                                          style: const TextStyle(
                                              color: Colors.white, fontSize: 13)),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Column(
                                  children: [
                                    GestureDetector(
                                      onTap: () => _activate(q),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 10, vertical: 5),
                                        decoration: BoxDecoration(
                                          color: Colors.orange,
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: const Text('Answer',
                                            style: TextStyle(
                                                color: Colors.white,
                                                fontSize: 11,
                                                fontWeight: FontWeight.bold)),
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    GestureDetector(
                                      onTap: () => _dismiss(q),
                                      child: const Text('Dismiss',
                                          style: TextStyle(
                                              color: Colors.white30, fontSize: 10)),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}

// ── Viewer: Active Question Banner ────────────────────────────────────────────

class ActiveQuestionBanner extends StatelessWidget {
  final LiveQuestion question;
  final VoidCallback onDismiss;

  const ActiveQuestionBanner({
    super.key,
    required this.question,
    required this.onDismiss,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF1A1A2E).withValues(alpha: 0.95),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.purple.withValues(alpha: 0.6)),
      ),
      child: Row(
        children: [
          const Text('❓', style: TextStyle(fontSize: 20)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('@${question.username}',
                    style: const TextStyle(
                        color: Colors.purple, fontSize: 11,
                        fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                Text(question.question,
                    style: const TextStyle(color: Colors.white, fontSize: 13)),
              ],
            ),
          ),
          GestureDetector(
            onTap: onDismiss,
            child: const Icon(Icons.close, color: Colors.white38, size: 18),
          ),
        ],
      ),
    );
  }
}

// ── Viewer: Submit Question ───────────────────────────────────────────────────

class SubmitQuestionSheet extends StatefulWidget {
  final int roomId;

  const SubmitQuestionSheet({super.key, required this.roomId});

  @override
  State<SubmitQuestionSheet> createState() => _SubmitQuestionSheetState();
}

class _SubmitQuestionSheetState extends State<SubmitQuestionSheet> {
  final _ctrl = TextEditingController();
  final _repo = LiveRepository();
  bool _loading = false;

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 16, right: 16, top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Ask the host a question',
              style: TextStyle(color: Colors.white, fontSize: 15,
                  fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          TextField(
            controller: _ctrl,
            autofocus: true,
            maxLines: 3,
            maxLength: 300,
            style: const TextStyle(color: Colors.white),
            decoration: InputDecoration(
              hintText: 'Type your question...',
              hintStyle: const TextStyle(color: Colors.white38),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.07),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none),
              counterStyle: const TextStyle(color: Colors.white30),
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _loading ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.purple,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Send Question ❓',
                      style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    final q = _ctrl.text.trim();
    if (q.isEmpty) return;
    setState(() => _loading = true);
    try {
      await _repo.submitQuestion(widget.roomId, q);
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Question sent! The host may answer it live.'),
            backgroundColor: Colors.purple,
          ),
        );
      }
    } catch (_) { setState(() => _loading = false); }
  }
}
