// ignore_for_file: use_build_context_synchronously
import 'package:flutter/material.dart';
import '../services/edata_local_cache.dart';
import '../api/module_api_service.dart';

// Keep alias so connectivity_wrapper import stays valid
typedef OfflineEdataOverlay = OfflineEdataPage;

// ─────────────────────────────────────────────────────────────────────────────
// Full-screen Navigator page — proper keyboard/focus, no Stack overlay issues
// ─────────────────────────────────────────────────────────────────────────────

class OfflineEdataPage extends StatefulWidget {
  final VoidCallback? onClose;
  const OfflineEdataPage({super.key, this.onClose});
  @override
  State<OfflineEdataPage> createState() => _OfflineEdataPageState();
}

enum _Step { provider, package, bundle, checkout }

class _OfflineEdataPageState extends State<OfflineEdataPage> {
  _Step _step = _Step.provider;

  List<dynamic> _providers = [];
  List<dynamic> _packages  = [];
  List<dynamic> _bundles   = [];
  List<dynamic> _filtered  = [];

  bool    _loading    = true;
  String? _cacheError;

  Map<String, dynamic>? _selectedProvider;
  Map<String, dynamic>? _selectedPackage;
  Map<String, dynamic>? _selectedBundle;

  final _payCtrl  = TextEditingController();
  final _dataCtrl = TextEditingController();
  String? _phoneError;

  bool    _paying     = false;
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

  // ── Data ──────────────────────────────────────────────────────────────────

  Future<void> _loadProviders() async {
    setState(() { _loading = true; _cacheError = null; });
    final list = await EdataLocalCache.loadProviders();
    if (!mounted) return;
    if (list.isEmpty) {
      setState(() {
        _loading    = false;
        _cacheError = 'Providers la\'aan.\nApp-ka internet leh hore u fur, kadib offline mode isticmaal.';
      });
    } else {
      setState(() { _loading = false; _providers = list; });
    }
  }

  Future<void> _selectProvider(Map<String, dynamic> prov) async {
    final id = int.tryParse(prov['id']?.toString() ?? '0') ?? 0;
    setState(() { _loading = true; _selectedProvider = prov; });

    final packages = await EdataLocalCache.loadPackages(id);
    final bundles  = await EdataLocalCache.loadBundles(id);
    final phones   = await EdataLocalCache.loadPhones(id);
    if (!mounted) return;

    if (phones != null) {
      _payCtrl.text  = phones['payment_phone'] ?? '';
      _dataCtrl.text = phones['data_phone']    ?? '';
    } else {
      _payCtrl.clear(); _dataCtrl.clear();
    }

    setState(() {
      _packages = packages;
      _bundles  = bundles;
      _loading  = false;
      _step     = packages.isEmpty ? _Step.bundle : _Step.package;
      if (packages.isEmpty) _filtered = bundles;
    });
  }

  void _selectPackage(Map<String, dynamic> pkg) {
    final pkgId  = pkg['id']?.toString();
    final filtered = pkgId == null
        ? _bundles
        : _bundles.where((b) => b['package_id']?.toString() == pkgId).toList();
    setState(() { _selectedPackage = pkg; _filtered = filtered; _step = _Step.bundle; });
  }

  void _selectBundle(Map<String, dynamic> b) =>
      setState(() { _selectedBundle = b; _step = _Step.checkout; });

  void _confirmPhones() {
    // kept for compatibility — not used since phones step removed
  }

  Future<void> _pay(String method) async {
    final phone = _dataCtrl.text.trim();
    setState(() { _paying = true; _payError = null; _paySuccess = null; });
    try {
      await ModuleApiService.create().purchaseData({
        'bundle_id':      _selectedBundle?['id'],
        'phone_number':   phone,
        'payment_method': method,
      });
      if (!mounted) return;
      setState(() {
        _paying     = false;
        _paySuccess = '✅ Dalabkaagu si guul leh ayuu u dirsaday!\nTelefoonkaaga eeg xaqiijinta.';
      });
    } catch (e) {
      if (!mounted) return;
      final msg   = e.toString().toLowerCase();
      final isNet = msg.contains('socket') || msg.contains('connection') ||
          msg.contains('timeout') || msg.contains('network') || msg.contains('reach');
      if (isNet) {
        await EdataLocalCache.savePendingOrder({
          'bundle_id':      _selectedBundle?['id'],
          'bundle_name':    _selectedBundle?['name'],
          'provider_id':    _selectedProvider?['id'],
          'provider_name':  _selectedProvider?['name'],
          'phone_number':   phone,
          'payment_method': method,
          'price':          _selectedBundle?['price'],
        });
        if (!mounted) return;
        setState(() {
          _paying     = false;
          _paySuccess = '📥 Order-kaaga la keydiiyay!\n\nInternet soo noqday waxaa toos u diri doonaa server-ka.\nAdmin-ku wuu arki doonaa.';
        });
      } else {
        setState(() { _paying = false; _payError = 'Khalad: $e'; });
      }
    }
  }

  void _back() {
    switch (_step) {
      case _Step.provider: Navigator.of(context).pop(); return;
      case _Step.package:
        setState(() { _step = _Step.provider; _packages = []; _bundles = []; });
      case _Step.bundle:
        if (_packages.isNotEmpty) {
          setState(() { _step = _Step.package; _filtered = []; });
        } else {
          setState(() { _step = _Step.provider; _bundles = []; _filtered = []; });
        }
      case _Step.checkout:
        setState(() { _step = _Step.bundle; _payError = null; _paySuccess = null; });
    }
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  Color _provColor([Map<String, dynamic>? p]) {
    p ??= _selectedProvider ?? {};
    final hex = (p['color']?.toString() ?? '#1565C0').replaceAll('#', '');
    final c   = int.tryParse('FF$hex', radix: 16) ?? 0xFF1565C0;
    return Color(c);
  }

  String get _stepTitle => switch (_step) {
    _Step.provider => 'Provider Dooro',
    _Step.package  => _selectedProvider?['name']?.toString() ?? 'Package Dooro',
    _Step.bundle   => _selectedPackage?['name']?.toString()  ?? 'Bundle Dooro',
    _Step.checkout => 'Dalabka Xaqiiji',
  };

  int get _stepIndex => switch (_step) {
    _Step.provider => 0,
    _Step.package  => 1,
    _Step.bundle   => _packages.isEmpty ? 1 : 2,
    _Step.checkout => _packages.isEmpty ? 2 : 3,
  };

  int get _totalSteps => _packages.isEmpty ? 3 : 4;

  String get _phonePlaceholder {
    final n = (_selectedProvider?['name'] ?? '').toString().toLowerCase();
    if (n.contains('hormuud')) return 'e.g. 61xxxxxxx';
    if (n.contains('somtel'))  return 'e.g. 62xxxxxxx';
    if (n.contains('somnet'))  return 'e.g. 68xxxxxxx';
    if (n.contains('amtel'))   return 'e.g. 71xxxxxxx';
    return 'e.g. 61xxxxxxx';
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      resizeToAvoidBottomInset: true,
      body: SafeArea(
        child: Column(children: [
          _buildHeader(),
          const Divider(height: 1, color: Color(0xFFF0F1F5)),
          Expanded(child: _buildBody()),
        ]),
      ),
    );
  }

  Widget _buildHeader() => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
    child: Row(children: [
      GestureDetector(
        onTap: _back,
        child: Container(
          width: 40, height: 40,
          decoration: BoxDecoration(
              color: const Color(0xFFF5F7FA),
              borderRadius: BorderRadius.circular(12)),
          child: const Icon(Icons.arrow_back_ios_new_rounded,
              size: 16, color: Color(0xFF07003B)),
        ),
      ),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_stepTitle,
            style: const TextStyle(
                fontWeight: FontWeight.w900, fontSize: 17, color: Color(0xFF07003B))),
        const Text('📵 Offline Mode',
            style: TextStyle(fontSize: 11, color: Color(0xFF8A8A9A))),
      ])),
      _StepDots(current: _stepIndex, total: _totalSteps),
      const SizedBox(width: 8),
      GestureDetector(
        onTap: () => Navigator.of(context).pop(),
        child: Container(
          width: 40, height: 40,
          decoration: BoxDecoration(
              color: const Color(0xFFF5F7FA),
              borderRadius: BorderRadius.circular(12)),
          child: const Icon(Icons.close_rounded, size: 18, color: Color(0xFF8A8A9A)),
        ),
      ),
    ]),
  );

  Widget _buildBody() {
    if (_loading) {
      return const Center(
          child: CircularProgressIndicator(color: Color(0xFFFF8A00)));
    }
    if (_cacheError != null) return _buildCacheError();
    return switch (_step) {
      _Step.provider => _buildProviders(),
      _Step.package  => _buildPackages(),
      _Step.bundle   => _buildBundles(),
      _Step.checkout => _buildCheckout(),
    };
  }

  // ── Steps ──────────────────────────────────────────────────────────────────

  Widget _buildProviders() => ListView.builder(
    padding: const EdgeInsets.all(16),
    itemCount: _providers.length,
    itemBuilder: (_, i) {
      final p     = Map<String, dynamic>.from(_providers[i] as Map);
      final color = _provColor(p);
      final init  = (p['name']?.toString() ?? 'P').substring(0, 1).toUpperCase();
      return _ListCard(
        onTap: () => _selectProvider(p),
        leading: Container(
          width: 50, height: 50,
          decoration:
              BoxDecoration(color: color, borderRadius: BorderRadius.circular(14)),
          child: Center(
              child: Text(init,
                  style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 22))),
        ),
        title: p['name']?.toString() ?? '',
        subtitle: 'Tap si aad u doorato',
        subtitleColor: color,
        trailing: Icon(Icons.chevron_right_rounded, color: color),
        borderColor: color.withValues(alpha: 0.25),
        bgColors: [
          color.withValues(alpha: 0.07),
          color.withValues(alpha: 0.02)
        ],
      );
    },
  );

  Widget _buildPackages() {
    if (_packages.isEmpty) {
      return Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.inbox_rounded, size: 48, color: Color(0xFFD1D5DB)),
        const SizedBox(height: 12),
        const Text('Package cache la\'aan',
            style: TextStyle(color: Color(0xFF8A8A9A))),
      ]));
    }
    final color = _provColor();
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _packages.length,
      itemBuilder: (_, i) {
        final pkg = Map<String, dynamic>.from(_packages[i] as Map);
        return _ListCard(
          onTap: () => _selectPackage(pkg),
          leading: Container(
            width: 44, height: 44,
            decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12)),
            child: Icon(Icons.inventory_2_rounded, color: color, size: 22),
          ),
          title: pkg['name']?.toString() ?? '',
          subtitle: 'Package ku dooro',
          subtitleColor: color,
          trailing: Icon(Icons.chevron_right_rounded, color: color),
        );
      },
    );
  }

  Widget _buildBundles() {
    final list  = _filtered.isEmpty ? _bundles : _filtered;
    final color = _provColor();
    if (list.isEmpty) {
      return Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.sim_card_alert_rounded,
            size: 48, color: Color(0xFFD1D5DB)),
        const SizedBox(height: 12),
        const Text('Bundle cache la\'aan',
            style: TextStyle(color: Color(0xFF8A8A9A))),
      ]));
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: list.length,
      itemBuilder: (_, i) {
        final b    = Map<String, dynamic>.from(list[i] as Map);
        final data = (b['data_amount']?.toString() ?? '').trim();
        return _ListCard(
          onTap: () => _selectBundle(b),
          leading: Container(
            width: 44, height: 44,
            decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12)),
            child: Icon(Icons.sim_card_rounded, color: color, size: 22),
          ),
          title: b['name']?.toString() ?? '',
          subtitle:
              '${data.isNotEmpty ? '${data}GB  •  ' : ''}${b['validity_days'] ?? ''} maalmood',
          trailing: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('\$${b['price'] ?? ''}',
                style: TextStyle(
                    fontWeight: FontWeight.w900, fontSize: 16, color: color)),
            const Icon(Icons.chevron_right_rounded,
                color: Color(0xFF8A8A9A), size: 18),
          ]),
        );
      },
    );
  }

  Widget _buildCheckout() {
    if (_paySuccess != null) return _buildSuccess();
    final color = _provColor();
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
      children: [
        _BundleCard(bundle: _selectedBundle ?? {}, color: color),
        const SizedBox(height: 20),

        // ── Phone number ─────────────────────────────────────────────────────
        const Text('📱 Telfoonka Data-ga',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1A2E))),
        const SizedBox(height: 4),
        const Text('Telfoonka internetka loo dira',
            style: TextStyle(fontSize: 12, color: Color(0xFF8A8A9A))),
        const SizedBox(height: 8),
        _PhoneInput(ctrl: _dataCtrl, hint: _phonePlaceholder, accent: color),

        if (_phoneError != null) ...[
          const SizedBox(height: 8),
          Text(_phoneError!,
              style: const TextStyle(color: Color(0xFFDC2626), fontSize: 12)),
        ],

        const SizedBox(height: 14),
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
                'Offline mode — order-kaaga la keydi doonaa, internet marka soo noqoto toos u diri doonaa.',
                style: TextStyle(fontSize: 12, color: Colors.orange, height: 1.4))),
          ]),
        ),

        if (_payError != null) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
                color: const Color(0xFFFEE2E2),
                borderRadius: BorderRadius.circular(10)),
            child: Text(_payError!,
                style: const TextStyle(color: Color(0xFFDC2626), fontSize: 12, height: 1.5)),
          ),
        ],
        const SizedBox(height: 22),
        const Text('Lacagta Soo Bixin',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1A1A2E))),
        const SizedBox(height: 12),
        _PayButton(
          label: '📱 Mobile Pay',
          sub: 'EVC Plus • WAAFI • E-Dahab',
          color: color,
          loading: _paying,
          onTap: () {
            if (_dataCtrl.text.trim().length < 7) {
              setState(() => _phoneError = 'Telfoon lambar sax ah geli');
              return;
            }
            setState(() => _phoneError = null);
            _pay('mobile_pay');
          },
        ),
        const SizedBox(height: 10),
        _PayButton(
          label: '💰 eSahlan Wallet',
          sub: 'eSahlan wallet-kaaga',
          color: Colors.green.shade600,
          loading: _paying,
          onTap: () {
            if (_dataCtrl.text.trim().length < 7) {
              setState(() => _phoneError = 'Telfoon lambar sax ah geli');
              return;
            }
            setState(() => _phoneError = null);
            _pay('wallet');
          },
          outlined: true,
        ),
      ],
    );
  }

  Widget _buildSuccess() => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration:
              const BoxDecoration(color: Color(0xFFD1FAE5), shape: BoxShape.circle),
          child:
              const Icon(Icons.check_circle_rounded, color: Colors.green, size: 48)),
        const SizedBox(height: 20),
        const Text('Guul!',
            style: TextStyle(
                fontWeight: FontWeight.w900,
                fontSize: 28,
                color: Color(0xFF1A1A2E))),
        const SizedBox(height: 10),
        Text(_paySuccess!,
            textAlign: TextAlign.center,
            style: const TextStyle(
                fontSize: 14, color: Color(0xFF6B7280), height: 1.6)),
        const SizedBox(height: 28),
        ElevatedButton(
          onPressed: () => Navigator.of(context).pop(),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFFFF8A00),
            foregroundColor: Colors.white,
            minimumSize: const Size(double.infinity, 52),
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14)),
          ),
          child: const Text('Xidh',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
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
        Text(_cacheError!,
            textAlign: TextAlign.center,
            style: const TextStyle(
                fontSize: 14, color: Color(0xFF8A8A9A), height: 1.6)),
        const SizedBox(height: 20),
        OutlinedButton(
            onPressed: _loadProviders, child: const Text('Dib u isku day')),
      ]),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Phone input — SizedBox + TextFormField(filled:false) — simplest reliable fix
// ─────────────────────────────────────────────────────────────────────────────

class _PhoneInput extends StatelessWidget {
  final TextEditingController ctrl;
  final String hint;
  final Color accent;
  const _PhoneInput({required this.ctrl, required this.hint, required this.accent});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 56,
      child: TextFormField(
        controller: ctrl,
        keyboardType: TextInputType.phone,
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w600,
          color: Color(0xFF1A1A2E),
        ),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(
            color: Color(0xFF9CA3AF),
            fontWeight: FontWeight.w400,
            fontSize: 15,
          ),
          prefixIcon: Icon(Icons.phone_rounded, color: accent, size: 20),
          filled: false,
          contentPadding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: accent, width: 2),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFDC2626)),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFDC2626), width: 2),
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared helpers
// ─────────────────────────────────────────────────────────────────────────────

class _ListCard extends StatelessWidget {
  final VoidCallback onTap;
  final Widget leading;
  final String title, subtitle;
  final Color subtitleColor;
  final Widget trailing;
  final Color? borderColor;
  final List<Color>? bgColors;
  const _ListCard({
    required this.onTap,
    required this.leading,
    required this.title,
    required this.subtitle,
    this.subtitleColor = const Color(0xFF8A8A9A),
    required this.trailing,
    this.borderColor,
    this.bgColors,
  });
  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: bgColors != null
                ? LinearGradient(
                    colors: bgColors!,
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight)
                : null,
            color: bgColors == null ? Colors.white : null,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor ?? const Color(0xFFF0F1F5)),
            boxShadow: [
              BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 8,
                  offset: const Offset(0, 2))
            ],
          ),
          child: Row(children: [
            leading,
            const SizedBox(width: 14),
            Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  Text(title,
                      style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          color: Color(0xFF1A1A2E))),
                  Text(subtitle,
                      style: TextStyle(fontSize: 12, color: subtitleColor)),
                ])),
            trailing,
          ]),
        ),
      );
}

class _StepDots extends StatelessWidget {
  final int current, total;
  const _StepDots({required this.current, required this.total});
  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: List.generate(
          total,
          (i) => Container(
            margin: const EdgeInsets.symmetric(horizontal: 2),
            width: i == current ? 18 : 6,
            height: 6,
            decoration: BoxDecoration(
              color: i == current
                  ? const Color(0xFFFF8A00)
                  : const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(3),
            ),
          ),
        ),
      );
}

class _BundleCard extends StatelessWidget {
  final Map<String, dynamic> bundle;
  final Color color;
  const _BundleCard({required this.bundle, required this.color});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: LinearGradient(
              colors: [color, color.withValues(alpha: 0.75)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(children: [
          const Icon(Icons.sim_card_rounded, color: Colors.white, size: 26),
          const SizedBox(width: 12),
          Expanded(
              child:
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(bundle['name']?.toString() ?? '',
                style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 14)),
            Text(
                '${(bundle['data_amount']?.toString() ?? '').isNotEmpty ? '${bundle['data_amount']}GB  •  ' : ''}${bundle['validity_days'] ?? ''} maalmood',
                style: const TextStyle(color: Colors.white70, fontSize: 11)),
          ])),
          Text('\$${bundle['price'] ?? ''}',
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 18)),
        ]),
      );
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const _InfoRow(this.icon, this.label, this.value);
  @override
  Widget build(BuildContext context) => Row(children: [
        Icon(icon, color: const Color(0xFF8A8A9A), size: 18),
        const SizedBox(width: 10),
        Text(label,
            style: const TextStyle(fontSize: 12, color: Color(0xFF8A8A9A))),
        const Spacer(),
        Text(value,
            style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 14,
                color: Color(0xFF1A1A2E))),
      ]);
}

class _PayButton extends StatelessWidget {
  final String label, sub;
  final Color color;
  final bool loading, outlined;
  final VoidCallback onTap;
  const _PayButton({
    required this.label,
    required this.sub,
    required this.color,
    required this.onTap,
    this.loading = false,
    this.outlined = false,
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
              ? Center(
                  child: SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                          color: outlined ? color : Colors.white,
                          strokeWidth: 2.5)))
              : Row(children: [
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(label,
                        style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            color: outlined ? color : Colors.white)),
                    Text(sub,
                        style: TextStyle(
                            fontSize: 11,
                            color: outlined
                                ? color.withValues(alpha: 0.7)
                                : Colors.white70)),
                  ]),
                  const Spacer(),
                  Icon(Icons.arrow_forward_ios_rounded,
                      size: 14, color: outlined ? color : Colors.white),
                ]),
        ),
      );
}
