import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/community_provider.dart';
import 'community_feed_screen.dart';
import 'reels_screen.dart';
import 'community_chat_list_screen.dart';
import 'community_profile_screen.dart';
import 'create_post_screen.dart';
import 'community_onboarding_screen.dart';
import '../../data/repositories/community_repository.dart';
import '../../data/models/community_models.dart';
import '../widgets/upload_progress_banner.dart';
import '../services/background_upload_service.dart';

final communityNavIndexProvider = StateProvider<int>((ref) => 0);

// ── eSahlan brand palette (shared across the whole community module) ──────────
const kOrange = Color(0xFFFF8A00);     // brand orange
const kNavy = Color(0xFF140465);       // brand navy (cards/accents)
const kNavBg = Color(0xFF140465);      // bottom-nav background = brand navy
const kNavInactive = Color(0xFF9AA0C2);
const kBg = Color(0xFFF0F2F5);         // Facebook-style page background

class CommunityShell extends ConsumerStatefulWidget {
  const CommunityShell({super.key});
  @override
  ConsumerState<CommunityShell> createState() => _CommunityShellState();
}

class _CommunityShellState extends ConsumerState<CommunityShell> with WidgetsBindingObserver {
  bool? _onboardingDone;
  DateTime? _backgroundedAt;

  @override
  void initState() {
    super.initState();
    _checkOnboarding();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Android "back"/home doesn't kill the process — it just backgrounds it,
    // so Riverpod provider state (already-loaded feed/reels) survives
    // untouched. Returning to the app resumes that exact in-memory state with
    // no new network call, which looked like "the feed never changes even
    // after closing and reopening the app". Match Facebook/Instagram-style
    // behavior instead: if the app was away for a while, refresh on resume.
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _backgroundedAt ??= DateTime.now();
    } else if (state == AppLifecycleState.resumed) {
      final awayFor = _backgroundedAt == null ? Duration.zero : DateTime.now().difference(_backgroundedAt!);
      _backgroundedAt = null;
      if (awayFor > const Duration(minutes: 2)) {
        ref.read(communityFeedProvider.notifier).load(refresh: true);
        ref.read(communityReelsProvider.notifier).load(refresh: true);
        ref.invalidate(communityStoriesProvider);
      }
    }
  }

  void _checkOnboarding() async {
    try {
      final done = await ref.read(communityRepoProvider).checkOnboarding();
      if (mounted) setState(() => _onboardingDone = done);
    } catch (_) {
      if (mounted) setState(() => _onboardingDone = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_onboardingDone == null) return const Scaffold(body: Center(child: CircularProgressIndicator(color: kOrange)));

    if (_onboardingDone == false) {
      return CommunityOnboardingScreen(onComplete: () => setState(() => _onboardingDone = true));
    }

    final idx = ref.watch(communityNavIndexProvider);

    // Listen for background upload success → pin post to feed top
    ref.listen<UploadState>(backgroundUploadProvider, (prev, next) {
      if (prev?.status != UploadStatus.success && next.status == UploadStatus.success && next.post != null) {
        ref.read(communityFeedProvider.notifier).prependPost(next.post!);
        ref.read(communityNavIndexProvider.notifier).state = 0;
      }
    });

    return Scaffold(
      body: Column(children: [
        const UploadProgressBanner(),
        Expanded(child: IndexedStack(
          index: idx,
          children: const [
            CommunityFeedScreen(),
            ReelsScreen(),
            CommunityChatListScreen(),
            CommunityMyProfileScreen(),
          ],
        )),
      ]),
      bottomNavigationBar: _CommunityNavBar(
        currentIndex: idx,
        onTap: (i) {
          if (i == 2) {
            _openCreatePost(context, ref);
            return;
          }
          final mapped = i > 2 ? i - 1 : i;
          if (mapped == 0 && idx == 0) {
            CommunityFeedScreen.scrollToTop();
            ref.read(communityFeedProvider.notifier).load(refresh: true);
          }
          ref.read(communityNavIndexProvider.notifier).state = mapped;
        },
      ),
    );
  }

  static void _openCreatePost(BuildContext context, WidgetRef ref) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const CreatePostScreen()),
    );
    if (result is CommunityPost) {
      ref.read(communityFeedProvider.notifier).prependPost(result);
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
