import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';

class LiveTile {
  final String label;
  final String sublabel;
  final VideoTrack? video;
  final bool isMuted;
  final bool isHost;

  const LiveTile({
    required this.label,
    this.sublabel = '',
    this.video,
    this.isMuted = false,
    this.isHost = false,
  });
}

/// TikTok-style adaptive grid for live participants.
/// 1 tile → fullscreen, 2 → side by side, 3 → left+right stacked, 4 → 2×2
class LiveTiledLayout extends StatelessWidget {
  final List<LiveTile> tiles;

  const LiveTiledLayout({super.key, required this.tiles});

  @override
  Widget build(BuildContext context) {
    if (tiles.isEmpty) return const SizedBox.shrink();

    if (tiles.length == 1) {
      return _tile(tiles[0]);
    }

    return LayoutBuilder(builder: (_, constraints) {
      final w = constraints.maxWidth;
      final h = constraints.maxHeight;

      if (tiles.length == 2) {
        return Row(children: [
          SizedBox(width: w / 2, height: h, child: _tile(tiles[0])),
          Container(width: 1, height: h, color: Colors.black),
          SizedBox(width: w / 2 - 1, height: h, child: _tile(tiles[1])),
        ]);
      }

      if (tiles.length == 3) {
        return Row(children: [
          SizedBox(width: w / 2, height: h, child: _tile(tiles[0])),
          Container(width: 1, height: h, color: Colors.black),
          Column(children: [
            SizedBox(width: w / 2 - 1, height: h / 2, child: _tile(tiles[1])),
            Container(width: w / 2 - 1, height: 1, color: Colors.black),
            SizedBox(width: w / 2 - 1, height: h / 2 - 1, child: _tile(tiles[2])),
          ]),
        ]);
      }

      // 4+ : 2×2 grid (show max 4)
      final show = tiles.take(4).toList();
      return Column(children: [
        Row(children: [
          SizedBox(width: w / 2, height: h / 2, child: _tile(show[0])),
          Container(width: 1, height: h / 2, color: Colors.black),
          SizedBox(width: w / 2 - 1, height: h / 2, child: _tile(show[1])),
        ]),
        Container(width: w, height: 1, color: Colors.black),
        Row(children: [
          SizedBox(width: w / 2, height: h / 2 - 1, child: _tile(show[2])),
          Container(width: 1, height: h / 2 - 1, color: Colors.black),
          SizedBox(
            width: w / 2 - 1,
            height: h / 2 - 1,
            child: show.length > 3 ? _tile(show[3]) : _empty(),
          ),
        ]),
      ]);
    });
  }

  Widget _tile(LiveTile t) {
    return Stack(
      fit: StackFit.expand,
      children: [
        // Video or placeholder
        t.video != null
            ? VideoTrackRenderer(t.video!)
            : _noVideo(t),

        // Gradient overlay at bottom
        Positioned(
          bottom: 0, left: 0, right: 0,
          child: Container(
            height: 56,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.bottomCenter,
                end: Alignment.topCenter,
                colors: [Colors.black54, Colors.transparent],
              ),
            ),
          ),
        ),

        // Host badge
        if (t.isHost)
          Positioned(
            top: 8, left: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.orange,
                borderRadius: BorderRadius.circular(4),
              ),
              child: const Text('Host',
                  style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
            ),
          ),

        // Mute indicator
        if (t.isMuted)
          Positioned(
            top: 8, right: 8,
            child: Container(
              padding: const EdgeInsets.all(3),
              decoration: BoxDecoration(
                color: Colors.black54,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white24),
              ),
              child: const Icon(Icons.mic_off, color: Colors.white60, size: 12),
            ),
          ),

        // Name label
        Positioned(
          bottom: 6, left: 6,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(t.label,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      shadows: [Shadow(blurRadius: 4, color: Colors.black)])),
              if (t.sublabel.isNotEmpty)
                Text('@${t.sublabel}',
                    style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 10,
                        shadows: [Shadow(blurRadius: 4, color: Colors.black)])),
            ],
          ),
        ),
      ],
    );
  }

  Widget _noVideo(LiveTile t) {
    return Container(
      color: const Color(0xFF1A1A2E),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircleAvatar(
              radius: 28,
              backgroundColor: t.isHost ? Colors.orange : Colors.purple,
              child: Text(
                t.label.isNotEmpty ? t.label[0].toUpperCase() : '?',
                style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(height: 8),
            Text(t.label,
                style: const TextStyle(color: Colors.white70, fontSize: 12)),
          ],
        ),
      ),
    );
  }

  Widget _empty() {
    return Container(
      color: const Color(0xFF0D0D1A),
      child: const Center(
        child: Icon(Icons.add_circle_outline, color: Colors.white24, size: 32),
      ),
    );
  }
}
