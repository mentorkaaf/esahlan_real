import 'dart:async';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';

enum StreamQuality { excellent, good, fair, poor, loading }

class StreamQualityIndicator extends StatefulWidget {
  final Room? room;
  const StreamQualityIndicator({super.key, this.room});

  @override
  State<StreamQualityIndicator> createState() => _StreamQualityIndicatorState();
}

class _StreamQualityIndicatorState extends State<StreamQualityIndicator> {
  StreamQuality _quality = StreamQuality.loading;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 3), (_) => _update());
    _update();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _update() {
    if (!mounted) return;
    final room = widget.room;
    if (room == null) {
      setState(() => _quality = StreamQuality.loading);
      return;
    }

    // Collect connection quality from all remote participants
    ConnectionQuality? worst;
    for (final p in room.remoteParticipants.values) {
      final q = p.connectionQuality;
      if (worst == null) {
        worst = q;
      } else if (_rank(q) < _rank(worst)) {
        worst = q;
      }
    }

    final q = worst == null ? StreamQuality.loading : _fromLk(worst);
    if (mounted && q != _quality) setState(() => _quality = q);
  }

  int _rank(ConnectionQuality q) {
    switch (q) {
      case ConnectionQuality.excellent: return 3;
      case ConnectionQuality.good:      return 2;
      case ConnectionQuality.poor:      return 1;
      default:                          return 0;
    }
  }

  StreamQuality _fromLk(ConnectionQuality q) {
    switch (q) {
      case ConnectionQuality.excellent: return StreamQuality.excellent;
      case ConnectionQuality.good:      return StreamQuality.good;
      case ConnectionQuality.poor:      return StreamQuality.poor;
      default:                          return StreamQuality.fair;
    }
  }

  Color get _color {
    switch (_quality) {
      case StreamQuality.excellent: return Colors.green;
      case StreamQuality.good:      return const Color(0xFF8BC34A);
      case StreamQuality.fair:      return Colors.orange;
      case StreamQuality.poor:      return Colors.red;
      case StreamQuality.loading:   return Colors.white38;
    }
  }

  String get _label {
    switch (_quality) {
      case StreamQuality.excellent: return 'HD';
      case StreamQuality.good:      return 'Good';
      case StreamQuality.fair:      return 'Fair';
      case StreamQuality.poor:      return 'Poor';
      case StreamQuality.loading:   return '...';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.black54,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _color.withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _QualityBars(quality: _quality, color: _color),
          const SizedBox(width: 4),
          Text(_label,
              style: TextStyle(
                  color: _color,
                  fontSize: 10,
                  fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}

class _QualityBars extends StatelessWidget {
  final StreamQuality quality;
  final Color color;
  const _QualityBars({required this.quality, required this.color});

  int get _bars {
    switch (quality) {
      case StreamQuality.excellent: return 4;
      case StreamQuality.good:      return 3;
      case StreamQuality.fair:      return 2;
      case StreamQuality.poor:      return 1;
      case StreamQuality.loading:   return 0;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: List.generate(4, (i) {
        final active = i < _bars;
        final height = 4.0 + i * 2.0;
        return Container(
          width: 3,
          height: height,
          margin: const EdgeInsets.only(right: 1),
          decoration: BoxDecoration(
            color: active ? color : Colors.white20,
            borderRadius: BorderRadius.circular(1),
          ),
        );
      }),
    );
  }
}
