import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../../../../core/utils/media_url.dart';
import 'live_viewer_screen.dart';
import 'go_live_screen.dart';
import 'past_lives_screen.dart';

class LiveRoomsScreen extends StatefulWidget {
  const LiveRoomsScreen({super.key});

  @override
  State<LiveRoomsScreen> createState() => _LiveRoomsScreenState();
}

class _LiveRoomsScreenState extends State<LiveRoomsScreen> {
  final _repo = LiveRepository();
  final _searchCtrl = TextEditingController();
  Timer? _searchDebounce;

  List<LiveCategory> _categories = [];
  String _selectedCategory = 'general';
  LiveDiscovery? _discovery;
  List<LiveRoom>? _searchResults;
  bool _loading = true;
  bool _searching = false;

  @override
  void initState() {
    super.initState();
    _loadCategories();
    _loadDiscovery();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _searchDebounce?.cancel();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    try {
      final cats = await _repo.getCategories();
      if (mounted) setState(() => _categories = cats);
    } catch (_) {}
  }

  Future<void> _loadDiscovery({bool showLoading = true}) async {
    if (showLoading) setState(() => _loading = true);
    try {
      final d = await _repo.getDiscovery(category: _selectedCategory);
      if (mounted) setState(() { _discovery = d; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSearchChanged(String q) {
    _searchDebounce?.cancel();
    if (q.trim().isEmpty) {
      setState(() { _searchResults = null; _searching = false; });
      return;
    }
    setState(() => _searching = true);
    _searchDebounce = Timer(const Duration(milliseconds: 400), () async {
      try {
        final results = await _repo.searchRooms(q.trim());
        if (mounted) setState(() { _searchResults = results; _searching = false; });
      } catch (_) {
        if (mounted) setState(() => _searching = false);
      }
    });
  }

  void _selectCategory(String key) {
    if (_selectedCategory == key) return;
    setState(() { _selectedCategory = key; _discovery = null; });
    _loadDiscovery();
  }

  void _openRoom(LiveRoom room) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => LiveViewerScreen(room: room)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0A14),
      body: SafeArea(
        child: Column(
          children: [
            _buildHeader(),
            _buildSearch(),
            _buildCategoryChips(),
            const Divider(color: Colors.white10, height: 1),
            Expanded(
              child: _searchCtrl.text.isNotEmpty
                  ? _buildSearchResults()
                  : _loading
                      ? const Center(child: CircularProgressIndicator(color: Colors.orange))
                      : _buildDiscovery(),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GoLiveScreen())),
        backgroundColor: Colors.red,
        icon: const Icon(Icons.live_tv, color: Colors.white),
        label: const Text('Go Live', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Row(
        children: [
          const Text('🔴', style: TextStyle(fontSize: 18)),
          const SizedBox(width: 8),
          const Text('Live',
              style: TextStyle(
                  color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
          const Spacer(),
          GestureDetector(
            onTap: () => Navigator.push(
                context, MaterialPageRoute(builder: (_) => const PastLivesScreen())),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.white12),
              ),
              child: const Row(
                children: [
                  Icon(Icons.history, color: Colors.white60, size: 14),
                  SizedBox(width: 4),
                  Text('Past', style: TextStyle(color: Colors.white60, fontSize: 12)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearch() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 8),
      child: Container(
        height: 40,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.07),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.white12),
        ),
        child: TextField(
          controller: _searchCtrl,
          style: const TextStyle(color: Colors.white, fontSize: 13),
          onChanged: _onSearchChanged,
          decoration: const InputDecoration(
            hintText: 'Search live streams...',
            hintStyle: TextStyle(color: Colors.white38, fontSize: 13),
            prefixIcon: Icon(Icons.search, color: Colors.white38, size: 18),
            border: InputBorder.none,
            contentPadding: EdgeInsets.symmetric(vertical: 10),
          ),
        ),
      ),
    );
  }

  Widget _buildCategoryChips() {
    if (_categories.isEmpty) return const SizedBox.shrink();
    return SizedBox(
      height: 38,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: _categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final cat = _categories[i];
          final selected = _selectedCategory == cat.key;
          return GestureDetector(
            onTap: () => _selectCategory(cat.key),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: selected
                    ? Colors.red.withValues(alpha: 0.25)
                    : Colors.white.withValues(alpha: 0.05),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                    color: selected ? Colors.red : Colors.white12,
                    width: selected ? 1.5 : 1),
              ),
              child: Row(
                children: [
                  Text(cat.emoji, style: const TextStyle(fontSize: 13)),
                  const SizedBox(width: 5),
                  Text(cat.label,
                      style: TextStyle(
                          color: selected ? Colors.white : Colors.white60,
                          fontSize: 12,
                          fontWeight: selected ? FontWeight.bold : FontWeight.normal)),
                  if (cat.count > 0) ...[
                    const SizedBox(width: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                      decoration: BoxDecoration(
                          color: Colors.red, borderRadius: BorderRadius.circular(8)),
                      child: Text('${cat.count}',
                          style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildSearchResults() {
    if (_searching) {
      return const Center(child: CircularProgressIndicator(color: Colors.orange));
    }
    final results = _searchResults ?? [];
    if (results.isEmpty) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('🔍', style: TextStyle(fontSize: 40)),
            SizedBox(height: 12),
            Text('No results found', style: TextStyle(color: Colors.white54, fontSize: 14)),
          ],
        ),
      );
    }
    return GridView.builder(
      padding: const EdgeInsets.all(12),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2, crossAxisSpacing: 10, mainAxisSpacing: 10, childAspectRatio: 0.72),
      itemCount: results.length,
      itemBuilder: (_, i) => _RoomCard(room: results[i], onTap: () => _openRoom(results[i])),
    );
  }

  Widget _buildDiscovery() {
    final d = _discovery;
    if (d == null) return const SizedBox.shrink();

    final hasAny = d.featured != null || d.following.isNotEmpty ||
        d.trending.isNotEmpty || d.all.isNotEmpty;

    if (!hasAny) {
      return RefreshIndicator(
        onRefresh: _loadDiscovery,
        color: Colors.orange,
        child: ListView(
          children: [
            const SizedBox(height: 80),
            const Center(
              child: Column(
                children: [
                  Icon(Icons.live_tv_outlined, color: Colors.white24, size: 64),
                  SizedBox(height: 16),
                  Text('No live streams right now',
                      style: TextStyle(color: Colors.white54, fontSize: 15)),
                  SizedBox(height: 8),
                  Text('Be the first to go live!',
                      style: TextStyle(color: Colors.white30, fontSize: 13)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadDiscovery,
      color: Colors.orange,
      child: CustomScrollView(
        slivers: [
          // Featured
          if (d.featured != null)
            SliverToBoxAdapter(
              child: _FeaturedCard(room: d.featured!, onTap: () => _openRoom(d.featured!)),
            ),

          // Following
          if (d.following.isNotEmpty) ...[
            _sectionHeader('👥 Following', d.following.length),
            SliverToBoxAdapter(
              child: SizedBox(
                height: 200,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: d.following.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 10),
                  itemBuilder: (_, i) => SizedBox(
                    width: 140,
                    child: _RoomCard(room: d.following[i], onTap: () => _openRoom(d.following[i])),
                  ),
                ),
              ),
            ),
          ],

          // Trending
          if (d.trending.isNotEmpty) ...[
            _sectionHeader('🔥 Trending', d.trending.length),
            SliverToBoxAdapter(
              child: SizedBox(
                height: 200,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: d.trending.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 10),
                  itemBuilder: (_, i) => SizedBox(
                    width: 140,
                    child: _RoomCard(room: d.trending[i], onTap: () => _openRoom(d.trending[i])),
                  ),
                ),
              ),
            ),
          ],

          // All rooms grid
          if (d.all.isNotEmpty) ...[
            _sectionHeader('📡 All Live', d.all.length),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 80),
              sliver: SliverGrid(
                delegate: SliverChildBuilderDelegate(
                  (_, i) => _RoomCard(room: d.all[i], onTap: () => _openRoom(d.all[i])),
                  childCount: d.all.length,
                ),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 10,
                    mainAxisSpacing: 10,
                    childAspectRatio: 0.72),
              ),
            ),
          ],
        ],
      ),
    );
  }

  SliverToBoxAdapter _sectionHeader(String title, int count) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 8),
        child: Row(
          children: [
            Text(title,
                style: const TextStyle(
                    color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold)),
            const SizedBox(width: 6),
            Text('($count)', style: const TextStyle(color: Colors.white38, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

// ─── Featured hero card ──────────────────────────────────────────────────────

class _FeaturedCard extends StatelessWidget {
  final LiveRoom room;
  final VoidCallback onTap;
  const _FeaturedCard({required this.room, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 220,
        margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          color: const Color(0xFF1A1A2E),
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(18),
          child: Stack(
            children: [
              // Background
              Positioned.fill(
                child: room.thumbnail?.isNotEmpty == true
                    ? Image.network(
                        fixMediaUrl(room.thumbnail!),
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _bg(),
                      )
                    : _bg(),
              ),
              // Gradient
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black.withValues(alpha: 0.85)],
                      stops: const [0.3, 1.0],
                    ),
                  ),
                ),
              ),
              // Top badges
              Positioned(
                top: 12,
                left: 14,
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                          color: Colors.red, borderRadius: BorderRadius.circular(6)),
                      child: const Row(
                        children: [
                          Text('●', style: TextStyle(color: Colors.white, fontSize: 8)),
                          SizedBox(width: 4),
                          Text('LIVE', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                          color: Colors.black54, borderRadius: BorderRadius.circular(6)),
                      child: const Text('FEATURED',
                          style: TextStyle(color: Colors.orange, fontSize: 10, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
              ),
              Positioned(
                top: 12,
                right: 14,
                child: _viewerBadge(room.viewerCount),
              ),
              // Bottom info
              Positioned(
                bottom: 14,
                left: 14,
                right: 14,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    CircleAvatar(
                      radius: 20,
                      backgroundColor: Colors.orange.withValues(alpha: 0.5),
                      backgroundImage: room.host.avatar.isNotEmpty
                          ? NetworkImage(fixMediaUrl(room.host.avatar))
                          : null,
                      child: room.host.avatar.isEmpty
                          ? Text(room.host.name.isNotEmpty ? room.host.name[0].toUpperCase() : '?',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
                          : null,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(room.host.name,
                              style: const TextStyle(color: Colors.white70, fontSize: 12)),
                          Text(room.title,
                              style: const TextStyle(
                                  color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                      decoration: BoxDecoration(
                          color: Colors.red, borderRadius: BorderRadius.circular(20)),
                      child: const Text('Join',
                          style: TextStyle(
                              color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _bg() => Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF2D1B69), Color(0xFF0D2137)]),
        ),
        child: const Center(child: Icon(Icons.live_tv, color: Colors.white12, size: 60)),
      );

  Widget _viewerBadge(int count) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
            color: Colors.black54, borderRadius: BorderRadius.circular(10)),
        child: Row(
          children: [
            const Icon(Icons.remove_red_eye, color: Colors.white70, size: 12),
            const SizedBox(width: 3),
            Text('$count',
                style: const TextStyle(color: Colors.white, fontSize: 11)),
          ],
        ),
      );
}

// ─── Regular room card ───────────────────────────────────────────────────────

class _RoomCard extends StatelessWidget {
  final LiveRoom room;
  final VoidCallback onTap;
  const _RoomCard({required this.room, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFF1A1A2E),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white10),
        ),
        child: Stack(
          children: [
            // Thumbnail
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: room.thumbnail?.isNotEmpty == true
                  ? Image.network(
                      fixMediaUrl(room.thumbnail!),
                      width: double.infinity,
                      height: double.infinity,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _gradientBg(),
                    )
                  : _gradientBg(),
            ),
            // Gradient overlay
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Colors.black.withValues(alpha: 0.82)],
                    stops: const [0.35, 1.0],
                  ),
                ),
              ),
            ),
            // LIVE badge
            Positioned(
              top: 8,
              left: 8,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                    color: Colors.red, borderRadius: BorderRadius.circular(5)),
                child: const Text('LIVE',
                    style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
              ),
            ),
            // Viewer count
            Positioned(
              top: 8,
              right: 8,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                    color: Colors.black54, borderRadius: BorderRadius.circular(8)),
                child: Row(
                  children: [
                    const Icon(Icons.remove_red_eye, color: Colors.white70, size: 10),
                    const SizedBox(width: 2),
                    Text('${room.viewerCount}',
                        style: const TextStyle(color: Colors.white, fontSize: 10)),
                  ],
                ),
              ),
            ),
            // Bottom info
            Positioned(
              bottom: 8,
              left: 8,
              right: 8,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 10,
                        backgroundColor: Colors.orange.withValues(alpha: 0.5),
                        backgroundImage: room.host.avatar.isNotEmpty
                            ? NetworkImage(fixMediaUrl(room.host.avatar))
                            : null,
                        child: room.host.avatar.isEmpty
                            ? Text(
                                room.host.name.isNotEmpty
                                    ? room.host.name[0].toUpperCase()
                                    : '?',
                                style: const TextStyle(color: Colors.white, fontSize: 8))
                            : null,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(room.host.name,
                            style: const TextStyle(color: Colors.white60, fontSize: 10),
                            overflow: TextOverflow.ellipsis),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(room.title,
                      style: const TextStyle(
                          color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _gradientBg() => Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF1A0A2E), Color(0xFF0D2137)]),
        ),
        child: const Center(child: Icon(Icons.live_tv, color: Colors.white10, size: 32)),
      );
}
