import 'package:flutter/material.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/theme/vc.dart';

const _kTeal    = Color(0xFF0EA5E9);
const _kGold    = Color(0xFFF59E0B);
const _kEmerald = Color(0xFF10B981);

class AgentPropertiesScreen extends StatefulWidget {
  const AgentPropertiesScreen({super.key});
  @override
  State<AgentPropertiesScreen> createState() => AgentPropertiesScreenState();
}

class AgentPropertiesScreenState extends State<AgentPropertiesScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final _statuses = ['all', 'active', 'rented'];

  final _data   = <String, List>{};
  final _loading = <String, bool>{};
  final _pages   = <String, int>{};
  final _hasMore = <String, bool>{};

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    for (final s in _statuses) {
      _data[s] = [];
      _loading[s] = false;
      _pages[s] = 1;
      _hasMore[s] = true;
      _load(s);
    }
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load(String status, {bool refresh = false}) async {
    if (_loading[status] == true) return;
    if (!refresh && _hasMore[status] == false) return;
    if (refresh) { _pages[status] = 1; _hasMore[status] = true; }
    setState(() => _loading[status] = true);
    try {
      final res = await AgentRepository.instance.properties(
        status: status == 'all' ? null : status,
        page: _pages[status]!,
      );
      final items = List<Map>.from(res['data'] ?? []);
      final meta  = res['meta'] as Map? ?? {};
      final lastPage = meta['last_page'] ?? 1;
      if (mounted) setState(() {
        if (refresh || _pages[status] == 1) {
          _data[status] = items;
        } else {
          _data[status] = [..._data[status]!, ...items];
        }
        _hasMore[status] = _pages[status]! < lastPage;
        _pages[status] = _pages[status]! + 1;
        _loading[status] = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading[status] = false);
    }
  }

  Future<void> _delete(Map property, String status) async {
    final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Delete Property'),
      content: Text('Delete "${property['title']}"? This cannot be undone.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
        TextButton(
          style: TextButton.styleFrom(foregroundColor: VC.red),
          onPressed: () => Navigator.pop(context, true),
          child: const Text('Delete'),
        ),
      ],
    ));
    if (ok != true) return;
    try {
      await AgentRepository.instance.deleteProperty(property['id']);
      _load(status, refresh: true);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Property deleted'), backgroundColor: VC.red));
    } catch (_) {}
  }

  Future<void> _markRented(Map property, String status) async {
    try {
      await AgentRepository.instance.markRented(property['id']);
      _load(status, refresh: true);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Marked as rented'), backgroundColor: _kGold));
    } catch (_) {}
  }

  Future<void> _markAvailable(Map property, String status) async {
    try {
      await AgentRepository.instance.markAvailable(property['id']);
      _load(status, refresh: true);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Marked as available'), backgroundColor: _kEmerald));
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg   = isDark ? VC.navy     : VC.lightBg;
    final surf = isDark ? VC.navyLight : VC.lightSurface;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: surf,
        title: const Text('My Properties'),
        bottom: TabBar(
          controller: _tabs,
          labelColor: _kTeal,
          unselectedLabelColor: isDark ? VC.textMuted : const Color(0xFF94A3B8),
          indicatorColor: _kTeal,
          tabs: const [Tab(text: 'All'), Tab(text: 'Active'), Tab(text: 'Rented')],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: _statuses.map((s) => _PropertyList(
          items: _data[s] ?? [],
          loading: _loading[s] ?? false,
          hasMore: _hasMore[s] ?? false,
          onRefresh: () => _load(s, refresh: true),
          onLoadMore: () => _load(s),
          onDelete: (p) => _delete(p, s),
          onMarkRented: (p) => _markRented(p, s),
          onMarkAvailable: (p) => _markAvailable(p, s),
        )).toList(),
      ),
    );
  }
}

class _PropertyList extends StatelessWidget {
  final List items;
  final bool loading, hasMore;
  final VoidCallback onRefresh, onLoadMore;
  final Function(Map) onDelete, onMarkRented, onMarkAvailable;
  const _PropertyList({required this.items, required this.loading, required this.hasMore,
    required this.onRefresh, required this.onLoadMore, required this.onDelete,
    required this.onMarkRented, required this.onMarkAvailable});

  @override
  Widget build(BuildContext context) {
    if (loading && items.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: _kTeal));
    }
    if (items.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.apartment_rounded, size: 64, color: Colors.grey.withValues(alpha: 0.3)),
        const SizedBox(height: 16),
        const Text('No properties yet', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        const SizedBox(height: 8),
        const Text('Tap + to list a new property', style: TextStyle(color: Colors.grey)),
      ]));
    }

    return RefreshIndicator(
      color: _kTeal,
      onRefresh: () async => onRefresh(),
      child: NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.pixels >= n.metrics.maxScrollExtent - 200 && hasMore && !loading) {
            onLoadMore();
          }
          return false;
        },
        child: ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: items.length + (loading ? 1 : 0),
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (ctx, i) {
            if (i == items.length) return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(color: _kTeal)));
            return _PropertyCard(
              property: items[i] as Map,
              onDelete: () => onDelete(items[i] as Map),
              onMarkRented: () => onMarkRented(items[i] as Map),
              onMarkAvailable: () => onMarkAvailable(items[i] as Map),
            );
          },
        ),
      ),
    );
  }
}

class _PropertyCard extends StatelessWidget {
  final Map property;
  final VoidCallback onDelete, onMarkRented, onMarkAvailable;
  const _PropertyCard({required this.property, required this.onDelete, required this.onMarkRented, required this.onMarkAvailable});

  @override
  Widget build(BuildContext context) {
    final isDark   = Theme.of(context).brightness == Brightness.dark;
    final card     = isDark ? VC.navyCard : VC.lightSurface;
    final txt      = isDark ? VC.text     : const Color(0xFF1A2340);
    final sec      = isDark ? VC.textSec  : const Color(0xFF5A6B82);
    final bord     = isDark ? VC.border   : VC.lightBorder;

    final isAvail  = property['is_available'] == true;
    final isBooked = property['is_booked'] == true;
    final status   = !isAvail ? 'Rented' : isBooked ? 'Booked' : 'Active';
    final sColor   = !isAvail ? VC.red : isBooked ? _kGold : _kEmerald;

    final type = (property['type'] ?? '').toString();
    final typeIcon = switch (type) {
      'villa'  => Icons.villa_rounded,
      'office' => Icons.business_rounded,
      'shop'   => Icons.store_rounded,
      'room'   => Icons.bedroom_parent_rounded,
      _        => Icons.apartment_rounded,
    };

    return Container(
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: bord),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: isDark ? 0.1 : 0.04), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Column(children: [
        // Image header
        ClipRRect(
          borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
          child: SizedBox(
            height: 160,
            width: double.infinity,
            child: property['thumbnail'] != null
                ? Image.network(property['thumbnail'], fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _ImgPlaceholder(typeIcon: typeIcon))
                : _ImgPlaceholder(typeIcon: typeIcon),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(property['title'] ?? '', style: TextStyle(color: txt, fontWeight: FontWeight.w800, fontSize: 15), maxLines: 1, overflow: TextOverflow.ellipsis)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: sColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(status, style: TextStyle(color: sColor, fontSize: 11, fontWeight: FontWeight.w800)),
              ),
            ]),
            const SizedBox(height: 6),
            Row(children: [
              Icon(Icons.location_on_rounded, size: 14, color: sec),
              const SizedBox(width: 3),
              Text(property['district_name'] ?? '', style: TextStyle(color: sec, fontSize: 12)),
              const SizedBox(width: 12),
              Icon(typeIcon, size: 14, color: sec),
              const SizedBox(width: 3),
              Text(_capitalize(type), style: TextStyle(color: sec, fontSize: 12)),
            ]),
            const SizedBox(height: 8),
            Row(children: [
              Icon(Icons.bed_rounded, size: 14, color: _kTeal),
              const SizedBox(width: 3),
              Text('${property['bedrooms'] ?? 0}bd', style: TextStyle(color: txt, fontSize: 12)),
              const SizedBox(width: 8),
              Icon(Icons.bathtub_rounded, size: 14, color: _kTeal),
              const SizedBox(width: 3),
              Text('${property['bathrooms'] ?? 0}ba', style: TextStyle(color: txt, fontSize: 12)),
              const Spacer(),
              Text('\$${(property['monthly_rent'] ?? 0).toStringAsFixed(0)}/mo',
                style: TextStyle(color: _kTeal, fontWeight: FontWeight.w900, fontSize: 16)),
            ]),
            const SizedBox(height: 12),
            // Action buttons
            Row(children: [
              if (isAvail && !isBooked) ...[
                Expanded(child: _ActionBtn(
                  label: 'Mark Rented', icon: Icons.key_rounded, color: _kGold,
                  onTap: () => _confirmMarkRented(context),
                )),
                const SizedBox(width: 8),
              ],
              if (!isAvail) ...[
                Expanded(child: _ActionBtn(
                  label: 'Re-list', icon: Icons.refresh_rounded, color: _kEmerald,
                  onTap: onMarkAvailable,
                )),
                const SizedBox(width: 8),
              ],
              _ActionBtn(
                label: 'Delete', icon: Icons.delete_outline_rounded, color: VC.red,
                onTap: onDelete, filled: false,
              ),
            ]),
          ]),
        ),
      ]),
    );
  }

  Future<void> _confirmMarkRented(BuildContext context) async {
    final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Mark as Rented?'),
      content: const Text('This will hide the property from customer listings.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
        ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: _kGold, foregroundColor: Colors.white),
          onPressed: () => Navigator.pop(context, true),
          child: const Text('Mark Rented'),
        ),
      ],
    ));
    if (ok == true) onMarkRented();
  }

  String _capitalize(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);
}

class _ActionBtn extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
  final bool filled;
  const _ActionBtn({required this.label, required this.icon, required this.color, required this.onTap, this.filled = true});

  @override
  Widget build(BuildContext context) {
    if (filled) {
      return ElevatedButton.icon(
        onPressed: onTap,
        icon: Icon(icon, size: 14),
        label: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 10),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
    }
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, size: 14, color: color),
      label: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: color)),
      style: OutlinedButton.styleFrom(
        side: BorderSide(color: color.withValues(alpha: 0.5)),
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}

class _ImgPlaceholder extends StatelessWidget {
  final IconData typeIcon;
  const _ImgPlaceholder({required this.typeIcon});
  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      color: isDark ? VC.navyLight : const Color(0xFFE0F2FE),
      child: Center(child: Icon(typeIcon, size: 48, color: _kTeal.withValues(alpha: 0.4))),
    );
  }
}
