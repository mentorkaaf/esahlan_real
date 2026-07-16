import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../services/podcast_audio_service.dart';
import '../../data/models/podcast_models.dart';

/// Full-screen episode player — slides up from bottom.
class EpisodePlayerScreen extends StatelessWidget {
  const EpisodePlayerScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0A0F),
      body: ValueListenableBuilder<PodcastEpisode?>(
        valueListenable: PodcastAudioService.instance.currentEpisodeNotifier,
        builder: (_, episode, __) {
          if (episode == null) {
            Navigator.of(context).pop();
            return const SizedBox.shrink();
          }
          return _PlayerBody(episode: episode);
        },
      ),
    );
  }
}

class _PlayerBody extends StatelessWidget {
  final PodcastEpisode episode;
  const _PlayerBody({required this.episode});

  @override
  Widget build(BuildContext context) {
    final svc = PodcastAudioService.instance;
    final top = MediaQuery.of(context).padding.top;

    return SafeArea(
      child: Column(
        children: [
          // ── Drag handle + close ──
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Row(
              children: [
                GestureDetector(
                  onTap: () => Navigator.of(context).pop(),
                  child: const Icon(Icons.keyboard_arrow_down_rounded, color: Colors.white70, size: 32),
                ),
                const Spacer(),
                const Text('Hada Dhageysanaya', style: TextStyle(color: Color(0xFF6B6B80), fontSize: 12, fontWeight: FontWeight.w600)),
                const Spacer(),
                const Icon(Icons.more_vert_rounded, color: Colors.white70, size: 24),
              ],
            ),
          ),

          // ── Cover art ──
          Expanded(
            flex: 4,
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Center(
                child: AspectRatio(
                  aspectRatio: 1,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: episode.coverImage != null
                        ? Image.network(episode.coverImage!, fit: BoxFit.cover,
                            errorBuilder: (_,__,___) => _CoverPlaceholder())
                        : _CoverPlaceholder(),
                  ),
                ),
              ),
            ),
          ),

          // ── Title + actions ──
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        episode.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800, height: 1.25),
                      ),
                      const SizedBox(height: 4),
                      if (episode.podcast != null)
                        Text(episode.podcast!.title,
                            style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 13)),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                _LikeButton(episode: episode),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // ── Seek bar ──
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: ValueListenableBuilder<Duration>(
              valueListenable: PodcastAudioService.instance.positionNotifier,
              builder: (_, pos, __) {
                return ValueListenableBuilder<Duration>(
                  valueListenable: PodcastAudioService.instance.durationNotifier,
                  builder: (_, dur, __) {
                    final ratio = dur.inMilliseconds > 0
                        ? (pos.inMilliseconds / dur.inMilliseconds).clamp(0.0, 1.0)
                        : 0.0;
                    return Column(
                      children: [
                        SliderTheme(
                          data: SliderThemeData(
                            trackHeight: 4,
                            thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 7),
                            overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
                            activeTrackColor: const Color(0xFF7C3AED),
                            inactiveTrackColor: const Color(0xFF2A2A3A),
                            thumbColor: const Color(0xFF7C3AED),
                            overlayColor: const Color(0x337C3AED),
                          ),
                          child: Slider(
                            value: ratio,
                            onChanged: (v) {
                              final target = Duration(milliseconds: (v * dur.inMilliseconds).round());
                              PodcastAudioService.instance.seekTo(target);
                            },
                          ),
                        ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(_fmt(pos), style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 11)),
                            Text(_fmt(dur), style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 11)),
                          ],
                        ),
                      ],
                    );
                  },
                );
              },
            ),
          ),

          const SizedBox(height: 16),

          // ── Controls row ──
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Speed
                _SpeedButton(),
                // Skip -10
                _CtrlBtn(icon: Icons.replay_10_rounded, size: 36, onTap: () => svc.skip(-10)),
                // Play/Pause
                ValueListenableBuilder<bool>(
                  valueListenable: svc.playingNotifier,
                  builder: (_, playing, __) => ValueListenableBuilder<bool>(
                    valueListenable: svc.bufferingNotifier,
                    builder: (_, buffering, __) => GestureDetector(
                      onTap: () => svc.toggle(),
                      child: Container(
                        width: 68,
                        height: 68,
                        decoration: BoxDecoration(
                          color: const Color(0xFF7C3AED),
                          shape: BoxShape.circle,
                          boxShadow: [BoxShadow(color: const Color(0xFF7C3AED).withOpacity(0.4), blurRadius: 24, spreadRadius: 2)],
                        ),
                        child: buffering
                            ? const Padding(padding: EdgeInsets.all(18),
                                child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                            : Icon(playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                                color: Colors.white, size: 38),
                      ),
                    ),
                  ),
                ),
                // Skip +30
                _CtrlBtn(icon: Icons.forward_30_rounded, size: 36, onTap: () => svc.skip(30)),
                // Sleep timer placeholder
                const _CtrlBtn(icon: Icons.bedtime_outlined, size: 24),
              ],
            ),
          ),

          const SizedBox(height: 32),
        ],
      ),
    );
  }

  String _fmt(Duration d) {
    final h = d.inHours;
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return h > 0 ? '$h:$m:$s' : '$m:$s';
  }
}

class _LikeButton extends StatefulWidget {
  final PodcastEpisode episode;
  const _LikeButton({required this.episode});
  @override
  State<_LikeButton> createState() => _LikeButtonState();
}

class _LikeButtonState extends State<_LikeButton> {
  late bool _liked;
  @override
  void initState() { super.initState(); _liked = widget.episode.isLiked; }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => setState(() => _liked = !_liked),
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 200),
        child: Icon(
          _liked ? Icons.favorite_rounded : Icons.favorite_border_rounded,
          key: ValueKey(_liked),
          color: _liked ? Colors.pinkAccent : Colors.white54,
          size: 28,
        ),
      ),
    );
  }
}

class _SpeedButton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final svc = PodcastAudioService.instance;
    return ValueListenableBuilder<double>(
      valueListenable: svc.speedNotifier,
      builder: (_, speed, __) => GestureDetector(
        onTap: () {
          final speeds = [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];
          final idx    = speeds.indexOf(speed);
          final next   = speeds[(idx + 1) % speeds.length];
          svc.setSpeed(next);
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: const Color(0xFF1C1C28),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            '${speed}x',
            style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700),
          ),
        ),
      ),
    );
  }
}

class _CtrlBtn extends StatelessWidget {
  final IconData icon;
  final double size;
  final VoidCallback? onTap;
  const _CtrlBtn({required this.icon, required this.size, this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.all(8),
      child: Icon(icon, color: onTap != null ? Colors.white : Colors.white38, size: size),
    ),
  );
}

class _CoverPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    color: const Color(0xFF1C1C28),
    child: const Icon(Icons.podcasts_rounded, color: Color(0xFF3A3A4A), size: 80),
  );
}
