import 'dart:async';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

// ── Data model ────────────────────────────────────────────────────────────────
class _Zone {
  final int    districtId;
  final double lat, lng;
  final int    orderCount;
  final String level; // active | busy | very_busy
  final double bonusAmount;
  final String label;
  final int    radiusM;
  const _Zone({required this.districtId, required this.lat, required this.lng,
    required this.orderCount, required this.level, required this.bonusAmount,
    required this.label, required this.radiusM});
}

// ── Provider ──────────────────────────────────────────────────────────────────
final _zonesProvider = FutureProvider.autoDispose<List<_Zone>>((ref) async {
  final raw = await ref.read(authRepoProvider).getHeatmap();
  return raw.map((z) {
    final m = z as Map<String, dynamic>;
    return _Zone(
      districtId:  (m['district_id'] as num).toInt(),
      lat:         (m['lat'] as num).toDouble(),
      lng:         (m['lng'] as num).toDouble(),
      orderCount:  (m['order_count'] as num).toInt(),
      level:       m['level'] as String,
      bonusAmount: (m['bonus_amount'] as num).toDouble(),
      label:       m['label'] as String,
      radiusM:     (m['radius_m'] as num).toInt(),
    );
  }).toList();
});

// ── Screen ────────────────────────────────────────────────────────────────────
class HeatmapScreen extends ConsumerStatefulWidget {
  const HeatmapScreen({super.key});
  @override
  ConsumerState<HeatmapScreen> createState() => _HeatmapScreenState();
}

class _HeatmapScreenState extends ConsumerState<HeatmapScreen> {
  GoogleMapController? _mapController;
  Timer? _refreshTimer;

  static const _mogadishuCenter = LatLng(2.0469, 45.3182);

  @override
  void initState() {
    super.initState();
    // Auto-refresh every 60s
    _refreshTimer = Timer.periodic(const Duration(seconds: 60), (_) {
      ref.invalidate(_zonesProvider);
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _mapController?.dispose();
    super.dispose();
  }

  // ── Build circles from zone data ──────────────────────────────────────────
  Set<Circle> _buildCircles(List<_Zone> zones) {
    final circles = <Circle>{};
    for (final z in zones) {
      final colors = _zoneColors(z.level);
      // Outer glow circle
      circles.add(Circle(
        circleId: CircleId('glow_${z.districtId}'),
        center:   LatLng(z.lat, z.lng),
        radius:   z.radiusM.toDouble() * 1.3,
        fillColor:   colors.$1.withOpacity(0.10),
        strokeColor: colors.$1.withOpacity(0.0),
        strokeWidth: 0,
      ));
      // Main fill circle
      circles.add(Circle(
        circleId: CircleId('zone_${z.districtId}'),
        center:   LatLng(z.lat, z.lng),
        radius:   z.radiusM.toDouble(),
        fillColor:   colors.$1.withOpacity(0.30),
        strokeColor: colors.$1.withOpacity(0.75),
        strokeWidth: 2,
      ));
    }
    return circles;
  }

  // ── Build label markers ───────────────────────────────────────────────────
  Future<Set<Marker>> _buildMarkers(List<_Zone> zones) async {
    final markers = <Marker>{};
    for (final z in zones) {
      final colors = _zoneColors(z.level);
      final icon = await _buildLabelIcon(z.label, colors.$1, colors.$2);
      markers.add(Marker(
        markerId: MarkerId('label_${z.districtId}'),
        position: LatLng(z.lat, z.lng),
        icon:     icon,
        anchor:   const Offset(0.5, 0.5),
        flat:     true,
        zIndex:   2,
        infoWindow: InfoWindow(
          title: z.label,
          snippet: '${z.orderCount} active orders nearby',
        ),
      ));
    }
    return markers;
  }

  // ── Colors per level ──────────────────────────────────────────────────────
  (Color, Color) _zoneColors(String level) => switch (level) {
    'very_busy' => (const Color(0xFFCC1010), Colors.white),
    'busy'      => (const Color(0xFFE05000), Colors.white),
    _           => (const Color(0xFFFF8A00), Colors.white),
  };

  // ── Draw label bitmap (pill with text) ────────────────────────────────────
  Future<BitmapDescriptor> _buildLabelIcon(String text, Color bg, Color fg) async {
    final recorder = ui.PictureRecorder();
    final canvas   = Canvas(recorder);

    const double pw = 160, ph = 44, r = 22;
    final bgPaint  = Paint()..color = bg;
    final rect     = RRect.fromLTRBR(0, 0, pw, ph, const Radius.circular(r));
    canvas.drawRRect(rect, bgPaint);

    // Signal bars icon (3 bars)
    final barPaint = Paint()..color = fg;
    for (int i = 0; i < 3; i++) {
      final bh = 6.0 + i * 5.0;
      canvas.drawRect(Rect.fromLTWH(12 + i * 8.0, ph/2 - bh/2, 5, bh), barPaint);
    }

    final tp = TextPainter(
      text: TextSpan(text: text,
          style: TextStyle(color: fg, fontSize: 17, fontWeight: FontWeight.w800,
              letterSpacing: -0.3)),
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: pw - 46);
    tp.paint(canvas, Offset(42, (ph - tp.height) / 2));

    final img  = await recorder.endRecording().toImage(pw.toInt(), ph.toInt());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return BitmapDescriptor.fromBytes(data!.buffer.asUint8List());
  }

  @override
  Widget build(BuildContext context) {
    final c     = context.dc;
    final async = ref.watch(_zonesProvider);

    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Busy Zones', style: TextStyle(fontWeight: FontWeight.w800, color: c.text, fontSize: 18)),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh_rounded, color: c.text),
            onPressed: () => ref.invalidate(_zonesProvider),
          ),
        ],
      ),
      body: Stack(children: [
        // ── Map ──────────────────────────────────────────────────────────
        async.when(
          loading: () => GoogleMap(
            onMapCreated: (ctrl) => _mapController = ctrl,
            initialCameraPosition: const CameraPosition(target: _mogadishuCenter, zoom: 12),
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            mapToolbarEnabled: false,
          ),
          error: (_, __) => GoogleMap(
            onMapCreated: (ctrl) => _mapController = ctrl,
            initialCameraPosition: const CameraPosition(target: _mogadishuCenter, zoom: 12),
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            mapToolbarEnabled: false,
          ),
          data: (zones) => FutureBuilder<Set<Marker>>(
            future: _buildMarkers(zones),
            builder: (ctx, snap) => GoogleMap(
              onMapCreated: (ctrl) => _mapController = ctrl,
              initialCameraPosition: const CameraPosition(target: _mogadishuCenter, zoom: 12),
              circles: _buildCircles(zones),
              markers: snap.data ?? {},
              myLocationEnabled: true,
              myLocationButtonEnabled: false,
              zoomControlsEnabled: false,
              mapToolbarEnabled: false,
              mapType: MapType.normal,
            ),
          ),
        ),

        // ── Legend (bottom pill) ──────────────────────────────────────────
        Positioned(
          bottom: 24, left: 16, right: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.12), blurRadius: 16, offset: const Offset(0, 4))],
            ),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              async.when(
                loading: () => const Text('Loading zones...', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                error:   (_, __) => const Text('Could not load zones', style: TextStyle(color: Colors.red, fontSize: 13)),
                data:    (zones) {
                  if (zones.isEmpty) {
                    return const Text('No busy zones right now', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Colors.grey));
                  }
                  final hasBusy     = zones.any((z) => z.level == 'busy');
                  final hasVeryBusy = zones.any((z) => z.level == 'very_busy');
                  final bonus       = zones.first.bonusAmount;
                  return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                    if (bonus > 0) ...[
                      Text('You\'ll get \$${bonus.toStringAsFixed(2)} more peak pay\non each order in busy zones',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                      const SizedBox(height: 10),
                    ],
                    Row(children: [
                      if (hasVeryBusy) ...[
                        _legend(const Color(0xFFCC1010), 'Very Busy'),
                        const SizedBox(width: 16),
                      ],
                      if (hasBusy) ...[
                        _legend(const Color(0xFFE05000), 'Busy'),
                        const SizedBox(width: 16),
                      ],
                      _legend(const Color(0xFFFF8A00), 'Active'),
                    ]),
                  ]);
                },
              ),
            ]),
          ),
        ),

        // ── Loading spinner overlay ────────────────────────────────────────
        if (async.isLoading)
          Positioned(
            top: 16, left: 0, right: 0,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20),
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 8)]),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFFF8A00))),
                  SizedBox(width: 8),
                  Text('Updating zones...', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                ]),
              ),
            ),
          ),
      ]),
    );
  }

  Widget _legend(Color color, String label) => Row(mainAxisSize: MainAxisSize.min, children: [
    Container(width: 14, height: 14, decoration: BoxDecoration(color: color.withOpacity(0.85), shape: BoxShape.circle,
        border: Border.all(color: color, width: 1.5))),
    const SizedBox(width: 5),
    Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.black87)),
  ]);
}
