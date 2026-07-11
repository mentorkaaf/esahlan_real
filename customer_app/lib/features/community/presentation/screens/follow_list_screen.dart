import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart' show kOrange;

class FollowListScreen extends ConsumerStatefulWidget {
  final int userId;
  final String type; // 'followers' or 'following'
  const FollowListScreen({super.key, required this.userId, required this.type});

  @override
  ConsumerState<FollowListScreen> createState() => _FollowListScreenState();
}

class _FollowListScreenState extends ConsumerState<FollowListScreen> {
  List<CommunityUser>? _users;
  bool _loading = true;
  String? _error;
  final Map<int, bool> _followLoading = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final repo = CommunityRepository();
      final users = widget.type == 'followers'
          ? await repo.getFollowers(widget.userId)
          : await repo.getFollowing(widget.userId);
      if (mounted) setState(() { _users = users; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _toggleFollow(CommunityUser user) async {
    if (_followLoading[user.id] == true) return;
    setState(() => _followLoading[user.id] = true);
    try {
      await ref.read(communityRepoProvider).toggleFollow(user.id);
      // Refresh list
      await _load();
    } catch (_) {
    } finally {
      if (mounted) setState(() => _followLoading[user.id] = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.type == 'followers' ? 'Followers' : 'Following';

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: kOrange))
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: Colors.red)))
              : _users == null || _users!.isEmpty
                  ? Center(child: Text('No $title yet', style: const TextStyle(color: Colors.grey)))
                  : ListView.builder(
                      itemCount: _users!.length,
                      itemBuilder: (context, i) {
                        final user = _users![i];
                        return _UserTile(
                          user: user,
                          followLoading: _followLoading[user.id] == true,
                          onTap: () => context.push('/community/profile/${user.id}', extra: user),
                          onFollowTap: () => _toggleFollow(user),
                        );
                      },
                    ),
    );
  }
}

class _UserTile extends StatelessWidget {
  final CommunityUser user;
  final bool followLoading;
  final VoidCallback onTap;
  final VoidCallback onFollowTap;

  const _UserTile({
    required this.user,
    required this.followLoading,
    required this.onTap,
    required this.onFollowTap,
  });

  @override
  Widget build(BuildContext context) {
    final isFollowing = user.isFollowing;
    final isRequested = user.isRequested;

    return ListTile(
      onTap: onTap,
      leading: CircleNetImage(url: user.avatar, size: 44, fallbackText: user.name),
      title: Text(user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
      subtitle: user.username != null
          ? Text('@${user.username}', style: const TextStyle(fontSize: 12, color: Colors.grey))
          : null,
      trailing: GestureDetector(
        onTap: followLoading ? null : onFollowTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
          decoration: BoxDecoration(
            color: isFollowing ? Colors.transparent : kOrange,
            border: Border.all(
              color: isFollowing ? Colors.grey : kOrange,
              width: 1.5,
            ),
            borderRadius: BorderRadius.circular(8),
          ),
          child: followLoading
              ? const SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(color: kOrange, strokeWidth: 2),
                )
              : Text(
                  isFollowing ? 'Following' : (isRequested ? 'Requested' : 'Follow'),
                  style: TextStyle(
                    color: isFollowing ? Colors.grey : Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
        ),
      ),
    );
  }
}
