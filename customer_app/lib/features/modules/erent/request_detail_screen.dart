import 'package:flutter/material.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_colors.dart';

const _kOrange = Color(0xFFFF6B35);
const _kTeal   = Color(0xFF0EA5E9);
const _kMuted  = Color(0xFF94A3B8);

class RequestDetailScreen extends StatefulWidget {
  final Map<String, dynamic> request;
  const RequestDetailScreen({super.key, required this.request});

  static Future<void> push(BuildContext context, Map<String, dynamic> request) =>
      Navigator.push(context, MaterialPageRoute(builder: (_) => RequestDetailScreen(request: request)));

  @override
  State<RequestDetailScreen> createState() => _RequestDetailScreenState();
}

class _RequestDetailScreenState extends State<RequestDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;
  final _svc = ModuleApiService();

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surf   = isDark ? const Color(0xFF1E293B) : Colors.white;
    final txt    = isDark ? Colors.white : const Color(0xFF1A2340);

    final req = widget.request;
    final status = req['status'] as String? ?? 'open';

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: surf,
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(req['request_ref'] ?? 'Request', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          Text(_statusLabel(status), style: TextStyle(fontSize: 11, color: _statusColor(status))),
        ]),
        bottom: TabBar(
          controller: _tab,
          labelColor: _kOrange, unselectedLabelColor: _kMuted,
          indicatorColor: _kOrange,
          labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
          tabs: const [Tab(text: 'Details'), Tab(text: 'Recommendations'), Tab(text: 'Chat')],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: [
          _DetailsTab(request: req, isDark: isDark, txt: txt),
          _RecommendationsTab(request: req, svc: _svc, isDark: isDark, txt: txt),
          _CustomerChatTab(request: req, svc: _svc, isDark: isDark),
        ],
      ),
    );
  }

  String _statusLabel(String s) {
    const map = {
      'open': 'Waiting for agent', 'assigned': 'Agent accepted',
      'searching': 'Searching…', 'matched': 'Match found',
      'viewing_scheduled': 'Viewing scheduled', 'completed': 'Completed', 'cancelled': 'Cancelled',
    };
    return map[s] ?? s;
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'open':               return _kOrange;
      case 'assigned':           return _kTeal;
      case 'searching':          return const Color(0xFF8B5CF6);
      case 'matched':            return const Color(0xFF10B981);
      case 'viewing_scheduled':  return const Color(0xFF6366F1);
      case 'completed':          return const Color(0xFF6B7280);
      case 'cancelled':          return const Color(0xFFEF4444);
      default:                   return _kMuted;
    }
  }
}

// ─── Details Tab ──────────────────────────────────────────────────────────────

class _DetailsTab extends StatelessWidget {
  final Map<String, dynamic> request;
  final bool isDark;
  final Color txt;
  const _DetailsTab({required this.request, required this.isDark, required this.txt});

  @override
  Widget build(BuildContext context) {
    final surf = isDark ? const Color(0xFF1E293B) : Colors.white;
    final r    = request;

    return ListView(padding: const EdgeInsets.all(16), children: [
      // Status card
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [Color(0xFFFF6B35), Color(0xFFFF9500)]),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(r['request_ref'] ?? '', style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(_purposeLabel(r['purpose']),
            style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          if (r['agent_name'] != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(99)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.person_rounded, size: 14, color: Colors.white),
                const SizedBox(width: 6),
                Text('Agent: ${r['agent_name']}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
              ]),
            ),
        ]),
      ),
      const SizedBox(height: 16),

      // Info grid
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: surf, borderRadius: BorderRadius.circular(16)),
        child: Column(children: [
          _InfoRow(Icons.home_rounded, 'Type', (r['type'] as String?)?.capitalize() ?? 'Any', txt),
          _InfoRow(Icons.location_on_rounded, 'District', r['district_name'] ?? 'Any', txt),
          _InfoRow(Icons.bed_rounded, 'Bedrooms', r['bedrooms']?.toString() ?? 'Any', txt),
          if (r['budget_min'] != null || r['budget_max'] != null)
            _InfoRow(Icons.attach_money_rounded, 'Budget',
              '\$${_fmt(r['budget_min'])}–\$${_fmt(r['budget_max'])}/mo', txt),
          if (r['move_in_date'] != null)
            _InfoRow(Icons.calendar_today_rounded, 'Move-in', _formatDate(r['move_in_date']), txt),
        ]),
      ),

      if (r['description'] != null && (r['description'] as String).isNotEmpty) ...[
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: surf, borderRadius: BorderRadius.circular(16)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Notes', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: _kMuted)),
            const SizedBox(height: 6),
            Text(r['description'] as String, style: TextStyle(color: txt, fontSize: 13)),
          ]),
        ),
      ],
    ]);
  }

  String _purposeLabel(dynamic p) {
    switch (p) { case 'buy': return 'Looking to Buy'; case 'lease': return 'Looking to Lease'; default: return 'Looking to Rent'; }
  }

  String _fmt(dynamic v) => v != null ? (double.tryParse(v.toString()) ?? 0).round().toString() : '0';
  String _formatDate(dynamic d) {
    try { final dt = DateTime.parse(d.toString()); return '${dt.day}/${dt.month}/${dt.year}'; } catch (_) { return d.toString(); }
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color txt;
  const _InfoRow(this.icon, this.label, this.value, this.txt);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(children: [
      Icon(icon, size: 18, color: _kOrange),
      const SizedBox(width: 12),
      Text(label, style: const TextStyle(color: _kMuted, fontSize: 13)),
      const Spacer(),
      Text(value, style: TextStyle(color: txt, fontWeight: FontWeight.w700, fontSize: 13)),
    ]),
  );
}

// ─── Recommendations Tab ──────────────────────────────────────────────────────

class _RecommendationsTab extends StatefulWidget {
  final Map<String, dynamic> request;
  final ModuleApiService svc;
  final bool isDark;
  final Color txt;
  const _RecommendationsTab({required this.request, required this.svc, required this.isDark, required this.txt});
  @override
  State<_RecommendationsTab> createState() => _RecommendationsTabState();
}

class _RecommendationsTabState extends State<_RecommendationsTab> {
  List<Map<String, dynamic>> _recs     = [];
  List<Map<String, dynamic>> _viewings = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    try {
      final r = await widget.svc.getRequestRecommendations(widget.request['id'] as int);
      final v = await widget.svc.getRequestViewings(widget.request['id'] as int);
      if (mounted) setState(() {
        _recs     = List<Map<String, dynamic>>.from((r['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _viewings = List<Map<String, dynamic>>.from((v['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _loading  = false;
      });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _respond(int recId, String action, {double? counterPrice, String? counterMessage}) async {
    try {
      await widget.svc.respondRecommendation(widget.request['id'] as int, recId, action,
        counterPrice: counterPrice, counterMessage: counterMessage);
      _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red));
    }
  }

  Future<void> _confirmViewing(int viewId, String action) async {
    try {
      await widget.svc.confirmViewing(widget.request['id'] as int, viewId, action);
      _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator(color: _kOrange));

    if (_recs.isEmpty && _viewings.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.home_search_rounded, size: 64, color: _kOrange.withValues(alpha: 0.3)),
        const SizedBox(height: 16),
        Text('No recommendations yet', style: TextStyle(color: widget.txt, fontWeight: FontWeight.w700, fontSize: 15)),
        const SizedBox(height: 6),
        const Text('Your agent will recommend properties here.', style: TextStyle(color: _kMuted, fontSize: 13)),
      ]));
    }

    return RefreshIndicator(
      onRefresh: _load, color: _kOrange,
      child: ListView(padding: const EdgeInsets.all(16), children: [
        if (_viewings.isNotEmpty) ...[
          _SectionHeader('Viewing Appointments', Icons.calendar_today_rounded, const Color(0xFF6366F1)),
          ..._viewings.map((v) => _ViewingCard(viewing: v, isDark: widget.isDark, txt: widget.txt, onConfirm: _confirmViewing)),
          const SizedBox(height: 8),
        ],
        if (_recs.isNotEmpty) ...[
          _SectionHeader('Recommended Properties', Icons.home_rounded, _kOrange),
          ..._recs.map((r) => _RecCard(rec: r, isDark: widget.isDark, txt: widget.txt, onRespond: _respond)),
        ],
      ]),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final IconData icon;
  final Color color;
  const _SectionHeader(this.title, this.icon, this.color);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(children: [
      Icon(icon, size: 16, color: color),
      const SizedBox(width: 8),
      Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: color)),
    ]),
  );
}

class _RecCard extends StatelessWidget {
  final Map<String, dynamic> rec;
  final bool isDark;
  final Color txt;
  final Future<void> Function(int recId, String action, {double? counterPrice, String? counterMessage}) onRespond;
  const _RecCard({required this.rec, required this.isDark, required this.txt, required this.onRespond});

  Color get _col {
    switch (rec['status']) {
      case 'accepted':  return const Color(0xFF10B981);
      case 'rejected':  return const Color(0xFFEF4444);
      case 'countered': return const Color(0xFF8B5CF6);
      default:          return _kOrange;
    }
  }

  Future<void> _showCounterDialog(BuildContext context) async {
    final priceCtl = TextEditingController();
    final msgCtl   = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Counter Offer', style: TextStyle(fontWeight: FontWeight.w800)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Propose a different price to your agent:', style: TextStyle(fontSize: 13)),
          const SizedBox(height: 12),
          TextField(
            controller: priceCtl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: 'Your price (e.g. 400)',
              prefixIcon: const Icon(Icons.attach_money_rounded, color: _kOrange),
              suffixText: '/mo',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: msgCtl,
            maxLines: 2,
            decoration: InputDecoration(
              labelText: 'Note (optional)',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: _kOrange, foregroundColor: Colors.white),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Send Counter'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      final price = double.tryParse(priceCtl.text.trim());
      final note  = msgCtl.text.trim();
      await onRespond(rec['id'] as int, 'counter',
        counterPrice: price, counterMessage: note.isNotEmpty ? note : null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final surf       = isDark ? const Color(0xFF1E293B) : Colors.white;
    final isPending  = rec['status'] == 'pending';
    final isCountered= rec['status'] == 'countered';
    final offeredPrice = rec['offered_price'];
    final counterPrice = rec['counter_price'];

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: surf, borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _col.withValues(alpha: 0.3)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (rec['thumbnail'] != null)
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            child: Image.network(rec['thumbnail'] as String, height: 160, width: double.infinity, fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(height: 120, color: _kOrange.withValues(alpha: 0.1),
                child: const Icon(Icons.home_rounded, size: 48, color: _kOrange))),
          ),
        Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(rec['property_title'] ?? 'Property',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: txt))),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: _col.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(99)),
              child: Text((rec['status'] as String? ?? '').toUpperCase(),
                style: TextStyle(color: _col, fontSize: 10, fontWeight: FontWeight.w800)),
            ),
          ]),
          const SizedBox(height: 4),
          Text('\$${rec['monthly_rent']}/mo · ${rec['district_name'] ?? ''} · ${rec['bedrooms'] ?? '?'} bed',
            style: const TextStyle(color: _kMuted, fontSize: 12)),
          if (rec['agent_name'] != null) ...[
            const SizedBox(height: 4),
            Text('From agent: ${rec['agent_name']}', style: const TextStyle(color: _kTeal, fontSize: 11, fontWeight: FontWeight.w600)),
          ],

          // Offered price banner
          if (offeredPrice != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF10B981).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFF10B981).withValues(alpha: 0.3)),
              ),
              child: Row(children: [
                const Icon(Icons.local_offer_rounded, size: 14, color: Color(0xFF10B981)),
                const SizedBox(width: 6),
                Text("Agent's offer: \$$offeredPrice/mo",
                  style: const TextStyle(color: Color(0xFF10B981), fontSize: 12, fontWeight: FontWeight.w700)),
              ]),
            ),
          ],

          // Counter price (if customer countered)
          if (counterPrice != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF8B5CF6).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(children: [
                const Icon(Icons.swap_horiz_rounded, size: 14, color: Color(0xFF8B5CF6)),
                const SizedBox(width: 6),
                Text("Your counter: \$$counterPrice/mo",
                  style: const TextStyle(color: Color(0xFF8B5CF6), fontSize: 12, fontWeight: FontWeight.w700)),
              ]),
            ),
          ],

          if (rec['message'] != null && (rec['message'] as String).isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity, padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: _kOrange.withValues(alpha: 0.07), borderRadius: BorderRadius.circular(10)),
              child: Text('"${rec['message']}"',
                style: const TextStyle(color: _kOrange, fontSize: 12, fontStyle: FontStyle.italic)),
            ),
          ],

          if (isPending) ...[
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: ElevatedButton(
                onPressed: () => onRespond(rec['id'] as int, 'accept'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF10B981), foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Accept', style: TextStyle(fontWeight: FontWeight.w800)),
              )),
              const SizedBox(width: 6),
              if (offeredPrice != null) ...[
                Expanded(child: OutlinedButton(
                  onPressed: () => _showCounterDialog(context),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF8B5CF6),
                    side: const BorderSide(color: Color(0xFF8B5CF6)),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: const Text('Counter', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                )),
                const SizedBox(width: 6),
              ],
              Expanded(child: OutlinedButton(
                onPressed: () => onRespond(rec['id'] as int, 'reject'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFFEF4444),
                  side: const BorderSide(color: Color(0xFFEF4444)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Reject', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
              )),
            ]),
          ],

          if (isCountered) ...[
            const SizedBox(height: 10),
            const Text('Waiting for agent to respond to your counter offer…',
              style: TextStyle(color: _kMuted, fontSize: 11, fontStyle: FontStyle.italic)),
          ],
        ])),
      ]),
    );
  }
}

class _ViewingCard extends StatelessWidget {
  final Map<String, dynamic> viewing;
  final bool isDark;
  final Color txt;
  final Future<void> Function(int, String) onConfirm;
  const _ViewingCard({required this.viewing, required this.isDark, required this.txt, required this.onConfirm});

  Color get _col {
    switch (viewing['status']) {
      case 'confirmed':  return const Color(0xFF10B981);
      case 'cancelled':  return const Color(0xFFEF4444);
      case 'completed':  return const Color(0xFF6B7280);
      default:           return const Color(0xFF6366F1);
    }
  }

  @override
  Widget build(BuildContext context) {
    final surf = isDark ? const Color(0xFF1E293B) : Colors.white;
    final isProp = viewing['status'] == 'proposed';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: surf, borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _col.withValues(alpha: 0.3)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.calendar_today_rounded, size: 18, color: _col),
          const SizedBox(width: 8),
          Expanded(child: Text(_formatDt(viewing['proposed_at']),
            style: TextStyle(fontWeight: FontWeight.w800, color: txt, fontSize: 13))),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: _col.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(99)),
            child: Text((viewing['status'] as String? ?? '').toUpperCase(),
              style: TextStyle(color: _col, fontSize: 10, fontWeight: FontWeight.w800)),
          ),
        ]),
        if (viewing['property_title'] != null) ...[
          const SizedBox(height: 6),
          Text('Property: ${viewing['property_title']}', style: const TextStyle(color: _kMuted, fontSize: 12)),
        ],
        if (viewing['agent_name'] != null)
          Text('Agent: ${viewing['agent_name']}', style: const TextStyle(color: _kTeal, fontSize: 12)),
        if (viewing['notes'] != null && (viewing['notes'] as String).isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(viewing['notes'] as String, style: const TextStyle(color: _kMuted, fontSize: 12, fontStyle: FontStyle.italic)),
        ],
        if (isProp) ...[
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: ElevatedButton(
              onPressed: () => onConfirm(viewing['id'] as int, 'confirm'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF10B981), foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Confirm', style: TextStyle(fontWeight: FontWeight.w800)),
            )),
            const SizedBox(width: 8),
            Expanded(child: OutlinedButton(
              onPressed: () => onConfirm(viewing['id'] as int, 'cancel'),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFFEF4444),
                side: const BorderSide(color: Color(0xFFEF4444)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
          ]),
        ],
      ]),
    );
  }

  String _formatDt(dynamic d) {
    if (d == null) return '';
    try {
      final dt = DateTime.parse(d.toString()).toLocal();
      const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return '${days[dt.weekday - 1]}, ${dt.day} ${months[dt.month - 1]} ${dt.year}  ${dt.hour.toString().padLeft(2,'0')}:${dt.minute.toString().padLeft(2,'0')}';
    } catch (_) { return d.toString(); }
  }
}

// ─── Chat Tab (Customer) ──────────────────────────────────────────────────────

class _CustomerChatTab extends StatefulWidget {
  final Map<String, dynamic> request;
  final ModuleApiService svc;
  final bool isDark;
  const _CustomerChatTab({required this.request, required this.svc, required this.isDark});
  @override
  State<_CustomerChatTab> createState() => _CustomerChatTabState();
}

class _CustomerChatTabState extends State<_CustomerChatTab> {
  List<Map<String, dynamic>> _messages = [];
  bool   _loading = true;
  bool   _sending = false;
  final  _msgCtl  = TextEditingController();
  final  _scroll  = ScrollController();

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose()   { _msgCtl.dispose(); _scroll.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final res = await widget.svc.getRequestMessages(widget.request['id'] as int);
      if (mounted) setState(() {
        _messages = List<Map<String, dynamic>>.from(
          (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _loading = false;
      });
      _scrollBottom();
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _send() async {
    final text = _msgCtl.text.trim();
    if (text.isEmpty) return;
    _msgCtl.clear();
    setState(() => _sending = true);
    try {
      await widget.svc.sendRequestMessage(widget.request['id'] as int, text);
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _scrollBottom() => WidgetsBinding.instance.addPostFrameCallback((_) {
    if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent,
      duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
  });

  @override
  Widget build(BuildContext context) {
    final bg   = widget.isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);
    final surf = widget.isDark ? const Color(0xFF1E293B) : Colors.white;

    if (widget.request['agent_user_id'] == null) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.lock_outline_rounded, size: 48, color: _kOrange.withValues(alpha: 0.4)),
        const SizedBox(height: 12),
        const Text('Chat available once an agent accepts\nyour request.',
          textAlign: TextAlign.center,
          style: TextStyle(color: _kMuted, fontSize: 13)),
      ]));
    }

    if (_loading) return const Center(child: CircularProgressIndicator(color: _kOrange));

    return Column(children: [
      Expanded(child: _messages.isEmpty
        ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.chat_bubble_outline_rounded, size: 48, color: _kOrange.withValues(alpha: 0.3)),
            const SizedBox(height: 12),
            const Text('Start chatting with your agent!',
              style: TextStyle(color: _kMuted), textAlign: TextAlign.center),
          ]))
        : ListView.builder(
            controller: _scroll,
            padding: const EdgeInsets.all(16),
            itemCount: _messages.length,
            itemBuilder: (_, i) {
              final m = _messages[i];
              final isMe = (m['sender_role'] as String?) == 'customer';
              return _Bubble(msg: m, isMe: isMe, isDark: widget.isDark);
            },
          )),
      Container(
        padding: EdgeInsets.only(left: 12, right: 8, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 8),
        decoration: BoxDecoration(color: surf, border: Border(top: BorderSide(color: widget.isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)))),
        child: Row(children: [
          Expanded(child: TextField(
            controller: _msgCtl,
            maxLines: null,
            decoration: InputDecoration(
              hintText: 'Message your agent…',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
              filled: true, fillColor: bg,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            ),
            textInputAction: TextInputAction.send,
            onSubmitted: (_) => _send(),
          )),
          const SizedBox(width: 6),
          Material(
            color: _kOrange, borderRadius: BorderRadius.circular(24),
            child: InkWell(
              borderRadius: BorderRadius.circular(24),
              onTap: _sending ? null : _send,
              child: const Padding(padding: EdgeInsets.all(10),
                child: Icon(Icons.send_rounded, color: Colors.white, size: 20)),
            ),
          ),
        ]),
      ),
    ]);
  }
}

class _Bubble extends StatelessWidget {
  final Map<String, dynamic> msg;
  final bool isMe;
  final bool isDark;
  const _Bubble({required this.msg, required this.isMe, required this.isDark});
  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: 8, left: isMe ? 48 : 0, right: isMe ? 0 : 48),
    child: Column(crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start, children: [
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isMe ? _kOrange : (isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9)),
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16), topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMe ? 16 : 4), bottomRight: Radius.circular(isMe ? 4 : 16),
          ),
        ),
        child: Text(msg['message'] as String? ?? '',
          style: TextStyle(color: isMe ? Colors.white : (isDark ? Colors.white : const Color(0xFF1A2340)), fontSize: 13)),
      ),
      const SizedBox(height: 2),
      Text(_time(msg['created_at']), style: const TextStyle(color: _kMuted, fontSize: 10)),
    ]),
  );
  String _time(dynamic t) {
    if (t == null) return '';
    try { final dt = DateTime.parse(t.toString()).toLocal(); return '${dt.hour.toString().padLeft(2,'0')}:${dt.minute.toString().padLeft(2,'0')}'; } catch (_) { return ''; }
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

extension _StrCap on String {
  String capitalize() => isEmpty ? this : '${this[0].toUpperCase()}${substring(1)}';
}

String _fmt(dynamic v) => v != null ? (double.tryParse(v.toString()) ?? 0).round().toString() : '0';
