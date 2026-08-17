// ignore_for_file: use_build_context_synchronously
import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../features/home/presentation/providers/home_provider.dart';
import '../api/module_api_service.dart';
import '../services/edata_local_cache.dart';
import 'offline_edata_overlay.dart';

// ─────────────────────────────────────────────────────────────────────────────
// ConnectivityWrapper — wraps the whole app, shows offline screen when needed
// ─────────────────────────────────────────────────────────────────────────────

class ConnectivityWrapper extends ConsumerStatefulWidget {
  final Widget child;
  const ConnectivityWrapper({super.key, required this.child});

  @override
  ConsumerState<ConnectivityWrapper> createState() => _ConnectivityWrapperState();
}

class _ConnectivityWrapperState extends ConsumerState<ConnectivityWrapper> {
  bool _isOnline    = true;
  bool _showEdataFlow = false; // toggled by eData Ka Shuub button
  late StreamSubscription<List<ConnectivityResult>> _sub;

  @override
  void initState() {
    super.initState();
    Connectivity().checkConnectivity().then((results) {
      _updateStatus(results);
      // Always refresh cache on startup if online (don't wait for status change)
      final online = results.any((r) => r != ConnectivityResult.none);
      if (online) _refreshEdataCache();
    });
    _sub = Connectivity().onConnectivityChanged.listen(_updateStatus);
  }

  void _updateStatus(List<ConnectivityResult> results) {
    final online = results.any((r) => r != ConnectivityResult.none);
    if (online == _isOnline) return;
    setState(() {
      _isOnline = online;
      if (online) _showEdataFlow = false; // close eData flow when internet returns
    });
    if (online) {
      _refreshProviders();
      _refreshEdataCache();
      _syncPendingOrders();
    }
  }

  void _refreshProviders() {
    ref.invalidate(modulesProvider);
    ref.invalidate(homeDataProvider);
    ref.invalidate(homeBannersProvider);
  }

  /// Syncs any orders the user made while offline back to the server.
  /// Runs silently — never blocks UI. Removes each order after success.
  Future<void> _syncPendingOrders() async {
    try {
      final orders = await EdataLocalCache.loadPendingOrders();
      if (orders.isEmpty) return;
      final svc = ModuleApiService.create();
      for (int i = orders.length - 1; i >= 0; i--) {
        try {
          final o = Map<String, dynamic>.from(orders[i]);
          o.remove('_queued_at');     // internal field, not needed by backend
          o.remove('bundle_name');    // display-only field
          o.remove('provider_id');    // not validated by backend
          o.remove('provider_name'); // display-only field
          o.remove('payment_phone'); // not a backend field
          o.remove('price');          // not a backend field
          // Ensure phone_number is set (older queued orders may use data_phone)
          if (!o.containsKey('phone_number') || (o['phone_number']?.toString() ?? '').isEmpty) {
            o['phone_number'] = o['data_phone'] ?? '';
          }
          o.remove('data_phone');
          await svc.purchaseData(o);
          await EdataLocalCache.removePendingOrderAt(i);
        } catch (_) {
          // leave it — will retry next time online
        }
      }
    } catch (_) {}
  }

  /// Fetches providers + bundles and stores them in EdataLocalCache.
  /// Runs silently in background — never blocks the UI.
  Future<void> _refreshEdataCache() async {
    try {
      final svc       = ModuleApiService.create();
      final provRes   = await svc.getDataProviders();
      final rawProv   = provRes is Map ? (provRes['data'] ?? []) : provRes;
      final providers = rawProv is List ? rawProv : <dynamic>[];
      if (providers.isEmpty) return;
      await EdataLocalCache.saveProviders(providers);

      // fetch bundles for each provider concurrently (max 5 at a time)
      const batchSize = 5;
      for (int i = 0; i < providers.length; i += batchSize) {
        final batch = providers.skip(i).take(batchSize);
        await Future.wait(batch.map((p) async {
          final id = int.tryParse(p['id']?.toString() ?? '0') ?? 0;
          if (id == 0) return;
          try {
            final res     = await svc.getDataBundles(id);
            final rawB    = res is Map ? (res['data'] ?? []) : res;
            final bundles = rawB is List ? rawB : <dynamic>[];
            await EdataLocalCache.saveBundles(id, bundles);
          } catch (_) {}
          try {
            final res      = await svc.getDataPackages(id);
            final rawP     = res is Map ? (res['data'] ?? []) : res;
            final packages = rawP is List ? rawP : <dynamic>[];
            await EdataLocalCache.savePackages(id, packages);
          } catch (_) {}
        }));
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _sub.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Stack(
        children: [
          widget.child,
          // Offline screen — only when offline AND not in eData flow
          if (!_isOnline && !_showEdataFlow)
            _NoInternetScreen(
              onBuyData: () => setState(() => _showEdataFlow = true),
            ),
          // eData purchase flow — shown as a proper Navigator route so keyboard
          // insets and focus work correctly; triggered by _showEdataFlow flag
          // (actual navigation happens in didUpdateWidget via post-frame callback)
          if (!_isOnline && _showEdataFlow)
            OfflineEdataOverlay(
              onClose: () => setState(() => _showEdataFlow = false),
            ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// New offline screen — same animation, new bottom sheet with eData CTA
// ─────────────────────────────────────────────────────────────────────────────

class _NoInternetScreen extends StatefulWidget {
  final VoidCallback onBuyData;
  const _NoInternetScreen({required this.onBuyData});
  @override
  State<_NoInternetScreen> createState() => _NoInternetScreenState();
}

class _NoInternetScreenState extends State<_NoInternetScreen>
    with TickerProviderStateMixin {
  late AnimationController _fadeCtrl;
  late AnimationController _floatCtrl;
  late Animation<double> _fadeAnim;
  late Animation<double> _floatAnim;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    _fadeCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _floatCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 2400))
      ..repeat(reverse: true);
    _fadeAnim  = CurvedAnimation(parent: _fadeCtrl, curve: Curves.easeOut);
    _floatAnim = Tween<double>(begin: -8, end: 8)
        .animate(CurvedAnimation(parent: _floatCtrl, curve: Curves.easeInOut));
    _fadeCtrl.forward();
  }

  @override
  void dispose() {
    _fadeCtrl.dispose();
    _floatCtrl.dispose();
    super.dispose();
  }

  Future<void> _retry() async {
    setState(() => _checking = true);
    await Future.delayed(const Duration(milliseconds: 900));
    await Connectivity().checkConnectivity();
    if (mounted) setState(() => _checking = false);
  }

  void _openEdataOffline() {
    widget.onBuyData(); // tells ConnectivityWrapper to show the Stack overlay
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Scaffold(
      body: FadeTransition(
        opacity: _fadeAnim,
        child: Stack(children: [
          // ── Dark navy gradient background ──────────────────────────────────
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFF07003B), Color(0xFF0D1B5E)],
                begin: Alignment.topCenter, end: Alignment.bottomCenter,
              ),
            ),
          ),

          // ── Floating signal/wifi illustration ─────────────────────────────
          Positioned(
            top: 0, left: 0, right: 0,
            height: size.height * 0.52,
            child: AnimatedBuilder(
              animation: _floatAnim,
              builder: (_, child) => Transform.translate(
                offset: Offset(0, _floatAnim.value),
                child: child,
              ),
              child: CustomPaint(
                painter: _StarfieldPainter(),
                child: Center(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    // Signal-off illustration
                    Stack(alignment: Alignment.center, children: [
                      Container(
                        width: 130, height: 130,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withValues(alpha: 0.06),
                        ),
                      ),
                      Container(
                        width: 90, height: 90,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withValues(alpha: 0.1),
                        ),
                      ),
                      const Icon(Icons.wifi_off_rounded, size: 50, color: Colors.white70),
                    ]),
                    const SizedBox(height: 20),
                    const Text(
                      'Internet La\'aan',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'WiFi iyo Data labadaba ma shaqeynayaan',
                      style: TextStyle(color: Colors.white54, fontSize: 13),
                    ),
                  ]),
                ),
              ),
            ),
          ),

          // ── Bottom white sheet ─────────────────────────────────────────────
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(36)),
              ),
              padding: EdgeInsets.fromLTRB(
                24, 28, 24, MediaQuery.of(context).padding.bottom + 28,
              ),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                // ── eData promo card ─────────────────────────────────────────
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF07003B), Color(0xFF1565C0)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF07003B).withValues(alpha: 0.3),
                        blurRadius: 20, offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFF8A00).withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(Icons.sim_card_rounded, color: Color(0xFFFF8A00), size: 22),
                      ),
                      const SizedBox(width: 12),
                      const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('eSahlan eData', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
                        Text('Internet si degdeg ah ugu shuub', style: TextStyle(color: Colors.white60, fontSize: 12)),
                      ]),
                    ]),
                    const SizedBox(height: 16),
                    const Text(
                      'Datadaadu waa dhammaatay?\neSahlan kaa caawin kartaa — provider dooro, bundle ku shuub, lacag ku bixin.',
                      style: TextStyle(color: Colors.white70, fontSize: 13, height: 1.5),
                    ),
                    const SizedBox(height: 16),
                    // Tags
                    Wrap(spacing: 6, children: [
                      _Tag('📡 Hormuud'), _Tag('📡 Somtel'), _Tag('📡 Somnet'), _Tag('📡 Amtel'),
                    ]),
                    const SizedBox(height: 18),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _openEdataOffline,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFFF8A00),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          elevation: 0,
                        ),
                        child: const Text(
                          '📶 eData Ka Shuub',
                          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                        ),
                      ),
                    ),
                  ]),
                ),

                const SizedBox(height: 16),

                // ── Retry button ─────────────────────────────────────────────
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: _checking ? null : _retry,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF07003B),
                      side: const BorderSide(color: Color(0xFFE5E7EB), width: 1.5),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
                    ),
                    child: _checking
                        ? const SizedBox(
                            width: 20, height: 20,
                            child: CircularProgressIndicator(color: Color(0xFF07003B), strokeWidth: 2.5),
                          )
                        : const Text('Dib u Isku Day',
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                  ),
                ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  final String text;
  const _Tag(this.text);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.white24),
    ),
    child: Text(text, style: const TextStyle(color: Colors.white70, fontSize: 11, fontWeight: FontWeight.w600)),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Background painter — soft star-field for the navy background
// ─────────────────────────────────────────────────────────────────────────────
class _StarfieldPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = Colors.white.withValues(alpha: 0.25);
    final dots = [
      [0.1, 0.15, 3.0], [0.85, 0.12, 2.0], [0.2, 0.55, 2.5],
      [0.75, 0.45, 3.5], [0.4, 0.08, 2.0], [0.6, 0.6, 2.5],
      [0.05, 0.7, 2.0], [0.9, 0.7, 3.0], [0.5, 0.35, 1.5],
      [0.3, 0.8, 2.0], [0.7, 0.85, 1.5],
    ];
    for (final d in dots) {
      canvas.drawCircle(Offset(size.width * d[0], size.height * d[1]), d[2], paint);
    }
  }
  @override
  bool shouldRepaint(covariant CustomPainter _) => false;
}
