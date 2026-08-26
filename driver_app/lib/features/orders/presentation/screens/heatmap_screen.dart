import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

final _heatmapProvider = FutureProvider<List<dynamic>>((ref) async {
  return ref.read(authRepoProvider).getHeatmap();
});

class HeatmapScreen extends ConsumerWidget {
  const HeatmapScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final async = ref.watch(_heatmapProvider);
    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Busy Zones', style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () => ref.invalidate(_heatmapProvider)),
        ],
      ),
      body: Column(children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          color: c.navyLight,
          child: Row(children: [
            Container(width: 12, height: 12, decoration: BoxDecoration(color: Colors.red, shape: BoxShape.circle)),
            const SizedBox(width: 6),
            Text('High demand (5+ orders)', style: TextStyle(color: c.textSec, fontSize: 12)),
            const SizedBox(width: 16),
            Container(width: 12, height: 12, decoration: BoxDecoration(color: DC.orange, shape: BoxShape.circle)),
            const SizedBox(width: 6),
            Text('Medium (2-4 orders)', style: TextStyle(color: c.textSec, fontSize: 12)),
          ]),
        ),
        Expanded(
          child: async.when(
            loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
            error: (e, _) => Center(child: Text('Error: $e', style: TextStyle(color: c.textMuted))),
            data: (points) {
              final markers = <Marker>{};
              for (int i = 0; i < points.length; i++) {
                final p = points[i] as Map<String, dynamic>;
                final lat = double.tryParse('${p['lat'] ?? 0}') ?? 0;
                final lng = double.tryParse('${p['lng'] ?? 0}') ?? 0;
                final weight = (p['weight'] as num?)?.toInt() ?? 1;
                if (lat == 0 || lng == 0) continue;
                final color = weight >= 5
                    ? BitmapDescriptor.hueRed
                    : weight >= 2
                        ? BitmapDescriptor.hueOrange
                        : BitmapDescriptor.hueYellow;
                markers.add(Marker(
                  markerId: MarkerId('zone_$i'),
                  position: LatLng(lat, lng),
                  icon: BitmapDescriptor.defaultMarkerWithHue(color),
                  infoWindow: InfoWindow(title: '$weight orders in last 6h'),
                ));
              }
              final center = markers.isNotEmpty
                  ? markers.first.position
                  : const LatLng(2.0469, 45.3182); // Mogadishu default
              return GoogleMap(
                onMapCreated: (_) {},
                initialCameraPosition: CameraPosition(target: center, zoom: 12),
                markers: markers,
                myLocationEnabled: true,
                myLocationButtonEnabled: true,
                zoomControlsEnabled: false,
                mapToolbarEnabled: false,
              );
            },
          ),
        ),
      ]),
    );
  }
}
