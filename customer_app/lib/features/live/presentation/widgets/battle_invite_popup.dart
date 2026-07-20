import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class BattleInvitePopup extends StatefulWidget {
  final BattleInvite invite;
  final VoidCallback onAccepted;
  final VoidCallback onDismissed;

  const BattleInvitePopup({
    super.key,
    required this.invite,
    required this.onAccepted,
    required this.onDismissed,
  });

  @override
  State<BattleInvitePopup> createState() => _BattleInvitePopupState();
}

class _BattleInvitePopupState extends State<BattleInvitePopup>
    with SingleTickerProviderStateMixin {
  late AnimationController _anim;
  late Animation<double> _scale;
  late int _remaining;
  Timer? _ticker;
  bool _loading = false;
  final _repo = LiveRepository();

  @override
  void initState() {
    super.initState();
    _remaining = widget.invite.expiresIn;
    _anim = AnimationController(vsync: this, duration: const Duration(milliseconds: 300));
    _scale = CurvedAnimation(parent: _anim, curve: Curves.elasticOut);
    _anim.forward();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _remaining--);
      if (_remaining <= 0) _reject();
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _anim.dispose();
    super.dispose();
  }

  Future<void> _accept() async {
    setState(() => _loading = true);
    try {
      await _repo.acceptBattleInvite(widget.invite.inviteId);
      widget.onAccepted();
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  Future<void> _reject() async {
    _ticker?.cancel();
    try { await _repo.rejectBattleInvite(widget.invite.inviteId); } catch (_) {}
    widget.onDismissed();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scale,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 24),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.orange.withOpacity(0.6), width: 1.5),
          boxShadow: [
            BoxShadow(
              color: Colors.orange.withOpacity(0.3),
              blurRadius: 20,
              spreadRadius: 2,
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ⚔️ header
            const Text('⚔️', style: TextStyle(fontSize: 36)),
            const SizedBox(height: 8),
            const Text(
              'PK Battle Invite',
              style: TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.bold,
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 16),
            // Challenger info
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundImage: widget.invite.fromAvatar.isNotEmpty
                      ? NetworkImage(widget.invite.fromAvatar)
                      : null,
                  backgroundColor: Colors.orange.withOpacity(0.3),
                  child: widget.invite.fromAvatar.isEmpty
                      ? Text(
                          widget.invite.fromName.isNotEmpty
                              ? widget.invite.fromName[0].toUpperCase()
                              : '?',
                          style: const TextStyle(color: Colors.white, fontSize: 18))
                      : null,
                ),
                const SizedBox(width: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.invite.fromName,
                      style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 15),
                    ),
                    Text(
                      '@${widget.invite.fromUsername}',
                      style: const TextStyle(color: Colors.white54, fontSize: 12),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'wants to PK Battle with you!',
              style: const TextStyle(color: Colors.white70, fontSize: 13),
            ),
            const SizedBox(height: 16),
            // Timer ring
            SizedBox(
              width: 48,
              height: 48,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  CircularProgressIndicator(
                    value: _remaining / widget.invite.expiresIn,
                    strokeWidth: 3,
                    backgroundColor: Colors.white12,
                    valueColor: AlwaysStoppedAnimation(
                      _remaining > 10 ? Colors.orange : Colors.red,
                    ),
                  ),
                  Text(
                    '$_remaining',
                    style: TextStyle(
                      color: _remaining > 10 ? Colors.white : Colors.red,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _loading ? null : _reject,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.white54,
                      side: const BorderSide(color: Colors.white24),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Decline'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: _loading ? null : _accept,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                    child: _loading
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Text('Accept ⚔️',
                            style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
