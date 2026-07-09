import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/community_repository.dart';

final _moderationStatusProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return CommunityRepository().getMyModerationStatus();
});

final _myAppealsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) {
  return CommunityRepository().getMyAppeals();
});

class TransparencyCenter extends ConsumerWidget {
  const TransparencyCenter({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statusAsync = ref.watch(_moderationStatusProvider);
    final appealsAsync = ref.watch(_myAppealsProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF111827)),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Account Status',
          style: TextStyle(color: Color(0xFF111827), fontWeight: FontWeight.w800, fontSize: 17),
        ),
      ),
      body: statusAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: Color(0xFFFF8A00))),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (status) {
          final accountStatus = status['account_status'] as String? ?? 'good';
          final strikePoints = status['strike_points'] as int? ?? 0;
          final totalStrikes = status['total_strikes'] as int? ?? 0;
          final activeStrikes = (status['active_strikes'] as List?) ?? [];
          final restrictions = (status['restrictions'] as List?) ?? [];
          final pendingPosts = status['pending_posts'] as int? ?? 0;
          final blockedPosts = status['blocked_posts'] as int? ?? 0;

          return RefreshIndicator(
            color: const Color(0xFFFF8A00),
            onRefresh: () async {
              ref.invalidate(_moderationStatusProvider);
              ref.invalidate(_myAppealsProvider);
            },
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _StatusCard(
                  accountStatus: accountStatus,
                  strikePoints: strikePoints,
                  pendingPosts: pendingPosts,
                  blockedPosts: blockedPosts,
                ),
                if (restrictions.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  _RestrictionsCard(restrictions: restrictions),
                ],
                if (activeStrikes.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  _StrikesCard(strikes: activeStrikes, totalStrikes: totalStrikes),
                ],
                const SizedBox(height: 16),
                appealsAsync.when(
                  loading: () => const SizedBox.shrink(),
                  error: (_, __) => const SizedBox.shrink(),
                  data: (appeals) => _AppealsCard(appeals: appeals),
                ),
                const SizedBox(height: 16),
                _GuidelinesCard(),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  final String accountStatus;
  final int strikePoints;
  final int pendingPosts;
  final int blockedPosts;

  const _StatusCard({
    required this.accountStatus,
    required this.strikePoints,
    required this.pendingPosts,
    required this.blockedPosts,
  });

  @override
  Widget build(BuildContext context) {
    final (color, icon, label, desc) = switch (accountStatus) {
      'good'       => (const Color(0xFF16A34A), Icons.verified_rounded,       'Good Standing',  'Your account is in good standing.'),
      'warned'     => (const Color(0xFFD97706), Icons.warning_amber_rounded,   'Warning',        'Your account has received warnings. Further violations may restrict your account.'),
      'at_risk'    => (const Color(0xFFDC2626), Icons.error_outline_rounded,   'At Risk',        'Your account is at risk. Please review community guidelines.'),
      'restricted' => (const Color(0xFF7C3AED), Icons.block_rounded,           'Restricted',     'Some features are temporarily restricted on your account.'),
      _            => (const Color(0xFF16A34A), Icons.verified_rounded,        'Good Standing',  'Your account is in good standing.'),
    };

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFEEF0F6)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48, height: 48,
                decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(14)),
                child: Icon(icon, color: color, size: 26),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: color)),
                    Text(desc, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              _Stat(label: 'Strike Points', value: strikePoints.toString(), color: strikePoints > 0 ? const Color(0xFFDC2626) : const Color(0xFF16A34A)),
              const SizedBox(width: 12),
              _Stat(label: 'Under Review', value: pendingPosts.toString(), color: const Color(0xFFD97706)),
              const SizedBox(width: 12),
              _Stat(label: 'Removed Posts', value: blockedPosts.toString(), color: const Color(0xFFDC2626)),
            ],
          ),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label, value;
  final Color color;
  const _Stat({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(color: color.withOpacity(0.08), borderRadius: BorderRadius.circular(10)),
      child: Column(
        children: [
          Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: color)),
          Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF6B7280)), textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _RestrictionsCard extends StatelessWidget {
  final List restrictions;
  const _RestrictionsCard({required this.restrictions});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: const Color(0xFFFEF3C7),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFF59E0B).withOpacity(0.4)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.info_outline_rounded, color: Color(0xFFD97706), size: 18),
            SizedBox(width: 8),
            Text('Active Restrictions', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF92400E))),
          ],
        ),
        const SizedBox(height: 10),
        ...restrictions.map((r) => Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Text(
            '• ${r['reason'] ?? 'Restriction active'}',
            style: const TextStyle(fontSize: 13, color: Color(0xFF92400E)),
          ),
        )),
      ],
    ),
  );
}

class _StrikesCard extends StatelessWidget {
  final List strikes;
  final int totalStrikes;
  const _StrikesCard({required this.strikes, required this.totalStrikes});

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFEEF0F6)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              const Text('Active Violations', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
              const Spacer(),
              Text('$totalStrikes total', style: const TextStyle(fontSize: 12, color: Color(0xFF9CA3AF))),
            ],
          ),
        ),
        const Divider(height: 1, color: Color(0xFFF3F4F6)),
        ...strikes.map((s) {
          final severity = s['severity'] as String? ?? 'medium';
          final (sColor, sBg) = switch (severity) {
            'critical' => (const Color(0xFFFFFFFF), const Color(0xFF7F1D1D)),
            'high'     => (const Color(0xFFDC2626), const Color(0xFFFEE2E2)),
            'medium'   => (const Color(0xFFD97706), const Color(0xFFFEF3C7)),
            _          => (const Color(0xFF16A34A), const Color(0xFFDCFCE7)),
          };
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: Color(0xFFF3F4F6))),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        (s['violation_type'] as String? ?? '').replaceAll('_', ' ').toUpperCase(),
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF9CA3AF), letterSpacing: 0.5),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        s['reason'] as String? ?? 'Violation of community guidelines',
                        style: const TextStyle(fontSize: 13, color: Color(0xFF374151)),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: sBg, borderRadius: BorderRadius.circular(20)),
                      child: Text(severity[0].toUpperCase() + severity.substring(1), style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: sColor)),
                    ),
                    const SizedBox(height: 4),
                    Text('+${s['points']} pts', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFFDC2626))),
                  ],
                ),
              ],
            ),
          );
        }),
      ],
    ),
  );
}

class _AppealsCard extends ConsumerWidget {
  final List<Map<String, dynamic>> appeals;
  const _AppealsCard({required this.appeals});

  @override
  Widget build(BuildContext context, WidgetRef ref) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFEEF0F6)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              const Text('My Appeals', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
              const Spacer(),
              TextButton(
                onPressed: () => _showAppealForm(context, ref),
                child: const Text('+ New Appeal', style: TextStyle(fontSize: 12, color: Color(0xFFFF8A00), fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ),
        const Divider(height: 1, color: Color(0xFFF3F4F6)),
        if (appeals.isEmpty)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Center(child: Text('No appeals submitted', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 13))),
          )
        else
          ...appeals.map((a) {
            final status = a['status'] as String? ?? 'pending';
            final (sColor, sBg) = switch (status) {
              'approved' => (const Color(0xFF16A34A), const Color(0xFFDCFCE7)),
              'rejected' => (const Color(0xFFDC2626), const Color(0xFFFEE2E2)),
              _          => (const Color(0xFFD97706), const Color(0xFFFEF3C7)),
            };
            return Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: Color(0xFFF3F4F6))),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          (a['action_type'] as String? ?? 'content_removal').replaceAll('_', ' '),
                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF111827)),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: sBg, borderRadius: BorderRadius.circular(20)),
                        child: Text(status[0].toUpperCase() + status.substring(1), style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: sColor)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(a['reason'] as String? ?? '', style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
                  if (a['moderator_note'] != null) ...[
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(8)),
                      child: Text('Admin: ${a['moderator_note']}', style: const TextStyle(fontSize: 11, color: Color(0xFF6B7280))),
                    ),
                  ],
                ],
              ),
            );
          }),
      ],
    ),
  );

  void _showAppealForm(BuildContext context, WidgetRef ref) {
    final postIdCtrl = TextEditingController();
    final reasonCtrl = TextEditingController();
    final evidenceCtrl = TextEditingController();
    bool loading = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => Padding(
          padding: EdgeInsets.only(
            left: 20, right: 20, top: 20,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
              const SizedBox(height: 16),
              const Text('Submit an Appeal', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
              const SizedBox(height: 4),
              const Text('Appeal a removed post. Provide the post ID and reason.', style: TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
              const SizedBox(height: 18),
              _FormField(ctrl: postIdCtrl, label: 'Post ID', hint: 'e.g. 123', keyboardType: TextInputType.number),
              const SizedBox(height: 12),
              _FormField(ctrl: reasonCtrl, label: 'Reason', hint: 'Explain why this content should be restored...', maxLines: 3),
              const SizedBox(height: 12),
              _FormField(ctrl: evidenceCtrl, label: 'Evidence (optional)', hint: 'Any additional context...', maxLines: 2),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF8A00),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: loading ? null : () async {
                    final postId = int.tryParse(postIdCtrl.text.trim());
                    if (postId == null || reasonCtrl.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please fill in the required fields')),
                      );
                      return;
                    }
                    setState(() => loading = true);
                    try {
                      await CommunityRepository().submitAppeal(
                        postId,
                        reasonCtrl.text.trim(),
                        evidence: evidenceCtrl.text.trim(),
                      );
                      if (ctx.mounted) Navigator.pop(ctx);
                      ref.invalidate(_myAppealsProvider);
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Appeal submitted successfully'), backgroundColor: Color(0xFF16A34A)),
                      );
                    } catch (e) {
                      setState(() => loading = false);
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text(e.toString()), backgroundColor: const Color(0xFFDC2626)),
                      );
                    }
                  },
                  child: loading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Submit Appeal', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FormField extends StatelessWidget {
  final TextEditingController ctrl;
  final String label, hint;
  final int maxLines;
  final TextInputType keyboardType;

  const _FormField({
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

class _GuidelinesCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFEEF0F6)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Community Guidelines', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
        const SizedBox(height: 12),
        ...[
          ('No nudity or explicit content', Icons.block_rounded, Color(0xFFDC2626)),
          ('No hate speech or harassment', Icons.sentiment_very_dissatisfied_rounded, Color(0xFFD97706)),
          ('No spam or misleading content', Icons.warning_amber_rounded, Color(0xFFD97706)),
          ('Respect other users', Icons.handshake_rounded, Color(0xFF16A34A)),
        ].map((item) => Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Row(
            children: [
              Icon(item.$2, color: item.$3, size: 18),
              const SizedBox(width: 10),
              Text(item.$1, style: const TextStyle(fontSize: 13, color: Color(0xFF374151))),
            ],
          ),
        )),
      ],
    ),
  );
}
