import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/error_handler.dart';
import '../rewards/tier_widgets.dart';

// ── Providers ─────────────────────────────────────────────────────────────────

final gamificationProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final res = await ModuleApiService.create().getGamificationProfile();
  return (res['data'] as Map<String, dynamic>?) ?? {};
});

final _leaderboardPeriodProvider = StateProvider.autoDispose((ref) => 'weekly');

final _leaderboardProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final period = ref.watch(_leaderboardPeriodProvider);
  final res = await ModuleApiService.create().getLeaderboard(period: period);
  final data = res['data'] as Map<String, dynamic>? ?? {};
  return (data['board'] as List?)?.cast<Map<String, dynamic>>() ?? [];
});

// ── Main Screen ────────────────────────────────────────────────────────────────

class GamificationScreen extends ConsumerStatefulWidget {
  const GamificationScreen({super.key});
  @override
  ConsumerState<GamificationScreen> createState() => _GamificationScreenState();
}

class _GamificationScreenState extends ConsumerState<GamificationScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FF),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1B4B),
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text('Achievements',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: Colors.white)),
        bottom: TabBar(
          controller: _tabs,
          indicatorColor: Colors.amber,
          indicatorWeight: 3,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white54,
          labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          tabs: const [
            Tab(icon: Icon(Icons.military_tech_rounded, size: 20), text: 'Badges'),
            Tab(icon: Icon(Icons.local_fire_department_rounded, size: 20), text: 'Streak'),
            Tab(icon: Icon(Icons.leaderboard_rounded, size: 20), text: 'Board'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: const [_BadgesTab(), _StreakTab(), _LeaderboardTab()],
      ),
    );
  }
}

// ── Badges Tab ─────────────────────────────────────────────────────────────────

class _BadgesTab extends ConsumerWidget {
  const _BadgesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(gamificationProvider);

    return data.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => _ErrorState(message: AppErrorHandler.message(e),
          onRetry: () => ref.invalidate(gamificationProvider)),
      data: (d) {
        final allBadges = (d['all_badges'] as List?)?.cast<Map<String, dynamic>>() ?? [];
        final earned = d['badges_earned'] as int? ?? 0;
        final total  = d['badges_total'] as int? ?? 0;

        if (allBadges.isEmpty) {
          return const _EmptyState(icon: '🏅', title: 'No badges yet', sub: 'Place your first order to start earning!');
        }

        final Map<String, List<Map<String, dynamic>>> grouped = {};
        for (final b in allBadges) {
          grouped.putIfAbsent(b['category'] as String? ?? 'general', () => []).add(b);
        }

        final catLabels = {
          'orders': '📦 Orders', 'spending': '💰 Spending',
          'streak': '🔥 Streaks', 'social': '👥 Social',
          'tier': '🏆 Tiers', 'explorer': '🗺️ Explorer',
        };

        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            // Progress hero
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF4338CA), Color(0xFF7C3AED)],
                  begin: Alignment.topLeft, end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [BoxShadow(color: const Color(0xFF4338CA).withAlpha(60), blurRadius: 20, offset: const Offset(0, 8))],
              ),
              child: Row(children: [
                // Circular progress
                SizedBox(width: 72, height: 72,
                  child: Stack(alignment: Alignment.center, children: [
                    SizedBox(width: 72, height: 72,
                      child: CircularProgressIndicator(
                        value: total > 0 ? earned / total : 0,
                        strokeWidth: 7,
                        backgroundColor: Colors.white24,
                        valueColor: const AlwaysStoppedAnimation(Colors.amber),
                      ),
                    ),
                    Text('${total > 0 ? (earned / total * 100).round() : 0}%',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
                  ]),
                ),
                const SizedBox(width: 16),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('$earned / $total Badges',
                      style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  Text('${total - earned} more to unlock',
                      style: const TextStyle(color: Colors.white60, fontSize: 13)),
                  const SizedBox(height: 10),
                  Row(children: List.generate(math.min(earned, 5), (i) =>
                    const Padding(padding: EdgeInsets.only(right: 4),
                      child: Text('⭐', style: TextStyle(fontSize: 14))))),
                ])),
              ]),
            ),
            const SizedBox(height: 20),

            ...grouped.entries.map((entry) {
              final catLabel = catLabels[entry.key] ?? entry.key;
              final badges = entry.value;
              final earnedInCat = badges.where((b) => b['earned'] == true).length;
              return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Text(catLabel,
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1E1B4B))),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                    decoration: BoxDecoration(
                      color: earnedInCat == badges.length ? Colors.green.withAlpha(20) : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text('$earnedInCat/${badges.length}',
                        style: TextStyle(
                            fontSize: 12, fontWeight: FontWeight.w700,
                            color: earnedInCat == badges.length ? Colors.green : Colors.grey.shade500)),
                  ),
                ]),
                const SizedBox(height: 10),
                GridView.count(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: 4,
                  mainAxisSpacing: 8,
                  crossAxisSpacing: 8,
                  childAspectRatio: 0.85,
                  children: badges.map((b) => _BadgeTile(badge: b)).toList(),
                ),
                const SizedBox(height: 16),
              ]);
            }),
          ]),
        );
      },
    );
  }
}

class _BadgeTile extends StatelessWidget {
  final Map<String, dynamic> badge;
  const _BadgeTile({required this.badge});

  @override
  Widget build(BuildContext context) {
    final earned = badge['earned'] as bool? ?? false;
    final icon   = badge['icon'] as String? ?? '🏅';
    final name   = badge['name'] as String? ?? '';
    final pts    = badge['pts_reward'] as int? ?? 0;

    return GestureDetector(
      onTap: () => _showDetail(context),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        decoration: BoxDecoration(
          color: earned ? Colors.white : const Color(0xFFF1F5F9),
          border: Border.all(
            color: earned ? const Color(0xFF6366F1).withAlpha(60) : Colors.transparent,
            width: 1.5,
          ),
          borderRadius: BorderRadius.circular(16),
          boxShadow: earned ? [BoxShadow(color: const Color(0xFF6366F1).withAlpha(20), blurRadius: 12, offset: const Offset(0, 4))] : null,
        ),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Stack(alignment: Alignment.topRight, children: [
            Text(icon,
                style: TextStyle(
                    fontSize: earned ? 32 : 26,
                    color: earned ? null : Colors.transparent),
            ),
            if (!earned)
              Positioned.fill(child: Center(
                child: ColorFiltered(
                  colorFilter: const ColorFilter.matrix([
                    0.2126, 0.7152, 0.0722, 0, 0,
                    0.2126, 0.7152, 0.0722, 0, 0,
                    0.2126, 0.7152, 0.0722, 0, 0,
                    0, 0, 0, 0.4, 0,
                  ]),
                  child: Text(icon, style: const TextStyle(fontSize: 26)),
                ),
              )),
            if (!earned)
              Positioned(top: 0, right: 0,
                child: Container(width: 14, height: 14, decoration: BoxDecoration(
                    color: Colors.grey.shade400, shape: BoxShape.circle),
                  child: const Icon(Icons.lock_rounded, color: Colors.white, size: 9))),
          ]),
          const SizedBox(height: 6),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Text(name, textAlign: TextAlign.center, maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                    color: earned ? const Color(0xFF1E1B4B) : Colors.grey.shade400)),
          ),
          if (pts > 0 && earned) ...[
            const SizedBox(height: 3),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(color: Colors.amber.withAlpha(20), borderRadius: BorderRadius.circular(8)),
              child: Text('+$pts', style: const TextStyle(fontSize: 9, color: Colors.amber, fontWeight: FontWeight.w800)),
            ),
          ],
        ]),
      ),
    );
  }

  void _showDetail(BuildContext context) {
    final earned = badge['earned'] as bool? ?? false;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          Text(badge['icon'] ?? '🏅', style: const TextStyle(fontSize: 56)),
          const SizedBox(height: 12),
          Text(badge['name'] ?? '', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF1E1B4B))),
          const SizedBox(height: 8),
          Text(badge['description'] ?? '', textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14, color: Colors.grey.shade600)),
          const SizedBox(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            if ((badge['pts_reward'] as int? ?? 0) > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(color: Colors.amber.withAlpha(15),
                    border: Border.all(color: Colors.amber.withAlpha(60)), borderRadius: BorderRadius.circular(20)),
                child: Text('⭐ +${badge['pts_reward']} pts',
                    style: const TextStyle(color: Colors.amber, fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            const SizedBox(width: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                  color: earned ? Colors.green.withAlpha(15) : Colors.grey.withAlpha(15),
                  border: Border.all(color: earned ? Colors.green.withAlpha(60) : Colors.grey.withAlpha(60)),
                  borderRadius: BorderRadius.circular(20)),
              child: Text(earned ? '✓ Earned' : '🔒 Locked',
                  style: TextStyle(color: earned ? Colors.green : Colors.grey,
                      fontWeight: FontWeight.w700, fontSize: 14)),
            ),
          ]),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }
}

// ── Streak Tab ─────────────────────────────────────────────────────────────────

class _StreakTab extends ConsumerWidget {
  const _StreakTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(gamificationProvider);

    return data.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => _ErrorState(message: AppErrorHandler.message(e),
          onRetry: () => ref.invalidate(gamificationProvider)),
      data: (d) {
        final streak  = d['streak'] as Map<String, dynamic>? ?? {};
        final current = streak['current'] as int? ?? 0;
        final longest = streak['longest'] as int? ?? 0;
        final nextM   = [7, 30, 100].firstWhere((m) => m > current, orElse: () => 0);
        final prog    = nextM > 0 ? (current / nextM).clamp(0.0, 1.0) : 1.0;

        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          child: Column(children: [
            // Main flame hero
            _StreakHeroCard(current: current),
            const SizedBox(height: 16),

            // Stats row
            Row(children: [
              _StatCard(label: 'Current', value: '$current', unit: 'days',
                  icon: Icons.local_fire_department_rounded, color: Colors.orange),
              const SizedBox(width: 12),
              _StatCard(label: 'Best Ever', value: '$longest', unit: 'days',
                  icon: Icons.emoji_events_rounded, color: Colors.amber),
            ]),
            const SizedBox(height: 16),

            // Progress to next milestone
            if (nextM > 0)
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: Colors.orange.withAlpha(40)),
                  boxShadow: [BoxShadow(color: Colors.orange.withAlpha(12), blurRadius: 16, offset: const Offset(0, 4))],
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    const Text('🎯', style: TextStyle(fontSize: 16)),
                    const SizedBox(width: 8),
                    Text('Next: $nextM-day milestone',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1E1B4B))),
                    const Spacer(),
                    Text('$current / $nextM',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Colors.orange.shade700)),
                  ]),
                  const SizedBox(height: 12),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: LinearProgressIndicator(
                      value: prog, minHeight: 10,
                      backgroundColor: Colors.orange.withAlpha(20),
                      valueColor: AlwaysStoppedAnimation(Colors.orange.shade600),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text('${nextM - current} more orders to earn bonus pts',
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                ]),
              ),
            const SizedBox(height: 16),

            // Milestones
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                boxShadow: [BoxShadow(color: Colors.black.withAlpha(6), blurRadius: 16, offset: const Offset(0, 4))],
              ),
              child: Column(children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 18, 18, 10),
                  child: Row(children: [
                    const Text('🏆', style: TextStyle(fontSize: 18)),
                    const SizedBox(width: 8),
                    const Text('Streak Milestones',
                        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF1E1B4B))),
                  ]),
                ),
                ...[
                  [7,   200,  '🔥', 'One week streak',     Colors.orange],
                  [10,  100,  '📅', '10-day (every 10)',    Colors.blue],
                  [30,  1000, '🌟', 'One month streak',     Colors.purple],
                  [100, 5000, '🚀', '100-day legend',       Colors.red],
                ].map((m) {
                  final days  = m[0] as int;
                  final pts   = m[1] as int;
                  final emoji = m[2] as String;
                  final label = m[3] as String;
                  final color = m[4] as Color;
                  final done  = current >= days;
                  return Container(
                    margin: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                    decoration: BoxDecoration(
                      color: done ? color.withAlpha(8) : const Color(0xFFF8F9FF),
                      border: Border.all(color: done ? color.withAlpha(40) : Colors.transparent),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: ListTile(
                      dense: true,
                      leading: Container(width: 36, height: 36, decoration: BoxDecoration(
                          color: done ? color.withAlpha(20) : Colors.grey.withAlpha(15),
                          borderRadius: BorderRadius.circular(10)),
                        child: Center(child: Text(emoji, style: const TextStyle(fontSize: 18)))),
                      title: Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13,
                          color: done ? const Color(0xFF1E1B4B) : Colors.grey.shade400)),
                      subtitle: Text('$days days', style: TextStyle(fontSize: 11,
                          color: done ? color : Colors.grey.shade400)),
                      trailing: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: done ? color.withAlpha(15) : Colors.grey.withAlpha(10),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text('+$pts pts', style: TextStyle(
                            fontSize: 12, fontWeight: FontWeight.w800,
                            color: done ? color : Colors.grey.shade400)),
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 8),
              ]),
            ),
          ]),
        );
      },
    );
  }
}

class _StreakHeroCard extends StatelessWidget {
  final int current;
  const _StreakHeroCard({required this.current});

  @override
  Widget build(BuildContext context) {
    final hasStreak = current > 0;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: hasStreak
              ? [const Color(0xFFEA580C), const Color(0xFFF97316)]
              : [const Color(0xFF64748B), const Color(0xFF94A3B8)],
          begin: Alignment.topLeft, end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: (hasStreak ? Colors.orange : Colors.grey).withAlpha(60),
            blurRadius: 24, offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(children: [
        // Flame animation simulation with stack
        Stack(alignment: Alignment.center, children: [
          if (hasStreak) ...[
            Positioned(child: Text('🔥', style: TextStyle(fontSize: 80, shadows: [
              Shadow(color: Colors.orange.withAlpha(120), blurRadius: 20),
            ]))),
          ] else
            const Text('💤', style: TextStyle(fontSize: 70)),
        ]),
        const SizedBox(height: 8),
        Text('$current',
            style: const TextStyle(
                color: Colors.white, fontSize: 72, fontWeight: FontWeight.w900,
                height: 1, letterSpacing: -2)),
        const Text('Day Streak',
            style: TextStyle(color: Colors.white70, fontSize: 16, fontWeight: FontWeight.w600)),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          decoration: BoxDecoration(
              color: Colors.white.withAlpha(20),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withAlpha(40))),
          child: Text(
            hasStreak ? '🔥 Keep ordering to maintain your streak!' : 'Place an order to start your streak!',
            style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
          ),
        ),
      ]),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label, value, unit;
  final IconData icon;
  final Color color;
  const _StatCard({required this.label, required this.value, required this.unit,
      required this.icon, required this.color});

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: color.withAlpha(30)),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: color.withAlpha(12), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Row(children: [
        Container(width: 40, height: 40, decoration: BoxDecoration(
            color: color.withAlpha(15), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: color, size: 22)),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: color)),
          Text('$label · $unit', style: TextStyle(fontSize: 11, color: Colors.grey.shade500, fontWeight: FontWeight.w600)),
        ])),
      ]),
    ),
  );
}

// ── Leaderboard Tab ────────────────────────────────────────────────────────────

class _LeaderboardTab extends ConsumerWidget {
  const _LeaderboardTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final period = ref.watch(_leaderboardPeriodProvider);
    final board  = ref.watch(_leaderboardProvider);

    return Column(children: [
      // Period selector
      Padding(
        padding: const EdgeInsets.all(16),
        child: Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: ['weekly', 'monthly', 'alltime'].map((p) {
              final labels = {'weekly': '📅 This Week', 'monthly': '🗓 Month', 'alltime': '🌟 All Time'};
              final sel = p == period;
              return Expanded(
                child: GestureDetector(
                  onTap: () => ref.read(_leaderboardPeriodProvider.notifier).state = p,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.all(2),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: BoxDecoration(
                      color: sel ? const Color(0xFF4338CA) : Colors.transparent,
                      borderRadius: BorderRadius.circular(10),
                      boxShadow: sel ? [BoxShadow(color: const Color(0xFF4338CA).withAlpha(40), blurRadius: 8)] : null,
                    ),
                    child: Text(labels[p] ?? p, textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
                            color: sel ? Colors.white : Colors.grey.shade500)),
                  ),
                ),
              );
            }).toList(),
          ),
        ),
      ),

      Expanded(
        child: board.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => _ErrorState(message: AppErrorHandler.message(e),
              onRetry: () => ref.invalidate(_leaderboardProvider)),
          data: (rows) => rows.isEmpty
              ? const _EmptyState(icon: '🏅', title: 'No data yet', sub: 'Be the first to earn points!')
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
                  itemCount: rows.length,
                  itemBuilder: (ctx, i) {
                    final row   = rows[i];
                    final rank  = row['rank'] as int? ?? i + 1;
                    final isMe  = row['is_me'] as bool? ?? false;
                    final tier  = row['tier'] as String? ?? 'bronze';
                    final pts   = row['pts_earned'] as int? ?? 0;
                    final name  = row['name'] as String? ?? '***';

                    final medals = {1: '🥇', 2: '🥈', 3: '🥉'};

                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      decoration: BoxDecoration(
                        color: isMe
                            ? const Color(0xFFEEF2FF)
                            : rank <= 3 ? Colors.amber.withAlpha(8) : Colors.white,
                        border: Border.all(
                          color: isMe
                              ? const Color(0xFF6366F1).withAlpha(80)
                              : rank <= 3 ? Colors.amber.withAlpha(50) : const Color(0xFFE2E8F0),
                          width: isMe ? 2 : 1,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: isMe
                            ? [BoxShadow(color: const Color(0xFF6366F1).withAlpha(15), blurRadius: 12, offset: const Offset(0, 4))]
                            : null,
                      ),
                      child: ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                        leading: medals.containsKey(rank)
                            ? Text(medals[rank]!, style: const TextStyle(fontSize: 28))
                            : Container(width: 36, height: 36, decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9), shape: BoxShape.circle),
                              child: Center(child: Text('$rank',
                                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14,
                                      color: Color(0xFF64748B))))),
                        title: Row(children: [
                          Text(isMe ? 'You' : name,
                              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15,
                                  color: isMe ? const Color(0xFF4338CA) : const Color(0xFF1E1B4B))),
                          const SizedBox(width: 8),
                          TierBadge(tier: tier, size: 11),
                        ]),
                        subtitle: Text('${_fmt(pts)} points earned',
                            style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                        trailing: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: rank <= 3 ? Colors.amber.withAlpha(15) : const Color(0xFFEEF2FF),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text('⭐ ${_fmt(pts)}',
                              style: TextStyle(
                                  fontWeight: FontWeight.w900, fontSize: 13,
                                  color: rank <= 3 ? Colors.amber.shade700 : const Color(0xFF4338CA))),
                        ),
                      ),
                    );
                  },
                ),
        ),
      ),
    ]);
  }

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}

// ── Shared Widgets ─────────────────────────────────────────────────────────────

class _EmptyState extends StatelessWidget {
  final String icon, title, sub;
  const _EmptyState({required this.icon, required this.title, required this.sub});

  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      Text(icon, style: const TextStyle(fontSize: 56)),
      const SizedBox(height: 12),
      Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF1E1B4B))),
      const SizedBox(height: 6),
      Text(sub, style: TextStyle(fontSize: 13, color: Colors.grey.shade500)),
    ]),
  );
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Text('⚠️', style: TextStyle(fontSize: 40)),
      const SizedBox(height: 12),
      Text(message, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
      const SizedBox(height: 16),
      TextButton.icon(
        onPressed: onRetry,
        icon: const Icon(Icons.refresh_rounded),
        label: const Text('Retry'),
        style: TextButton.styleFrom(foregroundColor: AppColors.primary),
      ),
    ]),
  );
}

// ── Streak Flame (wallet card inline) ─────────────────────────────────────────

class StreakFlame extends ConsumerWidget {
  const StreakFlame({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(gamificationProvider).valueOrNull;
    final streak = (data?['streak'] as Map<String, dynamic>?)?['current'] as int? ?? 0;
    if (streak == 0) return const SizedBox.shrink();

    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GamificationScreen())),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
            color: Colors.orange.withAlpha(40),
            border: Border.all(color: Colors.orange.withAlpha(100)),
            borderRadius: BorderRadius.circular(20)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Text('🔥', style: TextStyle(fontSize: 12)),
          const SizedBox(width: 3),
          Text('$streak', style: const TextStyle(color: Colors.orange, fontSize: 12, fontWeight: FontWeight.bold)),
        ]),
      ),
    );
  }
}
