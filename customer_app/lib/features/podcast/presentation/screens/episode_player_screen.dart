import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../services/podcast_audio_service.dart';
import '../../data/models/podcast_models.dart';
import '../widgets/podcast_cover.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class EpisodePlayerScreen extends StatelessWidget {
  const EpisodePlayerScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: kNavy,
    body: ValueListenableBuilder<PodcastEpisode?>(
      valueListenable: PodcastAudioService.instance.currentEpisodeNotifier,
      builder: (_, episode, __) {
        if (episode == null) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (Navigator.canPop(context)) Navigator.pop(context);
          });
          return const SizedBox.shrink();
        }
        return _PlayerBody(episode: episode);
      },
    ),
  );
}

class _PlayerBody extends StatelessWidget {
  final PodcastEpisode episode;
  const _PlayerBody({required this.episode});

  @override
  Widget build(BuildContext context) {
    final svc = PodcastAudioService.instance;
    return SafeArea(
      child: Column(children: [
        // ── Top bar ────────────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Row(children: [
            GestureDetector(
              onTap: () => Navigator.of(context).pop(),
              child: const Icon(Icons.keyboard_arrow_down_rounded, color: Colors.white70, size: 32),
            ),
            const Spacer(),
            Column(children: [
              const Text('PLAYING NOW', style: TextStyle(color: Colors.white54, fontSize: 9, letterSpacing: 1.5, fontWeight: FontWeight.w700)),
              Text(episode.podcast?.title ?? 'Podcast',
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
            ]),
            const Spacer(),
            const Icon(Icons.more_vert_rounded, color: Colors.white70, size: 24),
          ]),
        ),

        // ── Cover ──────────────────────────────────────────────────────────
        Expanded(
          flex: 4,
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: PodcastCover(url: episode.coverImage, width: double.infinity, height: double.infinity),
            ),
          ),
        ),

        // ── Title + like ─────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(episode.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800, height: 1.2)),
              if (episode.podcast != null)
                Text(episode.podcast!.title, style: const TextStyle(color: Colors.white54, fontSize: 13)),
            ])),
            const SizedBox(width: 12),
            ValueListenableBuilder<PodcastEpisode?>(
              valueListenable: svc.currentEpisodeNotifier,
              builder: (_, ep, __) => GestureDetector(
                onTap: () {},
                child: Icon(
                  ep?.isLiked == true ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                  color: ep?.isLiked == true ? kOrange : Colors.white54, size: 28,
                ),
              ),
            ),
          ]),
        ),

        const SizedBox(height: 20),

        // ── Waveform ─────────────────────────────────────────────────────
        SizedBox(
          height: 52,
          child: ValueListenableBuilder<bool>(
            valueListenable: svc.playingNotifier,
            builder: (_, playing, __) => ValueListenableBuilder<Duration>(
              valueListenable: svc.positionNotifier,
              builder: (_, pos, __) => ValueListenableBuilder<Duration>(
                valueListenable: svc.durationNotifier,
                builder: (_, dur, __) {
                  final progress = dur.inMilliseconds > 0
                      ? pos.inMilliseconds / dur.inMilliseconds : 0.0;
                  return _WaveformBar(playing: playing, progress: progress);
                },
              ),
            ),
          ),
        ),

        const SizedBox(height: 8),

        // ── Seek bar ─────────────────────────────────────────────────────
        ValueListenableBuilder<Duration>(
          valueListenable: svc.positionNotifier,
          builder: (_, pos, __) => ValueListenableBuilder<Duration>(
            valueListenable: svc.durationNotifier,
            builder: (_, dur, __) {
              final progress = dur.inMilliseconds > 0
                  ? (pos.inMilliseconds / dur.inMilliseconds).clamp(0.0, 1.0) : 0.0;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(children: [
                  SliderTheme(
                    data: SliderThemeData(
                      thumbColor: Colors.white,
                      activeTrackColor: kOrange,
                      inactiveTrackColor: Colors.white24,
                      thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
                      overlayShape: const RoundSliderOverlayShape(overlayRadius: 12),
                      trackHeight: 3,
                    ),
                    child: Slider(
                      value: progress,
                      onChanged: (v) => svc.seek(Duration(
                          milliseconds: (v * dur.inMilliseconds).round())),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text(_fmt(pos), style: const TextStyle(color: Colors.white54, fontSize: 11)),
                      Text(_fmt(dur), style: const TextStyle(color: Colors.white54, fontSize: 11)),
                    ]),
                  ),
                ]),
              );
            },
          ),
        ),

        const SizedBox(height: 12),

        // ── Controls ─────────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            // Speed
            ValueListenableBuilder<double>(
              valueListenable: svc.speedNotifier,
              builder: (_, speed, __) => GestureDetector(
                onTap: () => svc.cycleSpeed(),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white24),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text('${speed}x', style: const TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w700)),
                ),
              ),
            ),
            // Skip -15
            GestureDetector(
              onTap: () => svc.skip(-15),
              child: const Icon(Icons.replay_10_rounded, color: Colors.white70, size: 34),
            ),
            // Play/Pause — main button
            ValueListenableBuilder<bool>(
              valueListenable: svc.playingNotifier,
              builder: (_, playing, __) => GestureDetector(
                onTap: () => playing ? svc.pause() : svc.resume(),
                child: Container(
                  width: 68, height: 68,
                  decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                  child: Icon(
                    playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                    color: Colors.white, size: 38,
                  ),
                ),
              ),
            ),
            // Skip +30
            GestureDetector(
              onTap: () => svc.skip(30),
              child: const Icon(Icons.forward_30_rounded, color: Colors.white70, size: 34),
            ),
            // Share
            GestureDetector(
              onTap: () {},
              child: const Icon(Icons.share_rounded, color: Colors.white70, size: 28),
            ),
          ]),
        ),

        const SizedBox(height: 28),

        // ── Bottom actions ────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
            _ActionBtn(icon: Icons.bookmark_border_rounded, label: 'Save', onTap: () {}),
            _ActionBtn(icon: Icons.download_rounded, label: 'Download', onTap: () {}),
            _ActionBtn(icon: Icons.comment_outlined, label: 'Comments', onTap: () {}),
            _ActionBtn(icon: Icons.playlist_add_rounded, label: 'Queue', onTap: () {}),
          ]),
        ),

        const SizedBox(height: 24),
      ]),
    );
  }

  String _fmt(Duration d) {
    final h = d.inHours;
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return h > 0 ? '$h:$m:$s' : '$m:$s';
  }
}

// ─── Waveform animation ───────────────────────────────────────────────────────

class _WaveformBar extends StatefulWidget {
  final bool playing;
  final double progress;
  const _WaveformBar({required this.playing, required this.progress});

  @override
  State<_WaveformBar> createState() => _WaveformBarState();
}

class _WaveformBarState extends State<_WaveformBar> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  final _rng = math.Random(42);
  late List<double> _heights;
  static const _bars = 42;

  @override
  void initState() {
    super.initState();
    _heights = List.generate(_bars, (_) => 0.2 + _rng.nextDouble() * 0.8);
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 800))..repeat();
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  void didUpdateWidget(_WaveformBar old) {
    super.didUpdateWidget(old);
    if (widget.playing && !_ctrl.isAnimating) _ctrl.repeat();
    if (!widget.playing && _ctrl.isAnimating) _ctrl.stop();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _ctrl,
    builder: (_, __) => CustomPaint(
      size: const Size(double.infinity, 52),
      painter: _WavePainter(
        heights: _heights,
        progress: widget.progress,
        tick: _ctrl.value,
        playing: widget.playing,
      ),
    ),
  );
}

class _WavePainter extends CustomPainter {
  final List<double> heights;
  final double progress;
  final double tick;
  final bool playing;
  _WavePainter({required this.heights, required this.progress, required this.tick, required this.playing});

  @override
  void paint(Canvas canvas, Size size) {
    final barW = size.width / (heights.length * 1.5);
    final gap   = barW * 0.5;
    final maxH  = size.height;
    final cx    = size.width * progress;
    final pivot = (progress * heights.length).floor();

    for (var i = 0; i < heights.length; i++) {
      final x  = i * (barW + gap);
      double h = heights[i] * maxH;
      if (playing && i >= pivot - 2 && i <= pivot + 2) {
        final wave = math.sin(tick * math.pi * 2 + i * 0.5);
        h = h * (0.8 + 0.2 * wave.abs());
      }

      final isPlayed = x + barW < cx;
      final paint = Paint()
        ..color = isPlayed ? kOrange : Colors.white.withAlpha(51)
        ..style = PaintingStyle.fill;
      final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(x, (maxH - h) / 2, barW, h),
        const Radius.circular(2),
      );
      canvas.drawRRect(rect, paint);
    }
  }

  @override
  bool shouldRepaint(_WavePainter old) =>
      old.progress != progress || old.tick != tick || old.playing != playing;
}

// ─── Action Button ────────────────────────────────────────────────────────────

class _ActionBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _ActionBtn({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, color: Colors.white54, size: 22),
      const SizedBox(height: 3),
      Text(label, style: const TextStyle(color: Colors.white38, fontSize: 9, fontWeight: FontWeight.w600)),
    ]),
  );
}
