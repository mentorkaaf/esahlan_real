import 'dart:convert';
import 'web_video_helper.dart' as webvideo;
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shimmer/shimmer.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'package:intl/intl.dart';
import 'package:video_player/video_player.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/error_handler.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../ads/services/ad_service.dart';
import '../../../../core/theme/theme_x.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────
const _kOrange = AppColors.primary;
const _kNavy   = AppColors.secondary;
const _kBg     = Color(0xFFF5F7FA);
const _kCard   = Colors.white;
const _kMuted  = Color(0xFF8A8A9A);

// ─────────────────────────────────────────────────────────────────────────────
// Providers
// ─────────────────────────────────────────────────────────────────────────────
final _svc = ModuleApiService.create();
final _districtsProvider = FutureProvider((_) => _svc.getRentDistricts());
// Key is a JSON-encoded string so Riverpod equality works correctly
// (Maps don't implement == by value, causing infinite provider misses)
final _propertiesProvider = FutureProvider.family<dynamic, String>(
  (_, key) {
    final p = jsonDecode(key) as Map<String, dynamic>;
    return _svc.getProperties(
      districtId: p['district_id'] as int?,
      type: p['type'] as String?,
      minPrice: p['min_price'] != null ? (p['min_price'] as num).toDouble() : null,
      maxPrice: p['max_price'] != null ? (p['max_price'] as num).toDouble() : null,
      bedrooms: p['bedrooms'] as int?,
    );
  },
);
final _propertyProvider = FutureProvider.family<dynamic, int>(
  (_, id) => _svc.getProperty(id),
);
final _myBookingsProvider = FutureProvider((_) => _svc.getRentMyBookings());

// ─────────────────────────────────────────────────────────────────────────────
// Main Screen
// ─────────────────────────────────────────────────────────────────────────────
class ERentScreen extends ConsumerStatefulWidget {
  const ERentScreen({super.key});
  @override
  ConsumerState<ERentScreen> createState() => _ERentScreenState();
}

class _ERentScreenState extends ConsumerState<ERentScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() { super.initState();
    AdService.instance.triggerModulePopups(context, 'erent'); _tab = TabController(length: 2, vsync: this); }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
            body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            pinned: true, expandedHeight: 0,
            backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
            title: const Row(children: [
              Icon(Icons.home_rounded, size: 20, color: _kOrange),
              SizedBox(width: 8),
              Text('eRent', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 20)),
            ]),
            bottom: PreferredSize(
              preferredSize: const Size.fromHeight(48),
              child: Container(
                color: context.colors.navyText,
                child: TabBar(
                  controller: _tab,
                  indicatorColor: _kOrange, indicatorWeight: 3,
                  labelColor: _kOrange, unselectedLabelColor: Colors.white60,
                  labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                  tabs: const [Tab(text: 'Browse'), Tab(text: 'My Bookings')],
                ),
              ),
            ),
          ),
        ],
        body: TabBarView(
          controller: _tab,
          children: [_BrowseTab(), _MyBookingsTab()],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// BROWSE TAB — Districts 4x4 grid
// ─────────────────────────────────────────────────────────────────────────────
class _BrowseTab extends ConsumerStatefulWidget {
  @override
  ConsumerState<_BrowseTab> createState() => _BrowseTabState();
}

class _BrowseTabState extends ConsumerState<_BrowseTab> {
  int? _districtId;
  String? _type;
  int? _bedrooms;
  int _priceIdx = 0;

  static final _priceRanges = <Map<String, dynamic>>[
    {'label': 'Any Price', 'min': null, 'max': null},
    {'label': r'$0–300', 'min': 0.0, 'max': 300.0},
    {'label': r'$300–600', 'min': 300.0, 'max': 600.0},
    {'label': r'$600–1000', 'min': 600.0, 'max': 1000.0},
    {'label': r'$1000+', 'min': 1000.0, 'max': null},
  ];
  static const _typeOptions = <String>['apartment', 'house', 'villa', 'room', 'office', 'studio'];
  static const _bedroomOptions = <int>[1, 2, 3, 4, 5];

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_districtsProvider);
    return async.when(
      loading: () => _buildShimmer(),
      error: (e, _) => _ErrorState(message: AppErrorHandler.message(e), onRetry: () => ref.invalidate(_districtsProvider)),
      data: (data) {
        final districts = List<Map>.from(data is Map ? (data['data'] ?? []) : []);
        if (districts.isEmpty) return const _EmptyState(
          icon: Icons.location_city_rounded, title: 'No Districts',
          subtitle: 'Districts will appear here');
        return CustomScrollView(slivers: [
          SliverToBoxAdapter(child: _RentHeader()),
          SliverToBoxAdapter(child: _SearchSection(
            districts: districts,
            districtId: _districtId,
            type: _type,
            bedrooms: _bedrooms,
            priceIdx: _priceIdx,
            priceRanges: _priceRanges,
            typeOptions: _typeOptions,
            bedroomOptions: _bedroomOptions,
            onDistrictChanged: (v) => setState(() => _districtId = v),
            onTypeChanged: (v) => setState(() => _type = v),
            onBedroomsChanged: (v) => setState(() => _bedrooms = v),
            onPriceChanged: (v) => setState(() => _priceIdx = v),
            onSearch: () {
              final pr = _priceRanges[_priceIdx];
              final district = _districtId != null
                  ? districts.firstWhere(
                      (d) => d['id'].toString() == _districtId.toString(),
                      orElse: () => {'id': null, 'name': 'All Districts'})
                  : {'id': null, 'name': 'All Districts'};
              Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(
                builder: (_) => _PropertyListScreen(
                  district: district,
                  initialType: _type,
                  initialBedrooms: _bedrooms,
                  initialMinPrice: pr['min'] as double?,
                  initialMaxPrice: pr['max'] as double?,
                ),
              ));
            },
          )),
          SliverToBoxAdapter(child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Row(children: [
              Container(width: 4, height: 18,
                  decoration: BoxDecoration(color: _kOrange, borderRadius: BorderRadius.circular(2))),
              SizedBox(width: 8),
              Text('Browse by District',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText)),
            ]),
          )),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            sliver: SliverGrid(
              delegate: SliverChildBuilderDelegate(
                (_, i) => _DistrictCard(district: districts[i]),
                childCount: districts.length,
              ),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 4, childAspectRatio: 1.0,
                crossAxisSpacing: 8, mainAxisSpacing: 8,
              ),
            ),
          ),
          const SliverToBoxAdapter(child: SizedBox(height: 32)),
        ]);
      },
    );
  }

  Widget _buildShimmer() => Padding(
    padding: const EdgeInsets.all(16),
    child: GridView.builder(
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4, childAspectRatio: 1.0, crossAxisSpacing: 8, mainAxisSpacing: 8),
      itemCount: 8,
      itemBuilder: (_, __) => Shimmer.fromColors(
        baseColor: Colors.grey.shade200, highlightColor: Colors.grey.shade50,
        child: Container(decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14))),
      ),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SEARCH SECTION — 4 filter cards (2×2) + Search button
// ─────────────────────────────────────────────────────────────────────────────
class _SearchSection extends StatelessWidget {
  final List<Map> districts;
  final int? districtId;
  final String? type;
  final int? bedrooms;
  final int priceIdx;
  final List<Map<String, dynamic>> priceRanges;
  final List<String> typeOptions;
  final List<int> bedroomOptions;
  final ValueChanged<int?> onDistrictChanged;
  final ValueChanged<String?> onTypeChanged;
  final ValueChanged<int?> onBedroomsChanged;
  final ValueChanged<int> onPriceChanged;
  final VoidCallback onSearch;

  const _SearchSection({
    required this.districts, required this.districtId, required this.type,
    required this.bedrooms, required this.priceIdx, required this.priceRanges,
    required this.typeOptions, required this.bedroomOptions,
    required this.onDistrictChanged, required this.onTypeChanged,
    required this.onBedroomsChanged, required this.onPriceChanged, required this.onSearch,
  });

  String _cap(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);

  @override
  Widget build(BuildContext context) {
    final districtName = districtId != null
        ? (districts.firstWhere(
              (d) => d['id'].toString() == districtId.toString(),
              orElse: () => {'name': 'Any'})['name'] ?? 'Any').toString()
        : 'Any District';

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 16, offset: const Offset(0, 4))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 4, height: 16,
              decoration: BoxDecoration(color: _kOrange, borderRadius: BorderRadius.circular(2))),
          SizedBox(width: 8),
          Text('Search Properties', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: context.colors.navyText)),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: _FilterCard(
            icon: Icons.location_on_rounded, label: 'Location', value: districtName,
            color: _kOrange, onTap: () => _showDistrictSheet(context),
          )),
          const SizedBox(width: 10),
          Expanded(child: _FilterCard(
            icon: Icons.home_work_rounded, label: 'Type',
            value: type != null ? _cap(type!) : 'Any Type',
            color: const Color(0xFF1565C0), onTap: () => _showTypeSheet(context),
          )),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: _FilterCard(
            icon: Icons.bed_rounded, label: 'Unit Type',
            value: bedrooms != null ? '$bedrooms Bedroom${bedrooms! > 1 ? 's' : ''}' : 'Any',
            color: const Color(0xFF2E7D32), onTap: () => _showBedroomsSheet(context),
          )),
          const SizedBox(width: 10),
          Expanded(child: _FilterCard(
            icon: Icons.attach_money_rounded, label: 'Price',
            value: priceRanges[priceIdx]['label'] as String,
            color: const Color(0xFF6A1B9A), onTap: () => _showPriceSheet(context),
          )),
        ]),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: onSearch,
            icon: const Icon(Icons.search_rounded, size: 18),
            label: const Text('Search Properties', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            style: ElevatedButton.styleFrom(
              backgroundColor: _kOrange, foregroundColor: Colors.white,
              minimumSize: const Size(0, 50),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              elevation: 0,
            ),
          ),
        ),
      ]),
    );
  }

  void _showDistrictSheet(BuildContext context) {
    showModalBottomSheet(
      context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => _BottomSheetPicker(
        title: 'Select Location',
        items: [
          {'label': 'Any District', 'value': null},
          ...districts.map((d) => {'label': (d['name'] ?? '').toString(), 'value': d['id']}),
        ],
        selectedValue: districtId,
        onSelected: (v) { Navigator.pop(context); onDistrictChanged(v as int?); },
      ),
    );
  }

  void _showTypeSheet(BuildContext context) {
    showModalBottomSheet(
      context: context, backgroundColor: Colors.transparent,
      builder: (_) => _BottomSheetPicker(
        title: 'Property Type',
        items: [
          {'label': 'Any Type', 'value': null},
          ...typeOptions.map((t) => {'label': _cap(t), 'value': t}),
        ],
        selectedValue: type,
        onSelected: (v) { Navigator.pop(context); onTypeChanged(v as String?); },
      ),
    );
  }

  void _showBedroomsSheet(BuildContext context) {
    showModalBottomSheet(
      context: context, backgroundColor: Colors.transparent,
      builder: (_) => _BottomSheetPicker(
        title: 'Unit Type (Bedrooms)',
        items: [
          {'label': 'Any', 'value': null},
          ...bedroomOptions.map((b) => {'label': '$b Bedroom${b > 1 ? 's' : ''}', 'value': b}),
        ],
        selectedValue: bedrooms,
        onSelected: (v) { Navigator.pop(context); onBedroomsChanged(v as int?); },
      ),
    );
  }

  void _showPriceSheet(BuildContext context) {
    showModalBottomSheet(
      context: context, backgroundColor: Colors.transparent,
      builder: (_) => _BottomSheetPicker(
        title: 'Price Range',
        items: List.generate(priceRanges.length,
            (i) => {'label': priceRanges[i]['label'] as String, 'value': i}),
        selectedValue: priceIdx,
        onSelected: (v) { Navigator.pop(context); onPriceChanged(v as int); },
      ),
    );
  }
}

class _FilterCard extends StatelessWidget {
  final IconData icon;
  final String label, value;
  final Color color;
  final VoidCallback onTap;
  const _FilterCard({required this.icon, required this.label, required this.value,
      required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 28, height: 28,
            decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
            child: Icon(icon, size: 15, color: color),
          ),
          const Spacer(),
          Icon(Icons.keyboard_arrow_down_rounded, size: 16, color: color),
        ]),
        const SizedBox(height: 8),
        Text(label, style: const TextStyle(fontSize: 10, color: _kMuted, fontWeight: FontWeight.w600)),
        const SizedBox(height: 2),
        Text(value, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: color),
            maxLines: 1, overflow: TextOverflow.ellipsis),
      ]),
    ),
  );
}

class _BottomSheetPicker extends StatelessWidget {
  final String title;
  final List<Map<String, dynamic>> items;
  final dynamic selectedValue;
  final ValueChanged<dynamic> onSelected;
  const _BottomSheetPicker({required this.title, required this.items,
      required this.selectedValue, required this.onSelected});

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
    decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(24)),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      const SizedBox(height: 12),
      Container(width: 40, height: 4,
          decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
      SizedBox(height: 16),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Align(
          alignment: Alignment.centerLeft,
          child: Text(title, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText)),
        ),
      ),
      const SizedBox(height: 8),
      ...items.map((item) {
        final isSelected = item['value'] == selectedValue;
        return ListTile(
          onTap: () => onSelected(item['value']),
          leading: isSelected
              ? const Icon(Icons.check_circle_rounded, color: _kOrange)
              : Icon(Icons.circle_outlined, color: Colors.grey.shade400, size: 20),
          title: Text(item['label'] as String,
              style: TextStyle(
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w500,
                color: isSelected ? _kOrange : context.colors.navyText,
              )),
        );
      }),
      const SizedBox(height: 20),
    ]),
  );
}

class _RentHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.all(16),
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [_kNavy, Color(0xFF1a3a5c)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [BoxShadow(color: context.colors.navyText.withValues(alpha: 0.3), blurRadius: 20, offset: const Offset(0, 8))],
    ),
    child: Row(children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Find Your Home', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Text('Browse properties by district', style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 13)),
        const SizedBox(height: 12),
        Row(children: [
          _HeaderBadge(icon: Icons.home_outlined, label: 'Full Rent'),
          const SizedBox(width: 8),
          _HeaderBadge(icon: Icons.lock_clock_outlined, label: 'Carbuun'),
        ]),
      ])),
      const SizedBox(width: 12),
      const Icon(Icons.holiday_village_rounded, color: Colors.white38, size: 60),
    ]),
  );
}

class _HeaderBadge extends StatelessWidget {
  final IconData icon;
  final String label;
  const _HeaderBadge({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 12, color: context.colors.cardBg),
      const SizedBox(width: 4),
      Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
    ]),
  );
}

class _DistrictCard extends StatelessWidget {
  final Map district;
  const _DistrictCard({required this.district});

  @override
  Widget build(BuildContext context) {
    final name = district['name'] ?? '';
    return GestureDetector(
      onTap: () => Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(
        builder: (_) => _PropertyListScreen(district: district))),
      child: Container(
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.07),
              blurRadius: 8, offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 36, height: 36,
              decoration: BoxDecoration(
                color: _kOrange.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.location_on_rounded, color: _kOrange, size: 20),
            ),
            const SizedBox(height: 6),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Text(
                name,
                style: TextStyle(
                  color: context.colors.navyText,
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                ),
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// PROPERTY LIST SCREEN
// ─────────────────────────────────────────────────────────────────────────────
class _PropertyListScreen extends ConsumerStatefulWidget {
  final Map district;
  final String? initialType;
  final int? initialBedrooms;
  final double? initialMinPrice;
  final double? initialMaxPrice;
  const _PropertyListScreen({
    required this.district,
    this.initialType,
    this.initialBedrooms,
    this.initialMinPrice,
    this.initialMaxPrice,
  });
  @override
  ConsumerState<_PropertyListScreen> createState() => _PropertyListScreenState();
}

class _PropertyListScreenState extends ConsumerState<_PropertyListScreen> {
  String? _typeFilter;
  int? _bedroomsFilter;
  double? _minPriceFilter;
  double? _maxPriceFilter;
  final _filterTypes = ['apartment', 'villa', 'room', 'office', 'studio'];

  @override
  void initState() {
    super.initState();
    _typeFilter     = widget.initialType;
    _bedroomsFilter = widget.initialBedrooms;
    _minPriceFilter = widget.initialMinPrice;
    _maxPriceFilter = widget.initialMaxPrice;
  }

  // Use a stable String key so Riverpod family equality works correctly
  // (Maps don't implement == by value — new Map each build = provider never matches)
  String get _paramsKey => jsonEncode({
    if (widget.district['id'] != null) 'district_id': int.parse(widget.district['id'].toString()),
    'type': _typeFilter,
    if (_bedroomsFilter != null) 'bedrooms': _bedroomsFilter,
    if (_minPriceFilter != null) 'min_price': _minPriceFilter,
    if (_maxPriceFilter != null) 'max_price': _maxPriceFilter,
  });

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_propertiesProvider(_paramsKey));
    return Scaffold(
            appBar: AppBar(
        backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
        title: Text(widget.district['name'] ?? '',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: Container(
            height: 52, color: context.colors.navyText,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              itemCount: _filterTypes.length + 1,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final isAll = i == 0;
                final type = isAll ? null : _filterTypes[i - 1];
                final selected = _typeFilter == type;
                return GestureDetector(
                  onTap: () => setState(() => _typeFilter = type),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                    decoration: BoxDecoration(
                      color: selected ? _kOrange : Colors.white.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(isAll ? 'All' : _typeLabel(type!),
                        style: TextStyle(color: Colors.white, fontWeight: selected ? FontWeight.w800 : FontWeight.w500, fontSize: 12)),
                  ),
                );
              },
            ),
          ),
        ),
      ),
      body: async.when(
        loading: () => ListView(children: List.generate(3, (_) => _Skeleton(height: 280))),
        error: (e, _) => _ErrorState(message: AppErrorHandler.message(e), onRetry: () => ref.invalidate(_propertiesProvider(_paramsKey))),
        data: (data) {
          final props = List<Map>.from(data is Map ? (data['data'] ?? []) : []);
          if (props.isEmpty) return _EmptyState(
            icon: Icons.home_work_outlined,
            title: 'No Properties',
            subtitle: 'No properties available in ${widget.district['name']}');
          return GridView.builder(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              childAspectRatio: 0.60,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
            ),
            itemCount: props.length,
            itemBuilder: (_, i) => _PropertyGridCard(
              property: props[i],
              onTap: () => Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(
                builder: (_) => PropertyDetailScreen(propertyId: int.parse(props[i]['id'].toString())))),
            ),
          );
        },
      ),
    );
  }

  String _typeLabel(String t) {
    const m = {'apartment': 'Apt', 'villa': 'Villa', 'room': 'Room', 'office': 'Office', 'studio': 'Studio'};
    return m[t] ?? t[0].toUpperCase() + t.substring(1);
  }
}

// ─── Compact 3-col grid card ────────────────────────────────────────────────
class _PropertyGridCard extends StatelessWidget {
  final Map property;
  final VoidCallback onTap;
  const _PropertyGridCard({required this.property, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final images = List<String>.from(property['images'] ?? []);
    final isBooked = property['is_booked'] == true;
    final type = (property['type'] ?? '').toString();
    final rent = (property['monthly_rent'] as num?)?.toStringAsFixed(0) ?? '0';

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Image
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: AspectRatio(
                aspectRatio: 1.0,
                child: images.isNotEmpty
                    ? NetImage(url: images.first, fit: BoxFit.cover,
                        errorWidget: Container(color: Colors.grey.shade100,
                            child: const Icon(Icons.home_outlined, color: Colors.grey)))
                    : Container(color: Colors.grey.shade100,
                        child: Icon(Icons.home_outlined, color: Colors.grey)),
              ),
            ),
            // Type badge
            Positioned(top: 6, left: 6, child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: BoxDecoration(color: context.colors.navyText.withValues(alpha: 0.85), borderRadius: BorderRadius.circular(6)),
              child: Text(type.length > 3 ? type.substring(0, 3).toUpperCase() : type.toUpperCase(),
                  style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
            )),
            // Availability dot
            Positioned(top: 6, right: 6, child: Container(
              width: 8, height: 8,
              decoration: BoxDecoration(
                color: isBooked ? Colors.orange : Colors.green,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 1.5),
              ),
            )),
          ]),
          // Info
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 7, 8, 7),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text(property['title'] ?? '',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText),
                    maxLines: 1, overflow: TextOverflow.ellipsis),
                Row(children: [
                  const Icon(Icons.bed_rounded, size: 12, color: _kMuted),
                  const SizedBox(width: 3),
                  Text('${property['bedrooms'] ?? 0}', style: const TextStyle(fontSize: 11, color: _kMuted)),
                  const SizedBox(width: 8),
                  const Icon(Icons.bathtub_rounded, size: 12, color: _kMuted),
                  const SizedBox(width: 3),
                  Text('${property['bathrooms'] ?? 0}', style: const TextStyle(fontSize: 11, color: _kMuted)),
                ]),
                Text('\$$rent/mo',
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: _kOrange)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}



// ─────────────────────────────────────────────────────────────────────────────
// PROPERTY DETAIL SCREEN
// ─────────────────────────────────────────────────────────────────────────────
class PropertyDetailScreen extends ConsumerStatefulWidget {
  final int propertyId;
  const PropertyDetailScreen({required this.propertyId});
  @override
  ConsumerState<PropertyDetailScreen> createState() => PropertyDetailScreenState();
}

class PropertyDetailScreenState extends ConsumerState<PropertyDetailScreen> {
  int _imgIndex = 0;

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_propertyProvider(widget.propertyId));
    return async.when(
      loading: () => Scaffold(backgroundColor: context.colors.scaffoldBg, body: Center(child: CircularProgressIndicator(color: _kOrange))),
      error: (e, _) => Scaffold(appBar: AppBar(backgroundColor: context.colors.navyText, foregroundColor: Colors.white),
          body: _ErrorState(message: AppErrorHandler.message(e), onRetry: () => ref.invalidate(_propertyProvider(widget.propertyId)))),
      data: (data) {
        final p = Map<String, dynamic>.from(data is Map ? (data['data'] ?? data) : {});
        final images = List<String>.from(p['images'] ?? []);
        final reels = List<String>.from(p['reels'] ?? []);
        final amenities = List.from(p['amenities'] ?? []);
        final isBooked = p['is_booked'] == true;

        return Scaffold(
                    body: CustomScrollView(
            slivers: [
              // Back button appbar (thin, transparent)
              SliverAppBar(
                pinned: true, expandedHeight: 0,
                backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
                title: Text(p['title'] ?? '',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15),
                    maxLines: 1, overflow: TextOverflow.ellipsis),
              ),

              // Main image + thumbnail strip
              SliverToBoxAdapter(
                child: Column(children: [
                  // ── Main image ──────────────────────────────────────────
                  GestureDetector(
                    onHorizontalDragEnd: (d) {
                      if (d.primaryVelocity! < 0 && _imgIndex < images.length - 1) setState(() => _imgIndex++);
                      else if (d.primaryVelocity! > 0 && _imgIndex > 0) setState(() => _imgIndex--);
                    },
                    child: Stack(children: [
                      SizedBox(
                        height: 260, width: double.infinity,
                        child: images.isNotEmpty
                            ? NetImage(url: images[_imgIndex], fit: BoxFit.cover,
                                errorWidget: Container(color: Colors.grey.shade300,
                                    child: const Icon(Icons.home_rounded, size: 80, color: Colors.grey)))
                            : Container(color: Colors.grey.shade300,
                                child: const Icon(Icons.home_rounded, size: 80, color: Colors.grey)),
                      ),
                      // Counter badge
                      if (images.length > 1)
                        Positioned(bottom: 12, right: 12, child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(16)),
                          child: Text('${_imgIndex + 1}/${images.length}',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11)),
                        )),
                    ]),
                  ),
                  // ── Thumbnail strip ─────────────────────────────────────
                  if (images.length > 1)
                    Container(
                      height: 72,
                      color: context.colors.cardBg,
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: images.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 8),
                        itemBuilder: (_, i) => GestureDetector(
                          onTap: () => setState(() => _imgIndex = i),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 180),
                            width: 56, height: 56,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(
                                color: _imgIndex == i ? _kOrange : Colors.transparent,
                                width: 2.5,
                              ),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: NetImage(url: images[i], fit: BoxFit.cover,
                                  errorWidget: Container(color: Colors.grey.shade200)),
                            ),
                          ),
                        ),
                      ),
                    ),
                ]),
              ),

              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    // Title & status
                    Row(children: [
                      Expanded(child: Text(p['title'] ?? '',
                          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: context.colors.navyText))),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: isBooked ? Colors.orange.shade100 : Colors.green.shade50,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(isBooked ? 'Reserved' : 'Available',
                            style: TextStyle(
                              color: isBooked ? Colors.orange.shade800 : Colors.green.shade800,
                              fontWeight: FontWeight.w800, fontSize: 12)),
                      ),
                    ]),
                    const SizedBox(height: 6),
                    Row(children: [
                      const Icon(Icons.location_on_rounded, size: 16, color: _kMuted),
                      const SizedBox(width: 4),
                      Text('${p['district_name'] ?? ''} · ${p['address'] ?? ''}',
                          style: const TextStyle(color: _kMuted, fontSize: 13)),
                    ]),

                    SizedBox(height: 20),

                    // Room grid
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
                          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)]),
                      child: Column(children: [
                        Row(children: [
                          _RoomStat(icon: Icons.bed_rounded, value: '${p['bedrooms'] ?? 0}', label: 'Bedrooms', color: _kOrange),
                          _RoomStat(icon: Icons.bathtub_rounded, value: '${p['bathrooms'] ?? 0}', label: 'Bathrooms', color: Colors.blue),
                          _RoomStat(icon: Icons.kitchen_rounded, value: '${p['kitchens'] ?? 0}', label: 'Kitchens', color: Colors.green),
                          _RoomStat(icon: Icons.weekend_rounded, value: '${p['living_rooms'] ?? 0}', label: 'Living', color: Colors.purple),
                        ]),
                        const Divider(height: 20),
                        Row(children: [
                          _RoomStat(icon: Icons.layers_rounded, value: '${p['floor'] ?? '-'}', label: 'Floor', color: _kMuted),
                          _RoomStat(icon: Icons.chair_rounded, value: (p['furnishing'] ?? 'Unfurnished').toString().substring(0, 5), label: 'Furnished', color: _kOrange),
                          _RoomStat(icon: Icons.home_rounded, value: (p['type'] ?? '').toString().toUpperCase().substring(0, (p['type']?.toString() ?? '').length.clamp(0, 4)), label: 'Type', color: context.colors.navyText),
                          _RoomStat(icon: Icons.calendar_today_rounded, value: '${p['year_built'] ?? '-'}', label: 'Built', color: Colors.teal),
                        ]),
                      ]),
                    ),

                    const SizedBox(height: 20),

                    // Reels
                    if (reels.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('🎬 Reels',
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                            GestureDetector(
                              onTap: () => Navigator.of(context, rootNavigator: true).push(
                                MaterialPageRoute(builder: (_) => _ReelPlayerScreen(
                                  reels: reels, initialIndex: 0, property: p))),
                              child: const Text('View All',
                                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _kOrange)),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 8),
                      _ReelsRow(reels: reels, property: p),
                      const SizedBox(height: 20),
                    ],

                    // Description
                    if (p['description'] != null && p['description'].toString().isNotEmpty) ...[
                      const _SectionTitle(title: 'Description'),
                      const SizedBox(height: 8),
                      Text(p['description'], style: const TextStyle(color: _kMuted, height: 1.6, fontSize: 14)),
                      const SizedBox(height: 20),
                    ],

                    // Amenities
                    if (amenities.isNotEmpty) ...[
                      const _SectionTitle(title: 'Amenities'),
                      const SizedBox(height: 10),
                      Wrap(spacing: 8, runSpacing: 8, children: amenities.map((a) => Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: _kOrange.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(Icons.check_circle_rounded, size: 14, color: _kOrange),
                          SizedBox(width: 6),
                          Text(a.toString(), style: TextStyle(fontSize: 12, color: context.colors.navyText, fontWeight: FontWeight.w600)),
                        ]),
                      )).toList()),
                      const SizedBox(height: 20),
                    ],

                    // Pricing
                    const _SectionTitle(title: 'Pricing'),
                    SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
                          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)]),
                      child: Column(children: [
                        _PriceRow(label: 'Monthly Rent', value: '\$${(p['monthly_rent'] as num?)?.toStringAsFixed(2) ?? '0'}'),
                        if ((p['deposit'] as num? ?? 0) > 0)
                          _PriceRow(label: 'Security Deposit', value: '\$${(p['deposit'] as num).toStringAsFixed(2)}'),
                        if ((p['brokerage_fee'] as num? ?? 0) > 0)
                          _PriceRow(label: 'Brokerage Fee', value: '\$${(p['brokerage_fee'] as num).toStringAsFixed(2)}'),
                        const Divider(height: 20),
                        _PriceRow(label: 'Full Rent Total',
                            value: '\$${(p['full_rent_total'] as num?)?.toStringAsFixed(2) ?? '0'}',
                            bold: true, color: context.colors.navyText),
                        _PriceRow(label: 'Carbuun (30% Deposit)',
                            value: '\$${(p['carbuun_total'] as num?)?.toStringAsFixed(2) ?? '0'}',
                            bold: true, color: _kOrange),
                      ]),
                    ),

                    const SizedBox(height: 20),

                    // Carbuun info card
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: _kOrange.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: _kOrange.withValues(alpha: 0.2)),
                      ),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          const Icon(Icons.info_outline_rounded, color: _kOrange, size: 16),
                          const SizedBox(width: 8),
                          const Text('About Carbuun', style: TextStyle(fontWeight: FontWeight.w800, color: _kOrange)),
                        ]),
                        const SizedBox(height: 8),
                        const Text(
                          'Pay 30% now to reserve this property. Visit, approve, then pay the remaining 70% to activate your rental.',
                          style: TextStyle(color: _kMuted, fontSize: 12, height: 1.5),
                        ),
                      ]),
                    ),

                    const SizedBox(height: 100),
                  ]),
                ),
              ),
            ],
          ),
          bottomNavigationBar: isBooked
              ? Container(
                  padding: const EdgeInsets.all(16), color: context.colors.cardBg,
                  child: Text('⚠️ This property is currently reserved',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.orange, fontWeight: FontWeight.w700, fontSize: 15)))
              : Container(
                  padding: const EdgeInsets.all(16), color: context.colors.cardBg,
                  child: Row(children: [
                    Expanded(child: ElevatedButton(
                      onPressed: () => _showBookSheet(context, p, false),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _kOrange, minimumSize: const Size(0, 52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        const Text('Carbuun', style: TextStyle(fontWeight: FontWeight.w800)),
                        Text('\$${(p['carbuun_total'] as num?)?.toStringAsFixed(0) ?? '0'} · 30%',
                            style: const TextStyle(fontSize: 11)),
                      ]),
                    )),
                    SizedBox(width: 10),
                    Expanded(child: ElevatedButton(
                      onPressed: () => _showBookSheet(context, p, true),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: context.colors.navyText, minimumSize: const Size(0, 52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        const Text('Full Rent', style: TextStyle(fontWeight: FontWeight.w800)),
                        Text('\$${(p['full_rent_total'] as num?)?.toStringAsFixed(0) ?? '0'}',
                            style: const TextStyle(fontSize: 11)),
                      ]),
                    )),
                  ]),
                ),
        );
      },
    );
  }

  void _showBookSheet(BuildContext context, Map property, bool isFullRent) {
    Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(
      builder: (_) => _BookingScreen(
        property: property,
        bookingType: isFullRent ? 'full_rent' : 'carbuun',
      ),
    ));
  }
}

class _RoomStat extends StatelessWidget {
  final IconData icon;
  final String value, label;
  final Color color;
  const _RoomStat({required this.icon, required this.value, required this.label, required this.color});

  @override
  Widget build(BuildContext context) => Expanded(child: Column(children: [
    Container(width: 40, height: 40,
        decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 20)),
    SizedBox(height: 6),
    Text(value, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
    Text(label, style: const TextStyle(fontSize: 10, color: _kMuted)),
  ]));
}

class _SectionTitle extends StatelessWidget {
  final String title;
  const _SectionTitle({required this.title});

  @override
  Widget build(BuildContext context) => Row(children: [
    Container(width: 4, height: 18, decoration: BoxDecoration(color: _kOrange, borderRadius: BorderRadius.circular(2))),
    SizedBox(width: 8),
    Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText)),
  ]);
}

class _PriceRow extends StatelessWidget {
  final String label, value;
  final bool bold;
  final Color? color;
  const _PriceRow({required this.label, required this.value, this.bold = false, this.color});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: bold ? context.colors.navyText : _kMuted, fontWeight: bold ? FontWeight.w600 : FontWeight.w400)),
      Text(value, style: TextStyle(fontWeight: bold ? FontWeight.w900 : FontWeight.w600, color: color ?? context.colors.navyText)),
    ]),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// BOOKING SCREEN  (full screen — avoids dialog context/Riverpod issues)
// ─────────────────────────────────────────────────────────────────────────────
class _BookingScreen extends ConsumerStatefulWidget {
  final Map property;
  final String bookingType;
  const _BookingScreen({required this.property, required this.bookingType});
  @override
  ConsumerState<_BookingScreen> createState() => _BookingScreenState();
}

class _BookingScreenState extends ConsumerState<_BookingScreen> {
  DateTime? _moveInDate;
  int _duration = 1;
  String _payMethod = 'wallet';
  String? _waafiReference;
  bool _loading = false;
  bool _done = false;
  String _orderRef = '';
  final _noteCtrl = TextEditingController();

  @override
  void dispose() { _noteCtrl.dispose(); super.dispose(); }

  bool get _isCarbuun => widget.bookingType == 'carbuun';
  double get _amountDue => _isCarbuun
      ? (widget.property['carbuun_total'] as num?)?.toDouble() ?? 0
      : (widget.property['full_rent_total'] as num?)?.toDouble() ?? 0;
  Color get _accentColor => _isCarbuun ? _kOrange : _kNavy;

  Future<void> _book() async {
    if (_moveInDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a move-in date'), backgroundColor: Colors.red));
      return;
    }

    if (_payMethod == 'mobile_pay' || _payMethod == 'waafi_pay') {
      if (_payMethod == 'mobile_pay') {
        final result = await showMobilePaySheet(context, amount: _amountDue, description: 'eRent: ${widget.property['title'] ?? ''}');
        if (result?.success != true) return;
        _waafiReference = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
      } else {
        final result = await showWaafiPaySheet(
          context,
          amount: _amountDue,
          type: 'order',
          description: 'eRent: ${widget.property['title'] ?? ''}',
        );
        if (result?.success != true) return;
        _waafiReference = result!.reference;
      }
    }

    if (_payMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _loading = true);
    try {
      final svc = ModuleApiService.create();
      final result = await svc.bookProperty({
        'property_id': widget.property['id'],
        'booking_type': widget.bookingType,
        'move_in_date': DateFormat('yyyy-MM-dd').format(_moveInDate!),
        'duration_months': 1,
        'payment_method': _payMethod,
        'note': _noteCtrl.text.trim(),
        if (_waafiReference != null) 'payment_reference': _waafiReference,
      });
      ref.invalidate(_myBookingsProvider);
      if (_payMethod == 'wallet') ref.invalidate(walletProvider);
      if (mounted) {
        final ref2 = result is Map ? (result['data']?['order_number'] ?? '') : '';
        setState(() { _loading = false; _done = true; _orderRef = ref2.toString(); });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppErrorHandler.message(e)), backgroundColor: Colors.red));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
            appBar: AppBar(
        backgroundColor: _accentColor,
        foregroundColor: Colors.white,
        title: Text(
          _isCarbuun ? 'Carbuun — 30% Deposit' : 'Full Rent Booking',
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        elevation: 0,
      ),
      body: _done ? _buildSuccess() : _buildForm(),
    );
  }

  // ── Success view ────────────────────────────────────────────────────────────
  Widget _buildSuccess() => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 90, height: 90,
          decoration: BoxDecoration(color: Colors.green.shade50, shape: BoxShape.circle),
          child: const Icon(Icons.check_circle_rounded, color: Colors.green, size: 54),
        ),
        SizedBox(height: 24),
        Text(
          _isCarbuun ? '🏠 Property Reserved!' : '✅ Booking Submitted!',
          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: context.colors.navyText),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        Text(
          _isCarbuun
              ? 'Deposit of \$${_amountDue.toStringAsFixed(2)} paid.\nOur agent will contact you soon.'
              : 'Booking submitted. Our agent will contact you shortly.',
          style: const TextStyle(color: _kMuted, fontSize: 14, height: 1.6),
          textAlign: TextAlign.center,
        ),
        if (_orderRef.isNotEmpty) ...[
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
            child: Text('Ref: $_orderRef',
                style: const TextStyle(color: _kOrange, fontWeight: FontWeight.w800, fontSize: 13)),
          ),
        ],
        const SizedBox(height: 32),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: () => Navigator.of(context).pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: _accentColor, foregroundColor: Colors.white,
              minimumSize: const Size(0, 52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: const Text('Back to Property', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ),
        ),
      ]),
    ),
  );

  // ── Booking form ────────────────────────────────────────────────────────────
  Widget _buildForm() => SingleChildScrollView(
    padding: const EdgeInsets.all(20),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Property summary card
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
        ),
        child: Row(children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _accentColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
            child: Icon(_isCarbuun ? Icons.lock_clock_rounded : Icons.home_rounded,
                color: _accentColor, size: 26),
          ),
          SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(widget.property['title'] ?? '',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText),
                maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Text(widget.property['district_name'] ?? '',
                style: const TextStyle(color: _kMuted, fontSize: 12)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('\$${_amountDue.toStringAsFixed(0)}',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: _accentColor)),
            Text(_isCarbuun ? '30% deposit' : 'total',
                style: const TextStyle(fontSize: 11, color: _kMuted)),
          ]),
        ]),
      ),

      if (_isCarbuun) ...[
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _kOrange.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _kOrange.withValues(alpha: 0.2)),
          ),
          child: Row(children: [
            const Icon(Icons.info_outline_rounded, color: _kOrange, size: 18),
            const SizedBox(width: 10),
            const Expanded(child: Text(
              'Pay 30% now to reserve. Visit the property, approve, then pay remaining 70% to activate.',
              style: TextStyle(color: _kMuted, fontSize: 12, height: 1.4),
            )),
          ]),
        ),
      ],

      const SizedBox(height: 20),
      const _SectionTitle(title: 'Booking Details'),
      const SizedBox(height: 12),

      // Move-in date
      GestureDetector(
        onTap: () async {
          final d = await showDatePicker(
            context: context,
            initialDate: DateTime.now().add(const Duration(days: 1)),
            firstDate: DateTime.now().add(const Duration(days: 1)),
            lastDate: DateTime.now().add(const Duration(days: 365)),
            builder: (ctx, child) => Theme(
              data: Theme.of(ctx).copyWith(
                colorScheme: const ColorScheme.light(primary: _kOrange)), child: child!),
          );
          if (d != null) setState(() => _moveInDate = d);
        },
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: _moveInDate != null ? _kOrange.withValues(alpha: 0.5) : Colors.grey.shade200,
              width: _moveInDate != null ? 1.5 : 1,
            ),
          ),
          child: Row(children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: (_moveInDate != null ? _kOrange : _kMuted).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(Icons.calendar_month_rounded,
                  color: _moveInDate != null ? _kOrange : _kMuted, size: 20),
            ),
            const SizedBox(width: 12),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('MOVE-IN DATE', style: TextStyle(fontSize: 10, color: _kMuted, fontWeight: FontWeight.w700)),
              const SizedBox(height: 2),
              Text(
                _moveInDate != null ? DateFormat('EEE, d MMM yyyy').format(_moveInDate!) : 'Tap to select date',
                style: TextStyle(
                  fontWeight: FontWeight.w600, fontSize: 14,
                  color: _moveInDate != null ? context.colors.navyText : Colors.grey.shade400),
              ),
            ]),
          ]),
        ),
      ),
      const SizedBox(height: 12),

      // Note
      TextField(
        controller: _noteCtrl,
        maxLines: 2,
        decoration: InputDecoration(
          hintText: 'Optional note for landlord...',
          prefixIcon: Icon(Icons.note_alt_outlined, color: _kMuted),
          filled: true, fillColor: context.colors.cardBg,
          border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide(color: Colors.grey.shade200)),
          enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide(color: Colors.grey.shade200)),
          focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: _kOrange)),
        ),
      ),
      const SizedBox(height: 20),

      const _SectionTitle(title: 'Payment Method'),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _PayBtn(
            label: 'Mobile Pay', icon: Icons.phone_in_talk_rounded,
            selected: _payMethod == 'mobile_pay', color: const Color(0xFF4CAF50),
            onTap: () => setState(() => _payMethod = 'mobile_pay'))),
        const SizedBox(width: 8),
        Expanded(child: _PayBtn(
            label: 'ePay', icon: Icons.account_balance_wallet_outlined,
            selected: _payMethod == 'wallet', color: _accentColor,
            onTap: () => setState(() => _payMethod = 'wallet'))),
        const SizedBox(width: 8),
        Expanded(child: _PayBtn(
            label: 'Waafi Pay', icon: Icons.phone_android_rounded,
            selected: _payMethod == 'waafi_pay', color: const Color(0xFFFF8A00),
            onTap: () => setState(() => _payMethod = 'waafi_pay'))),
      ]),
      SizedBox(height: 24),

      // Confirm button
      Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: context.colors.cardBg, borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 16, offset: const Offset(0, -4))],
        ),
        child: Column(children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(_isCarbuun ? 'Deposit (30%)' : 'Total Amount',
                style: const TextStyle(color: _kMuted, fontSize: 13)),
            Text('\$${_amountDue.toStringAsFixed(2)}',
                style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900, color: _accentColor)),
          ]),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _loading ? null : _book,
              style: ElevatedButton.styleFrom(
                backgroundColor: _accentColor, foregroundColor: Colors.white,
                minimumSize: const Size(0, 54),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
              child: _loading
                  ? const SizedBox(width: 22, height: 22,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : Text(
                      _isCarbuun ? '🔒  Reserve with 30% Deposit' : '🏠  Confirm Full Rent Booking',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            ),
          ),
        ]),
      ),
      const SizedBox(height: 40),
    ]),
  );
}

class _StepBtn extends StatelessWidget {
  final IconData icon;
  final bool enabled;
  final VoidCallback onTap;
  const _StepBtn({required this.icon, required this.enabled, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: enabled ? onTap : null,
    child: Container(
      width: 36, height: 36,
      decoration: BoxDecoration(
        color: enabled ? _kOrange.withValues(alpha: 0.1) : Colors.grey.shade100,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
            color: enabled ? _kOrange.withValues(alpha: 0.35) : Colors.grey.shade200),
      ),
      child: Icon(icon, size: 18,
          color: enabled ? _kOrange : Colors.grey.shade400),
    ),
  );
}

class _PayBtn extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool selected;
  final Color color;
  final VoidCallback onTap;
  const _PayBtn({required this.label, required this.icon, required this.selected, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        color: selected ? color : context.colors.cardBg, borderRadius: BorderRadius.circular(12),
        border: selected ? null : Border.all(color: Colors.grey.shade300),
      ),
      child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(icon, color: selected ? Colors.white : _kMuted, size: 18),
        const SizedBox(width: 6),
        Text(label, style: TextStyle(color: selected ? Colors.white : _kMuted, fontWeight: FontWeight.w700)),
      ]),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// MY BOOKINGS TAB
// ─────────────────────────────────────────────────────────────────────────────
class _MyBookingsTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_myBookingsProvider);
    return async.when(
      loading: () => ListView(children: List.generate(3, (_) => _Skeleton(height: 140))),
      error: (e, _) => _ErrorState(message: AppErrorHandler.message(e), onRetry: () => ref.invalidate(_myBookingsProvider)),
      data: (data) {
        final bookings = List<Map>.from(data is Map ? (data['data'] ?? []) : []);
        if (bookings.isEmpty) return const _EmptyState(
          icon: Icons.bookmark_border_rounded, title: 'No Bookings Yet',
          subtitle: 'Your rent bookings will appear here');
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: bookings.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (_, i) => _BookingCard(booking: bookings[i], ref: ref),
        );
      },
    );
  }
}

class _BookingCard extends StatelessWidget {
  final Map booking;
  final WidgetRef ref;
  const _BookingCard({required this.booking, required this.ref});

  @override
  Widget build(BuildContext context) {
    final isCarbuun = booking['booking_type'] == 'carbuun';
    final status = booking['status'] ?? 'pending';
    final statusColor = status == 'confirmed' ? Colors.green : status == 'cancelled' ? Colors.red : _kOrange;
    final remaining = (booking['amount_remaining'] as num?)?.toDouble() ?? 0;
    final paid = (booking['amount_paid'] as num?)?.toDouble() ?? 0;

    return Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg, borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 16, offset: const Offset(0, 4))],
      ),
      child: Column(children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isCarbuun ? _kOrange.withValues(alpha: 0.06) : _kNavy.withValues(alpha: 0.04),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Row(children: [
            Container(
              width: 44, height: 44,
              decoration: BoxDecoration(
                color: isCarbuun ? _kOrange.withValues(alpha: 0.1) : _kNavy.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(isCarbuun ? Icons.lock_clock_rounded : Icons.home_rounded,
                  color: isCarbuun ? _kOrange : _kNavy, size: 22),
            ),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(booking['property_title'] ?? '', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontSize: 15),
                  overflow: TextOverflow.ellipsis),
              Text(booking['district_name'] ?? '', style: const TextStyle(color: _kMuted, fontSize: 12)),
            ])),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
              child: Text(status.toUpperCase(), style: TextStyle(color: statusColor, fontWeight: FontWeight.w800, fontSize: 10)),
            ),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(children: [
            Row(children: [
              Expanded(child: _BookingDetail(label: 'Type', value: isCarbuun ? 'Carbuun 30%' : 'Full Rent')),
              Expanded(child: _BookingDetail(label: 'Move-in', value: booking['move_in_date'] ?? '')),
              Expanded(child: _BookingDetail(label: 'Duration', value: '${booking['duration_months'] ?? 1} mo')),
            ]),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: _BookingDetail(label: 'Paid', value: '\$${paid.toStringAsFixed(2)}')),
              Expanded(child: _BookingDetail(label: 'Remaining', value: '\$${remaining.toStringAsFixed(2)}')),
              Expanded(child: _BookingDetail(label: 'Monthly', value: '\$${(booking['monthly_rent'] as num?)?.toStringAsFixed(0) ?? '0'}')),
            ]),
            // Refund requested badge
            if (status == 'refund_requested') ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.orange.shade200),
                ),
                child: Row(children: [
                  const Icon(Icons.pending_actions_rounded, color: Colors.orange, size: 16),
                  const SizedBox(width: 8),
                  Text('Refund requested — awaiting admin review',
                      style: TextStyle(color: Colors.orange.shade800, fontSize: 12, fontWeight: FontWeight.w600)),
                ]),
              ),
            ],
            // Carbuun actions
            if (isCarbuun && status == 'pending' && remaining > 0) ...[
              const SizedBox(height: 12),
              Row(children: [
                Expanded(child: ElevatedButton.icon(
                  onPressed: () => Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(
                    builder: (_) => _PayRemainingScreen(booking: booking))),
                  icon: Icon(Icons.payment_rounded, size: 16),
                  label: Text('Pay Remaining'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
                    minimumSize: const Size(0, 42),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                )),
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  onPressed: () => _requestRefund(context, booking),
                  icon: const Icon(Icons.undo_rounded, size: 16),
                  label: const Text('Refund'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red,
                    side: const BorderSide(color: Colors.red),
                    minimumSize: const Size(100, 42),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                ),
              ]),
            ],
          ]),
        ),
      ]),
    );
  }

  void _requestRefund(BuildContext context, Map booking) async {
    final reasonCtrl = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (_) => StatefulBuilder(
        builder: (ctx, setDlgState) {
          final canSubmit = reasonCtrl.text.trim().length >= 5;
          return AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            title: Text('Request Refund', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText)),
            content: Column(mainAxisSize: MainAxisSize.min, children: [
              const Text('Please describe why you want to cancel and request a refund.',
                  style: TextStyle(color: _kMuted, fontSize: 13)),
              const SizedBox(height: 12),
              TextField(
                controller: reasonCtrl,
                maxLines: 3,
                onChanged: (_) => setDlgState(() {}),
                decoration: InputDecoration(
                  hintText: 'Reason for refund request...',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _kOrange, width: 2)),
                ),
              ),
            ]),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Back')),
              ElevatedButton(
                onPressed: canSubmit ? () => Navigator.pop(ctx, reasonCtrl.text.trim()) : null,
                style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
                child: const Text('Submit Request'),
              ),
            ],
          );
        },
      ),
    );
    if (reason == null || reason.isEmpty) return;
    try {
      final svc = ModuleApiService.create();
      await svc.requestRentRefund(int.parse(booking['id'].toString()), reason);
      ref.invalidate(_myBookingsProvider);
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Refund request submitted. Admin will review within 24 hours.'),
          backgroundColor: Colors.orange));
    } catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppErrorHandler.message(e)), backgroundColor: Colors.red));
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// PAY REMAINING SCREEN
// ─────────────────────────────────────────────────────────────────────────────
class _PayRemainingScreen extends ConsumerStatefulWidget {
  final Map booking;
  const _PayRemainingScreen({required this.booking});
  @override
  ConsumerState<_PayRemainingScreen> createState() => _PayRemainingScreenState();
}

class _PayRemainingScreenState extends ConsumerState<_PayRemainingScreen> {
  String _payMethod = 'wallet';
  bool _loading = false;
  bool _done = false;

  double get _remaining => (widget.booking['amount_remaining'] as num?)?.toDouble() ?? 0;
  double get _monthlyRent => (widget.booking['monthly_rent'] as num?)?.toDouble() ?? 0;

  Future<void> _pay() async {
    if (_payMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }
    setState(() => _loading = true);
    try {
      final svc = ModuleApiService.create();
      await svc.payRemainingRent(
          int.parse(widget.booking['id'].toString()), _payMethod);
      ref.invalidate(_myBookingsProvider);
      if (_payMethod == 'wallet') ref.invalidate(walletProvider);
      if (mounted) setState(() { _loading = false; _done = true; });
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(AppErrorHandler.message(e)), backgroundColor: Colors.red));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
            appBar: AppBar(
        backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
        title: const Text('Pay Remaining Balance',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
      ),
      body: _done ? _buildSuccess() : _buildForm(),
    );
  }

  Widget _buildSuccess() => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 90, height: 90,
          decoration: BoxDecoration(color: Colors.green.shade50, shape: BoxShape.circle),
          child: const Icon(Icons.check_circle_rounded, color: Colors.green, size: 54),
        ),
        SizedBox(height: 24),
        Text('🏠 Full Rental Confirmed!',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: context.colors.navyText),
            textAlign: TextAlign.center),
        const SizedBox(height: 12),
        Text('Remaining \$${_remaining.toStringAsFixed(2)} paid.\nWelcome to your new home!',
            style: const TextStyle(color: _kMuted, fontSize: 14, height: 1.6),
            textAlign: TextAlign.center),
        SizedBox(height: 32),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: () => Navigator.of(context).pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
              minimumSize: const Size(0, 52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: const Text('Back to Bookings', style: TextStyle(fontWeight: FontWeight.w800)),
          ),
        ),
      ]),
    ),
  );

  Widget _buildForm() => SingleChildScrollView(
    padding: const EdgeInsets.all(20),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Property card
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: context.colors.navyText.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(12)),
              child: Icon(Icons.home_rounded, color: context.colors.navyText, size: 24),
            ),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(widget.booking['property_title'] ?? '',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText),
                  maxLines: 1, overflow: TextOverflow.ellipsis),
              Text(widget.booking['district_name'] ?? '',
                  style: const TextStyle(color: _kMuted, fontSize: 12)),
            ])),
          ]),
          const SizedBox(height: 16),
          const Divider(height: 1),
          const SizedBox(height: 14),
          // Amount breakdown
          _BalanceRow(label: 'Already Paid (30%)',
              value: '\$${(widget.booking['amount_paid'] as num?)?.toStringAsFixed(2) ?? '0'}',
              color: Colors.green),
          SizedBox(height: 8),
          _BalanceRow(label: 'Remaining Balance (70%)',
              value: '\$${_remaining.toStringAsFixed(2)}',
              color: context.colors.navyText, bold: true, large: true),
          const SizedBox(height: 8),
          _BalanceRow(label: 'Monthly Rent',
              value: '\$${_monthlyRent.toStringAsFixed(2)}/mo', color: _kMuted),
        ]),
      ),
      const SizedBox(height: 20),
      const _SectionTitle(title: 'Payment Method'),
      SizedBox(height: 12),
      Row(children: [
        Expanded(child: _PayBtn(
            label: 'Mobile Pay', icon: Icons.phone_in_talk_rounded,
            selected: _payMethod == 'mobile_pay', color: const Color(0xFF4CAF50),
            onTap: () => setState(() => _payMethod = 'mobile_pay'))),
        const SizedBox(width: 8),
        Expanded(child: _PayBtn(
            label: 'ePay', icon: Icons.account_balance_wallet_outlined,
            selected: _payMethod == 'wallet', color: context.colors.navyText,
            onTap: () => setState(() => _payMethod = 'wallet'))),
        const SizedBox(width: 8),
        Expanded(child: _PayBtn(
            label: 'Waafi Pay', icon: Icons.phone_android_rounded,
            selected: _payMethod == 'waafi_pay', color: const Color(0xFFFF8A00),
            onTap: () => setState(() => _payMethod = 'waafi_pay'))),
      ]),
      SizedBox(height: 24),
      // Confirm
      Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: context.colors.cardBg, borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 16)],
        ),
        child: Column(children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Amount Due', style: TextStyle(color: _kMuted, fontSize: 13)),
            Text('\$${_remaining.toStringAsFixed(2)}',
                style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900, color: context.colors.navyText)),
          ]),
          SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _loading ? null : _pay,
              style: ElevatedButton.styleFrom(
                backgroundColor: context.colors.navyText, foregroundColor: Colors.white,
                minimumSize: const Size(0, 54),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
              child: _loading
                  ? const SizedBox(width: 22, height: 22,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : const Text('🏠  Confirm Full Payment',
                      style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            ),
          ),
        ]),
      ),
      const SizedBox(height: 40),
    ]),
  );
}

class _BalanceRow extends StatelessWidget {
  final String label, value;
  final Color color;
  final bool bold, large;
  const _BalanceRow({required this.label, required this.value, required this.color,
      this.bold = false, this.large = false});

  @override
  Widget build(BuildContext context) => Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
    Text(label, style: TextStyle(color: _kMuted, fontSize: large ? 14 : 12, fontWeight: bold ? FontWeight.w600 : FontWeight.w400)),
    Text(value, style: TextStyle(color: color, fontSize: large ? 18 : 13, fontWeight: bold ? FontWeight.w900 : FontWeight.w600)),
  ]);
}

class _BookingDetail extends StatelessWidget {
  final String label, value;
  const _BookingDetail({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Text(label, style: const TextStyle(fontSize: 10, color: _kMuted, fontWeight: FontWeight.w600)),
    SizedBox(height: 2),
    Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText)),
  ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED WIDGETS
// ─────────────────────────────────────────────────────────────────────────────
class _Skeleton extends StatelessWidget {
  final double height;
  const _Skeleton({required this.height});

  @override
  Widget build(BuildContext context) => Shimmer.fromColors(
    baseColor: Colors.grey.shade200, highlightColor: Colors.grey.shade50,
    child: Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6), height: height,
      decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16)),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  const _EmptyState({required this.icon, required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(40),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 80, height: 80,
            decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: Icon(icon, size: 40, color: _kOrange)),
        SizedBox(height: 16),
        Text(title, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: context.colors.navyText)),
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
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.error_outline_rounded, size: 56, color: Colors.red),
      const SizedBox(height: 12),
      Text(message, style: const TextStyle(color: _kMuted), textAlign: TextAlign.center),
      const SizedBox(height: 16),
      ElevatedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('Retry')),
    ]),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// REELS ROW  (horizontal slider, Facebook-style)
// ─────────────────────────────────────────────────────────────────────────────
class _ReelsRow extends StatelessWidget {
  final List<String> reels;
  final Map property;
  const _ReelsRow({required this.reels, required this.property});

  @override
  Widget build(BuildContext context) {
    // card: 100px wide × 160px tall  (9:16 portrait ratio ≈ 0.625)
    return SizedBox(
      height: 160,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 4),
        itemCount: reels.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) => SizedBox(
          width: 100,
          child: _ReelThumbnailCard(
            url: reels[i],
            index: i,
            total: reels.length,
            onTap: () => Navigator.of(context, rootNavigator: true).push(
              MaterialPageRoute(
                builder: (_) => _ReelPlayerScreen(
                  reels: reels,
                  initialIndex: i,
                  property: property,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ── Single reel thumbnail card (lightweight — no VideoPlayer, just static UI) ─
class _ReelThumbnailCard extends StatelessWidget {
  final String url;
  final int index, total;
  final VoidCallback onTap;
  const _ReelThumbnailCard({
    required this.url, required this.index,
    required this.total, required this.onTap,
  });

  // Gradient list for variety
  static const _gradients = [
    [Color(0xFF1a1a2e), Color(0xFF16213e)],
    [Color(0xFF0d2137), Color(0xFF1B5E20)],
    [Color(0xFF2d1b4e), Color(0xFF0d47a1)],
    [Color(0xFF1B0000), Color(0xFF4a0000)],
  ];

  @override
  Widget build(BuildContext context) {
    final grad = _gradients[index % _gradients.length];
    return GestureDetector(
      onTap: onTap,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft, end: Alignment.bottomRight,
              colors: grad,
            ),
          ),
          child: Stack(fit: StackFit.expand, children: [
            // Subtle scanlines
            Positioned.fill(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: List.generate(4, (_) => Container(
                  height: 1, color: Colors.white.withValues(alpha: 0.04),
                )),
              ),
            ),

            // Center play button — compact
            Center(
              child: Container(
                width: 36, height: 36,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.18),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.6), width: 1.5),
                ),
                child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 22),
              ),
            ),

            // 🎬 REEL badge — top-left, compact
            Positioned(
              top: 6, left: 6,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                decoration: BoxDecoration(
                  color: _kOrange, borderRadius: BorderRadius.circular(4)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.videocam_rounded, color: Colors.white, size: 8),
                  SizedBox(width: 2),
                  Text('REEL', style: TextStyle(color: Colors.white, fontSize: 7, fontWeight: FontWeight.w900, letterSpacing: 0.3)),
                ]),
              ),
            ),

            // Index number — top-right
            Positioned(
              top: 6, right: 6,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.black54, borderRadius: BorderRadius.circular(4)),
                child: Text(
                  '${index + 1}/$total',
                  style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w700),
                ),
              ),
            ),

            // Bottom gradient + label
            Positioned(
              bottom: 0, left: 0, right: 0,
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 7),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter, end: Alignment.topCenter,
                    colors: [Colors.black.withValues(alpha: 0.75), Colors.transparent],
                  ),
                ),
                child: const Center(
                  child: Text('Watch', style: TextStyle(
                    color: Colors.white70, fontSize: 9, fontWeight: FontWeight.w600,
                    letterSpacing: 0.4,
                  )),
                ),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

// ── Full-screen reel player ───────────────────────────────────────────────────
class _ReelPlayerScreen extends StatefulWidget {
  final List<String> reels;
  final int initialIndex;
  final Map property;
  const _ReelPlayerScreen({
    required this.reels,
    required this.initialIndex,
    required this.property,
  });

  @override
  State<_ReelPlayerScreen> createState() => _ReelPlayerScreenState();
}

class _ReelPlayerScreenState extends State<_ReelPlayerScreen> {
  late PageController _page;
  int _current = 0;
  bool _showControls = true;
  bool _cardExpanded = false;
  // Web: use HtmlElementView with <video> tags registered per reel
  final Map<int, String> _viewIds = {};
  final Map<int, webvideo.VideoElementStub> _videoEls = {};

  // Non-web fallback
  final Map<int, VideoPlayerController> _controllers = {};
  bool _userStarted = false;

  @override
  void initState() {
    super.initState();
    _current = widget.initialIndex;
    _page = PageController(initialPage: widget.initialIndex);
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    if (kIsWeb) {
      _registerWebVideo(_current);
      if (_current + 1 < widget.reels.length) _registerWebVideo(_current + 1);
    } else {
      _initController(_current);
      if (_current + 1 < widget.reels.length) _initController(_current + 1);
    }
  }

  // ── Web: register native <video> element ──────────────────────────────────
  void _registerWebVideo(int index) {
    if (_viewIds.containsKey(index)) return;
    final viewId = 'reel-video-$index-${DateTime.now().millisecondsSinceEpoch}';
    final video = webvideo.createVideoElement()
      ..src = widget.reels[index]
      ..style.width = '100%'
      ..style.height = '100%'
      ..style.objectFit = 'cover'
      ..loop = true
      ..muted = false;
    webvideo.platformViewRegistry.registerViewFactory(viewId, (_) => video);
    _viewIds[index] = viewId;
    _videoEls[index] = video;
  }

  void _webPlay(int index) {
    _videoEls[index]?.play();
  }

  void _webPause(int index) {
    _videoEls[index]?.pause();
  }

  bool get _webIsPlaying => !(_videoEls[_current]?.paused ?? true);

  void _startPlay() {
    _userStarted = true;
    setState(() {});
    if (kIsWeb) {
      _webPlay(_current);
    } else {
      _controllers[_current]?.play();
    }
  }

  void _onPageChanged(int index) {
    if (kIsWeb) {
      _webPause(_current);
    } else {
      _controllers[_current]?.pause();
    }
    setState(() { _current = index; _showControls = true; });
    if (kIsWeb) {
      _registerWebVideo(index);
      if (_userStarted) _webPlay(index);
      if (index + 1 < widget.reels.length) _registerWebVideo(index + 1);
    } else {
      _initController(index);
      if (_userStarted) _controllers[index]?.play();
      if (index + 1 < widget.reels.length) _initController(index + 1);
    }
  }

  void _togglePlayPause() {
    setState(() {});
    if (kIsWeb) {
      _webIsPlaying ? _webPause(_current) : _webPlay(_current);
    } else {
      final ctrl = _controllers[_current];
      if (ctrl == null) return;
      ctrl.value.isPlaying ? ctrl.pause() : ctrl.play();
    }
  }

  void _toggleControls() => setState(() => _showControls = !_showControls);

  // ── Non-web controller init ───────────────────────────────────────────────
  void _initController(int index) {
    if (_controllers.containsKey(index)) return;
    final ctrl = VideoPlayerController.networkUrl(
      Uri.parse(widget.reels[index]),
      videoPlayerOptions: VideoPlayerOptions(mixWithOthers: true),
    );
    _controllers[index] = ctrl;
    ctrl.setLooping(true);
    ctrl.initialize().then((_) {
      if (!mounted) return;
      setState(() {});
      if (index == _current && _userStarted) ctrl.play();
    });
  }

  @override
  void dispose() {
    if (kIsWeb) {
      for (final v in _videoEls.values) { v.pause(); v.remove(); }
    } else {
      for (final c in _controllers.values) { c.pause(); c.dispose(); }
    }
    SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.property;
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(children: [
        // PageView — swipe up/down between reels
        PageView.builder(
          controller: _page,
          scrollDirection: Axis.vertical,
          onPageChanged: _onPageChanged,
          itemCount: widget.reels.length,
          itemBuilder: (_, i) {
            return GestureDetector(
              onTap: () {
                if (!_userStarted) { _startPlay(); return; }
                _togglePlayPause();
              },
              child: Stack(fit: StackFit.expand, children: [
                // ── Video display ─────────────────────────────────────────
                if (kIsWeb && _viewIds.containsKey(i))
                  HtmlElementView(viewType: _viewIds[i]!)
                else if (!kIsWeb)
                  Builder(builder: (_) {
                    final ctrl = _controllers[i];
                    if (ctrl != null && ctrl.value.isInitialized) {
                      return FittedBox(
                        fit: BoxFit.cover,
                        child: SizedBox(
                          width: ctrl.value.size.width,
                          height: ctrl.value.size.height,
                          child: VideoPlayer(ctrl),
                        ),
                      );
                    }
                    return Container(color: Colors.black);
                  })
                else
                  Container(color: Colors.black),

                // Loading (web: before viewId registered; non-web: before init)
                if (kIsWeb && !_viewIds.containsKey(i) && _userStarted)
                  const Center(child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),
                if (!kIsWeb && !(_controllers[i]?.value.isInitialized ?? false) && _userStarted)
                  const Center(child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),

                // BIG PLAY BUTTON — shown before first tap
                if (!_userStarted && i == _current)
                  Center(
                    child: GestureDetector(
                      onTap: _startPlay,
                      child: Container(
                        width: 80, height: 80,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.black.withValues(alpha: 0.5),
                          border: Border.all(color: Colors.white, width: 2.5),
                        ),
                        child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
                      ),
                    ),
                  ),

                // Pause indicator
                if (_userStarted && !_webIsPlaying && _showControls && kIsWeb)
                  Center(
                    child: Container(
                      width: 64, height: 64,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.black.withValues(alpha: 0.45)),
                      child: const Icon(Icons.pause_rounded, color: Colors.white, size: 36),
                    ),
                  ),
              ]),
            );
          },
        ),

        // Top gradient + back button
        Positioned(
          top: 0, left: 0, right: 0,
          child: AnimatedOpacity(
            opacity: _showControls ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 200),
            child: Container(
              padding: EdgeInsets.only(
                top: MediaQuery.of(context).padding.top + 8,
                left: 8, right: 16, bottom: 20,
              ),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter, end: Alignment.bottomCenter,
                  colors: [Colors.black.withValues(alpha: 0.7), Colors.transparent],
                ),
              ),
              child: Row(children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 20),
                  onPressed: () => Navigator.pop(context),
                ),
                const Spacer(),
                Text(
                  '${_current + 1} / ${widget.reels.length}',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13),
                ),
              ]),
            ),
          ),
        ),

        // Right side: play/pause + reel dots
        Positioned(
          right: 12, bottom: 180,
          child: AnimatedOpacity(
            opacity: _showControls ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 200),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              // Play/Pause
              GestureDetector(
                onTap: _userStarted ? _togglePlayPause : _startPlay,
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: Colors.black38, shape: BoxShape.circle),
                  child: Icon(
                    (kIsWeb ? _webIsPlaying : _controllers[_current]?.value.isPlaying == true)
                        ? Icons.pause_rounded : Icons.play_arrow_rounded,
                    color: Colors.white, size: 28,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              // Reel dots
              ...List.generate(widget.reels.length, (i) => AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(vertical: 2),
                width: 4, height: _current == i ? 20 : 8,
                decoration: BoxDecoration(
                  color: _current == i ? _kOrange : Colors.white38,
                  borderRadius: BorderRadius.circular(4),
                ),
              )),
            ]),
          ),
        ),

        // Progress bar
        if (_controllers[_current]?.value.isInitialized == true)
        Positioned(
          bottom: 160, left: 16, right: 50,
          child: VideoProgressIndicator(
            _controllers[_current]!,
            allowScrubbing: true,
            colors: VideoProgressColors(
              playedColor: _kOrange,
              bufferedColor: Colors.white24,
              backgroundColor: Colors.white12,
            ),
            padding: EdgeInsets.zero,
          ),
        ),

        // ── Property card at bottom ────────────────────────────────────
        Positioned(
          bottom: 0, left: 0, right: 0,
          child: GestureDetector(
            onTap: () => setState(() => _cardExpanded = !_cardExpanded),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              curve: Curves.easeOut,
              padding: EdgeInsets.fromLTRB(16, 14, 16, MediaQuery.of(context).padding.bottom + 14),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.bottomCenter, end: Alignment.topCenter,
                  colors: [Colors.black.withValues(alpha: 0.92), Colors.transparent],
                ),
              ),
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                // Handle bar
                Center(child: Container(
                  width: 36, height: 3,
                  margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(color: Colors.white30, borderRadius: BorderRadius.circular(4)),
                )),

                Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  // Property thumbnail
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: NetImage(
                      url: (p['thumbnail'] ?? p['images']?[0] ?? '').toString(),
                      width: 60, height: 60, fit: BoxFit.cover,
                      errorWidget: Container(
                        width: 60, height: 60,
                        color: context.colors.navyText,
                        child: const Icon(Icons.home_rounded, color: Colors.white38, size: 28),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(
                      p['title'] ?? '',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15),
                      maxLines: 1, overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${p['district_name'] ?? ''} · ${(p['type'] ?? '').toString().toUpperCase()}',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 11),
                    ),
                    const SizedBox(height: 6),
                    Row(children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: _kOrange, borderRadius: BorderRadius.circular(20)),
                        child: Text(
                          '\$${(p['monthly_rent'] as num?)?.toStringAsFixed(0) ?? '—'}/mo',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 12),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.white12, borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.white24),
                        ),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(Icons.bed_rounded, color: Colors.white70, size: 12),
                          const SizedBox(width: 3),
                          Text('${p['bedrooms']} Beds', style: const TextStyle(color: Colors.white70, fontSize: 11)),
                        ]),
                      ),
                    ]),
                  ])),
                ]),

                // Expanded extra info
                if (_cardExpanded) ...[
                  const SizedBox(height: 12),
                  const Divider(color: Colors.white12, height: 1),
                  const SizedBox(height: 12),
                  Row(children: [
                    _ReelInfoChip(icon: Icons.bathtub_outlined, label: '${p['bathrooms']} Bath'),
                    const SizedBox(width: 8),
                    _ReelInfoChip(icon: Icons.layers_rounded, label: 'Floor ${p['floor'] ?? '—'}'),
                    const SizedBox(width: 8),
                    _ReelInfoChip(icon: Icons.chair_rounded, label: (p['furnishing'] ?? '').toString().capitalize()),
                  ]),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        // Scroll back to booking on detail screen handled by nav
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _kOrange, foregroundColor: Colors.white,
                        minimumSize: const Size(0, 46),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      icon: const Icon(Icons.home_rounded, size: 18),
                      label: const Text('View & Book Property', style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
                  ),
                ],
              ]),
            ),
          ),
        ),
      ]),
    );
  }
}

class _ReelInfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  const _ReelInfoChip({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white10, borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.white24),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, color: Colors.white60, size: 12),
      const SizedBox(width: 4),
      Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
    ]),
  );
}

extension _StringExt on String {
  String capitalize() => isEmpty ? this : '${this[0].toUpperCase()}${substring(1)}';
}

