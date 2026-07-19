import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../ads/services/ad_service.dart';
import '../../../../core/theme/theme_x.dart';

final _svc = ModuleApiService.create();
final _parcelTypesProvider     = FutureProvider((_) => _svc.getParcelTypes());
final _parcelDistrictsProvider = FutureProvider((_) => _svc.getParcelDistricts());

// ── Brand colors ──────────────────────────────────────────────────────────────
const _navy  = Color(0xFF07003B);
const _amber = Color(0xFFFF8A00);
const _green = Color(0xFF22C55E);

class EParcelScreen extends ConsumerStatefulWidget {
  const EParcelScreen({super.key});
  @override
  ConsumerState<EParcelScreen> createState() => _EParcelScreenState();
}

class _EParcelScreenState extends ConsumerState<EParcelScreen>
    with SingleTickerProviderStateMixin {
  // ── form state ─────────────────────────────────────────────────────────────
  final _recipientNameCtrl  = TextEditingController();
  final _recipientPhoneCtrl = TextEditingController();
  final _descCtrl           = TextEditingController();

  int?   _parcelTypeId;
  String? _parcelTypeName;
  int?   _pickupDistrictId;
  String? _pickupDistrictName;
  int?   _deliveryDistrictId;
  String? _deliveryDistrictName;

  Map<String, dynamic>? _price;
  bool _calculating  = false;
  bool _ordering     = false;
  String _payMethod  = 'wallet';
  String? _waafiRef;

  String _senderName  = '';
  String _senderPhone = '';

  late final AnimationController _priceAnim;
  late final Animation<double>   _priceFade;

  @override
  void initState() {
    super.initState();
    AdService.instance.triggerModulePopups(context, 'eparcel');
    _priceAnim = AnimationController(vsync: this, duration: const Duration(milliseconds: 500));
    _priceFade = CurvedAnimation(parent: _priceAnim, curve: Curves.easeOut);
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('user_data');
    if (raw != null) {
      final u = jsonDecode(raw);
      setState(() { _senderName = u['name'] ?? ''; _senderPhone = u['phone'] ?? ''; });
    }
  }

  @override
  void dispose() {
    _recipientNameCtrl.dispose();
    _recipientPhoneCtrl.dispose();
    _descCtrl.dispose();
    _priceAnim.dispose();
    super.dispose();
  }

  bool get _canCalculate =>
      _parcelTypeId != null && _pickupDistrictId != null && _deliveryDistrictId != null;

  // ── build ──────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final parcelTypes = ref.watch(_parcelTypesProvider).valueOrNull?['data'] as List? ?? [];
    final districts   = ref.watch(_parcelDistrictsProvider).valueOrNull?['data'] as List? ?? [];

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      body: CustomScrollView(
        slivers: [
          // ── Hero AppBar ──────────────────────────────────────────────────
          SliverAppBar(
            expandedHeight: 160,
            pinned: true,
            backgroundColor: context.colors.navyText,
            leading: IconButton(
              icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.cardBg),
              onPressed: () => context.pop(),
            ),
            flexibleSpace: FlexibleSpaceBar(
              collapseMode: CollapseMode.pin,
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [_navy, Color(0xFF1B0F6E)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: Stack(
                  children: [
                    // decorative circles
                    Positioned(right: -30, top: -30,
                      child: Container(width: 140, height: 140,
                        decoration: BoxDecoration(shape: BoxShape.circle,
                          color: Colors.white.withOpacity(0.05)))),
                    Positioned(left: -20, bottom: -40,
                      child: Container(width: 120, height: 120,
                        decoration: BoxDecoration(shape: BoxShape.circle,
                          color: _amber.withOpacity(0.12)))),
                    // content
                    Positioned.fill(
                      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const SizedBox(height: 40),
                        Container(
                          width: 56, height: 56,
                          decoration: BoxDecoration(
                            color: _amber.withOpacity(0.15),
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(color: _amber.withOpacity(0.4)),
                          ),
                          child: const Icon(Icons.local_shipping_rounded, color: _amber, size: 28),
                        ),
                        const SizedBox(height: 10),
                        const Text('eParcel', style: TextStyle(
                          color: Colors.white, fontSize: 22,
                          fontWeight: FontWeight.w900, letterSpacing: 0.5)),
                        const Text('Fast & Reliable Delivery', style: TextStyle(
                          color: Colors.white54, fontSize: 12)),
                      ]),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ── Form body ────────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

                // ── 1. Sender card ─────────────────────────────────────────
                _StepCard(
                  step: 1, title: 'Sender Information',
                  icon: Icons.person_rounded,
                  child: Row(children: [
                    Expanded(child: _InfoChip(
                      icon: Icons.badge_outlined,
                      label: 'Name',
                      value: _senderName.isNotEmpty ? _senderName : '—',
                    )),
                    const SizedBox(width: 10),
                    Expanded(child: _InfoChip(
                      icon: Icons.phone_outlined,
                      label: 'Phone',
                      value: _senderPhone.isNotEmpty ? _senderPhone : '—',
                    )),
                  ]),
                ),
                const SizedBox(height: 14),

                // ── 2. Receiver card ───────────────────────────────────────
                _StepCard(
                  step: 2, title: 'Receiver Information',
                  icon: Icons.person_add_rounded,
                  child: Column(children: [
                    _PremiumField(
                      ctrl: _recipientNameCtrl,
                      label: 'Receiver Name',
                      hint: 'Full name',
                      icon: Icons.person_outline_rounded,
                    ),
                    const SizedBox(height: 12),
                    _PremiumField(
                      ctrl: _recipientPhoneCtrl,
                      label: 'Receiver Phone',
                      hint: '+252 xxx xxx xxx',
                      icon: Icons.phone_outlined,
                      inputType: TextInputType.phone,
                    ),
                  ]),
                ),
                const SizedBox(height: 14),

                // ── 3. Package type ────────────────────────────────────────
                _StepCard(
                  step: 3, title: 'Package Type',
                  icon: Icons.inventory_2_rounded,
                  child: parcelTypes.isEmpty
                      ? const _LoadingRow()
                      : _PremiumDropdown(
                          value: _parcelTypeName,
                          hint: 'Select package type',
                          icon: Icons.inventory_2_outlined,
                          items: parcelTypes.map<Map<String, dynamic>>((t) => {
                            'label': t['name'].toString(),
                            'sub':   t['description']?.toString() ?? '',
                          }).toList(),
                          onChanged: (label) {
                            final t = parcelTypes.firstWhere((t) => t['name'] == label);
                            setState(() {
                              _parcelTypeId   = t['id'];
                              _parcelTypeName = label;
                              _price = null;
                            });
                          },
                        ),
                ),
                const SizedBox(height: 14),

                // ── 4. Districts ───────────────────────────────────────────
                _StepCard(
                  step: 4, title: 'Route',
                  icon: Icons.route_rounded,
                  child: districts.isEmpty
                      ? const _LoadingRow()
                      : Column(children: [
                          _PremiumDropdown(
                            value: _pickupDistrictName,
                            hint: 'Pickup District',
                            icon: Icons.my_location_rounded,
                            iconColor: _green,
                            items: districts.map<Map<String, dynamic>>((d) => {
                              'label': d['name'].toString(),
                            }).toList(),
                            onChanged: (v) {
                              final d = districts.firstWhere((d) => d['name'] == v);
                              setState(() {
                                _pickupDistrictId   = d['id'];
                                _pickupDistrictName = v;
                                _price = null;
                              });
                            },
                          ),
                          const SizedBox(height: 10),
                          // arrow
                          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                            Container(width: 36, height: 1, color: AppColors.divider),
                            Container(
                              margin: const EdgeInsets.symmetric(horizontal: 8),
                              padding: const EdgeInsets.all(6),
                              decoration: BoxDecoration(
                                color: _amber.withOpacity(0.1),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.south_rounded, size: 16, color: _amber),
                            ),
                            Container(width: 36, height: 1, color: AppColors.divider),
                          ]),
                          const SizedBox(height: 10),
                          _PremiumDropdown(
                            value: _deliveryDistrictName,
                            hint: 'Delivery District',
                            icon: Icons.location_on_rounded,
                            iconColor: Colors.redAccent,
                            items: districts.map<Map<String, dynamic>>((d) => {
                              'label': d['name'].toString(),
                            }).toList(),
                            onChanged: (v) {
                              final d = districts.firstWhere((d) => d['name'] == v);
                              setState(() {
                                _deliveryDistrictId   = d['id'];
                                _deliveryDistrictName = v;
                                _price = null;
                              });
                            },
                          ),
                        ]),
                ),
                const SizedBox(height: 14),

                // ── 5. Description ─────────────────────────────────────────
                _StepCard(
                  step: 5, title: 'Package Contents',
                  icon: Icons.notes_rounded,
                  child: TextField(
                    controller: _descCtrl,
                    maxLines: 2,
                    style: const TextStyle(fontSize: 14, color: AppColors.textDark),
                    decoration: InputDecoration(
                      hintText: 'What is inside the package? (Optional)',
                      hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 13),
                      filled: true,
                      fillColor: context.colors.inputFill,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide.none),
                      enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: AppColors.divider)),
                      focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: _amber, width: 1.5)),
                    ),
                  ),
                ),
                const SizedBox(height: 24),

                // ── Calculate button ───────────────────────────────────────
                if (_price == null)
                  _GradientButton(
                    label: _calculating ? 'Calculating...' : 'Get Delivery Price',
                    icon: _calculating
                        ? null
                        : Icons.calculate_rounded,
                    loading: _calculating,
                    enabled: _canCalculate,
                    outlined: true,
                    onTap: _canCalculate ? _calculate : null,
                  ),

                // ── Price reveal ───────────────────────────────────────────
                if (_price != null) ...[
                  FadeTransition(
                    opacity: _priceFade,
                    child: _PriceCard(price: _price!),
                  ),
                  const SizedBox(height: 14),
                  // Payment method
                  Row(children: [
                    Expanded(child: _PayTile(label: 'ePay',      icon: Icons.account_balance_wallet_rounded, selected: _payMethod == 'wallet',    color: AppColors.primary,        onTap: () => setState(() => _payMethod = 'wallet'))),
                    const SizedBox(width: 10),
                    Expanded(child: _PayTile(label: 'Waafi Pay', icon: Icons.phone_android_rounded,          selected: _payMethod == 'waafi_pay', color: const Color(0xFFFF8A00), onTap: () => setState(() => _payMethod = 'waafi_pay'))),
                  ]),
                  const SizedBox(height: 14),
                  _GradientButton(
                    label: _ordering ? 'Placing Order...' : 'Send Parcel Now',
                    icon: _ordering ? null : Icons.send_rounded,
                    loading: _ordering,
                    enabled: _recipientNameCtrl.text.trim().isNotEmpty &&
                             _recipientPhoneCtrl.text.trim().isNotEmpty,
                    onTap: _placeOrder,
                  ),
                  const SizedBox(height: 8),
                  // change route button
                  Center(
                    child: TextButton.icon(
                      onPressed: () => setState(() => _price = null),
                      icon: const Icon(Icons.refresh_rounded, size: 16),
                      label: const Text('Change details'),
                      style: TextButton.styleFrom(foregroundColor: AppColors.textGrey),
                    ),
                  ),
                ],

                const SizedBox(height: 40),
              ]),
            ),
          ),
        ],
      ),
    );
  }

  // ── actions ──────────────────────────────────────────────────────────────
  Future<void> _calculate() async {
    setState(() { _calculating = true; _price = null; });
    try {
      final res = await _svc.calculateParcel({
        'parcel_type_id':       _parcelTypeId,
        'pickup_district_id':   _pickupDistrictId,
        'delivery_district_id': _deliveryDistrictId,
      });
      setState(() => _price = Map<String, dynamic>.from(res['data']));
      _priceAnim.forward(from: 0);
    } catch (e) {
      if (mounted) _snack('$e', error: true);
    } finally {
      setState(() => _calculating = false);
    }
  }

  Future<void> _placeOrder() async {
    if (_recipientNameCtrl.text.trim().isEmpty) { _snack('Enter receiver name', error: true); return; }
    if (_recipientPhoneCtrl.text.trim().isEmpty) { _snack('Enter receiver phone', error: true); return; }

    if (_payMethod == 'waafi_pay') {
      final price = double.tryParse(_price?['total']?.toString() ?? '0') ?? 0;
      final result = await showWaafiPaySheet(
        context, amount: price, type: 'order', description: 'eParcel Delivery',
        prefillPhone: _senderPhone,
      );
      if (result?.success != true) return;
      _waafiRef = result!.reference;
    }

    if (_payMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _ordering = true);
    try {
      await _svc.placeParcelOrder({
        'parcel_type_id':   _parcelTypeId,
        'pickup_address':   {'district_id': _pickupDistrictId, 'name': _senderName, 'phone': _senderPhone},
        'delivery_address': {'district_id': _deliveryDistrictId},
        'recipient_name':   _recipientNameCtrl.text.trim(),
        'recipient_phone':  _recipientPhoneCtrl.text.trim(),
        'description':      _descCtrl.text.trim(),
        'payment_method':   _payMethod,
        if (_waafiRef != null) 'payment_reference': _waafiRef,
      });
      if (_payMethod == 'wallet') ref.invalidate(walletProvider);
      if (mounted) {
        _snack('Parcel order placed successfully! 🎉');
        await Future.delayed(const Duration(milliseconds: 800));
        if (mounted) context.pop();
      }
    } catch (e) {
      if (mounted) _snack('$e', error: true);
    } finally {
      if (mounted) setState(() => _ordering = false);
    }
  }

  void _snack(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? AppColors.error : _green,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ));
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// REUSABLE COMPONENTS
// ══════════════════════════════════════════════════════════════════════════════

/// Numbered step card with title + icon
class _StepCard extends StatelessWidget {
  final int step;
  final String title;
  final IconData icon;
  final Widget child;
  const _StepCard({required this.step, required this.title, required this.icon, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 12, offset: const Offset(0, 3))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // header
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
          child: Row(children: [
            Container(
              width: 28, height: 28,
              decoration: BoxDecoration(
                color: context.colors.navyText,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Center(
                child: Text('$step', style: const TextStyle(
                    color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
              ),
            ),
            const SizedBox(width: 10),
            Icon(icon, size: 18, color: _amber),
            SizedBox(width: 6),
            Text(title, style: TextStyle(
                fontSize: 14, fontWeight: FontWeight.w800, color: context.colors.navyText)),
          ]),
        ),
        const SizedBox(height: 12),
        Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 16), child: child),
      ]),
    );
  }
}

/// Small chip showing a key-value pair
class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _InfoChip({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: context.colors.inputFill,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(children: [
        Icon(icon, size: 16, color: _amber),
        SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(fontSize: 10, color: AppColors.textGrey, fontWeight: FontWeight.w500)),
          Text(value,
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText),
            overflow: TextOverflow.ellipsis),
        ])),
      ]),
    );
  }
}

/// Styled text field
class _PremiumField extends StatelessWidget {
  final TextEditingController ctrl;
  final String label, hint;
  final IconData icon;
  final TextInputType inputType;
  const _PremiumField({
    required this.ctrl, required this.label,
    required this.hint, required this.icon,
    this.inputType = TextInputType.text,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: ctrl,
      keyboardType: inputType,
      style: TextStyle(fontSize: 14, color: context.colors.navyText, fontWeight: FontWeight.w600),
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        labelStyle: const TextStyle(fontSize: 12, color: AppColors.textGrey),
        hintStyle: const TextStyle(fontSize: 13, color: AppColors.textLight),
        prefixIcon: Icon(icon, size: 18, color: AppColors.textGrey),
        filled: true,
        fillColor: context.colors.inputFill,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.divider)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: _amber, width: 1.5)),
      ),
    );
  }
}

/// Premium bottom-sheet dropdown
class _PremiumDropdown extends StatelessWidget {
  final String? value;
  final String hint;
  final IconData icon;
  final Color? iconColor;
  final List<Map<String, dynamic>> items;
  final ValueChanged<String> onChanged;

  const _PremiumDropdown({
    required this.value, required this.hint,
    required this.icon, required this.items,
    required this.onChanged, this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _openSheet(context),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: context.colors.inputFill,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: value != null ? _amber.withOpacity(0.5) : AppColors.divider,
            width: value != null ? 1.5 : 1,
          ),
        ),
        child: Row(children: [
          Icon(icon, size: 18, color: iconColor ?? (value != null ? _amber : AppColors.textGrey)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              value ?? hint,
              style: TextStyle(
                fontSize: 14,
                fontWeight: value != null ? FontWeight.w700 : FontWeight.w400,
                color: value != null ? context.colors.navyText : AppColors.textGrey,
              ),
            ),
          ),
          Icon(Icons.keyboard_arrow_down_rounded,
              color: value != null ? _amber : AppColors.textGrey, size: 20),
        ]),
      ),
    );
  }

  void _openSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DropdownSheet(
        hint: hint,
        items: items,
        selected: value,
        onSelected: (v) { Navigator.pop(context); onChanged(v); },
      ),
    );
  }
}

class _PayTile extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool selected;
  final Color color;
  final VoidCallback onTap;
  const _PayTile({required this.label, required this.icon, required this.selected, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: selected ? color.withOpacity(0.08) : context.colors.cardBg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? color : AppColors.divider, width: selected ? 2 : 1),
        ),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, size: 17, color: selected ? color : AppColors.textGrey),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: selected ? color : AppColors.textGrey)),
        ]),
      ),
    );
  }
}

/// Bottom sheet for dropdown items with search
class _DropdownSheet extends StatefulWidget {
  final String hint;
  final List<Map<String, dynamic>> items;
  final String? selected;
  final ValueChanged<String> onSelected;
  const _DropdownSheet({required this.hint, required this.items, required this.selected, required this.onSelected});

  @override
  State<_DropdownSheet> createState() => _DropdownSheetState();
}

class _DropdownSheetState extends State<_DropdownSheet> {
  late List<Map<String, dynamic>> _filtered;
  final _search = TextEditingController();

  @override
  void initState() {
    super.initState();
    _filtered = widget.items;
    _search.addListener(() {
      final q = _search.text.toLowerCase();
      setState(() => _filtered = q.isEmpty
          ? widget.items
          : widget.items.where((i) => i['label'].toString().toLowerCase().contains(q)).toList());
    });
  }

  @override
  void dispose() { _search.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      maxChildSize: 0.92,
      minChildSize: 0.4,
      builder: (_, ctrl) => Container(
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          // handle
          Container(margin: const EdgeInsets.only(top: 12),
            width: 40, height: 4,
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          // title
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Row(children: [
              Text(widget.hint,
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: context.colors.navyText)),
            ]),
          ),
          // search
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: TextField(
              controller: _search,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'Search...',
                hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 13),
                prefixIcon: const Icon(Icons.search, size: 18, color: AppColors.textGrey),
                filled: true,
                fillColor: context.colors.inputFill,
                contentPadding: const EdgeInsets.symmetric(vertical: 10),
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.divider)),
                focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _amber, width: 1.5)),
              ),
            ),
          ),
          const Divider(height: 16),
          // list
          Expanded(
            child: ListView.builder(
              controller: ctrl,
              itemCount: _filtered.length,
              itemBuilder: (_, i) {
                final item = _filtered[i];
                final label = item['label'].toString();
                final sub   = item['sub']?.toString() ?? '';
                final isSelected = label == widget.selected;
                return ListTile(
                  onTap: () => widget.onSelected(label),
                  leading: Container(
                    width: 36, height: 36,
                    decoration: BoxDecoration(
                      color: isSelected ? _amber.withOpacity(0.15) : context.colors.inputFill,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(
                      isSelected ? Icons.check_circle_rounded : Icons.location_on_outlined,
                      color: isSelected ? _amber : AppColors.textGrey, size: 18),
                  ),
                  title: Text(label,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                      color: context.colors.navyText,
                    )),
                  subtitle: sub.isNotEmpty
                      ? Text(sub, style: const TextStyle(fontSize: 11, color: AppColors.textGrey))
                      : null,
                  trailing: isSelected
                      ? const Icon(Icons.check_rounded, color: _amber, size: 18)
                      : null,
                );
              },
            ),
          ),
        ]),
      ),
    );
  }
}

/// Animated price reveal card
class _PriceCard extends StatelessWidget {
  final Map<String, dynamic> price;
  const _PriceCard({required this.price});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [_navy, Color(0xFF2D1B8E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [BoxShadow(color: context.colors.navyText.withOpacity(0.35), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Stack(
        children: [
          // deco circles
          Positioned(right: -20, top: -20,
            child: Container(width: 100, height: 100,
              decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withOpacity(0.04)))),
          Positioned(left: -15, bottom: -25,
            child: Container(width: 80, height: 80,
              decoration: BoxDecoration(shape: BoxShape.circle, color: _amber.withOpacity(0.08)))),
          // content
          Padding(
            padding: const EdgeInsets.all(22),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // route
              Row(children: [
                _RoutePin(
                  label: '${price['from_district']}',
                  color: _green,
                  icon: Icons.my_location_rounded,
                  sub: 'Pickup',
                ),
                Expanded(child: _DashedLine()),
                const Icon(Icons.local_shipping_rounded, color: Colors.white38, size: 22),
                Expanded(child: _DashedLine()),
                _RoutePin(
                  label: '${price['to_district']}',
                  color: Colors.redAccent,
                  icon: Icons.location_on_rounded,
                  sub: 'Delivery',
                  align: CrossAxisAlignment.end,
                ),
              ]),
              const SizedBox(height: 20),
              Container(height: 1, color: Colors.white12),
              const SizedBox(height: 18),
              // price
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Delivery Price', style: TextStyle(color: Colors.white60, fontSize: 12)),
                  const SizedBox(height: 4),
                  Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text('\$', style: TextStyle(color: _amber.withOpacity(0.8), fontSize: 16, fontWeight: FontWeight.w700)),
                    Text('${price['total']}',
                      style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w900, height: 1)),
                  ]),
                  Text('${price['currency'] ?? 'USD'} • Flat Rate',
                    style: const TextStyle(color: Colors.white38, fontSize: 11)),
                ]),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: _green.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: _green.withOpacity(0.3)),
                  ),
                  child: Column(children: [
                    const Icon(Icons.verified_rounded, color: _green, size: 22),
                    const SizedBox(height: 4),
                    const Text('Cash on\nDelivery', textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white70, fontSize: 10, fontWeight: FontWeight.w600)),
                  ]),
                ),
              ]),
            ]),
          ),
        ],
      ),
    );
  }
}

class _RoutePin extends StatelessWidget {
  final String label, sub;
  final Color color;
  final IconData icon;
  final CrossAxisAlignment align;
  const _RoutePin({required this.label, required this.sub,
    required this.color, required this.icon,
    this.align = CrossAxisAlignment.start});

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: align, children: [
    Icon(icon, color: color, size: 18),
    const SizedBox(height: 4),
    SizedBox(
      width: 72,
      child: Text(label,
        textAlign: align == CrossAxisAlignment.end ? TextAlign.right : TextAlign.left,
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12),
        maxLines: 2, overflow: TextOverflow.ellipsis),
    ),
    Text(sub, style: TextStyle(color: color.withOpacity(0.8), fontSize: 10)),
  ]);
}

class _DashedLine extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 4),
    child: LayoutBuilder(builder: (_, c) {
      final count = (c.maxWidth / 6).floor();
      return Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: List.generate(count, (_) => Container(
          width: 3, height: 1, color: Colors.white24)),
      );
    }),
  );
}

/// Gradient action button
class _GradientButton extends StatelessWidget {
  final String label;
  final IconData? icon;
  final bool loading, enabled, outlined;
  final VoidCallback? onTap;
  const _GradientButton({
    required this.label, this.icon,
    this.loading = false, this.enabled = true,
    this.outlined = false, this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: (enabled && !loading) ? onTap : null,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: double.infinity,
        height: 56,
        decoration: BoxDecoration(
          gradient: (!enabled || loading)
              ? null
              : outlined
                  ? null
                  : const LinearGradient(colors: [_amber, Color(0xFFFF6B00)]),
          color: (!enabled || loading)
              ? Colors.grey.shade300
              : outlined ? Colors.transparent : null,
          border: outlined && enabled ? Border.all(color: _amber, width: 2) : null,
          borderRadius: BorderRadius.circular(16),
          boxShadow: (!outlined && enabled && !loading)
              ? [BoxShadow(color: _amber.withOpacity(0.35), blurRadius: 16, offset: const Offset(0, 6))]
              : null,
        ),
        child: Center(
          child: loading
              ? const SizedBox(width: 22, height: 22,
                  child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
              : Row(mainAxisSize: MainAxisSize.min, children: [
                  if (icon != null) ...[
                    Icon(icon, size: 20,
                      color: outlined ? _amber : Colors.white),
                    const SizedBox(width: 8),
                  ],
                  Text(label, style: TextStyle(
                    fontSize: 15, fontWeight: FontWeight.w800,
                    color: (!enabled || loading)
                        ? Colors.white60
                        : outlined ? _amber : Colors.white,
                  )),
                ]),
        ),
      ),
    );
  }
}

class _LoadingRow extends StatelessWidget {
  const _LoadingRow();
  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 8),
    child: Center(child: SizedBox(width: 24, height: 24,
      child: CircularProgressIndicator(strokeWidth: 2, color: _amber))),
  );
}
