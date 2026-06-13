import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/community_provider.dart';
import 'community_feed_screen.dart';
import 'reels_screen.dart';
import 'community_chat_list_screen.dart';
import 'community_profile_screen.dart';
import 'create_post_screen.dart';

final communityNavIndexProvider = StateProvider<int>((ref) => 0);

const kOrange = Color(0xFFF97316);
const kNavBg = Color(0xFF1A1B2E);
const kNavInactive = Color(0xFF8A8D91);
const kBg = Color(0xFFF0F2F5);

class CommunityShell extends ConsumerWidget {
  const CommunityShell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final idx = ref.watch(communityNavIndexProvider);

    return Scaffold(
      backgroundColor: kBg,
      body: IndexedStack(
        index: idx,
        children: const [
          CommunityFeedScreen(),
          ReelsScreen(),
          CommunityChatListScreen(),
          CommunityMyProfileScreen(),
        ],
      ),
      bottomNavigationBar: _CommunityNavBar(
        currentIndex: idx,
        onTap: (i) {
          if (i == 2) {
            _openCreatePost(context, ref);
            return;
          }
          final mapped = i > 2 ? i - 1 : i;
          ref.read(communityNavIndexProvider.notifier).state = mapped;
        },
      ),
    );
  }

  static void _openCreatePost(BuildContext context, WidgetRef ref) async {
    final post = await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const CreatePostScreen()),
    );
    if (post != null) {
      ref.read(communityFeedProvider.notifier).prependPost(post);
    }
  }
}

class _CommunityNavBar extends StatelessWidget {
  final int currentIndex;
  final void Function(int) onTap;
  const _CommunityNavBar({required this.currentIndex, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).padding.bottom;
    // map internal idx to nav slot: 0→0, 1→1, skip 2(+), 2→3, 3→4
    final navSlot = currentIndex > 1 ? currentIndex + 1 : currentIndex;

    return Container(
      color: kNavBg,
      padding: EdgeInsets.only(bottom: bottom),
      child: SizedBox(
        height: 62,
        child: Row(
          children: [
            _NavItem(icon: Icons.home_rounded, label: 'Home', active: navSlot == 0, onTap: () => onTap(0)),
            _NavItem(icon: Icons.play_circle_fill_rounded, label: 'Reels', active: navSlot == 1, onTap: () => onTap(1)),
            _CenterAddBtn(onTap: () => onTap(2)),
            _NavItem(icon: Icons.chat_bubble_rounded, label: 'Chat', active: navSlot == 3, onTap: () => onTap(3)),
            _NavItem(icon: Icons.person_rounded, label: 'Profile', active: navSlot == 4, onTap: () => onTap(4)),
          ],
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;
  const _NavItem({required this.icon, required this.label, required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: active ? kOrange : kNavInactive, size: 24),
            const SizedBox(height: 2),
            Text(label,
                style: TextStyle(
                  color: active ? kOrange : kNavInactive,
                  fontSize: 10,
                  fontWeight: active ? FontWeight.w700 : FontWeight.w400,
                )),
            const SizedBox(height: 2),
            Container(
              width: 4, height: 4,
              decoration: BoxDecoration(
                color: active ? kOrange : Colors.transparent,
                shape: BoxShape.circle,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CenterAddBtn extends StatelessWidget {
  final VoidCallback onTap;
  const _CenterAddBtn({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Center(
          child: Container(
            width: 46,
            height: 46,
            decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
            child: const Icon(Icons.add_rounded, color: Colors.white, size: 28),
          ),
        ),
      ),
    );
  }
}
