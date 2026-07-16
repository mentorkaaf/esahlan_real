import 'package:flutter/material.dart';
import '../services/podcast_audio_service.dart';
import '../screens/episode_player_screen.dart';

/// Persistent mini player bar — shown above bottom nav when audio is active.
/// Wrap the bottom of your Scaffold with [PodcastMiniPlayer].
class PodcastMiniPlayer extends StatelessWidget {
  const PodcastMiniPlayer({super.key});

  @override
  Widget build(BuildContext context) {
    final svc = PodcastAudioService.instance;
    return ValueListenableBuilder(
      valueListenable: svc.currentEpisodeNotifier,
      builder: (_, episode, __) {
        if (episode == null) return const SizedBox.shrink();
        return GestureDetector(
          onTap: () => Navigator.of(context).push(
            PageRouteBuilder(
              pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
              transitionsBuilder: (_, anim, __, child) =>
                  SlideTransition(
                    position: Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
                        .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
                    child: child,
                  ),
            ),
          ),
          child: Container(
            margin: const EdgeInsets.fromLTRB(8, 0, 8, 4),
            decoration: BoxDecoration(
              color: const Color(0xFF1C1C28),
              borderRadius: BorderRadius.circular(14),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.4), blurRadius: 16, offset: const Offset(0, 4))],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  child: Row(
                    children: [
                      // Cover
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: episode.coverImage != null
                            ? Image.network(episode.coverImage!, width: 44, height: 44, fit: BoxFit.cover,
                                errorBuilder: (_,__,___) => _PlaceholderCover())
                            : _PlaceholderCover(),
                      ),
                      const SizedBox(width: 10),
                      // Title + show
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(episode.title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
                            if (episode.podcast != null)
                              Text(episode.podcast!.title,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 11)),
                          ],
                        ),
                      ),
                      // Skip back 15s
                      _IconBtn(
                        icon: Icons.replay_10_rounded,
                        onTap: () => PodcastAudioService.instance.skip(-10),
                      ),
                      // Play/pause
                      ValueListenableBuilder(
                        valueListenable: svc.playingNotifier,
                        builder: (_, playing, __) => ValueListenableBuilder(
                          valueListenable: svc.bufferingNotifier,
                          builder: (_, buffering, __) {
                            return GestureDetector(
                              onTap: () => svc.toggle(),
                              child: Container(
                                width: 36,
                                height: 36,
                                decoration: const BoxDecoration(
                                  color: Color(0xFF7C3AED),
                                  shape: BoxShape.circle,
                                ),
                                child: buffering
                                    ? const Padding(
                                        padding: EdgeInsets.all(8),
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : Icon(
                                        playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                                        color: Colors.white,
                                        size: 22,
                                      ),
                              ),
                            );
                          },
                        ),
                      ),
                      // Skip forward 30s
                      _IconBtn(
                        icon: Icons.forward_30_rounded,
                        onTap: () => PodcastAudioService.instance.skip(30),
                      ),
                      // Close
                      _IconBtn(
                        icon: Icons.close_rounded,
                        onTap: () => svc.stop(),
                        color: const Color(0xFF6B6B80),
                      ),
                    ],
                  ),
                ),
                // Progress bar
                ValueListenableBuilder(
                  valueListenable: svc.positionNotifier,
                  builder: (_, pos, __) {
                    final dur = svc.durationNotifier.value;
                    final ratio = dur.inMilliseconds > 0
                        ? (pos.inMilliseconds / dur.inMilliseconds).clamp(0.0, 1.0)
                        : 0.0;
                    return ClipRRect(
                      borderRadius: const BorderRadius.only(
                        bottomLeft: Radius.circular(14),
                        bottomRight: Radius.circular(14),
                      ),
                      child: LinearProgressIndicator(
                        value: ratio,
                        backgroundColor: const Color(0xFF2A2A3A),
                        valueColor: const AlwaysStoppedAnimation(Color(0xFF7C3AED)),
                        minHeight: 3,
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _PlaceholderCover extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width: 44, height: 44,
    color: const Color(0xFF2A2A3A),
    child: const Icon(Icons.podcasts_rounded, color: Color(0xFF3A3A4A), size: 22),
  );
}

class _IconBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final Color color;
  const _IconBtn({required this.icon, required this.onTap, this.color = Colors.white70});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.all(6),
      child: Icon(icon, color: color, size: 22),
    ),
  );
}
