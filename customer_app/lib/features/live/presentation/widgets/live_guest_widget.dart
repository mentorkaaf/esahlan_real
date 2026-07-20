import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

// ── Viewer: request to join button — state managed by parent ─────────────────

enum GuestJoinStatus { none, waiting, accepted }

class JoinRequestButton extends StatelessWidget {
  final GuestJoinStatus status;
  final bool loading;
  final VoidCallback? onJoin;
  final VoidCallback? onCancel;

  const JoinRequestButton({
    super.key,
    required this.status,
    this.loading = false,
    this.onJoin,
    this.onCancel,
  });

  @override
  Widget build(BuildContext context) {
    if (status == GuestJoinStatus.accepted) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.green.withValues(alpha: 0.25),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.green, width: 1),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.videocam, color: Colors.green, size: 16),
            SizedBox(width: 6),
            Text('On Stage', style: TextStyle(color: Colors.green, fontSize: 13, fontWeight: FontWeight.bold)),
          ],
        ),
      );
    }

    final isWaiting = status == GuestJoinStatus.waiting;
    return GestureDetector(
      onTap: loading ? null : (isWaiting ? onCancel : onJoin),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isWaiting ? Colors.white.withValues(alpha: 0.15) : Colors.orange,
          borderRadius: BorderRadius.circular(20),
          border: isWaiting ? Border.all(color: Colors.orange, width: 1) : null,
        ),
        child: loading
            ? const SizedBox(
                width: 16, height: 16,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    isWaiting ? Icons.hourglass_top : Icons.people_alt_outlined,
                    color: Colors.white, size: 16,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    isWaiting ? 'Waiting...' : 'Join Stage',
                    style: const TextStyle(
                        color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
      ),
    );
  }
}

// ── Host: incoming request popup ──────────────────────────────────────────────

class GuestRequestPopup extends StatelessWidget {
  final GuestRequest request;
  final int roomId;
  final VoidCallback onHandled;

  const GuestRequestPopup({
    super.key,
    required this.request,
    required this.roomId,
    required this.onHandled,
  });

  @override
  Widget build(BuildContext context) {
    final repo = LiveRepository();
    return Positioned(
      top: 80,
      left: 12,
      right: 12,
      child: Material(
        color: Colors.transparent,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFF1A1A2E),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.white12),
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: Colors.orange,
                backgroundImage: request.avatar.isNotEmpty
                    ? NetworkImage(request.avatar)
                    : null,
                child: request.avatar.isEmpty
                    ? Text(
                        request.name.isNotEmpty ? request.name[0].toUpperCase() : '?',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                      )
                    : null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(request.name,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                    const Text('wants to join the stage',
                        style: TextStyle(color: Colors.white54, fontSize: 11)),
                  ],
                ),
              ),
              // Reject
              GestureDetector(
                onTap: () async {
                  await repo.rejectGuest(roomId, request.requestId);
                  onHandled();
                },
                child: Container(
                  width: 36, height: 36,
                  decoration: const BoxDecoration(
                      color: Colors.red, shape: BoxShape.circle),
                  child: const Icon(Icons.close, color: Colors.white, size: 18),
                ),
              ),
              const SizedBox(width: 8),
              // Accept
              GestureDetector(
                onTap: () async {
                  await repo.acceptGuest(roomId, request.requestId);
                  onHandled();
                },
                child: Container(
                  width: 36, height: 36,
                  decoration: const BoxDecoration(
                      color: Colors.green, shape: BoxShape.circle),
                  child: const Icon(Icons.check, color: Colors.white, size: 18),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Guest grid (host view) ────────────────────────────────────────────────────

class LiveGuestGrid extends StatelessWidget {
  final List<LiveGuest> guests;
  final int roomId;
  final bool isHost;

  const LiveGuestGrid({
    super.key,
    required this.guests,
    required this.roomId,
    required this.isHost,
  });

  @override
  Widget build(BuildContext context) {
    if (guests.isEmpty) return const SizedBox.shrink();

    return Positioned(
      top: 80,
      right: 12,
      child: Column(
        children: guests.map((g) => _GuestTile(
          guest: g,
          roomId: roomId,
          isHost: isHost,
        )).toList(),
      ),
    );
  }
}

class _GuestTile extends StatelessWidget {
  final LiveGuest guest;
  final int roomId;
  final bool isHost;

  const _GuestTile({required this.guest, required this.roomId, required this.isHost});

  @override
  Widget build(BuildContext context) {
    final repo = LiveRepository();
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      width: 80,
      child: Column(
        children: [
          Stack(
            alignment: Alignment.center,
            children: [
              CircleAvatar(
                radius: 30,
                backgroundColor: Colors.purple,
                backgroundImage: guest.avatar.isNotEmpty
                    ? NetworkImage(guest.avatar)
                    : null,
                child: guest.avatar.isEmpty
                    ? Text(
                        guest.name.isNotEmpty ? guest.name[0].toUpperCase() : '?',
                        style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      )
                    : null,
              ),
              if (guest.isMuted)
                Positioned(
                  bottom: 0, right: 0,
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    decoration: const BoxDecoration(
                        color: Colors.red, shape: BoxShape.circle),
                    child: const Icon(Icons.mic_off, color: Colors.white, size: 10),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            guest.username.isNotEmpty ? guest.username : guest.name,
            style: const TextStyle(color: Colors.white70, fontSize: 10),
            overflow: TextOverflow.ellipsis,
          ),
          if (isHost) ...[
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Mute toggle
                GestureDetector(
                  onTap: () => repo.muteGuest(roomId, guest.userId, muted: !guest.isMuted),
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Icon(
                      guest.isMuted ? Icons.mic : Icons.mic_off,
                      color: Colors.white, size: 12,
                    ),
                  ),
                ),
                const SizedBox(width: 4),
                // Remove
                GestureDetector(
                  onTap: () => repo.removeGuest(roomId, guest.userId),
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.red.withValues(alpha: 0.7),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(Icons.person_remove, color: Colors.white, size: 12),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
