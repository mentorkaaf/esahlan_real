import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shimmer/shimmer.dart';
import 'package:intl/intl.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/error_handler.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../ads/services/ad_service.dart';
import '../../../../core/theme/theme_x.dart';

// ═══════════════════════════════════════════════════════════════════════════
// DESIGN CONSTANTS
// ═══════════════════════════════════════════════════════════════════════════

const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);
const _kBg     = Color(0xFFF5F6FA);
const _kMuted  = Color(0xFF8A8A9A);

// ═══════════════════════════════════════════════════════════════════════════
// SERVICE
// ═══════════════════════════════════════════════════════════════════════════

final _svc = ModuleApiService.create();

// ═══════════════════════════════════════════════════════════════════════════
// PROVIDERS
// ═══════════════════════════════════════════════════════════════════════════

final _moveTypesProvider      = FutureProvider((_) => _svc.getMovingMoveTypes());
final _districtsProvider      = FutureProvider((_) => _svc.getMovingDistricts());
final _extraServicesProvider  = FutureProvider((_) => _svc.getMovingExtraServices());
final _myOrdersProvider       = FutureProvider((_) => _svc.getMovingMyOrders());

final _packagesProvider = FutureProvider
    .family<dynamic, String>((_, type) => _svc.getMovingPackages(type));

// ═══════════════════════════════════════════════════════════════════════════
// ROOT SCREEN — single page with tab toggle for My Orders
// ═══════════════════════════════════════════════════════════════════════════

class EMovingScreen extends ConsumerStatefulWidget {
  const EMovingScreen({super.key});

  @override
  ConsumerState<EMovingScreen> createState() => _EMovingScreenState();
}

class _EMovingScreenState extends ConsumerState<EMovingScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() {
    super.initState();
    AdService.instance.triggerModulePopups(context, 'emoving');
    _tab = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
            body: Column(
        children: [
          // ── GRADIENT HEADER ────────────────────────────────────────────
          _buildHeader(context),
          // ── TAB BAR ───────────────────────────────────────────────────
          Container(
            color: context.colors.navyText,
            child: TabBar(
              controller: _tab,
              indicatorColor: _kOrange,
              indicatorWeight: 3,
              labelColor: Colors.white,
              unselectedLabelColor: Colors.white60,
              labelStyle: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 14,
              ),
              tabs: const [
                Tab(text: 'Book Move'),
                Tab(text: 'My Orders'),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tab,
              children: const [
                _BookTab(),
                _MyOrdersTab(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    final top = MediaQuery.of(context).padding.top;
    return Container(
      padding: EdgeInsets.fromLTRB(20, top + 16, 20, 20),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [_kNavy, Color(0xFF1a0a5e), _kOrange],
          stops: [0.0, 0.55, 1.3],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -40,
            top: -30,
            child: Container(
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.04),
              ),
            ),
          ),
          Positioned(
            left: -20,
            bottom: -40,
            child: Container(
              width: 140,
              height: 140,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _kOrange.withValues(alpha: 0.12),
              ),
            ),
          ),
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: _kOrange.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: _kOrange.withValues(alpha: 0.4)),
                ),
                child: const Icon(Icons.local_shipping_rounded,
                    color: _kOrange, size: 34),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'eMoving',
                      style: TextStyle(
                        color: context.colors.cardBg,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.5,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Professional Moving Services',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      children: const [
                        _HeroBadge('House'),
                        _HeroBadge('Office'),
                        _HeroBadge('Commercial'),
                        _HeroBadge('Single Item'),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroBadge extends StatelessWidget {
  final String label;
  const _HeroBadge(this.label);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: _kOrange.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _kOrange.withValues(alpha: 0.4)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// BOOK TAB — Single scrollable page with all sections
// ═══════════════════════════════════════════════════════════════════════════

class _BookTab extends ConsumerStatefulWidget {
  const _BookTab();

  @override
  ConsumerState<_BookTab> createState() => _BookTabState();
}

class _BookTabState extends ConsumerState<_BookTab> {
  // ── Selections ───────────────────────────────────────────────────────────
  Map<String, dynamic>? _selectedType;
  Map<String, dynamic>? _selectedPackage;
  int _rooms = 0;
  Map<String, dynamic>? _fromDistrict;
  Map<String, dynamic>? _toDistrict;
  final Set<int> _selectedExtras = {};

  // ── Calculated price ─────────────────────────────────────────────────────
  Map<String, dynamic>? _priceBreakdown;
  bool _calculating = false;

  String get _typeSlug =>
      (_selectedType?['id'] ?? _selectedType?['slug'] ?? _selectedType?['type'] ?? '')
          .toString()
          .toLowerCase();

  bool get _isHouseOrSingle =>
      _typeSlug.contains('house') || _typeSlug.contains('single');

  bool get _canCalculate =>
      _selectedType != null &&
      _fromDistrict != null &&
      _toDistrict != null &&
      (_isHouseOrSingle ? _rooms > 0 : _selectedPackage != null);

  // ── Type display info ─────────────────────────────────────────────────────
  static const _typeInfo = {
    'house':       {'icon': Icons.home_rounded,       'color': Color(0xFF2ECC71), 'desc': 'Home furniture & belongings'},
    'office':      {'icon': Icons.business_rounded,   'color': Color(0xFF3498DB), 'desc': 'Professional office relocation'},
    'commercial':  {'icon': Icons.storefront_rounded, 'color': Color(0xFF9B59B6), 'desc': 'Large commercial goods'},
    'single_item': {'icon': Icons.inventory_2_rounded,'color': Color(0xFFE67E22), 'desc': 'One item or a few pieces'},
  };

  IconData _typeIcon(String slug) {
    final key = _typeInfo.keys.firstWhere((k) => slug.contains(k.split('_')[0]), orElse: () => 'house');
    return (_typeInfo[key]!['icon'] as IconData);
  }

  Color _typeColor(String slug) {
    final key = _typeInfo.keys.firstWhere((k) => slug.contains(k.split('_')[0]), orElse: () => 'house');
    return (_typeInfo[key]!['color'] as Color);
  }

  Future<void> _calculate() async {
    if (!_canCalculate) return;
    setState(() { _calculating = true; _priceBreakdown = null; });
    try {
      final params = {
        'from_district_id': _fromDistrict!['id'],
        'to_district_id':   _toDistrict!['id'],
        'move_type':        _typeSlug,
        if (_isHouseOrSingle && _rooms > 0) 'room_count': _rooms,
        if (_selectedPackage != null)        'package_id': _selectedPackage!['id'],
        if (_selectedExtras.isNotEmpty)      'extra_services': _selectedExtras.toList(),
      };
      final res = await _svc.calculateMoving(params);
      setState(() {
        _priceBreakdown = res['data'] ?? res;
        _calculating = false;
      });
    } catch (e) {
      setState(() => _calculating = false);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Calculation failed: $e'), backgroundColor: Colors.red));
    }
  }

  void _openBookingSheet() {
    Navigator.of(context, rootNavigator: true).push(
      MaterialPageRoute(
        builder: (_) => _BookingFlowScreen(
          moveType:        _selectedType!,
          selectedPackage: _selectedPackage,
          rooms:           _rooms,
          fromDistrict:    _fromDistrict!,
          toDistrict:      _toDistrict!,
          selectedExtras:  Set.from(_selectedExtras),
          priceBreakdown:  _priceBreakdown,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final typesAsync = ref.watch(_moveTypesProvider);

    return SingleChildScrollView(
      padding: const EdgeInsets.only(bottom: 40),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── SECTION 1: Move Type Grid ──────────────────────────────────
          _SectionHeader(
            icon: Icons.category_rounded,
            title: 'Select Move Type',
            subtitle: 'Choose what you need to move',
          ),
          typesAsync.when(
            loading: () => _TypeGridSkeleton(),
            error: (e, _) => _ErrorWidget(
              onRetry: () => ref.invalidate(_moveTypesProvider),
            ),
            data: (data) {
              final types = List<Map<String, dynamic>>.from(data['data'] ?? data ?? []);
              return _TypeGrid(
                types: types,
                selected: _selectedType,
                typeInfo: _typeInfo,
                onSelect: (t) => setState(() {
                  _selectedType    = t;
                  _selectedPackage = null;
                  _rooms           = 0;
                  _priceBreakdown  = null;
                }),
              );
            },
          ),

          // ── SECTION 2: Packages (animated) ────────────────────────────
          AnimatedSize(
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeInOut,
            child: _selectedType == null
                ? const SizedBox.shrink()
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _SectionHeader(
                        icon: Icons.inventory_2_rounded,
                        title: _isHouseOrSingle ? 'Room Count' : 'Select Package',
                        subtitle: _isHouseOrSingle
                            ? 'How many rooms are you moving?'
                            : 'Pick the package that fits your needs',
                      ),
                      _PackagesSection(
                        typeSlug:        _typeSlug,
                        isHouseOrSingle: _isHouseOrSingle,
                        selectedPackage: _selectedPackage,
                        rooms:           _rooms,
                        onPackageSelect: (p) => setState(() {
                          _selectedPackage = p;
                          _priceBreakdown  = null;
                        }),
                        onRoomsChange: (r) => setState(() {
                          _rooms          = r;
                          _priceBreakdown = null;
                        }),
                      ),
                    ],
                  ),
          ),

          // ── SECTION 3: Route Selection ─────────────────────────────────
          _SectionHeader(
            icon: Icons.route_rounded,
            title: 'Select Route',
            subtitle: 'Pickup and delivery districts',
          ),
          _RouteSection(
            fromDistrict: _fromDistrict,
            toDistrict:   _toDistrict,
            onFromSelect: (d) => setState(() { _fromDistrict = d; _priceBreakdown = null; }),
            onToSelect:   (d) => setState(() { _toDistrict = d;   _priceBreakdown = null; }),
          ),

          // ── SECTION 4: Extra Services ─────────────────────────────────
          _SectionHeader(
            icon: Icons.add_box_outlined,
            title: 'Extra Services',
            subtitle: 'Optional add-ons for your move',
          ),
          _ExtrasSection(
            selectedExtras: _selectedExtras,
            onToggle: (id) => setState(() {
              if (_selectedExtras.contains(id)) {
                _selectedExtras.remove(id);
              } else {
                _selectedExtras.add(id);
              }
              _priceBreakdown = null;
            }),
          ),

          // ── SECTION 5: Price Breakdown ────────────────────────────────
          AnimatedSize(
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeInOut,
            child: _canCalculate
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _SectionHeader(
                        icon: Icons.receipt_long_rounded,
                        title: 'Price Estimate',
                        subtitle: 'Tap Calculate to get your quote',
                      ),
                      _PriceSection(
                        breakdown:   _priceBreakdown,
                        calculating: _calculating,
                        onCalculate: _calculate,
                      ),
                    ],
                  )
                : const SizedBox.shrink(),
          ),

          // ── SECTION 6: Book Button ────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            child: ElevatedButton(
              onPressed: (_canCalculate) ? _openBookingSheet : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: _kOrange,
                disabledBackgroundColor: _kMuted,
                minimumSize: const Size(double.infinity, 56),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16)),
                elevation: 4,
                shadowColor: _kOrange.withValues(alpha: 0.4),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: const [
                  Icon(Icons.local_shipping_rounded, size: 22),
                  SizedBox(width: 10),
                  Text(
                    'Book Moving Service',
                    style: TextStyle(
                        fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION HEADER
// ═══════════════════════════════════════════════════════════════════════════

class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  const _SectionHeader({required this.icon, required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 22, 16, 10),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: _kOrange.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: _kOrange, size: 18),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title,
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: context.colors.navyText)),
              Text(subtitle,
                  style: TextStyle(fontSize: 12, color: AppColors.textGrey)),
            ],
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// TYPE GRID — 2×2
// ═══════════════════════════════════════════════════════════════════════════

class _TypeGrid extends StatelessWidget {
  final List<Map<String, dynamic>> types;
  final Map<String, dynamic>? selected;
  final Map<String, Object> typeInfo;
  final ValueChanged<Map<String, dynamic>> onSelect;

  const _TypeGrid({
    required this.types,
    required this.selected,
    required this.typeInfo,
    required this.onSelect,
  });

  IconData _icon(String slug) {
    if (slug.contains('house')) return Icons.home_rounded;
    if (slug.contains('office')) return Icons.business_rounded;
    if (slug.contains('commercial')) return Icons.storefront_rounded;
    if (slug.contains('single')) return Icons.inventory_2_rounded;
    return Icons.local_shipping_rounded;
  }

  Color _color(String slug) {
    if (slug.contains('house')) return const Color(0xFF2ECC71);
    if (slug.contains('office')) return const Color(0xFF3498DB);
    if (slug.contains('commercial')) return const Color(0xFF9B59B6);
    if (slug.contains('single')) return const Color(0xFFE67E22);
    return _kOrange;
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          childAspectRatio: 2.2,
        ),
        itemCount: types.length,
        itemBuilder: (_, i) {
          final t    = types[i];
          final slug = (t['id'] ?? t['type'] ?? t['slug'] ?? '').toString().toLowerCase();
          final name = t['name']?.toString() ?? slug;
          final icon = _icon(slug);
          final color = _color(slug);
          final isSelected = (selected?['id'] ?? selected?['type'] ?? '') == (t['id'] ?? t['type']);

          return GestureDetector(
            onTap: () => onSelect(t),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              decoration: BoxDecoration(
                color: isSelected ? color : context.colors.cardBg,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: isSelected ? color : AppColors.divider,
                  width: isSelected ? 2 : 1,
                ),
                boxShadow: isSelected
                    ? [BoxShadow(color: color.withValues(alpha: 0.35), blurRadius: 10, offset: const Offset(0, 4))]
                    : [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 4, offset: const Offset(0, 2))],
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? Colors.white.withValues(alpha: 0.25)
                            : color.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(icon,
                          color: isSelected ? Colors.white : color,
                          size: 22),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        name,
                        style: TextStyle(
                          color: isSelected ? Colors.white : context.colors.navyText,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (isSelected)
                      Icon(Icons.check_circle_rounded,
                          color: context.colors.cardBg, size: 18),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// PACKAGES SECTION
// ═══════════════════════════════════════════════════════════════════════════

class _PackagesSection extends ConsumerWidget {
  final String typeSlug;
  final bool isHouseOrSingle;
  final Map<String, dynamic>? selectedPackage;
  final int rooms;
  final ValueChanged<Map<String, dynamic>> onPackageSelect;
  final ValueChanged<int> onRoomsChange;

  const _PackagesSection({
    required this.typeSlug,
    required this.isHouseOrSingle,
    required this.selectedPackage,
    required this.rooms,
    required this.onPackageSelect,
    required this.onRoomsChange,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pkgAsync = ref.watch(_packagesProvider(typeSlug));

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: pkgAsync.when(
        loading: () => _PackagesSkeleton(),
        error: (e, _) => _ErrorWidget(
          onRetry: () => ref.invalidate(_packagesProvider(typeSlug)),
        ),
        data: (data) {
          final packages = List<Map<String, dynamic>>.from(data['data'] ?? data ?? []);

          if (isHouseOrSingle) {
            return Column(
              children: [
                // Room count stepper
                _RoomsSelector(rooms: rooms, onChanged: onRoomsChange),
                // Show packages if any exist for the type
                if (packages.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Or select a package:',
                      style: TextStyle(fontSize: 13, color: AppColors.textGrey, fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...packages.map((p) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _PackageCard(
                          package: p,
                          selected: selectedPackage?['id']?.toString() == p['id']?.toString(),
                          onTap: () => onPackageSelect(p),
                        ),
                      )),
                ],
              ],
            );
          }

          if (packages.isEmpty) {
            return Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: context.colors.cardBg,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.divider),
              ),
              child: const Center(
                child: Text(
                  'No packages available — contact us for a custom quote',
                  style: TextStyle(color: _kMuted, fontSize: 13),
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          return SizedBox(
            height: 170,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: packages.length,
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemBuilder: (_, i) => SizedBox(
                width: 220,
                child: _PackageCard(
                  package: packages[i],
                  selected: selectedPackage?['id']?.toString() == packages[i]['id']?.toString(),
                  onTap: () => onPackageSelect(packages[i]),
                  compact: false,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _RoomsSelector extends StatelessWidget {
  final int rooms;
  final ValueChanged<int> onChanged;
  const _RoomsSelector({required this.rooms, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Number of Rooms',
              style: TextStyle(
                  fontSize: 14, fontWeight: FontWeight.w700, color: context.colors.navyText)),
          const SizedBox(height: 12),
          Row(
            children: List.generate(6, (i) {
              final r = i + 1;
              final label = r == 6 ? '5+' : '$r';
              final active = rooms == r;
              return Expanded(
                child: GestureDetector(
                  onTap: () => onChanged(r),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    margin: EdgeInsets.only(right: i < 5 ? 6 : 0),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: BoxDecoration(
                      color: active ? _kOrange : context.colors.surfaceBg,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: active ? _kOrange : AppColors.divider,
                      ),
                    ),
                    child: Column(
                      children: [
                        Text(
                          label,
                          style: TextStyle(
                            color: active ? Colors.white : context.colors.navyText,
                            fontWeight: FontWeight.w800,
                            fontSize: 16,
                          ),
                        ),
                        Text(
                          r == 1 ? 'room' : 'rooms',
                          style: TextStyle(
                            color: active ? Colors.white70 : _kMuted,
                            fontSize: 9,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _PackageCard extends StatelessWidget {
  final Map<String, dynamic> package;
  final bool selected;
  final VoidCallback onTap;
  final bool compact;
  const _PackageCard({
    required this.package,
    required this.selected,
    required this.onTap,
    this.compact = true,
  });

  @override
  Widget build(BuildContext context) {
    final name     = package['name']?.toString() ?? '';
    final desc     = package['description']?.toString() ?? '';
    final price    = package['price'] ?? 0;
    final includes = List<String>.from(package['includes'] ?? package['features'] ?? []);

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? _kOrange : AppColors.divider,
            width: selected ? 2 : 1,
          ),
          boxShadow: selected
              ? [BoxShadow(color: _kOrange.withValues(alpha: 0.18), blurRadius: 12, offset: const Offset(0, 4))]
              : [],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(name,
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: context.colors.navyText)),
                ),
                Text(
                  '\$${NumberFormat('#,##0').format(double.tryParse(price.toString()) ?? 0.0)}',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: _kOrange),
                ),
              ],
            ),
            if (desc.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(desc,
                  style: TextStyle(fontSize: 12, color: AppColors.textGrey),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis),
            ],
            if (includes.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 5,
                runSpacing: 4,
                children: includes
                    .take(4)
                    .map((f) => Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                          decoration: BoxDecoration(
                            color: selected
                                ? _kOrange.withValues(alpha: 0.1)
                                : context.colors.surfaceBg,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(f,
                              style: TextStyle(
                                  fontSize: 10,
                                  color: selected ? _kOrange : _kMuted)),
                        ))
                    .toList(),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// ROUTE SECTION
// ═══════════════════════════════════════════════════════════════════════════

class _RouteSection extends ConsumerWidget {
  final Map<String, dynamic>? fromDistrict;
  final Map<String, dynamic>? toDistrict;
  final ValueChanged<Map<String, dynamic>> onFromSelect;
  final ValueChanged<Map<String, dynamic>> onToSelect;

  const _RouteSection({
    required this.fromDistrict,
    required this.toDistrict,
    required this.onFromSelect,
    required this.onToSelect,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final districtsAsync = ref.watch(_districtsProvider);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: districtsAsync.when(
        loading: () => Container(
          height: 110,
          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16)),
          child: const Center(child: CircularProgressIndicator()),
        ),
        error: (_, __) => _ErrorWidget(
          onRetry: () => ref.invalidate(_districtsProvider),
        ),
        data: (data) {
          final districts = List<Map<String, dynamic>>.from(data['data'] ?? data ?? []);
          return Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: _DistrictDropdown(
                      label: 'From District',
                      icon: Icons.location_on_rounded,
                      iconColor: _kOrange,
                      selected: fromDistrict,
                      districts: districts,
                      onSelected: onFromSelect,
                    ),
                  ),
                  Container(
                    margin: const EdgeInsets.symmetric(horizontal: 10),
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: context.colors.navyText.withValues(alpha: 0.07),
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.divider),
                    ),
                    child: Icon(Icons.swap_horiz_rounded,
                        color: context.colors.navyText, size: 18),
                  ),
                  Expanded(
                    child: _DistrictDropdown(
                      label: 'To District',
                      icon: Icons.flag_rounded,
                      iconColor: const Color(0xFF2ECC71),
                      selected: toDistrict,
                      districts: districts,
                      onSelected: onToSelect,
                    ),
                  ),
                ],
              ),
              if (fromDistrict != null && toDistrict != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [_kNavy, Color(0xFF0D006B)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          children: [
                            const Icon(Icons.location_on_rounded,
                                color: _kOrange, size: 18),
                            const SizedBox(height: 4),
                            Text(
                              fromDistrict!['name']?.toString() ?? '',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.arrow_forward_rounded,
                          color: Colors.white38, size: 18),
                      Expanded(
                        child: Column(
                          children: [
                            const Icon(Icons.flag_rounded,
                                color: Color(0xFF2ECC71), size: 18),
                            const SizedBox(height: 4),
                            Text(
                              toDistrict!['name']?.toString() ?? '',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _DistrictDropdown extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color iconColor;
  final Map<String, dynamic>? selected;
  final List<Map<String, dynamic>> districts;
  final ValueChanged<Map<String, dynamic>> onSelected;

  const _DistrictDropdown({
    required this.label,
    required this.icon,
    required this.iconColor,
    required this.selected,
    required this.districts,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _DistrictPickerSheet(
          title: label,
          districts: districts,
          onSelected: onSelected,
        ),
      ),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected != null ? iconColor.withValues(alpha: 0.5) : AppColors.divider,
            width: selected != null ? 1.5 : 1,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 14, color: iconColor),
                const SizedBox(width: 5),
                Text(label,
                    style: TextStyle(fontSize: 10, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
                const Spacer(),
                const Icon(Icons.keyboard_arrow_down_rounded,
                    size: 16, color: AppColors.textGrey),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              selected != null ? selected!['name']?.toString() ?? 'Selected' : 'Tap to select',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: selected != null ? context.colors.navyText : _kMuted,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// EXTRAS SECTION
// ═══════════════════════════════════════════════════════════════════════════

class _ExtrasSection extends ConsumerStatefulWidget {
  final Set<int> selectedExtras;
  final ValueChanged<int> onToggle;

  const _ExtrasSection({required this.selectedExtras, required this.onToggle});

  @override
  ConsumerState<_ExtrasSection> createState() => _ExtrasSectionState();
}

class _ExtrasSectionState extends ConsumerState<_ExtrasSection> {
  bool _expanded = false;

  static const _icons = {
    'packing': Icons.inventory_2_rounded,
    'unpack':  Icons.unarchive_rounded,
    'load':    Icons.upload_rounded,
    'clean':   Icons.cleaning_services_rounded,
    'assem':   Icons.build_rounded,
    'storage': Icons.warehouse_rounded,
    'insur':   Icons.security_rounded,
  };

  IconData _icon(String name) {
    final lower = name.toLowerCase();
    final key = _icons.keys.firstWhere((k) => lower.contains(k), orElse: () => '');
    return key.isEmpty ? Icons.add_box_outlined : _icons[key]!;
  }

  @override
  Widget build(BuildContext context) {
    final extrasAsync = ref.watch(_extraServicesProvider);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: extrasAsync.when(
        loading: () => const SizedBox.shrink(),
        error: (_, __) => const SizedBox.shrink(),
        data: (data) {
          final extras = List<Map<String, dynamic>>.from(data['data'] ?? data ?? []);
          if (extras.isEmpty) return const SizedBox.shrink();

          final selectedCount = widget.selectedExtras.length;

          return Container(
            decoration: BoxDecoration(color: context.colors.cardBg,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: selectedCount > 0 ? _kOrange : AppColors.divider,
                width: selectedCount > 0 ? 1.5 : 1,
              ),
            ),
            child: Column(
              children: [
                // Dropdown header
                InkWell(
                  onTap: () => setState(() => _expanded = !_expanded),
                  borderRadius: BorderRadius.circular(14),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(7),
                          decoration: BoxDecoration(
                            color: _kOrange.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Icon(Icons.add_box_outlined, color: _kOrange, size: 18),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            selectedCount == 0
                                ? 'Select extra services'
                                : '$selectedCount service${selectedCount > 1 ? 's' : ''} selected',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: selectedCount > 0 ? _kOrange : _kMuted,
                            ),
                          ),
                        ),
                        if (selectedCount > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: _kOrange,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              '+\$${NumberFormat('#,##0').format(extras
                                  .where((e) => widget.selectedExtras.contains(int.tryParse(e['id']?.toString() ?? '0') ?? 0))
                                  .fold<double>(0, (s, e) => s + (double.tryParse(e['price']?.toString() ?? '0') ?? 0))
                                  .toInt())}',
                              style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700),
                            ),
                          ),
                        const SizedBox(width: 6),
                        AnimatedRotation(
                          turns: _expanded ? 0.5 : 0,
                          duration: const Duration(milliseconds: 200),
                          child: const Icon(Icons.keyboard_arrow_down_rounded, color: _kMuted, size: 20),
                        ),
                      ],
                    ),
                  ),
                ),
                // Expanded list
                AnimatedCrossFade(
                  firstChild: const SizedBox.shrink(),
                  secondChild: Column(
                    children: [
                      const Divider(height: 1),
                      ...extras.map((e) {
                        final id   = int.tryParse(e['id']?.toString() ?? '0') ?? 0;
                        final name = e['name']?.toString() ?? '';
                        final price = double.tryParse(e['price']?.toString() ?? '0') ?? 0.0;
                        final unit  = e['unit']?.toString() ?? '';
                        final isSelected = widget.selectedExtras.contains(id);

                        return InkWell(
                          onTap: () => widget.onToggle(id),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 150),
                            color: isSelected ? _kOrange.withValues(alpha: 0.06) : Colors.transparent,
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            child: Row(
                              children: [
                                Icon(_icon(name),
                                    color: isSelected ? _kOrange : _kMuted,
                                    size: 18),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(name,
                                      style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                                        color: context.colors.navyText,
                                      )),
                                ),
                                Text(
                                  '\$${NumberFormat('#,##0').format(price)}${unit.isNotEmpty ? ' / $unit' : ''}',
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: isSelected ? _kOrange : _kMuted,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                AnimatedContainer(
                                  duration: const Duration(milliseconds: 150),
                                  width: 22,
                                  height: 22,
                                  decoration: BoxDecoration(
                                    color: isSelected ? _kOrange : Colors.transparent,
                                    borderRadius: BorderRadius.circular(6),
                                    border: Border.all(
                                      color: isSelected ? _kOrange : AppColors.divider,
                                      width: 1.5,
                                    ),
                                  ),
                                  child: isSelected
                                      ? const Icon(Icons.check, color: Colors.white, size: 14)
                                      : null,
                                ),
                              ],
                            ),
                          ),
                        );
                      }),
                    ],
                  ),
                  crossFadeState: _expanded ? CrossFadeState.showSecond : CrossFadeState.showFirst,
                  duration: const Duration(milliseconds: 250),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// PRICE SECTION
// ═══════════════════════════════════════════════════════════════════════════

class _PriceSection extends StatelessWidget {
  final Map<String, dynamic>? breakdown;
  final bool calculating;
  final VoidCallback onCalculate;

  const _PriceSection({
    required this.breakdown,
    required this.calculating,
    required this.onCalculate,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.divider),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          children: [
            if (breakdown != null) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 0),
                child: Column(
                  children: [
                    _PriceLineItem('Base Price',     breakdown!['base_price'] ?? 0),
                    _PriceLineItem('Room Cost',      breakdown!['room_price'] ?? 0),
                    _PriceLineItem('Package Cost',   breakdown!['package_price'] ?? 0),
                    _PriceLineItem('Extra Services', breakdown!['extra_fee'] ?? 0),
                    _PriceLineItem('Distance Fee',   breakdown!['distance_fee'] ?? 0),
                    const Divider(height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('TOTAL',
                            style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: context.colors.navyText,
                                letterSpacing: 0.5)),
                        Text(
                          '\$${NumberFormat('#,##0.00').format(double.tryParse(breakdown!['total']?.toString() ?? '0') ?? 0)}',
                          style: TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                              color: _kOrange),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              const Divider(height: 1),
            ],
            Padding(
              padding: const EdgeInsets.all(16),
              child: ElevatedButton(
                onPressed: calculating ? null : onCalculate,
                style: ElevatedButton.styleFrom(
                  backgroundColor: context.colors.navyText,
                  minimumSize: const Size(double.infinity, 48),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: calculating
                    ? SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            color: context.colors.cardBg, strokeWidth: 2),
                      )
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Icon(Icons.calculate_rounded, size: 18),
                          SizedBox(width: 8),
                          Text('Calculate Price',
                              style: TextStyle(
                                  fontSize: 14, fontWeight: FontWeight.w700)),
                        ],
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PriceLineItem extends StatelessWidget {
  final String label;
  final dynamic value;
  const _PriceLineItem(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    final n = double.tryParse(value.toString()) ?? 0.0;
    if (n == 0) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
          Text('\$${NumberFormat('#,##0.00').format(n)}',
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: context.colors.navyText)),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// DISTRICT PICKER SHEET
// ═══════════════════════════════════════════════════════════════════════════

class _DistrictPickerSheet extends StatefulWidget {
  final String title;
  final List<Map<String, dynamic>> districts;
  final ValueChanged<Map<String, dynamic>> onSelected;

  const _DistrictPickerSheet({
    required this.title,
    required this.districts,
    required this.onSelected,
  });

  @override
  State<_DistrictPickerSheet> createState() => _DistrictPickerSheetState();
}

class _DistrictPickerSheetState extends State<_DistrictPickerSheet> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.districts
        .where((d) => (d['name']?.toString().toLowerCase() ?? '').contains(_q.toLowerCase()))
        .toList();

    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      minChildSize: 0.4,
      maxChildSize: 0.92,
      expand: false,
      builder: (_, ctrl) => Container(
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(top: 12, bottom: 16),
              decoration: BoxDecoration(
                color: AppColors.divider,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(widget.title,
                      style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: context.colors.navyText)),
                  const SizedBox(height: 12),
                  TextField(
                    onChanged: (v) => setState(() => _q = v),
                    decoration: const InputDecoration(
                      hintText: 'Search district...',
                      prefixIcon: Icon(Icons.search_rounded),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: ListView.builder(
                controller: ctrl,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: filtered.length,
                itemBuilder: (_, i) {
                  final d = filtered[i];
                  return ListTile(
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                    leading: Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: _kOrange.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.location_on_rounded,
                          color: _kOrange, size: 20),
                    ),
                    title: Text(d['name']?.toString() ?? '',
                        style: TextStyle(
                            fontWeight: FontWeight.w600, fontSize: 14)),
                    onTap: () {
                      Navigator.pop(context);
                      widget.onSelected(d);
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// BOOKING FLOW SCREEN — multi-step for date/address/confirm
// ═══════════════════════════════════════════════════════════════════════════

class _BookingFlowScreen extends ConsumerStatefulWidget {
  final dynamic moveType;
  final Map<String, dynamic>? selectedPackage;
  final int rooms;
  final Map<String, dynamic> fromDistrict;
  final Map<String, dynamic> toDistrict;
  final Set<int> selectedExtras;
  final Map<String, dynamic>? priceBreakdown;

  const _BookingFlowScreen({
    required this.moveType,
    required this.selectedPackage,
    required this.rooms,
    required this.fromDistrict,
    required this.toDistrict,
    required this.selectedExtras,
    required this.priceBreakdown,
  });

  @override
  ConsumerState<_BookingFlowScreen> createState() => _BookingFlowScreenState();
}

class _BookingFlowScreenState extends ConsumerState<_BookingFlowScreen> {
  // step 0 = schedule/details, step 1 = confirm, step 2 = success
  int _step = 0;

  DateTime _moveDate    = DateTime.now().add(const Duration(days: 1));
  String _notes         = '';
  String _paymentMethod = 'wallet';
  String? _waafiReference;
  bool _submitting      = false;
  Map<String, dynamic>? _confirmedOrder;
  Map<String, dynamic>? _finalBreakdown;

  String get _typeSlug =>
      (widget.moveType['id'] ?? widget.moveType['slug'] ?? widget.moveType['type'] ?? 'house')
          .toString()
          .toLowerCase();

  bool get _isHouseOrSingle =>
      _typeSlug.contains('house') || _typeSlug.contains('single');

  Future<void> _recalcAndNext() async {
    // Re-calculate price with updated fields if needed
    Map<String, dynamic>? breakdown = widget.priceBreakdown;
    if (breakdown == null) {
      try {
        final params = {
          'from_district_id': widget.fromDistrict['id'],
          'to_district_id':   widget.toDistrict['id'],
          'move_type':        _typeSlug,
          if (_isHouseOrSingle && widget.rooms > 0) 'room_count': widget.rooms,
          if (widget.selectedPackage != null)        'package_id': widget.selectedPackage!['id'],
          if (widget.selectedExtras.isNotEmpty)      'extra_services': widget.selectedExtras.toList(),
        };
        final res = await _svc.calculateMoving(params);
        breakdown = res['data'] ?? res;
      } catch (_) {}
    }
    setState(() {
      _finalBreakdown = breakdown;
      _step = 1;
    });
  }

  Future<void> _submitOrder() async {
    if (_paymentMethod == 'waafi_pay') {
      final total = (_finalBreakdown?['total'] as num?)?.toDouble() ?? 0;
      final result = await showWaafiPaySheet(
        context,
        amount: total,
        type: 'order',
        description: 'eMoving Order',
      );
      if (result?.success != true) return;
      _waafiReference = result!.reference;
    }

    if (_paymentMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _submitting = true);
    try {
      final body = {
        'from_district_id': widget.fromDistrict['id'],
        'to_district_id':   widget.toDistrict['id'],
        'move_type':        _typeSlug,
        if (widget.selectedPackage != null)        'package_id': widget.selectedPackage!['id'],
        if (_isHouseOrSingle && widget.rooms > 0)  'room_count': widget.rooms,
        if (widget.selectedExtras.isNotEmpty)      'extra_services': widget.selectedExtras.toList(),
        'scheduled_date': DateFormat('yyyy-MM-dd').format(_moveDate),
        'payment_method': _paymentMethod,
        'note': _notes,
        if (_waafiReference != null) 'payment_reference': _waafiReference,
      };
      final res = await _svc.placeMovingOrder(body);
      setState(() {
        _confirmedOrder = res['data'] ?? res;
        _submitting = false;
        _step = 2;
      });
      ref.invalidate(_myOrdersProvider);
      if (_paymentMethod == 'wallet') ref.invalidate(walletProvider);
    } catch (e) {
      setState(() => _submitting = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Booking failed: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
            appBar: _step < 2
          ? AppBar(
              backgroundColor: context.colors.navyText,
              foregroundColor: Colors.white,
              title: Text(
                _step == 0 ? 'Schedule Your Move' : 'Confirm Booking',
                style: TextStyle(
                    color: context.colors.cardBg, fontWeight: FontWeight.w700),
              ),
              bottom: PreferredSize(
                preferredSize: const Size.fromHeight(50),
                child: _StepIndicator(current: _step, total: 2),
              ),
            )
          : null,
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 300),
        transitionBuilder: (child, animation) =>
            SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0.3, 0),
                end: Offset.zero,
              ).animate(animation),
              child: FadeTransition(opacity: animation, child: child),
            ),
        child: _buildStep(),
      ),
    );
  }

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _ScheduleStep(
          key: const ValueKey(0),
          moveDate:      _moveDate,
          notes:         _notes,
          paymentMethod: _paymentMethod,
          onDateChanged:    (d) => setState(() => _moveDate = d),
          onNotesChanged:   (n) => setState(() => _notes = n),
          onPaymentChanged: (p) => setState(() => _paymentMethod = p),
          onNext: _recalcAndNext,
        );
      case 1:
        return _ConfirmStep(
          key: const ValueKey(1),
          moveType:       widget.moveType,
          fromDistrict:   widget.fromDistrict,
          toDistrict:     widget.toDistrict,
          selectedPackage:widget.selectedPackage,
          rooms:          widget.rooms,
          selectedExtras: widget.selectedExtras,
          moveDate:       _moveDate,
          notes:          _notes,
          paymentMethod:  _paymentMethod,
          priceBreakdown: _finalBreakdown,
          submitting:     _submitting,
          onConfirm:      _submitOrder,
          onBack:         () => setState(() => _step = 0),
        );
      case 2:
        return _SuccessScreen(
          key: const ValueKey(2),
          order: _confirmedOrder,
        );
      default:
        return const SizedBox.shrink();
    }
  }
}

// ─── Step Indicator ─────────────────────────────────────────────────────────

class _StepIndicator extends StatelessWidget {
  final int current;
  final int total;
  const _StepIndicator({required this.current, required this.total});

  static const _labels = ['Schedule', 'Confirm'];

  @override
  Widget build(BuildContext context) {
    return Container(
      color: context.colors.navyText,
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
      child: Row(
        children: List.generate(total, (i) {
          final done   = i < current;
          final active = i == current;
          return Expanded(
            child: Row(
              children: [
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: done
                            ? _kOrange
                            : active
                                ? Colors.white
                                : Colors.white24,
                      ),
                      child: Center(
                        child: done
                            ? const Icon(Icons.check, color: Colors.white, size: 13)
                            : Text('${i + 1}',
                                style: TextStyle(
                                    color: active ? _kNavy : Colors.white60,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700)),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(_labels[i],
                        style: TextStyle(
                            color: active ? Colors.white : Colors.white54,
                            fontSize: 9,
                            fontWeight: active ? FontWeight.w700 : FontWeight.w400)),
                  ],
                ),
                if (i < total - 1)
                  Expanded(
                    child: Container(
                      height: 2,
                      margin: const EdgeInsets.only(bottom: 14),
                      color: done ? _kOrange : Colors.white24,
                    ),
                  ),
              ],
            ),
          );
        }),
      ),
    );
  }
}

// ─── Schedule Step ───────────────────────────────────────────────────────────

class _ScheduleStep extends StatelessWidget {
  final DateTime moveDate;
  final String notes;
  final String paymentMethod;
  final ValueChanged<DateTime> onDateChanged;
  final ValueChanged<String> onNotesChanged;
  final ValueChanged<String> onPaymentChanged;
  final VoidCallback onNext;

  const _ScheduleStep({
    super.key,
    required this.moveDate,
    required this.notes,
    required this.paymentMethod,
    required this.onDateChanged,
    required this.onNotesChanged,
    required this.onPaymentChanged,
    required this.onNext,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Move Date
                const _FormLabel('Move Date'),
                const SizedBox(height: 8),
                GestureDetector(
                  onTap: () async {
                    final d = await showDatePicker(
                      context: context,
                      initialDate: moveDate,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 90)),
                      builder: (ctx, child) => Theme(
                        data: Theme.of(ctx).copyWith(
                          colorScheme: const ColorScheme.light(primary: _kOrange),
                        ),
                        child: child!,
                      ),
                    );
                    if (d != null) onDateChanged(d);
                  },
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(color: context.colors.cardBg,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.divider),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.calendar_month_rounded,
                            color: _kOrange, size: 22),
                        const SizedBox(width: 12),
                        Text(
                          DateFormat('EEEE, MMMM d, yyyy').format(moveDate),
                          style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: context.colors.navyText),
                        ),
                        const Spacer(),
                        const Icon(Icons.keyboard_arrow_down_rounded,
                            color: AppColors.textGrey),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                // Payment
                const _FormLabel('Payment Method'),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: _PaymentTile(
                        label: 'Wallet',
                        icon: Icons.account_balance_wallet_rounded,
                        selected: paymentMethod == 'wallet',
                        onTap: () => onPaymentChanged('wallet'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _PaymentTile(
                        label: 'Waafi Pay',
                        icon: Icons.phone_android_rounded,
                        selected: paymentMethod == 'waafi_pay',
                        onTap: () => onPaymentChanged('waafi_pay'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                // Notes
                const _FormLabel('Notes (Optional)'),
                const SizedBox(height: 8),
                TextFormField(
                  initialValue: notes,
                  onChanged: onNotesChanged,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    hintText: 'Any special instructions for the moving team...',
                  ),
                ),
              ],
            ),
          ),
        ),
        _BottomCTA(label: 'Review & Confirm', onPressed: onNext),
      ],
    );
  }
}

class _FormLabel extends StatelessWidget {
  final String text;
  const _FormLabel(this.text);
  @override
  Widget build(BuildContext context) {
    return Text(text,
        style: TextStyle(
            fontSize: 14, fontWeight: FontWeight.w700, color: context.colors.navyText));
  }
}

class _PaymentTile extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  const _PaymentTile({required this.label, required this.icon, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: selected ? _kOrange.withValues(alpha: 0.08) : context.colors.cardBg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? _kOrange : AppColors.divider,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Column(
          children: [
            Icon(icon, color: selected ? _kOrange : _kMuted, size: 24),
            const SizedBox(height: 6),
            Text(label,
                style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: selected ? _kOrange : _kMuted)),
          ],
        ),
      ),
    );
  }
}

// ─── Confirm Step ─────────────────────────────────────────────────────────────

class _ConfirmStep extends ConsumerWidget {
  final dynamic moveType;
  final Map<String, dynamic> fromDistrict;
  final Map<String, dynamic> toDistrict;
  final Map<String, dynamic>? selectedPackage;
  final int rooms;
  final Set<int> selectedExtras;
  final DateTime moveDate;
  final String notes;
  final String paymentMethod;
  final Map<String, dynamic>? priceBreakdown;
  final bool submitting;
  final VoidCallback onConfirm;
  final VoidCallback onBack;

  const _ConfirmStep({
    super.key,
    required this.moveType,
    required this.fromDistrict,
    required this.toDistrict,
    required this.selectedPackage,
    required this.rooms,
    required this.selectedExtras,
    required this.moveDate,
    required this.notes,
    required this.paymentMethod,
    required this.priceBreakdown,
    required this.submitting,
    required this.onConfirm,
    required this.onBack,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final extrasAsync = ref.watch(_extraServicesProvider);
    final allExtras = extrasAsync.maybeWhen(
      data: (d) => List<Map<String, dynamic>>.from(d['data'] ?? d ?? []),
      orElse: () => <Map<String, dynamic>>[],
    );
    final selectedExtrasList = allExtras
        .where((e) => selectedExtras.contains(int.tryParse(e['id']?.toString() ?? '0') ?? 0))
        .toList();

    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Confirm Booking',
                    style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: context.colors.navyText)),
                const SizedBox(height: 4),
                Text('Review your details before confirming',
                    style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
                const SizedBox(height: 20),
                // Service
                _ConfirmCard(
                  title: 'SERVICE',
                  rows: [
                    _ConfirmRow(
                        icon: Icons.local_shipping_rounded,
                        label: 'Type',
                        value: moveType['name']?.toString() ?? ''),
                    if (rooms > 0)
                      _ConfirmRow(
                          icon: Icons.bed_rounded,
                          label: 'Rooms',
                          value: '$rooms Room${rooms > 1 ? 's' : ''}'),
                    if (selectedPackage != null)
                      _ConfirmRow(
                          icon: Icons.inventory_2_rounded,
                          label: 'Package',
                          value: selectedPackage!['name']?.toString() ?? ''),
                  ],
                ),
                const SizedBox(height: 12),
                // Route
                _ConfirmCard(
                  title: 'ROUTE',
                  rows: [
                    _ConfirmRow(
                        icon: Icons.location_on_rounded,
                        iconColor: _kOrange,
                        label: 'From',
                        value: fromDistrict['name']?.toString() ?? ''),
                    _ConfirmRow(
                        icon: Icons.flag_rounded,
                        iconColor: const Color(0xFF2ECC71),
                        label: 'To',
                        value: toDistrict['name']?.toString() ?? ''),
                  ],
                ),
                if (selectedExtrasList.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _ConfirmCard(
                    title: 'EXTRA SERVICES',
                    rows: selectedExtrasList
                        .map((e) => _ConfirmRow(
                              icon: Icons.add_circle_outline_rounded,
                              label: e['name']?.toString() ?? '',
                              value: '+\$${NumberFormat('#,##0').format(double.tryParse(e['price']?.toString() ?? '0') ?? 0)}',
                            ))
                        .toList(),
                  ),
                ],
                const SizedBox(height: 12),
                _ConfirmCard(
                  title: 'SCHEDULE',
                  rows: [
                    _ConfirmRow(
                        icon: Icons.calendar_month_rounded,
                        label: 'Date',
                        value: DateFormat('EEE, MMM d, yyyy').format(moveDate)),
                    _ConfirmRow(
                        icon: Icons.payment_rounded,
                        label: 'Payment',
                        value: paymentMethod == 'wallet' ? 'Wallet' : 'Waafi Pay'),
                  ],
                ),
                if (notes.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _ConfirmCard(
                    title: 'NOTES',
                    rows: [
                      _ConfirmRow(
                          icon: Icons.notes_rounded,
                          label: '',
                          value: notes),
                    ],
                  ),
                ],
                if (priceBreakdown != null) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [_kNavy, Color(0xFF0D006B)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('TOTAL AMOUNT',
                            style: TextStyle(
                                color: Colors.white70,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.5)),
                        Text(
                          '\$${NumberFormat('#,##0.00').format(double.tryParse(priceBreakdown!['total']?.toString() ?? '0') ?? 0)}',
                          style: TextStyle(
                              color: _kOrange,
                              fontSize: 24,
                              fontWeight: FontWeight.w900),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
        _BottomCTA(
          label: submitting ? 'Booking...' : 'Confirm Booking',
          onPressed: submitting ? null : onConfirm,
          loading: submitting,
          color: AppColors.success,
          onBack: onBack,
        ),
      ],
    );
  }
}

class _ConfirmCard extends StatelessWidget {
  final String title;
  final List<_ConfirmRow> rows;
  const _ConfirmCard({required this.title, required this.rows});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textGrey,
                  letterSpacing: 0.5)),
          const SizedBox(height: 10),
          ...rows.map((r) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: r,
              )),
        ],
      ),
    );
  }
}

class _ConfirmRow extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  const _ConfirmRow({
    required this.icon,
    this.iconColor = AppColors.secondary,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: iconColor, size: 17),
        const SizedBox(width: 8),
        if (label.isNotEmpty) ...[
          Text(label,
              style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
          const Spacer(),
        ] else
          const SizedBox(width: 4),
        Flexible(
          child: Text(value,
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: context.colors.navyText),
              textAlign: label.isEmpty ? TextAlign.left : TextAlign.right,
              overflow: TextOverflow.ellipsis),
        ),
      ],
    );
  }
}

// ─── Success Screen ───────────────────────────────────────────────────────────

class _SuccessScreen extends StatelessWidget {
  final Map<String, dynamic>? order;
  const _SuccessScreen({super.key, required this.order});

  @override
  Widget build(BuildContext context) {
    final orderNo = order?['order_number'] ?? order?['id'] ?? '—';
    final total   = order?['total'] ?? order?['total_amount'] ?? 0;

    return Scaffold(
            body: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: 1),
                duration: const Duration(milliseconds: 700),
                curve: Curves.elasticOut,
                builder: (_, v, child) =>
                    Transform.scale(scale: v, child: child),
                child: Container(
                  width: 110,
                  height: 110,
                  decoration: BoxDecoration(
                    color: AppColors.success.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.check_circle_rounded,
                      color: AppColors.success, size: 64),
                ),
              ),
              const SizedBox(height: 28),
              Text('Booking Confirmed!',
                  style: TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w900,
                      color: context.colors.navyText)),
              const SizedBox(height: 8),
              Text(
                'Your moving request has been placed.\nOur team will contact you shortly.',
                style: TextStyle(
                    fontSize: 14, color: AppColors.textGrey, height: 1.5),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [_kNavy, Color(0xFF0D006B)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Order Number',
                            style: TextStyle(color: Colors.white60, fontSize: 13)),
                        Text('#$orderNo',
                            style: TextStyle(
                                color: context.colors.cardBg,
                                fontSize: 17,
                                fontWeight: FontWeight.w800)),
                      ],
                    ),
                    if ((double.tryParse(total.toString()) ?? 0) > 0) ...[
                      const Divider(color: Colors.white24, height: 22),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Total Amount',
                              style: TextStyle(color: Colors.white60, fontSize: 13)),
                          Text(
                            '\$${NumberFormat('#,##0.00').format(double.tryParse(total.toString()) ?? 0)}',
                            style: TextStyle(
                                color: _kOrange,
                                fontSize: 22,
                                fontWeight: FontWeight.w900),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.of(context)..pop()..pop(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _kOrange,
                    minimumSize: const Size(double.infinity, 50),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                  child: Text('Back to Home',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                ),
              ),
              SizedBox(height: 12),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text('View My Orders',
                    style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Bottom CTA ───────────────────────────────────────────────────────────────

class _BottomCTA extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final VoidCallback? onBack;
  final bool loading;
  final Color color;

  const _BottomCTA({
    required this.label,
    required this.onPressed,
    this.onBack,
    this.loading = false,
    this.color = _kOrange,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + MediaQuery.of(context).padding.bottom),
      decoration: BoxDecoration(color: context.colors.cardBg,
        border: const Border(top: BorderSide(color: AppColors.divider)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 10,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: Row(
        children: [
          if (onBack != null) ...[
            GestureDetector(
              onTap: onBack,
              child: Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: context.colors.surfaceBg,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.divider),
                ),
                child: const Icon(Icons.arrow_back_rounded, color: AppColors.textGrey),
              ),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: ElevatedButton(
              onPressed: onPressed,
              style: ElevatedButton.styleFrom(
                backgroundColor: onPressed == null ? _kMuted : color,
                minimumSize: const Size(double.infinity, 52),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
              ),
              child: loading
                  ? SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                          color: context.colors.cardBg, strokeWidth: 2),
                    )
                  : Text(label,
                      style: TextStyle(
                          fontSize: 15, fontWeight: FontWeight.w800)),
            ),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// MY ORDERS TAB
// ═══════════════════════════════════════════════════════════════════════════

class _MyOrdersTab extends ConsumerWidget {
  const _MyOrdersTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(_myOrdersProvider);

    return ordersAsync.when(
      loading: () => _OrdersSkeleton(),
      error: (e, _) => _ErrorWidget(
        onRetry: () => ref.invalidate(_myOrdersProvider),
        message: AppErrorHandler.message(e),
      ),
      data: (data) {
        final orders = List<Map<String, dynamic>>.from(data['data'] ?? data ?? []);
        if (orders.isEmpty) {
          return const _EmptyState(
            icon: Icons.local_shipping_outlined,
            title: 'No Orders Yet',
            subtitle: 'Your moving orders will appear here',
          );
        }
        return RefreshIndicator(
          color: _kOrange,
          onRefresh: () async => ref.invalidate(_myOrdersProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: orders.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _OrderCard(order: orders[i]),
          ),
        );
      },
    );
  }
}

class _OrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  const _OrderCard({required this.order});

  static const _statusColors = {
    'pending':     Color(0xFFF57C00),
    'confirmed':   Color(0xFF1565C0),
    'in_progress': Color(0xFF0288D1),
    'completed':   Color(0xFF2E7D32),
    'cancelled':   Color(0xFFE53935),
  };

  @override
  Widget build(BuildContext context) {
    final orderNo  = order['order_number'] ?? order['id'] ?? '—';
    final status   = (order['status'] ?? 'pending').toString().toLowerCase();
    final moveType = order['move_type'] ?? order['type'] ?? '—';
    final moveDate = order['move_date'] ?? order['scheduled_date'] ?? '';
    final total    = order['total_amount'] ?? order['total'] ?? 0;
    final statusColor = _statusColors[status] ?? _kMuted;

    final from = (order['from_district'] is Map
            ? order['from_district']['name']?.toString()
            : order['from_district']?.toString()) ?? '—';
    final to = (order['to_district'] is Map
            ? order['to_district']['name']?.toString()
            : order['to_district']?.toString()) ?? '—';

    return Container(
      decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            decoration: BoxDecoration(
              color: context.colors.navyText.withValues(alpha: 0.04),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(20),
                topRight: Radius.circular(20),
              ),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: context.colors.navyText.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(Icons.local_shipping_rounded,
                      color: context.colors.navyText, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('#$orderNo',
                          style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: context.colors.navyText)),
                      Text(
                        moveType.toString().replaceAll('_', ' ').toUpperCase(),
                        style: TextStyle(
                            fontSize: 11,
                            color: AppColors.textGrey,
                            letterSpacing: 0.3),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    status.replaceAll('_', ' ').toUpperCase(),
                    style: TextStyle(
                        color: statusColor,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.3),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _RouteChip(
                        label: 'From',
                        value: from.toString(),
                        icon: Icons.location_on_rounded,
                        iconColor: _kOrange,
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 8),
                      child: Icon(Icons.arrow_forward_rounded,
                          color: AppColors.textGrey, size: 16),
                    ),
                    Expanded(
                      child: _RouteChip(
                        label: 'To',
                        value: to.toString(),
                        icon: Icons.flag_rounded,
                        iconColor: const Color(0xFF2ECC71),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    if (moveDate.toString().isNotEmpty) ...[
                      const Icon(Icons.calendar_month_rounded,
                          size: 13, color: AppColors.textGrey),
                      const SizedBox(width: 4),
                      Text(moveDate.toString(),
                          style: TextStyle(
                              fontSize: 12, color: AppColors.textGrey)),
                      const Spacer(),
                    ],
                    Text(
                      '\$${NumberFormat('#,##0.00').format(double.tryParse(total.toString()) ?? 0.0)}',
                      style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: _kOrange),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RouteChip extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color iconColor;
  const _RouteChip({required this.label, required this.value, required this.icon, required this.iconColor});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: context.colors.surfaceBg,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: iconColor),
          const SizedBox(width: 5),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: TextStyle(fontSize: 9, color: AppColors.textGrey)),
                Text(value,
                    style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: context.colors.navyText),
                    overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// SKELETONS & UTILITIES
// ═══════════════════════════════════════════════════════════════════════════

class _TypeGridSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.shimmer,
      highlightColor: Colors.white,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 1.05,
          ),
          itemCount: 4,
          itemBuilder: (_, __) => Container(
            decoration: BoxDecoration(color: context.colors.cardBg,
              borderRadius: BorderRadius.circular(20),
            ),
          ),
        ),
      ),
    );
  }
}

class _PackagesSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.shimmer,
      highlightColor: Colors.white,
      child: Column(
        children: List.generate(2, (i) => Container(
          margin: const EdgeInsets.only(bottom: 10),
          height: 80,
          decoration: BoxDecoration(color: context.colors.cardBg,
            borderRadius: BorderRadius.circular(14),
          ),
        )),
      ),
    );
  }
}

class _OrdersSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.shimmer,
      highlightColor: Colors.white,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: List.generate(4, (i) => Container(
            margin: const EdgeInsets.only(bottom: 12),
            height: 130,
            decoration: BoxDecoration(color: context.colors.cardBg,
              borderRadius: BorderRadius.circular(20),
            ),
          )),
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  const _EmptyState({required this.icon, required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(40),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: _kOrange.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 48, color: _kOrange.withValues(alpha: 0.5)),
            ),
            const SizedBox(height: 20),
            Text(title,
                style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: context.colors.navyText)),
            const SizedBox(height: 8),
            Text(subtitle,
                style: TextStyle(fontSize: 14, color: AppColors.textGrey),
                textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class _ErrorWidget extends StatelessWidget {
  final VoidCallback onRetry;
  final String message;
  const _ErrorWidget({required this.onRetry, this.message = 'Something went wrong'});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.wifi_off_rounded,
                size: 48, color: AppColors.error.withValues(alpha: 0.5)),
            const SizedBox(height: 16),
            Text(message,
                style: TextStyle(fontSize: 13, color: AppColors.textGrey),
                textAlign: TextAlign.center,
                maxLines: 3,
                overflow: TextOverflow.ellipsis),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Try Again'),
              style: ElevatedButton.styleFrom(backgroundColor: _kOrange),
            ),
          ],
        ),
      ),
    );
  }
}
