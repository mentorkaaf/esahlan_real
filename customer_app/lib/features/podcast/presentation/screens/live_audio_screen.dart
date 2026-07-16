import 'package:flutter/material.dart';
import '../../data/repositories/podcast_repository.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class LiveAudioScreen extends StatefulWidget {
  const LiveAudioScreen({super.key});
  @override
  State<LiveAudioScreen> createState() => _LiveAudioScreenState();
}

class _LiveAudioScreenState extends State<LiveAudioScreen> {
  final _repo = PodcastRepository();
  List<dynamic> _rooms = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    try {
      final d = await _repo.getLiveRooms();
      if (mounted) setState(() { _rooms = d; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF0F2F5),
    appBar: AppBar(
      backgroundColor: kNavy,
      title: Row(children: [
        const Text('Live Audio', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
        const SizedBox(width: 8),
        Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(4)),
            child: const Text('LIVE', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
      ]),
      iconTheme: const IconThemeData(color: Colors.white),
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator(color: kOrange))
        : _rooms.isEmpty
            ? _emptyState()
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _rooms.length,
                itemBuilder: (_, i) => _RoomCard(room: _rooms[i] as Map<String, dynamic>),
              ),
  );

  Widget _emptyState() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.sensors_rounded, size: 60, color: Colors.grey.shade400),
    const SizedBox(height: 12),
    const Text('No live rooms right now', style: TextStyle(color: Colors.grey, fontSize: 15)),
    const SizedBox(height: 6),
    const Text('Check back later for live sessions', style: TextStyle(color: Colors.grey, fontSize: 12)),
    const SizedBox(height: 20),
    ElevatedButton(
      onPressed: _load,
      style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
      child: const Text('Refresh'),
    ),
  ]));
}

class _RoomCard extends StatelessWidget {
  final Map<String, dynamic> room;
  const _RoomCard({required this.room});

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF07003B), Color(0xFF1a0080)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(16),
    ),
    padding: const EdgeInsets.all(16),
    child: Row(children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(4)),
              child: const Text('LIVE', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
          const SizedBox(width: 8),
          const Icon(Icons.people_rounded, color: Colors.white54, size: 14),
          const SizedBox(width: 4),
          Text('${room['listener_count'] ?? 0} listening',
              style: const TextStyle(color: Colors.white54, fontSize: 12)),
        ]),
        const SizedBox(height: 8),
        Text(room['title'] ?? 'Live Session',
            style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Row(children: [
          const Icon(Icons.person_rounded, color: Colors.white38, size: 14),
          const SizedBox(width: 4),
          Text(room['host_name'] ?? '',
              style: const TextStyle(color: Colors.white60, fontSize: 12)),
        ]),
      ])),
      const SizedBox(width: 12),
      ElevatedButton(
        onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Live rooms joining coming soon'), backgroundColor: kNavy)),
        style: ElevatedButton.styleFrom(
          backgroundColor: kOrange, foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
        ),
        child: const Text('Join', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
    ]),
  );
}
