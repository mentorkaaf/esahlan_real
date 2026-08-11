import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';
import '../../../../core/l10n/app_strings.dart';

class CommunityExploreScreen extends ConsumerStatefulWidget {
  const CommunityExploreScreen({super.key});

  @override
  ConsumerState<CommunityExploreScreen> createState() => _State();
}

class _State extends ConsumerState<CommunityExploreScreen> {
  final _searchCtrl = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final exploreAsync = ref.watch(communityExploreProvider);
    final suggestions = ref.watch(communitySuggestionsProvider);
    final l = AppL10n.of(context);

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_rounded, color: context.colors.navyText),
          onPressed: () => Navigator.pop(context),
        ),
        title: Container(
          height: 40,
          decoration: BoxDecoration(
            color: context.colors.searchBarBg,
            borderRadius: BorderRadius.circular(20),
          ),
          child: TextField(
            controller: _searchCtrl,
            onChanged: (v) => setState(() => _query = v),
            style: TextStyle(fontSize: 14, color: context.colors.navyText),
            decoration: InputDecoration(
              hintText: l.searchCommunity,
              hintStyle: TextStyle(color: context.colors.mutedText, fontSize: 14),
              prefixIcon: Icon(Icons.search_rounded, color: context.colors.mutedText, size: 20),
              border: InputBorder.none,
              contentPadding: EdgeInsets.symmetric(vertical: 10),
            ),
          ),
        ),
      ),
      body: ListView(
        padding: EdgeInsets.only(bottom: 20),
        children: [
          // People you may know
          if (_query.isEmpty) ...[
            _section(l.peopleYouMayKnow),
            suggestions.when(
              data: (users) => SizedBox(
                height: 120,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: EdgeInsets.symmetric(horizontal: 16),
                  itemCount: users.length,
                  itemBuilder: (ctx, i) => _PersonCard(user: users[i]),
                ),
              ),
              loading: () => SizedBox(height: 120, child: Center(child: CircularProgressIndicator(color: kOrange))),
              error: (_, __) => const SizedBox.shrink(),
            ),
            _section(l.trendingPosts),
          ],

          // Posts
          exploreAsync.when(
            data: (posts) {
              if (posts.isEmpty) {
                return Center(
                  child: Padding(
                    padding: EdgeInsets.all(40),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.trending_up_rounded, size: 50, color: Color(0xFFD1D5DB)),
                      SizedBox(height: 12),
                      Text(l.noTrendingPosts,
                          style: TextStyle(color: context.colors.mutedText, fontSize: 15)),
                    ]),
                  ),
                );
              }
              return Column(
                children: posts.map((p) => _MiniPostCard(post: p)).toList(),
              );
            },
            loading: () => Center(
              child: Padding(
                padding: EdgeInsets.all(40),
                child: CircularProgressIndicator(color: kOrange),
              ),
            ),
            error: (e, _) => Center(child: Text('$e', style: TextStyle(color: Colors.red))),
          ),
        ],
      ),
    );
  }

  Widget _section(String title) => Padding(
    padding: EdgeInsets.fromLTRB(16, 14, 16, 8),
    child: Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
  );
}

class _PersonCard extends ConsumerWidget {
  final CommunityUser user;
  const _PersonCard({required this.user});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () => context.push('/community/profile/${user.id}'),
      child: Container(
        width: 80,
        margin: EdgeInsets.only(right: 12),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Container(
            padding: EdgeInsets.all(2),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(colors: [kOrange, Color(0xFFFF8C42)]),
            ),
            child: Container(
              padding: EdgeInsets.all(2),
              decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle),
              child: CircleNetImage(url: user.avatar, size: 56, fallbackText: user.name),
            ),
          ),
          SizedBox(height: 6),
          Text(user.name.split(' ')[0],
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: context.colors.navyText),
              maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
        ]),
      ),
    );
  }
}

class _MiniPostCard extends StatelessWidget {
  final CommunityPost post;
  const _MiniPostCard({required this.post});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: 8),
      color: context.colors.cardBg,
      padding: EdgeInsets.all(12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          CircleNetImage(url: post.user.avatar, size: 36, fallbackText: post.user.name),
          SizedBox(width: 8),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(post.user.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
              if (post.user.isVerified)
                Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 12),
            ]),
          ),
          Row(children: [
            Icon(Icons.thumb_up_rounded, color: kOrange, size: 14),
            SizedBox(width: 4),
            Text('${post.likesCount}', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
          ]),
        ]),
        if (post.content != null && post.content!.isNotEmpty) ...[
          SizedBox(height: 8),
          Text(post.content!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: context.colors.bodyText, fontSize: 14)),
        ],
        if (post.media.isNotEmpty) ...[
          SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: NetImage(
              url: post.media[0].url,
              height: 160, width: double.infinity, fit: BoxFit.cover,
            ),
          ),
        ],
      ]),
    );
  }
}
