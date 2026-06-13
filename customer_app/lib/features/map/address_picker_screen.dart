import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../core/services/maps_service.dart';
import '../../core/theme/app_theme.dart';

/// Returns a [DeliveryAddress] when user confirms location.
class DeliveryAddress {
  final LatLng latLng;
  final String address;
  const DeliveryAddress({required this.latLng, required this.address});
}

class AddressPickerScreen extends StatefulWidget {
  final LatLng? initialLocation;
  const AddressPickerScreen({super.key, this.initialLocation});

  @override
  State<AddressPickerScreen> createState() => _AddressPickerScreenState();
}

class _AddressPickerScreenState extends State<AddressPickerScreen> {
  GoogleMapController? _mapController;
  final MapsService _mapsService = MapsService();

  LatLng _pickedLocation = MapsService.defaultLocation;
  String  _pickedAddress  = 'Move map to select location…';
  bool    _isLoading      = true;
  bool    _isGeoLoading   = false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    if (widget.initialLocation != null) {
      _pickedLocation = widget.initialLocation!;
    } else {
      final pos = await _mapsService.getCurrentPosition();
      if (pos != null && mounted) {
        _pickedLocation = _mapsService.positionToLatLng(pos);
      }
    }
    if (mounted) {
      setState(() => _isLoading = false);
      _reverseGeocode(_pickedLocation);
    }
  }

  Future<void> _reverseGeocode(LatLng latLng) async {
    setState(() => _isGeoLoading = true);
    final addr = await _mapsService.getAddressFromLatLng(latLng);
    if (mounted) setState(() { _pickedAddress = addr; _isGeoLoading = false; });
  }

  void _onCameraIdle() {
    _reverseGeocode(_pickedLocation);
  }

  void _onCameraMove(CameraPosition position) {
    _pickedLocation = position.target;
  }

  void _confirmLocation() {
    Navigator.of(context).pop(
      DeliveryAddress(latLng: _pickedLocation, address: _pickedAddress),
    );
  }

  Future<void> _goToCurrentLocation() async {
    final pos = await _mapsService.getCurrentPosition();
    if (pos == null) return;
    final latLng = _mapsService.positionToLatLng(pos);
    _mapController?.animateCamera(CameraUpdate.newLatLngZoom(latLng, 16));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Pick Delivery Location',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : Stack(
              children: [
                // ── Map (mobile only) ─────────────────────────────────────
                if (kIsWeb)
                  Container(
                    color: const Color(0xFFEEF2FF),
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.map_outlined, size: 56, color: AppColors.primary.withOpacity(0.4)),
                          const SizedBox(height: 12),
                          const Text('Map picker available on mobile app',
                              style: TextStyle(color: AppColors.textGrey, fontSize: 14)),
                          const SizedBox(height: 8),
                          const Text('Enter your address below',
                              style: TextStyle(color: AppColors.textLight, fontSize: 12)),
                        ],
                      ),
                    ),
                  )
                else
                  GoogleMap(
                    initialCameraPosition: CameraPosition(
                      target: _pickedLocation,
                      zoom: 15,
                    ),
                    onMapCreated: (c) => _mapController = c,
                    onCameraMove: _onCameraMove,
                    onCameraIdle: _onCameraIdle,
                    myLocationEnabled: true,
                    myLocationButtonEnabled: false,
                    zoomControlsEnabled: false,
                    mapToolbarEnabled: false,
                    compassEnabled: false,
                  ),

                // ── Center pin ────────────────────────────────────────────
                const Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.location_pin, size: 48, color: AppColors.primary),
                      SizedBox(height: 24), // offset for pin base
                    ],
                  ),
                ),

                // ── My location button ────────────────────────────────────
                Positioned(
                  right: 16, bottom: 200,
                  child: FloatingActionButton.small(
                    heroTag: 'my_location',
                    backgroundColor: Colors.white,
                    foregroundColor: AppColors.primary,
                    elevation: 4,
                    onPressed: _goToCurrentLocation,
                    child: const Icon(Icons.my_location_rounded),
                  ),
                ),

                // ── Bottom address panel ──────────────────────────────────
                Positioned(
                  left: 0, right: 0, bottom: 0,
                  child: Container(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                      boxShadow: [
                        BoxShadow(color: Color(0x1A000000), blurRadius: 20, offset: Offset(0, -4)),
                      ],
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Delivery location',
                            style: TextStyle(fontSize: 12, color: AppColors.textGrey, fontWeight: FontWeight.w500)),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            const Icon(Icons.location_on_rounded, color: AppColors.primary, size: 20),
                            const SizedBox(width: 8),
                            Expanded(
                              child: _isGeoLoading
                                  ? const LinearProgressIndicator(color: AppColors.primary, minHeight: 2)
                                  : Text(
                                      _pickedAddress,
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w600,
                                        color: AppColors.textDark,
                                      ),
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _isGeoLoading ? null : _confirmLocation,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                              elevation: 0,
                            ),
                            child: const Text('Confirm Location',
                                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  @override
  void dispose() {
    _mapController?.dispose();
    super.dispose();
  }
}
