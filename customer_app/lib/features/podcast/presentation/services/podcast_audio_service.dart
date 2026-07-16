import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';

/// Singleton audio service for podcast playback.
/// All screens observe [currentEpisode], [playingNotifier], [positionNotifier].
class PodcastAudioService {
  PodcastAudioService._();
  static final instance = PodcastAudioService._();

  final _player = AudioPlayer();
  final _repo   = PodcastRepository();

  // ─── Observables ──────────────────────────────────────────────────────────
  final currentEpisodeNotifier = ValueNotifier<PodcastEpisode?>(null);
  final playingNotifier        = ValueNotifier<bool>(false);
  final positionNotifier       = ValueNotifier<Duration>(Duration.zero);
  final durationNotifier       = ValueNotifier<Duration>(Duration.zero);
  final bufferingNotifier      = ValueNotifier<bool>(false);
  final speedNotifier          = ValueNotifier<double>(1.0);

  PodcastEpisode? get current  => currentEpisodeNotifier.value;
  bool            get isPlaying => playingNotifier.value;

  Timer? _progressTimer;
  bool   _recordedPlay = false;

  void init() {
    _player.onPlayerStateChanged.listen((state) {
      playingNotifier.value   = state == PlayerState.playing;
      bufferingNotifier.value = false;
    });

    _player.onPositionChanged.listen((pos) {
      positionNotifier.value = pos;
      _maybeRecordPlay(pos);
    });

    _player.onDurationChanged.listen((dur) {
      durationNotifier.value = dur;
    });

    _player.onPlayerComplete.listen((_) {
      playingNotifier.value = false;
      positionNotifier.value = Duration.zero;
      if (current != null) {
        _repo.recordPlay(current!.id, current!.duration, completed: true);
      }
    });
  }

  // ─── Load & Play ──────────────────────────────────────────────────────────

  Future<void> play(PodcastEpisode episode) async {
    if (current?.id == episode.id) {
      await toggle();
      return;
    }

    // Persist progress for previously playing episode
    if (current != null) {
      _repo.recordPlay(current!.id, positionNotifier.value.inSeconds);
    }

    currentEpisodeNotifier.value = episode;
    positionNotifier.value       = Duration(seconds: episode.resumePosition);
    durationNotifier.value       = Duration(seconds: episode.duration);
    playingNotifier.value        = false;
    bufferingNotifier.value      = true;
    _recordedPlay                = false;

    if (episode.audioUrl == null) return;

    await _player.stop();
    await _player.play(UrlSource(episode.audioUrl!));
    await _player.setPlaybackRate(speedNotifier.value);

    if (episode.resumePosition > 0) {
      await _player.seek(Duration(seconds: episode.resumePosition));
    }
  }

  Future<void> toggle() async {
    if (isPlaying) {
      await _player.pause();
    } else {
      await _player.resume();
    }
  }

  Future<void> seekTo(Duration position) async {
    await _player.seek(position);
    positionNotifier.value = position;
  }

  Future<void> skip(int seconds) async {
    final raw = positionNotifier.value + Duration(seconds: seconds);
    final dur = durationNotifier.value;
    final next = raw < Duration.zero ? Duration.zero : (raw > dur ? dur : raw);
    await seekTo(next);
  }

  Future<void> setSpeed(double speed) async {
    speedNotifier.value = speed;
    await _player.setPlaybackRate(speed);
  }

  Future<void> stop() async {
    if (current != null) {
      _repo.recordPlay(current!.id, positionNotifier.value.inSeconds);
    }
    await _player.stop();
    currentEpisodeNotifier.value = null;
    playingNotifier.value        = false;
    positionNotifier.value       = Duration.zero;
  }

  // Record play event after 30s listened
  void _maybeRecordPlay(Duration pos) {
    if (!_recordedPlay && pos.inSeconds >= 30 && current != null) {
      _recordedPlay = true;
      _repo.recordPlay(current!.id, pos.inSeconds);
    }
  }

  void dispose() {
    _player.dispose();
    _progressTimer?.cancel();
    currentEpisodeNotifier.dispose();
    playingNotifier.dispose();
    positionNotifier.dispose();
    durationNotifier.dispose();
    bufferingNotifier.dispose();
    speedNotifier.dispose();
  }
}
