import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class LiveLeaderboardSheet extends StatefulWidget {
  final int roomId;
  const LiveLeaderboardSheet({super.key, required this.roomId});

  @override
  State<LiveLeaderboardSheet> createState() => _LiveLeaderboardSheetState();
}

class _LiveLeaderboardSheetState extends State<LiveLeaderboardSheet>
    with SingleTickerProviderStateMixin {
  final _repo = LiveRepository();
  final _periods = ['all', 'daily', 'weekly', 'monthly'];
  final _labels  = ['All Time', 'Today', 'This Week', 'This Month'];
  late TabController _tab;
  final _cache = <String, List<LeaderboardEntry>>{};
  List<LeaderboardEntry>? _current;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: _periods.length, vsync: this);
    _tab.addListener(() { if (!_tab.indexIsChanging) _load(_periods[_tab.index]); });
    _load('all');
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  Future<void> _load(String period) async {
    if (_cache.containsKey(period)) {
      setState(() { _current = _cache[period]; _loading = false; });
      return;
    }
    setState(() => _loading = true);
    try {
      final list = await _repo.getRoomLeaderboard(widget.roomId, period: period);
      _cache[period] = list;
      if (mounted) setState(() { _current = list; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Color _medalColor(int rank) {
    switch (rank) {
      case 1: return const Color(0xFFFFD700);
      case 2: return const Color(0xFFC0C0C0);
      case 3: return const Color(0xFFCD7F32);
      default: return Colors.white38;
    }
  }

  String _medal(int rank) {
    switch (rank) {
      case 1: return '🥇';
      case 2: return '🥈';
      case 3: return '🥉';
      default: return '${rank}';
    }
  }

  String _fmt(int n) {
    if (n >= 1000000) return '${(n / 1000000).toStringAsFixed(1)}M';
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.7,
      decoration: const BoxDecoration(
        color: Color(0xFF0F0F1A),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          // Handle
          const SizedBox(height: 10),
          Center(
            child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                  color: Colors.white24, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const SizedBox(height: 12),
          // Header
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Text('🏆', style: TextStyle(fontSize: 22)),
                SizedBox(width: 8),
                Text('Top Gifters',
                    style: TextStyle(
                        color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              ],
            ),
          ),
          const SizedBox(height: 12),
          // Period tabs
          TabBar(
            controller: _tab,
            indicatorColor: Colors.orange,
            labelColor: Colors.orange,
            unselectedLabelColor: Colors.white38,
            dividerColor: Colors.transparent,
            labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            tabs: _labels.map((l) => Tab(text: l)).toList(),
          ),
          const Divider(color: Colors.white12, height: 1),
          // Content
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: Colors.orange))
                : (_current?.isEmpty ?? true)
                    ? const Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text('🎁', style: TextStyle(fontSize: 40)),
                            SizedBox(height: 12),
                            Text('No gifts sent yet',
                                style: TextStyle(color: Colors.white54, fontSize: 14)),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _current!.length,
                        itemBuilder: (_, i) {
                          final e = _current![i];
                          final isTop3 = e.rank <= 3;
                          return AnimatedContainer(
                            duration: Duration(milliseconds: 200 + i * 30),
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            decoration: BoxDecoration(
                              color: isTop3
                                  ? _medalColor(e.rank).withValues(alpha: 0.08)
                                  : Colors.white.withValues(alpha: 0.04),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: isTop3
                                    ? _medalColor(e.rank).withValues(alpha: 0.3)
                                    : Colors.white12,
                              ),
                            ),
                            child: Row(
                              children: [
                                // Rank
                                SizedBox(
                                  width: 32,
                                  child: Text(
                                    _medal(e.rank),
                                    style: TextStyle(
                                        fontSize: isTop3 ? 22 : 13,
                                        color: _medalColor(e.rank),
                                        fontWeight: FontWeight.bold),
                                    textAlign: TextAlign.center,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                // Avatar
                                CircleAvatar(
                                  radius: 18,
                                  backgroundColor:
                                      _medalColor(e.rank).withValues(alpha: 0.3),
                                  backgroundImage: e.avatar.isNotEmpty
                                      ? NetworkImage(e.avatar)
                                      : null,
                                  child: e.avatar.isEmpty
                                      ? Text(
                                          e.name.isNotEmpty ? e.name[0].toUpperCase() : '?',
                                          style: TextStyle(
                                              color: Colors.white,
                                              fontSize: 13,
                                              fontWeight: FontWeight.bold),
                                        )
                                      : null,
                                ),
                                const SizedBox(width: 10),
                                // Name
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(e.name,
                                          style: TextStyle(
                                              color: isTop3
                                                  ? _medalColor(e.rank)
                                                  : Colors.white,
                                              fontWeight: isTop3
                                                  ? FontWeight.bold
                                                  : FontWeight.normal,
                                              fontSize: 13)),
                                      if (e.username.isNotEmpty)
                                        Text('@${e.username}',
                                            style: const TextStyle(
                                                color: Colors.white38, fontSize: 10)),
                                    ],
                                  ),
                                ),
                                // Coins
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Row(
                                      children: [
                                        const Text('🪙',
                                            style: TextStyle(fontSize: 13)),
                                        const SizedBox(width: 3),
                                        Text(
                                          _fmt(e.totalCoins),
                                          style: TextStyle(
                                              color: isTop3
                                                  ? _medalColor(e.rank)
                                                  : Colors.white70,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14),
                                        ),
                                      ],
                                    ),
                                    if (e.totalGifts > 0)
                                      Text('${e.totalGifts} gifts',
                                          style: const TextStyle(
                                              color: Colors.white38, fontSize: 10)),
                                  ],
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),
          SizedBox(height: MediaQuery.of(context).padding.bottom),
        ],
      ),
    );
  }
}
