import 'dart:async';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

// ── Data model ────────────────────────────────────────────────────────────────
class _Zone {
  final int    vendorId;
  final String vendorName;
  final String? logo;
  final double lat, lng;
  final int    orderCount;
  final String level; // active | busy | very_busy
  final double bonusAmount;
  final String label;
  final int    radiusM;
  const _Zone({required this.vendorId, required this.vendorName, this.logo,
    required this.lat, required this.lng, required this.orderCount,
    required this.level, required this.bonusAmount,
    required this.label, required this.radiusM});
}

// ── Provider ──────────────────────────────────────────────────────────────────
final _zonesProvider = FutureProvider.autoDispose<List<_Zone>>((ref) async {
  final raw = await ref.read(authRepoProvider).getHeatmap();
  return raw.map((z) {
    final m = z as Map<String, dynamic>;
    return _Zone(
      vendorId:    (m['vendor_id'] as num).toInt(),
      vendorName:  m['vendor_name'] as String? ?? 'Store',
      logo:        m['logo'] as String?,
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
      // Outer glow
      circles.add(Circle(
        circleId: CircleId('glow_${z.vendorId}'),
        center:   LatLng(z.lat, z.lng),
        radius:   z.radiusM.toDouble() * 1.4,
        fillColor:   colors.$1.withOpacity(0.08),
        strokeColor: colors.$1.withOpacity(0.0),
        strokeWidth: 0,
      ));
      // Main circle
      circles.add(Circle(
        circleId: CircleId('zone_${z.vendorId}'),
        center:   LatLng(z.lat, z.lng),
        radius:   z.radiusM.toDouble(),
        fillColor:   colors.$1.withOpacity(0.25),
        strokeColor: colors.$1.withOpacity(0.70),
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
      final icon = await _buildVendorIcon(z.vendorName, z.orderCount, colors.$1);
      markers.add(Marker(
        markerId: MarkerId('vendor_${z.vendorId}'),
        position: LatLng(z.lat, z.lng),
        icon:     icon,
        anchor:   const Offset(0.5, 1.0),
        zIndex:   3,
        infoWindow: InfoWindow(
          title: z.vendorName,
          snippet: '${z.orderCount} active order${z.orderCount > 1 ? 's' : ''} · ${z.label}',
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

  // ── Vendor marker: store circle + name pill + count badge ────────────────
  Future<BitmapDescriptor> _buildVendorIcon(String name, int count, Color zoneColor) async {
    // Scale factor — multiply everything by 3 for sharp hi-DPI rendering
    const double s = 3.0;

    final shortName = name.length > 18 ? '${name.substring(0, 16)}…' : name;

    // Measure name text at 3× scale
    final nameTp = TextPainter(
      text: TextSpan(
        text: shortName,
        style: TextStyle(color: Colors.white, fontSize: 13 * s, fontWeight: FontWeight.w800),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    // Pill width = name + padding
    final pillW  = nameTp.width + 24 * s;
    final pillH  = 28 * s;
    final circleR = 30 * s; // icon circle radius
    final badgeR  = 13 * s; // count badge radius
    final canvasW = (pillW > circleR * 2 + badgeR ? pillW + badgeR : circleR * 2 + badgeR * 2 + 4 * s);
    final canvasH = circleR * 2 + 6 * s + pillH + 4 * s;

    final cx = canvasW / 2;
    final cy = circleR;

    final recorder = ui.PictureRecorder();
    final canvas   = Canvas(recorder);

    // ── Shadow ────────────────────────────────────────────────────────────
    canvas.drawCircle(Offset(cx, cy + 3 * s), circleR + 2 * s,
        Paint()..color = Colors.black.withOpacity(0.20)
                ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6));

    // ── White circle background ───────────────────────────────────────────
    canvas.drawCircle(Offset(cx, cy), circleR, Paint()..color = Colors.white);

    // ── Colored border ring ───────────────────────────────────────────────
    canvas.drawCircle(Offset(cx, cy), circleR,
        Paint()..color = zoneColor..style = PaintingStyle.stroke..strokeWidth = 4 * s);

    // ── Store emoji 🏪 ────────────────────────────────────────────────────
    final emojiTp = TextPainter(
      text: TextSpan(text: '🏪', style: TextStyle(fontSize: 28 * s)),
      textDirection: TextDirection.ltr,
    )..layout();
    emojiTp.paint(canvas, Offset(cx - emojiTp.width / 2, cy - emojiTp.height / 2));

    // ── Count badge (top-right) ───────────────────────────────────────────
    final bx = cx + circleR - badgeR * 0.3;
    final by = cy - circleR + badgeR * 0.3;
    canvas.drawCircle(Offset(bx, by), badgeR,
        Paint()..color = Colors.white);
    canvas.drawCircle(Offset(bx, by), badgeR,
        Paint()..color = zoneColor..style = PaintingStyle.stroke..strokeWidth = 2 * s);
    final countTp = TextPainter(
      text: TextSpan(text: '$count',
          style: TextStyle(color: zoneColor, fontSize: 13 * s, fontWeight: FontWeight.w900)),
      textDirection: TextDirection.ltr,
    )..layout();
    countTp.paint(canvas, Offset(bx - countTp.width / 2, by - countTp.height / 2));

    // ── Name pill below circle ────────────────────────────────────────────
    final pillX = cx - pillW / 2;
    final pillY = cy + circleR + 6 * s;
    final pillRect = RRect.fromLTRBR(
      pillX, pillY, pillX + pillW, pillY + pillH,
      Radius.circular(pillH / 2),
    );
    // Pill shadow
    canvas.drawRRect(pillRect,
        Paint()..color = Colors.black.withOpacity(0.18)
                ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4));
    // Pill background
    canvas.drawRRect(pillRect, Paint()..color = zoneColor);
    // Name text
    nameTp.paint(canvas,
        Offset(cx - nameTp.width / 2, pillY + (pillH - nameTp.height) / 2));

    // Render at 3× then Flutter scales down = crisp result
    final img  = await recorder.endRecording()
        .toImage(canvasW.ceil(), canvasH.ceil());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return BitmapDescriptor.fromBytes(
      data!.buffer.asUint8List(),
      size: Size(canvasW / s, canvasH / s), // logical pixels
    );
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
