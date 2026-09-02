import 'dart:async';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

// ── Model ─────────────────────────────────────────────────────────────────────
class _Zone {
  final int    vendorId;
  final String vendorName;
  final double lat, lng;
  final int    orderCount;
  final String level;
  final double bonusAmount;
  final String label;
  final int    radiusM;
  const _Zone({
    required this.vendorId, required this.vendorName,
    required this.lat,      required this.lng,
    required this.orderCount, required this.level,
    required this.bonusAmount, required this.label,
    required this.radiusM,
  });
}

// ── Provider ──────────────────────────────────────────────────────────────────
final _zonesProvider = FutureProvider.autoDispose<List<_Zone>>((ref) async {
  final raw = await ref.read(authRepoProvider).getHeatmap();
  return raw.map((z) {
    final m = z as Map<String, dynamic>;
    return _Zone(
      vendorId:    (m['vendor_id']   as num).toInt(),
      vendorName:  m['vendor_name']  as String? ?? 'Store',
      lat:         (m['lat']         as num).toDouble(),
      lng:         (m['lng']         as num).toDouble(),
      orderCount:  (m['order_count'] as num).toInt(),
      level:       m['level']        as String,
      bonusAmount: (m['bonus_amount']as num).toDouble(),
      label:       m['label']        as String,
      radiusM:     (m['radius_m']    as num).toInt(),
    );
  }).toList();
});

// ── Colors ────────────────────────────────────────────────────────────────────
Color _zoneColor(String level) => switch (level) {
  'very_busy' => const Color(0xFFCC1010),
  'busy'      => const Color(0xFFE05000),
  _           => const Color(0xFFFF8A00),
};

// ── Marker icon builder (simple, fixed logical size) ──────────────────────────
// Total: 56×72 logical pixels
// - Circle 56×56 (white bg + colored border + emoji + count badge)
// - Tail 16px pointing down (same color as border)
Future<BitmapDescriptor> _buildMarkerIcon(int count, String level) async {
  final color = _zoneColor(level);

  // Draw at 2× for sharpness, display at 1×
  const double scale  = 2.0;
  const double diam   = 56 * scale;  // circle diameter
  const double tail   = 16 * scale;  // pointer height
  const double canvasW = diam;
  const double canvasH = diam + tail;
  final double cx = diam / 2, cy = diam / 2;
  final double r  = diam / 2 - 2 * scale;

  final rec    = ui.PictureRecorder();
  final canvas = Canvas(rec);

  // ── Drop shadow ───────────────────────────────────────────────────────────
  canvas.drawCircle(
    Offset(cx, cy + 2 * scale), r + scale,
    Paint()
      ..color      = Colors.black.withOpacity(0.20)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4),
  );

  // ── White circle ──────────────────────────────────────────────────────────
  canvas.drawCircle(Offset(cx, cy), r, Paint()..color = Colors.white);

  // ── Colored border ────────────────────────────────────────────────────────
  canvas.drawCircle(
    Offset(cx, cy), r,
    Paint()
      ..color       = color
      ..style       = PaintingStyle.stroke
      ..strokeWidth = 3.5 * scale,
  );

  // ── Store emoji ───────────────────────────────────────────────────────────
  final emojiTp = TextPainter(
    text: TextSpan(text: '🏪', style: TextStyle(fontSize: 22 * scale)),
    textDirection: TextDirection.ltr,
  )..layout();
  emojiTp.paint(
    canvas,
    Offset(cx - emojiTp.width / 2, cy - emojiTp.height / 2 - 4 * scale),
  );

  // ── Count badge (top-right) ───────────────────────────────────────────────
  final badgeR = 11 * scale;
  final bx = cx + r * 0.65, by = cy - r * 0.65;
  canvas.drawCircle(Offset(bx, by), badgeR, Paint()..color = color);
  canvas.drawCircle(
    Offset(bx, by), badgeR,
    Paint()..color = Colors.white..style = PaintingStyle.stroke..strokeWidth = 1.5 * scale,
  );
  final countTp = TextPainter(
    text: TextSpan(
      text: '$count',
      style: TextStyle(color: Colors.white, fontSize: 11 * scale, fontWeight: FontWeight.w900),
    ),
    textDirection: TextDirection.ltr,
  )..layout();
  countTp.paint(canvas, Offset(bx - countTp.width / 2, by - countTp.height / 2));

  // ── Tail (pointer) ────────────────────────────────────────────────────────
  final path = Path()
    ..moveTo(cx - 6 * scale, diam - 2 * scale)
    ..lineTo(cx, canvasH)
    ..lineTo(cx + 6 * scale, diam - 2 * scale)
    ..close();
  canvas.drawPath(path, Paint()..color = color);

  final img  = await rec.endRecording().toImage(canvasW.toInt(), canvasH.toInt());
  final data = await img.toByteData(format: ui.ImageByteFormat.png);
  return BitmapDescriptor.fromBytes(
    data!.buffer.asUint8List(),
    size: const Size(56, 72), // logical pixels — fixed, zoom-independent
  );
}

// ── Screen ────────────────────────────────────────────────────────────────────
class HeatmapScreen extends ConsumerStatefulWidget {
  const HeatmapScreen({super.key});
  @override
  ConsumerState<HeatmapScreen> createState() => _HeatmapScreenState();
}

class _HeatmapScreenState extends ConsumerState<HeatmapScreen> {
  GoogleMapController? _ctrl;
  Timer? _timer;
  _Zone? _selected; // tapped vendor

  static const _center = LatLng(2.0469, 45.3182);

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 60), (_) {
      ref.invalidate(_zonesProvider);
      setState(() => _selected = null);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _ctrl?.dispose();
    super.dispose();
  }

  // ── Circles ───────────────────────────────────────────────────────────────
  Set<Circle> _circles(List<_Zone> zones) {
    final out = <Circle>{};
    for (final z in zones) {
      final c = _zoneColor(z.level);
      final pos = LatLng(z.lat, z.lng);
      // Glow
      out.add(Circle(
        circleId:    CircleId('g${z.vendorId}'),
        center:      pos,
        radius:      z.radiusM * 1.5,
        fillColor:   c.withOpacity(0.07),
        strokeWidth: 0,
        strokeColor: Colors.transparent,
      ));
      // Fill
      out.add(Circle(
        circleId:    CircleId('f${z.vendorId}'),
        center:      pos,
        radius:      z.radiusM.toDouble(),
        fillColor:   c.withOpacity(0.20),
        strokeColor: c.withOpacity(0.60),
        strokeWidth: 2,
      ));
    }
    return out;
  }

  // ── Markers ───────────────────────────────────────────────────────────────
  Future<Set<Marker>> _markers(List<_Zone> zones) async {
    final out = <Marker>{};
    for (final z in zones) {
      final icon = await _buildMarkerIcon(z.orderCount, z.level);
      out.add(Marker(
        markerId: MarkerId('m${z.vendorId}'),
        position: LatLng(z.lat, z.lng),
        icon:     icon,
        anchor:   const Offset(0.5, 1.0), // tail points to exact location
        zIndex:   2,
        onTap:    () => setState(() => _selected = z),
      ));
    }
    return out;
  }

  @override
  Widget build(BuildContext context) {
    final c     = context.dc;
    final async = ref.watch(_zonesProvider);

    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Busy Zones',
            style: TextStyle(fontWeight: FontWeight.w800, color: c.text, fontSize: 18)),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh_rounded, color: c.text),
            onPressed: () {
              setState(() => _selected = null);
              ref.invalidate(_zonesProvider);
            },
          ),
        ],
      ),
      body: Stack(children: [

        // ── Map ──────────────────────────────────────────────────────────
        async.when(
          loading: () => _baseMap(const {}, const {}),
          error:   (_, __) => _baseMap(const {}, const {}),
          data:    (zones) => FutureBuilder<Set<Marker>>(
            future: _markers(zones),
            builder: (_, snap) => _baseMap(_circles(zones), snap.data ?? {}),
          ),
        ),

        // ── Vendor info card (on tap) ─────────────────────────────────────
        if (_selected != null)
          Positioned(
            bottom: 100, left: 16, right: 16,
            child: _VendorCard(zone: _selected!, onClose: () => setState(() => _selected = null)),
          ),

        // ── Legend ───────────────────────────────────────────────────────
        Positioned(
          bottom: 24, left: 16, right: 16,
          child: async.when(
            loading: () => _legendCard([], 0),
            error:   (_, __) => _legendCard([], 0),
            data:    (zones) => _legendCard(zones, zones.isNotEmpty ? zones.first.bonusAmount : 0),
          ),
        ),

        // ── Loading indicator ─────────────────────────────────────────────
        if (async.isLoading)
          Positioned(
            top: 12, left: 0, right: 0,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.10), blurRadius: 8)],
                ),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  SizedBox(width: 14, height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFFF8A00))),
                  SizedBox(width: 8),
                  Text('Updating...', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                ]),
              ),
            ),
          ),
      ]),
    );
  }

  Widget _baseMap(Set<Circle> circles, Set<Marker> markers) => GoogleMap(
    onMapCreated:         (ctrl) => _ctrl = ctrl,
    initialCameraPosition: const CameraPosition(target: _center, zoom: 12.5),
    circles:              circles,
    markers:              markers,
    myLocationEnabled:    true,
    myLocationButtonEnabled: false,
    zoomControlsEnabled:  false,
    mapToolbarEnabled:    false,
    onTap:                (_) => setState(() => _selected = null),
  );

  Widget _legendCard(List<_Zone> zones, double bonus) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.10), blurRadius: 16, offset: const Offset(0, 4))],
    ),
    child: zones.isEmpty
        ? const Text('No busy zones right now',
            style: TextStyle(color: Colors.grey, fontSize: 13, fontWeight: FontWeight.w600))
        : Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (bonus > 0) ...[
              Text('💰 +\$${bonus.toStringAsFixed(2)} peak pay per order in busy zones',
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              const SizedBox(height: 8),
            ],
            Row(children: [
              if (zones.any((z) => z.level == 'very_busy')) ...[
                _dot(const Color(0xFFCC1010)), const SizedBox(width: 5),
                const Text('Very Busy', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(width: 14),
              ],
              if (zones.any((z) => z.level == 'busy')) ...[
                _dot(const Color(0xFFE05000)), const SizedBox(width: 5),
                const Text('Busy', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(width: 14),
              ],
              _dot(const Color(0xFFFF8A00)), const SizedBox(width: 5),
              const Text('Active', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            ]),
          ]),
  );

  Widget _dot(Color c) => Container(
    width: 12, height: 12,
    decoration: BoxDecoration(color: c, shape: BoxShape.circle),
  );
}

// ── Vendor detail card (shown on marker tap) ──────────────────────────────────
class _VendorCard extends StatelessWidget {
  final _Zone zone;
  final VoidCallback onClose;
  const _VendorCard({required this.zone, required this.onClose});

  @override
  Widget build(BuildContext context) {
    final color = _zoneColor(zone.level);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.15), blurRadius: 20, offset: const Offset(0, 6))],
      ),
      child: Row(children: [
        // Store icon
        Container(
          width: 52, height: 52,
          decoration: BoxDecoration(
            color: color.withOpacity(0.10),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: color.withOpacity(0.30), width: 1.5),
          ),
          child: const Center(child: Text('🏪', style: TextStyle(fontSize: 26))),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(zone.vendorName,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF07003B)),
                maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Row(children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(20)),
                child: Text(zone.label,
                    style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
              ),
              const SizedBox(width: 8),
              Text('${zone.orderCount} active order${zone.orderCount > 1 ? 's' : ''}',
                  style: const TextStyle(color: Colors.grey, fontSize: 12)),
            ]),
          ]),
        ),
        GestureDetector(
          onTap: onClose,
          child: const Padding(
            padding: EdgeInsets.only(left: 8),
            child: Icon(Icons.close, color: Colors.grey, size: 20),
          ),
        ),
      ]),
    );
  }
}
