import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_theme.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Country data
// ─────────────────────────────────────────────────────────────────────────────

class CountryCode {
  final String name;
  final String dialCode; // e.g. '+252'
  final String flag;     // emoji
  final String iso;      // e.g. 'SO'
  final int maxDigits;   // max local digits (generous — capped at 15 for safety)

  const CountryCode({
    required this.name,
    required this.dialCode,
    required this.flag,
    required this.iso,
    this.maxDigits = 12,
  });

  String get dialCodeNumeric => dialCode.replaceAll('+', '');
}

const List<CountryCode> kAllCountries = [
  // ── Preferred / top of list ──────────────────────────────────────────────
  CountryCode(name: 'Somalia',              dialCode: '+252', flag: '🇸🇴', iso: 'SO', maxDigits: 9),
  CountryCode(name: 'Ethiopia',             dialCode: '+251', flag: '🇪🇹', iso: 'ET', maxDigits: 9),
  CountryCode(name: 'Kenya',                dialCode: '+254', flag: '🇰🇪', iso: 'KE', maxDigits: 9),
  CountryCode(name: 'Djibouti',             dialCode: '+253', flag: '🇩🇯', iso: 'DJ', maxDigits: 8),
  CountryCode(name: 'United Arab Emirates', dialCode: '+971', flag: '🇦🇪', iso: 'AE', maxDigits: 9),
  CountryCode(name: 'Saudi Arabia',         dialCode: '+966', flag: '🇸🇦', iso: 'SA', maxDigits: 9),
  CountryCode(name: 'United States',        dialCode: '+1',   flag: '🇺🇸', iso: 'US', maxDigits: 10),
  CountryCode(name: 'United Kingdom',       dialCode: '+44',  flag: '🇬🇧', iso: 'GB', maxDigits: 10),

  // ── Africa ───────────────────────────────────────────────────────────────
  CountryCode(name: 'Algeria',              dialCode: '+213', flag: '🇩🇿', iso: 'DZ', maxDigits: 9),
  CountryCode(name: 'Angola',               dialCode: '+244', flag: '🇦🇴', iso: 'AO', maxDigits: 9),
  CountryCode(name: 'Benin',                dialCode: '+229', flag: '🇧🇯', iso: 'BJ', maxDigits: 8),
  CountryCode(name: 'Botswana',             dialCode: '+267', flag: '🇧🇼', iso: 'BW', maxDigits: 8),
  CountryCode(name: 'Burkina Faso',         dialCode: '+226', flag: '🇧🇫', iso: 'BF', maxDigits: 8),
  CountryCode(name: 'Burundi',              dialCode: '+257', flag: '🇧🇮', iso: 'BI', maxDigits: 8),
  CountryCode(name: 'Cameroon',             dialCode: '+237', flag: '🇨🇲', iso: 'CM', maxDigits: 8),
  CountryCode(name: 'Cape Verde',           dialCode: '+238', flag: '🇨🇻', iso: 'CV', maxDigits: 7),
  CountryCode(name: 'Central African Rep.', dialCode: '+236', flag: '🇨🇫', iso: 'CF', maxDigits: 8),
  CountryCode(name: 'Chad',                 dialCode: '+235', flag: '🇹🇩', iso: 'TD', maxDigits: 8),
  CountryCode(name: 'Comoros',              dialCode: '+269', flag: '🇰🇲', iso: 'KM', maxDigits: 7),
  CountryCode(name: 'Congo',                dialCode: '+242', flag: '🇨🇬', iso: 'CG', maxDigits: 9),
  CountryCode(name: 'DR Congo',             dialCode: '+243', flag: '🇨🇩', iso: 'CD', maxDigits: 9),
  CountryCode(name: "Côte d'Ivoire",        dialCode: '+225', flag: '🇨🇮', iso: 'CI', maxDigits: 8),
  CountryCode(name: 'Egypt',                dialCode: '+20',  flag: '🇪🇬', iso: 'EG', maxDigits: 10),
  CountryCode(name: 'Equatorial Guinea',    dialCode: '+240', flag: '🇬🇶', iso: 'GQ', maxDigits: 9),
  CountryCode(name: 'Eritrea',              dialCode: '+291', flag: '🇪🇷', iso: 'ER', maxDigits: 7),
  CountryCode(name: 'Eswatini',             dialCode: '+268', flag: '🇸🇿', iso: 'SZ', maxDigits: 8),
  CountryCode(name: 'Gabon',                dialCode: '+241', flag: '🇬🇦', iso: 'GA', maxDigits: 7),
  CountryCode(name: 'Gambia',               dialCode: '+220', flag: '🇬🇲', iso: 'GM', maxDigits: 7),
  CountryCode(name: 'Ghana',                dialCode: '+233', flag: '🇬🇭', iso: 'GH', maxDigits: 9),
  CountryCode(name: 'Guinea',               dialCode: '+224', flag: '🇬🇳', iso: 'GN', maxDigits: 9),
  CountryCode(name: 'Guinea-Bissau',        dialCode: '+245', flag: '🇬🇼', iso: 'GW', maxDigits: 7),
  CountryCode(name: 'Lesotho',              dialCode: '+266', flag: '🇱🇸', iso: 'LS', maxDigits: 8),
  CountryCode(name: 'Liberia',              dialCode: '+231', flag: '🇱🇷', iso: 'LR', maxDigits: 8),
  CountryCode(name: 'Libya',                dialCode: '+218', flag: '🇱🇾', iso: 'LY', maxDigits: 9),
  CountryCode(name: 'Madagascar',           dialCode: '+261', flag: '🇲🇬', iso: 'MG', maxDigits: 9),
  CountryCode(name: 'Malawi',               dialCode: '+265', flag: '🇲🇼', iso: 'MW', maxDigits: 9),
  CountryCode(name: 'Mali',                 dialCode: '+223', flag: '🇲🇱', iso: 'ML', maxDigits: 8),
  CountryCode(name: 'Mauritania',           dialCode: '+222', flag: '🇲🇷', iso: 'MR', maxDigits: 8),
  CountryCode(name: 'Mauritius',            dialCode: '+230', flag: '🇲🇺', iso: 'MU', maxDigits: 8),
  CountryCode(name: 'Morocco',              dialCode: '+212', flag: '🇲🇦', iso: 'MA', maxDigits: 9),
  CountryCode(name: 'Mozambique',           dialCode: '+258', flag: '🇲🇿', iso: 'MZ', maxDigits: 9),
  CountryCode(name: 'Namibia',              dialCode: '+264', flag: '🇳🇦', iso: 'NA', maxDigits: 9),
  CountryCode(name: 'Niger',                dialCode: '+227', flag: '🇳🇪', iso: 'NE', maxDigits: 8),
  CountryCode(name: 'Nigeria',              dialCode: '+234', flag: '🇳🇬', iso: 'NG', maxDigits: 10),
  CountryCode(name: 'Rwanda',               dialCode: '+250', flag: '🇷🇼', iso: 'RW', maxDigits: 9),
  CountryCode(name: 'São Tomé & Príncipe',  dialCode: '+239', flag: '🇸🇹', iso: 'ST', maxDigits: 7),
  CountryCode(name: 'Senegal',              dialCode: '+221', flag: '🇸🇳', iso: 'SN', maxDigits: 9),
  CountryCode(name: 'Seychelles',           dialCode: '+248', flag: '🇸🇨', iso: 'SC', maxDigits: 7),
  CountryCode(name: 'Sierra Leone',         dialCode: '+232', flag: '🇸🇱', iso: 'SL', maxDigits: 8),
  CountryCode(name: 'South Africa',         dialCode: '+27',  flag: '🇿🇦', iso: 'ZA', maxDigits: 9),
  CountryCode(name: 'South Sudan',          dialCode: '+211', flag: '🇸🇸', iso: 'SS', maxDigits: 9),
  CountryCode(name: 'Sudan',                dialCode: '+249', flag: '🇸🇩', iso: 'SD', maxDigits: 9),
  CountryCode(name: 'Tanzania',             dialCode: '+255', flag: '🇹🇿', iso: 'TZ', maxDigits: 9),
  CountryCode(name: 'Togo',                 dialCode: '+228', flag: '🇹🇬', iso: 'TG', maxDigits: 8),
  CountryCode(name: 'Tunisia',              dialCode: '+216', flag: '🇹🇳', iso: 'TN', maxDigits: 8),
  CountryCode(name: 'Uganda',               dialCode: '+256', flag: '🇺🇬', iso: 'UG', maxDigits: 9),
  CountryCode(name: 'Zambia',               dialCode: '+260', flag: '🇿🇲', iso: 'ZM', maxDigits: 9),
  CountryCode(name: 'Zimbabwe',             dialCode: '+263', flag: '🇿🇼', iso: 'ZW', maxDigits: 9),

  // ── Middle East ──────────────────────────────────────────────────────────
  CountryCode(name: 'Bahrain',              dialCode: '+973', flag: '🇧🇭', iso: 'BH', maxDigits: 8),
  CountryCode(name: 'Iraq',                 dialCode: '+964', flag: '🇮🇶', iso: 'IQ', maxDigits: 10),
  CountryCode(name: 'Jordan',               dialCode: '+962', flag: '🇯🇴', iso: 'JO', maxDigits: 9),
  CountryCode(name: 'Kuwait',               dialCode: '+965', flag: '🇰🇼', iso: 'KW', maxDigits: 8),
  CountryCode(name: 'Lebanon',              dialCode: '+961', flag: '🇱🇧', iso: 'LB', maxDigits: 8),
  CountryCode(name: 'Oman',                 dialCode: '+968', flag: '🇴🇲', iso: 'OM', maxDigits: 8),
  CountryCode(name: 'Palestine',            dialCode: '+970', flag: '🇵🇸', iso: 'PS', maxDigits: 9),
  CountryCode(name: 'Qatar',                dialCode: '+974', flag: '🇶🇦', iso: 'QA', maxDigits: 8),
  CountryCode(name: 'Syria',                dialCode: '+963', flag: '🇸🇾', iso: 'SY', maxDigits: 9),
  CountryCode(name: 'Turkey',               dialCode: '+90',  flag: '🇹🇷', iso: 'TR', maxDigits: 10),
  CountryCode(name: 'Yemen',                dialCode: '+967', flag: '🇾🇪', iso: 'YE', maxDigits: 9),

  // ── Asia ─────────────────────────────────────────────────────────────────
  CountryCode(name: 'Afghanistan',          dialCode: '+93',  flag: '🇦🇫', iso: 'AF', maxDigits: 9),
  CountryCode(name: 'Bangladesh',           dialCode: '+880', flag: '🇧🇩', iso: 'BD', maxDigits: 10),
  CountryCode(name: 'China',                dialCode: '+86',  flag: '🇨🇳', iso: 'CN', maxDigits: 11),
  CountryCode(name: 'India',                dialCode: '+91',  flag: '🇮🇳', iso: 'IN', maxDigits: 10),
  CountryCode(name: 'Indonesia',            dialCode: '+62',  flag: '🇮🇩', iso: 'ID', maxDigits: 12),
  CountryCode(name: 'Iran',                 dialCode: '+98',  flag: '🇮🇷', iso: 'IR', maxDigits: 10),
  CountryCode(name: 'Israel',               dialCode: '+972', flag: '🇮🇱', iso: 'IL', maxDigits: 9),
  CountryCode(name: 'Japan',                dialCode: '+81',  flag: '🇯🇵', iso: 'JP', maxDigits: 10),
  CountryCode(name: 'Malaysia',             dialCode: '+60',  flag: '🇲🇾', iso: 'MY', maxDigits: 10),
  CountryCode(name: 'Maldives',             dialCode: '+960', flag: '🇲🇻', iso: 'MV', maxDigits: 7),
  CountryCode(name: 'Myanmar',              dialCode: '+95',  flag: '🇲🇲', iso: 'MM', maxDigits: 9),
  CountryCode(name: 'Nepal',                dialCode: '+977', flag: '🇳🇵', iso: 'NP', maxDigits: 10),
  CountryCode(name: 'Pakistan',             dialCode: '+92',  flag: '🇵🇰', iso: 'PK', maxDigits: 10),
  CountryCode(name: 'Philippines',          dialCode: '+63',  flag: '🇵🇭', iso: 'PH', maxDigits: 10),
  CountryCode(name: 'Singapore',            dialCode: '+65',  flag: '🇸🇬', iso: 'SG', maxDigits: 8),
  CountryCode(name: 'South Korea',          dialCode: '+82',  flag: '🇰🇷', iso: 'KR', maxDigits: 10),
  CountryCode(name: 'Sri Lanka',            dialCode: '+94',  flag: '🇱🇰', iso: 'LK', maxDigits: 9),
  CountryCode(name: 'Thailand',             dialCode: '+66',  flag: '🇹🇭', iso: 'TH', maxDigits: 9),
  CountryCode(name: 'Vietnam',              dialCode: '+84',  flag: '🇻🇳', iso: 'VN', maxDigits: 10),

  // ── Europe ───────────────────────────────────────────────────────────────
  CountryCode(name: 'Albania',              dialCode: '+355', flag: '🇦🇱', iso: 'AL', maxDigits: 9),
  CountryCode(name: 'Austria',              dialCode: '+43',  flag: '🇦🇹', iso: 'AT', maxDigits: 11),
  CountryCode(name: 'Belgium',              dialCode: '+32',  flag: '🇧🇪', iso: 'BE', maxDigits: 9),
  CountryCode(name: 'Bulgaria',             dialCode: '+359', flag: '🇧🇬', iso: 'BG', maxDigits: 9),
  CountryCode(name: 'Croatia',              dialCode: '+385', flag: '🇭🇷', iso: 'HR', maxDigits: 9),
  CountryCode(name: 'Cyprus',               dialCode: '+357', flag: '🇨🇾', iso: 'CY', maxDigits: 8),
  CountryCode(name: 'Czech Republic',       dialCode: '+420', flag: '🇨🇿', iso: 'CZ', maxDigits: 9),
  CountryCode(name: 'Denmark',              dialCode: '+45',  flag: '🇩🇰', iso: 'DK', maxDigits: 8),
  CountryCode(name: 'Finland',              dialCode: '+358', flag: '🇫🇮', iso: 'FI', maxDigits: 10),
  CountryCode(name: 'France',               dialCode: '+33',  flag: '🇫🇷', iso: 'FR', maxDigits: 9),
  CountryCode(name: 'Germany',              dialCode: '+49',  flag: '🇩🇪', iso: 'DE', maxDigits: 11),
  CountryCode(name: 'Greece',               dialCode: '+30',  flag: '🇬🇷', iso: 'GR', maxDigits: 10),
  CountryCode(name: 'Hungary',              dialCode: '+36',  flag: '🇭🇺', iso: 'HU', maxDigits: 9),
  CountryCode(name: 'Ireland',              dialCode: '+353', flag: '🇮🇪', iso: 'IE', maxDigits: 9),
  CountryCode(name: 'Italy',                dialCode: '+39',  flag: '🇮🇹', iso: 'IT', maxDigits: 10),
  CountryCode(name: 'Netherlands',          dialCode: '+31',  flag: '🇳🇱', iso: 'NL', maxDigits: 9),
  CountryCode(name: 'Norway',               dialCode: '+47',  flag: '🇳🇴', iso: 'NO', maxDigits: 8),
  CountryCode(name: 'Poland',               dialCode: '+48',  flag: '🇵🇱', iso: 'PL', maxDigits: 9),
  CountryCode(name: 'Portugal',             dialCode: '+351', flag: '🇵🇹', iso: 'PT', maxDigits: 9),
  CountryCode(name: 'Romania',              dialCode: '+40',  flag: '🇷🇴', iso: 'RO', maxDigits: 9),
  CountryCode(name: 'Russia',               dialCode: '+7',   flag: '🇷🇺', iso: 'RU', maxDigits: 10),
  CountryCode(name: 'Serbia',               dialCode: '+381', flag: '🇷🇸', iso: 'RS', maxDigits: 9),
  CountryCode(name: 'Spain',                dialCode: '+34',  flag: '🇪🇸', iso: 'ES', maxDigits: 9),
  CountryCode(name: 'Sweden',               dialCode: '+46',  flag: '🇸🇪', iso: 'SE', maxDigits: 10),
  CountryCode(name: 'Switzerland',          dialCode: '+41',  flag: '🇨🇭', iso: 'CH', maxDigits: 9),
  CountryCode(name: 'Ukraine',              dialCode: '+380', flag: '🇺🇦', iso: 'UA', maxDigits: 9),

  // ── Americas ─────────────────────────────────────────────────────────────
  CountryCode(name: 'Argentina',            dialCode: '+54',  flag: '🇦🇷', iso: 'AR', maxDigits: 10),
  CountryCode(name: 'Brazil',               dialCode: '+55',  flag: '🇧🇷', iso: 'BR', maxDigits: 11),
  CountryCode(name: 'Canada',               dialCode: '+1',   flag: '🇨🇦', iso: 'CA', maxDigits: 10),
  CountryCode(name: 'Chile',                dialCode: '+56',  flag: '🇨🇱', iso: 'CL', maxDigits: 9),
  CountryCode(name: 'Colombia',             dialCode: '+57',  flag: '🇨🇴', iso: 'CO', maxDigits: 10),
  CountryCode(name: 'Mexico',               dialCode: '+52',  flag: '🇲🇽', iso: 'MX', maxDigits: 10),
  CountryCode(name: 'Peru',                 dialCode: '+51',  flag: '🇵🇪', iso: 'PE', maxDigits: 9),

  // ── Oceania ──────────────────────────────────────────────────────────────
  CountryCode(name: 'Australia',            dialCode: '+61',  flag: '🇦🇺', iso: 'AU', maxDigits: 9),
  CountryCode(name: 'New Zealand',          dialCode: '+64',  flag: '🇳🇿', iso: 'NZ', maxDigits: 9),
];

const kDefaultCountry = CountryCode(
  name: 'Somalia', dialCode: '+252', flag: '🇸🇴', iso: 'SO', maxDigits: 9,
);
// Private alias used inside this file
const _kDefaultCountry = kDefaultCountry;

// ─────────────────────────────────────────────────────────────────────────────
// PhoneInputField — reusable widget
// ─────────────────────────────────────────────────────────────────────────────

/// Reusable international phone field.
///
/// Usage:
/// ```dart
/// PhoneInputField(
///   controller: _phoneCtrl,
///   onCountryChanged: (country) => _selectedCountry = country,
/// )
/// ```
/// To get full E.164 number: `selectedCountry.dialCode + controller.text.trim()`
class PhoneInputField extends StatefulWidget {
  final TextEditingController controller;
  final CountryCode initialCountry;
  final ValueChanged<CountryCode>? onCountryChanged;
  final ValueChanged<String>? onChanged; // called with full phone string

  const PhoneInputField({
    super.key,
    required this.controller,
    this.initialCountry = _kDefaultCountry,
    this.onCountryChanged,
    this.onChanged,
  });

  @override
  State<PhoneInputField> createState() => _PhoneInputFieldState();
}

class _PhoneInputFieldState extends State<PhoneInputField> {
  late CountryCode _selected;

  @override
  void initState() {
    super.initState();
    _selected = widget.initialCountry;
  }

  void _notifyChanged() {
    widget.onChanged?.call('${_selected.dialCode}${widget.controller.text.trim()}');
  }

  void _openPicker() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CountryPickerSheet(
        selected: _selected,
        onPick: (country) {
          setState(() => _selected = country);
          widget.onCountryChanged?.call(country);
          _notifyChanged();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF8F9FF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE8EAF0), width: 1.5),
      ),
      child: Row(children: [
        // ── Country picker button ─────────────────────────────────────────
        GestureDetector(
          onTap: _openPicker,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.07),
              borderRadius: const BorderRadius.horizontal(left: Radius.circular(13)),
            ),
            child: Row(children: [
              Text(_selected.flag, style: const TextStyle(fontSize: 20)),
              const SizedBox(width: 6),
              Text(
                _selected.dialCode,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14,
                  color: AppColors.primary,
                  letterSpacing: 0.3,
                ),
              ),
              const SizedBox(width: 3),
              Icon(Icons.keyboard_arrow_down_rounded,
                  size: 16, color: AppColors.primary.withOpacity(0.7)),
            ]),
          ),
        ),

        // ── Divider ───────────────────────────────────────────────────────
        Container(width: 1, height: 52, color: const Color(0xFFE8EAF0)),

        // ── Number input ──────────────────────────────────────────────────
        Expanded(
          child: TextField(
            controller: widget.controller,
            keyboardType: TextInputType.phone,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(_selected.maxDigits),
            ],
            style: const TextStyle(
              fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: 0.5),
            onChanged: (_) => _notifyChanged(),
            decoration: InputDecoration(
              hintText: _hintFor(_selected),
              hintStyle: const TextStyle(
                color: Color(0xFFB0B3C6),
                fontWeight: FontWeight.w400,
                fontSize: 15,
                letterSpacing: 0,
              ),
              border: InputBorder.none,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
            ),
          ),
        ),
      ]),
    );
  }

  String _hintFor(CountryCode c) {
    switch (c.iso) {
      case 'SO': return '61 xxx xxxx';
      case 'US':
      case 'CA': return '201 555 0123';
      case 'GB': return '7700 900123';
      case 'AE': return '50 123 4567';
      case 'SA': return '51 234 5678';
      case 'ET': return '91 234 5678';
      case 'KE': return '712 345 678';
      default:   return 'Enter number';
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Country Picker Bottom Sheet
// ─────────────────────────────────────────────────────────────────────────────

class _CountryPickerSheet extends StatefulWidget {
  final CountryCode selected;
  final ValueChanged<CountryCode> onPick;
  const _CountryPickerSheet({required this.selected, required this.onPick});

  @override
  State<_CountryPickerSheet> createState() => _CountryPickerSheetState();
}

class _CountryPickerSheetState extends State<_CountryPickerSheet> {
  final _search = TextEditingController();
  List<CountryCode> _filtered = kAllCountries;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String q) {
    final lower = q.toLowerCase();
    setState(() {
      _filtered = q.isEmpty
          ? kAllCountries
          : kAllCountries
              .where((c) =>
                  c.name.toLowerCase().contains(lower) ||
                  c.dialCode.contains(q) ||
                  c.iso.toLowerCase().contains(lower))
              .toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.88,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (_, scroll) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          // Handle
          Container(
            margin: const EdgeInsets.only(top: 10),
            width: 38, height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          // Title
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Select Country',
                    style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF1F2937))),
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                        color: const Color(0xFFF3F4F6),
                        borderRadius: BorderRadius.circular(8)),
                    child: const Icon(Icons.close_rounded,
                        size: 18, color: Color(0xFF6B7280)),
                  ),
                ),
              ],
            ),
          ),
          // Search
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: Container(
              decoration: BoxDecoration(
                color: const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(12),
              ),
              child: TextField(
                controller: _search,
                onChanged: _onSearch,
                autofocus: true,
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                decoration: const InputDecoration(
                  hintText: 'Search country or code…',
                  hintStyle: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14),
                  prefixIcon:
                      Icon(Icons.search_rounded, color: Color(0xFF9CA3AF), size: 20),
                  border: InputBorder.none,
                  contentPadding: EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ),
          const Divider(height: 1, color: Color(0xFFF3F4F6)),
          // List
          Expanded(
            child: _filtered.isEmpty
                ? const Center(
                    child: Text('No country found',
                        style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)))
                : ListView.builder(
                    controller: scroll,
                    itemCount: _filtered.length,
                    itemExtent: 58,
                    itemBuilder: (_, i) {
                      final c = _filtered[i];
                      final isSelected = c.iso == widget.selected.iso;
                      return InkWell(
                        onTap: () {
                          widget.onPick(c);
                          Navigator.pop(context);
                        },
                        child: Container(
                          color: isSelected
                              ? AppColors.primary.withOpacity(0.05)
                              : null,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 8),
                          child: Row(children: [
                            Text(c.flag,
                                style: const TextStyle(fontSize: 24)),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Text(c.name,
                                  style: TextStyle(
                                    fontSize: 15,
                                    fontWeight: isSelected
                                        ? FontWeight.w700
                                        : FontWeight.w500,
                                    color: isSelected
                                        ? AppColors.primary
                                        : const Color(0xFF1F2937),
                                  )),
                            ),
                            Text(c.dialCode,
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                  color: isSelected
                                      ? AppColors.primary
                                      : const Color(0xFF6B7280),
                                )),
                            if (isSelected) ...[
                              const SizedBox(width: 8),
                              Icon(Icons.check_circle_rounded,
                                  size: 16, color: AppColors.primary),
                            ],
                          ]),
                        ),
                      );
                    },
                  ),
          ),
        ]),
      ),
    );
  }
}
