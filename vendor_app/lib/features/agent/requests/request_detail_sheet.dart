import 'package:flutter/material.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/theme/vc.dart';

const _kTeal   = Color(0xFF0EA5E9);
const _kOrange = Color(0xFFFF6B35);

class RequestDetailSheet extends StatefulWidget {
  final Map<String, dynamic> request;
  final VoidCallback onRefresh;
  final String? initialTab;
  const RequestDetailSheet({super.key, required this.request, required this.onRefresh, this.initialTab});

  static Future<void> show(BuildContext context, Map<String, dynamic> request, VoidCallback onRefresh, {String? initialTab}) =>
      showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => RequestDetailSheet(request: request, onRefresh: onRefresh, initialTab: initialTab),
      );

  @override
  State<RequestDetailSheet> createState() => _RequestDetailSheetState();
}

class _RequestDetailSheetState extends State<RequestDetailSheet>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    final tabIndex = widget.initialTab == 'chat' ? 2 : widget.initialTab == 'recommend' ? 1 : 0;
    _tab = TabController(length: 3, vsync: this, initialIndex: tabIndex);
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surf   = isDark ? VC.navyLight : VC.lightSurface;

    return DraggableScrollableSheet(
      initialChildSize: 0.92,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      builder: (_, ctrl) => Container(
        decoration: BoxDecoration(
          color: surf,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          // Handle
          Padding(
            padding: const EdgeInsets.only(top: 12, bottom: 8),
            child: Container(width: 40, height: 4,
              decoration: BoxDecoration(color: isDark ? VC.border : VC.lightBorder, borderRadius: BorderRadius.circular(99))),
          ),
          // Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(children: [
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(widget.request['customer_name'] ?? 'Customer',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17,
                    color: isDark ? VC.text : const Color(0xFF1A2340))),
                const SizedBox(height: 2),
                if (widget.request['request_ref'] != null)
                  Text(widget.request['request_ref'] as String,
                    style: const TextStyle(color: _kOrange, fontWeight: FontWeight.w700, fontSize: 12)),
              ]),
              const Spacer(),
              IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => Navigator.pop(context)),
            ]),
          ),
          // Tabs
          TabBar(
            controller: _tab,
            labelColor: _kTeal, unselectedLabelColor: VC.textSec,
            indicatorColor: _kTeal,
            labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
            tabs: const [Tab(text: 'Recommend'), Tab(text: 'Viewing'), Tab(text: 'Chat')],
          ),
          Expanded(child: TabBarView(
            controller: _tab,
            children: [
              _RecommendTab(request: widget.request, isDark: isDark, onDone: () { widget.onRefresh(); Navigator.pop(context); }),
              _ViewingTab(request: widget.request, isDark: isDark, onDone: () { widget.onRefresh(); Navigator.pop(context); }),
              _AgentChatTab(request: widget.request, isDark: isDark),
            ],
          )),
        ]),
      ),
    );
  }
}

// ─── Recommend Tab ────────────────────────────────────────────────────────────

class _RecommendTab extends StatefulWidget {
  final Map<String, dynamic> request;
  final bool isDark;
  final VoidCallback onDone;
  const _RecommendTab({required this.request, required this.isDark, required this.onDone});
  @override
  State<_RecommendTab> createState() => _RecommendTabState();
}

class _RecommendTabState extends State<_RecommendTab> {
  List<Map<String, dynamic>> _properties = [];
  List<Map<String, dynamic>> _recs       = [];
  int?    _selectedPropertyId;
  final _msgCtl = TextEditingController();
  final _priceCtl = TextEditingController();
  bool _loading     = true;
  bool _submitting  = false;

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { _msgCtl.dispose(); _priceCtl.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final res  = await AgentRepository.instance.properties(status: 'active');
      final recs = await AgentRepository.instance.getRecommendations(widget.request['id'] as int);
      if (mounted) setState(() {
        _properties = List<Map<String, dynamic>>.from(
          (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _recs = List<Map<String, dynamic>>.from(
          (recs['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        // Auto-select the only property (or first one if just one exists)
        if (_properties.length == 1) {
          _selectedPropertyId = _properties.first['id'] as int;
        }
        _loading = false;
      });
    } catch (e) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    if (_selectedPropertyId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('⚠️ Property doorasho — property card-ka taabso'),
        backgroundColor: Color(0xFFEF4444),
        duration: Duration(seconds: 3),
      ));
      return;
    }
    setState(() => _submitting = true);
    try {
      final price = double.tryParse(_priceCtl.text.trim());
      await AgentRepository.instance.recommendProperty(
        widget.request['id'] as int, _selectedPropertyId!, _msgCtl.text.trim(), offeredPrice: price);
      widget.onDone();
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: VC.red));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final bg  = widget.isDark ? VC.navy      : VC.lightBg;
    final txt = widget.isDark ? VC.text      : const Color(0xFF1A2340);

    if (_loading) return const Center(child: CircularProgressIndicator(color: _kTeal));

    return ListView(padding: const EdgeInsets.all(16), children: [
      // Existing recommendations
      if (_recs.isNotEmpty) ...[
        Text('Sent Recommendations', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: txt)),
        const SizedBox(height: 8),
        ..._recs.map((r) => _RecSentCard(rec: r, isDark: widget.isDark, txt: txt)),
        const Divider(height: 24),
      ],

      Text('Recommend a Property', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: txt)),
      const SizedBox(height: 10),

      if (_properties.isEmpty)
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
          child: const Text('No available properties. List a property first.',
            style: TextStyle(color: _kOrange), textAlign: TextAlign.center),
        )
      else ...[
        // Property picker
        ..._properties.map((p) {
          final id  = p['id'] as int;
          final sel = _selectedPropertyId == id;
          return GestureDetector(
            onTap: () => setState(() {
              _selectedPropertyId = sel ? null : id;
            }),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: sel ? _kTeal.withValues(alpha: 0.1) : bg,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: sel ? _kTeal : (widget.isDark ? VC.border : VC.lightBorder), width: sel ? 2 : 1),
              ),
              child: Row(children: [
                if (p['thumbnail'] != null)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.network(p['thumbnail'] as String, width: 52, height: 52, fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(width: 52, height: 52, color: _kTeal.withValues(alpha: 0.1),
                        child: const Icon(Icons.home_rounded, color: _kTeal))),
                  )
                else
                  Container(width: 52, height: 52, decoration: BoxDecoration(color: _kTeal.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                    child: const Icon(Icons.home_rounded, color: _kTeal)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(p['title'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: txt)),
                  Text('\$${p['monthly_rent']}/mo · ${p['district_name'] ?? ''}',
                    style: TextStyle(color: VC.textSec, fontSize: 11)),
                ])),
                if (sel) const Icon(Icons.check_circle_rounded, color: _kTeal),
              ]),
            ),
          );
        }),
        const SizedBox(height: 12),
        // Offered price (optional)
        const SizedBox(height: 12),
        TextFormField(
          controller: _priceCtl,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            hintText: 'Offer price (optional, e.g. 450)',
            prefixIcon: const Icon(Icons.attach_money_rounded, color: _kTeal),
            suffixText: '/mo',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _msgCtl,
          maxLines: 3,
          decoration: InputDecoration(
            hintText: 'Add a message to customer (optional)…',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
        const SizedBox(height: 16),
        ElevatedButton(
          onPressed: _submitting ? null : _submit,
          style: ElevatedButton.styleFrom(
            backgroundColor: _kTeal, foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          child: _submitting
              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Send Recommendation', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
        ),
      ],
    ]);
  }
}

class _RecSentCard extends StatelessWidget {
  final Map<String, dynamic> rec;
  final bool isDark;
  final Color txt;
  const _RecSentCard({required this.rec, required this.isDark, required this.txt});

  Color get _col {
    switch (rec['status']) {
      case 'accepted': return const Color(0xFF10B981);
      case 'rejected': return const Color(0xFFEF4444);
      default:         return _kOrange;
    }
  }

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: _col.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: _col.withValues(alpha: 0.25)),
    ),
    child: Row(children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(rec['title'] ?? 'Property', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: txt)),
        if (rec['message'] != null && (rec['message'] as String).isNotEmpty)
          Text('"${rec['message']}"', style: TextStyle(color: VC.textSec, fontSize: 11, fontStyle: FontStyle.italic)),
      ])),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: _col.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(99)),
        child: Text((rec['status'] as String).toUpperCase(), style: TextStyle(color: _col, fontSize: 10, fontWeight: FontWeight.w800)),
      ),
    ]),
  );
}

// ─── Viewing Tab ──────────────────────────────────────────────────────────────

class _ViewingTab extends StatefulWidget {
  final Map<String, dynamic> request;
  final bool isDark;
  final VoidCallback onDone;
  const _ViewingTab({required this.request, required this.isDark, required this.onDone});
  @override
  State<_ViewingTab> createState() => _ViewingTabState();
}

class _ViewingTabState extends State<_ViewingTab> {
  List<Map<String, dynamic>> _properties = [];
  int?      _propertyId;
  DateTime? _proposedAt;
  final _notesCtl = TextEditingController();
  bool _loading    = true;
  bool _submitting = false;

  @override
  void initState() { super.initState(); _loadProperties(); }
  @override
  void dispose() { _notesCtl.dispose(); super.dispose(); }

  Future<void> _loadProperties() async {
    try {
      final res = await AgentRepository.instance.properties(status: 'active');
      if (mounted) setState(() {
        _properties = List<Map<String, dynamic>>.from(
          (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _loading = false;
      });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _pickDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 60)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(context: context, initialTime: const TimeOfDay(hour: 10, minute: 0));
    if (time == null || !mounted) return;
    setState(() => _proposedAt = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _submit() async {
    if (_propertyId == null) { _snack('Select a property'); return; }
    if (_proposedAt == null) { _snack('Pick a date and time'); return; }
    setState(() => _submitting = true);
    try {
      await AgentRepository.instance.scheduleViewing(widget.request['id'] as int, {
        'property_id': _propertyId,
        'proposed_at': _proposedAt!.toIso8601String(),
        if (_notesCtl.text.trim().isNotEmpty) 'notes': _notesCtl.text.trim(),
      });
      widget.onDone();
    } catch (e) {
      if (mounted) { setState(() => _submitting = false); _snack('Error: $e', error: true); }
    }
  }

  void _snack(String msg, {bool error = false}) =>
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), backgroundColor: error ? VC.red : _kTeal));

  @override
  Widget build(BuildContext context) {
    final bg  = widget.isDark ? VC.navy  : VC.lightBg;
    final txt = widget.isDark ? VC.text  : const Color(0xFF1A2340);

    if (_loading) return const Center(child: CircularProgressIndicator(color: _kTeal));

    return ListView(padding: const EdgeInsets.all(16), children: [
      Text('Schedule a Viewing', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: txt)),
      const SizedBox(height: 14),

      // Property picker
      Text('Property', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: VC.textSec)),
      const SizedBox(height: 8),
      if (_properties.isEmpty)
        const Text('No available properties', style: TextStyle(color: _kOrange))
      else
        DropdownButtonFormField<int>(
          value: _propertyId,
          decoration: InputDecoration(hintText: 'Select property', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
          items: _properties.map((p) => DropdownMenuItem<int>(value: p['id'] as int, child: Text(p['title'] ?? '', overflow: TextOverflow.ellipsis))).toList(),
          onChanged: (v) => setState(() => _propertyId = v),
        ),
      const SizedBox(height: 16),

      // Date/Time picker
      Text('Date & Time', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: VC.textSec)),
      const SizedBox(height: 8),
      GestureDetector(
        onTap: _pickDateTime,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: bg, borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _proposedAt != null ? _kTeal : (widget.isDark ? VC.border : VC.lightBorder),
              width: _proposedAt != null ? 1.5 : 1),
          ),
          child: Row(children: [
            Icon(Icons.calendar_today_rounded, color: _proposedAt != null ? _kTeal : VC.textSec, size: 18),
            const SizedBox(width: 10),
            Text(
              _proposedAt != null
                  ? '${_proposedAt!.day}/${_proposedAt!.month}/${_proposedAt!.year}  ${_proposedAt!.hour.toString().padLeft(2, '0')}:${_proposedAt!.minute.toString().padLeft(2, '0')}'
                  : 'Pick date and time',
              style: TextStyle(color: _proposedAt != null ? txt : VC.textSec,
                fontWeight: _proposedAt != null ? FontWeight.w700 : FontWeight.normal),
            ),
          ]),
        ),
      ),
      const SizedBox(height: 16),

      Text('Notes (optional)', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: VC.textSec)),
      const SizedBox(height: 8),
      TextFormField(
        controller: _notesCtl,
        maxLines: 3,
        decoration: InputDecoration(
          hintText: 'Any special instructions for the customer…',
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      const SizedBox(height: 20),

      ElevatedButton.icon(
        onPressed: _submitting ? null : _submit,
        icon: const Icon(Icons.calendar_today_rounded, size: 16),
        label: _submitting
            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
            : const Text('Schedule Viewing', style: TextStyle(fontWeight: FontWeight.w800)),
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF6366F1), foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    ]);
  }
}

// ─── Chat Tab (Agent) ─────────────────────────────────────────────────────────

class _AgentChatTab extends StatefulWidget {
  final Map<String, dynamic> request;
  final bool isDark;
  const _AgentChatTab({required this.request, required this.isDark});
  @override
  State<_AgentChatTab> createState() => _AgentChatTabState();
}

class _AgentChatTabState extends State<_AgentChatTab> {
  List<Map<String, dynamic>> _messages = [];
  bool   _loading  = true;
  bool   _sending  = false;
  final  _msgCtl   = TextEditingController();
  final  _scroll   = ScrollController();

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose()   { _msgCtl.dispose(); _scroll.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final res = await AgentRepository.instance.getMessages(widget.request['id'] as int);
      if (mounted) setState(() {
        _messages = List<Map<String, dynamic>>.from(
          (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _loading  = false;
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
      await AgentRepository.instance.sendMessage(widget.request['id'] as int, text);
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: VC.red));
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
    final bg  = widget.isDark ? VC.navy      : VC.lightBg;
    final surf = widget.isDark ? VC.navyLight : VC.lightSurface;

    if (_loading) return const Center(child: CircularProgressIndicator(color: _kTeal));

    return Column(children: [
      Expanded(child: _messages.isEmpty
        ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.chat_bubble_outline_rounded, size: 48, color: _kTeal.withValues(alpha: 0.3)),
            const SizedBox(height: 12),
            const Text('No messages yet. Start the conversation!',
              style: TextStyle(color: Color(0xFF94A3B8)), textAlign: TextAlign.center),
          ]))
        : ListView.builder(
            controller: _scroll,
            padding: const EdgeInsets.all(16),
            itemCount: _messages.length,
            itemBuilder: (_, i) {
              final m      = _messages[i];
              final isAgent = (m['sender_role'] as String?) == 'agent';
              return _ChatBubble(msg: m, isMe: isAgent, isDark: widget.isDark);
            },
          )),
      // Input bar
      Container(
        padding: EdgeInsets.only(left: 12, right: 8, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 8),
        decoration: BoxDecoration(color: surf, border: Border(top: BorderSide(color: widget.isDark ? VC.border : VC.lightBorder))),
        child: Row(children: [
          Expanded(child: TextField(
            controller: _msgCtl,
            maxLines: null,
            decoration: InputDecoration(
              hintText: 'Type a message…',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
              filled: true, fillColor: bg,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            ),
            textInputAction: TextInputAction.send,
            onSubmitted: (_) => _send(),
          )),
          const SizedBox(width: 6),
          Material(
            color: _kTeal, borderRadius: BorderRadius.circular(24),
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

class _ChatBubble extends StatelessWidget {
  final Map<String, dynamic> msg;
  final bool isMe;
  final bool isDark;
  const _ChatBubble({required this.msg, required this.isMe, required this.isDark});

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(
      bottom: 8, left: isMe ? 48 : 0, right: isMe ? 0 : 48),
    child: Column(crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start, children: [
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isMe ? _kTeal : (isDark ? VC.navyLight : const Color(0xFFF1F5F9)),
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16), topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMe ? 16 : 4),
            bottomRight: Radius.circular(isMe ? 4 : 16),
          ),
        ),
        child: Text(msg['message'] as String? ?? '',
          style: TextStyle(color: isMe ? Colors.white : (isDark ? VC.text : const Color(0xFF1A2340)), fontSize: 13)),
      ),
      const SizedBox(height: 2),
      Text(_time(msg['created_at']), style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 10)),
    ]),
  );

  String _time(dynamic t) {
    if (t == null) return '';
    try {
      final dt = DateTime.parse(t.toString()).toLocal();
      return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) { return ''; }
  }
}
