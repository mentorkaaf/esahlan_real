import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../core/theme/app_color_tokens.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/module_widgets.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../ads/services/ad_service.dart';

final _svc = ModuleApiService.create();
final _doctorsProvider = FutureProvider.family<dynamic, String>((_, spec) => _svc.getDoctors(specialization: spec));

class EHealthScreen extends ConsumerStatefulWidget {
  const EHealthScreen({super.key});
  @override
  ConsumerState<EHealthScreen> createState() => _EHealthScreenState();
}

class _EHealthScreenState extends ConsumerState<EHealthScreen> {
  int _categoryIdx = 0;

  static const _categories = [
    {'id': 0, 'name': 'Ambulance', 'icon': Icons.emergency_rounded, 'color': 0xFFE74C3C, 'desc': 'Emergency response 24/7'},
    {'id': 1, 'name': 'Nurse',     'icon': Icons.medical_services_rounded, 'color': 0xFF27AE60, 'desc': 'Home nursing care'},
    {'id': 2, 'name': 'Doctor',    'icon': Icons.local_hospital_rounded,   'color': 0xFF2980B9, 'desc': 'Book an appointment'},
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: context.colors.cardBg, elevation: 0,
        leading: IconButton(icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText), onPressed: () => context.pop()),
        title: Text('eHospital', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontFamily: 'Cairo')),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(children: [

          // ── Category Cards ─────────────────────────────────────────
          Row(children: _categories.asMap().entries.map((e) {
            final cat   = e.value;
            final sel   = _categoryIdx == e.key;
            final color = Color(cat['color'] as int);
            return Expanded(child: GestureDetector(
              onTap: () => setState(() => _categoryIdx = e.key),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: EdgeInsets.only(right: e.key < 2 ? 10 : 0),
                padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
                decoration: BoxDecoration(
                  color: sel ? color : context.colors.cardBg,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: sel ? color : context.colors.borderColor, width: sel ? 0 : 1),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                ),
                child: Column(children: [
                  Icon(cat['icon'] as IconData, color: sel ? Colors.white : color, size: 32),
                  SizedBox(height: 8),
                  Text(cat['name'] as String, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13,
                      color: sel ? Colors.white : context.colors.navyText)),
                  SizedBox(height: 2),
                  Text(cat['desc'] as String, style: TextStyle(fontSize: 9, color: sel ? Colors.white70 : context.colors.mutedText),
                      textAlign: TextAlign.center),
                ]),
              ),
            ));
          }).toList()),
          const SizedBox(height: 24),

          // ── Content per category ───────────────────────────────────
          if (_categoryIdx == 0) const _AmbulanceSection(),
          if (_categoryIdx == 1) const _NurseSection(),
          if (_categoryIdx == 2) const _DoctorSection(),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Ambulance Section
// ─────────────────────────────────────────────────────────────────
class _AmbulanceSection extends StatefulWidget {
  const _AmbulanceSection();
  @override
  State<_AmbulanceSection> createState() => _AmbulanceSectionState();
}

class _AmbulanceSectionState extends State<_AmbulanceSection> {
  final _addrCtrl  = TextEditingController();
  final _phoneCtrl = TextEditingController();
  String _urgency  = 'high';
  bool   _sending  = false;

  @override
  void dispose() { _addrCtrl.dispose(); _phoneCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFFFFEBEE), borderRadius: BorderRadius.circular(14)),
        child: Row(children: [
          const Icon(Icons.emergency_rounded, color: Color(0xFFE74C3C), size: 32),
          const SizedBox(width: 12),
          const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Emergency Ambulance', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFFE74C3C))),
            Text('Response time: 10–20 minutes', style: TextStyle(fontSize: 12, color: AppColors.textGrey)),
          ])),
        ]),
      ),
      SizedBox(height: 16),
      Text('Urgency Level', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
      const SizedBox(height: 10),
      Row(children: ['high', 'medium', 'low'].map((u) {
        final sel = _urgency == u;
        final color = u == 'high' ? const Color(0xFFE74C3C) : u == 'medium' ? const Color(0xFFF39C12) : const Color(0xFF27AE60);
        return Expanded(child: GestureDetector(
          onTap: () => setState(() => _urgency = u),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            margin: EdgeInsets.only(right: u != 'low' ? 8 : 0),
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              color: sel ? color.withValues(alpha: 0.15) : context.colors.chipBg,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: sel ? color : AppColors.divider),
            ),
            child: Center(child: Text(u.toUpperCase(),
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: sel ? color : AppColors.textGrey))),
          ),
        ));
      }).toList()),
      const SizedBox(height: 16),
      ModuleFormCard(title: 'Your Location', child: Column(children: [
        _field(_addrCtrl, 'Address / Landmark', Icons.location_on_outlined),
        const SizedBox(height: 12),
        _field(_phoneCtrl, 'Contact Phone', Icons.phone_outlined, isPhone: true),
      ])),
      const SizedBox(height: 16),
      AppButton(
        label: _sending ? 'Requesting...' : 'Request Ambulance NOW',
        isLoading: _sending,
        onPressed: (_addrCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty) ? null : _request,
      ),
    ]);
  }

  Widget _field(TextEditingController ctrl, String hint, IconData icon, {bool isPhone = false}) =>
      TextField(
        controller: ctrl,
        keyboardType: isPhone ? TextInputType.phone : TextInputType.text,
        onChanged: (_) => setState(() {}),
        decoration: InputDecoration(
          hintText: hint, hintStyle: TextStyle(color: AppColors.textGrey, fontSize: 13),
          prefixIcon: Icon(icon, color: AppColors.textGrey, size: 20),
          filled: true, fillColor: context.colors.cardBg,
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFE74C3C))),
        ),
      );

  Future<void> _request() async {
    setState(() => _sending = true);
    try {
      final svc = ModuleApiService.create();
      await svc.requestAmbulance({'address': _addrCtrl.text.trim(), 'phone': _phoneCtrl.text.trim(), 'urgency': _urgency});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Ambulance dispatched! Help is on the way.'), backgroundColor: Color(0xFFE74C3C)));
        context.pop();
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _sending = false);
    }
  }
}

// ─────────────────────────────────────────────────────────────────
// Nurse Section
// ─────────────────────────────────────────────────────────────────
class _NurseSection extends StatefulWidget {
  const _NurseSection();
  @override
  State<_NurseSection> createState() => _NurseSectionState();
}

class _NurseSectionState extends State<_NurseSection> {
  final _nameCtrl  = TextEditingController();
  final _addrCtrl  = TextEditingController();
  final _notesCtrl = TextEditingController();
  String _duration  = '1h';
  DateTime? _date;
  bool _booking     = false;
  String _payMethod = 'wallet';
  String? _waafiRef;
  String? _mobileProofToken;

  @override
  void dispose() { _nameCtrl.dispose(); _addrCtrl.dispose(); _notesCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      ModuleFormCard(title: 'Patient Information', child: Column(children: [
        _field(_nameCtrl, 'Patient Name', Icons.person_outlined),
        const SizedBox(height: 12),
        _field(_addrCtrl, 'Home Address', Icons.location_on_outlined),
      ])),
      SizedBox(height: 14),
      Text('Session Duration', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
      const SizedBox(height: 10),
      Row(children: ['1h', '2h', '4h', '8h'].map((d) {
        final sel = _duration == d;
        return Expanded(child: GestureDetector(
          onTap: () => setState(() => _duration = d),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            margin: EdgeInsets.only(right: d != '8h' ? 8 : 0),
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              color: sel ? AppColors.primary : context.colors.chipBg,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: sel ? AppColors.primary : AppColors.divider),
            ),
            child: Center(child: Text(d, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13,
                color: sel ? Colors.white : AppColors.textGrey))),
          ),
        ));
      }).toList()),
      const SizedBox(height: 14),
      ModuleFormCard(title: 'Schedule Date', child: GestureDetector(
        onTap: () async {
          final d = await showDatePicker(context: context,
              initialDate: DateTime.now().add(const Duration(days: 1)),
              firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 30)),
              builder: (ctx, child) => Theme(data: Theme.of(ctx).copyWith(
                  colorScheme: const ColorScheme.light(primary: AppColors.primary)), child: child!));
          if (d != null) setState(() => _date = d);
        },
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(10), border: Border.all(color: AppColors.divider)),
          child: Row(children: [
            const Icon(Icons.calendar_today_outlined, color: AppColors.primary, size: 18),
            SizedBox(width: 10),
            Text(_date != null ? '${_date!.day}/${_date!.month}/${_date!.year}' : 'Select date',
                style: TextStyle(color: _date != null ? context.colors.navyText : context.colors.mutedText,
                    fontWeight: _date != null ? FontWeight.w700 : FontWeight.w400)),
          ]),
        ),
      )),
      SizedBox(height: 14),
      ModuleFormCard(title: 'Special Notes (Optional)', child: TextField(
        controller: _notesCtrl, maxLines: 3,
        decoration: InputDecoration(hintText: 'Any specific requirements or medical conditions...',
            hintStyle: TextStyle(fontSize: 12, color: AppColors.textGrey),
            filled: true, fillColor: context.colors.cardBg,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)),
      )),
      const SizedBox(height: 16),
      _HealthPayRow(payMethod: _payMethod, onChanged: (v) => setState(() => _payMethod = v)),
      const SizedBox(height: 12),
      AppButton(
        label: _booking ? 'Booking...' : 'Book Nurse',
        isLoading: _booking,
        onPressed: (_nameCtrl.text.trim().isEmpty || _addrCtrl.text.trim().isEmpty || _date == null) ? null : _book,
      ),
    ]);
  }

  Widget _field(TextEditingController ctrl, String hint, IconData icon) => TextField(
    controller: ctrl,
    onChanged: (_) => setState(() {}),
    decoration: InputDecoration(
      hintText: hint, hintStyle: TextStyle(color: AppColors.textGrey, fontSize: 13),
      prefixIcon: Icon(icon, color: AppColors.textGrey, size: 20),
      filled: true, fillColor: context.colors.cardBg,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.primary)),
    ),
  );

  Future<void> _book() async {
    if (_payMethod == 'mobile_pay') {
      const nurseRate = 15.0;
      final result = await showMobilePaySheet(context, amount: nurseRate, description: 'Nurse Home Visit');
      if (result?.success != true) return;
      _waafiRef = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
      _mobileProofToken = result.proofToken;
    } else if (_payMethod == 'waafi_pay') {
      const nurseRate = 15.0;
      final result = await showWaafiPaySheet(context, amount: nurseRate, type: 'order', description: 'Nurse Home Visit');
      if (result?.success != true) return;
      _waafiRef = result!.reference;
    }
    setState(() => _booking = true);
    try {
      final svc = ModuleApiService.create();
      await svc.bookAppointment({'type': 'nurse', 'patient_name': _nameCtrl.text.trim(),
          'address': _addrCtrl.text.trim(), 'duration': _duration,
          'scheduled_date': '${_date!.year}-${_date!.month.toString().padLeft(2,'0')}-${_date!.day.toString().padLeft(2,'0')}',
          'notes': _notesCtrl.text.trim(),
          'payment_method': _payMethod,
          if (_waafiRef != null) 'payment_reference': _waafiRef});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Nurse booking confirmed!'), backgroundColor: AppColors.success));
        context.pop();
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _booking = false);
    }
  }
}

// ─────────────────────────────────────────────────────────────────
// Doctor Section
// ─────────────────────────────────────────────────────────────────
class _DoctorSection extends ConsumerStatefulWidget {
  const _DoctorSection();
  @override
  ConsumerState<_DoctorSection> createState() => _DoctorSectionState();
}

class _DoctorSectionState extends ConsumerState<_DoctorSection> {
  String _spec = '';
  // ignore: unused_field
  dynamic _selectedDoctor;

  static const _specs = ['General', 'Pediatrics', 'Cardiology', 'Orthopedics', 'Dermatology', 'Gynecology'];

  @override
  Widget build(BuildContext context) {
    final doctorsAsync = ref.watch(_doctorsProvider(_spec));

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Specialization filter
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(children: [
          _specChip('All', ''),
          ..._specs.map((s) => _specChip(s, s)),
        ]),
      ),
      const SizedBox(height: 16),

      doctorsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Text('Error: $e'),
        data: (res) {
          final doctors = res['data'] as List? ?? [];
          if (doctors.isEmpty) return const Center(child: Padding(padding: EdgeInsets.all(32),
              child: Text('No doctors found', style: TextStyle(color: AppColors.textGrey))));
          return ListView.separated(
            shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
            itemCount: doctors.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final doc = doctors[i];
              return GestureDetector(
                onTap: () { setState(() => _selectedDoctor = doc); _showBookSheet(context, doc); },
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
                  child: Row(children: [
                    Container(
                      width: 60, height: 60,
                      decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), shape: BoxShape.circle),
                      child: doc['avatar'] != null
                          ? ClipOval(child: NetImage(url: doc['avatar'], fit: BoxFit.cover))
                          : const Icon(Icons.person_rounded, color: AppColors.primary, size: 30),
                    ),
                    SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(doc['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
                      Text(doc['specialization'] ?? '', style: TextStyle(fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.w600)),
                      if (doc['experience_years'] != null)
                        Text('${doc['experience_years']} yrs experience', style: TextStyle(fontSize: 11, color: AppColors.textGrey)),
                      if (doc['consultation_fee'] != null)
                        Text('\$${doc['consultation_fee']} per visit', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: context.colors.navyText)),
                    ])),
                    const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textGrey),
                  ]),
                ),
              );
            },
          );
        },
      ),
    ]);
  }

  Widget _specChip(String label, String value) => GestureDetector(
    onTap: () => setState(() { _spec = value; _selectedDoctor = null; }),
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      margin: const EdgeInsets.only(right: 8, bottom: 4),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: _spec == value ? AppColors.primary : context.colors.cardBg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _spec == value ? AppColors.primary : context.colors.borderColor),
      ),
      child: Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12,
          color: _spec == value ? Colors.white : context.colors.navyText)),
    ),
  );

  void _showBookSheet(BuildContext context, dynamic doc) {
    showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: context.colors.cardBg,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        builder: (_) => _BookDoctorSheet(doctor: doc));
  }
}

class _BookDoctorSheet extends StatefulWidget {
  final dynamic doctor;
  const _BookDoctorSheet({required this.doctor});
  @override State<_BookDoctorSheet> createState() => _BookDoctorSheetState();
}

class _BookDoctorSheetState extends State<_BookDoctorSheet> {
  DateTime? _date;
  String? _slot;
  final _notesCtrl = TextEditingController();
  bool _booking    = false;
  String _payMethod = 'wallet';
  String? _waafiRef;
  String? _mobileProofToken;

  static const _slots = ['08:00 AM','09:00 AM','10:00 AM','11:00 AM','02:00 PM','03:00 PM','04:00 PM'];

  @override
  void dispose() { _notesCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Book Dr. ${widget.doctor['name']}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
            IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
          ]),
          Text(widget.doctor['specialization'] ?? '', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600)),
          SizedBox(height: 16),
          Text('Select Date', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
          const SizedBox(height: 8),
          GestureDetector(
            onTap: () async {
              final d = await showDatePicker(context: context,
                  initialDate: DateTime.now().add(const Duration(days: 1)),
                  firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 30)));
              if (d != null) setState(() => _date = d);
            },
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(10), border: Border.all(color: AppColors.divider)),
              child: Row(children: [
                const Icon(Icons.calendar_today_outlined, color: AppColors.primary, size: 18),
                SizedBox(width: 10),
                Text(_date != null ? '${_date!.day}/${_date!.month}/${_date!.year}' : 'Pick a date',
                    style: TextStyle(color: _date != null ? context.colors.navyText : context.colors.mutedText,
                        fontWeight: _date != null ? FontWeight.w700 : FontWeight.w400)),
              ]),
            ),
          ),
          SizedBox(height: 14),
          Text('Time Slot', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
          const SizedBox(height: 8),
          Wrap(spacing: 8, runSpacing: 8, children: _slots.map((s) => GestureDetector(
            onTap: () => setState(() => _slot = s),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: _slot == s ? AppColors.primary : context.colors.inputFill,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: _slot == s ? AppColors.primary : context.colors.borderColor),
              ),
              child: Text(s, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
                  color: _slot == s ? Colors.white : context.colors.navyText)),
            ),
          )).toList()),
          SizedBox(height: 14),
          TextField(
            controller: _notesCtrl, maxLines: 2,
            decoration: InputDecoration(
              hintText: 'Describe your symptoms (optional)',
              hintStyle: TextStyle(fontSize: 12, color: AppColors.textGrey),
              filled: true, fillColor: context.colors.cardBg,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
            ),
          ),
          const SizedBox(height: 16),
          const SizedBox(height: 4),
          _HealthPayRow(payMethod: _payMethod, onChanged: (v) => setState(() => _payMethod = v)),
          const SizedBox(height: 12),
          AppButton(
            label: _booking ? 'Booking...' : 'Book Appointment',
            isLoading: _booking,
            onPressed: (_date == null || _slot == null) ? null : _book,
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  Future<void> _book() async {
    if (_payMethod == 'mobile_pay') {
      final fee = (widget.doctor['consultation_fee'] as num?)?.toDouble() ?? 10.0;
      final result = await showMobilePaySheet(context, amount: fee, description: 'Doctor Appointment');
      if (result?.success != true) return;
      _waafiRef = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
      _mobileProofToken = result.proofToken;
    } else if (_payMethod == 'waafi_pay') {
      final fee = (widget.doctor['consultation_fee'] as num?)?.toDouble() ?? 10.0;
      final result = await showWaafiPaySheet(context, amount: fee, type: 'order', description: 'Doctor Appointment');
      if (result?.success != true) return;
      _waafiRef = result!.reference;
    }
    setState(() => _booking = true);
    try {
      final svc = ModuleApiService.create();
      await svc.bookAppointment({'type': 'doctor', 'doctor_id': widget.doctor['id'],
          'scheduled_date': '${_date!.year}-${_date!.month.toString().padLeft(2,'0')}-${_date!.day.toString().padLeft(2,'0')}',
          'time_slot': _slot, 'notes': _notesCtrl.text.trim(),
          'payment_method': _payMethod,
          if (_waafiRef != null) 'payment_reference': _waafiRef});
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Appointment booked!'), backgroundColor: AppColors.success));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _booking = false);
    }
  }
}

class _HealthPayRow extends ConsumerWidget {
  final String payMethod;
  final ValueChanged<String> onChanged;
  const _HealthPayRow({required this.payMethod, required this.onChanged});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
      const SizedBox(height: 8),
      PaymentMethodSection(selected: payMethod, onChanged: onChanged),
    ]);
  }
}

class _HealthPayChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  const _HealthPayChip({required this.label, required this.icon, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 11),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary.withValues(alpha: 0.08) : context.colors.chipBg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? AppColors.primary : AppColors.divider, width: selected ? 2 : 1),
        ),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, size: 16, color: selected ? AppColors.primary : AppColors.textGrey),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: selected ? AppColors.primary : AppColors.textGrey)),
        ]),
      ),
    );
  }
}
