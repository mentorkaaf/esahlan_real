import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';

class EwKybScreen extends ConsumerStatefulWidget {
  const EwKybScreen({super.key});

  @override
  ConsumerState<EwKybScreen> createState() => _EwKybScreenState();
}

class _EwKybScreenState extends ConsumerState<EwKybScreen> {
  final _formKey = GlobalKey<FormState>();
  final _bizName    = TextEditingController();
  final _bizAddress = TextEditingController();
  final _taxId      = TextEditingController();
  final _website    = TextEditingController();
  String _bizType   = 'sole_proprietorship';
  String? _licenseFileName;
  bool   _loading   = false;
  String? _error;

  static const _types = {
    'sole_proprietorship': 'Sole Proprietorship',
    'partnership':         'Partnership',
    'llc':                 'LLC',
    'corporation':         'Corporation',
    'cooperative':         'Cooperative',
  };

  @override
  void dispose() {
    _bizName.dispose(); _bizAddress.dispose(); _taxId.dispose(); _website.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final profileAsync = ref.watch(ewBuyerProfileProvider);

    // Already pending/approved — show status screen
    return profileAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator(color: EwTheme.orange))),
      error:   (_, __) => _buildForm(context),
      data:    (p) {
        if (p.kybStatus == 'approved') return _buildApprovedScreen(context);
        if (p.kybStatus == 'pending')  return _buildPendingScreen(context, p.submittedAt);
        if (p.kybStatus == 'rejected') return _buildForm(context, rejectionReason: p.kybRejectionReason);
        return _buildForm(context);
      },
    );
  }

  Widget _buildApprovedScreen(BuildContext context) {
    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(backgroundColor: EwTheme.navy, foregroundColor: Colors.white, title: const Text('Business Verification')),
      body: Center(
        child: Padding(padding: const EdgeInsets.all(32), child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.verified, size: 80, color: EwTheme.green),
          const SizedBox(height: 20),
          Text('Verified!', style: EwTheme.heading1.copyWith(color: EwTheme.green)),
          const SizedBox(height: 8),
          const Text('Your business is verified. You have full access to wholesale ordering.', textAlign: TextAlign.center, style: EwTheme.body),
          const SizedBox(height: 24),
          ElevatedButton(style: EwTheme.primaryButton, onPressed: () => context.go('/ewholesale'), child: const Text('Start Ordering')),
        ])),
      ),
    );
  }

  Widget _buildPendingScreen(BuildContext context, DateTime? submittedAt) {
    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(backgroundColor: EwTheme.navy, foregroundColor: Colors.white, title: const Text('Business Verification')),
      body: Center(
        child: Padding(padding: const EdgeInsets.all(32), child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.hourglass_top, size: 72, color: EwTheme.amber),
          const SizedBox(height: 20),
          Text('Under Review', style: EwTheme.heading1),
          const SizedBox(height: 8),
          Text(
            submittedAt != null
              ? 'Submitted ${_fmtDate(submittedAt)}. We\'ll notify you within 1-2 business days.'
              : 'Your application is under review. We\'ll notify you within 1-2 business days.',
            textAlign: TextAlign.center,
            style: EwTheme.body,
          ),
          const SizedBox(height: 24),
          const _StepRow(icon: Icons.upload_file_outlined,   label: 'Documents submitted',   done: true),
          const _StepRow(icon: Icons.search_outlined,        label: 'Team review in progress', done: false),
          const _StepRow(icon: Icons.check_circle_outline,   label: 'Approval decision',      done: false),
          const SizedBox(height: 24),
          OutlinedButton(style: EwTheme.secondaryButton, onPressed: () => context.pop(), child: const Text('Go back')),
        ])),
      ),
    );
  }

  Widget _buildForm(BuildContext context, {String? rejectionReason}) {
    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(backgroundColor: EwTheme.navy, foregroundColor: Colors.white, title: const Text('Verify Your Business')),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          // Rejected banner
          if (rejectionReason != null)
            Container(
              margin: const EdgeInsets.only(bottom: 14),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFFEE2E2), borderRadius: EwTheme.radius8, border: Border.all(color: EwTheme.red.withOpacity(0.3))),
              child: Row(children: [
                const Icon(Icons.info_outline, color: EwTheme.red, size: 18),
                const SizedBox(width: 8),
                Expanded(child: Text('Previous application rejected: $rejectionReason', style: const TextStyle(color: EwTheme.red, fontSize: 13))),
              ]),
            ),

          // Why verify
          Container(
            margin: const EdgeInsets.only(bottom: 20),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: EwTheme.navy.withOpacity(0.04), borderRadius: EwTheme.radius12),
            child: const Row(children: [
              Icon(Icons.shield_outlined, color: EwTheme.navy, size: 28),
              SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Why verify?', style: TextStyle(fontWeight: FontWeight.w700, color: EwTheme.navy)),
                SizedBox(height: 2),
                Text('Access wholesale pricing, credit terms, and bulk order placement after a one-time business review.',
                  style: TextStyle(fontSize: 13, color: EwTheme.textSecondary)),
              ])),
            ]),
          ),

          EwSectionHeader(title: 'Business Information'),
          const SizedBox(height: 12),

          _Field(controller: _bizName, label: 'Legal Business Name', required: true, hint: 'As registered'),
          const SizedBox(height: 12),
          _Field(controller: _bizAddress, label: 'Business Address', required: true, hint: 'Street, City, Country'),
          const SizedBox(height: 12),
          _Field(controller: _taxId, label: 'Tax / Registration ID', required: true, hint: 'Government ID'),
          const SizedBox(height: 12),
          _Field(controller: _website, label: 'Website (optional)', hint: 'https://'),
          const SizedBox(height: 12),

          // Business type dropdown
          DropdownButtonFormField<String>(
            value: _bizType,
            decoration: InputDecoration(
              labelText: 'Business Type',
              border: OutlineInputBorder(borderRadius: EwTheme.radius8),
              focusedBorder: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.navy, width: 1.5)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
            ),
            items: _types.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
            onChanged: (v) => setState(() => _bizType = v ?? _bizType),
          ),
          const SizedBox(height: 24),

          EwSectionHeader(title: 'Business License'),
          const SizedBox(height: 8),
          _LicenseUploader(
            fileName: _licenseFileName,
            onPick: (name) => setState(() => _licenseFileName = name),
          ),
          const SizedBox(height: 24),

          // Error
          if (_error != null)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: const Color(0xFFFEE2E2), borderRadius: EwTheme.radius8),
              child: Text(_error!, style: const TextStyle(color: EwTheme.red, fontSize: 13)),
            ),

          ElevatedButton(
            style: EwTheme.primaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(double.infinity, 52))),
            onPressed: _loading ? null : _submit,
            child: _loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Submit for Review', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          ),
          const SizedBox(height: 24),
          Text('Review takes 1-2 business days. You\'ll receive a push notification.', textAlign: TextAlign.center, style: EwTheme.bodySmall),
          const SizedBox(height: 32),
        ]),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });
    try {
      await ref.read(ewRepoProvider).submitKyb(
        businessName: _bizName.text.trim(),
        businessType: _bizType,
        address:      _bizAddress.text.trim(),
        taxId:        _taxId.text.trim(),
        website:      _website.text.trim().isEmpty ? null : _website.text.trim(),
      );
      ref.invalidate(ewBuyerProfileProvider);
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _fmtDate(DateTime d) => '${d.day}/${d.month}/${d.year}';
}

class _Field extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final String? hint;
  final bool required;
  const _Field({required this.controller, required this.label, this.hint, this.required = false});

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      decoration: InputDecoration(
        labelText: required ? '$label *' : label,
        hintText: hint,
        border: OutlineInputBorder(borderRadius: EwTheme.radius8),
        focusedBorder: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.navy, width: 1.5)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      ),
      validator: required ? (v) => (v == null || v.trim().isEmpty) ? '$label is required' : null : null,
    );
  }
}

class _LicenseUploader extends StatelessWidget {
  final String? fileName;
  final ValueChanged<String> onPick;
  const _LicenseUploader({this.fileName, required this.onPick});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => onPick('business_license.pdf'), // Simulated pick — real impl: file_picker
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: fileName != null ? EwTheme.green.withOpacity(0.06) : EwTheme.bg,
          borderRadius: EwTheme.radius12,
          border: Border.all(color: fileName != null ? EwTheme.green : EwTheme.border, width: fileName != null ? 1.5 : 1),
        ),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(fileName != null ? Icons.check_circle : Icons.upload_file_outlined,
            color: fileName != null ? EwTheme.green : EwTheme.navy, size: 28),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(fileName ?? 'Upload Business License', style: EwTheme.heading3.copyWith(color: fileName != null ? EwTheme.green : EwTheme.navy)),
            Text(fileName != null ? 'Tap to replace' : 'PDF, JPG, PNG — max 10MB', style: EwTheme.bodySmall),
          ])),
        ]),
      ),
    );
  }
}

class _StepRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool done;
  const _StepRow({required this.icon, required this.label, required this.done});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(
          color: done ? EwTheme.green.withOpacity(0.12) : EwTheme.bg,
          shape: BoxShape.circle,
          border: Border.all(color: done ? EwTheme.green : EwTheme.border),
        ), child: Icon(icon, size: 18, color: done ? EwTheme.green : EwTheme.textMuted)),
        const SizedBox(width: 12),
        Text(label, style: EwTheme.body.copyWith(color: done ? EwTheme.navy : EwTheme.textMuted,
          fontWeight: done ? FontWeight.w600 : FontWeight.normal)),
      ]),
    );
  }
}
