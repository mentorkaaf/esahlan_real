import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../../data/repositories/global_repository.dart';

// Same country list as checkout
const _kAddrCountries = [
  ('US', '🇺🇸', 'United States'),
  ('GB', '🇬🇧', 'United Kingdom'),
  ('CA', '🇨🇦', 'Canada'),
  ('AU', '🇦🇺', 'Australia'),
  ('DE', '🇩🇪', 'Germany'),
  ('FR', '🇫🇷', 'France'),
  ('NL', '🇳🇱', 'Netherlands'),
  ('SE', '🇸🇪', 'Sweden'),
  ('NO', '🇳🇴', 'Norway'),
  ('DK', '🇩🇰', 'Denmark'),
  ('FI', '🇫🇮', 'Finland'),
  ('CH', '🇨🇭', 'Switzerland'),
  ('AT', '🇦🇹', 'Austria'),
  ('BE', '🇧🇪', 'Belgium'),
  ('IT', '🇮🇹', 'Italy'),
  ('ES', '🇪🇸', 'Spain'),
  ('PT', '🇵🇹', 'Portugal'),
  ('IE', '🇮🇪', 'Ireland'),
  ('NZ', '🇳🇿', 'New Zealand'),
  ('SG', '🇸🇬', 'Singapore'),
  ('AE', '🇦🇪', 'UAE'),
  ('SA', '🇸🇦', 'Saudi Arabia'),
  ('QA', '🇶🇦', 'Qatar'),
  ('KW', '🇰🇼', 'Kuwait'),
  ('BH', '🇧🇭', 'Bahrain'),
  ('OM', '🇴🇲', 'Oman'),
  ('ET', '🇪🇹', 'Ethiopia'),
  ('KE', '🇰🇪', 'Kenya'),
  ('NG', '🇳🇬', 'Nigeria'),
  ('ZA', '🇿🇦', 'South Africa'),
  ('EG', '🇪🇬', 'Egypt'),
  ('SO', '🇸🇴', 'Somalia'),
  ('DJ', '🇩🇯', 'Djibouti'),
  ('TR', '🇹🇷', 'Turkey'),
  ('IN', '🇮🇳', 'India'),
  ('PK', '🇵🇰', 'Pakistan'),
  ('JP', '🇯🇵', 'Japan'),
  ('CN', '🇨🇳', 'China'),
  ('KR', '🇰🇷', 'South Korea'),
  ('MY', '🇲🇾', 'Malaysia'),
  ('ID', '🇮🇩', 'Indonesia'),
  ('PH', '🇵🇭', 'Philippines'),
  ('TH', '🇹🇭', 'Thailand'),
  ('BR', '🇧🇷', 'Brazil'),
  ('MX', '🇲🇽', 'Mexico'),
];

(String, String, String) _findCountry(String code) =>
    _kAddrCountries.firstWhere((c) => c.$1 == code,
        orElse: () => const ('US', '🇺🇸', 'United States'));

class GlobalAddressScreen extends ConsumerStatefulWidget {
  const GlobalAddressScreen({super.key});

  @override
  ConsumerState<GlobalAddressScreen> createState() =>
      _GlobalAddressScreenState();
}

class _GlobalAddressScreenState extends ConsumerState<GlobalAddressScreen> {
  final _formKey = GlobalKey<FormState>();

  final _nameCtrl  = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addr1Ctrl = TextEditingController();
  final _addr2Ctrl = TextEditingController();
  final _cityCtrl  = TextEditingController();
  final _stateCtrl = TextEditingController();
  final _zipCtrl   = TextEditingController();
  String _country  = 'US';

  bool _loading = false;
  bool _prefilled = false;
  GlobalAddress? _existingAddress;

  @override
  void initState() {
    super.initState();
    // Pre-fill from saved address after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) => _prefill());
  }

  void _prefill() {
    final auth = ref.read(globalAuthProvider).valueOrNull;
    if (auth == null || _prefilled) return;
    _prefilled = true;

    final addr = auth.addresses.isNotEmpty
        ? auth.addresses.firstWhere((a) => a.isDefault,
            orElse: () => auth.addresses.first)
        : null;

    if (addr != null) {
      _existingAddress = addr;
      _nameCtrl.text  = addr.name;
      _phoneCtrl.text = addr.phone ?? '';
      _addr1Ctrl.text = addr.addressLine1;
      _addr2Ctrl.text = addr.addressLine2 ?? '';
      _cityCtrl.text  = addr.city;
      _stateCtrl.text = addr.state ?? '';
      _zipCtrl.text   = addr.zip;
      final code = addr.country.length == 2
          ? addr.country.toUpperCase()
          : 'US';
      if (mounted) setState(() => _country = code);
    } else {
      // Pre-fill name from user profile
      _nameCtrl.text = auth.name;
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _addr1Ctrl.dispose();
    _addr2Ctrl.dispose();
    _cityCtrl.dispose();
    _stateCtrl.dispose();
    _zipCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);

    try {
      final repo = ref.read(globalRepoProvider);
      final payload = {
        'name':          _nameCtrl.text.trim(),
        'phone':         _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
        'address_line1': _addr1Ctrl.text.trim(),
        'address_line2': _addr2Ctrl.text.trim().isEmpty ? null : _addr2Ctrl.text.trim(),
        'city':          _cityCtrl.text.trim(),
        'state':         _stateCtrl.text.trim().isEmpty ? null : _stateCtrl.text.trim(),
        'zip':           _zipCtrl.text.trim().isEmpty ? null : _zipCtrl.text.trim(),
        'country':       _country,
        'is_default':    true,
      };

      if (_existingAddress != null) {
        await repo.updateAddress(_existingAddress!.id, payload);
      } else {
        await repo.addAddress(payload);
      }

      // Refresh auth so checkout sees updated address
      await ref.read(globalAuthProvider.notifier).fetchMe();

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('✓ Address saved!'),
          backgroundColor: Colors.green,
          behavior: SnackBarBehavior.floating,
        ));
        context.pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.toString()),
          backgroundColor: Colors.red.shade700,
          behavior: SnackBarBehavior.floating,
        ));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final authAsync = ref.watch(globalAuthProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        title: Text(
          _existingAddress != null ? 'Edit Address' : 'Add Address',
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: SizedBox(
            height: 52,
            child: ElevatedButton(
              onPressed: _loading ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF59E0B),
                foregroundColor: const Color(0xFF1A1A2E),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
              child: _loading
                  ? const SizedBox(
                      width: 22, height: 22,
                      child: CircularProgressIndicator(
                          color: Color(0xFF1A1A2E), strokeWidth: 2.5))
                  : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Icon(Icons.check_rounded, size: 18),
                      const SizedBox(width: 8),
                      Text(
                        _existingAddress != null
                            ? 'Save Changes'
                            : 'Add Address',
                        style: const TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 15),
                      ),
                    ]),
            ),
          ),
        ),
      ),
      body: authAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (_) => Form(
          key: _formKey,
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _card(children: [
                  _sectionLabel('Contact Info'),

                  // Full name
                  _FField(
                    ctrl: _nameCtrl,
                    label: 'Full Name *',
                    hint: 'Your name',
                    textCapitalization: TextCapitalization.words,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),

                  // Phone (optional)
                  _FField(
                    ctrl: _phoneCtrl,
                    label: 'Phone Number',
                    hint: 'Optional — for delivery updates',
                    keyboard: TextInputType.phone,
                  ),
                ]),

                const SizedBox(height: 14),

                _card(children: [
                  _sectionLabel('Shipping Address'),

                  // Street address
                  _FField(
                    ctrl: _addr1Ctrl,
                    label: 'Street Address *',
                    hint: '123 Main St',
                    textCapitalization: TextCapitalization.words,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),

                  // Apt/Suite (optional)
                  _FField(
                    ctrl: _addr2Ctrl,
                    label: 'Apt / Suite / Floor',
                    hint: 'Optional',
                  ),
                  const SizedBox(height: 12),

                  // City + State row
                  Row(children: [
                    Expanded(
                      flex: 3,
                      child: _FField(
                        ctrl: _cityCtrl,
                        label: 'City *',
                        textCapitalization: TextCapitalization.words,
                        validator: (v) =>
                            v == null || v.trim().isEmpty ? 'Required' : null,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      flex: 2,
                      child: _FField(
                        ctrl: _stateCtrl,
                        label: 'State / Province',
                        hint: 'Optional',
                        textCapitalization: TextCapitalization.words,
                      ),
                    ),
                  ]),
                  const SizedBox(height: 12),

                  // ZIP + Country row
                  Row(children: [
                    SizedBox(
                      width: 110,
                      child: _FField(
                        ctrl: _zipCtrl,
                        label: 'ZIP / Postal',
                        hint: 'Optional',
                        keyboard: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(
                              RegExp(r'[0-9A-Za-z\- ]'))
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(child: _CountryPicker(
                      selected: _country,
                      onChanged: (c) => setState(() => _country = c),
                    )),
                  ]),
                ]),

                const SizedBox(height: 80),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _card({required List<Widget> children}) => Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 8,
                offset: const Offset(0, 2))
          ],
        ),
        padding: const EdgeInsets.all(16),
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: children),
      );

  Widget _sectionLabel(String label) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Text(label,
            style: const TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 14,
                color: Color(0xFF1A1A2E))),
      );
}

// ── Country picker ────────────────────────────────────────────────────────────

class _CountryPicker extends StatelessWidget {
  final String selected;
  final ValueChanged<String> onChanged;
  const _CountryPicker({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final c = _findCountry(selected);
    return GestureDetector(
      onTap: () => _showSheet(context),
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.grey.shade300),
        ),
        child: Row(children: [
          Text(c.$2, style: const TextStyle(fontSize: 20)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(c.$3,
                style: const TextStyle(
                    fontSize: 13, fontWeight: FontWeight.w600),
                overflow: TextOverflow.ellipsis),
          ),
          Icon(Icons.expand_more, color: Colors.grey.shade400, size: 18),
        ]),
      ),
    );
  }

  void _showSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CountrySheet(
        selected: selected,
        onPick: (code) {
          onChanged(code);
          Navigator.pop(context);
        },
      ),
    );
  }
}

class _CountrySheet extends StatefulWidget {
  final String selected;
  final ValueChanged<String> onPick;
  const _CountrySheet({required this.selected, required this.onPick});
  @override
  State<_CountrySheet> createState() => _CountrySheetState();
}

class _CountrySheetState extends State<_CountrySheet> {
  String _q = '';
  List<(String, String, String)> get _filtered => _q.isEmpty
      ? _kAddrCountries
      : _kAddrCountries
          .where((c) =>
              c.$3.toLowerCase().contains(_q.toLowerCase()) ||
              c.$1.toLowerCase().contains(_q.toLowerCase()))
          .toList();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(children: [
        const SizedBox(height: 12),
        Container(
            width: 36, height: 4,
            decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2))),
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text('Select Country',
                style:
                    TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: TextField(
            onChanged: (v) => setState(() => _q = v),
            decoration: InputDecoration(
              prefixIcon:
                  const Icon(Icons.search, size: 18, color: Colors.grey),
              hintText: 'Search country...',
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: Colors.grey.shade300)),
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: Colors.grey.shade300)),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Expanded(
          child: ListView.builder(
            itemCount: _filtered.length,
            itemBuilder: (_, i) {
              final c   = _filtered[i];
              final sel = c.$1 == widget.selected;
              return ListTile(
                leading: Text(c.$2, style: const TextStyle(fontSize: 22)),
                title: Text(c.$3,
                    style: TextStyle(
                        fontWeight:
                            sel ? FontWeight.w700 : FontWeight.w500,
                        fontSize: 14)),
                trailing: sel
                    ? const Icon(Icons.check_circle_rounded,
                        color: Color(0xFFF59E0B))
                    : null,
                onTap: () => widget.onPick(c.$1),
              );
            },
          ),
        ),
      ]),
    );
  }
}

// ── Form field widget ─────────────────────────────────────────────────────────

class _FField extends StatelessWidget {
  final TextEditingController ctrl;
  final String label;
  final String? hint;
  final TextInputType? keyboard;
  final TextCapitalization textCapitalization;
  final List<TextInputFormatter>? inputFormatters;
  final String? Function(String?)? validator;

  const _FField({
    required this.ctrl,
    required this.label,
    this.hint,
    this.keyboard,
    this.textCapitalization = TextCapitalization.none,
    this.inputFormatters,
    this.validator,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: ctrl,
      keyboardType: keyboard,
      textCapitalization: textCapitalization,
      inputFormatters: inputFormatters,
      validator: validator,
      style: const TextStyle(fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: BorderSide(color: Colors.grey.shade300)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: BorderSide(color: Colors.grey.shade300)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(
                color: Color(0xFF6366F1), width: 1.8)),
        labelStyle: TextStyle(color: Colors.grey.shade600, fontSize: 13),
        hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 12),
        filled: true,
        fillColor: Colors.white,
      ),
    );
  }
}
