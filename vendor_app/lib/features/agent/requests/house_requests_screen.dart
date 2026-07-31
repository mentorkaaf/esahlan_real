import 'package:flutter/material.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/theme/vc.dart';

const _kTeal = Color(0xFF0EA5E9);

class HouseRequestsScreen extends StatefulWidget {
  const HouseRequestsScreen({super.key});
  @override
  State<HouseRequestsScreen> createState() => _HouseRequestsScreenState();
}

class _HouseRequestsScreenState extends State<HouseRequestsScreen> {
  List<Map<String, dynamic>> _requests = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await AgentRepository.instance.houseRequests();
      final items = List<Map<String, dynamic>>.from(
        (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
      if (mounted) setState(() { _requests = items; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _markContacted(int id) async {
    try {
      await AgentRepository.instance.contactRequest(id);
      setState(() {
        final idx = _requests.indexWhere((r) => r['id'] == id);
        if (idx != -1) _requests[idx] = {..._requests[idx], 'status': 'contacted'};
      });
      _showSnack('Marked as contacted ✓');
    } catch (e) {
      _showSnack('Error: $e', error: true);
    }
  }

  Future<void> _closeRequest(int id) async {
    try {
      await AgentRepository.instance.closeRequest(id);
      setState(() {
        final idx = _requests.indexWhere((r) => r['id'] == id);
        if (idx != -1) _requests[idx] = {..._requests[idx], 'status': 'closed'};
      });
      _showSnack('Request closed');
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
    final bg   = isDark ? VC.navy     : VC.lightBg;
    final surf = isDark ? VC.navyLight : VC.lightSurface;
    final txt  = isDark ? VC.text     : const Color(0xFF1A2340);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: surf,
        title: const Text('House Requests'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: _kTeal))
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.error_outline_rounded, size: 48, color: VC.red),
                  const SizedBox(height: 12),
                  Text(_error!, style: TextStyle(color: txt), textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  ElevatedButton(onPressed: _load, child: const Text('Retry')),
                ]))
              : _requests.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.inbox_rounded, size: 64, color: _kTeal.withValues(alpha: 0.3)),
                      const SizedBox(height: 16),
                      Text('No house requests yet', style: TextStyle(color: txt, fontWeight: FontWeight.w700, fontSize: 16)),
                      const SizedBox(height: 6),
                      Text('Customers in your area will appear here', style: TextStyle(color: VC.textSec, fontSize: 13)),
                    ]))
                  : RefreshIndicator(
                      onRefresh: _load,
                      color: _kTeal,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _requests.length,
                        itemBuilder: (_, i) => _RequestCard(
                          request: _requests[i],
                          isDark: isDark,
                          txt: txt,
                          onContact: () => _markContacted(_requests[i]['id'] as int),
                          onClose: () => _closeRequest(_requests[i]['id'] as int),
                        ),
                      ),
                    ),
    );
  }
}

class _RequestCard extends StatelessWidget {
  final Map<String, dynamic> request;
  final bool isDark;
  final Color txt;
  final VoidCallback onContact;
  final VoidCallback onClose;
  const _RequestCard({required this.request, required this.isDark, required this.txt, required this.onContact, required this.onClose});

  Color get _statusColor {
    switch (request['status']) {
      case 'contacted': return const Color(0xFFF59E0B);
      case 'closed':    return const Color(0xFF9CA3AF);
      default:          return _kTeal;
    }
  }

  @override
  Widget build(BuildContext context) {
    final surf = isDark ? VC.navyLight : VC.lightSurface;
    final isOpen = request['status'] == 'open';
    final budgetMin = request['budget_min'];
    final budgetMax = request['budget_max'];

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isOpen ? _kTeal.withValues(alpha: 0.25) : (isDark ? VC.border : VC.lightBorder),
          width: isOpen ? 1.5 : 1,
        ),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Header row
          Row(children: [
            // Avatar
            Container(
              width: 44, height: 44,
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF0369A1), Color(0xFF0EA5E9)]),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Center(child: Text(
                (request['customer_name'] as String? ?? '?').substring(0, 1).toUpperCase(),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 18),
              )),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(request['customer_name'] ?? 'Customer', style: TextStyle(color: txt, fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 2),
              Text(_timeAgo(request['created_at']), style: TextStyle(color: VC.textSec, fontSize: 11)),
            ])),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _statusColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(99),
              ),
              child: Text((request['status'] as String? ?? 'open').toUpperCase(),
                style: TextStyle(color: _statusColor, fontSize: 10, fontWeight: FontWeight.w800)),
            ),
          ]),
          const SizedBox(height: 12),

          // Chips
          Wrap(spacing: 6, runSpacing: 6, children: [
            if (request['district_name'] != null) _Chip(Icons.location_on_rounded, request['district_name'], const Color(0xFF0EA5E9)),
            if (request['type'] != null) _Chip(Icons.home_rounded, (request['type'] as String).toUpperCase(), const Color(0xFF8B5CF6)),
            if (request['bedrooms'] != null) _Chip(Icons.bed_rounded, '${request['bedrooms']} bed', const Color(0xFF10B981)),
            if (budgetMin != null || budgetMax != null)
              _Chip(Icons.attach_money_rounded, _budget(budgetMin, budgetMax), const Color(0xFFF59E0B)),
          ]),

          if (request['description'] != null && (request['description'] as String).isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: isDark ? VC.navyCard : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text('"${request['description']}"',
                style: TextStyle(color: VC.textSec, fontSize: 12, fontStyle: FontStyle.italic)),
            ),
          ],

          if (isOpen) ...[
            const SizedBox(height: 14),
            Row(children: [
              Expanded(child: ElevatedButton.icon(
                onPressed: onContact,
                icon: const Icon(Icons.phone_rounded, size: 15),
                label: const Text('I Can Help', style: TextStyle(fontWeight: FontWeight.w800)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _kTeal, foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              )),
              const SizedBox(width: 8),
              OutlinedButton(
                onPressed: onClose,
                style: OutlinedButton.styleFrom(
                  foregroundColor: VC.textSec,
                  side: BorderSide(color: isDark ? VC.border : VC.lightBorder),
                  padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Close', style: TextStyle(fontSize: 12)),
              ),
            ]),
          ] else if (request['status'] == 'contacted') ...[
            const SizedBox(height: 10),
            Row(children: [
              const Icon(Icons.check_circle_rounded, size: 15, color: Color(0xFFF59E0B)),
              const SizedBox(width: 5),
              Text('You marked this as contacted', style: TextStyle(color: VC.textSec, fontSize: 12)),
            ]),
          ],
        ]),
      ),
    );
  }

  String _timeAgo(dynamic createdAt) {
    if (createdAt == null) return '';
    try {
      final dt = DateTime.parse(createdAt.toString()).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      return '${diff.inDays}d ago';
    } catch (_) { return ''; }
  }

  String _budget(dynamic min, dynamic max) {
    if (min != null && max != null) return '\$${_fmt(min)}–\$${_fmt(max)}/mo';
    if (max != null) return 'Up to \$${_fmt(max)}/mo';
    return 'From \$${_fmt(min)}/mo';
  }

  String _fmt(dynamic v) => (double.tryParse(v.toString()) ?? 0).round().toString();
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
