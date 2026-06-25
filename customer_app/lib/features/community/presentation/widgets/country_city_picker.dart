import 'package:flutter/material.dart';
import '../../../../core/api/api_client.dart';
import '../screens/community_shell.dart';

class CountryCityPicker extends StatefulWidget {
  final String? initialCountry;
  final String? initialCity;
  final void Function(String country, String countryCode, String city) onChanged;
  const CountryCityPicker({super.key, this.initialCountry, this.initialCity, required this.onChanged});
  @override
  State<CountryCityPicker> createState() => _CountryCityPickerState();
}

class _CountryCityPickerState extends State<CountryCityPicker> {
  List<Map<String, dynamic>> _countries = [];
  List<String> _cities = [];
  String? _selectedCountryCode;
  String? _selectedCountryName;
  String? _selectedCity;
  bool _loadingCountries = true;
  bool _loadingCities = false;

  @override
  void initState() {
    super.initState();
    _loadCountries();
  }

  Future<void> _loadCountries() async {
    try {
      final r = await ApiClient.instance.get('/community/geo/countries');
      final list = (r.data['data'] as List).cast<Map<String, dynamic>>();
      setState(() { _countries = list; _loadingCountries = false; });
      if (widget.initialCountry != null) {
        final match = list.where((c) => c['name'] == widget.initialCountry).firstOrNull;
        if (match != null) {
          _selectedCountryCode = match['code'];
          _selectedCountryName = match['name'];
          _loadCities(match['code']);
        }
      }
    } catch (_) { setState(() => _loadingCountries = false); }
  }

  Future<void> _loadCities(String code) async {
    setState(() { _loadingCities = true; _cities = []; _selectedCity = null; });
    try {
      final r = await ApiClient.instance.get('/community/geo/cities/$code');
      final list = (r.data['data'] as List).cast<String>();
      setState(() { _cities = list; _loadingCities = false; });
      if (widget.initialCity != null && list.contains(widget.initialCity)) {
        _selectedCity = widget.initialCity;
      }
    } catch (_) { setState(() => _loadingCities = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Country', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF374151))),
      const SizedBox(height: 8),
      GestureDetector(
        onTap: () => _showCountryPicker(),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _selectedCountryName != null ? kOrange : const Color(0xFFE5E7EB))),
          child: Row(children: [
            if (_selectedCountryCode != null) ...[
              Text(_countries.firstWhere((c) => c['code'] == _selectedCountryCode, orElse: () => {'flag': ''})['flag'] ?? '', style: const TextStyle(fontSize: 20)),
              const SizedBox(width: 10),
            ],
            Expanded(child: Text(_selectedCountryName ?? 'Select country',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: _selectedCountryName != null ? const Color(0xFF1A1B2E) : const Color(0xFF9CA3AF)))),
            const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF9CA3AF)),
          ]),
        ),
      ),
      const SizedBox(height: 16),

      const Text('City', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF374151))),
      const SizedBox(height: 8),
      GestureDetector(
        onTap: _selectedCountryCode != null ? () => _showCityPicker() : null,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _selectedCity != null ? kOrange : const Color(0xFFE5E7EB))),
          child: Row(children: [
            const Icon(Icons.location_city_rounded, size: 20, color: Color(0xFF9CA3AF)),
            const SizedBox(width: 10),
            Expanded(child: Text(
              _loadingCities ? 'Loading cities...' : (_selectedCity ?? (_selectedCountryCode != null ? 'Select city' : 'Select country first')),
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600,
                color: _selectedCity != null ? const Color(0xFF1A1B2E) : const Color(0xFF9CA3AF)))),
            const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF9CA3AF)),
          ]),
        ),
      ),
    ]);
  }

  void _showCountryPicker() {
    showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => _SearchSheet(
        title: 'Select Country',
        items: _countries.map((c) => {'label': '${c['flag']} ${c['name']}', 'value': c['code'], 'name': c['name']}).toList(),
        onSelected: (item) {
          setState(() { _selectedCountryCode = item['value']; _selectedCountryName = item['name']; _selectedCity = null; });
          _loadCities(item['value']!);
          Navigator.pop(context);
        },
      ),
    );
  }

  void _showCityPicker() {
    showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => _SearchSheet(
        title: 'Select City',
        items: _cities.map((c) => {'label': c, 'value': c, 'name': c}).toList(),
        onSelected: (item) {
          setState(() => _selectedCity = item['value']);
          widget.onChanged(_selectedCountryName!, _selectedCountryCode!, _selectedCity!);
          Navigator.pop(context);
        },
      ),
    );
  }
}

class _SearchSheet extends StatefulWidget {
  final String title;
  final List<Map<String, String?>> items;
  final void Function(Map<String, String?>) onSelected;
  const _SearchSheet({required this.title, required this.items, required this.onSelected});
  @override
  State<_SearchSheet> createState() => _SearchSheetState();
}

class _SearchSheetState extends State<_SearchSheet> {
  String _search = '';
  @override
  Widget build(BuildContext context) {
    final filtered = _search.isEmpty ? widget.items : widget.items.where((i) => (i['label'] ?? '').toLowerCase().contains(_search.toLowerCase())).toList();
    return Container(
      height: MediaQuery.of(context).size.height * 0.7,
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      child: Column(children: [
        const SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2))),
        Padding(padding: const EdgeInsets.all(16), child: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18))),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: TextField(
          autofocus: true, onChanged: (v) => setState(() => _search = v),
          decoration: InputDecoration(hintText: 'Search...', prefixIcon: const Icon(Icons.search, size: 20),
            filled: true, fillColor: const Color(0xFFF9FAFB), contentPadding: const EdgeInsets.symmetric(vertical: 10),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none)),
        )),
        const SizedBox(height: 8),
        Expanded(child: ListView.builder(
          itemCount: filtered.length,
          itemBuilder: (_, i) => ListTile(
            title: Text(filtered[i]['label'] ?? '', style: const TextStyle(fontSize: 15)),
            onTap: () => widget.onSelected(filtered[i]),
          ),
        )),
      ]),
    );
  }
}
