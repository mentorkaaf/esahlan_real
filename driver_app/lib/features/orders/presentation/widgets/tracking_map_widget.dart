import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:flutter_polyline_points/flutter_polyline_points.dart';
import 'package:http/http.dart' as http;

// ─────────────────────────────────────────────────────────────────────────────
// Full Live Tracking Map
//
// Shows:
//   • Driver live blue pulsing dot (GPS, updates every 5 s)
//   • Pickup  orange marker
//   • Delivery green marker
//   • Real road route polyline (Directions API)  orange solid
//   • ETA + distance chips overlaid on map
//   • Auto-zoom to fit all 3 points
// ─────────────────────────────────────────────────────────────────────────────

const _kMapsKey = 'AIzaSyC1pxwcaFZxDXwqDpxK_gDfPAdpFM8bTnc';

class TrackingMapWidget extends StatefulWidget {
  final double pickupLat;
  final double pickupLng;
  final double deliveryLat;
  final double deliveryLng;
  final String pickupLabel;
  final String deliveryLabel;
  final double height;

  const TrackingMapWidget({
    super.key,
    required this.pickupLat,
    required this.pickupLng,
    required this.deliveryLat,
    required this.deliveryLng,
    this.pickupLabel   = 'Pickup',
    this.deliveryLabel = 'Delivery',
    this.height        = 260,
  });

  @override
  State<TrackingMapWidget> createState() => _TrackingMapWidgetState();
}

class _TrackingMapWidgetState extends State<TrackingMapWidget> {
  GoogleMapController? _mapCtrl;
  StreamSubscription<Position>? _gpsSub;
  Timer? _etaTimer;

  // State
  LatLng? _driverPos;
  List<LatLng> _routePoints = [];
  String _eta     = '—';
  String _distKm  = '—';
  bool _routeLoaded = false;

  @override
  void initState() {
    super.initState();
    _startGps();
    _loadRoute();
  }

  @override
  void dispose() {
    _gpsSub?.cancel();
    _etaTimer?.cancel();
    _mapCtrl?.dispose();
    super.dispose();
  }

  // ── GPS — live driver position ─────────────────────────────────────────────
  void _startGps() {
    _gpsSub = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.bestForNavigation,
        distanceFilter: 5,           // update every 5 m moved
      ),
    ).listen((pos) {
      final newPos = LatLng(pos.latitude, pos.longitude);
      setState(() => _driverPos = newPos);
      _animateCamera();
    });
    // Also get instant first fix
    Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    ).then((pos) {
      if (!mounted) return;
      setState(() => _driverPos = LatLng(pos.latitude, pos.longitude));
      _fitAllMarkers();
    }).catchError((_) {});
  }

  // ── Directions API — real road polyline ────────────────────────────────────
  Future<void> _loadRoute() async {
    try {
      final pp     = PolylinePoints();
      final result = await pp.getRouteBetweenCoordinates(
        googleApiKey: _kMapsKey,
        request: PolylineRequest(
          origin:      PointLatLng(widget.pickupLat, widget.pickupLng),
          destination: PointLatLng(widget.deliveryLat, widget.deliveryLng),
          mode:        TravelMode.driving,
        ),
      );
      if (result.points.isNotEmpty) {
        if (mounted) {
          setState(() {
            _routePoints = result.points
                .map((p) => LatLng(p.latitude, p.longitude))
                .toList();
            _routeLoaded = true;
          });
        }
      }
    } catch (e) {
      debugPrint('[TrackingMap] Directions error: $e');
    }
    // Load ETA after route
    _loadEta();
  }

  // ── Distance Matrix API — ETA ──────────────────────────────────────────────
  Future<void> _loadEta() async {
    if (_driverPos == null) return;
    try {
      final origin = '${_driverPos!.latitude},${_driverPos!.longitude}';
      final dest   = '${widget.deliveryLat},${widget.deliveryLng}';
      final url    = Uri.parse(
        'https://maps.googleapis.com/maps/api/distancematrix/json'
        '?origins=$origin&destinations=$dest'
        '&mode=driving&key=$_kMapsKey',
      );
      final res  = await http.get(url).timeout(const Duration(seconds: 8));
      final body = jsonDecode(res.body) as Map<String, dynamic>;
      final rows = body['rows'] as List?;
      final elem = (rows?.first?['elements'] as List?)?.first as Map?;
      if (elem != null && elem['status'] == 'OK') {
        final dur  = elem['duration']?['text']  as String? ?? '—';
        final dist = elem['distance']?['text']  as String? ?? '—';
        if (mounted) setState(() { _eta = dur; _distKm = dist; });
      }
    } catch (e) {
      debugPrint('[TrackingMap] ETA error: $e');
    }
    // Refresh ETA every 2 min
    _etaTimer = Timer(const Duration(minutes: 2), _loadEta);
  }

  // ── Camera ─────────────────────────────────────────────────────────────────
  void _fitAllMarkers() {
    final ctrl = _mapCtrl;
    if (ctrl == null) return;
    final points = <LatLng>[
      LatLng(widget.pickupLat, widget.pickupLng),
      LatLng(widget.deliveryLat, widget.deliveryLng),
      if (_driverPos != null) _driverPos!,
    ];
    if (points.isEmpty) return;

    var minLat = points.first.latitude;
    var maxLat = points.first.latitude;
    var minLng = points.first.longitude;
    var maxLng = points.first.longitude;
    for (final p in points) {
      minLat = math.min(minLat, p.latitude);
      maxLat = math.max(maxLat, p.latitude);
      minLng = math.min(minLng, p.longitude);
      maxLng = math.max(maxLng, p.longitude);
    }
    ctrl.animateCamera(CameraUpdate.newLatLngBounds(
      LatLngBounds(
        southwest: LatLng(minLat, minLng),
        northeast: LatLng(maxLat, maxLng),
      ),
      70, // padding px
    ));
  }

  void _animateCamera() {
    // Smooth follow — only if map is not showing all markers yet
    if (_mapCtrl != null && _driverPos != null) {
      // Don't pan aggressively; re-fit is on demand
    }
  }

  // ── Markers ────────────────────────────────────────────────────────────────
  Set<Marker> _buildMarkers() {
    final markers = <Marker>{
      Marker(
        markerId: const MarkerId('pickup'),
        position: LatLng(widget.pickupLat, widget.pickupLng),
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
        infoWindow: InfoWindow(title: widget.pickupLabel),
      ),
      Marker(
        markerId: const MarkerId('delivery'),
        position: LatLng(widget.deliveryLat, widget.deliveryLng),
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
        infoWindow: InfoWindow(title: widget.deliveryLabel),
      ),
    };
    if (_driverPos != null) {
      markers.add(Marker(
        markerId: const MarkerId('driver'),
        position: _driverPos!,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueAzure),
        infoWindow: const InfoWindow(title: 'You'),
        zIndex: 2,
      ));
    }
    return markers;
  }

  // ── Polylines ──────────────────────────────────────────────────────────────
  Set<Polyline> _buildPolylines() {
    if (_routePoints.isEmpty) {
      // Fallback straight line while route loads
      return {
        Polyline(
          polylineId: const PolylineId('straight'),
          points: [
            LatLng(widget.pickupLat, widget.pickupLng),
            LatLng(widget.deliveryLat, widget.deliveryLng),
          ],
          color: Colors.orange.withValues(alpha: 0.5),
          width: 2,
          patterns: [PatternItem.dash(15), PatternItem.gap(10)],
        ),
      };
    }
    final lines = <Polyline>{
      Polyline(
        polylineId: const PolylineId('route'),
        points: _routePoints,
        color: const Color(0xFFFF8A00),
        width: 5,
        startCap: Cap.roundCap,
        endCap: Cap.roundCap,
        jointType: JointType.round,
      ),
    };
    // Driver-to-pickup segment (dashed blue)
    if (_driverPos != null) {
      lines.add(Polyline(
        polylineId: const PolylineId('driver_to_pickup'),
        points: [_driverPos!, LatLng(widget.pickupLat, widget.pickupLng)],
        color: const Color(0xFF2196F3),
        width: 3,
        patterns: [PatternItem.dash(12), PatternItem.gap(8)],
      ));
    }
    return lines;
  }

  @override
  Widget build(BuildContext context) {
    final center = LatLng(
      (widget.pickupLat + widget.deliveryLat) / 2,
      (widget.pickupLng + widget.deliveryLng) / 2,
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        height: widget.height,
        child: Stack(
          children: [
            // ── Map ────────────────────────────────────────────────────────
            GoogleMap(
              initialCameraPosition: CameraPosition(target: center, zoom: 12),
              onMapCreated: (ctrl) {
                _mapCtrl = ctrl;
                Future.delayed(const Duration(milliseconds: 400), _fitAllMarkers);
              },
              markers:   _buildMarkers(),
              polylines: _buildPolylines(),
              myLocationEnabled:    false,
              zoomControlsEnabled:  false,
              mapToolbarEnabled:    false,
              compassEnabled:       false,
              rotateGesturesEnabled: true,
              tiltGesturesEnabled:  false,
            ),

            // ── ETA chip — top right ───────────────────────────────────────
            Positioned(
              top: 10, right: 10,
              child: _InfoChip(
                icon: Icons.schedule_rounded,
                color: const Color(0xFF1E293B),
                label: _eta,
                sublabel: _distKm,
              ),
            ),

            // ── Fit button — bottom right ──────────────────────────────────
            Positioned(
              bottom: 10, right: 10,
              child: GestureDetector(
                onTap: _fitAllMarkers,
                child: Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 6)],
                  ),
                  child: const Icon(Icons.my_location_rounded, size: 20, color: Color(0xFF1E293B)),
                ),
              ),
            ),

            // ── Loading indicator while route is fetching ─────────────────
            if (!_routeLoaded)
              Positioned(
                bottom: 10, left: 10,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Row(mainAxisSize: MainAxisSize.min, children: [
                    SizedBox(
                      width: 12, height: 12,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    ),
                    SizedBox(width: 6),
                    Text('Loading route…', style: TextStyle(color: Colors.white, fontSize: 11)),
                  ]),
                ),
              ),

            // ── Legend ────────────────────────────────────────────────────
            Positioned(
              top: 10, left: 10,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _LegendDot(color: const Color(0xFFFF8A00), label: widget.pickupLabel),
                const SizedBox(height: 4),
                _LegendDot(color: const Color(0xFF22C55E), label: widget.deliveryLabel),
                if (_driverPos != null) ...[
                  const SizedBox(height: 4),
                  _LegendDot(color: const Color(0xFF2196F3), label: 'You'),
                ],
              ]),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Small UI components
// ─────────────────────────────────────────────────────────────────────────────

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label;
  final String sublabel;
  const _InfoChip({required this.icon, required this.color, required this.label, required this.sublabel});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: Colors.white, size: 14),
        const SizedBox(width: 5),
        Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700)),
          if (sublabel != '—')
            Text(sublabel, style: const TextStyle(color: Colors.white70, fontSize: 10)),
        ]),
      ]),
    );
  }
}

class _LegendDot extends StatelessWidget {
  final Color color;
  final String label;
  const _LegendDot({required this.color, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.black54,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 8, height: 8, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 5),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600)),
      ]),
    );
  }
}
