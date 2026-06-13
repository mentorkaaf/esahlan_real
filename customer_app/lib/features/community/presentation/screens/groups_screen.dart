import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';

class GroupsScreen extends ConsumerStatefulWidget {
  const GroupsScreen({super.key});

  @override
  ConsumerState<GroupsScreen> createState() => _GroupsScreenState();
}

class _GroupsScreenState extends ConsumerState<GroupsScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final groupsAsync = ref.watch(communityGroupsProvider);

    return Scaffold(
      backgroundColor: kBg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1A1B2E)),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Groups',
            style: TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w800, fontSize: 20)),
        actions: [
          IconButton(icon: const Icon(Icons.search_rounded, color: Color(0xFF1A1B2E)), onPressed: () {}),
          IconButton(
            icon: const Icon(Icons.add_rounded, color: kOrange, size: 28),
            onPressed: () => _showCreateGroup(context),
          ),
        ],
        bottom: TabBar(
          controller: _tab,
          indicatorColor: kOrange,
          indicatorWeight: 2.5,
          labelColor: kOrange,
          unselectedLabelColor: const Color(0xFF9CA3AF),
          labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          tabs: const [
            Tab(text: 'Your Groups'),
            Tab(text: 'Discover'),
            Tab(text: 'Categories'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: [
          _GroupsList(groupsAsync: groupsAsync, myGroups: true),
          _GroupsList(groupsAsync: groupsAsync, myGroups: false),
          const _CategoriesTab(),
        ],
      ),
    );
  }

  void _showCreateGroup(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CreateGroupSheet(),
    );
  }
}

class _GroupsList extends ConsumerWidget {
  final AsyncValue<List<CommunityGroup>> groupsAsync;
  final bool myGroups;
  const _GroupsList({required this.groupsAsync, required this.myGroups});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return groupsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (e, _) => Center(child: Text('Error: $e', style: const TextStyle(color: Colors.red))),
      data: (groups) {
        if (groups.isEmpty) {
          return Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.group_rounded, size: 60, color: Color(0xFFD1D5DB)),
              const SizedBox(height: 14),
              const Text('No groups yet',
                  style: TextStyle(color: Color(0xFF1A1B2E), fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: kOrange,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                  minimumSize: const Size(double.infinity, 48),
                ),
                onPressed: () {},
                child: const Text('Create a Group', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              ),
            ]),
          );
        }
        return RefreshIndicator(
          color: kOrange,
          onRefresh: () => ref.read(communityGroupsProvider.notifier).load(),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: groups.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (ctx, i) => _GroupCard(group: groups[i]),
          ),
        );
      },
    );
  }
}

class _GroupCard extends ConsumerWidget {
  final CommunityGroup group;
  const _GroupCard({required this.group});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () => Navigator.push(
          context, MaterialPageRoute(builder: (_) => GroupDetailScreen(group: group))),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: Row(children: [
          // Cover image
          ClipRRect(
            borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(12), bottomLeft: Radius.circular(12)),
            child: group.coverPhoto != null
                ? CachedNetworkImage(
                    imageUrl: group.coverPhoto!,
                    width: 80, height: 80, fit: BoxFit.cover,
                  )
                : Container(
                    width: 80, height: 80,
                    color: kOrange.withOpacity(0.15),
                    child: const Icon(Icons.group_rounded, color: kOrange, size: 36),
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(
                    child: Text(group.name,
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1A1B2E))),
                  ),
                  if (group.privacy == 'private')
                    const Icon(Icons.lock_rounded, size: 14, color: Color(0xFF9CA3AF)),
                ]),
                const SizedBox(height: 3),
                Text('${_fmt(group.membersCount)} Members',
                    style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
                if (group.description != null && group.description!.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(group.description!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12)),
                ],
              ]),
            ),
          ),
          const SizedBox(width: 12),
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              onPressed: () async {
                try {
                  await ref.read(communityRepoProvider).joinGroup(group.id);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Joined group!'), backgroundColor: kOrange),
                  );
                } catch (_) {}
              },
              child: const Text('Join', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
            ),
          ),
        ]),
      ),
    );
  }

  String _fmt(int n) {
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }
}

class _CategoriesTab extends StatelessWidget {
  const _CategoriesTab();

  static const _cats = [
    {'icon': Icons.sports_soccer_rounded, 'label': 'Sports', 'color': Color(0xFF45BD62)},
    {'icon': Icons.restaurant_rounded, 'label': 'Food', 'color': Color(0xFFF97316)},
    {'icon': Icons.music_note_rounded, 'label': 'Music', 'color': Color(0xFF8B5CF6)},
    {'icon': Icons.school_rounded, 'label': 'Education', 'color': Color(0xFF1877F2)},
    {'icon': Icons.business_rounded, 'label': 'Business', 'color': Color(0xFFF59E0B)},
    {'icon': Icons.travel_explore_rounded, 'label': 'Travel', 'color': Color(0xFF06B6D4)},
  ];

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 1.6,
      ),
      itemCount: _cats.length,
      itemBuilder: (ctx, i) {
        final cat = _cats[i];
        return Container(
          decoration: BoxDecoration(
            color: (cat['color'] as Color).withOpacity(0.1),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: (cat['color'] as Color).withOpacity(0.2)),
          ),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(cat['icon'] as IconData, color: cat['color'] as Color, size: 36),
            const SizedBox(height: 8),
            Text(cat['label'] as String,
                style: TextStyle(
                  color: cat['color'] as Color,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                )),
          ]),
        );
      },
    );
  }
}

class GroupDetailScreen extends ConsumerWidget {
  final CommunityGroup group;
  const GroupDetailScreen({super.key, required this.group});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: kBg,
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            backgroundColor: kOrange,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
              onPressed: () => Navigator.pop(context),
            ),
            flexibleSpace: FlexibleSpaceBar(
              background: group.coverPhoto != null
                  ? CachedNetworkImage(imageUrl: group.coverPhoto!, fit: BoxFit.cover)
                  : Container(
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [kOrange, Color(0xFFFF8C42)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                      ),
                    ),
            ),
          ),
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(group.name,
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Color(0xFF1A1B2E))),
                const SizedBox(height: 4),
                Text('${group.membersCount} Members · ${group.privacy == 'public' ? 'Public' : 'Private'} Group',
                    style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
                if (group.description != null) ...[
                  const SizedBox(height: 10),
                  Text(group.description!, style: const TextStyle(color: Color(0xFF374151), fontSize: 14)),
                ],
                const SizedBox(height: 16),
                Row(children: [
                  Expanded(
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kOrange,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      onPressed: () async {
                        try {
                          await ref.read(communityRepoProvider).joinGroup(group.id);
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Joined!'), backgroundColor: kOrange),
                            );
                          }
                        } catch (_) {}
                      },
                      child: const Text('Join Group', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: kOrange,
                      side: const BorderSide(color: kOrange),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 20),
                    ),
                    onPressed: () {},
                    child: const Text('Share', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ]),
              ]),
            ),
          ),
          const SliverToBoxAdapter(child: SizedBox(height: 8)),
          // Group posts
          _GroupPostsSliver(groupId: group.id),
        ],
      ),
    );
  }
}

class _GroupPostsSliver extends ConsumerWidget {
  final int groupId;
  const _GroupPostsSliver({required this.groupId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final postsAsync = ref.watch(communityGroupPostsProvider(groupId));
    return postsAsync.when(
      loading: () => const SliverFillRemaining(
          child: Center(child: CircularProgressIndicator(color: kOrange))),
      error: (e, _) => SliverFillRemaining(child: Center(child: Text('$e'))),
      data: (posts) {
        if (posts.isEmpty) {
          return const SliverFillRemaining(
            child: Center(child: Text('No posts in this group yet',
                style: TextStyle(color: Color(0xFF9CA3AF)))),
          );
        }
        return SliverList(
          delegate: SliverChildBuilderDelegate(
            (ctx, i) => Container(
              margin: const EdgeInsets.only(bottom: 8),
              color: Colors.white,
              padding: const EdgeInsets.all(12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(posts[i].user.name,
                    style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF1A1B2E))),
                if (posts[i].content != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(posts[i].content!, style: const TextStyle(color: Color(0xFF374151), fontSize: 14)),
                  ),
              ]),
            ),
            childCount: posts.length,
          ),
        );
      },
    );
  }
}

class _CreateGroupSheet extends ConsumerStatefulWidget {
  @override
  ConsumerState<_CreateGroupSheet> createState() => _CreateGroupSheetState();
}

class _CreateGroupSheetState extends ConsumerState<_CreateGroupSheet> {
  final _nameCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  String _privacy = 'public';
  bool _loading = false;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _create() async {
    if (_nameCtrl.text.trim().isEmpty) return;
    setState(() => _loading = true);
    try {
      await ref.read(communityRepoProvider).createGroup(
        name: _nameCtrl.text.trim(),
        description: _descCtrl.text.trim().isEmpty ? null : _descCtrl.text.trim(),
        privacy: _privacy,
      );
      if (mounted) {
        Navigator.pop(context);
        ref.read(communityGroupsProvider.notifier).load();
      }
    } catch (_) {} finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 16, right: 16, top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Create Group',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF1A1B2E))),
        const SizedBox(height: 16),
        TextField(
          controller: _nameCtrl,
          decoration: InputDecoration(
            hintText: 'Group name',
            filled: true,
            fillColor: const Color(0xFFF0F2F5),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _descCtrl,
          maxLines: 2,
          decoration: InputDecoration(
            hintText: 'Description (optional)',
            filled: true,
            fillColor: const Color(0xFFF0F2F5),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
          ),
        ),
        const SizedBox(height: 10),
        Row(children: [
          const Text('Privacy: ', style: TextStyle(fontWeight: FontWeight.w600)),
          ChoiceChip(
            label: const Text('Public'),
            selected: _privacy == 'public',
            selectedColor: kOrange,
            onSelected: (_) => setState(() => _privacy = 'public'),
          ),
          const SizedBox(width: 8),
          ChoiceChip(
            label: const Text('Private'),
            selected: _privacy == 'private',
            selectedColor: kOrange,
            onSelected: (_) => setState(() => _privacy = 'private'),
          ),
        ]),
        const SizedBox(height: 16),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: kOrange,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            onPressed: _loading ? null : _create,
            child: _loading
                ? const SizedBox(width: 20, height: 20,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Text('Create', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          ),
        ),
      ]),
    );
  }
}
