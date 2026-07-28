import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/fcm_service.dart';
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';
import '../auth/login_screen.dart';

final _storeProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => VendorRepository.instance.storeProfile());

class StoreScreen extends ConsumerWidget {
  const StoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final store = ref.watch(_storeProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Store Profile'),
        actions: [
          IconButton(icon: Icon(Icons.refresh_rounded, color: context.vcTextSec), onPressed: () => ref.invalidate(_storeProvider)),
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: VC.red),
            tooltip: 'Logout',
            onPressed: () async {
              final confirm = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
                backgroundColor: ctx.vcCard,
                title: Text('Logout', style: TextStyle(color: ctx.vcText, fontWeight: FontWeight.w800)),
                content: Text('Are you sure you want to logout?', style: TextStyle(color: ctx.vcTextSec)),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('Cancel', style: TextStyle(color: ctx.vcTextSec))),
                  ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: VC.red), onPressed: () => Navigator.pop(ctx, true), child: const Text('Logout', style: TextStyle(color: Colors.white))),
                ],
              ));
              if (confirm == true && context.mounted) {
                await VendorFcmService.clearToken();
                await AuthService.instance.logout();
                Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
              }
            },
          ),
        ]),
      body: store.when(
        loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: VC.red))),
        data: (d) => ListView(padding: const EdgeInsets.all(16), children: [
          _InfoCard(store: d, onSaved: () => ref.invalidate(_storeProvider)),
          const SizedBox(height: 14),
          _ScheduleCard(schedules: d['schedules'] as List? ?? [], onSaved: () => ref.invalidate(_storeProvider)),
          const SizedBox(height: 14),
          _StatsCard(store: d),
        ]),
      ),
    );
  }
}

class _InfoCard extends StatefulWidget {
  final Map<String, dynamic> store;
  final VoidCallback onSaved;
  const _InfoCard({required this.store, required this.onSaved});
  @override
  State<_InfoCard> createState() => _InfoCardState();
}

class _InfoCardState extends State<_InfoCard> {
  late final _nameCtrl  = TextEditingController(text: widget.store['name'] ?? '');
  late final _descCtrl  = TextEditingController(text: widget.store['description'] ?? '');
  late final _phoneCtrl = TextEditingController(text: widget.store['phone'] ?? '');
  late final _feeCtrl   = TextEditingController(text: '${widget.store['delivery_fee'] ?? ''}');
  XFile? _logo;
  bool _loading = false;

  Future<void> _save() async {
    setState(() => _loading = true);
    try {
      final form = FormData.fromMap({
        'name': _nameCtrl.text.trim(),
        'description': _descCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        'delivery_fee': double.tryParse(_feeCtrl.text.trim()) ?? 0,
        if (_logo != null) 'logo': await MultipartFile.fromFile(_logo!.path, filename: _logo!.name),
      });
      await VendorRepository.instance.updateStore(form);
      widget.onSaved();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Store updated!'), backgroundColor: VC.green));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.store;
    final logo = s['logo'] as String?;
    final rating = double.tryParse('${s['rating'] ?? 0}') ?? 0;
    final isVerified = s['is_verified'] == true;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(18), border: Border.all(color: context.vcBorder)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          GestureDetector(
            onTap: () async {
              final img = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
              if (img != null) setState(() => _logo = img);
            },
            child: Stack(children: [
              CircleAvatar(radius: 32, backgroundColor: VC.orangeDim,
                backgroundImage: logo != null ? NetworkImage(logo.startsWith('http') ? logo : 'https://esahlan.com/storage/$logo') : null,
                child: logo == null ? const Icon(Icons.storefront_rounded, color: VC.orange, size: 28) : null),
              Positioned(bottom: 0, right: 0, child: Container(width: 20, height: 20, decoration: const BoxDecoration(color: VC.orange, shape: BoxShape.circle),
                child: const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 12))),
            ]),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(s['name'] ?? '', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w900, fontSize: 16)),
            Row(children: [
              const Icon(Icons.star_rounded, color: Color(0xFFFBBF24), size: 14),
              const SizedBox(width: 3),
              Text(rating.toStringAsFixed(1), style: TextStyle(color: context.vcTextSec, fontSize: 12, fontWeight: FontWeight.w700)),
              if (isVerified) ...[const SizedBox(width: 8), const Icon(Icons.verified_rounded, color: VC.blue, size: 14)],
            ]),
          ])),
          Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(8)),
            child: Text((s['module_slug'] ?? 'efood') == 'efood' ? 'eFood' : 'eShop',
              style: const TextStyle(color: VC.orange, fontSize: 11, fontWeight: FontWeight.w800))),
        ]),
        if (_logo != null) ...[
          const SizedBox(height: 6),
          Text('New logo selected: ${_logo!.name}', style: const TextStyle(color: VC.green, fontSize: 11)),
        ],
        const SizedBox(height: 18),
        _label('Store Name'), _field(_nameCtrl, 'Store name'), const SizedBox(height: 12),
        _label('Phone'), _field(_phoneCtrl, '+252...', type: TextInputType.phone), const SizedBox(height: 12),
        _label('Delivery Fee (USD)'), _field(_feeCtrl, '0.00', type: TextInputType.numberWithOptions(decimal: true)), const SizedBox(height: 12),
        _label('Description'),
        TextField(controller: _descCtrl, maxLines: 3, style: TextStyle(color: context.vcText), decoration: _dec('About your store')),
        const SizedBox(height: 18),
        SizedBox(width: double.infinity, height: 48,
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: VC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            onPressed: _loading ? null : _save,
            child: _loading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Save Changes', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
          ),
        ),
      ]),
    );
  }

  Widget _label(String t) => Padding(padding: const EdgeInsets.only(bottom: 6), child: Text(t, style: TextStyle(color: context.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)));
  Widget _field(TextEditingController c, String hint, {TextInputType? type}) => Padding(padding: EdgeInsets.zero, child: TextField(controller: c, keyboardType: type, style: TextStyle(color: context.vcText), decoration: _dec(hint)));
  InputDecoration _dec(String hint) => InputDecoration(
    hintText: hint, hintStyle: TextStyle(color: context.vcTextMute),
    filled: true, fillColor: context.vcInputFill,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: context.vcBorder)),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: context.vcBorder)),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: VC.orange, width: 1.5)),
    isDense: true, contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
  );
}

class _ScheduleCard extends StatefulWidget {
  final List schedules;
  final VoidCallback onSaved;
  const _ScheduleCard({required this.schedules, required this.onSaved});
  @override
  State<_ScheduleCard> createState() => _ScheduleCardState();
}

class _ScheduleCardState extends State<_ScheduleCard> {
  static const _days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  static const _dayLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  late List<Map<String, dynamic>> _schedule;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _schedule = _days.map((day) {
      final existing = widget.schedules.firstWhere((s) => s['day'] == day, orElse: () => null);
      return {
        'day': day,
        'is_open': existing?['is_open'] ?? true,
        'open_time': existing?['open_time'] ?? '08:00',
        'close_time': existing?['close_time'] ?? '22:00',
      };
    }).toList();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await VendorRepository.instance.updateSchedule(_schedule);
      widget.onSaved();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Schedule saved!'), backgroundColor: VC.green));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(18), border: Border.all(color: context.vcBorder)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Opening Hours', style: TextStyle(color: context.vcText, fontSize: 14, fontWeight: FontWeight.w800)),
      const SizedBox(height: 14),
      ..._schedule.asMap().entries.map((e) {
        final i = e.key; final s = e.value;
        return Padding(padding: const EdgeInsets.only(bottom: 8), child: Row(children: [
          SizedBox(width: 36, child: Text(_dayLabels[i], style: TextStyle(color: context.vcTextSec, fontWeight: FontWeight.w700, fontSize: 12))),
          Switch(value: s['is_open'] as bool, onChanged: (v) => setState(() => _schedule[i]['is_open'] = v),
            activeColor: VC.green, inactiveThumbColor: VC.red, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
          if (s['is_open'] as bool) ...[
            const SizedBox(width: 6),
            _timeBtn(s['open_time'], () => _pickTime(i, true), context),
            Text(' – ', style: TextStyle(color: context.vcTextSec)),
            _timeBtn(s['close_time'], () => _pickTime(i, false), context),
          ] else
            Padding(padding: const EdgeInsets.only(left: 6), child: Text('Closed', style: const TextStyle(color: VC.red, fontSize: 12, fontWeight: FontWeight.w600))),
        ]));
      }),
      const SizedBox(height: 12),
      SizedBox(width: double.infinity, height: 44,
        child: ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: VC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
          onPressed: _saving ? null : _save,
          child: _saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
            : const Text('Save Schedule', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
        )),
    ]),
  );

  Widget _timeBtn(String time, VoidCallback onTap, BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: context.vcInputFill, borderRadius: BorderRadius.circular(8), border: Border.all(color: context.vcBorder)),
      child: Text(time, style: TextStyle(color: context.vcText, fontSize: 12, fontWeight: FontWeight.w700))));

  Future<void> _pickTime(int i, bool isOpen) async {
    final current = _schedule[i][isOpen ? 'open_time' : 'close_time'] as String;
    final parts = current.split(':');
    final picked = await showTimePicker(context: context,
      initialTime: TimeOfDay(hour: int.tryParse(parts[0]) ?? 8, minute: int.tryParse(parts[1]) ?? 0));
    if (picked != null && mounted) {
      setState(() => _schedule[i][isOpen ? 'open_time' : 'close_time'] = '${picked.hour.toString().padLeft(2,'0')}:${picked.minute.toString().padLeft(2,'0')}');
    }
  }
}

class _StatsCard extends StatelessWidget {
  final Map<String, dynamic> store;
  const _StatsCard({required this.store});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(18), border: Border.all(color: context.vcBorder)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Store Info', style: TextStyle(color: context.vcText, fontSize: 14, fontWeight: FontWeight.w800)),
      const SizedBox(height: 12),
      _row('District', store['district']?['name'] ?? '—', context),
      _row('Min Order', '\$${store['minimum_order'] ?? '0'}', context),
      _row('Total Reviews', '${store['total_reviews'] ?? 0}', context),
      _row('Verification', store['is_verified'] == true ? '✓ Verified' : 'Pending', context),
      _row('Status', store['is_open'] == true ? 'Active' : 'Inactive', context),
    ]),
  );

  Widget _row(String label, String val, BuildContext context) => Padding(padding: const EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: context.vcTextSec, fontSize: 13)),
      Text(val, style: TextStyle(color: context.vcText, fontSize: 13, fontWeight: FontWeight.w700)),
    ]));
}
