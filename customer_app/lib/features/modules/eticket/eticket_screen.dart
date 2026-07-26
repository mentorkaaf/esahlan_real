import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shimmer/shimmer.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'package:intl/intl.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../core/theme/app_color_tokens.dart';
import '../../../core/utils/error_handler.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../ads/services/ad_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Theme Constants
// ─────────────────────────────────────────────────────────────────────────────
const _kOrange  = AppColors.primary;    // #FF8A00
const _kNavy    = AppColors.secondary;  // #07003B — for gradients/overlays
const _kSky     = Color(0xFF1565C0);
// _kBg/_kCard are light-mode defaults; scaffolds use context.colors in build()
const _kBg      = Color(0xFFF5F7FA);
const _kCard    = Colors.white;
const _kMuted   = Color(0xFF8A8A9A);
const _kDivider = Color(0xFFF0F1F5);

// ─────────────────────────────────────────────────────────────────────────────
// Providers
// ─────────────────────────────────────────────────────────────────────────────
final _svc = ModuleApiService.create();

final _citiesProvider = FutureProvider<List<Map<String, String>>>((_) async {
  final resp = await _svc.getFlightCities();
  final list = List<Map>.from(resp is Map ? (resp['data'] ?? []) : (resp ?? []));
  return list
      .map((c) => {'city': c['city']?.toString() ?? '', 'code': c['code']?.toString() ?? ''})
      .where((c) => c['city']!.isNotEmpty)
      .toList()
    ..sort((a, b) => a['city']!.compareTo(b['city']!));
});

final _activeDatesProvider = FutureProvider
    .family<Set<String>, Map<String, String>>((_, p) async {
  final resp = await _svc.getFlightActiveDates(from: p['from']!, to: p['to']!);
  final list = List<dynamic>.from(resp is Map ? (resp['data'] ?? []) : (resp ?? []));
  return list.map((d) => d.toString()).toSet();
});

final _searchProvider = FutureProvider
    .family<List<Map<String, dynamic>>, Map<String, dynamic>>((_, p) async {
  final resp = await _svc.searchFlights(
    from: p['from'], to: p['to'], date: p['date'],
    adults: p['adults'] ?? 1,
    children: p['children'] ?? 0,
    infants: p['infants'] ?? 0,
    seatClass: p['seat_class'] ?? 'economy',
  );
  final data = resp is Map ? (resp['data'] ?? []) : (resp ?? []);
  return List<Map<String, dynamic>>.from(data);
});

final _myBookingsProvider = FutureProvider((_) async {
  final resp = await _svc.getMyFlightBookings();
  final data = resp is Map ? (resp['data'] ?? []) : (resp ?? []);
  return List<Map<String, dynamic>>.from(data);
});

// ─────────────────────────────────────────────────────────────────────────────
// Country list for nationality dropdown
// ─────────────────────────────────────────────────────────────────────────────
const _kCountries = [
  'Somalia','Kenya','Ethiopia','Uganda','Djibouti','Eritrea','Tanzania','South Sudan',
  'Sudan','Egypt','Saudi Arabia','UAE','Qatar','Kuwait','Bahrain','Oman','Jordan',
  'Turkey','United Kingdom','United States','Canada','Germany','France','Italy',
  'Netherlands','Sweden','Norway','Denmark','Belgium','Spain','Portugal','Switzerland',
  'Australia','New Zealand','Japan','China','India','Pakistan','Bangladesh','Malaysia',
  'Indonesia','Philippines','Thailand','South Korea','South Africa','Nigeria','Ghana',
  'Senegal','Morocco','Tunisia','Algeria','Libya',
];

// Passenger title options
const _kTitles = ['Mr', 'Mrs', 'Ms', 'Dr', 'Prof'];

// ─────────────────────────────────────────────────────────────────────────────
// Main Screen
// ─────────────────────────────────────────────────────────────────────────────
class ETicketScreen extends ConsumerStatefulWidget {
  const ETicketScreen({super.key});
  @override
  ConsumerState<ETicketScreen> createState() => _ETicketScreenState();
}

class _ETicketScreenState extends ConsumerState<ETicketScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    AdService.instance.triggerModulePopups(context, 'eticket');
    _tab = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            pinned: true,
            expandedHeight: 160,
            backgroundColor: _kNavy,
            foregroundColor: Colors.white,
            elevation: 0,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [_kNavy, Color(0xFF0D47A1), Color(0xFF1976D2)],
                  ),
                ),
                child: Stack(children: [
                  // Decorative circles
                  Positioned(right: -40, top: -40, child: Container(
                    width: 200, height: 200,
                    decoration: BoxDecoration(shape: BoxShape.circle,
                        color: Colors.white.withValues(alpha: 0.05)),
                  )),
                  Positioned(left: -20, bottom: -30, child: Container(
                    width: 120, height: 120,
                    decoration: BoxDecoration(shape: BoxShape.circle,
                        color: _kOrange.withValues(alpha: 0.1)),
                  )),
                  SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 50, 20, 0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            const Icon(Icons.flight_takeoff_rounded,
                                color: _kOrange, size: 28),
                            const SizedBox(width: 10),
                            Text('eTicket',
                                style: TextStyle(color: Colors.white,
                                    fontSize: 24, fontWeight: FontWeight.w900)),
                          ]),
                          const SizedBox(height: 4),
                          Text('Book your flight across Somalia & beyond',
                              style: TextStyle(color: Colors.white.withValues(alpha: 0.75),
                                  fontSize: 13)),
                        ],
                      ),
                    ),
                  ),
                ]),
              ),
            ),
            bottom: TabBar(
              controller: _tab,
              indicatorColor: _kOrange,
              indicatorWeight: 3,
              labelColor: Colors.white,
              unselectedLabelColor: Colors.white60,
              labelStyle: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
              tabs: const [Tab(text: 'Book Flight'), Tab(text: 'My Tickets')],
            ),
          ),
        ],
        body: TabBarView(
          controller: _tab,
          children: const [
            _SearchTab(),
            _MyTicketsTab(),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Search Tab
// ─────────────────────────────────────────────────────────────────────────────
class _SearchTab extends ConsumerStatefulWidget {
  const _SearchTab();
  @override
  ConsumerState<_SearchTab> createState() => _SearchTabState();
}

class _SearchTabState extends ConsumerState<_SearchTab> {
  // Search state
  String _tripType = 'one_way';
  Map<String, String>? _fromCity;
  Map<String, String>? _toCity;
  DateTime? _departureDate;
  DateTime? _returnDate;
  int _adults   = 1;
  int _children = 0;
  int _infants  = 0;
  String _seatClass = 'economy';

  // Search result trigger
  Map<String, dynamic>? _searchParams;

  bool get _canSearch =>
      _fromCity != null && _toCity != null && _departureDate != null;

  void _doSearch() {
    if (!_canSearch) return;
    HapticFeedback.lightImpact();
    setState(() {
      _searchParams = {
        'from': _fromCity!['city'],
        'to':   _toCity!['city'],
        'date': DateFormat('yyyy-MM-dd').format(_departureDate!),
        'adults':   _adults,
        'children': _children,
        'infants':  _infants,
        'seat_class': _seatClass,
      };
    });
  }

  void _swap() {
    HapticFeedback.selectionClick();
    setState(() {
      final tmp = _fromCity; _fromCity = _toCity; _toCity = tmp;
    });
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(children: [
        // ── Search card ──────────────────────────────────────────────
        Container(
          margin: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: context.colors.cardBg,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 24, offset: const Offset(0, 6),
            )],
          ),
          child: Column(children: [
            // Trip type toggle
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Row(children: [
                _TripTypeChip(label: 'One Way',  value: 'one_way',   selected: _tripType, onTap: (v) => setState(() { _tripType = v; _returnDate = null; })),
                const SizedBox(width: 8),
                _TripTypeChip(label: 'Round Trip', value: 'round_trip', selected: _tripType, onTap: (v) => setState(() => _tripType = v)),
              ]),
            ),
            const SizedBox(height: 16),
            // From / Swap / To
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(children: [
                Expanded(child: _CitySelector(
                  label: 'From',
                  city: _fromCity,
                  hint: 'Departure city',
                  onSelect: (c) => setState(() => _fromCity = c),
                )),
                GestureDetector(
                  onTap: _swap,
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 8),
                    width: 36, height: 36,
                    decoration: BoxDecoration(
                      color: context.colors.navyText,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.swap_horiz_rounded,
                        color: Colors.white, size: 20),
                  ),
                ),
                Expanded(child: _CitySelector(
                  label: 'To',
                  city: _toCity,
                  hint: 'Destination city',
                  onSelect: (c) => setState(() => _toCity = c),
                )),
              ]),
            ),
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Divider(color: _kDivider, height: 1),
            ),
            const SizedBox(height: 12),
            // Date row
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(children: [
                Expanded(child: _DatePicker(
                  label: 'Departure',
                  date: _departureDate,
                  fromCity: _fromCity?['city'],
                  toCity: _toCity?['city'],
                  onPick: (d) => setState(() => _departureDate = d),
                )),
                if (_tripType == 'round_trip') ...[
                  const SizedBox(width: 12),
                  Expanded(child: _DatePicker(
                    label: 'Return',
                    date: _returnDate,
                    fromCity: _toCity?['city'],
                    toCity: _fromCity?['city'],
                    minDate: _departureDate,
                    onPick: (d) => setState(() => _returnDate = d),
                  )),
                ],
              ]),
            ),
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Divider(color: _kDivider, height: 1),
            ),
            const SizedBox(height: 12),
            // Passengers + Class
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(children: [
                Expanded(child: _PassengerSelector(
                  adults: _adults, children: _children, infants: _infants,
                  onChanged: (a, c, i) => setState(() { _adults = a; _children = c; _infants = i; }),
                )),
                const SizedBox(width: 12),
                Expanded(child: _ClassSelector(
                  selected: _seatClass,
                  onChanged: (v) => setState(() => _seatClass = v),
                )),
              ]),
            ),
            const SizedBox(height: 16),
            // Search button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _canSearch ? _doSearch : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _kOrange,
                    disabledBackgroundColor: _kOrange.withValues(alpha: 0.4),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    elevation: 2,
                  ),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.search_rounded, color: Colors.white, size: 20),
                    const SizedBox(width: 8),
                    Text(
                      _searchParams != null ? 'Update Search' : 'Search Flights',
                      style: TextStyle(color: Colors.white,
                          fontWeight: FontWeight.w800, fontSize: 16),
                    ),
                  ]),
                ),
              ),
            ),
            const SizedBox(height: 16),
          ]),
        ),
        // ── Search Results ────────────────────────────────────────────
        if (_searchParams != null) ...[
          _SearchResults(
            params: _searchParams!,
            adults: _adults, children: _children, infants: _infants,
          ),
        ] else ...[
          _PopularRoutesHint(),
        ],
        const SizedBox(height: 32),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Trip type chip
// ─────────────────────────────────────────────────────────────────────────────
class _TripTypeChip extends StatelessWidget {
  final String label, value, selected;
  final ValueChanged<String> onTap;
  const _TripTypeChip({required this.label, required this.value,
      required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = value == selected;
    return GestureDetector(
      onTap: () => onTap(value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: active ? _kNavy : const Color(0xFFF4F5FA),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: active ? _kNavy : _kDivider),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(active ? Icons.radio_button_checked : Icons.radio_button_unchecked,
              size: 14,
              color: active ? _kOrange : _kMuted),
          const SizedBox(width: 6),
          Text(label,
              style: TextStyle(
                  fontSize: 13, fontWeight: FontWeight.w600,
                  color: active ? Colors.white : _kMuted)),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// City Selector
// ─────────────────────────────────────────────────────────────────────────────
class _CitySelector extends ConsumerWidget {
  final String label, hint;
  final Map<String, String>? city;
  final ValueChanged<Map<String, String>> onSelect;
  const _CitySelector({required this.label, required this.hint,
      this.city, required this.onSelect});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final citiesAsync = ref.watch(_citiesProvider);

    return GestureDetector(
      onTap: () => citiesAsync.whenData((cities) =>
          _showCitySheet(context, cities)),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: context.colors.inputFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: city != null ? _kNavy.withValues(alpha: 0.3) : _kDivider),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(
              fontSize: 11, fontWeight: FontWeight.w600, color: _kMuted)),
          const SizedBox(height: 4),
          city != null
              ? Row(children: [
                  Text(city!['code'] ?? '',
                      style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900,
                          color: context.colors.navyText)),
                  const SizedBox(width: 6),
                  Expanded(child: Text(city!['city'] ?? '',
                      style: TextStyle(fontSize: 12, color: _kMuted),
                      overflow: TextOverflow.ellipsis)),
                ])
              : Row(children: [
                  const Icon(Icons.location_on_outlined, size: 14, color: _kMuted),
                  const SizedBox(width: 4),
                  Text(hint, style: TextStyle(fontSize: 13, color: _kMuted)),
                ]),
        ]),
      ),
    );
  }

  void _showCitySheet(BuildContext ctx, List<Map<String, String>> cities) {
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CityPickerSheet(cities: cities, onSelect: onSelect),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// City picker bottom sheet
// ─────────────────────────────────────────────────────────────────────────────
class _CityPickerSheet extends StatefulWidget {
  final List<Map<String, String>> cities;
  final ValueChanged<Map<String, String>> onSelect;
  const _CityPickerSheet({required this.cities, required this.onSelect});

  @override
  State<_CityPickerSheet> createState() => _CityPickerSheetState();
}

class _CityPickerSheetState extends State<_CityPickerSheet> {
  final _ctrl = TextEditingController();
  List<Map<String, String>> _filtered = [];

  @override
  void initState() {
    super.initState();
    _filtered = widget.cities;
    _ctrl.addListener(() {
      final q = _ctrl.text.toLowerCase();
      setState(() => _filtered = q.isEmpty
          ? widget.cities
          : widget.cities.where((c) =>
              c['city']!.toLowerCase().contains(q) ||
              c['code']!.toLowerCase().contains(q)).toList());
    });
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.7,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (_, sc) => Container(
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          const SizedBox(height: 8),
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: _kDivider,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(children: [
              Icon(Icons.flight_rounded, color: context.colors.navyText, size: 22),
              const SizedBox(width: 10),
              Text('Select City',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800,
                      color: context.colors.navyText)),
              const Spacer(),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: Container(
                  width: 30, height: 30,
                  decoration: BoxDecoration(color: context.colors.surfaceBg,
                      borderRadius: BorderRadius.circular(8)),
                  child: const Icon(Icons.close, size: 16, color: _kMuted),
                ),
              ),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: TextField(
              controller: _ctrl,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'Search city or IATA code…',
                prefixIcon: const Icon(Icons.search_rounded, size: 20, color: _kMuted),
                filled: true, fillColor: context.colors.inputFill,
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none),
                contentPadding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: ListView.builder(
              controller: sc,
              itemCount: _filtered.length,
              itemBuilder: (_, i) {
                final c = _filtered[i];
                return ListTile(
                  leading: Container(
                    width: 44, height: 44,
                    decoration: BoxDecoration(
                      color: _kNavy.withValues(alpha: 0.06),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Center(child: Text(c['code'] ?? '',
                        style: TextStyle(fontSize: 12,
                            fontWeight: FontWeight.w800, color: context.colors.navyText))),
                  ),
                  title: Text(c['city'] ?? '',
                      style: TextStyle(fontWeight: FontWeight.w600,
                          fontSize: 14, color: context.colors.navyText)),
                  onTap: () { Navigator.pop(context); widget.onSelect(c); },
                );
              },
            ),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Date Picker — shows calendar with active-flight dates highlighted
// ─────────────────────────────────────────────────────────────────────────────
class _DatePicker extends ConsumerWidget {
  final String label;
  final DateTime? date;
  final String? fromCity, toCity;
  final DateTime? minDate;
  final ValueChanged<DateTime> onPick;
  const _DatePicker({required this.label, this.date, this.fromCity,
      this.toCity, this.minDate, required this.onPick});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final activeDatesAsync = (fromCity != null && toCity != null)
        ? ref.watch(_activeDatesProvider({'from': fromCity!, 'to': toCity!}))
        : const AsyncValue<Set<String>>.data({});

    final activeDates = activeDatesAsync.maybeWhen(
        data: (d) => d, orElse: () => <String>{});

    return GestureDetector(
      onTap: () => _pick(context, activeDates),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: context.colors.inputFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: date != null ? _kNavy.withValues(alpha: 0.3) : _kDivider),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(
              fontSize: 11, fontWeight: FontWeight.w600, color: _kMuted)),
          const SizedBox(height: 4),
          Row(children: [
            Icon(Icons.calendar_today_rounded, size: 14,
                color: date != null ? _kNavy : _kMuted),
            const SizedBox(width: 6),
            date != null
                ? Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(DateFormat('EEE, d MMM').format(date!),
                        style: TextStyle(fontSize: 13,
                            fontWeight: FontWeight.w700, color: context.colors.navyText)),
                    Text(DateFormat('yyyy').format(date!),
                        style: TextStyle(fontSize: 11, color: _kMuted)),
                  ])
                : Text('Select date',
                    style: TextStyle(fontSize: 13, color: _kMuted)),
          ]),
        ]),
      ),
    );
  }

  void _pick(BuildContext context, Set<String> activeDates) async {
    final first = minDate ?? DateTime.now();
    final last = DateTime.now().add(const Duration(days: 365));
    final picked = await showDatePicker(
      context: context,
      initialDate: date ?? first,
      firstDate: first,
      lastDate: last,
      selectableDayPredicate: activeDates.isEmpty
          ? null
          : (day) {
              final s = DateFormat('yyyy-MM-dd').format(day);
              return activeDates.contains(s);
            },
      builder: (ctx, child) => Theme(
        data: Theme.of(ctx).copyWith(
          colorScheme: const ColorScheme.light(
            primary: _kNavy, onPrimary: Colors.white,
            secondary: _kOrange, onSecondary: Colors.white,
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) onPick(picked);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Passenger selector — tappable tile that opens a bottom sheet
// ─────────────────────────────────────────────────────────────────────────────
class _PassengerSelector extends StatelessWidget {
  final int adults, children, infants;
  final void Function(int, int, int) onChanged;
  const _PassengerSelector({required this.adults, required this.children,
      required this.infants, required this.onChanged});

  int get _total => adults + children + infants;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _showSheet(context),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: context.colors.inputFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _kDivider),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Passengers', style: TextStyle(
              fontSize: 11, fontWeight: FontWeight.w600, color: _kMuted)),
          const SizedBox(height: 4),
          Row(children: [
            Icon(Icons.person_outline_rounded, size: 14, color: context.colors.navyText),
            const SizedBox(width: 4),
            Text('$_total Passenger${_total != 1 ? 's' : ''}',
                style: TextStyle(fontSize: 13,
                    fontWeight: FontWeight.w700, color: context.colors.navyText)),
          ]),
          Text('$adults Adult${adults != 1 ? 's' : ''}'
              '${children > 0 ? ' · $children Child' : ''}'
              '${infants > 0 ? ' · $infants Infant' : ''}',
              style: TextStyle(fontSize: 11, color: _kMuted)),
        ]),
      ),
    );
  }

  void _showSheet(BuildContext context) {
    int _a = adults, _c = children, _i = infants;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => StatefulBuilder(
        builder: (ctx, ss) => Container(
          decoration: BoxDecoration(
            color: context.colors.cardBg,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 40, height: 4,
                decoration: BoxDecoration(color: _kDivider,
                    borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 20),
            Text('Passengers',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800,
                    color: context.colors.navyText)),
            const SizedBox(height: 20),
            _PaxRow(label: 'Adults', sub: '12+ years', value: _a, min: 1,
                onChanged: (v) => ss(() => _a = v)),
            const Divider(height: 24),
            _PaxRow(label: 'Children', sub: '2–11 years', value: _c, min: 0,
                onChanged: (v) => ss(() => _c = v)),
            const Divider(height: 24),
            _PaxRow(label: 'Infants', sub: 'Under 2 years', value: _i, min: 0,
                onChanged: (v) => ss(() => _i = v)),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () { Navigator.pop(ctx); onChanged(_a, _c, _i); },
                style: ElevatedButton.styleFrom(
                  backgroundColor: _kNavy,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: Text('Confirm',
                    style: TextStyle(color: Colors.white,
                        fontWeight: FontWeight.w700, fontSize: 15)),
              ),
            ),
            const SizedBox(height: 8),
          ]),
        ),
      ),
    );
  }
}

class _PaxRow extends StatelessWidget {
  final String label, sub;
  final int value, min;
  final ValueChanged<int> onChanged;
  const _PaxRow({required this.label, required this.sub,
      required this.value, required this.min, required this.onChanged});

  @override
  Widget build(BuildContext context) => Row(children: [
    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: TextStyle(fontWeight: FontWeight.w700,
          fontSize: 15, color: context.colors.navyText)),
      Text(sub, style: TextStyle(fontSize: 12, color: _kMuted)),
    ])),
    _CountBtn(icon: Icons.remove, onTap: value > min
        ? () => onChanged(value - 1) : null),
    SizedBox(width: 36, child: Center(child: Text('$value',
        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800,
            color: context.colors.navyText)))),
    _CountBtn(icon: Icons.add, onTap: () => onChanged(value + 1)),
  ]);
}

class _CountBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;
  const _CountBtn({required this.icon, this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      width: 32, height: 32,
      decoration: BoxDecoration(
        color: onTap != null ? _kNavy : _kDivider,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, size: 16,
          color: onTap != null ? Colors.white : _kMuted),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Class selector
// ─────────────────────────────────────────────────────────────────────────────
class _ClassSelector extends StatelessWidget {
  final String selected;
  final ValueChanged<String> onChanged;
  const _ClassSelector({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _showSheet(context),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: context.colors.inputFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _kDivider),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Class', style: TextStyle(
              fontSize: 11, fontWeight: FontWeight.w600, color: _kMuted)),
          const SizedBox(height: 4),
          Row(children: [
            Icon(selected == 'business' ? Icons.star_rounded : Icons.airline_seat_recline_normal_rounded,
                size: 14, color: context.colors.navyText),
            const SizedBox(width: 4),
            Text(selected == 'business' ? 'Business' : 'Economy',
                style: TextStyle(fontSize: 13,
                    fontWeight: FontWeight.w700, color: context.colors.navyText)),
          ]),
          Text(selected == 'business' ? 'Premium seats' : 'Standard seats',
              style: TextStyle(fontSize: 11, color: _kMuted)),
        ]),
      ),
    );
  }

  void _showSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: _kDivider,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          Text('Select Class',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: context.colors.navyText)),
          const SizedBox(height: 16),
          _ClassOption(label: 'Economy', sub: 'Standard seats · best value',
              icon: Icons.airline_seat_recline_normal_rounded,
              value: 'economy', selected: selected, color: const Color(0xFF10B981),
              onTap: () { Navigator.pop(context); onChanged('economy'); }),
          const SizedBox(height: 12),
          _ClassOption(label: 'Business', sub: 'Premium seats · more space',
              icon: Icons.star_rounded,
              value: 'business', selected: selected, color: context.colors.navyText,
              onTap: () { Navigator.pop(context); onChanged('business'); }),
          const SizedBox(height: 24),
        ]),
      ),
    );
  }
}

class _ClassOption extends StatelessWidget {
  final String label, sub, value, selected;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
  const _ClassOption({required this.label, required this.sub, required this.icon,
      required this.value, required this.selected, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = value == selected;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: active ? color.withValues(alpha: 0.07) : context.colors.inputFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: active ? color : _kDivider,
              width: active ? 2 : 1),
        ),
        child: Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 12),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: TextStyle(
                fontSize: 15, fontWeight: FontWeight.w700, color: color)),
            Text(sub, style: TextStyle(fontSize: 12, color: _kMuted)),
          ]),
          const Spacer(),
          if (active) Icon(Icons.check_circle_rounded, color: color, size: 22),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Popular routes hint (shown when no search yet)
// ─────────────────────────────────────────────────────────────────────────────
class _PopularRoutesHint extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    const routes = [
      {'from': 'Mogadishu', 'fcode': 'MGQ', 'to': 'Hargeysa', 'tcode': 'HGA'},
      {'from': 'Mogadishu', 'fcode': 'MGQ', 'to': 'Nairobi', 'tcode': 'NBO'},
      {'from': 'Hargeysa', 'fcode': 'HGA', 'to': 'Dubai', 'tcode': 'DXB'},
      {'from': 'Mogadishu', 'fcode': 'MGQ', 'to': 'Doha', 'tcode': 'DOH'},
    ];

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(children: [
          Container(width: 4, height: 18,
              decoration: BoxDecoration(color: _kOrange,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(width: 8),
          Text('Popular Routes',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800,
                  color: context.colors.navyText)),
        ]),
      ),
      SizedBox(
        height: 90,
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          scrollDirection: Axis.horizontal,
          itemCount: routes.length,
          separatorBuilder: (_, __) => const SizedBox(width: 10),
          itemBuilder: (_, i) {
            final r = routes[i];
            return Container(
              width: 160,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: context.colors.cardBg,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: _kDivider),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 8, offset: const Offset(0, 2))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Row(children: [
                    Text(r['fcode']!,
                        style: TextStyle(fontSize: 16,
                            fontWeight: FontWeight.w900, color: context.colors.navyText)),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 6),
                      child: Icon(Icons.arrow_forward_rounded,
                          size: 12, color: _kMuted),
                    ),
                    Text(r['tcode']!,
                        style: TextStyle(fontSize: 16,
                            fontWeight: FontWeight.w900, color: context.colors.navyText)),
                  ]),
                  const SizedBox(height: 4),
                  Text('${r['from']} → ${r['to']}',
                      style: TextStyle(fontSize: 10, color: _kMuted),
                      overflow: TextOverflow.ellipsis),
                ],
              ),
            );
          },
        ),
      ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Search Results
// ─────────────────────────────────────────────────────────────────────────────
class _SearchResults extends ConsumerWidget {
  final Map<String, dynamic> params;
  final int adults, children, infants;
  const _SearchResults({required this.params, required this.adults,
      required this.children, required this.infants});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final flightsAsync = ref.watch(_searchProvider(params));

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 4, height: 18,
              decoration: BoxDecoration(color: _kOrange,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(width: 8),
          Text('Available Flights',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800,
                  color: context.colors.navyText)),
          const Spacer(),
          flightsAsync.when(
            data: (flights) => Text('${flights.length} found',
                style: TextStyle(fontSize: 12, color: _kMuted,
                    fontWeight: FontWeight.w600)),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
        ]),
        const SizedBox(height: 12),
        flightsAsync.when(
          loading: () => Column(children: List.generate(3, (_) => const _FlightCardSkeleton())),
          error: (e, _) => _ErrorCard(message: 'No flights found for this route and date.'),
          data: (flights) {
            if (flights.isEmpty) return _EmptyFlights();
            return Column(
              children: flights.map((f) => _FlightCard(
                flight: f,
                adults: adults, children: children, infants: infants,
                seatClass: params['seat_class'] ?? 'economy',
              )).toList(),
            );
          },
        ),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Flight Card
// ─────────────────────────────────────────────────────────────────────────────
class _FlightCard extends StatelessWidget {
  final Map<String, dynamic> flight;
  final int adults, children, infants;
  final String seatClass;
  const _FlightCard({required this.flight, required this.adults,
      required this.children, required this.infants, required this.seatClass});

  @override
  Widget build(BuildContext context) {
    final classes = Map<String, dynamic>.from(flight['classes'] ?? {});
    final double eco = (classes['economy'] as num?)?.toDouble() ?? 0;
    final double biz = (classes['business'] as num?)?.toDouble() ?? 0;
    final double chd = (classes['child'] as num?)?.toDouble() ?? 0;
    final double inf = (classes['infant'] as num?)?.toDouble() ?? 0;

    final double adultPrice = seatClass == 'business' ? biz : eco;
    final double total = adultPrice * adults +
        (chd > 0 ? chd : adultPrice * 0.75) * children +
        (inf > 0 ? inf : adultPrice * 0.1) * infants;

    String? dep, arr, dur;
    try {
      final dt = DateTime.parse(flight['departure_at'] ?? '');
      dep = DateFormat('HH:mm').format(dt);
    } catch (_) { dep = '--:--'; }
    try {
      final dt = DateTime.parse(flight['arrival_at'] ?? '');
      arr = DateFormat('HH:mm').format(dt);
    } catch (_) { arr = '--:--'; }
    dur = flight['duration']?.toString();

    final color = Color(int.tryParse(
        (flight['airline_color'] ?? '#1565C0').replaceFirst('#', '0xFF')) ?? 0xFF1565C0);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: context.colors.borderColor),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 12, offset: const Offset(0, 3))],
      ),
      child: Column(children: [
        // Header
        Container(
          padding: const EdgeInsets.all(16),
          child: Column(children: [
            // Airline + flight number
            Row(children: [
              _AirlineLogo(logo: flight['airline_logo']?.toString(), name: flight['airline']?.toString() ?? '', color: color),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(flight['airline']?.toString() ?? '',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText)),
                Text(flight['flight_number']?.toString() ?? '',
                    style: TextStyle(fontSize: 11, color: _kMuted)),
              ])),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: (flight['available_seats'] as int? ?? 0) > 10
                      ? const Color(0xFF10B981).withValues(alpha: 0.1)
                      : const Color(0xFFF59E0B).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  '${flight['available_seats'] ?? 0} seats',
                  style: TextStyle(
                    fontSize: 11, fontWeight: FontWeight.w700,
                    color: (flight['available_seats'] as int? ?? 0) > 10
                        ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                  ),
                ),
              ),
            ]),
            const SizedBox(height: 16),
            // Route row
            Row(children: [
              _TimeBlock(code: flight['from_code']?.toString() ?? '', city: flight['from_city']?.toString() ?? '', time: dep),
              Expanded(child: Column(children: [
                if (dur != null) Text(dur,
                    style: TextStyle(fontSize: 11, color: _kMuted)),
                const SizedBox(height: 4),
                Row(children: [
                  Expanded(child: Divider(color: _kDivider, thickness: 1)),
                  const Padding(padding: EdgeInsets.symmetric(horizontal: 4),
                      child: Icon(Icons.flight_rounded, size: 14, color: _kMuted)),
                  Expanded(child: Divider(color: _kDivider, thickness: 1)),
                ]),
                Text('Non-stop', style: TextStyle(fontSize: 10, color: _kMuted)),
              ])),
              _TimeBlock(code: flight['to_code']?.toString() ?? '', city: flight['to_city']?.toString() ?? '', time: arr, right: true),
            ]),
          ]),
        ),
        // Price footer
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          decoration: BoxDecoration(
            color: _kNavy.withValues(alpha: 0.03),
            border: Border(top: BorderSide(color: _kDivider)),
            borderRadius: const BorderRadius.vertical(bottom: Radius.circular(18)),
          ),
          child: Row(children: [
            // Price breakdown chips
            Expanded(child: Wrap(spacing: 6, runSpacing: 4, children: [
              if (eco > 0) _PriceChip(label: 'ECO', price: eco, color: const Color(0xFF10B981)),
              if (biz > 0) _PriceChip(label: 'BIZ', price: biz, color: context.colors.navyText),
              if (chd > 0) _PriceChip(label: 'CHD', price: chd, color: const Color(0xFFF59E0B)),
              if (inf > 0) _PriceChip(label: 'INF', price: inf, color: const Color(0xFFEF4444)),
            ])),
            const SizedBox(width: 12),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('\$${total.toStringAsFixed(0)}',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900,
                      color: _kOrange)),
              Text('total · ${adults + children + infants} pax',
                  style: TextStyle(fontSize: 10, color: _kMuted)),
            ]),
            const SizedBox(width: 12),
            ElevatedButton(
              onPressed: () {
                showGeneralDialog(
                  context: context,
                  useRootNavigator: true,
                  barrierDismissible: false,
                  barrierColor: Colors.black54,
                  transitionDuration: const Duration(milliseconds: 280),
                  transitionBuilder: (_, anim, __, child) => SlideTransition(
                    position: Tween<Offset>(
                        begin: const Offset(0, 1), end: Offset.zero)
                        .animate(CurvedAnimation(parent: anim,
                            curve: Curves.easeOutCubic)),
                    child: child,
                  ),
                  pageBuilder: (_, __, ___) => _BookingFlowDialog(
                    flight: flight,
                    adults: adults, children: children, infants: infants,
                    seatClass: seatClass,
                  ),
                );
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: _kOrange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                minimumSize: const Size(80, 40),
              ),
              child: Text('Select',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700,
                      fontSize: 13)),
            ),
          ]),
        ),
      ]),
    );
  }
}

class _AirlineLogo extends StatelessWidget {
  final String? logo;
  final String name;
  final Color color;
  const _AirlineLogo({this.logo, required this.name, required this.color});

  @override
  Widget build(BuildContext context) => Container(
    width: 44, height: 44,
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(12),
    ),
    clipBehavior: Clip.antiAlias,
    child: logo != null && logo!.startsWith('http')
        ? NetImage(url: logo!,
            fit: BoxFit.contain,
            errorWidget: _fallback(name, color))
        : _fallback(name, color),
  );

  Widget _fallback(String n, Color c) => Center(child: Text(
    n.substring(0, n.length.clamp(0, 2)).toUpperCase(),
    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: c),
  ));
}

class _TimeBlock extends StatelessWidget {
  final String code, city, time;
  final bool right;
  const _TimeBlock({required this.code, required this.city,
      required this.time, this.right = false});

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: right ? CrossAxisAlignment.end : CrossAxisAlignment.start,
    children: [
      Text(time, style: TextStyle(fontSize: 22,
          fontWeight: FontWeight.w900, color: context.colors.navyText)),
      Text(code, style: TextStyle(fontSize: 14,
          fontWeight: FontWeight.w800, color: _kSky)),
      Text(city, style: TextStyle(fontSize: 10, color: _kMuted),
          overflow: TextOverflow.ellipsis),
    ],
  );
}

class _PriceChip extends StatelessWidget {
  final String label;
  final double price;
  final Color color;
  const _PriceChip({required this.label, required this.price, required this.color});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text('$label \$${price.toStringAsFixed(0)}',
        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: color)),
  );
}

class _FlightCardSkeleton extends StatelessWidget {
  const _FlightCardSkeleton();
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    height: 180,
    decoration: BoxDecoration(
      color: context.colors.cardBg,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: _kDivider),
    ),
    child: Shimmer.fromColors(
      baseColor: const Color(0xFFE8E8E8),
      highlightColor: const Color(0xFFF5F5F5),
      child: Container(color: context.colors.cardBg),
    ),
  );
}

class _EmptyFlights extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(40),
    decoration: BoxDecoration(
      color: context.colors.cardBg, borderRadius: BorderRadius.circular(18),
      border: Border.all(color: _kDivider),
    ),
    child: Column(children: [
      const Icon(Icons.flight_land_rounded, size: 52, color: _kDivider),
      const SizedBox(height: 16),
      Text('No flights found',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
      const SizedBox(height: 6),
      Text('Try a different date or route',
          style: TextStyle(fontSize: 13, color: _kMuted)),
    ]),
  );
}

class _ErrorCard extends StatelessWidget {
  final String message;
  const _ErrorCard({required this.message});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF3CD), borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFFFE566)),
    ),
    child: Row(children: [
      const Icon(Icons.info_outline_rounded, color: Color(0xFF856404)),
      const SizedBox(width: 10),
      Expanded(child: Text(message,
          style: TextStyle(color: Color(0xFF856404), fontSize: 13))),
    ]),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// BOOKING FLOW DIALOG — handles Passengers → Payment → Success in one dialog
// No Navigator.push needed; useRootNavigator:true guarantees full-screen cover
// ─────────────────────────────────────────────────────────────────────────────
class _BookingFlowDialog extends ConsumerStatefulWidget {
  final Map<String, dynamic> flight;
  final int adults, children, infants;
  final String seatClass;
  const _BookingFlowDialog({required this.flight, required this.adults,
      required this.children, required this.infants, required this.seatClass});

  @override
  ConsumerState<_BookingFlowDialog> createState() => _BookingFlowDialogState();
}

class _BookingFlowDialogState extends ConsumerState<_BookingFlowDialog> {
  // ── State ────────────────────────────────────────────────────
  int _step = 0;          // 0=passengers, 1=payment, 2=success
  late List<_PaxData> _passengers;
  final _formKey = GlobalKey<FormState>();
  String _payMethod = 'wallet';
  String? _waafiReference;
  String? _mobileProofToken;
  bool _booking = false;
  String? _error;
  String _orderNumber = '';

  // ── Init ─────────────────────────────────────────────────────
  @override
  void initState() {
    super.initState();
    _passengers = [
      ...List.generate(widget.adults,   (_) => _PaxData('adult')),
      ...List.generate(widget.children, (_) => _PaxData('child')),
      ...List.generate(widget.infants,  (_) => _PaxData('infant')),
    ];
  }

  // ── Price helpers ─────────────────────────────────────────────
  double _priceFor(_PaxData pax) {
    final cls = Map<String, dynamic>.from(widget.flight['classes'] ?? {});
    final eco = (cls['economy'] as num?)?.toDouble() ?? 0;
    switch (pax.type) {
      case 'adult':   return (cls[widget.seatClass] as num?)?.toDouble() ?? eco;
      case 'child':   return (cls['child']   as num?)?.toDouble() ?? eco * 0.75;
      case 'infant':  return (cls['infant']  as num?)?.toDouble() ?? eco * 0.1;
      default:        return eco;
    }
  }

  double get _total => _passengers.fold(0.0, (s, p) => s + _priceFor(p));

  // ── Navigation between steps ──────────────────────────────────
  void _nextStep() {
    if (_step == 0) {
      if (_formKey.currentState?.validate() ?? false) {
        _formKey.currentState!.save();
        setState(() { _step = 1; _error = null; });
      }
    }
  }

  Future<void> _confirmBooking() async {
    if (_payMethod == 'wallet') {
      final ok = await showWalletPinDialog(context);
      if (!ok) return;
    } else if (_payMethod == 'mobile_pay') {
      final result = await showMobilePaySheet(context, amount: _total, description: 'eTicket Flight Booking');
      if (result?.success != true) return;
      _waafiReference = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
      _mobileProofToken = result.proofToken;
    } else if (_payMethod == 'waafi_pay') {
      final result = await showWaafiPaySheet(
        context,
        amount: _total,
        type: 'order',
        description: 'eTicket Flight Booking',
      );
      if (result?.success != true) return;
      _waafiReference = result!.reference;
    }

    setState(() { _booking = true; _error = null; });
    try {
      final svc = ModuleApiService.create();
      final resp = await svc.bookFlight({
        'flight_id':      widget.flight['id'],
        'seat_class':     widget.seatClass,
        'payment_method': _payMethod,
        'passengers':     _passengers.map((p) => p.toMap()).toList(),
        if (_waafiReference != null) 'payment_reference': _waafiReference,
      });
      final data = resp is Map ? (resp['data'] ?? {}) : {};
      final ticketOrderNum = data['order_number']?.toString();
      if (_mobileProofToken != null && ticketOrderNum != null) {
        ModuleApiService.create().attachMobilePayProof(ticketOrderNum, _mobileProofToken!);
      }
      if (mounted) {
        setState(() {
          _orderNumber = ticketOrderNum ?? 'ESH-??????';
          _step = 2;
          _booking = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _booking = false;
          _error = AppErrorHandler.message(e);
        });
      }
    }
  }

  // ── App bar title per step ────────────────────────────────────
  String get _stepTitle => _step == 0 ? 'Passenger Details'
      : _step == 1 ? 'Review & Pay'
      : 'Booking Confirmed';

  bool get _canPop => _step < 2;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Material(
      color: Colors.transparent,
      child: Container(
        width: size.width,
        height: size.height,
        color: Theme.of(context).scaffoldBackgroundColor,
        child: Column(children: [
          // ── Custom App Bar ────────────────────────────────────
          Container(
            color: context.colors.navyText,
            padding: EdgeInsets.only(
              top: MediaQuery.of(context).padding.top + 8,
              bottom: 12, left: 4, right: 16,
            ),
            child: Row(children: [
              if (_canPop)
                IconButton(
                  onPressed: () {
                    if (_step == 0) Navigator.of(context).pop();
                    else setState(() => _step--);
                  },
                  icon: const Icon(Icons.arrow_back_ios_new_rounded,
                      color: Colors.white, size: 20),
                )
              else
                const SizedBox(width: 48),
              Expanded(child: Text(_stepTitle,
                  style: TextStyle(color: Colors.white,
                      fontWeight: FontWeight.w800, fontSize: 17))),
              // Step indicator
              if (_step < 2)
                Row(mainAxisSize: MainAxisSize.min, children: List.generate(2, (i) =>
                  Container(
                    width: i == _step ? 20 : 8,
                    height: 8, margin: const EdgeInsets.only(left: 4),
                    decoration: BoxDecoration(
                      color: i == _step ? _kOrange : Colors.white30,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                )),
            ]),
          ),
          // ── Body ─────────────────────────────────────────────
          Expanded(child: AnimatedSwitcher(
            duration: const Duration(milliseconds: 300),
            transitionBuilder: (child, anim) => SlideTransition(
              position: Tween<Offset>(
                  begin: const Offset(0.08, 0), end: Offset.zero)
                  .animate(CurvedAnimation(parent: anim, curve: Curves.easeOut)),
              child: FadeTransition(opacity: anim, child: child),
            ),
            child: _step == 0 ? _buildPassengersStep()
                : _step == 1 ? _buildPaymentStep()
                : _buildSuccessStep(),
          )),
        ]),
      ),
    );
  }

  // ════════════════════════════════════════════════════════════
  // STEP 0 — Passenger Forms
  // ════════════════════════════════════════════════════════════
  Widget _buildPassengersStep() {
    final f = widget.flight;
    return Column(key: const ValueKey(0), children: [
      Expanded(child: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
          children: [
            // Mini flight header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [_kNavy, Color(0xFF0D47A1)]),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(children: [
                const Icon(Icons.flight_rounded, color: Colors.white60, size: 18),
                const SizedBox(width: 10),
                Expanded(child: Text(
                  '${f['from_city'] ?? ''} → ${f['to_city'] ?? ''}',
                  style: TextStyle(color: Colors.white,
                      fontWeight: FontWeight.w700, fontSize: 14),
                )),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: _kOrange,
                      borderRadius: BorderRadius.circular(20)),
                  child: Text(
                    widget.seatClass == 'business' ? 'Business' : 'Economy',
                    style: TextStyle(color: Colors.white,
                        fontSize: 11, fontWeight: FontWeight.w700),
                  ),
                ),
              ]),
            ),
            const SizedBox(height: 16),
            // One card per passenger
            ...List.generate(_passengers.length, (i) =>
                _PaxFormCard(
                  index: i, pax: _passengers[i],
                  totalCount: _passengers.length,
                )),
            const SizedBox(height: 16),
          ],
        ),
      )),
      // Bottom CTA
      _BottomBar(
        leftLabel: 'Passengers',
        leftValue: '${_passengers.length} pax · ${widget.seatClass == 'business' ? 'Business' : 'Economy'}',
        buttonLabel: 'Continue to Payment',
        buttonIcon: Icons.arrow_forward_rounded,
        loading: false,
        onPressed: _nextStep,
      ),
    ]);
  }

  // ════════════════════════════════════════════════════════════
  // STEP 1 — Payment Review
  // ════════════════════════════════════════════════════════════
  Widget _buildPaymentStep() {
    final f = widget.flight;
    String depTime = '—', depDate = '—', arrTime = '—';
    try { final dt = DateTime.parse(f['departure_at'].toString());
      depTime = DateFormat('HH:mm').format(dt);
      depDate = DateFormat('EEE, d MMM yyyy').format(dt);
    } catch (_) {}
    try { arrTime = DateFormat('HH:mm').format(DateTime.parse(f['arrival_at'].toString()));
    } catch (_) {}

    return Column(key: const ValueKey(1), children: [
      Expanded(child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
        children: [
          // Flight card
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topLeft, end: Alignment.bottomRight,
                colors: [_kNavy, Color(0xFF0D47A1)],
              ),
              borderRadius: BorderRadius.circular(18),
              boxShadow: [BoxShadow(color: _kNavy.withValues(alpha: 0.3),
                  blurRadius: 14, offset: const Offset(0, 5))],
            ),
            child: Column(children: [
              Row(children: [
                _AirlineLogo(logo: f['airline_logo']?.toString(),
                    name: f['airline']?.toString() ?? '', color: context.colors.cardBg),
                const SizedBox(width: 10),
                Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(f['airline']?.toString() ?? '',
                      style: TextStyle(color: Colors.white,
                          fontWeight: FontWeight.w800, fontSize: 14)),
                  Text(f['flight_number']?.toString() ?? '',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.65),
                          fontSize: 12)),
                ])),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: _kOrange,
                      borderRadius: BorderRadius.circular(20)),
                  child: Text(widget.seatClass == 'business' ? 'Business' : 'Economy',
                      style: TextStyle(color: Colors.white,
                          fontSize: 11, fontWeight: FontWeight.w700)),
                ),
              ]),
              const SizedBox(height: 16),
              Row(children: [
                Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(f['from_code']?.toString() ?? '',
                      style: TextStyle(color: Colors.white,
                          fontSize: 30, fontWeight: FontWeight.w900, height: 1)),
                  Text(f['from_city']?.toString() ?? '',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.65),
                          fontSize: 11)),
                  const SizedBox(height: 3),
                  Text(depTime, style: TextStyle(color: Colors.white,
                      fontSize: 16, fontWeight: FontWeight.w700)),
                ])),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Column(children: [
                    Icon(Icons.flight_rounded,
                        color: Colors.white.withValues(alpha: 0.55), size: 20),
                    if ((f['duration']?.toString() ?? '').isNotEmpty)
                      Text(f['duration'].toString(),
                          style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.55),
                              fontSize: 10)),
                  ]),
                ),
                Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Text(f['to_code']?.toString() ?? '',
                      style: TextStyle(color: Colors.white,
                          fontSize: 30, fontWeight: FontWeight.w900, height: 1)),
                  Text(f['to_city']?.toString() ?? '',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.65),
                          fontSize: 11)),
                  const SizedBox(height: 3),
                  Text(arrTime, style: TextStyle(color: Colors.white,
                      fontSize: 16, fontWeight: FontWeight.w700)),
                ])),
              ]),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
                ),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.calendar_today_rounded, size: 11,
                      color: Colors.white.withValues(alpha: 0.8)),
                  const SizedBox(width: 5),
                  Text(depDate, style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.9),
                      fontSize: 11, fontWeight: FontWeight.w600)),
                ]),
              ),
            ]),
          ),
          const SizedBox(height: 14),
          // Passengers
          _PayCard(title: 'Passengers', icon: Icons.people_rounded,
            child: Column(children: _passengers.asMap().entries.map((e) {
              final p = e.value;
              final typeColor = p.type == 'adult' ? _kNavy
                  : p.type == 'child' ? const Color(0xFFF59E0B)
                  : const Color(0xFFEF4444);
              return Container(
                margin: EdgeInsets.only(bottom: e.key < _passengers.length - 1 ? 10 : 0),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(color: context.colors.surfaceBg,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: _kDivider)),
                child: Row(children: [
                  Container(width: 36, height: 36,
                    decoration: BoxDecoration(
                      color: typeColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Center(child: Text(
                      (p.fullName?.isNotEmpty == true
                          ? p.fullName![0] : '?').toUpperCase(),
                      style: TextStyle(fontSize: 15,
                          fontWeight: FontWeight.w900, color: typeColor),
                    )),
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${p.title ?? ''} ${p.fullName ?? 'Passenger ${e.key + 1}'}'.trim(),
                        style: TextStyle(fontSize: 13,
                            fontWeight: FontWeight.w700, color: context.colors.navyText)),
                    Row(children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                        decoration: BoxDecoration(
                            color: typeColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(5)),
                        child: Text(p.type.toUpperCase(),
                            style: TextStyle(fontSize: 9,
                                fontWeight: FontWeight.w800, color: typeColor)),
                      ),
                      if (p.nationality?.isNotEmpty == true) ...[
                        const SizedBox(width: 6),
                        Text(p.nationality!, style: TextStyle(
                            fontSize: 11, color: _kMuted)),
                      ],
                    ]),
                  ])),
                  Text('\$${_priceFor(p).toStringAsFixed(2)}',
                      style: TextStyle(fontSize: 13,
                          fontWeight: FontWeight.w800, color: context.colors.navyText)),
                ]),
              );
            }).toList()),
          ),
          const SizedBox(height: 14),
          // Price summary
          _PayCard(title: 'Price Breakdown', icon: Icons.receipt_long_rounded,
            child: Column(children: [
              ..._passengers.asMap().entries.map((e) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(children: [
                  Expanded(child: Text(
                    '${e.value.title ?? ''} ${e.value.fullName ?? 'Pax ${e.key + 1}'} (${e.value.type})'.trim(),
                    style: TextStyle(fontSize: 12, color: _kMuted),
                    overflow: TextOverflow.ellipsis,
                  )),
                  Text('\$${_priceFor(e.value).toStringAsFixed(2)}',
                      style: TextStyle(fontSize: 12,
                          fontWeight: FontWeight.w600, color: context.colors.navyText)),
                ]),
              )),
              Divider(color: _kDivider, height: 20),
              Row(children: [
                Text('Total Amount',
                    style: TextStyle(fontSize: 15,
                        fontWeight: FontWeight.w800, color: context.colors.navyText)),
                const Spacer(),
                Text('\$${_total.toStringAsFixed(2)}',
                    style: TextStyle(fontSize: 22,
                        fontWeight: FontWeight.w900, color: _kOrange)),
              ]),
            ]),
          ),
          const SizedBox(height: 14),
          // Payment method
          _PayCard(title: 'Payment Method', icon: Icons.payment_rounded,
            child: PaymentMethodSection(
              selected: _payMethod,
              onChanged: (m) => setState(() => _payMethod = m),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF3F3),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFFCCCC)),
              ),
              child: Row(children: [
                const Icon(Icons.error_outline_rounded,
                    color: Color(0xFFEF4444), size: 18),
                const SizedBox(width: 10),
                Expanded(child: Text(_error!,
                    style: TextStyle(
                        color: Color(0xFFEF4444), fontSize: 13))),
              ]),
            ),
          ],
          const SizedBox(height: 16),
        ],
      )),
      _BottomBar(
        leftLabel: 'Total Amount',
        leftValue: '\$${_total.toStringAsFixed(2)}',
        leftValueColor: _kOrange,
        subLabel: '${_passengers.length} pax · ${widget.seatClass == 'business' ? 'Business' : 'Economy'}',
        buttonLabel: 'Confirm Booking',
        buttonIcon: Icons.lock_rounded,
        loading: _booking,
        onPressed: _confirmBooking,
      ),
    ]);
  }

  // ════════════════════════════════════════════════════════════
  // STEP 2 — Success
  // ════════════════════════════════════════════════════════════
  Widget _buildSuccessStep() {
    final f = widget.flight;
    String depDate = '—';
    try { depDate = DateFormat('EEE, d MMM yyyy').format(
        DateTime.parse(f['departure_at'].toString())); } catch (_) {}

    return Container(key: const ValueKey(2),
      color: Theme.of(context).scaffoldBackgroundColor,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            const Spacer(),
            // Animated check
            TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: 1),
              duration: const Duration(milliseconds: 700),
              curve: Curves.elasticOut,
              builder: (_, v, child) => Transform.scale(scale: v, child: child),
              child: Container(
                width: 100, height: 100,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Color(0xFF10B981), Color(0xFF059669)]),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.check_rounded, color: Colors.white, size: 52),
              ),
            ),
            const SizedBox(height: 24),
            Text('Booking Confirmed! ✈️',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 22,
                    fontWeight: FontWeight.w900, color: context.colors.navyText)),
            const SizedBox(height: 8),
            Text('Order #$_orderNumber',
                style: TextStyle(fontSize: 14, color: _kMuted)),
            const SizedBox(height: 28),
            // Ticket card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft, end: Alignment.bottomRight,
                  colors: [_kNavy, Color(0xFF0D47A1)]),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Column(children: [
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(f['from_code']?.toString() ?? '',
                        style: TextStyle(color: Colors.white,
                            fontSize: 32, fontWeight: FontWeight.w900)),
                    Text(f['from_city']?.toString() ?? '',
                        style: TextStyle(color: Colors.white70, fontSize: 11)),
                  ]),
                  const Icon(Icons.flight_rounded, color: Colors.white38, size: 30),
                  Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text(f['to_code']?.toString() ?? '',
                        style: TextStyle(color: Colors.white,
                            fontSize: 32, fontWeight: FontWeight.w900)),
                    Text(f['to_city']?.toString() ?? '',
                        style: TextStyle(color: Colors.white70, fontSize: 11)),
                  ]),
                ]),
                Divider(color: Colors.white.withValues(alpha: 0.2), height: 22),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  _InfoBadge('Date', depDate),
                  _InfoBadge('Class', widget.seatClass == 'business' ? 'Business' : 'Economy'),
                  _InfoBadge('Pax', '${_passengers.length}'),
                  _InfoBadge('Total', '\$${_total.toStringAsFixed(0)}'),
                ]),
              ]),
            ),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton(
                onPressed: () => Navigator.of(context).pop(),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _kOrange,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
                child: Text('Back to Flights',
                    style: TextStyle(color: Colors.white,
                        fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
            const SizedBox(height: 8),
          ]),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// _PaxData model
// ─────────────────────────────────────────────────────────────────────────────
class _PaxData {
  String type;
  String? title, fullName, passportNumber, nationality;
  DateTime? dob;
  _PaxData(this.type) : title = _kTitles[0];

  Map<String, dynamic> toMap() => {
    'type': type, 'title': title, 'name': fullName,
    'passport_number': passportNumber, 'nationality': nationality,
    'dob': dob != null ? DateFormat('yyyy-MM-dd').format(dob!) : null,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// _PaxFormCard — one passenger's form fields (used inside _BookingFlowDialog)
// ─────────────────────────────────────────────────────────────────────────────
class _PaxFormCard extends StatefulWidget {
  final int index, totalCount;
  final _PaxData pax;
  const _PaxFormCard({required this.index, required this.pax,
      required this.totalCount});
  @override
  State<_PaxFormCard> createState() => _PaxFormCardState();
}

class _PaxFormCardState extends State<_PaxFormCard> {
  bool _expanded = true;

  Color get _typeColor => widget.pax.type == 'adult' ? _kNavy
      : widget.pax.type == 'child' ? const Color(0xFFF59E0B)
      : const Color(0xFFEF4444);
  IconData get _typeIcon => widget.pax.type == 'adult' ? Icons.person_rounded
      : widget.pax.type == 'child' ? Icons.child_care_rounded
      : Icons.baby_changing_station_rounded;
  String get _typeLabel => widget.pax.type == 'adult' ? 'Adult'
      : widget.pax.type == 'child' ? 'Child' : 'Infant';

  InputDecoration _dec(String hint) => InputDecoration(
    hintText: hint,
    hintStyle: TextStyle(color: _kMuted, fontSize: 13),
    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: _kDivider)),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: _kDivider)),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: context.colors.navyText, width: 1.5)),
    filled: true, fillColor: context.colors.inputFill,
  );

  Widget _label(String t) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(t, style: TextStyle(fontSize: 12,
        fontWeight: FontWeight.w600, color: context.colors.navyText)),
  );

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: _kDivider),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 6)]),
      child: Column(children: [
        // Header
        GestureDetector(
          onTap: () => setState(() => _expanded = !_expanded),
          child: Container(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Container(width: 38, height: 38,
                decoration: BoxDecoration(
                  color: _typeColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(_typeIcon, color: _typeColor, size: 18)),
              const SizedBox(width: 12),
              Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Passenger ${widget.index + 1}',
                    style: TextStyle(fontWeight: FontWeight.w700,
                        fontSize: 14, color: context.colors.navyText)),
                Text(_typeLabel, style: TextStyle(fontSize: 12,
                    color: _typeColor, fontWeight: FontWeight.w600)),
              ])),
              Icon(_expanded ? Icons.keyboard_arrow_up_rounded
                  : Icons.keyboard_arrow_down_rounded,
                  color: _kMuted, size: 20),
            ]),
          ),
        ),
        if (_expanded) ...[
          Divider(height: 1, color: _kDivider),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(children: [
              // Title + Full Name row
              Row(children: [
                // Title dropdown
                SizedBox(width: 90, child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _label('Title'),
                    FormField<String>(
                      initialValue: widget.pax.title ?? _kTitles[0],
                      validator: (v) => (v == null) ? 'Required' : null,
                      onSaved: (v) => widget.pax.title = v,
                      builder: (f) => InputDecorator(
                        decoration: _dec('').copyWith(errorText: f.errorText,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 10)),
                        child: DropdownButtonHideUnderline(child: DropdownButton<String>(
                          value: f.value, isDense: true, isExpanded: true,
                          onChanged: (v) { f.didChange(v); widget.pax.title = v; },
                          items: _kTitles.map((t) => DropdownMenuItem(
                              value: t, child: Text(t, style: TextStyle(
                                  fontSize: 13)))).toList(),
                        )),
                      ),
                    ),
                  ],
                )),
                const SizedBox(width: 10),
                // Full name
                Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _label('Full Name *'),
                    TextFormField(
                      initialValue: widget.pax.fullName,
                      textCapitalization: TextCapitalization.words,
                      decoration: _dec('As in passport'),
                      validator: (v) => (v?.trim().isEmpty ?? true) ? 'Required' : null,
                      onSaved: (v) => widget.pax.fullName = v?.trim(),
                      onChanged: (v) => widget.pax.fullName = v.trim(),
                    ),
                  ],
                )),
              ]),
              const SizedBox(height: 12),
              // Passport number
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Passport Number *'),
                TextFormField(
                  initialValue: widget.pax.passportNumber,
                  textCapitalization: TextCapitalization.characters,
                  decoration: _dec('e.g. AB123456'),
                  validator: (v) => (v?.trim().isEmpty ?? true) ? 'Required' : null,
                  onSaved: (v) => widget.pax.passportNumber = v?.trim(),
                  onChanged: (v) => widget.pax.passportNumber = v.trim(),
                ),
              ]),
              const SizedBox(height: 12),
              // Nationality
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Nationality *'),
                FormField<String>(
                  initialValue: widget.pax.nationality,
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                  onSaved: (v) => widget.pax.nationality = v,
                  builder: (f) => InputDecorator(
                    decoration: _dec('Select nationality').copyWith(
                        errorText: f.errorText),
                    child: DropdownButtonHideUnderline(child: DropdownButton<String>(
                      value: f.value, isDense: true, isExpanded: true,
                      hint: Text('Select nationality',
                          style: TextStyle(color: _kMuted, fontSize: 13)),
                      onChanged: (v) {
                        f.didChange(v); widget.pax.nationality = v;
                        setState(() {});
                      },
                      items: _kCountries.map((c) => DropdownMenuItem(
                          value: c, child: Text(c, style: TextStyle(
                              fontSize: 13)))).toList(),
                    )),
                  ),
                ),
              ]),
              const SizedBox(height: 12),
              // Date of birth
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Date of Birth *'),
                FormField<DateTime>(
                  initialValue: widget.pax.dob,
                  validator: (v) => v == null ? 'Required' : null,
                  onSaved: (v) => widget.pax.dob = v,
                  builder: (f) => GestureDetector(
                    onTap: () async {
                      final now = DateTime.now();
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: f.value ?? DateTime(now.year - 25),
                        firstDate: DateTime(1920),
                        lastDate: now,
                        builder: (ctx, child) => Theme(
                          data: Theme.of(ctx).copyWith(colorScheme:
                              const ColorScheme.light(primary: _kNavy,
                                  onPrimary: Colors.white)),
                          child: child!,
                        ),
                      );
                      if (picked != null) {
                        f.didChange(picked); widget.pax.dob = picked;
                        setState(() {});
                      }
                    },
                    child: InputDecorator(
                      decoration: _dec('').copyWith(errorText: f.errorText,
                        suffixIcon: const Icon(Icons.calendar_today_outlined,
                            size: 16, color: _kMuted)),
                      child: Text(
                        widget.pax.dob != null
                            ? DateFormat('dd MMM yyyy').format(widget.pax.dob!)
                            : 'Select date of birth',
                        style: TextStyle(fontSize: 13,
                            color: widget.pax.dob != null ? _kNavy : _kMuted),
                      ),
                    ),
                  ),
                ),
              ]),
            ]),
          ),
        ],
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// _BottomBar — sticky bottom action bar used in booking flow steps
// ─────────────────────────────────────────────────────────────────────────────
class _BottomBar extends StatelessWidget {
  final String leftLabel, leftValue, buttonLabel;
  final String? subLabel;
  final Color? leftValueColor;
  final IconData buttonIcon;
  final bool loading;
  final VoidCallback onPressed;
  const _BottomBar({
    required this.leftLabel, required this.leftValue,
    required this.buttonLabel, required this.buttonIcon,
    required this.loading, required this.onPressed,
    this.subLabel, this.leftValueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        border: Border(top: BorderSide(color: _kDivider)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07),
            blurRadius: 12, offset: const Offset(0, -3))],
      ),
      child: SafeArea(top: false, child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: Row(children: [
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(leftLabel, style: TextStyle(
                fontSize: 10, color: _kMuted, fontWeight: FontWeight.w600)),
            Text(leftValue, style: TextStyle(fontSize: 20,
                fontWeight: FontWeight.w900,
                color: leftValueColor ?? _kNavy)),
            if (subLabel != null)
              Text(subLabel!, style: TextStyle(
                  fontSize: 10, color: _kMuted)),
          ]),
          const SizedBox(width: 14),
          Expanded(child: SizedBox(height: 50, child: ElevatedButton(
            onPressed: loading ? null : onPressed,
            style: ElevatedButton.styleFrom(
              backgroundColor: _kOrange,
              disabledBackgroundColor: _kOrange.withValues(alpha: 0.45),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(13)),
              elevation: 0,
            ),
            child: loading
                ? SizedBox(width: 20, height: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2.5, color: context.colors.cardBg))
                : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(buttonIcon, color: Colors.white, size: 15),
                    const SizedBox(width: 7),
                    Text(buttonLabel, style: TextStyle(
                        color: Colors.white, fontWeight: FontWeight.w800,
                        fontSize: 14)),
                  ]),
          ))),
        ]),
      )),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// _InfoBadge — used in success ticket card
// ─────────────────────────────────────────────────────────────────────────────
class _InfoBadge extends StatelessWidget {
  final String label, value;
  const _InfoBadge(this.label, this.value);
  @override
  Widget build(BuildContext context) => Column(children: [
    Text(label, style: TextStyle(color: Colors.white54, fontSize: 10)),
    const SizedBox(height: 3),
    Text(value, style: TextStyle(color: Colors.white,
        fontWeight: FontWeight.w700, fontSize: 12)),
  ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// _PayCard — section card used in payment step
// ─────────────────────────────────────────────────────────────────────────────
class _PayCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;
  const _PayCard({required this.title, required this.icon, required this.child});

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
      border: Border.all(color: _kDivider),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04),
          blurRadius: 6, offset: const Offset(0, 2))],
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
        child: Row(children: [
          Icon(icon, size: 15, color: context.colors.navyText),
          const SizedBox(width: 8),
          Text(title, style: TextStyle(fontSize: 13,
              fontWeight: FontWeight.w700, color: context.colors.navyText)),
        ]),
      ),
      Divider(height: 1, color: _kDivider),
      Padding(padding: const EdgeInsets.all(14), child: child),
    ]),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// _PayMethodTile — payment method selector tile
// ─────────────────────────────────────────────────────────────────────────────
class _PayMethodTile extends StatelessWidget {
  final String label, sub;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;
  const _PayMethodTile({required this.label, required this.sub,
      required this.icon, required this.color, required this.selected,
      required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: selected ? color.withValues(alpha: 0.06) : context.colors.cardBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
            color: selected ? color : _kDivider,
            width: selected ? 2 : 1),
      ),
      child: Row(children: [
        Container(width: 42, height: 42,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 20)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start,
            children: [
          Text(label, style: TextStyle(fontWeight: FontWeight.w700,
              fontSize: 14, color: selected ? color : _kNavy)),
          Text(sub, style: TextStyle(fontSize: 12, color: _kMuted)),
        ])),
        if (selected) Icon(Icons.check_circle_rounded, color: color, size: 22),
      ]),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// MY TICKETS TAB
// ─────────────────────────────────────────────────────────────────────────────
class _MyTicketsTab extends ConsumerWidget {
  const _MyTicketsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookingsAsync = ref.watch(_myBookingsProvider);
    return bookingsAsync.when(
      loading: () => ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: 4,
        itemBuilder: (_, __) => const _BookingCardSkeleton(),
      ),
      error: (_, __) => const Center(child: Text('Failed to load bookings')),
      data: (bookings) {
        if (bookings.isEmpty) {
          return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Icon(Icons.airplane_ticket_outlined, size: 64, color: _kDivider),
            const SizedBox(height: 16),
            Text('No tickets yet',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: context.colors.navyText)),
            const SizedBox(height: 8),
            Text('Book your first flight!',
                style: TextStyle(color: _kMuted)),
          ]));
        }
        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: bookings.length,
          itemBuilder: (_, i) => _BookingCard(booking: bookings[i]),
        );
      },
    );
  }
}

class _BookingCard extends StatelessWidget {
  final Map<String, dynamic> booking;
  const _BookingCard({required this.booking});

  @override
  Widget build(BuildContext context) {
    final status = booking['status']?.toString() ?? 'confirmed';
    final statusColor = status == 'confirmed' ? const Color(0xFF10B981)
        : status == 'cancelled' ? const Color(0xFFEF4444)
        : const Color(0xFFF59E0B);

    String? dep;
    try {
      dep = DateFormat('EEE, d MMM yyyy').format(DateTime.parse(booking['departure'] ?? ''));
    } catch (_) {}

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _kDivider),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8)],
      ),
      child: Column(children: [
        // Header
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: _kNavy.withValues(alpha: 0.04),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          ),
          child: Row(children: [
            Icon(Icons.airplane_ticket_rounded, color: context.colors.navyText, size: 18),
            const SizedBox(width: 8),
            Text('#${booking['order_number'] ?? '—'}',
                style: TextStyle(fontWeight: FontWeight.w800,
                    fontSize: 14, color: context.colors.navyText)),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: statusColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(status.toUpperCase(),
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800,
                      color: statusColor, letterSpacing: 0.5)),
            ),
          ]),
        ),
        // Route
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(children: [
            Row(children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(booking['from_code']?.toString() ?? '—',
                    style: TextStyle(fontSize: 26,
                        fontWeight: FontWeight.w900, color: context.colors.navyText)),
                Text(booking['from']?.toString() ?? '',
                    style: TextStyle(fontSize: 11, color: _kMuted)),
              ])),
              Column(children: [
                const Icon(Icons.flight_rounded, color: _kMuted, size: 20),
                if (booking['airline'] != null)
                  Text(booking['airline']!,
                      style: TextStyle(fontSize: 10, color: _kMuted)),
              ]),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text(booking['to_code']?.toString() ?? '—',
                    style: TextStyle(fontSize: 26,
                        fontWeight: FontWeight.w900, color: context.colors.navyText)),
                Text(booking['to']?.toString() ?? '',
                    style: TextStyle(fontSize: 11, color: _kMuted)),
              ])),
            ]),
            const SizedBox(height: 12),
            Row(children: [
              _InfoChip(Icons.calendar_today_rounded, dep ?? '—'),
              const SizedBox(width: 8),
              _InfoChip(Icons.people_rounded, '${(booking['passengers'] as List?)?.length ?? 1} pax'),
              const Spacer(),
              Text('\$${(booking['total_amount'] as num?)?.toStringAsFixed(2) ?? '0'}',
                  style: TextStyle(fontSize: 18,
                      fontWeight: FontWeight.w900, color: _kOrange)),
            ]),
          ]),
        ),
      ]),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  const _InfoChip(this.icon, this.label);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(8),
      border: Border.all(color: _kDivider),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 12, color: _kMuted),
      const SizedBox(width: 4),
      Text(label, style: TextStyle(fontSize: 11, color: _kMuted,
          fontWeight: FontWeight.w600)),
    ]),
  );
}

class _BookingCardSkeleton extends StatelessWidget {
  const _BookingCardSkeleton();
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    height: 160,
    decoration: BoxDecoration(
      color: context.colors.cardBg, borderRadius: BorderRadius.circular(18),
      border: Border.all(color: _kDivider),
    ),
    child: Shimmer.fromColors(
      baseColor: const Color(0xFFE8E8E8),
      highlightColor: const Color(0xFFF5F5F5),
      child: Container(color: context.colors.cardBg),
    ),
  );
}
