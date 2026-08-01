import 'package:flutter/material.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/services/fcm_service.dart';
import '../../../core/theme/vc.dart';
import 'request_detail_sheet.dart';

const _kTeal   = Color(0xFF0EA5E9);
const _kOrange = Color(0xFFFF6B35);

// Global key so agent_shell can trigger auto-open after FCM deep link
final houseRequestsScreenKey = GlobalKey<HouseRequestsScreenState>();

class HouseRequestsScreen extends StatefulWidget {
  const HouseRequestsScreen({super.key});
  @override
  State<HouseRequestsScreen> createState() => HouseRequestsScreenState();
}

class HouseRequestsScreenState extends State<HouseRequestsScreen>
    with SingleTickerProviderStateMixin {
  List<Map<String, dynamic>> _all = [];
  bool _loading = true;
  String? _error;
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await AgentRepository.instance.houseRequests();
      final items = List<Map<String, dynamic>>.from(
        (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
      if (mounted) setState(() { _all = items; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  /// Called by agent_shell after FCM deep link switches to requests tab.
  Future<void> openPendingRequest() async {
    final pending = VendorFcmService.consumePendingRequest();
    if (pending == null || !mounted) return;

    // Reload to ensure data is fresh
    await _load();
    if (!mounted) return;

    final requestId = int.tryParse(pending.id);
    final match = _all.cast<Map<String, dynamic>?>().firstWhere(
      (r) => r?['id'] == requestId,
      orElse: () => null,
    );
    if (match != null && mounted) {
      await RequestDetailSheet.show(context, match, _load, initialTab: pending.tab);
    }
  }

  List<Map<String, dynamic>> get _open     => _all.where((r) => r['status'] == 'open').toList();
  List<Map<String, dynamic>> get _myActive => _all.where((r) => r['status'] != 'open').toList();

  Future<void> _assign(int id) async {
    try {
      await AgentRepository.instance.assignRequest(id);
      _showSnack('Request accepted! ✓');
      _load();
    } catch (e) {
      _showSnack('Error: $e', error: true);
    }
  }

  Future<void> _updateStatus(int id, String status) async {
    try {
      await AgentRepository.instance.updateRequestStatus(id, status);
      _showSnack('Status updated');
      _load();
    } catch (e) {
      _showSnack('Error: $e', error: true);
    }
  }

  void _showSnack(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: error ? VC.red : _kTeal));
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg   = isDark ? VC.navy      : VC.lightBg;
    final surf = isDark ? VC.navyLight : VC.lightSurface;
    final txt  = isDark ? VC.text      : const Color(0xFF1A2340);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: surf,
        title: const Text('House Requests', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
        bottom: TabBar(
          controller: _tab,
          labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
          indicatorColor: _kTeal,
          labelColor: _kTeal,
          unselectedLabelColor: VC.textSec,
          tabs: [
            Tab(text: 'Open (${_open.length})'),
            Tab(text: 'My Active (${_myActive.length})'),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: _kTeal))
          : _error != null
              ? _ErrorView(error: _error!, onRetry: _load, txt: txt)
              : TabBarView(
                  controller: _tab,
                  children: [
                    _RequestList(
                      requests: _open, isDark: isDark, txt: txt,
                      onRefresh: _load,
                      onAssign: _assign,
                      onUpdateStatus: _updateStatus,
                    ),
                    _RequestList(
                      requests: _myActive, isDark: isDark, txt: txt,
                      onRefresh: _load,
                      onAssign: _assign,
                      onUpdateStatus: _updateStatus,
                      emptyMsg: 'No active requests yet.\nAccept requests from the Open tab.',
                    ),
                  ],
                ),
    );
  }
}

// ─── Request List ─────────────────────────────────────────────────────────────

class _RequestList extends StatelessWidget {
  final List<Map<String, dynamic>> requests;
  final bool isDark;
  final Color txt;
  final Future<void> Function() onRefresh;
  final Future<void> Function(int) onAssign;
  final Future<void> Function(int, String) onUpdateStatus;
  final String emptyMsg;

  const _RequestList({
    required this.requests, required this.isDark, required this.txt,
    required this.onRefresh, required this.onAssign, required this.onUpdateStatus,
    this.emptyMsg = 'No open requests in your area yet.',
  });

  @override
  Widget build(BuildContext context) {
    if (requests.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.inbox_rounded, size: 64, color: _kTeal.withValues(alpha: 0.25)),
        const SizedBox(height: 16),
        Text(emptyMsg, textAlign: TextAlign.center,
          style: TextStyle(color: txt, fontWeight: FontWeight.w700, fontSize: 15)),
      ]));
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      color: _kTeal,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: requests.length,
        itemBuilder: (_, i) => GestureDetector(
          onTap: requests[i]['status'] != 'open'
              ? () => RequestDetailSheet.show(context, requests[i], onRefresh)
              : null,
          onLongPress: () => RequestDetailSheet.show(context, requests[i], onRefresh),
          child: _RequestCard(
            request: requests[i],
            isDark: isDark, txt: txt,
            onAssign: () => onAssign(requests[i]['id'] as int),
            onUpdateStatus: (s) => onUpdateStatus(requests[i]['id'] as int, s),
          ),
        ),
      ),
    );
  }
}

// ─── Request Card ─────────────────────────────────────────────────────────────

class _RequestCard extends StatelessWidget {
  final Map<String, dynamic> request;
  final bool isDark;
  final Color txt;
  final VoidCallback onAssign;
  final void Function(String) onUpdateStatus;

  const _RequestCard({
    required this.request, required this.isDark, required this.txt,
    required this.onAssign, required this.onUpdateStatus,
  });

  String get _status => request['status'] as String? ?? 'open';

  Color get _statusColor {
    switch (_status) {
      case 'assigned':  return _kTeal;
      case 'searching': return const Color(0xFF8B5CF6);
      case 'matched':   return const Color(0xFF10B981);
      case 'completed': return const Color(0xFF6B7280);
      case 'cancelled': return VC.red;
      default:          return _kOrange;
    }
  }

  String get _statusLabel {
    switch (_status) {
      case 'open':      return 'OPEN';
      case 'assigned':  return 'ACCEPTED';
      case 'searching': return 'SEARCHING';
      case 'matched':   return 'MATCHED';
      case 'completed': return 'COMPLETED';
      case 'cancelled': return 'CANCELLED';
      default:          return _status.toUpperCase();
    }
  }

  @override
  Widget build(BuildContext context) {
    final surf = isDark ? VC.navyLight : VC.lightSurface;
    final isOpen    = _status == 'open';
    final isMyTask  = !isOpen && _status != 'completed' && _status != 'cancelled';

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isOpen
              ? _kOrange.withValues(alpha: 0.3)
              : isMyTask
                  ? _kTeal.withValues(alpha: 0.3)
                  : (isDark ? VC.border : VC.lightBorder),
          width: (isOpen || isMyTask) ? 1.5 : 1,
        ),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // ── Header Row ──
          Row(children: [
            // Avatar
            Container(
              width: 46, height: 46,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: isOpen
                      ? [const Color(0xFFFF6B35), const Color(0xFFFF9500)]
                      : [const Color(0xFF0369A1), const Color(0xFF0EA5E9)],
                ),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Center(child: Text(
                (request['customer_name'] as String? ?? '?').substring(0, 1).toUpperCase(),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 20),
              )),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(request['customer_name'] ?? 'Customer',
                style: TextStyle(color: txt, fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 2),
              Row(children: [
                if (request['request_ref'] != null) ...[
                  Text(request['request_ref'] as String,
                    style: const TextStyle(color: _kTeal, fontSize: 11, fontWeight: FontWeight.w700)),
                  const Text(' · ', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
                ],
                Text(_timeAgo(request['created_at']),
                  style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
              ]),
            ])),
            // Status badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _statusColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(99),
                border: Border.all(color: _statusColor.withValues(alpha: 0.3)),
              ),
              child: Text(_statusLabel,
                style: TextStyle(color: _statusColor, fontSize: 10, fontWeight: FontWeight.w800)),
            ),
          ]),
          const SizedBox(height: 12),

          // ── Info Chips ──
          Wrap(spacing: 6, runSpacing: 6, children: [
            if (request['purpose'] != null)
              _Chip(_purposeIcon(request['purpose']), (request['purpose'] as String).toUpperCase(), const Color(0xFFFF6B35)),
            if (request['district_name'] != null)
              _Chip(Icons.location_on_rounded, request['district_name'] as String, _kTeal),
            if (request['type'] != null)
              _Chip(Icons.home_rounded, (request['type'] as String).toUpperCase(), const Color(0xFF8B5CF6)),
            if (request['bedrooms'] != null)
              _Chip(Icons.bed_rounded, '${request['bedrooms']} bed', const Color(0xFF10B981)),
            if (request['budget_min'] != null || request['budget_max'] != null)
              _Chip(Icons.attach_money_rounded, _budget(request['budget_min'], request['budget_max']), const Color(0xFFF59E0B)),
            if (request['move_in_date'] != null)
              _Chip(Icons.calendar_today_rounded, _formatDate(request['move_in_date']), const Color(0xFF6366F1)),
          ]),

          // ── Description ──
          if (request['description'] != null && (request['description'] as String).isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: isDark ? VC.navyCard : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text('"${request['description']}"',
                style: TextStyle(color: VC.textSec, fontSize: 12, fontStyle: FontStyle.italic)),
            ),
          ],

          // ── Customer contact (when assigned) ──
          if (!isOpen && request['customer_phone'] != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: _kTeal.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: _kTeal.withValues(alpha: 0.2)),
              ),
              child: Row(children: [
                const Icon(Icons.phone_rounded, size: 14, color: _kTeal),
                const SizedBox(width: 6),
                Text('Customer: ${request['customer_phone']}',
                  style: const TextStyle(color: _kTeal, fontSize: 12, fontWeight: FontWeight.w700)),
              ]),
            ),
          ],

          const SizedBox(height: 14),

          // ── Action Buttons ──
          if (isOpen)
            SizedBox(width: double.infinity, child: ElevatedButton.icon(
              onPressed: onAssign,
              icon: const Icon(Icons.handshake_rounded, size: 16),
              label: const Text('Accept Request', style: TextStyle(fontWeight: FontWeight.w800)),
              style: ElevatedButton.styleFrom(
                backgroundColor: _kOrange, foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ))
          else if (isMyTask)
            _StatusProgressRow(status: _status, onUpdate: onUpdateStatus, isDark: isDark),
        ]),
      ),
    );
  }

  IconData _purposeIcon(dynamic p) {
    switch (p) {
      case 'buy':   return Icons.shopping_bag_rounded;
      case 'lease': return Icons.assignment_rounded;
      default:      return Icons.vpn_key_rounded;
    }
  }

  String _budget(dynamic min, dynamic max) {
    if (min != null && max != null) return '\$${_fmt(min)}–\$${_fmt(max)}/mo';
    if (max != null) return 'Up to \$${_fmt(max)}/mo';
    return 'From \$${_fmt(min)}/mo';
  }

  String _fmt(dynamic v) => (double.tryParse(v.toString()) ?? 0).round().toString();

  String _formatDate(dynamic d) {
    if (d == null) return '';
    try {
      final dt = DateTime.parse(d.toString());
      return '${dt.day}/${dt.month}/${dt.year}';
    } catch (_) { return d.toString(); }
  }

  String _timeAgo(dynamic createdAt) {
    if (createdAt == null) return '';
    try {
      final dt   = DateTime.parse(createdAt.toString()).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24)   return '${diff.inHours}h ago';
      return '${diff.inDays}d ago';
    } catch (_) { return ''; }
  }
}

// ─── Status Progress Row ──────────────────────────────────────────────────────

class _StatusProgressRow extends StatelessWidget {
  final String status;
  final void Function(String) onUpdate;
  final bool isDark;

  const _StatusProgressRow({required this.status, required this.onUpdate, required this.isDark});

  @override
  Widget build(BuildContext context) {
    // Status flow: assigned → searching → matched → completed
    final steps = [
      ('assigned',  'Accepted',  _kTeal),
      ('searching', 'Searching', const Color(0xFF8B5CF6)),
      ('matched',   'Matched',   const Color(0xFF10B981)),
      ('completed', 'Done',      const Color(0xFF6B7280)),
    ];

    final curIdx = steps.indexWhere((s) => s.$1 == status);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Progress bar
      Row(children: List.generate(steps.length, (i) {
        final done   = i <= curIdx;
        final active = i == curIdx;
        final color  = steps[i].$3;
        return Expanded(child: Row(children: [
          Container(
            width: 28, height: 28,
            decoration: BoxDecoration(
              color: done ? color : (isDark ? VC.navyCard : const Color(0xFFF1F5F9)),
              shape: BoxShape.circle,
              border: active ? Border.all(color: color, width: 2) : null,
            ),
            child: Icon(
              done ? Icons.check_rounded : Icons.circle_outlined,
              size: 14, color: done ? Colors.white : VC.textSec,
            ),
          ),
          if (i < steps.length - 1)
            Expanded(child: Container(
              height: 2,
              color: i < curIdx ? steps[i + 1].$3.withValues(alpha: 0.4) : (isDark ? VC.border : VC.lightBorder),
            )),
        ]));
      })),
      const SizedBox(height: 8),
      // Next action buttons
      if (status == 'assigned')
        Row(children: [
          Expanded(child: _ActionBtn('Start Searching', const Color(0xFF8B5CF6), () => onUpdate('searching'))),
          const SizedBox(width: 8),
          _CancelBtn(() => onUpdate('cancelled'), isDark),
        ])
      else if (status == 'searching')
        Row(children: [
          Expanded(child: _ActionBtn('Mark Matched', const Color(0xFF10B981), () => onUpdate('matched'))),
          const SizedBox(width: 8),
          _CancelBtn(() => onUpdate('cancelled'), isDark),
        ])
      else if (status == 'matched')
        Row(children: [
          Expanded(child: _ActionBtn('Mark Completed ✓', const Color(0xFF6B7280), () => onUpdate('completed'))),
          const SizedBox(width: 8),
          _CancelBtn(() => onUpdate('cancelled'), isDark),
        ]),
    ]);
  }
}

class _ActionBtn extends StatelessWidget {
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _ActionBtn(this.label, this.color, this.onTap);
  @override
  Widget build(BuildContext context) => ElevatedButton(
    onPressed: onTap,
    style: ElevatedButton.styleFrom(
      backgroundColor: color, foregroundColor: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ),
    child: Text(label, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
  );
}

class _CancelBtn extends StatelessWidget {
  final VoidCallback onTap;
  final bool isDark;
  const _CancelBtn(this.onTap, this.isDark);
  @override
  Widget build(BuildContext context) => OutlinedButton(
    onPressed: onTap,
    style: OutlinedButton.styleFrom(
      foregroundColor: VC.red,
      side: BorderSide(color: VC.red.withValues(alpha: 0.5)),
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ),
    child: const Text('Cancel', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
  );
}

// ─── Widgets ──────────────────────────────────────────────────────────────────

class _ErrorView extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  final Color txt;
  const _ErrorView({required this.error, required this.onRetry, required this.txt});
  @override
  Widget build(BuildContext context) => Center(child: Column(
    mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(Icons.error_outline_rounded, size: 48, color: VC.red),
      const SizedBox(height: 12),
      Text(error, style: TextStyle(color: txt), textAlign: TextAlign.center),
      const SizedBox(height: 16),
      ElevatedButton(onPressed: onRetry, child: const Text('Retry')),
    ],
  ));
}

class _Chip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _Chip(this.icon, this.label, this.color);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(99),
      border: Border.all(color: color.withValues(alpha: 0.25)),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 11, color: color),
      const SizedBox(width: 4),
      Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
    ]),
  );
}
