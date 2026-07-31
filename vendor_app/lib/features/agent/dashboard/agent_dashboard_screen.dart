import 'package:flutter/material.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/services/auth_service.dart';
import '../../../core/theme/vc.dart';

const _kTeal   = Color(0xFF0EA5E9);
const _kGold   = Color(0xFFF59E0B);
const _kEmerald= Color(0xFF10B981);

class AgentDashboardScreen extends StatefulWidget {
  const AgentDashboardScreen({super.key});
  @override
  State<AgentDashboardScreen> createState() => _AgentDashboardScreenState();
}

class _AgentDashboardScreenState extends State<AgentDashboardScreen> {
  Map<String, dynamic>? _data;
  String? _agentName;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final user = await AuthService.instance.getUser();
      _agentName = user?['name'] ?? 'Agent';
      final res = await AgentRepository.instance.dashboard();
      if (mounted) setState(() { _data = res['data']; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg   = isDark ? VC.navy      : VC.lightBg;
    final card = isDark ? VC.navyCard  : VC.lightSurface;
    final txt  = isDark ? VC.text      : const Color(0xFF1A2340);
    final sec  = isDark ? VC.textSec   : const Color(0xFF5A6B82);

    return Scaffold(
      backgroundColor: bg,
      body: RefreshIndicator(
        color: _kTeal,
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            // ── Header ────────────────────────────────────────────────────
            SliverAppBar(
              expandedHeight: 160,
              pinned: true,
              backgroundColor: isDark ? VC.navyLight : VC.lightSurface,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                      colors: [
                        isDark ? const Color(0xFF0B2540) : const Color(0xFF0369A1),
                        isDark ? const Color(0xFF0C2035) : const Color(0xFF0EA5E9),
                      ],
                    ),
                  ),
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          Container(
                            width: 44, height: 44,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.real_estate_agent_rounded, color: Colors.white, size: 26),
                          ),
                          const SizedBox(width: 12),
                          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('Welcome back,', style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 13)),
                            Text(_agentName ?? 'Agent', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
                          ]),
                          const Spacer(),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: const Row(children: [
                              Icon(Icons.verified_rounded, color: Colors.white, size: 14),
                              SizedBox(width: 4),
                              Text('Agent', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                            ]),
                          ),
                        ]),
                        const SizedBox(height: 20),
                        Text(
                          _loading ? '—' : '\$${(_data?['stats']?['wallet_balance'] ?? 0.0).toStringAsFixed(2)}',
                          style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: -1),
                        ),
                        const Text('Wallet Balance', style: TextStyle(color: Colors.white70, fontSize: 13)),
                      ]),
                    ),
                  ),
                ),
              ),
            ),

            if (_loading)
              const SliverFillRemaining(child: Center(child: CircularProgressIndicator(color: _kTeal)))
            else ...[
              // ── KPI Grid ──────────────────────────────────────────────
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                sliver: SliverGrid(
                  delegate: SliverChildListDelegate([
                    _KpiCard(label: 'Total Listings',  value: '${_data?['stats']?['total_listings'] ?? 0}',  icon: Icons.apartment_rounded, color: _kTeal,    card: card, txt: txt, sec: sec),
                    _KpiCard(label: 'Active',          value: '${_data?['stats']?['active_listings'] ?? 0}', icon: Icons.check_circle_rounded, color: _kEmerald, card: card, txt: txt, sec: sec),
                    _KpiCard(label: 'Rented Out',      value: '${_data?['stats']?['rented'] ?? 0}',          icon: Icons.key_rounded,         color: _kGold,    card: card, txt: txt, sec: sec),
                    _KpiCard(label: 'This Month',      value: '\$${(_data?['stats']?['month_earned'] ?? 0.0).toStringAsFixed(0)}', icon: Icons.trending_up_rounded, color: VC.purple, card: card, txt: txt, sec: sec),
                  ]),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 1.55,
                  ),
                ),
              ),

              // ── Total earned banner ────────────────────────────────────
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(colors: [_kGold.withValues(alpha: 0.15), _kGold.withValues(alpha: 0.05)]),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: _kGold.withValues(alpha: 0.3)),
                    ),
                    child: Row(children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(color: _kGold.withValues(alpha: 0.2), shape: BoxShape.circle),
                        child: const Icon(Icons.workspace_premium_rounded, color: _kGold, size: 22),
                      ),
                      const SizedBox(width: 14),
                      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('Total Commission Earned', style: TextStyle(color: sec, fontSize: 12)),
                        Text('\$${(_data?['stats']?['total_earned'] ?? 0.0).toStringAsFixed(2)}',
                          style: TextStyle(color: txt, fontSize: 22, fontWeight: FontWeight.w900)),
                      ]),
                    ]),
                  ),
                ),
              ),

              // ── Recent Listings ────────────────────────────────────────
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
                  child: Text('Recent Listings', style: TextStyle(color: txt, fontSize: 16, fontWeight: FontWeight.w800)),
                ),
              ),
              SliverList(
                delegate: SliverChildBuilderDelegate(
                  (ctx, i) {
                    final list = List<Map>.from(_data?['recent_properties'] ?? []);
                    if (i >= list.length) return null;
                    final p = list[i];
                    return _RecentPropertyTile(property: p, card: card, txt: txt, sec: sec);
                  },
                  childCount: (_data?['recent_properties'] as List?)?.length ?? 0,
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 24)),
            ],
          ],
        ),
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  final String label, value;
  final IconData icon;
  final Color color, card;
  final Color txt, sec;
  const _KpiCard({required this.label, required this.value, required this.icon,
    required this.color, required this.card, required this.txt, required this.sec});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.15)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: color, size: 18),
        ),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: TextStyle(color: txt, fontSize: 22, fontWeight: FontWeight.w900)),
          Text(label, style: TextStyle(color: sec, fontSize: 12)),
        ]),
      ]),
    );
  }
}

class _RecentPropertyTile extends StatelessWidget {
  final Map property;
  final Color card, txt, sec;
  const _RecentPropertyTile({required this.property, required this.card, required this.txt, required this.sec});

  @override
  Widget build(BuildContext context) {
    final isAvail  = property['is_available'] == true;
    final isBooked = property['is_booked'] == true;
    final status   = !isAvail ? 'Rented' : isBooked ? 'Booked' : 'Active';
    final sColor   = !isAvail ? VC.red : isBooked ? _kGold : _kEmerald;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.withValues(alpha: 0.08)),
      ),
      child: Row(children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: property['thumbnail'] != null
              ? Image.network(property['thumbnail'], width: 56, height: 56, fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => _imgPlaceholder())
              : _imgPlaceholder(),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(property['title'] ?? '', style: TextStyle(color: txt, fontWeight: FontWeight.w700, fontSize: 14), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 2),
          Text(property['district_name'] ?? '', style: TextStyle(color: sec, fontSize: 12)),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text('\$${(property['monthly_rent'] ?? 0).toStringAsFixed(0)}/mo',
            style: TextStyle(color: txt, fontWeight: FontWeight.w800, fontSize: 13)),
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: sColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
            child: Text(status, style: TextStyle(color: sColor, fontSize: 11, fontWeight: FontWeight.w700)),
          ),
        ]),
      ]),
    );
  }

  Widget _imgPlaceholder() => Container(
    width: 56, height: 56,
    decoration: BoxDecoration(color: _kTeal.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
    child: const Icon(Icons.apartment_rounded, color: _kTeal, size: 26),
  );
}
