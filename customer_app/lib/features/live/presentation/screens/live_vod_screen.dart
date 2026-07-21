import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

// ── VOD List Screen ───────────────────────────────────────────────────────────

class LiveVODScreen extends StatefulWidget {
  const LiveVODScreen({super.key});

  @override
  State<LiveVODScreen> createState() => _LiveVODScreenState();
}

class _LiveVODScreenState extends State<LiveVODScreen> {
  final _repo = LiveRepository();
  List<LiveRecording> _vods = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final v = await _repo.getVODs();
      if (mounted) setState(() { _vods = v; _loading = false; });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        title: const Text('📹 Replays', style: TextStyle(color: Colors.white)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Colors.orange))
          : _vods.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text('📹', style: TextStyle(fontSize: 48)),
                      SizedBox(height: 12),
                      Text('No replays yet',
                          style: TextStyle(color: Colors.white54, fontSize: 16)),
                      SizedBox(height: 6),
                      Text('Live recordings will appear here',
                          style: TextStyle(color: Colors.white38, fontSize: 13)),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _vods.length,
                  itemBuilder: (_, i) => _VODCard(
                    recording: _vods[i],
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => VODPlayerScreen(recording: _vods[i]),
                      ),
                    ),
                  ),
                ),
    );
  }
}

class _VODCard extends StatelessWidget {
  final LiveRecording recording;
  final VoidCallback onTap;

  const _VODCard({required this.recording, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: const Color(0xFF1A1A2E),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Thumbnail
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: AspectRatio(
                aspectRatio: 9 / 16,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (recording.thumbnailUrl != null)
                      Image.network(recording.thumbnailUrl!, fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => Container(color: const Color(0xFF0D0D1A)))
                    else
                      Container(
                        color: const Color(0xFF0D0D1A),
                        child: const Center(
                          child: Icon(Icons.play_circle_fill,
                              color: Colors.white30, size: 48),
                        ),
                      ),
                    Positioned.fill(
                      child: Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)],
                          ),
                        ),
                      ),
                    ),
                    // Play button
                    const Center(
                      child: Icon(Icons.play_circle_fill,
                          color: Colors.white70, size: 52),
                    ),
                    // Duration badge
                    Positioned(
                      bottom: 8, right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.75),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          recording.durationStr,
                          style: const TextStyle(
                              color: Colors.white, fontSize: 11,
                              fontWeight: FontWeight.bold),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // Info
            Padding(
              padding: const EdgeInsets.all(10),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundImage: recording.hostAvatar.isNotEmpty
                        ? NetworkImage(recording.hostAvatar)
                        : null,
                    backgroundColor: Colors.orange.withValues(alpha: 0.3),
                    child: recording.hostAvatar.isEmpty
                        ? Text(recording.hostName.isNotEmpty
                              ? recording.hostName[0].toUpperCase()
                              : '?',
                            style: const TextStyle(color: Colors.white, fontSize: 12))
                        : null,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(recording.title,
                            style: const TextStyle(color: Colors.white,
                                fontWeight: FontWeight.w600, fontSize: 13),
                            maxLines: 2, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 2),
                        Text(recording.hostName,
                            style: const TextStyle(
                                color: Colors.white54, fontSize: 11)),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.remove_red_eye,
                              size: 12, color: Colors.white38),
                          const SizedBox(width: 3),
                          Text('${recording.viewCount}',
                              style: const TextStyle(
                                  color: Colors.white38, fontSize: 11)),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── VOD Player Screen ─────────────────────────────────────────────────────────

class VODPlayerScreen extends StatefulWidget {
  final LiveRecording recording;

  const VODPlayerScreen({super.key, required this.recording});

  @override
  State<VODPlayerScreen> createState() => _VODPlayerScreenState();
}

class _VODPlayerScreenState extends State<VODPlayerScreen> {
  VideoPlayerController? _ctrl;
  bool _initialized = false;
  bool _showControls = true;
  final _repo = LiveRepository();

  @override
  void initState() {
    super.initState();
    _init();
    _repo.recordView(widget.recording.id).catchError((_) {});
  }

  Future<void> _init() async {
    final url = widget.recording.recordingUrl;
    if (url == null || url.isEmpty) return;
    _ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    await _ctrl!.initialize();
    if (mounted) {
      setState(() => _initialized = true);
      _ctrl!.play();
    }
  }

  @override
  void dispose() {
    _ctrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTap: () => setState(() => _showControls = !_showControls),
        child: Stack(
          children: [
            // Video
            if (_initialized && _ctrl != null)
              Positioned.fill(
                child: Center(
                  child: AspectRatio(
                    aspectRatio: _ctrl!.value.aspectRatio,
                    child: VideoPlayer(_ctrl!),
                  ),
                ),
              )
            else
              const Center(child: CircularProgressIndicator(color: Colors.orange)),

            // Controls overlay
            if (_showControls) ...[
              // Top bar
              Positioned(
                top: 0, left: 0, right: 0,
                child: SafeArea(
                  child: Row(
                    children: [
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.arrow_back, color: Colors.white),
                      ),
                      Expanded(
                        child: Text(widget.recording.title,
                            style: const TextStyle(
                                color: Colors.white, fontWeight: FontWeight.bold),
                            overflow: TextOverflow.ellipsis),
                      ),
                    ],
                  ),
                ),
              ),
              // Center play/pause
              if (_initialized && _ctrl != null)
                Center(
                  child: GestureDetector(
                    onTap: () {
                      if (_ctrl!.value.isPlaying) {
                        _ctrl!.pause();
                      } else {
                        _ctrl!.play();
                      }
                      setState(() {});
                    },
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.black45,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        _ctrl!.value.isPlaying ? Icons.pause : Icons.play_arrow,
                        color: Colors.white, size: 36,
                      ),
                    ),
                  ),
                ),
              // Bottom progress bar
              if (_initialized && _ctrl != null)
                Positioned(
                  bottom: 0, left: 0, right: 0,
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: VideoProgressIndicator(
                        _ctrl!,
                        allowScrubbing: true,
                        colors: const VideoProgressColors(
                          playedColor: Colors.orange,
                          bufferedColor: Colors.white24,
                          backgroundColor: Colors.white12,
                        ),
                      ),
                    ),
                  ),
                ),
            ],

            // No video available message
            if (!_initialized && widget.recording.recordingUrl == null)
              const Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.video_library, color: Colors.white30, size: 48),
                    SizedBox(height: 12),
                    Text('Recording not available yet',
                        style: TextStyle(color: Colors.white54)),
                    SizedBox(height: 6),
                    Text('Please check back later',
                        style: TextStyle(color: Colors.white30, fontSize: 12)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
