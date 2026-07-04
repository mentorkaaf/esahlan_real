import 'package:dio/dio.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/theme_x.dart';
import 'dart:io';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/repositories/community_repository.dart';
import '../../../../core/theme/theme_x.dart';
import '../providers/community_provider.dart';
import '../../../../core/theme/theme_x.dart';
import '../widgets/country_city_picker.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../shared/widgets/wallet_pin_dialog.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../wallet/presentation/providers/wallet_provider.dart';
import '../../../../core/theme/theme_x.dart';
import 'community_shell.dart';
import '../../../../core/theme/theme_x.dart';

class CreateAdScreen extends ConsumerStatefulWidget {
  final int pageId;
  final String pageName;
  const CreateAdScreen({super.key, required this.pageId, required this.pageName});
  @override
  ConsumerState<CreateAdScreen> createState() => _CreateAdScreenState();
}

class _CreateAdScreenState extends ConsumerState<CreateAdScreen> {
  final _titleCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _ctaTextCtrl = TextEditingController(text: 'Learn More');
  final _ctaUrlCtrl = TextEditingController();
  final _budgetCtrl = TextEditingController(text: '10');

  String _adType = 'image';
  String _placement = 'feed';
  String _payment = 'wallet';
  XFile? _mediaFile;
  XFile? _thumbnailFile;
  bool _creating = false;
  DateTime _startDate = DateTime.now();
  DateTime _endDate = DateTime.now().add(const Duration(days: 7));

  // Targeting
  String _targetGender = 'all';
  String _targetCountry = '';
  String _targetCity = '';
  String _targetMinAge = '';
  String _targetMaxAge = '';
  final Set<String> _targetInterests = {};
  int? _estimatedAudience;

  List<Map<String, dynamic>> _pricing = [];

  static const _interestOptions = [
    'Technology', 'Business', 'Education', 'Health', 'Sports', 'Fashion',
    'Food & Cooking', 'Travel', 'Music', 'Photography', 'Art & Design',
    'Gaming', 'Fitness', 'Real Estate', 'Cars & Motors', 'News & Politics',
    'Religion', 'Science', 'Entertainment', 'Beauty', 'Parenting',
    'Finance & Investment', 'Agriculture', 'Construction', 'Telecom',
    'E-commerce', 'Marketing', 'Freelancing', 'Coding', 'Startups',
  ];

  @override
  void initState() {
    super.initState();
    _loadPricing();
  }

  Future<void> _loadPricing() async {
    try {
      final pricing = await ref.read(communityRepoProvider).getAdPricing();
      if (mounted) setState(() => _pricing = pricing);
    } catch (_) {}
  }

  Map<String, dynamic>? get _matchedPricing {
    try {
      return _pricing.firstWhere((p) => p['ad_type'] == _adType && p['placement'] == _placement);
    } catch (_) { return null; }
  }

  @override
  void dispose() { _titleCtrl.dispose(); _descCtrl.dispose(); _ctaTextCtrl.dispose(); _ctaUrlCtrl.dispose(); _budgetCtrl.dispose(); super.dispose(); }

  Future<void> _pickMedia() async {
    final picker = ImagePicker();
    XFile? file;
    if (_adType == 'video') {
      file = await picker.pickVideo(source: ImageSource.gallery, maxDuration: AppConstants.adVideoMaxDuration);
    } else {
      file = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    }
    if (file != null) setState(() => _mediaFile = file);
  }

  Future<void> _submit() async {
    if (_titleCtrl.text.trim().isEmpty) { _snack('Enter ad title'); return; }
    if (_mediaFile == null) { _snack('Select media file'); return; }
    final budget = double.tryParse(_budgetCtrl.text) ?? 0;
    if (budget < 1) { _snack('Minimum budget is \$1'); return; }

    if (_payment == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _creating = true);
    try {
      final bytes = await _mediaFile!.readAsBytes();
      final ext = _mediaFile!.name.split('.').last.toLowerCase();
      final mime = _adType == 'video'
          ? (ext == 'mp4' ? 'video/mp4' : 'video/$ext')
          : (ext == 'png' ? 'image/png' : 'image/jpeg');

      final form = FormData.fromMap({
        'page_id': widget.pageId,
        'title': _titleCtrl.text.trim(),
        if (_descCtrl.text.trim().isNotEmpty) 'description': _descCtrl.text.trim(),
        'ad_type': _adType,
        'placement': _placement,
        'budget': budget,
        'payment_method': _payment,
        if (_ctaTextCtrl.text.trim().isNotEmpty) 'cta_text': _ctaTextCtrl.text.trim(),
        if (_ctaUrlCtrl.text.trim().isNotEmpty) 'cta_url': _ctaUrlCtrl.text.trim(),
        'starts_at': _startDate.toIso8601String().split('T')[0],
        'ends_at': _endDate.toIso8601String().split('T')[0],
        if (_targetGender != 'all') 'target_gender': _targetGender,
        if (_targetCountry.isNotEmpty) 'target_country': _targetCountry,
        if (_targetCity.isNotEmpty) 'target_city': _targetCity,
        if (_targetMinAge.isNotEmpty) 'target_min_age': int.tryParse(_targetMinAge),
        if (_targetMaxAge.isNotEmpty) 'target_max_age': int.tryParse(_targetMaxAge),
        ...{ for (var i = 0; i < _targetInterests.length; i++) 'target_interests[$i]': _targetInterests.elementAt(i) },
        'media': MultipartFile.fromBytes(bytes, filename: 'ad_media.$ext', contentType: DioMediaType.parse(mime)),
        if (_thumbnailFile != null) 'thumbnail': MultipartFile.fromBytes(
          await _thumbnailFile!.readAsBytes(), filename: 'thumb.jpg', contentType: DioMediaType.parse('image/jpeg')),
      });
      await ref.read(communityRepoProvider).createAd(form);
      if (_payment == 'wallet') ref.invalidate(walletProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Ad submitted for review!'), backgroundColor: Color(0xFF10B981)));
        Navigator.pop(context);
      }
    } catch (e) {
      String msg = '$e';
      if (e is DioException && e.response?.data != null) {
        final data = e.response!.data;
        if (data is Map) {
          if (data['message'] != null) msg = '${data['message']}';
          if (data['errors'] != null) {
            final errors = data['errors'] as Map;
            msg = errors.values.map((v) => v is List ? v.join(', ') : '$v').join('\n');
          }
        }
      }
      _snack(msg);
    } finally { if (mounted) setState(() => _creating = false); }
  }

  void _estimate() async {
    try {
      final result = await ref.read(communityRepoProvider).estimateAudience({
        if (_targetGender != 'all') 'gender': _targetGender,
        if (_targetCountry.isNotEmpty) 'country': _targetCountry,
        if (_targetCity.isNotEmpty) 'city': _targetCity,
        if (_targetMinAge.isNotEmpty) 'min_age': int.tryParse(_targetMinAge),
        if (_targetMaxAge.isNotEmpty) 'max_age': int.tryParse(_targetMaxAge),
        if (_targetInterests.isNotEmpty) 'interests': _targetInterests.toList(),
      });
      if (mounted) setState(() => _estimatedAudience = result['matched_users'] as int?);
    } catch (_) {}
  }

  void _snack(String msg) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red, behavior: SnackBarBehavior.floating));

  @override
  Widget build(BuildContext context) {
    final matched = _matchedPricing;
    return Scaffold(
      appBar: AppBar(title: Text('Create Ad — ${widget.pageName}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16))),
      body: ListView(padding: EdgeInsets.all(16), children: [
        // Ad Type
        _sectionLabel('Ad Type'),
        Row(children: [
          _chip('Image', Icons.image_rounded, _adType == 'image', () => setState(() { _adType = 'image'; _mediaFile = null; })),
          SizedBox(width: 10),
          _chip('Video', Icons.videocam_rounded, _adType == 'video', () => setState(() { _adType = 'video'; _mediaFile = null; })),
        ]),
        SizedBox(height: 16),

        // Placement
        _sectionLabel('Placement'),
        Wrap(spacing: 8, runSpacing: 8, children: [
          _chip('Feed', Icons.dynamic_feed_rounded, _placement == 'feed', () => setState(() => _placement = 'feed')),
          _chip('Reels', Icons.videocam_rounded, _placement == 'reels', () => setState(() => _placement = 'reels')),
          _chip('Explore', Icons.explore_rounded, _placement == 'explore', () => setState(() => _placement = 'explore')),
        ]),
        SizedBox(height: 16),

        // Pricing info
        if (matched != null) Container(
          padding: EdgeInsets.all(12), margin: EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(12), border: Border.all(color: kOrange.withValues(alpha: 0.3))),
          child: Row(children: [
            Icon(Icons.info_outline_rounded, color: kOrange, size: 20),
            SizedBox(width: 10),
            Expanded(child: Text(
              'Cost: \$${matched['cost_per_click']}/click · \$${matched['cost_per_1000']}/1K views · Min \$${matched['min_budget']}',
              style: TextStyle(fontSize: 12, color: context.colors.bodyText, fontWeight: FontWeight.w600))),
          ]),
        ),

        // Media
        _sectionLabel('Media'),
        GestureDetector(
          onTap: _pickMedia,
          child: Container(
            height: 180,
            decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE5E7EB), width: 1.5, strokeAlign: BorderSide.strokeAlignInside)),
            child: _mediaFile != null
                ? ClipRRect(borderRadius: BorderRadius.circular(14),
                    child: _adType == 'image'
                        ? Image.file(File(_mediaFile!.path), fit: BoxFit.cover, width: double.infinity)
                        : Stack(alignment: Alignment.center, children: [
                            Container(color: Colors.black87),
                            Icon(Icons.videocam_rounded, color: Colors.white, size: 48),
                            Positioned(bottom: 8, child: Text(_mediaFile!.name, style: TextStyle(color: Colors.white70, fontSize: 11))),
                          ]))
                : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(_adType == 'video' ? Icons.videocam_rounded : Icons.add_photo_alternate_rounded, size: 40, color: const Color(0xFFD1D5DB)),
                    SizedBox(height: 8),
                    Text('Tap to select ${_adType == 'video' ? 'video' : 'image'}', style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
                  ]),
          ),
        ),
        SizedBox(height: 16),

        SizedBox(height: 16),

        // Title
        _sectionLabel('Ad Title *'),
        TextField(controller: _titleCtrl, decoration: _inputDeco('What\'s your ad about?')),
        SizedBox(height: 14),

        // Description
        _sectionLabel('Description'),
        TextField(controller: _descCtrl, maxLines: 3, decoration: _inputDeco('Tell people more...')),
        SizedBox(height: 14),

        // CTA
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _sectionLabel('CTA Button'),
            TextField(controller: _ctaTextCtrl, decoration: _inputDeco('Learn More')),
          ])),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _sectionLabel('CTA Link'),
            TextField(controller: _ctaUrlCtrl, decoration: _inputDeco('https://...')),
          ])),
        ]),
        SizedBox(height: 16),

        // Budget
        _sectionLabel('Budget (\$)'),
        TextField(controller: _budgetCtrl, keyboardType: TextInputType.number, decoration: _inputDeco('10.00')),
        SizedBox(height: 16),

        // Dates
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _sectionLabel('Start date'),
            GestureDetector(
              onTap: () async {
                final d = await showDatePicker(context: context, initialDate: _startDate, firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 365)));
                if (d != null) setState(() => _startDate = d);
              },
              child: Container(padding: EdgeInsets.all(14), decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE5E7EB))),
                child: Row(children: [Icon(Icons.calendar_today_rounded, size: 16, color: kOrange), SizedBox(width: 8),
                  Text('${_startDate.day}/${_startDate.month}/${_startDate.year}', style: TextStyle(fontSize: 14))])),
            ),
          ])),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _sectionLabel('End date'),
            GestureDetector(
              onTap: () async {
                final d = await showDatePicker(context: context, initialDate: _endDate, firstDate: _startDate, lastDate: DateTime.now().add(const Duration(days: 365)));
                if (d != null) setState(() => _endDate = d);
              },
              child: Container(padding: EdgeInsets.all(14), decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE5E7EB))),
                child: Row(children: [Icon(Icons.calendar_today_rounded, size: 16, color: kOrange), SizedBox(width: 8),
                  Text('${_endDate.day}/${_endDate.month}/${_endDate.year}', style: TextStyle(fontSize: 14))])),
            ),
          ])),
        ]),
        SizedBox(height: 16),

        // ── Audience Targeting ──
        Divider(height: 32),
        Row(children: [
          Icon(Icons.people_rounded, color: kOrange, size: 20),
          SizedBox(width: 8),
          Text('Audience Targeting', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.bodyText)),
          Spacer(),
          if (_estimatedAudience != null) Container(
            padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
            child: Text('~$_estimatedAudience users', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700, fontSize: 12))),
        ]),
        SizedBox(height: 14),

        // Gender
        _sectionLabel('Gender'),
        Row(children: [
          _chip('All', Icons.people_rounded, _targetGender == 'all', () => setState(() { _targetGender = 'all'; _estimate(); })),
          SizedBox(width: 8),
          _chip('Male', Icons.male_rounded, _targetGender == 'male', () => setState(() { _targetGender = 'male'; _estimate(); })),
          SizedBox(width: 8),
          _chip('Female', Icons.female_rounded, _targetGender == 'female', () => setState(() { _targetGender = 'female'; _estimate(); })),
        ]),
        SizedBox(height: 14),

        // Country & City
        CountryCityPicker(
          initialCountry: _targetCountry.isNotEmpty ? _targetCountry : null,
          initialCity: _targetCity.isNotEmpty ? _targetCity : null,
          onChanged: (country, code, city) { setState(() { _targetCountry = country; _targetCity = city; }); _estimate(); },
        ),
        SizedBox(height: 14),

        // Age Range
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _sectionLabel('Min Age'),
            TextField(keyboardType: TextInputType.number, onChanged: (v) { _targetMinAge = v; _estimate(); }, decoration: _inputDeco('13')),
          ])),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _sectionLabel('Max Age'),
            TextField(keyboardType: TextInputType.number, onChanged: (v) { _targetMaxAge = v; _estimate(); }, decoration: _inputDeco('65')),
          ])),
        ]),
        SizedBox(height: 14),

        // Interests
        _sectionLabel('Interests'),
        Wrap(spacing: 6, runSpacing: 6, children: _interestOptions.map((i) {
          final sel = _targetInterests.contains(i);
          return GestureDetector(
            onTap: () => setState(() { sel ? _targetInterests.remove(i) : _targetInterests.add(i); _estimate(); }),
            child: Container(padding: EdgeInsets.symmetric(horizontal: 12, vertical: 7),
              decoration: BoxDecoration(color: sel ? kOrange : const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(20),
                border: Border.all(color: sel ? kOrange : const Color(0xFFE5E7EB))),
              child: Text(i, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: sel ? Colors.white : const Color(0xFF6B7280)))),
          );
        }).toList()),
        SizedBox(height: 16),

        // Payment
        _sectionLabel('Payment Method'),
        Row(children: [
          _chip('Wallet', Icons.account_balance_wallet_rounded, _payment == 'wallet', () => setState(() => _payment = 'wallet')),
          SizedBox(width: 10),
          _chip('Waafi Pay', Icons.phone_android_rounded, _payment == 'waafi_pay', () => setState(() => _payment = 'waafi_pay')),
        ]),
        SizedBox(height: 24),

        // Submit
        ElevatedButton(
          onPressed: _creating ? null : _submit,
          style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
            padding: EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
          child: _creating ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : Text('Submit Ad for Review', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ),
        SizedBox(height: 8),
        Text('Your ad will be reviewed by admin before going live.', textAlign: TextAlign.center, style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
        SizedBox(height: 40),
      ]),
    );
  }

  Widget _sectionLabel(String text) => Padding(padding: EdgeInsets.only(bottom: 8),
    child: Text(text, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.bodyText)));

  Widget _chip(String label, IconData icon, bool selected, VoidCallback onTap) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(duration: const Duration(milliseconds: 200),
      padding: EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: selected ? kOrange.withValues(alpha: 0.1) : const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: selected ? kOrange : const Color(0xFFE5E7EB), width: selected ? 2 : 1)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 18, color: selected ? kOrange : const Color(0xFF9CA3AF)),
        SizedBox(width: 6),
        Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: selected ? kOrange : const Color(0xFF6B7280))),
      ]),
    ),
  );

  InputDecoration _inputDeco(String hint) => InputDecoration(
    hintText: hint, filled: true, fillColor: const Color(0xFFF9FAFB),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
    contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12));
}
