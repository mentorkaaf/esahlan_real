import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/community_provider.dart';
import '../widgets/post_card.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import 'create_post_screen.dart';
import 'reels_screen.dart';
import 'groups_screen.dart';
import 'community_chat_list_screen.dart';
import 'community_notifications_screen.dart';

class CommunityScreen extends ConsumerStatefulWidget {
  const CommunityScreen({super.key});
  @override
  ConsumerState<CommunityScreen> createState() => _CommunityScreenState();
}

class _CommunityScreenState extends ConsumerState<CommunityScreen>
    with TickerProviderStateMixin {
  late TabController _tabCtrl;
  final _scrollCtrl = ScrollController();
  int _selectedTab = 0; // 0=Feed 1=Explore 2=Reels 3=Groups

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 4, vsync: this);
    _tabCtrl.addListener(() => setState(() => _selectedTab = _tabCtrl.index));
    _scrollCtrl.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) {
      if (_selectedTab == 0) ref.read(communityFeedProvider.notifier).load();
      if (_selectedTab == 1) ref.read(communityExploreProvider.notifier).load();
    }
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F5F8),
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            floating: true,
            snap: true,
            elevation: 0,
            
            title: Row(
              children: [
                RichText(
                  text: const TextSpan(
                    style: TextStyle(fontFamily: 'Poppins'),
                    children: [
                      TextSpan(text: 'e', style: TextStyle(color: Color(0xFFFF6B35), fontWeight: FontWeight.w800, fontSize: 22)),
                      TextSpan(text: '-Sahlan ', style: TextStyle(color: Color(0xFF140465), fontWeight: FontWeight.w800, fontSize: 22)),
                      TextSpan(text: 'Community', style: TextStyle(color: Color(0xFF140465), fontWeight: FontWeight.w500, fontSize: 18)),
                    ],
                  ),
                ),
              ],
            ),
            actions: [
              IconButton(
                icon: Icon(Icons.search, color: Color(0xFF140465)),
                onPressed: () => showSearch(context: context, delegate: _CommunitySearchDelegate()),
              ),
              Consumer(builder: (_, ref, __) {
                final count = ref.watch(communityUnreadCountProvider);
                return Stack(
                  children: [
                    IconButton(
                      icon: Icon(Icons.notifications_outlined, color: Color(0xFF140465)),
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunityNotificationsScreen())),
                    ),
                    if (count > 0)
                      Positioned(
                        right: 8, top: 8,
                        child: Container(
                          padding: EdgeInsets.all(4),
                          decoration: BoxDecoration(color: Color(0xFFFF6B35), shape: BoxShape.circle),
                          child: Text('$count', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                        ),
                      ),
                  ],
                );
              }),
              IconButton(
                icon: Icon(Icons.chat_bubble_outline, color: Color(0xFF140465)),
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunityChatListScreen())),
              ),
            ],
            bottom: TabBar(
              controller: _tabCtrl,
              labelColor: context.colors.navyText,
              unselectedLabelColor: Colors.grey,
              indicatorColor: const Color(0xFFFF6B35),
              indicatorWeight: 3,
              labelStyle: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
              tabs: const [
                Tab(text: 'Feed'),
                Tab(text: 'Explore'),
                Tab(text: 'Reels'),
                Tab(text: 'Groups'),
              ],
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabCtrl,
          children: [
            _FeedTab(scrollCtrl: _scrollCtrl),
            _ExploreTab(),
            const ReelsScreen(),
            const GroupsScreen(),
          ],
        ),
      ),
      floatingActionButton: _selectedTab != 2 ? FloatingActionButton(
        backgroundColor: const Color(0xFFFF6B35),
        onPressed: () async {
          final post = await Navigator.push<CommunityPost>(
            context,
            MaterialPageRoute(builder: (_) => const CreatePostScreen()),
          );
          if (post != null) {
            ref.read(communityFeedProvider.notifier).prependPost(post);
          }
        },
        child: Icon(Icons.add, color: Colors.white),
      ) : null,
    );
  }
}

// ── Feed Tab ──────────────────────────────────────────────────────────────────
class _FeedTab extends ConsumerWidget {
  final ScrollController scrollCtrl;
  const _FeedTab({required this.scrollCtrl});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feedState = ref.watch(communityFeedProvider);
    final storiesState = ref.watch(communityStoriesProvider);

    return RefreshIndicator(
      color: context.colors.navyText,
      onRefresh: () => ref.read(communityFeedProvider.notifier).refresh(),
      child: CustomScrollView(
        controller: scrollCtrl,
        slivers: [
          // Stories bar
          SliverToBoxAdapter(
            child: storiesState.when(
              data: (groups) => _StoriesBar(groups: groups),
              loading: () => SizedBox(height: 110, child: Center(child: CircularProgressIndicator())),
              error: (_, __) => const SizedBox.shrink(),
            ),
          ),
          // What's on your mind
          SliverToBoxAdapter(child: _CreatePostBar()),
          // Posts
          feedState.when(
            data: (posts) => posts.isEmpty
                ? SliverFillRemaining(child: _EmptyFeed())
                : SliverList(delegate: SliverChildBuilderDelegate(
                    (ctx, i) {
                      if (i == posts.length) {
                        return ref.read(communityFeedProvider.notifier).hasMore
                            ? Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()))
                            : Padding(padding: EdgeInsets.all(16), child: Center(child: Text('No more posts', style: TextStyle(color: Colors.grey))));
                      }
                      return PostCard(
                        post: posts[i],
                        onDelete: () => ref.read(communityFeedProvider.notifier).removePost(posts[i].id),
                        onTap: () {},
                      );
                    },
                    childCount: posts.length + 1,
                  )),
            loading: () => const SliverFillRemaining(child: Center(child: CircularProgressIndicator(color: Color(0xFF140465)))),
            error: (e, _) => SliverFillRemaining(child: Center(child: Text('Error: $e'))),
          ),
          // Bottom padding to clear floating nav bar
          SliverPadding(padding: EdgeInsets.only(bottom: MediaQuery.of(context).padding.bottom + 90)),
        ],
      ),
    );
  }
}

// ── Explore Tab ───────────────────────────────────────────────────────────────
class _ExploreTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(communityExploreProvider);
    final suggestions = ref.watch(communitySuggestionsProvider);

    return RefreshIndicator(
      color: context.colors.navyText,
      onRefresh: () => ref.read(communityExploreProvider.notifier).refresh(),
      child: CustomScrollView(
        slivers: [
          // Suggested users
          SliverToBoxAdapter(
            child: suggestions.when(
              data: (users) => users.isEmpty ? const SizedBox.shrink() : _SuggestedUsers(users: users),
              loading: () => const SizedBox.shrink(),
              error: (_, __) => const SizedBox.shrink(),
            ),
          ),
          // Trending posts
          state.when(
            data: (posts) => SliverList(
              delegate: SliverChildBuilderDelegate(
                (_, i) => PostCard(
                  post: posts[i],
                  onTap: () {},
                ),
                childCount: posts.length,
              ),
            ),
            loading: () => const SliverFillRemaining(child: Center(child: CircularProgressIndicator(color: Color(0xFF140465)))),
            error: (e, _) => SliverFillRemaining(child: Center(child: Text('$e'))),
          ),
          // Bottom padding to clear floating nav bar
          SliverPadding(padding: EdgeInsets.only(bottom: MediaQuery.of(context).padding.bottom + 90)),
        ],
      ),
    );
  }
}

// ── Stories Bar ───────────────────────────────────────────────────────────────
class _StoriesBar extends StatelessWidget {
  final List<StoryGroup> groups;
  const _StoriesBar({required this.groups});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: EdgeInsets.symmetric(vertical: 12),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          children: [
            // Add story button
            _AddStoryButton(),
            SizedBox(width: 12),
            ...groups.map((g) => Padding(
                  padding: EdgeInsets.only(right: 12),
                  child: _StoryAvatar(group: g),
                )),
          ],
        ),
      ),
    );
  }
}

class _StoryAvatar extends StatelessWidget {
  final StoryGroup group;
  const _StoryAvatar({required this.group});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {},
      child: Column(
        children: [
          Container(
            padding: EdgeInsets.all(2.5),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: group.allViewed
                  ? null
                  : const LinearGradient(colors: [Color(0xFFFF6B35), Color(0xFF140465)]),
              color: group.allViewed ? Colors.grey[300] : null,
            ),
            child: Container(
              padding: EdgeInsets.all(2),
              decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white),
              child: CircleAvatar(
                radius: 28,
                backgroundColor: const Color(0xFFEEF0FF),
                backgroundImage: group.user.avatar != null
                    ? CachedNetworkImageProvider(group.user.avatar!)
                    : null,
                child: group.user.avatar == null
                    ? Text(group.user.name[0].toUpperCase(),
                        style: TextStyle(color: Color(0xFF140465), fontWeight: FontWeight.bold))
                    : null,
              ),
            ),
          ),
          SizedBox(height: 4),
          SizedBox(
            width: 68,
            child: Text(
              group.user.name.split(' ').first,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11, color: context.colors.bodyText),
            ),
          ),
        ],
      ),
    );
  }
}

class _AddStoryButton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {},
      child: Column(
        children: [
          Stack(
            children: [
              CircleAvatar(
                radius: 32,
                backgroundColor: const Color(0xFFEEF0FF),
                child: Icon(Icons.person, color: Color(0xFF140465), size: 28),
              ),
              Positioned(
                bottom: 0, right: 0,
                child: Container(
                  padding: EdgeInsets.all(2),
                  decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle),
                  child: Container(
                    padding: EdgeInsets.all(2),
                    decoration: BoxDecoration(color: Color(0xFFFF6B35), shape: BoxShape.circle),
                    child: Icon(Icons.add, size: 14, color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 4),
          SizedBox(
            width: 68,
            child: Text('Your Story', maxLines: 1, overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center, style: TextStyle(fontSize: 11, color: context.colors.bodyText)),
          ),
        ],
      ),
    );
  }
}

// ── Create Post Bar ───────────────────────────────────────────────────────────
class _CreatePostBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.symmetric(vertical: 6),
      color: Colors.white,
      padding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          const CircleAvatar(radius: 20, backgroundColor: Color(0xFFEEF0FF),
              child: Icon(Icons.person, color: Color(0xFF140465))),
          SizedBox(width: 10),
          Expanded(
            child: GestureDetector(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CreatePostScreen())),
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF4F5F8),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Text("What's on your mind?", style: TextStyle(color: Colors.grey, fontSize: 14)),
              ),
            ),
          ),
          SizedBox(width: 8),
          GestureDetector(
            onTap: () => Navigator.push(context, MaterialPageRoute(
                builder: (_) => const CreatePostScreen(initialType: 'image'))),
            child: Icon(Icons.photo, color: Color(0xFF4CAF50), size: 28),
          ),
          SizedBox(width: 8),
          GestureDetector(
            onTap: () => Navigator.push(context, MaterialPageRoute(
                builder: (_) => const CreatePostScreen(initialType: 'video'))),
            child: Icon(Icons.videocam, color: Color(0xFFFF6B35), size: 28),
          ),
        ],
      ),
    );
  }
}

// ── Suggested Users ───────────────────────────────────────────────────────────
class _SuggestedUsers extends StatelessWidget {
  final List<CommunityUser> users;
  const _SuggestedUsers({required this.users});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      margin: EdgeInsets.only(bottom: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Text('People you may know', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF140465))),
          ),
          SizedBox(
            height: 130,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: EdgeInsets.symmetric(horizontal: 12),
              separatorBuilder: (_, __) => SizedBox(width: 8),
              itemCount: users.length,
              itemBuilder: (_, i) => _SuggestedUserCard(user: users[i]),
            ),
          ),
          SizedBox(height: 12),
        ],
      ),
    );
  }
}

class _SuggestedUserCard extends StatefulWidget {
  final CommunityUser user;
  const _SuggestedUserCard({required this.user});

  @override
  State<_SuggestedUserCard> createState() => _SuggestedUserCardState();
}

class _SuggestedUserCardState extends State<_SuggestedUserCard> {
  bool _following = false;
  final _repo = CommunityRepository();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 120,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEEF0FF)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)],
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: const Color(0xFFEEF0FF),
            backgroundImage: widget.user.avatar != null ? CachedNetworkImageProvider(widget.user.avatar!) : null,
            child: widget.user.avatar == null
                ? Text(widget.user.name[0].toUpperCase(), style: TextStyle(color: Color(0xFF140465), fontWeight: FontWeight.bold))
                : null,
          ),
          SizedBox(height: 6),
          Text(widget.user.name.split(' ').first, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: context.colors.bodyText), maxLines: 1, overflow: TextOverflow.ellipsis),
          SizedBox(height: 6),
          GestureDetector(
            onTap: () async {
              final result = await _repo.toggleFollow(widget.user.id);
              setState(() => _following = result['following'] as bool? ?? _following);
            },
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 16, vertical: 5),
              decoration: BoxDecoration(
                color: _following ? const Color(0xFFF4F5F8) : const Color(0xFF140465),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(_following ? 'Following' : 'Follow',
                  style: TextStyle(color: _following ? const Color(0xFF140465) : Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Empty Feed ────────────────────────────────────────────────────────────────
class _EmptyFeed extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.people_alt_outlined, size: 72, color: Color(0xFFEEF0FF)),
          SizedBox(height: 16),
          Text('Your feed is empty', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Color(0xFF140465))),
          SizedBox(height: 8),
          Text('Follow people to see their posts here', style: TextStyle(color: Colors.grey)),
          SizedBox(height: 20),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: context.colors.navyText, foregroundColor: Colors.white),
            onPressed: () {},
            child: Text('Discover People'),
          ),
        ],
      ),
    );
  }
}

// ── Search Delegate ───────────────────────────────────────────────────────────
class _CommunitySearchDelegate extends SearchDelegate {
  final _repo = CommunityRepository();

  @override
  String get searchFieldLabel => 'Search community...';

  @override
  List<Widget> buildActions(BuildContext context) => [
        IconButton(icon: Icon(Icons.clear), onPressed: () => query = ''),
      ];

  @override
  Widget buildLeading(BuildContext context) =>
      IconButton(icon: Icon(Icons.arrow_back), onPressed: () => close(context, null));

  @override
  Widget buildResults(BuildContext context) => _buildSearch(context);

  @override
  Widget buildSuggestions(BuildContext context) => query.isEmpty
      ? Center(child: Text('Search for posts, people, or groups'))
      : _buildSearch(context);

  Widget _buildSearch(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Column(
        children: [
          const TabBar(
            labelColor: Color(0xFF140465),
            indicatorColor: Color(0xFFFF6B35),
            tabs: [Tab(text: 'Posts'), Tab(text: 'People'), Tab(text: 'Groups')],
          ),
          Expanded(
            child: TabBarView(
              children: [
                _SearchResults(query: query, type: 'posts', repo: _repo),
                _SearchResults(query: query, type: 'users', repo: _repo),
                _SearchResults(query: query, type: 'groups', repo: _repo),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchResults extends StatefulWidget {
  final String query;
  final String type;
  final CommunityRepository repo;
  const _SearchResults({required this.query, required this.type, required this.repo});

  @override
  State<_SearchResults> createState() => _SearchResultsState();
}

class _SearchResultsState extends State<_SearchResults> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repo.search(widget.query, type: widget.type);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (_, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return Center(child: CircularProgressIndicator());
        }
        final data = snap.data?['data'] as List? ?? [];
        if (data.isEmpty) return Center(child: Text('No results found'));

        if (widget.type == 'posts') {
          return ListView.builder(
            itemCount: data.length,
            itemBuilder: (_, i) {
              final post = CommunityPost.fromJson(data[i] as Map<String, dynamic>);
              return PostCard(post: post);
            },
          );
        }
        if (widget.type == 'users') {
          return ListView.builder(
            itemCount: data.length,
            itemBuilder: (_, i) {
              final user = CommunityUser.fromJson(data[i] as Map<String, dynamic>);
              return ListTile(
                leading: CircleAvatar(
                  backgroundColor: const Color(0xFFEEF0FF),
                  backgroundImage: user.avatar != null ? CachedNetworkImageProvider(user.avatar!) : null,
                  child: user.avatar == null ? Text(user.name[0].toUpperCase()) : null,
                ),
                title: Row(children: [
                  Text(user.name, style: TextStyle(fontWeight: FontWeight.w600)),
                  if (user.isVerified) Icon(Icons.verified, size: 14, color: Color(0xFFFF6B35)),
                ]),
                subtitle: Text(user.username != null ? '@${user.username}' : '${user.followersCount} followers'),
                onTap: () => context.push('/community/profile/${user.id}'),
              );
            },
          );
        }
        return ListView.builder(
          itemCount: data.length,
          itemBuilder: (_, i) {
            final group = CommunityGroup.fromJson(data[i] as Map<String, dynamic>);
            return ListTile(
              leading: CircleAvatar(
                backgroundColor: const Color(0xFFEEF0FF),
                backgroundImage: group.avatar != null ? CachedNetworkImageProvider(group.avatar!) : null,
                child: group.avatar == null ? Icon(Icons.group, color: Color(0xFF140465)) : null,
              ),
              title: Text(group.name, style: TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text('${group.membersCount} members · ${group.category}'),
            );
          },
        );
      },
    );
  }
}
