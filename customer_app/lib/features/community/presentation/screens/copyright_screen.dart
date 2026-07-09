import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/community_repository.dart';

final _myClaimsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) {
  return CommunityRepository().getMyCopyrightClaims();
});

final _againstMeProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) {
  return CommunityRepository().getCopyrightClaimsAgainstMe();
});

class CopyrightScreen extends ConsumerStatefulWidget {
  const CopyrightScreen({super.key});

  @override
  ConsumerState<CopyrightScreen> createState() => _CopyrightScreenState();
}

class _CopyrightScreenState extends ConsumerState<CopyrightScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF111827)),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Copyright', style: TextStyle(color: Color(0xFF111827), fontWeight: FontWeight.w800, fontSize: 17)),
        actions: [
          TextButton.icon(
            icon: const Icon(Icons.add, color: Color(0xFFFF8A00), size: 18),
            label: const Text('File Claim', style: TextStyle(color: Color(0xFFFF8A00), fontWeight: FontWeight.w700, fontSize: 13)),
            onPressed: () => _showClaimForm(context),
          ),
        ],
        bottom: TabBar(
          controller: _tab,
          labelColor: const Color(0xFFFF8A00),
          unselectedLabelColor: const Color(0xFF9CA3AF),
          indicatorColor: const Color(0xFFFF8A00),
          labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          tabs: const [Tab(text: 'My Claims'), Tab(text: 'Against My Content')],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: [
          _ClaimsList(provider: _myClaimsProvider, isMyClaims: true),
          _ClaimsList(provider: _againstMeProvider, isMyClaims: false),
        ],
      ),
    );
  }

  void _showClaimForm(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CopyrightClaimForm(onSubmitted: () {
        ref.invalidate(_myClaimsProvider);
      }),
    );
  }
}

class _ClaimsList extends ConsumerWidget {
  final ProviderBase<AsyncValue<List<Map<String, dynamic>>>> provider;
  final bool isMyClaims;

  const _ClaimsList({required this.provider, required this.isMyClaims});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(provider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: Color(0xFFFF8A00))),
      error: (e, _) => Center(child: Text('Error: $e', style: const TextStyle(color: Color(0xFF6B7280)))),
      data: (claims) {
        if (claims.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(isMyClaims ? Icons.copyright_rounded : Icons.shield_outlined,
                    size: 48, color: const Color(0xFFD1D5DB)),
                const SizedBox(height: 12),
                Text(
                  isMyClaims ? 'No claims filed yet' : 'No claims against your content',
                  style: const TextStyle(fontSize: 14, color: Color(0xFF9CA3AF)),
                ),
              ],
            ),
          );
        }
        return RefreshIndicator(
          color: const Color(0xFFFF8A00),
          onRefresh: () async => ref.invalidate(provider),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: claims.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (ctx, i) => _ClaimCard(
              claim: claims[i],
              isMyClaim: isMyClaims,
              onCounter: isMyClaims
                  ? null
                  : () => _showCounterForm(ctx, ref, claims[i]['id'] as int),
            ),
          ),
        );
      },
    );
  }

  void _showCounterForm(BuildContext context, WidgetRef ref, int claimId) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CounterNoticeForm(
        claimId: claimId,
        onSubmitted: () => ref.invalidate(_againstMeProvider),
      ),
    );
  }
}

class _ClaimCard extends StatelessWidget {
  final Map<String, dynamic> claim;
  final bool isMyClaim;
  final VoidCallback? onCounter;

  const _ClaimCard({required this.claim, required this.isMyClaim, this.onCounter});

  @override
  Widget build(BuildContext context) {
    final status = claim['status'] as String? ?? 'pending';
    final (sColor, sBg) = switch (status) {
      'upheld'         => (const Color(0xFFDC2626), const Color(0xFFFEE2E2)),
      'dismissed'      => (const Color(0xFF16A34A), const Color(0xFFDCFCE7)),
      'under_review'   => (const Color(0xFF3B82F6), const Color(0xFFEFF6FF)),
      'counter_notice' => (const Color(0xFF7C3AED), const Color(0xFFF5F3FF)),
      _                => (const Color(0xFFD97706), const Color(0xFFFEF3C7)),
    };
    final disabled = claim['content_disabled'] as bool? ?? false;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEEF0F6)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: const Color(0xFFF3F4F6), borderRadius: BorderRadius.circular(6)),
                child: Text(
                  '${(claim['reported_type'] as String? ?? 'post').toUpperCase()} #${claim['reported_id']}',
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF374151)),
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: sBg, borderRadius: BorderRadius.circular(20)),
                child: Text(
                  status.replaceAll('_', ' '),
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: sColor),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (!isMyClaim) ...[
            Text(
              'By: ${claim['claimant_name'] as String? ?? 'Unknown'}',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
            ),
            const SizedBox(height: 4),
          ],
          Text(
            claim['work_description'] as String? ?? '',
            style: const TextStyle(fontSize: 13, color: Color(0xFF6B7280)),
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
          ),
          if (claim['admin_note'] != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(8)),
              child: Text(
                'Admin: ${claim['admin_note']}',
                style: const TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
              ),
            ),
          ],
          if (disabled) ...[
            const SizedBox(height: 8),
            Row(
              children: const [
                Icon(Icons.block_rounded, size: 13, color: Color(0xFFDC2626)),
                SizedBox(width: 4),
                Text('Content disabled', style: TextStyle(fontSize: 11, color: Color(0xFFDC2626), fontWeight: FontWeight.w700)),
              ],
            ),
          ],
          if (!isMyClaim && onCounter != null && status != 'dismissed') ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFF7C3AED)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                ),
                onPressed: onCounter,
                child: const Text(
                  'Submit Counter-Notice',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF7C3AED)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CopyrightClaimForm extends StatefulWidget {
  final VoidCallback? onSubmitted;
  const _CopyrightClaimForm({this.onSubmitted});

  @override
  State<_CopyrightClaimForm> createState() => _CopyrightClaimFormState();
}

class _CopyrightClaimFormState extends State<_CopyrightClaimForm> {
  final _typeCtrl        = TextEditingController(text: 'post');
  final _idCtrl          = TextEditingController();
  final _nameCtrl        = TextEditingController();
  final _emailCtrl       = TextEditingController();
  final _descCtrl        = TextEditingController();
  final _urlCtrl         = TextEditingController();
  String _reportedType   = 'post';
  bool _loading          = false;

  @override
  void dispose() {
    for (final c in [_typeCtrl,_idCtrl,_nameCtrl,_emailCtrl,_descCtrl,_urlCtrl]) c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 24),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('File Copyright Claim', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
            const SizedBox(height: 4),
            const Text('Report content that infringes your copyright.', style: TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
            const SizedBox(height: 18),

            // Content type + ID
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Content Type', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        decoration: BoxDecoration(
                          border: Border.all(color: const Color(0xFFEEF0F6)),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            value: _reportedType,
                            isExpanded: true,
                            items: const [
                              DropdownMenuItem(value: 'post', child: Text('Post', style: TextStyle(fontSize: 13))),
                              DropdownMenuItem(value: 'story', child: Text('Story', style: TextStyle(fontSize: 13))),
                            ],
                            onChanged: (v) => setState(() => _reportedType = v!),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _Field(ctrl: _idCtrl, label: 'Content ID', hint: 'e.g. 123', keyboardType: TextInputType.number),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _Field(ctrl: _nameCtrl, label: 'Your Full Name', hint: 'Legal name of copyright holder'),
            const SizedBox(height: 12),
            _Field(ctrl: _emailCtrl, label: 'Your Email', hint: 'Contact email', keyboardType: TextInputType.emailAddress),
            const SizedBox(height: 12),
            _Field(ctrl: _descCtrl, label: 'Work Description', hint: 'Describe your original work and why this content infringes it...', maxLines: 4),
            const SizedBox(height: 12),
            _Field(ctrl: _urlCtrl, label: 'Original Work URL (optional)', hint: 'Link to your original content'),
            const SizedBox(height: 20),

            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFFEF3C7), borderRadius: BorderRadius.circular(10)),
              child: const Text(
                'By submitting this claim, you confirm under penalty of perjury that you are the copyright holder or authorized to act on their behalf.',
                style: TextStyle(fontSize: 11, color: Color(0xFF92400E), height: 1.5),
              ),
            ),
            const SizedBox(height: 16),

            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFF8A00),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: _loading ? null : _submit,
                child: _loading
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Submit Claim', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    final id = int.tryParse(_idCtrl.text.trim());
    if (id == null || _nameCtrl.text.trim().isEmpty || _emailCtrl.text.trim().isEmpty || _descCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill in all required fields')));
      return;
    }
    setState(() => _loading = true);
    try {
      await CommunityRepository().submitCopyrightClaim(
        reportedType:    _reportedType,
        reportedId:      id,
        claimantName:    _nameCtrl.text.trim(),
        claimantEmail:   _emailCtrl.text.trim(),
        workDescription: _descCtrl.text.trim(),
        originalUrl:     _urlCtrl.text.trim(),
      );
      if (mounted) {
        Navigator.pop(context);
        widget.onSubmitted?.call();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Copyright claim submitted successfully'), backgroundColor: Color(0xFF16A34A)),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: const Color(0xFFDC2626)),
        );
      }
    }
  }
}

class _CounterNoticeForm extends StatefulWidget {
  final int claimId;
  final VoidCallback? onSubmitted;
  const _CounterNoticeForm({required this.claimId, this.onSubmitted});

  @override
  State<_CounterNoticeForm> createState() => _CounterNoticeFormState();
}

class _CounterNoticeFormState extends State<_CounterNoticeForm> {
  final _statementCtrl   = TextEditingController();
  final _jurisdictionCtrl = TextEditingController();
  bool _loading = false;

  @override
  void dispose() {
    _statementCtrl.dispose();
    _jurisdictionCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
          const SizedBox(height: 16),
          const Text('Submit Counter-Notice', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
          const SizedBox(height: 4),
          const Text('Dispute a copyright claim against your content.', style: TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
          const SizedBox(height: 18),
          _Field(ctrl: _statementCtrl, label: 'Statement', hint: 'Explain why you have the right to use this content...', maxLines: 4),
          const SizedBox(height: 12),
          _Field(ctrl: _jurisdictionCtrl, label: 'Jurisdiction (optional)', hint: 'e.g. Somalia, US, UK'),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(10)),
            child: const Text(
              'By submitting, you consent to jurisdiction of the court and acknowledge that the information is accurate.',
              style: TextStyle(fontSize: 11, color: Color(0xFF1E40AF), height: 1.5),
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF7C3AED),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: _loading ? null : _submit,
              child: _loading
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Submit Counter-Notice', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    if (_statementCtrl.text.trim().length < 20) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Statement must be at least 20 characters')));
      return;
    }
    setState(() => _loading = true);
    try {
      await CommunityRepository().submitCounterNotice(
        widget.claimId,
        _statementCtrl.text.trim(),
        jurisdiction: _jurisdictionCtrl.text.trim(),
      );
      if (mounted) {
        Navigator.pop(context);
        widget.onSubmitted?.call();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Counter-notice submitted'), backgroundColor: Color(0xFF7C3AED)),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: const Color(0xFFDC2626)),
        );
      }
    }
  }
}

class _Field extends StatelessWidget {
  final TextEditingController ctrl;
  final String label, hint;
  final int maxLines;
  final TextInputType keyboardType;

  const _Field({
    required this.ctrl,
    required this.label,
    required this.hint,
    this.maxLines = 1,
    this.keyboardType = TextInputType.text,
  });

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
      const SizedBox(height: 6),
      TextField(
        controller: ctrl,
        maxLines: maxLines,
        keyboardType: keyboardType,
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: Color(0xFFD1D5DB), fontSize: 13),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFEEF0F6))),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFEEF0F6))),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFFF8A00))),
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        ),
      ),
    ],
  );
}
