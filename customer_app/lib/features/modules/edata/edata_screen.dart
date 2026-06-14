import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shimmer/shimmer.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'package:intl/intl.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────
const _kOrange = AppColors.primary;   // #FF8A00
const _kNavy   = AppColors.secondary; // #07003B
const _kBg     = Color(0xFFF5F7FA);
const _kCard   = Colors.white;
const _kMuted  = Color(0xFF8A8A9A);
const _kDivider= Color(0xFFF0F1F5);

// ─────────────────────────────────────────────────────────────────────────────
// Providers
// ─────────────────────────────────────────────────────────────────────────────
final _svc = ModuleApiService.create();
final _providersProvider  = FutureProvider((_) => _svc.getDataProviders());
final _historyProvider    = FutureProvider((_) => _svc.getDataHistory());
final _packagesProvider   = FutureProvider.family<dynamic,int>((_, id) => _svc.getDataPackages(id));
final _pkgBundlesProvider = FutureProvider.family<dynamic,int>((_, id) => _svc.getDataPackageBundles(id));

// ─────────────────────────────────────────────────────────────────────────────
// MAIN SCREEN
// ─────────────────────────────────────────────────────────────────────────────
class EDataScreen extends ConsumerStatefulWidget {
  const EDataScreen({super.key});
  @override
  ConsumerState<EDataScreen> createState() => _EDataScreenState();
}

class _EDataScreenState extends ConsumerState<EDataScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() { super.initState(); _tab = TabController(length: 2, vsync: this); }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _kBg,
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            pinned: true, expandedHeight: 0,
            backgroundColor: _kNavy,
            foregroundColor: Colors.white,
            title: const Row(children: [
              Icon(Icons.sim_card_rounded, size: 20, color: _kOrange),
              SizedBox(width: 8),
              Text('eData', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 20)),
            ]),
            bottom: PreferredSize(
              preferredSize: const Size.fromHeight(48),
              child: Container(
                color: _kNavy,
                child: TabBar(
                  controller: _tab,
                  indicatorColor: _kOrange, indicatorWeight: 3,
                  labelColor: _kOrange, unselectedLabelColor: Colors.white60,
                  labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                  tabs: const [Tab(text: 'Buy Data'), Tab(text: 'History')],
                ),
              ),
            ),
          ),
        ],
        body: TabBarView(controller: _tab, children: [_BuyDataTab(), _HistoryTab()]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// BUY DATA TAB — Hero + Provider Grid
// ─────────────────────────────────────────────────────────────────────────────
class _BuyDataTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_providersProvider);
    return async.when(
      loading: () => _buildShimmer(),
      error: (e, _) => _ErrorState(message: e.toString(), onRetry: () => ref.invalidate(_providersProvider)),
      data: (data) {
        final providers = List<Map>.from(data is Map ? (data['data'] ?? []) : data ?? []);
        if (providers.isEmpty) return const _EmptyState(
          icon: Icons.sim_card_outlined, title: 'No Providers', subtitle: 'Data providers will appear here');
        return CustomScrollView(slivers: [
          SliverToBoxAdapter(child: _HeroBanner()),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            sliver: SliverToBoxAdapter(
              child: Text('Choose Provider',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: _kNavy)),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 6, 16, 20),
            sliver: SliverGrid(
              delegate: SliverChildBuilderDelegate(
                (_, i) => _ProviderCard(provider: providers[i]),
                childCount: providers.length,
              ),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2, childAspectRatio: 1.35,
                crossAxisSpacing: 12, mainAxisSpacing: 12,
              ),
            ),
          ),
        ]);
      },
    );
  }

  Widget _buildShimmer() => CustomScrollView(slivers: [
    SliverToBoxAdapter(child: _HeroBanner()),
    SliverPadding(
      padding: const EdgeInsets.all(16),
      sliver: SliverGrid(
        delegate: SliverChildBuilderDelegate(
          (_, __) => Shimmer.fromColors(
            baseColor: Colors.grey.shade200, highlightColor: Colors.grey.shade50,
            child: Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20))),
          ),
          childCount: 4,
        ),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2, childAspectRatio: 1.35, crossAxisSpacing: 12, mainAxisSpacing: 12),
      ),
    ),
  ]);
}

// ── Hero Banner ──
class _HeroBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.all(16),
    padding: const EdgeInsets.all(22),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [_kNavy, Color(0xFF1B0F6E), Color(0xFF1565C0)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [BoxShadow(color: _kNavy.withValues(alpha: 0.35), blurRadius: 24, offset: const Offset(0, 8))],
    ),
    child: Row(children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Mobile Data & Bundles',
            style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        Text('Choose provider, pick a package,\nthen select your bundle',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.70), fontSize: 12, height: 1.5)),
        const SizedBox(height: 14),
        Row(children: [
          _HeroBadge(icon: Icons.flash_on_rounded, label: 'Instant'),
          const SizedBox(width: 8),
          _HeroBadge(icon: Icons.security_rounded, label: 'Secure'),
        ]),
      ])),
      const SizedBox(width: 16),
      Container(
        width: 72, height: 72,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(18),
        ),
        child: const Icon(Icons.signal_cellular_alt_rounded, color: Colors.white, size: 40),
      ),
    ]),
  );
}

class _HeroBadge extends StatelessWidget {
  final IconData icon;
  final String label;
  const _HeroBadge({required this.icon, required this.label});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: _kOrange.withValues(alpha: 0.2),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: _kOrange.withValues(alpha: 0.4)),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 12, color: _kOrange),
      const SizedBox(width: 4),
      Text(label, style: const TextStyle(color: _kOrange, fontSize: 11, fontWeight: FontWeight.w700)),
    ]),
  );
}

// ── Provider Card — Image + Name only ──
class _ProviderCard extends StatelessWidget {
  final Map provider;
  const _ProviderCard({required this.provider});

  Color get _color {
    final hex = provider['color']?.toString() ?? '#1565C0';
    final c = int.tryParse(hex.replaceFirst('#', '0xFF')) ?? 0xFF1565C0;
    return Color(c);
  }

  @override
  Widget build(BuildContext context) {
    final logoUrl = provider['logo_url']?.toString() ?? provider['logo']?.toString();
    final name = provider['name']?.toString() ?? '';
    return GestureDetector(
      onTap: () => showGeneralDialog(
        context: context,
        useRootNavigator: true,
        barrierDismissible: false,
        barrierColor: Colors.black54,
        transitionDuration: const Duration(milliseconds: 300),
        transitionBuilder: (_, anim, __, child) => SlideTransition(
          position: Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
              .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
          child: child,
        ),
        pageBuilder: (_, __, ___) => _EDataFlowDialog(provider: provider, providerColor: _color),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: _kCard,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 16, offset: const Offset(0, 4))],
        ),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          // Provider logo / color block
          Container(
            width: 60, height: 60,
            decoration: BoxDecoration(
              color: _color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: logoUrl != null && logoUrl.isNotEmpty
                  ? NetImage(
                      url: logoUrl, fit: BoxFit.contain,
                      errorWidget: Icon(Icons.sim_card_rounded, color: _color, size: 32))
                  : Icon(Icons.sim_card_rounded, color: _color, size: 32),
            ),
          ),
          const SizedBox(height: 10),
          Text(name,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: _kNavy),
              textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 6),
          Container(
            width: 28, height: 3,
            decoration: BoxDecoration(color: _color, borderRadius: BorderRadius.circular(2)),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// EDATA FLOW DIALOG — manages all steps
// step 0 = Packages grid
// step 1 = Bundles list
// step 2 = Order Summary
// step 3 = Success
// ─────────────────────────────────────────────────────────────────────────────
class _EDataFlowDialog extends ConsumerStatefulWidget {
  final Map provider;
  final Color providerColor;
  const _EDataFlowDialog({required this.provider, required this.providerColor});
  @override
  ConsumerState<_EDataFlowDialog> createState() => _EDataFlowDialogState();
}

class _EDataFlowDialogState extends ConsumerState<_EDataFlowDialog> {
  int _step = 0;
  Map? _selectedPackage;
  Map? _selectedBundle;

  String _payMethod = 'wallet';
  String? _waafiReference;
  final _phoneCtrl = TextEditingController();
  bool _loading = false;
  String? _error;
  String _orderNumber = '';

  @override
  void dispose() { _phoneCtrl.dispose(); super.dispose(); }

  void _selectPackage(Map pkg) => setState(() { _selectedPackage = pkg; _step = 1; });
  void _selectBundle(Map bnd)  => setState(() { _selectedBundle  = bnd; _step = 2; });

  Future<void> _placeOrder() async {
    final phone = _phoneCtrl.text.trim();
    if (phone.isEmpty) { setState(() => _error = 'Enter phone number'); return; }

    if (_payMethod == 'waafi_pay') {
      final price = _toD(_selectedBundle!['price']);
      final result = await showWaafiPaySheet(
        context,
        amount: price,
        type: 'order',
        description: 'eData: ${_selectedBundle!['name'] ?? ''}',
        prefillPhone: phone,
      );
      if (result?.success != true) return;
      _waafiReference = result!.reference;
    }

    if (_payMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() { _loading = true; _error = null; });
    try {
      final svc = ModuleApiService.create();
      final res = await svc.purchaseData({
        'bundle_id': _selectedBundle!['id'],
        'phone_number': phone,
        'payment_method': _payMethod,
        if (_waafiReference != null) 'payment_reference': _waafiReference,
      });
      final data = res is Map ? (res['data'] ?? {}) : {};
      if (_payMethod == 'wallet') ref.invalidate(walletProvider);
      if (mounted) setState(() {
        _orderNumber = data['order_number']?.toString() ?? 'DATA-??????';
        _step = 3;
        _loading = false;
      });
    } catch (e) {
      if (mounted) setState(() {
        _loading = false;
        _error = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Material(
      color: Colors.transparent,
      child: Container(
        width: size.width, height: size.height,
        color: _kBg,
        child: Column(children: [
          _buildAppBar(),
          Expanded(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 280),
              transitionBuilder: (child, anim) => SlideTransition(
                position: Tween<Offset>(begin: const Offset(0.3, 0), end: Offset.zero)
                    .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
                child: FadeTransition(opacity: anim, child: child),
              ),
              child: KeyedSubtree(
                key: ValueKey(_step),
                child: _step == 0 ? _PackagesStep(
                  providerId: int.parse(widget.provider['id'].toString()),
                  providerColor: widget.providerColor,
                  onSelectPackage: _selectPackage,
                ) : _step == 1 ? _BundlesStep(
                  packageId: int.parse(_selectedPackage!['id'].toString()),
                  packageName: _selectedPackage!['name']?.toString() ?? '',
                  providerColor: widget.providerColor,
                  onSelectBundle: _selectBundle,
                ) : _step == 2 ? _OrderSummaryStep(
                  bundle: _selectedBundle!,
                  package: _selectedPackage!,
                  provider: widget.provider,
                  providerColor: widget.providerColor,
                  phoneCtrl: _phoneCtrl,
                  payMethod: _payMethod,
                  onPayMethodChanged: (v) => setState(() => _payMethod = v),
                  loading: _loading,
                  error: _error,
                  onSubmit: _placeOrder,
                ) : _SuccessStep(
                  bundle: _selectedBundle!,
                  orderNumber: _orderNumber,
                  phone: _phoneCtrl.text,
                  onDone: () => Navigator.of(context).pop(),
                ),
              ),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _buildAppBar() {
    final titles = ['Choose Package', 'Choose Bundle', 'Order Summary', 'Booking Confirmed'];
    final logoUrl = widget.provider['logo_url']?.toString() ?? widget.provider['logo']?.toString();
    return Container(
      color: _kNavy,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
          child: Row(children: [
            // Back / Close
            IconButton(
              icon: Icon(_step == 3 ? Icons.close : Icons.arrow_back_ios_new_rounded,
                  color: Colors.white, size: 20),
              onPressed: () {
                if (_step == 3 || _step == 0) Navigator.of(context).pop();
                else setState(() => _step--);
              },
            ),
            // Provider logo
            if (logoUrl != null && logoUrl.isNotEmpty) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: NetImage(url: logoUrl, width: 28, height: 28, fit: BoxFit.contain,
                    errorWidget: const SizedBox()),
              ),
              const SizedBox(width: 8),
            ],
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(titles[_step],
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                Text(widget.provider['name']?.toString() ?? '',
                    style: TextStyle(color: Colors.white.withValues(alpha: 0.6), fontSize: 12)),
              ]),
            ),
            // Step indicators
            Row(children: List.generate(3, (i) {
              final done = i < _step;
              final active = i == _step && _step < 3;
              return Container(
                margin: const EdgeInsets.only(left: 4),
                width: active ? 20 : 8, height: 8,
                decoration: BoxDecoration(
                  color: done
                      ? Colors.green
                      : active
                          ? _kOrange
                          : Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(4),
                ),
              );
            })),
            const SizedBox(width: 12),
          ]),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 0 — Packages 2×2 Grid
// ─────────────────────────────────────────────────────────────────────────────
class _PackagesStep extends ConsumerWidget {
  final int providerId;
  final Color providerColor;
  final ValueChanged<Map> onSelectPackage;
  const _PackagesStep({required this.providerId, required this.providerColor, required this.onSelectPackage});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_packagesProvider(providerId));
    return async.when(
      loading: () => _buildShimmer(),
      error: (e, _) => _ErrorState(message: e.toString(), onRetry: () => ref.invalidate(_packagesProvider(providerId))),
      data: (data) {
        final packages = List<Map>.from(data is Map ? (data['data'] ?? []) : data ?? []);
        if (packages.isEmpty) return const _EmptyState(
          icon: Icons.sim_card_outlined, title: 'No Packages', subtitle: 'No packages available for this provider');
        return GridView.builder(
          padding: const EdgeInsets.all(16),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            childAspectRatio: 1.0,  // 2:2 ratio (square)
            crossAxisSpacing: 14,
            mainAxisSpacing: 14,
          ),
          itemCount: packages.length,
          itemBuilder: (_, i) => _PackageCard(
            package: packages[i],
            color: providerColor,
            onTap: () => onSelectPackage(packages[i]),
          ),
        );
      },
    );
  }

  Widget _buildShimmer() => GridView.builder(
    padding: const EdgeInsets.all(16),
    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
      crossAxisCount: 2, childAspectRatio: 1.0, crossAxisSpacing: 14, mainAxisSpacing: 14),
    itemCount: 4,
    itemBuilder: (_, __) => Shimmer.fromColors(
      baseColor: Colors.grey.shade200, highlightColor: Colors.grey.shade50,
      child: Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20))),
    ),
  );
}

// ── Package Card — 2:2 image-top card ──
class _PackageCard extends StatelessWidget {
  final Map package;
  final Color color;
  final VoidCallback onTap;
  const _PackageCard({required this.package, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final imageUrl = package['image_url']?.toString() ?? package['image']?.toString();
    final name = package['name']?.toString() ?? '';
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: _kCard,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 16, offset: const Offset(0, 4))],
        ),
        child: Column(children: [
          // Image — top 60%
          Expanded(
            flex: 6,
            child: ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
              child: imageUrl != null && imageUrl.isNotEmpty
                  ? NetImage(
                      url: imageUrl,
                      fit: BoxFit.cover,
                      width: double.infinity,
                      placeholder: Container(color: color.withValues(alpha: 0.1),
                          child: Icon(Icons.image_rounded, color: color.withValues(alpha: 0.3), size: 32)),
                      errorWidget: _PackageGradient(color: color, name: name),
                    )
                  : _PackageGradient(color: color, name: name),
            ),
          ),
          // Name — bottom 40%
          Expanded(
            flex: 4,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Text(name,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: _kNavy),
                    maxLines: 2, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 4),
                Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text('View bundles', style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
                  Icon(Icons.chevron_right_rounded, size: 14, color: color),
                ]),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class _PackageGradient extends StatelessWidget {
  final Color color;
  final String name;
  const _PackageGradient({required this.color, required this.name});
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity, height: double.infinity,
    decoration: BoxDecoration(
      gradient: LinearGradient(
        colors: [color, color.withValues(alpha: 0.6)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
    ),
    child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
      Icon(Icons.wifi_tethering_rounded, color: Colors.white.withValues(alpha: 0.8), size: 32),
    ])),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 1 — Bundles Horizontal Cards
// ─────────────────────────────────────────────────────────────────────────────
class _BundlesStep extends ConsumerWidget {
  final int packageId;
  final String packageName;
  final Color providerColor;
  final ValueChanged<Map> onSelectBundle;
  const _BundlesStep({required this.packageId, required this.packageName,
      required this.providerColor, required this.onSelectBundle});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_pkgBundlesProvider(packageId));
    return async.when(
      loading: () => ListView(children: List.generate(3, (_) => _BundleSkeleton())),
      error: (e, _) => _ErrorState(message: e.toString(), onRetry: () => ref.invalidate(_pkgBundlesProvider(packageId))),
      data: (data) {
        final bundles = List<Map>.from(data is Map ? (data['data'] ?? []) : data ?? []);
        if (bundles.isEmpty) return const _EmptyState(
          icon: Icons.data_usage_rounded, title: 'No Bundles',
          subtitle: 'No bundles available for this package');
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: bundles.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (_, i) => _BundleCard(
            bundle: bundles[i],
            color: providerColor,
            onTap: () => onSelectBundle(bundles[i]),
          ),
        );
      },
    );
  }
}

// ── Bundle Card — Horizontal (image right, details left) ──
class _BundleCard extends StatelessWidget {
  final Map bundle;
  final Color color;
  final VoidCallback onTap;
  const _BundleCard({required this.bundle, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final imageUrl = bundle['image_url']?.toString() ?? bundle['image']?.toString();
    final name  = bundle['name']?.toString() ?? '';
    final price = _toD(bundle['price']);
    final desc  = bundle['description']?.toString() ?? '';
    final badge = bundle['badge_label']?.toString() ?? '';
    final dataAmt = bundle['data_amount']?.toString() ?? '';
    final days    = bundle['validity_days']?.toString() ?? '';
    final voice   = bundle['voice_minutes']?.toString() ?? '';
    final sms     = bundle['sms_count']?.toString() ?? '';

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: _kCard,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 16, offset: const Offset(0, 4))],
          border: badge.isNotEmpty ? Border.all(color: color.withValues(alpha: 0.4), width: 1.5) : null,
        ),
        child: Row(children: [
          // LEFT — content
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                // Name + badge
                Row(children: [
                  Expanded(
                    child: Text(name,
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: _kNavy),
                        maxLines: 2, overflow: TextOverflow.ellipsis),
                  ),
                  if (badge.isNotEmpty) ...[
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(20)),
                      child: Text(badge, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 10)),
                    ),
                  ],
                ]),
                const SizedBox(height: 6),
                // Price
                Text('\$${price.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: _kOrange)),
                const SizedBox(height: 6),
                // Description
                if (desc.isNotEmpty) ...[
                  Text(desc,
                      style: const TextStyle(fontSize: 12, color: _kMuted, height: 1.4),
                      maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 8),
                ],
                // Icons row
                Wrap(spacing: 6, runSpacing: 4, children: [
                  if (dataAmt.isNotEmpty) _BundleChip(icon: Icons.wifi_rounded, label: dataAmt, color: color),
                  if (days.isNotEmpty)    _BundleChip(icon: Icons.calendar_today_rounded, label: '${days}d', color: color),
                  if (voice.isNotEmpty)   _BundleChip(icon: Icons.phone_rounded, label: '${voice}min', color: color),
                  if (sms.isNotEmpty)     _BundleChip(icon: Icons.sms_rounded, label: '${sms} SMS', color: color),
                ]),
              ]),
            ),
          ),
          // RIGHT — image
          ClipRRect(
            borderRadius: const BorderRadius.horizontal(right: Radius.circular(18)),
            child: SizedBox(
              width: 100, height: 140,
              child: imageUrl != null && imageUrl.isNotEmpty
                  ? NetImage(
                      url: imageUrl, fit: BoxFit.cover,
                      placeholder: Container(color: color.withValues(alpha: 0.15),
                          child: Icon(Icons.data_usage_rounded, color: color.withValues(alpha: 0.4), size: 32)),
                      errorWidget: _BundleImageGradient(color: color),
                    )
                  : _BundleImageGradient(color: color),
            ),
          ),
        ]),
      ),
    );
  }
}

class _BundleImageGradient extends StatelessWidget {
  final Color color;
  const _BundleImageGradient({required this.color});
  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: LinearGradient(
        colors: [color.withValues(alpha: 0.8), color],
        begin: Alignment.topCenter, end: Alignment.bottomCenter,
      ),
    ),
    child: Center(child: Icon(Icons.data_usage_rounded, color: Colors.white.withValues(alpha: 0.6), size: 36)),
  );
}

class _BundleChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _BundleChip({required this.icon, required this.label, required this.color});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 11, color: color),
      const SizedBox(width: 3),
      Text(label, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
    ]),
  );
}

class _BundleSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Shimmer.fromColors(
    baseColor: Colors.grey.shade200, highlightColor: Colors.grey.shade50,
    child: Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      height: 140,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 2 — Order Summary
// ─────────────────────────────────────────────────────────────────────────────
class _OrderSummaryStep extends StatelessWidget {
  final Map bundle, package, provider;
  final Color providerColor;
  final TextEditingController phoneCtrl;
  final String payMethod;
  final ValueChanged<String> onPayMethodChanged;
  final bool loading;
  final String? error;
  final VoidCallback onSubmit;

  const _OrderSummaryStep({
    required this.bundle, required this.package, required this.provider,
    required this.providerColor, required this.phoneCtrl, required this.payMethod,
    required this.onPayMethodChanged, required this.loading, this.error,
    required this.onSubmit,
  });

  @override
  Widget build(BuildContext context) {
    final price = _toD(bundle['price']);
    final dataAmt = bundle['data_amount']?.toString() ?? '';
    final days    = bundle['validity_days']?.toString() ?? '';
    final voice   = bundle['voice_minutes']?.toString() ?? '';
    final sms     = bundle['sms_count']?.toString() ?? '';
    final logoUrl = provider['logo_url']?.toString() ?? provider['logo']?.toString();

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // ── Order Summary Card ──
        Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [providerColor, providerColor.withValues(alpha: 0.7), const Color(0xFF1a0e6e)],
              begin: Alignment.topLeft, end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(20),
            boxShadow: [BoxShadow(color: providerColor.withValues(alpha: 0.3), blurRadius: 20, offset: const Offset(0, 8))],
          ),
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.all(20),
              child: Row(children: [
                // Provider logo
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    width: 52, height: 52,
                    color: Colors.white.withValues(alpha: 0.15),
                    child: logoUrl != null && logoUrl.isNotEmpty
                        ? NetImage(url: logoUrl, fit: BoxFit.contain,
                            errorWidget: const Icon(Icons.sim_card_rounded, color: Colors.white, size: 28))
                        : const Icon(Icons.sim_card_rounded, color: Colors.white, size: 28),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(bundle['name']?.toString() ?? '',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 18)),
                  Text(provider['name']?.toString() ?? '',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 13)),
                  Text(package['name']?.toString() ?? '',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 11)),
                ])),
                Text('\$${price.toStringAsFixed(2)}',
                    style: const TextStyle(color: _kOrange, fontWeight: FontWeight.w900, fontSize: 26)),
              ]),
            ),
            // Benefits row
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                borderRadius: const BorderRadius.vertical(bottom: Radius.circular(20)),
              ),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
                if (dataAmt.isNotEmpty) _SummaryBadge(icon: Icons.wifi_rounded, label: dataAmt),
                if (days.isNotEmpty)    _SummaryBadge(icon: Icons.calendar_today_rounded, label: '${days}d'),
                if (voice.isNotEmpty)   _SummaryBadge(icon: Icons.phone_rounded, label: '${voice}min'),
                if (sms.isNotEmpty)     _SummaryBadge(icon: Icons.sms_rounded, label: '$sms SMS'),
                if (dataAmt.isEmpty && days.isEmpty && voice.isEmpty && sms.isEmpty)
                  _SummaryBadge(icon: Icons.check_circle_rounded, label: 'Bundle'),
              ]),
            ),
          ]),
        ),

        const SizedBox(height: 20),

        // ── Phone Number ──
        Container(
          decoration: BoxDecoration(
            color: _kCard, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
          ),
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Data Destination', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: _kNavy)),
            const SizedBox(height: 4),
            Text('Phone number that will receive the data',
                style: const TextStyle(fontSize: 12, color: _kMuted)),
            const SizedBox(height: 12),
            TextField(
              controller: phoneCtrl,
              keyboardType: TextInputType.phone,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
              decoration: InputDecoration(
                hintText: '061XXXXXXX',
                prefixIcon: const Icon(Icons.phone_rounded, color: _kOrange),
                filled: true, fillColor: _kBg,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: providerColor, width: 2)),
              ),
            ),
          ]),
        ),

        const SizedBox(height: 14),

        // ── Payment Method ──
        Container(
          decoration: BoxDecoration(
            color: _kCard, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
          ),
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: _kNavy)),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: _PayTile(
                icon: Icons.account_balance_wallet_rounded,
                label: 'Wallet', subtitle: 'Pay from balance',
                selected: payMethod == 'wallet',
                color: providerColor,
                onTap: () => onPayMethodChanged('wallet'),
              )),
              const SizedBox(width: 10),
              const SizedBox(width: 10),
              Expanded(child: _PayTile(
                icon: Icons.phone_android_rounded,
                label: 'Waafi Pay', subtitle: 'EVC / eDahab',
                selected: payMethod == 'waafi_pay',
                color: const Color(0xFFFF8A00),
                onTap: () => onPayMethodChanged('waafi_pay'),
              )),
            ]),
          ]),
        ),

        const SizedBox(height: 14),

        // ── Price breakdown ──
        Container(
          decoration: BoxDecoration(
            color: _kCard, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
          ),
          padding: const EdgeInsets.all(16),
          child: Column(children: [
            _SummaryRow(label: 'Bundle', value: bundle['name']?.toString() ?? ''),
            _SummaryRow(label: 'Provider', value: provider['name']?.toString() ?? ''),
            _SummaryRow(label: 'Package', value: package['name']?.toString() ?? ''),
            Container(height: 1, color: _kDivider, margin: const EdgeInsets.symmetric(vertical: 8)),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('Total', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: _kNavy)),
              Text('\$${price.toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: _kOrange)),
            ]),
          ]),
        ),

        if (error != null) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10)),
            child: Row(children: [
              const Icon(Icons.error_outline_rounded, color: Colors.red, size: 18),
              const SizedBox(width: 8),
              Expanded(child: Text(error!, style: const TextStyle(color: Colors.red, fontSize: 13))),
            ]),
          ),
        ],

        const SizedBox(height: 20),

        // ── Submit button ──
        SizedBox(
          width: double.infinity,
          child: loading
              ? const Center(child: CircularProgressIndicator(color: _kOrange))
              : ElevatedButton(
                  onPressed: onSubmit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _kOrange,
                    minimumSize: const Size(double.infinity, 54),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    elevation: 0,
                  ),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.bolt_rounded, size: 22),
                    const SizedBox(width: 8),
                    Text('Confirm & Pay \$${price.toStringAsFixed(2)}',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                  ]),
                ),
        ),
        const SizedBox(height: 32),
      ]),
    );
  }
}

class _SummaryBadge extends StatelessWidget {
  final IconData icon;
  final String label;
  const _SummaryBadge({required this.icon, required this.label});
  @override
  Widget build(BuildContext context) => Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, color: Colors.white, size: 18),
    const SizedBox(height: 3),
    Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11)),
  ]);
}

class _SummaryRow extends StatelessWidget {
  final String label, value;
  const _SummaryRow({required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: _kMuted, fontSize: 13)),
      Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: _kNavy)),
    ]),
  );
}

class _PayTile extends StatelessWidget {
  final IconData icon;
  final String label, subtitle;
  final bool selected;
  final Color color;
  final VoidCallback onTap;
  const _PayTile({required this.icon, required this.label, required this.subtitle,
      required this.selected, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: selected ? color.withValues(alpha: 0.1) : _kBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: selected ? color : Colors.grey.shade300,
          width: selected ? 2 : 1.5,
        ),
      ),
      child: Row(children: [
        Icon(icon, color: selected ? color : _kMuted, size: 22),
        const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13,
              color: selected ? color : _kNavy)),
          Text(subtitle, style: const TextStyle(fontSize: 10, color: _kMuted)),
        ])),
        if (selected) Icon(Icons.check_circle_rounded, color: color, size: 18),
      ]),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 3 — Success
// ─────────────────────────────────────────────────────────────────────────────
class _SuccessStep extends StatelessWidget {
  final Map bundle;
  final String orderNumber, phone;
  final VoidCallback onDone;
  const _SuccessStep({required this.bundle, required this.orderNumber, required this.phone, required this.onDone});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 100, height: 100,
          decoration: const BoxDecoration(color: Color(0xFFE8F5E9), shape: BoxShape.circle),
          child: const Icon(Icons.check_circle_rounded, color: Colors.green, size: 60),
        ),
        const SizedBox(height: 24),
        const Text('Data Sent Successfully! 🎉',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: _kNavy),
            textAlign: TextAlign.center),
        const SizedBox(height: 10),
        Text('${bundle['name']} has been sent to $phone',
            style: const TextStyle(color: _kMuted, fontSize: 14), textAlign: TextAlign.center),
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: const Color(0xFFF0F4FF), borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            const Text('Order Reference', style: TextStyle(color: _kMuted, fontSize: 12)),
            const SizedBox(height: 4),
            Text(orderNumber,
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: _kNavy,
                    letterSpacing: 1)),
          ]),
        ),
        const SizedBox(height: 32),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: onDone,
            style: ElevatedButton.styleFrom(
              backgroundColor: _kOrange, minimumSize: const Size(double.infinity, 52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), elevation: 0,
            ),
            child: const Text('Done', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ),
        ),
      ]),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// HISTORY TAB
// ─────────────────────────────────────────────────────────────────────────────
class _HistoryTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_historyProvider);
    return async.when(
      loading: () => ListView(children: List.generate(4, (_) => _HistorySkeleton())),
      error: (_, __) => const _EmptyState(icon: Icons.history_rounded, title: 'No History',
          subtitle: 'Your purchase history will appear here'),
      data: (data) {
        final orders = List<Map>.from(data is Map ? (data['data'] ?? []) : []);
        if (orders.isEmpty) return const _EmptyState(
          icon: Icons.history_rounded, title: 'No Purchases Yet',
          subtitle: 'Buy a data bundle to see your history here');
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: orders.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (_, i) => _HistoryCard(order: orders[i]),
        );
      },
    );
  }
}

class _HistoryCard extends StatelessWidget {
  final Map order;
  const _HistoryCard({required this.order});

  @override
  Widget build(BuildContext context) {
    final note = order['note'] is Map ? order['note'] : {};
    final status = order['status']?.toString() ?? 'confirmed';
    final statusColor = status == 'confirmed' ? Colors.green
        : status == 'cancelled' ? Colors.red : _kOrange;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _kCard, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
      ),
      child: Row(children: [
        Container(
          width: 48, height: 48,
          decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
          child: const Icon(Icons.data_usage_rounded, color: _kOrange, size: 24),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(note['item_name']?.toString() ?? 'Data Purchase',
              style: const TextStyle(fontWeight: FontWeight.w700, color: _kNavy)),
          Text(note['phone_number']?.toString() ?? '', style: const TextStyle(color: _kMuted, fontSize: 12)),
          Text(_fmtDate(order['placed_at']?.toString()), style: const TextStyle(color: _kMuted, fontSize: 11)),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text('\$${_toD(order['total_amount']).toStringAsFixed(2)}',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: _kOrange)),
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
            child: Text(status.toUpperCase(), style: TextStyle(color: statusColor, fontWeight: FontWeight.w700, fontSize: 10)),
          ),
        ]),
      ]),
    );
  }

  String _fmtDate(String? dt) {
    if (dt == null) return '';
    try { return DateFormat('d MMM yyyy').format(DateTime.parse(dt)); } catch (_) { return dt; }
  }
}

class _HistorySkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Shimmer.fromColors(
    baseColor: Colors.grey.shade200, highlightColor: Colors.grey.shade50,
    child: Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      height: 80,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED WIDGETS
// ─────────────────────────────────────────────────────────────────────────────
class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  const _EmptyState({required this.icon, required this.title, required this.subtitle});
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(40),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.1), shape: BoxShape.circle),
          child: Icon(icon, size: 40, color: _kOrange)),
        const SizedBox(height: 16),
        Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: _kNavy)),
        const SizedBox(height: 8),
        Text(subtitle, style: const TextStyle(color: _kMuted, fontSize: 14), textAlign: TextAlign.center),
      ]),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorState({required this.message, required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.error_outline_rounded, size: 56, color: Colors.red),
        const SizedBox(height: 12),
        Text(message, style: const TextStyle(color: _kMuted), textAlign: TextAlign.center),
        const SizedBox(height: 16),
        ElevatedButton.icon(onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded), label: const Text('Retry')),
      ]),
    ),
  );
}
