import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/theme_x.dart';
import '../../core/utils/error_handler.dart';
import '../rewards/tier_widgets.dart';

// ── Providers ─────────────────────────────────────────────────────────────────

final _gamificationProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
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

// ── Main screen (tabs: Badges | Streaks | Leaderboard) ────────────────────────

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
      appBar: AppBar(
        title: const Text('Achievements'),
        centerTitle: true,
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(icon: Icon(Icons.military_tech_rounded), text: 'Badges'),
            Tab(icon: Icon(Icons.local_fire_department_rounded), text: 'Streak'),
            Tab(icon: Icon(Icons.leaderboard_rounded), text: 'Leaderboard'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: const [
          _BadgesTab(),
          _StreakTab(),
          _LeaderboardTab(),
        ],
      ),
    );
  }
}

// ── Badges tab ────────────────────────────────────────────────────────────────

class _BadgesTab extends ConsumerWidget {
  const _BadgesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_gamificationProvider);

    return data.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text(ErrorHandler.message(e))),
      data: (d) {
        final allBadges = (d['all_badges'] as List?)?.cast<Map<String, dynamic>>() ?? [];
        final earned = d['badges_earned'] as int? ?? 0;
        final total  = d['badges_total'] as int? ?? 0;

        // Group by category
        final Map<String, List<Map<String, dynamic>>> grouped = {};
        for (final b in allBadges) {
          final cat = b['category'] as String? ?? 'general';
          grouped.putIfAbsent(cat, () => []).add(b);
        }

        final catLabels = {
          'orders': '📦 Orders',
          'spending': '💰 Spending',
          'streak': '🔥 Streaks',
          'social': '👥 Social',
          'tier': '🏆 Tiers',
          'explorer': '🗺️ Explorer',
        };

        return SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Progress summary
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [AppColors.primary.withAlpha(200), AppColors.primary.withAlpha(120)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    const Text('🏅', style: TextStyle(fontSize: 40)),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('$earned / $total Badges',
                              style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 6),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: LinearProgressIndicator(
                              value: total > 0 ? earned / total : 0,
                              minHeight: 8,
                              backgroundColor: Colors.white24,
                              valueColor: const AlwaysStoppedAnimation(Colors.white),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Categories
              ...grouped.entries.map((entry) {
                final catLabel = catLabels[entry.key] ?? entry.key;
                final badges = entry.value;
                final earnedInCat = badges.where((b) => b['earned'] == true).length;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(catLabel, style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
                        const Spacer(),
                        Text('$earnedInCat/${badges.length}',
                            style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText)),
                      ],
                    ),
                    const SizedBox(height: 8),
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
                  ],
                );
              }),
            ],
          ),
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
      onTap: () => _showDetail(context, badge),
      child: Container(
        decoration: BoxDecoration(
          color: earned ? AppColors.primary.withAlpha(15) : context.colors.cardBg,
          border: Border.all(
            color: earned ? AppColors.primary.withAlpha(80) : Colors.transparent,
          ),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            ColorFiltered(
              colorFilter: earned
                  ? const ColorFilter.mode(Colors.transparent, BlendMode.saturation)
                  : const ColorFilter.mode(Colors.grey, BlendMode.saturation),
              child: Text(icon, style: TextStyle(fontSize: earned ? 28 : 24)),
            ),
            const SizedBox(height: 4),
            Text(name, textAlign: TextAlign.center,
                maxLines: 2, overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    fontSize: 9, fontWeight: FontWeight.w600,
                    color: earned ? context.tt.bodySmall?.color : context.colors.mutedText)),
            if (pts > 0 && earned)
              Text('+$pts', style: const TextStyle(fontSize: 9, color: Colors.amber, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }

  void _showDetail(BuildContext context, Map<String, dynamic> b) {
    final earned = b['earned'] as bool? ?? false;
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Row(children: [
          Text(b['icon'] ?? '🏅', style: const TextStyle(fontSize: 28)),
          const SizedBox(width: 8),
          Expanded(child: Text(b['name'] ?? '', style: const TextStyle(fontSize: 16))),
        ]),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(b['description'] ?? ''),
            if ((b['pts_reward'] as int? ?? 0) > 0) ...[
              const SizedBox(height: 8),
              Text('Reward: +${b['pts_reward']} pts', style: const TextStyle(color: Colors.amber, fontWeight: FontWeight.bold)),
            ],
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: earned ? Colors.green.withAlpha(20) : Colors.grey.withAlpha(20),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                earned ? '✓ Earned${b['earned_at'] != null ? ' on ${b['earned_at'].toString().substring(0, 10)}' : ''}' : 'Not yet earned',
                style: TextStyle(fontSize: 12, color: earned ? Colors.green : Colors.grey, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close'))],
      ),
    );
  }
}

// ── Streak tab ────────────────────────────────────────────────────────────────

class _StreakTab extends ConsumerWidget {
  const _StreakTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_gamificationProvider);

    return data.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text(ErrorHandler.message(e))),
      data: (d) {
        final streak   = d['streak'] as Map<String, dynamic>? ?? {};
        final current  = streak['current'] as int? ?? 0;
        final longest  = streak['longest'] as int? ?? 0;

        final nextMilestone = [7, 30, 100].firstWhere((m) => m > current, orElse: () => 0);
        final progress = nextMilestone > 0
            ? (current / nextMilestone).clamp(0.0, 1.0)
            : 1.0;

        return SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Main flame card
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: current > 0
                        ? [const Color(0xFFFF6B35), const Color(0xFFFF8C42)]
                        : [Colors.grey.shade600, Colors.grey.shade400],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    Text(current > 0 ? '🔥' : '💤', style: const TextStyle(fontSize: 60)),
                    const SizedBox(height: 8),
                    Text('$current', style: const TextStyle(
                        color: Colors.white, fontSize: 64, fontWeight: FontWeight.w900, height: 1)),
                    Text('Day Streak', style: const TextStyle(color: Colors.white70, fontSize: 16)),
                    const SizedBox(height: 4),
                    Text(
                      current > 0 ? 'Keep it up! Order today to continue.' : 'Order today to start your streak!',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white60, fontSize: 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Stats row
              Row(
                children: [
                  _StreakStat('Current', '$current days', Icons.local_fire_department_rounded, Colors.orange),
                  const SizedBox(width: 8),
                  _StreakStat('Best Ever', '$longest days', Icons.emoji_events_rounded, Colors.amber),
                ],
              ),
              const SizedBox(height: 16),

              // Progress to next milestone
              if (nextMilestone > 0) ...[
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: context.colors.cardBg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text('Next milestone: $nextMilestone days 🔥',
                              style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
                          const Spacer(),
                          Text('$current / $nextMilestone', style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: LinearProgressIndicator(
                          value: progress,
                          minHeight: 8,
                          backgroundColor: Colors.orange.withAlpha(30),
                          valueColor: const AlwaysStoppedAnimation(Colors.orange),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${nextMilestone - current} more days to earn bonus points!',
                        style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Milestones table
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Streak Milestones', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 10),
                    ...[
                      [7,   200,  '🔥 One week streak'],
                      [10,  100,  '📅 10-day streak (then every 10 days)'],
                      [30,  1000, '🌟 One month streak'],
                      [100, 5000, '🚀 100-day legend'],
                    ].map((m) {
                      final days = m[0] as int;
                      final pts  = m[1] as int;
                      final label= m[2] as String;
                      final done = current >= days;
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 5),
                        child: Row(
                          children: [
                            Icon(done ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                                color: done ? Colors.green : context.colors.mutedText, size: 18),
                            const SizedBox(width: 8),
                            Expanded(child: Text(label,
                                style: TextStyle(
                                    fontSize: 13,
                                    color: done ? null : context.colors.mutedText,
                                    decoration: done ? TextDecoration.none : null))),
                            Text('+$pts pts', style: TextStyle(
                                fontSize: 12, fontWeight: FontWeight.bold,
                                color: done ? Colors.green : context.colors.mutedText)),
                          ],
                        ),
                      );
                    }),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _StreakStat extends StatelessWidget {
  final String label, value;
  final IconData icon;
  final Color color;
  const _StreakStat(this.label, this.value, this.icon, this.color);

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(12),
        border: Border.all(color: color.withAlpha(40)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color)),
              Text(label, style: TextStyle(fontSize: 11, color: color.withAlpha(160))),
            ],
          ),
        ],
      ),
    ),
  );
}

// ── Leaderboard tab ───────────────────────────────────────────────────────────

class _LeaderboardTab extends ConsumerWidget {
  const _LeaderboardTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final period = ref.watch(_leaderboardPeriodProvider);
    final board  = ref.watch(_leaderboardProvider);

    return Column(
      children: [
        // Period selector
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: ['weekly', 'monthly', 'alltime'].map((p) {
              final labels = {'weekly': 'This Week', 'monthly': 'This Month', 'alltime': 'All Time'};
              final sel = p == period;
              return Expanded(
                child: GestureDetector(
                  onTap: () => ref.read(_leaderboardPeriodProvider.notifier).state = p,
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    decoration: BoxDecoration(
                      color: sel ? AppColors.primary : context.colors.cardBg,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      labels[p] ?? p,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          fontSize: 12, fontWeight: FontWeight.bold,
                          color: sel ? Colors.white : context.colors.mutedText),
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ),

        // List
        Expanded(
          child: board.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text(ErrorHandler.message(e))),
            data: (rows) => rows.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text('🏅', style: TextStyle(fontSize: 48)),
                        const SizedBox(height: 8),
                        Text('No data yet', style: context.tt.bodyMedium?.copyWith(color: context.colors.mutedText)),
                        Text('Be the first to earn points!', style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText)),
                      ],
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    itemCount: rows.length,
                    itemBuilder: (ctx, i) {
                      final row   = rows[i];
                      final rank  = row['rank'] as int? ?? i + 1;
                      final isMe  = row['is_me'] as bool? ?? false;
                      final tier  = row['tier'] as String? ?? 'bronze';
                      final pts   = row['pts_earned'] as int? ?? 0;
                      final name  = row['name'] as String? ?? '***';

                      final rankEmoji = rank == 1 ? '🥇' : rank == 2 ? '🥈' : rank == 3 ? '🥉' : '#$rank';

                      return Container(
                        margin: const EdgeInsets.only(bottom: 6),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: isMe
                              ? AppColors.primary.withAlpha(15)
                              : rank <= 3
                                  ? Colors.amber.withAlpha(10)
                                  : context.colors.cardBg,
                          border: Border.all(
                            color: isMe
                                ? AppColors.primary.withAlpha(60)
                                : rank <= 3
                                    ? Colors.amber.withAlpha(40)
                                    : Colors.transparent,
                          ),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Row(
                          children: [
                            SizedBox(
                              width: 36,
                              child: Text(rankEmoji,
                                  textAlign: TextAlign.center,
                                  style: TextStyle(fontSize: rank <= 3 ? 22 : 14,
                                      fontWeight: FontWeight.bold,
                                      color: rank > 3 ? context.colors.mutedText : null)),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Text(isMe ? 'You' : name,
                                          style: TextStyle(fontWeight: FontWeight.bold,
                                              color: isMe ? AppColors.primary : null)),
                                      const SizedBox(width: 6),
                                      TierBadge(tier: tier, size: 11),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                            Text('${_fmt(pts)} pts',
                                style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: rank <= 3 ? Colors.amber.shade700 : AppColors.primary)),
                          ],
                        ),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}

// ── Streak flame widget (shown inline on wallet card) ─────────────────────────

class StreakFlame extends ConsumerWidget {
  const StreakFlame({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_gamificationProvider).valueOrNull;
    final streak = (data?['streak'] as Map<String, dynamic>?)?['current'] as int? ?? 0;
    if (streak == 0) return const SizedBox.shrink();

    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GamificationScreen())),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.orange.withAlpha(40),
          border: Border.all(color: Colors.orange.withAlpha(100)),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('🔥', style: TextStyle(fontSize: 12)),
            const SizedBox(width: 3),
            Text('$streak', style: const TextStyle(
                color: Colors.orange, fontSize: 12, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }
}
