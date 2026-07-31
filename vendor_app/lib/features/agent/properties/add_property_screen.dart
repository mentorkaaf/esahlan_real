import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/theme/vc.dart';

const _kTeal = Color(0xFF0EA5E9);

class AddPropertyScreen extends StatefulWidget {
  const AddPropertyScreen({super.key});
  @override
  State<AddPropertyScreen> createState() => _AddPropertyScreenState();
}

class _AddPropertyScreenState extends State<AddPropertyScreen> {
  final _formKey  = GlobalKey<FormState>();
  final _titleCtl = TextEditingController();
  final _descCtl  = TextEditingController();
  final _addrCtl  = TextEditingController();
  final _rentCtl  = TextEditingController();
  final _depCtl   = TextEditingController();
  final _feeCtl   = TextEditingController();
  final _areaCtl  = TextEditingController();

  String _type      = 'apartment';
  String _furnishing = 'unfurnished';
  int _bedrooms  = 1;
  int _bathrooms = 1;
  int _kitchens  = 1;
  int _livingRooms = 1;
  int? _districtId;
  List<Map> _districts = [];
  List<File> _images   = [];
  List<String> _amenities = [];
  bool _loading  = false;
  int _step = 0; // 0: basics, 1: details, 2: pricing, 3: photos

  static const _typeOpts = [
    ('apartment', 'Apartment', Icons.apartment_rounded),
    ('house',     'House',     Icons.house_rounded),
    ('villa',     'Villa',     Icons.villa_rounded),
    ('room',      'Room',      Icons.bedroom_parent_rounded),
    ('office',    'Office',    Icons.business_rounded),
    ('shop',      'Shop',      Icons.store_rounded),
  ];

  static const _furnishOpts = ['unfurnished', 'semi_furnished', 'furnished'];
  static const _amenityOpts = ['Parking', 'Garden', 'Pool', 'Security', 'Generator', 'Water Tank', 'Elevator', 'Internet', 'AC', 'Furnished Kitchen'];

  @override
  void initState() {
    super.initState();
    _loadDistricts();
  }

  @override
  void dispose() {
    _titleCtl.dispose(); _descCtl.dispose(); _addrCtl.dispose();
    _rentCtl.dispose(); _depCtl.dispose(); _feeCtl.dispose(); _areaCtl.dispose();
    super.dispose();
  }

  Future<void> _loadDistricts() async {
    try {
      final res = await AgentRepository.instance.districts();
      if (mounted) setState(() => _districts = List<Map>.from(res['data'] ?? []));
    } catch (_) {}
  }

  Future<void> _pickImages() async {
    final picker = ImagePicker();
    final picked = await picker.pickMultiImage(imageQuality: 80);
    if (picked.isNotEmpty) {
      setState(() => _images = [..._images, ...picked.map((x) => File(x.path))]);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_districtId == null) {
      _showError('Please select a district');
      return;
    }
    setState(() => _loading = true);
    try {
      await AgentRepository.instance.createProperty({
        'title':        _titleCtl.text.trim(),
        'description':  _descCtl.text.trim(),
        'type':         _type,
        'district_id':  _districtId,
        'address':      _addrCtl.text.trim(),
        'bedrooms':     _bedrooms,
        'bathrooms':    _bathrooms,
        'kitchens':     _kitchens,
        'living_rooms': _livingRooms,
        'furnishing':   _furnishing,
        'area_sqm':     _areaCtl.text.isNotEmpty ? double.tryParse(_areaCtl.text) : null,
        'monthly_rent': double.parse(_rentCtl.text),
        'deposit':      _depCtl.text.isNotEmpty ? double.tryParse(_depCtl.text) : 0,
        'brokerage_fee':_feeCtl.text.isNotEmpty ? double.tryParse(_feeCtl.text) : 0,
        'amenities':    _amenities,
      }, _images);

      if (mounted) {
        Navigator.of(context).pop(true);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Property listed successfully!'), backgroundColor: _kTeal));
      }
    } catch (e) {
      setState(() => _loading = false);
      _showError('Failed to list property. Please try again.');
    }
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: VC.red));
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg     = isDark ? VC.navy     : VC.lightBg;
    final surf   = isDark ? VC.navyLight : VC.lightSurface;
    final txt    = isDark ? VC.text     : const Color(0xFF1A2340);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: surf,
        title: const Text('List a Property'),
        leading: IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => Navigator.pop(context)),
      ),
      body: Form(
        key: _formKey,
        child: Column(children: [
          // Step indicator
          _StepBar(step: _step),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: [
                _buildStep0(txt, isDark),
                _buildStep1(txt, isDark),
                _buildStep2(txt, isDark),
                _buildStep3(txt, isDark),
              ][_step],
            ),
          ),
          // Navigation
          _buildNav(isDark),
        ]),
      ),
    );
  }

  Widget _buildStep0(Color txt, bool isDark) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _Label('Property Title', txt),
    TextFormField(controller: _titleCtl, decoration: const InputDecoration(hintText: 'e.g. Modern 2BR Apartment in Hodan'),
      validator: (v) => v?.isEmpty == true ? 'Required' : null),
    const SizedBox(height: 16),
    _Label('Property Type', txt),
    Wrap(spacing: 8, runSpacing: 8, children: _typeOpts.map((opt) {
      final (val, label, icon) = opt;
      final sel = _type == val;
      return GestureDetector(
        onTap: () => setState(() => _type = val),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: sel ? _kTeal : (isDark ? VC.navyCard : VC.lightCard),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: sel ? _kTeal : (isDark ? VC.border : VC.lightBorder)),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 16, color: sel ? Colors.white : (isDark ? VC.textSec : const Color(0xFF5A6B82))),
            const SizedBox(width: 6),
            Text(label, style: TextStyle(color: sel ? Colors.white : txt, fontWeight: FontWeight.w600, fontSize: 13)),
          ]),
        ),
      );
    }).toList()),
    const SizedBox(height: 16),
    _Label('District', txt),
    DropdownButtonFormField<int>(
      value: _districtId,
      decoration: const InputDecoration(hintText: 'Select district'),
      dropdownColor: isDark ? VC.navyCard : VC.lightSurface,
      items: _districts.map((d) => DropdownMenuItem<int>(
        value: d['id'] as int,
        child: Text(d['name'] ?? ''),
      )).toList(),
      onChanged: (v) => setState(() => _districtId = v),
      validator: (v) => v == null ? 'Required' : null,
    ),
    const SizedBox(height: 16),
    _Label('Address (optional)', txt),
    TextFormField(controller: _addrCtl, decoration: const InputDecoration(hintText: 'Street / building details')),
    const SizedBox(height: 16),
    _Label('Description (optional)', txt),
    TextFormField(controller: _descCtl, maxLines: 4, decoration: const InputDecoration(hintText: 'Describe the property...')),
  ]);

  Widget _buildStep1(Color txt, bool isDark) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _Label('Rooms', txt),
    _CounterRow('Bedrooms',    _bedrooms,    isDark, (v) => setState(() => _bedrooms    = v), txt),
    _CounterRow('Bathrooms',   _bathrooms,   isDark, (v) => setState(() => _bathrooms   = v), txt),
    _CounterRow('Kitchens',    _kitchens,    isDark, (v) => setState(() => _kitchens    = v), txt),
    _CounterRow('Living Rooms',_livingRooms, isDark, (v) => setState(() => _livingRooms = v), txt),
    const SizedBox(height: 16),
    _Label('Furnishing', txt),
    Row(children: _furnishOpts.map((f) {
      final sel = _furnishing == f;
      return Expanded(child: GestureDetector(
        onTap: () => setState(() => _furnishing = f),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          margin: const EdgeInsets.symmetric(horizontal: 3),
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: sel ? _kTeal : (isDark ? VC.navyCard : VC.lightCard),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: sel ? _kTeal : (isDark ? VC.border : VC.lightBorder)),
          ),
          child: Center(child: Text(f.replaceAll('_', ' ').toUpperCase(),
            style: TextStyle(color: sel ? Colors.white : txt, fontSize: 11, fontWeight: FontWeight.w700))),
        ),
      ));
    }).toList()),
    const SizedBox(height: 16),
    _Label('Area (sqm)', txt),
    TextFormField(controller: _areaCtl, keyboardType: TextInputType.number, decoration: const InputDecoration(hintText: 'e.g. 120')),
    const SizedBox(height: 16),
    _Label('Amenities', txt),
    Wrap(spacing: 8, runSpacing: 8, children: _amenityOpts.map((a) {
      final sel = _amenities.contains(a);
      return FilterChip(
        label: Text(a, style: TextStyle(fontSize: 12, color: sel ? Colors.white : txt)),
        selected: sel,
        onSelected: (v) => setState(() => v ? _amenities.add(a) : _amenities.remove(a)),
        backgroundColor: isDark ? VC.navyCard : VC.lightCard,
        selectedColor: _kTeal,
        checkmarkColor: Colors.white,
        side: BorderSide(color: sel ? _kTeal : (isDark ? VC.border : VC.lightBorder)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      );
    }).toList()),
  ]);

  Widget _buildStep2(Color txt, bool isDark) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _Label('Monthly Rent (USD) *', txt),
    TextFormField(
      controller: _rentCtl,
      keyboardType: TextInputType.number,
      decoration: const InputDecoration(prefixText: '\$ ', hintText: '0.00'),
      validator: (v) => (v?.isEmpty == true || double.tryParse(v ?? '') == null) ? 'Enter valid rent' : null,
    ),
    const SizedBox(height: 16),
    _Label('Security Deposit (optional)', txt),
    TextFormField(controller: _depCtl, keyboardType: TextInputType.number, decoration: const InputDecoration(prefixText: '\$ ', hintText: '0.00')),
    const SizedBox(height: 16),
    _Label('Brokerage Fee (Dalaalad)', txt),
    TextFormField(controller: _feeCtl, keyboardType: TextInputType.number, decoration: const InputDecoration(prefixText: '\$ ', hintText: '0.00')),
    const SizedBox(height: 16),
    Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _kTeal.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _kTeal.withValues(alpha: 0.2)),
      ),
      child: Row(children: [
        const Icon(Icons.info_outline_rounded, color: _kTeal, size: 18),
        const SizedBox(width: 10),
        Expanded(child: Text('You earn 10% of the brokerage fee as commission when a customer books.',
          style: TextStyle(color: txt, fontSize: 12))),
      ]),
    ),
  ]);

  Widget _buildStep3(Color txt, bool isDark) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _Label('Property Photos', txt),
    const SizedBox(height: 8),
    if (_images.isEmpty)
      GestureDetector(
        onTap: _pickImages,
        child: Container(
          height: 160,
          decoration: BoxDecoration(
            color: isDark ? VC.navyCard : VC.lightCard,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: isDark ? VC.border : VC.lightBorder, style: BorderStyle.solid),
          ),
          child: Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.add_photo_alternate_rounded, size: 48, color: _kTeal.withValues(alpha: 0.5)),
            const SizedBox(height: 8),
            Text('Tap to add photos', style: TextStyle(color: txt, fontWeight: FontWeight.w600)),
            const SizedBox(height: 4),
            const Text('Max 5MB per photo', style: TextStyle(color: Colors.grey, fontSize: 12)),
          ])),
        ),
      )
    else
      Column(children: [
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, mainAxisSpacing: 8, crossAxisSpacing: 8),
          itemCount: _images.length + 1,
          itemBuilder: (ctx, i) {
            if (i == _images.length) {
              return GestureDetector(
                onTap: _pickImages,
                child: Container(
                  decoration: BoxDecoration(
                    color: isDark ? VC.navyCard : VC.lightCard,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: isDark ? VC.border : VC.lightBorder),
                  ),
                  child: const Icon(Icons.add_rounded, color: _kTeal),
                ),
              );
            }
            return ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Stack(fit: StackFit.expand, children: [
                Image.file(_images[i], fit: BoxFit.cover),
                Positioned(top: 4, right: 4, child: GestureDetector(
                  onTap: () => setState(() => _images.removeAt(i)),
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                    child: const Icon(Icons.close, color: Colors.white, size: 12),
                  ),
                )),
              ]),
            );
          },
        ),
      ]),
  ]);

  Widget _buildNav(bool isDark) {
    return Container(
      padding: EdgeInsets.fromLTRB(20, 12, 20, MediaQuery.of(context).padding.bottom + 12),
      decoration: BoxDecoration(
        color: isDark ? VC.navyLight : VC.lightSurface,
        border: Border(top: BorderSide(color: isDark ? VC.border : VC.lightBorder)),
      ),
      child: Row(children: [
        if (_step > 0)
          Expanded(child: OutlinedButton(
            onPressed: () => setState(() => _step--),
            style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            child: const Text('Back'),
          )),
        if (_step > 0) const SizedBox(width: 12),
        Expanded(
          flex: 2,
          child: ElevatedButton(
            onPressed: _loading ? null : () {
              if (_step < 3) {
                setState(() => _step++);
              } else {
                _submit();
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: _kTeal, foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: _loading
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text(_step == 3 ? 'List Property' : 'Continue', style: const TextStyle(fontWeight: FontWeight.w800)),
          ),
        ),
      ]),
    );
  }
}

class _StepBar extends StatelessWidget {
  final int step;
  const _StepBar({required this.step});
  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final labels = ['Basics', 'Details', 'Pricing', 'Photos'];
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
      color: isDark ? VC.navyLight : VC.lightSurface,
      child: Row(children: List.generate(4, (i) {
        final done   = i < step;
        final active = i == step;
        return Expanded(child: Row(children: [
          Column(children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 28, height: 28,
              decoration: BoxDecoration(
                color: done ? _kTeal : active ? _kTeal : Colors.grey.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
              child: Center(child: done
                  ? const Icon(Icons.check_rounded, size: 16, color: Colors.white)
                  : Text('${i + 1}', style: TextStyle(color: active ? Colors.white : Colors.grey, fontSize: 12, fontWeight: FontWeight.w700))),
            ),
            const SizedBox(height: 4),
            Text(labels[i], style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: active ? _kTeal : Colors.grey)),
          ]),
          if (i < 3) Expanded(child: Container(height: 2, color: i < step ? _kTeal : Colors.grey.withValues(alpha: 0.2))),
        ]));
      })),
    );
  }
}

class _Label extends StatelessWidget {
  final String text;
  final Color color;
  const _Label(this.text, this.color);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 13)),
  );
}

class _CounterRow extends StatelessWidget {
  final String label;
  final int value;
  final bool isDark;
  final ValueChanged<int> onChanged;
  final Color txt;
  const _CounterRow(this.label, this.value, this.isDark, this.onChanged, this.txt);

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    decoration: BoxDecoration(
      color: isDark ? VC.navyCard : VC.lightCard,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: isDark ? VC.border : VC.lightBorder),
    ),
    child: Row(children: [
      Text(label, style: TextStyle(color: txt, fontWeight: FontWeight.w600)),
      const Spacer(),
      GestureDetector(
        onTap: () { if (value > 0) onChanged(value - 1); },
        child: Container(
          width: 32, height: 32,
          decoration: BoxDecoration(color: _kTeal.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
          child: const Icon(Icons.remove_rounded, color: _kTeal, size: 18),
        ),
      ),
      Padding(padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Text('$value', style: TextStyle(color: txt, fontWeight: FontWeight.w800, fontSize: 16))),
      GestureDetector(
        onTap: () => onChanged(value + 1),
        child: Container(
          width: 32, height: 32,
          decoration: BoxDecoration(color: _kTeal.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
          child: const Icon(Icons.add_rounded, color: _kTeal, size: 18),
        ),
      ),
    ]),
  );
}
