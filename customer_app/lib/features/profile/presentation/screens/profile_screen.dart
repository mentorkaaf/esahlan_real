import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../features/auth/presentation/providers/auth_provider.dart';
import '../../../../features/auth/data/models/user_model.dart';
import '../../../../core/storage/local_storage.dart';

const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);

// ── Saved GPS location provider ──────────────────────────────────────────────
final _savedLocationProvider = StateProvider<LatLng?>((ref) => null);

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});
  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  bool _loadingLocation = false;

  @override
  void initState() {
    super.initState();
    _loadSavedLocation();
  }

  Future<void> _loadSavedLocation() async {
    final lat = await LocalStorage.getDouble('saved_lat');
    final lng = await LocalStorage.getDouble('saved_lng');
    if (lat != null && lng != null && mounted) {
      ref.read(_savedLocationProvider.notifier).state = LatLng(lat, lng);
    }
  }

  Future<void> _requestAndSaveLocation() async {
    setState(() => _loadingLocation = true);
    try {
      LocationPermission perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        perm = await Geolocator.requestPermission();
      }
      if (perm == LocationPermission.deniedForever) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Location permission permanently denied. Enable it in Settings.')),
          );
        }
        return;
      }
      if (perm == LocationPermission.denied) return;

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      final ll = LatLng(pos.latitude, pos.longitude);
      await LocalStorage.saveDouble('saved_lat', pos.latitude);
      await LocalStorage.saveDouble('saved_lng', pos.longitude);
      if (mounted) {
        ref.read(_savedLocationProvider.notifier).state = ll;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Location saved to My Address')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not get location: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _loadingLocation = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(authStateProvider);
    final user      = userAsync.valueOrNull;
    final savedLoc  = ref.watch(_savedLocationProvider);
    final bottomPad = MediaQuery.of(context).padding.bottom + 86;

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      body: CustomScrollView(
        slivers: [
          // ── Header ──────────────────────────────────────────────────────
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            backgroundColor: context.colors.navyText,
            foregroundColor: Colors.white,
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            actions: [
              IconButton(
                icon: const Icon(Icons.edit_outlined, color: Colors.white),
                onPressed: () => _showEditDialog(context, ref, user),
              ),
            ],
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [_kNavy, Color(0xFF1a0a5e), _kOrange],
                    stops: [0.0, 0.6, 1.4],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 16, 24, 20),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(children: [
                          Container(
                            width: 68, height: 68,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white.withValues(alpha: 0.2),
                              border: Border.all(color: _kOrange, width: 2.5),
                            ),
                            child: Center(
                              child: Text(
                                user?.initials ?? 'U',
                                style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900),
                              ),
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(user?.name ?? '—', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
                            const SizedBox(height: 3),
                            Text(user?.phone ?? '', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                            if (user?.email != null) ...[
                              const SizedBox(height: 2),
                              Text(user!.email!, style: const TextStyle(color: Colors.white60, fontSize: 12)),
                            ],
                            const SizedBox(height: 6),
                            Row(children: [
                              const Icon(Icons.stars_rounded, color: Colors.amber, size: 15),
                              const SizedBox(width: 4),
                              Text('${user?.loyaltyPoints ?? 0} points',
                                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
                            ]),
                          ])),
                        ]),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),

          SliverToBoxAdapter(
            child: Column(children: [
              const SizedBox(height: 12),

              // ── Referral card ──────────────────────────────────────────
              if (user?.referralCode != null)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [_kOrange, Color(0xFFFF6B00)],
                        begin: Alignment.topLeft, end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(18),
                      boxShadow: [BoxShadow(color: _kOrange.withValues(alpha: 0.35), blurRadius: 16, offset: const Offset(0, 6))],
                    ),
                    child: Row(children: [
                      const Icon(Icons.card_giftcard_rounded, color: Colors.white, size: 28),
                      const SizedBox(width: 12),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Your Referral Code', style: TextStyle(color: Colors.white70, fontSize: 12)),
                        const SizedBox(height: 2),
                        Text(user!.referralCode!, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900, letterSpacing: 2)),
                      ])),
                      GestureDetector(
                        onTap: () {
                          Clipboard.setData(ClipboardData(text: user.referralCode!));
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Referral code copied!'), duration: Duration(seconds: 2)),
                          );
                        },
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(10)),
                          child: const Icon(Icons.copy_rounded, color: Colors.white, size: 20),
                        ),
                      ),
                    ]),
                  ),
                ),

              const SizedBox(height: 16),

              // ── My Address ────────────────────────────────────────────
              _AddressCard(
                user: user,
                savedLoc: savedLoc,
                loadingLocation: _loadingLocation,
                onGetLocation: _requestAndSaveLocation,
              ),

              const SizedBox(height: 10),

              // ── Support section ────────────────────────────────────────
              _Section(title: 'Support', items: [
                _Item(
                  icon: Icons.privacy_tip_outlined,
                  label: 'Privacy Policy',
                  onTap: () {},
                ),
                _Item(
                  icon: Icons.description_outlined,
                  label: 'Terms of Service',
                  onTap: () {},
                ),
              ]),

              const SizedBox(height: 10),

              // ── Sign out ───────────────────────────────────────────────
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: BoxDecoration(
                    color: context.colors.cardBg,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.red.shade100),
                  ),
                  child: ListTile(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    leading: Container(
                      width: 38, height: 38,
                      decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10)),
                      child: const Icon(Icons.logout_rounded, color: Colors.red, size: 20),
                    ),
                    title: const Text('Sign Out', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.red)),
                    trailing: const Icon(Icons.chevron_right_rounded, color: Colors.red, size: 20),
                    onTap: () => _confirmLogout(context, ref),
                  ),
                ),
              ),

              Padding(
                padding: EdgeInsets.only(top: 20, bottom: bottomPad),
                child: const Text('eSahlan v1.0.0', style: TextStyle(color: AppColors.textLight, fontSize: 12)),
              ),
            ]),
          ),
        ],
      ),
    );
  }

  void _showEditDialog(BuildContext context, WidgetRef ref, UserModel? user) {
    final nameCtrl  = TextEditingController(text: user?.name ?? '');
    final emailCtrl = TextEditingController(text: user?.email ?? '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Edit Profile', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Name', prefixIcon: Icon(Icons.person_outline))),
          const SizedBox(height: 12),
          TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)), keyboardType: TextInputType.emailAddress),
        ]),
        actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        actions: [
          Row(children: [
            Expanded(child: OutlinedButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel'))),
            const SizedBox(width: 10),
            Expanded(child: ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: _kOrange, foregroundColor: Colors.white),
              onPressed: () async {
                Navigator.pop(ctx);
                await ref.read(updateProfileProvider)({'name': nameCtrl.text.trim(), 'email': emailCtrl.text.trim()});
              },
              child: const Text('Save'),
            )),
          ]),
        ],
      ),
    );
  }

  void _confirmLogout(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(children: [
          Icon(Icons.logout_rounded, color: Colors.red, size: 22),
          SizedBox(width: 8),
          Text('Sign Out?', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        ]),
        content: const Text('Are you sure you want to sign out?', style: TextStyle(fontSize: 14)),
        actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        actions: [
          Row(children: [
            Expanded(child: OutlinedButton(
              onPressed: () => Navigator.pop(dialogCtx),
              style: OutlinedButton.styleFrom(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), padding: const EdgeInsets.symmetric(vertical: 12)),
              child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
            const SizedBox(width: 10),
            Expanded(child: ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogCtx);
                WidgetsBinding.instance.addPostFrameCallback((_) async {
                  await ref.read(logoutProvider)();
                });
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
              child: const Text('Sign Out', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
          ]),
        ],
      ),
    );
  }
}

// ── Address card ──────────────────────────────────────────────────────────────
class _AddressCard extends StatelessWidget {
  final UserModel? user;
  final LatLng? savedLoc;
  final bool loadingLocation;
  final VoidCallback onGetLocation;
  const _AddressCard({required this.user, required this.savedLoc, required this.loadingLocation, required this.onGetLocation});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
            child: Text('MY ADDRESS', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textGrey, letterSpacing: 0.8)),
          ),

          // District row
          ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
            leading: Container(
              width: 38, height: 38,
              decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.location_city_outlined, color: _kOrange, size: 20),
            ),
            title: Text('District', style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
            subtitle: Text(
              user?.districtName ?? 'Not set',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: context.colors.navyText),
            ),
          ),

          const Divider(height: 1, indent: 56, color: Color(0xFFF0F0F5)),

          // GPS location row
          ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
            leading: Container(
              width: 38, height: 38,
              decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.my_location_rounded, color: _kOrange, size: 20),
            ),
            title: Text('My Location', style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
            subtitle: savedLoc != null
                ? Text(
                    '${savedLoc!.latitude.toStringAsFixed(5)}, ${savedLoc!.longitude.toStringAsFixed(5)}',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: context.colors.navyText),
                  )
                : Text('Tap to save your GPS location', style: TextStyle(fontSize: 13, color: AppColors.textLight)),
            trailing: loadingLocation
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: _kOrange))
                : IconButton(
                    icon: const Icon(Icons.gps_fixed_rounded, color: _kOrange),
                    onPressed: onGetLocation,
                  ),
          ),

          // Google Map mini-view (only when location saved)
          if (savedLoc != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: SizedBox(
                  height: 180,
                  child: GoogleMap(
                    initialCameraPosition: CameraPosition(target: savedLoc!, zoom: 15),
                    markers: {
                      Marker(
                        markerId: const MarkerId('home'),
                        position: savedLoc!,
                        infoWindow: const InfoWindow(title: 'My Location'),
                      ),
                    },
                    myLocationButtonEnabled: false,
                    zoomControlsEnabled: false,
                    scrollGesturesEnabled: false,
                    rotateGesturesEnabled: false,
                    tiltGesturesEnabled: false,
                    zoomGesturesEnabled: false,
                  ),
                ),
              ),
            ),

          const SizedBox(height: 4),
        ]),
      ),
    );
  }
}

// ── Section ───────────────────────────────────────────────────────────────────
class _Section extends StatelessWidget {
  final String title;
  final List<_Item> items;
  const _Section({required this.title, required this.items});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
            child: Text(title.toUpperCase(), style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textGrey, letterSpacing: 0.8)),
          ),
          ...List.generate(items.length, (i) => Column(children: [
            items[i],
            if (i < items.length - 1) const Divider(height: 1, indent: 56, color: Color(0xFFF0F0F5)),
          ])),
          const SizedBox(height: 4),
        ]),
      ),
    );
  }
}

// ── Item ──────────────────────────────────────────────────────────────────────
class _Item extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _Item({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
      leading: Container(
        width: 38, height: 38,
        decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: _kOrange, size: 20),
      ),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: context.colors.navyText)),
      trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.textLight, size: 20),
      onTap: onTap,
    );
  }
}
