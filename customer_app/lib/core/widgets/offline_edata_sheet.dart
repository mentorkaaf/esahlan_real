// ignore_for_file: use_build_context_synchronously
import 'dart:convert';
import 'package:flutter/material.dart';
import '../services/edata_local_cache.dart';
import '../api/module_api_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Public entry-point
// ─────────────────────────────────────────────────────────────────────────────

/// Shows the offline eData purchase sheet as a full-screen modal.
/// Call this when the device has no internet but we want to let the user
/// buy data using the locally-cached provider/bundle catalogue.
Future<void> showOfflineEdataSheet(BuildContext context) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    useSafeArea: true,
    useRootNavigator: true, // must use root nav — screen is overlaid on main app
    builder: (_) => const _OfflineEdataSheet(),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main sheet widget
// ─────────────────────────────────────────────────────────────────────────────

class _OfflineEdataSheet extends StatefulWidget {
  const _OfflineEdataSheet();
  @override
  State<_OfflineEdataSheet> createState() => _OfflineEdataSheetState();
}

enum _Step { provider, bundle, phones, checkout }

class _OfflineEdataSheetState extends State<_OfflineEdataSheet> {
  _Step _step = _Step.provider;

  // cached data
  List<dynamic> _providers = [];
  List<dynamic> _bundles   = [];
  bool _loading = true;
  String? _cacheError;

  // selections
  Map<String, dynamic>? _selectedProvider;
  Map<String, dynamic>? _selectedBundle;

  // phones
  final _payCtrl  = TextEditingController();
  final _dataCtrl = TextEditingController();
  String? _phoneError;

  // checkout / payment
  bool _paying   = false;
  String? _payError;
  String? _paySuccess;

  @override
  void initState() {
    super.initState();
    _loadProviders();
  }

  @override
  void dispose() {
    _payCtrl.dispose();
    _dataCtrl.dispose();
    super.dispose();
  }

  // ── Data loading ───────────────────────────────────────────────────────────

  Future<void> _loadProviders() async {
    setState(() { _loading = true; _cacheError = null; });
    final list = await EdataLocalCache.loadProviders();
    if (!mounted) return;
    if (list.isEmpty) {
      setState(() { _loading = false; _cacheError = 'Providers la\'aaan.\nHore u fur app-ka adiga oo internet leh, kadib offline mode-ka isticmaal.'; });
    } else {
      setState(() { _loading = false; _providers = list; });
    }
  }

  Future<void> _selectProvider(Map<String, dynamic> prov) async {
    final id = int.tryParse(prov['id']?.toString() ?? '0') ?? 0;
    setState(() { _loading = true; _selectedProvider = prov; });
    final bundles = await EdataLocalCache.loadBundles(id);
    if (!mounted) return;

    // pre-load saved phones for this provider
    final phones = await EdataLocalCache.loadPhones(id);
    if (phones != null) {
      _payCtrl.text  = phones['payment_phone'] ?? '';
      _dataCtrl.text = phones['data_phone']    ?? '';
    } else {
      _payCtrl.clear(); _dataCtrl.clear();
    }

    setState(() {
      _bundles = bundles;
      _loading = false;
      _step    = _Step.bundle;
    });
  }

  void _selectBundle(Map<String, dynamic> bundle) {
    setState(() { _selectedBundle = bundle; _step = _Step.phones; });
  }

  void _confirmPhones() {
    final pay  = _payCtrl.text.trim();
    final data = _dataCtrl.text.trim();
    if (pay.length < 7 || data.length < 7) {
      setState(() => _phoneError = 'Labada phone ugu yaraan 7 lambar waa in ay leeyihiin');
      return;
    }
    setState(() { _phoneError = null; _step = _Step.checkout; });
    // save locally for future offline use
    final id = int.tryParse(_selectedProvider?['id']?.toString() ?? '0') ?? 0;
    EdataLocalCache.savePhones(id, pay, data);
  }

  Future<void> _pay(String method) async {
    setState(() { _paying = true; _payError = null; _paySuccess = null; });
    try {
      final svc = ModuleApiService.create();
      final bundleId   = _selectedBundle?['id'];
      final providerId = _selectedProvider?['id'];
      await svc.purchaseData({
        'bundle_id':     bundleId,
        'provider_id':   providerId,
        'payment_phone': _payCtrl.text.trim(),
        'data_phone':    _dataCtrl.text.trim(),
        'payment_method': method,
      });
      if (!mounted) return;
      setState(() { _paying = false; _paySuccess = '✅ Dalabkaagu si guul leh ayuu u dirsaday!\nTelefoonkaaga eeg xaqiijinta.'; });
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().toLowerCase();
      final isNetwork = msg.contains('socket') || msg.contains('connection') ||
          msg.contains('timeout') || msg.contains('network') || msg.contains('reach');
      setState(() {
        _paying = false;
        _payError = isNetwork
            ? 'Xiriirka internet ah ayaa hoos u dhacay.\n\nMobile Pay si toos ah:\n'
              '• Hormuud: *712#\n• Telesom: *152#\n• Amtel: *888#'
            : 'Khalad: $e';
      });
    }
  }

  // ── Back navigation ────────────────────────────────────────────────────────

  void _back() {
    setState(() {
      switch (_step) {
        case _Step.bundle:   _step = _Step.provider; _bundles = [];
        case _Step.phones:   _step = _Step.bundle;
        case _Step.checkout: _step = _Step.phones; _payError = null; _paySuccess = null;
        case _Step.provider: Navigator.of(context).pop();
      }
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  Color _provColor(Map<String, dynamic> p) {
    final hex = p['color']?.toString() ?? '#1565C0';
    final c = int.tryParse(hex.replaceAll('#', '0xFF')) ?? 0xFF1565C0;
    return Color(c);
  }

  String _stepTitle() => switch (_step) {
    _Step.provider => 'Provider Dooro',
    _Step.bundle   => _selectedProvider?['name'] ?? 'Bundle Dooro',
    _Step.phones   => 'Telfoonadaada',
    _Step.checkout => 'Dalabka Xaqiiji',
  };

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.92,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Column(children: [
        _buildHandle(),
        _buildHeader(),
        Expanded(child: _buildBody()),
      ]),
    );
  }

  Widget _buildHandle() => Container(
    margin: const EdgeInsets.only(top: 10),
    width: 36, height: 4,
    decoration: BoxDecoration(
      color: Colors.grey.shade300,
      borderRadius: BorderRadius.circular(2),
    ),
  );

  Widget _buildHeader() => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
    child: Row(children: [
      GestureDetector(
        onTap: _back,
        child: Container(
          width: 38, height: 38,
          decoration: BoxDecoration(
            color: const Color(0xFFF5F7FA),
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: Color(0xFF07003B)),
        ),
      ),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_stepTitle(), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17, color: Color(0xFF07003B))),
        const Text('📵 Offline Mode — Cache', style: TextStyle(fontSize: 11, color: Color(0xFF8A8A9A))),
      ])),
      // Step indicator
      _StepDots(current: _step.index, total: 4),
    ]),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator(color: Color(0xFFFF8A00)));
    if (_cacheError != null) return _buildCacheError();
    return switch (_step) {
      _Step.provider => _buildProviderStep(),
      _Step.bundle   => _buildBundleStep(),
      _Step.phones   => _buildPhonesStep(),
      _Step.checkout => _buildCheckoutStep(),
    };
  }

  // ── Step: Provider ─────────────────────────────────────────────────────────

  Widget _buildProviderStep() => ListView.builder(
    padding: const EdgeInsets.all(16),
    itemCount: _providers.length,
    itemBuilder: (_, i) {
      final p = Map<String, dynamic>.from(_providers[i] as Map);
      final color = _provColor(p);
      final initials = (p['name']?.toString() ?? 'P').substring(0, 1).toUpperCase();
      return GestureDetector(
        onTap: () => _selectProvider(p),
        child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [color.withValues(alpha: 0.08), color.withValues(alpha: 0.02)],
              begin: Alignment.topLeft, end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: color.withValues(alpha: 0.25)),
          ),
          child: Row(children: [
            Container(
              width: 50, height: 50,
              decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(14)),
              child: Center(child: Text(initials,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 20))),
            ),
            const SizedBox(width: 16),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF1A1A2E))),
              Text('Tap si aad u doorato', style: TextStyle(fontSize: 12, color: color)),
            ])),
            Icon(Icons.chevron_right_rounded, color: color),
          ]),
        ),
      );
    },
  );

  // ── Step: Bundle ───────────────────────────────────────────────────────────

  Widget _buildBundleStep() {
    if (_bundles.isEmpty) {
      return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.sim_card_alert_rounded, size: 48, color: Color(0xFFD1D5DB)),
        const SizedBox(height: 12),
        Text('${_selectedProvider?['name']} bundle cache la\'aan',
            style: const TextStyle(color: Color(0xFF8A8A9A))),
        const SizedBox(height: 8),
        const Text('Internet soo xidh bundles-ka si ay cache-gareen u helaan',
            style: TextStyle(fontSize: 12, color: Color(0xFF8A8A9A)), textAlign: TextAlign.center),
      ]));
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _bundles.length,
      itemBuilder: (_, i) {
        final b = Map<String, dynamic>.from(_bundles[i] as Map);
        final color = _provColor(_selectedProvider ?? {});
        final price = b['price']?.toString() ?? '0';
        final data  = b['data_gb']?.toString() ?? '';
        final days  = b['validity_days']?.toString() ?? '';
        return GestureDetector(
          onTap: () => _selectBundle(b),
          child: Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFF0F1F5)),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8, offset: const Offset(0, 2))],
            ),
            child: Row(children: [
              Container(
                width: 44, height: 44,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                child: Icon(Icons.sim_card_rounded, color: color, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(b['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF1A1A2E))),
                Text('$data  •  $days maalmood', style: const TextStyle(fontSize: 12, color: Color(0xFF8A8A9A))),
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text('$price SOS', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: color)),
                const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A8A9A), size: 18),
              ]),
            ]),
          ),
        );
      },
    );
  }

  // ── Step: Phones ───────────────────────────────────────────────────────────

  Widget _buildPhonesStep() {
    final color = _provColor(_selectedProvider ?? {});
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Selected bundle summary card
        _BundleSummaryCard(bundle: _selectedBundle ?? {}, color: color),
        const SizedBox(height: 24),

        const Text('💳 Telfoonka Lacagta', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFF1A1A2E))),
        const Text('Telfoonka aad ka tuurtid lacagta', style: TextStyle(fontSize: 11, color: Color(0xFF8A8A9A))),
        const SizedBox(height: 8),
        _PhoneField(ctrl: _payCtrl, hint: _phonePlaceholder, accentColor: color),
        const SizedBox(height: 16),

        const Text('📶 Telfoonka Internet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFF1A1A2E))),
        const Text('Telfoonka internetka loo rabo', style: TextStyle(fontSize: 11, color: Color(0xFF8A8A9A))),
        const SizedBox(height: 8),
        _PhoneField(ctrl: _dataCtrl, hint: _phonePlaceholder, accentColor: color),

        if (_phoneError != null) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10)),
            child: Row(children: [
              const Icon(Icons.error_outline_rounded, color: Colors.red, size: 16),
              const SizedBox(width: 8),
              Expanded(child: Text(_phoneError!, style: const TextStyle(color: Colors.red, fontSize: 12))),
            ]),
          ),
        ],
        const SizedBox(height: 28),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _confirmPhones,
            style: ElevatedButton.styleFrom(
              backgroundColor: color,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: const Text('Xigta — Dalabka Xaqiiji', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ),
        ),
      ]),
    );
  }

  String get _phonePlaceholder {
    final n = (_selectedProvider?['name'] ?? '').toString().toLowerCase();
    if (n.contains('hormuud')) return 'e.g. 61xxxxxxx';
    if (n.contains('somtel'))  return 'e.g. 62xxxxxxx';
    if (n.contains('somnet'))  return 'e.g. 68xxxxxxx';
    if (n.contains('amtel'))   return 'e.g. 71xxxxxxx';
    return 'e.g. 61xxxxxxx';
  }

  // ── Step: Checkout ─────────────────────────────────────────────────────────

  Widget _buildCheckoutStep() {
    final color = _provColor(_selectedProvider ?? {});
    if (_paySuccess != null) return _buildSuccess();

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _BundleSummaryCard(bundle: _selectedBundle ?? {}, color: color),
        const SizedBox(height: 16),

        // Phone summary
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: const Color(0xFFF5F7FA), borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            _PhoneRow(icon: Icons.credit_card_rounded, label: 'Lacagta', value: _payCtrl.text),
            const Divider(height: 20),
            _PhoneRow(icon: Icons.sim_card_rounded, label: 'Internet', value: _dataCtrl.text),
          ]),
        ),
        const SizedBox(height: 20),

        // Offline notice
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.orange.shade50,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.orange.shade200),
          ),
          child: const Row(children: [
            Icon(Icons.wifi_off_rounded, color: Colors.orange, size: 18),
            SizedBox(width: 10),
            Expanded(child: Text(
              'Offline mode — Lacagtu waxay u baahan kartaa xiriir yar. Ku isku day dalabka.',
              style: TextStyle(fontSize: 12, color: Colors.orange, height: 1.4),
            )),
          ]),
        ),
        const SizedBox(height: 24),

        if (_payError != null) ...[
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(12)),
            child: Text(_payError!, style: const TextStyle(color: Colors.red, fontSize: 12, height: 1.5)),
          ),
          const SizedBox(height: 16),
        ],

        // Payment buttons
        const Text('Lacagta Soo Bixin', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF1A1A2E))),
        const SizedBox(height: 12),
        _PayButton(
          label: '📱 Mobile Pay',
          subtitle: 'EVC Plus • WAAFI • E-Dahab',
          color: color,
          loading: _paying,
          onTap: () => _pay('mobile_pay'),
        ),
        const SizedBox(height: 10),
        _PayButton(
          label: '💰 ePay Balance',
          subtitle: 'eSahlan wallet-kaaga',
          color: Colors.green.shade600,
          loading: _paying,
          onTap: () => _pay('epay'),
          outlined: true,
        ),
      ]),
    );
  }

  Widget _buildSuccess() => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: const BoxDecoration(color: Color(0xFFD1FAE5), shape: BoxShape.circle),
          child: const Icon(Icons.check_circle_rounded, color: Colors.green, size: 48),
        ),
        const SizedBox(height: 20),
        const Text('Guul!', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 26, color: Color(0xFF1A1A2E))),
        const SizedBox(height: 10),
        Text(_paySuccess!, textAlign: TextAlign.center, style: const TextStyle(fontSize: 14, color: Color(0xFF8A8A9A), height: 1.6)),
        const SizedBox(height: 28),
        ElevatedButton(
          onPressed: () => Navigator.of(context).pop(),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFFFF8A00),
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
          child: const Text('Xidh', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        ),
      ]),
    ),
  );

  Widget _buildCacheError() => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.cloud_off_rounded, size: 56, color: Color(0xFFD1D5DB)),
        const SizedBox(height: 16),
        Text(_cacheError!, textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 14, color: Color(0xFF8A8A9A), height: 1.6)),
        const SizedBox(height: 20),
        OutlinedButton(
          onPressed: _loadProviders,
          child: const Text('Dib u isku day'),
        ),
      ]),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper widgets
// ─────────────────────────────────────────────────────────────────────────────

class _StepDots extends StatelessWidget {
  final int current, total;
  const _StepDots({required this.current, required this.total});
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: List.generate(total, (i) => Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      width: i == current ? 16 : 6, height: 6,
      decoration: BoxDecoration(
        color: i == current ? const Color(0xFFFF8A00) : const Color(0xFFE5E7EB),
        borderRadius: BorderRadius.circular(3),
      ),
    )),
  );
}

class _BundleSummaryCard extends StatelessWidget {
  final Map<String, dynamic> bundle;
  final Color color;
  const _BundleSummaryCard({required this.bundle, required this.color});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: LinearGradient(
        colors: [color, color.withValues(alpha: 0.7)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(children: [
      const Icon(Icons.sim_card_rounded, color: Colors.white, size: 28),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(bundle['name']?.toString() ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
        Text('${bundle['data_gb'] ?? ''} • ${bundle['validity_days'] ?? ''} maalmood',
            style: const TextStyle(color: Colors.white70, fontSize: 12)),
      ])),
      Text('${bundle['price'] ?? ''} SOS',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 18)),
    ]),
  );
}

class _PhoneField extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final Color accentColor;
  const _PhoneField({required this.ctrl, required this.hint, required this.accentColor});
  @override
  Widget build(BuildContext context) => TextField(
    controller: ctrl,
    keyboardType: TextInputType.phone,
    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: const Icon(Icons.phone_rounded, color: Color(0xFFFF8A00)),
      filled: true, fillColor: const Color(0xFFF5F7FA),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: accentColor, width: 2)),
    ),
  );
}

class _PhoneRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const _PhoneRow({required this.icon, required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Row(children: [
    Icon(icon, color: const Color(0xFF8A8A9A), size: 18),
    const SizedBox(width: 10),
    Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF8A8A9A))),
    const Spacer(),
    Text(value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1A2E))),
  ]);
}

class _PayButton extends StatelessWidget {
  final String label, subtitle;
  final Color color;
  final bool loading, outlined;
  final VoidCallback onTap;
  const _PayButton({
    required this.label, required this.subtitle,
    required this.color, required this.onTap,
    this.loading = false, this.outlined = false,
  });
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: loading ? null : onTap,
    child: Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 20),
      decoration: BoxDecoration(
        color: outlined ? Colors.white : color,
        border: outlined ? Border.all(color: color, width: 1.5) : null,
        borderRadius: BorderRadius.circular(14),
      ),
      child: loading
          ? Center(child: SizedBox(width: 20, height: 20,
              child: CircularProgressIndicator(color: outlined ? color : Colors.white, strokeWidth: 2.5)))
          : Row(children: [
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(label, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: outlined ? color : Colors.white)),
                Text(subtitle, style: TextStyle(fontSize: 11, color: outlined ? color.withValues(alpha: 0.7) : Colors.white70)),
              ]),
              const Spacer(),
              Icon(Icons.arrow_forward_ios_rounded, size: 14, color: outlined ? color : Colors.white),
            ]),
    ),
  );
}
