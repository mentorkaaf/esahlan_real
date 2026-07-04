import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';

class CommunitySearchScreen extends ConsumerStatefulWidget {
  const CommunitySearchScreen({super.key});
  @override
  ConsumerState<CommunitySearchScreen> createState() => _CommunitySearchScreenState();
}

class _CommunitySearchScreenState extends ConsumerState<CommunitySearchScreen> with SingleTickerProviderStateMixin {
  final _ctrl = TextEditingController();
  late TabController _tab;
  String _query = '';
  Map<String, dynamic>? _results;
  bool _loading = false;

  @override
  void initState() { super.initState(); _tab = TabController(length: 4, vsync: this); }
  @override
  void dispose() { _ctrl.dispose(); _tab.dispose(); super.dispose(); }

  Future<void> _search(String type) async {
    if (_query.isEmpty) return;
    setState(() => _loading = true);
    try {
      final res = await ref.read(communityRepoProvider).search(_query, type: type);
      setState(() => _results = res);
    } catch (_) {} finally { setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    final trending = ref.watch(_trendingProvider);

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Container(
          height: 40, margin: EdgeInsets.only(right: 12),
          child: TextField(
            controller: _ctrl, autofocus: true,
            onChanged: (v) => setState(() => _query = v),
            onSubmitted: (_) => _search(['posts', 'users', 'groups', 'hashtags'][_tab.index]),
            decoration: InputDecoration(
              hintText: 'Search community...', hintStyle: TextStyle(fontSize: 14, color: context.colors.mutedText),
              prefixIcon: Icon(Icons.search_rounded, size: 20, color: context.colors.mutedText),
              suffixIcon: _query.isNotEmpty ? IconButton(icon: Icon(Icons.close, size: 16), onPressed: () { _ctrl.clear(); setState(() { _query = ''; _results = null; }); }) : null,
              filled: true, fillColor: context.colors.searchBarBg,
              contentPadding: EdgeInsets.symmetric(vertical: 8),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(20), borderSide: BorderSide.none),
            ),
          ),
        ),
        bottom: TabBar(
          controller: _tab, indicatorColor: kOrange, labelColor: kOrange, unselectedLabelColor: const Color(0xFF9CA3AF),
          labelStyle: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          onTap: (i) => _search(['posts', 'users', 'groups', 'hashtags'][i]),
          tabs: const [Tab(text: 'Posts'), Tab(text: 'People'), Tab(text: 'Groups'), Tab(text: 'Tags')],
        ),
      ),
      body: _query.isEmpty
          ? _buildTrending(trending)
          : _loading
              ? Center(child: CircularProgressIndicator(color: kOrange))
              : _buildResults(),
    );
  }

  Widget _buildTrending(AsyncValue<List<Map<String, dynamic>>> trending) {
    return trending.when(
      loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => SizedBox(),
      data: (tags) {
        if (tags.isEmpty) return Center(child: Text('Start typing to search', style: TextStyle(color: context.colors.mutedText)));
        return ListView(padding: EdgeInsets.all(16), children: [
          Text('Trending', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: context.colors.navyText)),
          SizedBox(height: 12),
          ...tags.asMap().entries.map((e) {
            final tag = e.value;
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Container(width: 40, height: 40, decoration: BoxDecoration(color: context.colors.chipBg, borderRadius: BorderRadius.circular(10)),
                child: Center(child: Text('#', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: kOrange)))),
              title: Text('#${tag['name'] ?? ''}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: context.colors.navyText)),
              subtitle: Text('${tag['posts_count'] ?? 0} posts', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
              trailing: Text('#${e.key + 1}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFFD1D5DB))),
              onTap: () { _ctrl.text = '#${tag['name']}'; setState(() => _query = '#${tag['name']}'); _search('posts'); },
            );
          }),
        ]);
      },
    );
  }

  Widget _buildResults() {
    final data = _results?['data'];
    if (data == null || (data is List && data.isEmpty)) {
      return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.search_off_rounded, size: 48, color: Color(0xFFD1D5DB)),
        SizedBox(height: 12),
        Text('No results for "$_query"', style: TextStyle(color: context.colors.mutedText, fontSize: 15)),
      ]));
    }

    final list = data is List ? data : (data is Map ? data['data'] as List? ?? [] : []);
    final type = ['posts', 'users', 'groups', 'hashtags'][_tab.index];

    return ListView.builder(
      padding: EdgeInsets.all(12),
      itemCount: list.length,
      itemBuilder: (_, i) {
        final item = list[i] as Map<String, dynamic>;
        if (type == 'users') return _userTile(item);
        if (type == 'groups') return _groupTile(item);
        if (type == 'hashtags') return _hashtagTile(item);
        return _postTile(item);
      },
    );
  }

  Widget _userTile(Map<String, dynamic> u) => ListTile(
    contentPadding: EdgeInsets.symmetric(vertical: 4),
    leading: CircleNetImage(url: u['avatar'], size: 44, fallbackText: u['name'] ?? '?'),
    title: Row(children: [
      Flexible(child: Text(u['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14))),
      if (u['is_verified'] == true) Padding(padding: EdgeInsets.only(left: 4), child: Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 16)),
    ]),
    subtitle: Text(u['username'] != null ? '@${u['username']}' : '', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
    onTap: () => context.push('/community/profile/${u['id']}'),
  );

  Widget _groupTile(Map<String, dynamic> g) => ListTile(
    contentPadding: EdgeInsets.symmetric(vertical: 4),
    leading: Container(width: 44, height: 44, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
      child: Icon(Icons.group_rounded, color: kOrange)),
    title: Text(g['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
    subtitle: Text('${g['members_count'] ?? 0} members', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
  );

  Widget _hashtagTile(Map<String, dynamic> h) => ListTile(
    contentPadding: EdgeInsets.symmetric(vertical: 4),
    leading: Container(width: 44, height: 44, decoration: BoxDecoration(color: context.colors.chipBg, borderRadius: BorderRadius.circular(12)),
      child: Center(child: Text('#', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: kOrange)))),
    title: Text('#${h['name'] ?? ''}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
    subtitle: Text('${h['posts_count'] ?? 0} posts', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
    onTap: () { _ctrl.text = '#${h['name']}'; setState(() => _query = '#${h['name']}'); _tab.animateTo(0); _search('posts'); },
  );

  Widget _postTile(Map<String, dynamic> p) {
    final user = p['user'] as Map<String, dynamic>? ?? {};
    return Container(
      margin: EdgeInsets.only(bottom: 10), padding: EdgeInsets.all(12),
      decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          CircleNetImage(url: user['avatar'], size: 32, fallbackText: user['name'] ?? '?'),
          SizedBox(width: 8),
          Expanded(child: Text(user['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13))),
        ]),
        if (p['content'] != null) Padding(padding: EdgeInsets.only(top: 8),
          child: Text(p['content'], style: TextStyle(fontSize: 14, color: context.colors.bodyText), maxLines: 3, overflow: TextOverflow.ellipsis)),
        Padding(padding: EdgeInsets.only(top: 8), child: Row(children: [
          Icon(Icons.favorite_rounded, size: 14, color: context.colors.mutedText),
          Text(' ${p['likes_count'] ?? 0}', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
          SizedBox(width: 12),
          Icon(Icons.chat_bubble_outline_rounded, size: 14, color: context.colors.mutedText),
          Text(' ${p['comments_count'] ?? 0}', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
        ])),
      ]),
    );
  }
}

final _trendingProvider = FutureProvider<List<Map<String, dynamic>>>((ref) => ref.read(communityRepoProvider).getTrending());
