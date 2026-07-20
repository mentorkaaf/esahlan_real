import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../../../../core/utils/media_url.dart';

class PastLivesScreen extends StatefulWidget {
  const PastLivesScreen({super.key});

  @override
  State<PastLivesScreen> createState() => _PastLivesScreenState();
}

class _PastLivesScreenState extends State<PastLivesScreen> {
  final _repo = LiveRepository();
  List<LiveRoom> _rooms = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final rooms = await _repo.getPastRooms();
      if (mounted) setState(() { _rooms = rooms; _loading = false; });
    } catch (e) {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _fmtDuration(int? secs) {
    if (secs == null || secs <= 0) return '—';
    final m = secs ~/ 60;
    final s = secs % 60;
    if (m >= 60) return '${m ~/ 60}h ${m % 60}m';
    return '${m}m ${s.toString().padLeft(2, '0')}s';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0D0D0D),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1A0A2E),
        title: const Text('Past Lives', style: TextStyle(color: Colors.white)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Colors.orange))
          : _rooms.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.live_tv, size: 64, color: Colors.white24),
                      SizedBox(height: 12),
                      Text('No past lives yet', style: TextStyle(color: Colors.white54, fontSize: 16)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _rooms.length,
                    itemBuilder: (ctx, i) => _PastLiveCard(room: _rooms[i], fmtDuration: _fmtDuration),
                  ),
                ),
    );
  }
}

class _PastLiveCard extends StatelessWidget {
  final LiveRoom room;
  final String Function(int?) fmtDuration;

  const _PastLiveCard({required this.room, required this.fmtDuration});

  @override
  Widget build(BuildContext context) {
    final endedAgo = room.endedAt != null
        ? _timeAgo(room.endedAt!)
        : '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: const Color(0xFF1A1A2E),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Thumbnail / gradient header
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
            child: AspectRatio(
              aspectRatio: 16 / 9,
              child: room.thumbnail != null && room.thumbnail!.isNotEmpty
                  ? Image.network(fixMediaUrl(room.thumbnail!), fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _gradient())
                  : _gradient(),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Host row
                Row(
                  children: [
                    CircleAvatar(
                      radius: 18,
                      backgroundColor: Colors.orange,
                      backgroundImage: room.host.avatar.isNotEmpty
                          ? NetworkImage(fixMediaUrl(room.host.avatar))
                          : null,
                      child: room.host.avatar.isEmpty
                          ? Text(room.host.name.isNotEmpty ? room.host.name[0] : '?',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
                          : null,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(room.host.name,
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                          if (room.host.username.isNotEmpty)
                            Text('@${room.host.username}',
                                style: const TextStyle(color: Colors.white54, fontSize: 12)),
                        ],
                      ),
                    ),
                    // Ended time
                    Text(endedAgo, style: const TextStyle(color: Colors.white38, fontSize: 11)),
                  ],
                ),
                const SizedBox(height: 10),
                // Title
                Text(room.title,
                    style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600)),
                const SizedBox(height: 10),
                // Stats row
                Row(
                  children: [
                    _Stat(icon: Icons.remove_red_eye, value: '${room.peakViewers}', label: 'peak'),
                    const SizedBox(width: 16),
                    _Stat(icon: Icons.access_time, value: fmtDuration(room.durationSeconds), label: 'duration'),
                    if ((room.totalGifts ?? 0) > 0) ...[
                      const SizedBox(width: 16),
                      _Stat(icon: Icons.card_giftcard, value: '${room.totalGifts}', label: 'coins'),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _gradient() => Container(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF3D1A8A), Color(0xFF1A0A2E)],
      ),
    ),
    child: const Center(child: Icon(Icons.live_tv, size: 48, color: Colors.white30)),
  );

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inDays >= 1) return '${diff.inDays}d ago';
    if (diff.inHours >= 1) return '${diff.inHours}h ago';
    return '${diff.inMinutes}m ago';
  }
}

class _Stat extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;
  const _Stat({required this.icon, required this.value, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: Colors.orange),
        const SizedBox(width: 4),
        Text('$value ', style: const TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600)),
        Text(label, style: const TextStyle(color: Colors.white38, fontSize: 12)),
      ],
    );
  }
}
