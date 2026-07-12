import 'package:flutter/material.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
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
import '../services/video_pool.dart';
import 'dart:async';
import '../../../../core/services/realtime_client.dart';

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
  static const _kCachedReelUrls = 'video_pool_reel_urls_v1';
  static const _kCachedFeedUrls = 'feed_engine_cached_urls_v1';

  bool? _onboardingDone;
  DateTime? _backgroundedAt;
  bool _feedPreloaded = false;
  bool _reelsPreloaded = false;
  void Function(dynamic)? _inboxListener;
  void Function(dynamic)? _typingListener;
  String? _inboxChannel;
  final Map<int, Timer> _typingClearTimers = {};

  @override
  void initState() {
    super.initState();
    _checkOnboarding();
    _subscribeInbox();
    WidgetsBinding.instance.addObserver(this);
    // Start preloading from the last-seen URLs immediately — before the API
    // responds. On first open the list is empty (no-op). On every subsequent
    // open the user sees the first video playing before the API even responds.
    _warmPoolFromCache();
  }

  Future<void> _warmPoolFromCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final reelUrls = prefs.getStringList(_kCachedReelUrls) ?? [];
      final feedUrls = prefs.getStringList(_kCachedFeedUrls) ?? [];
      // Preload first URLs before the API responds — both pools in parallel.
      for (final u in reelUrls.take(2).where((u) => u.isNotEmpty)) {
        VideoPool.reels.preload(u);
      }
      for (final u in feedUrls.take(2).where((u) => u.isNotEmpty)) {
        VideoPool.feed.preload(u);
      }
    } catch (_) {}
  }

  Future<void> _saveReelUrlsToCache(List<String> urls) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setStringList(_kCachedReelUrls, urls.take(8).toList());
    } catch (_) {}
  }

  Future<void> _saveFeedUrlsToCache(List<String> urls) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setStringList(_kCachedFeedUrls, urls.take(8).toList());
    } catch (_) {}
  }

  void _silentPreloadFeed(List<CommunityPost> posts) {
    if (_feedPreloaded) return;
    _feedPreloaded = true;
    final urls = posts
        .expand((p) => p.media)
        .where((m) => m.type == 'video')
        .map((m) => m.mp4DirectUrl)
        .where((u) => u.isNotEmpty)
        .take(6)
        .toList();
    if (urls.isEmpty) return;
    for (final u in urls.take(2)) VideoPool.feed.preload(u);
    _saveFeedUrlsToCache(urls);
  }

  void _silentPreloadReels(List<CommunityPost> reels) {
    if (_reelsPreloaded) return;
    _reelsPreloaded = true;
    final urls = reels
        .map((r) => r.media.isNotEmpty ? r.media.first.mp4DirectUrl : '')
        .where((u) => u.isNotEmpty)
        .take(6)
        .toList();
    if (urls.isEmpty) return;
    VideoPool.reels.setWindow(urls, 0);
    _saveReelUrlsToCache(urls);
  }

  Future<void> _subscribeInbox() async {
    try {
      final me = await ref.read(communityMyProfileProvider.future);
      _inboxChannel = 'private-user.${me.id}';

      _inboxListener = (_) {
        if (mounted) ref.read(communityChatsProvider.notifier).load();
      };
      RealtimeClient.instance.listen(_inboxChannel!, 'chat.inbox_update', _inboxListener!);

      _typingListener = (data) {
        if (!mounted) return;
        final chatId = data['chat_id'] as int?;
        final isTyping = data['is_typing'] == true;
        if (chatId == null) return;
        ref.read(chatTypingProvider(chatId).notifier).state = isTyping;
        // Auto-clear after 5 s in case stop event is missed
        _typingClearTimers[chatId]?.cancel();
        if (isTyping) {
          _typingClearTimers[chatId] = Timer(const Duration(seconds: 5), () {
            if (mounted) ref.read(chatTypingProvider(chatId).notifier).state = false;
          });
        }
      };
      RealtimeClient.instance.listen(_inboxChannel!, 'chat.typing_update', _typingListener!);
    } catch (_) {}
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    for (final t in _typingClearTimers.values) t.cancel();
    if (_inboxChannel != null) {
      try {
        if (_inboxListener != null) RealtimeClient.instance.removeListener(_inboxChannel!, 'chat.inbox_update', _inboxListener!);
        if (_typingListener != null) RealtimeClient.instance.removeListener(_inboxChannel!, 'chat.typing_update', _typingListener!);
      } catch (_) {}
    }
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
      if (awayFor > AppConstants.presenceAwayThreshold) {
        _feedPreloaded = false; // allow re-preload for the refreshed feed
        ref.read(communityFeedProvider.notifier).load(refresh: true);
        // Do NOT refresh reels on resume — it resets the provider to loading,
        // which wipes _cachedItems in ReelsScreen and disposes all reel cards.
        // Reels are loaded once and survive background/resume correctly.
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

    // Silent background preload — fires once when feed/reels data first arrives.
    // VideoPool starts buffering the first few video URLs before the user
    // scrolls to them, so playback begins instantly on first view.
    ref.listen<AsyncValue<List<CommunityPost>>>(communityFeedProvider, (_, next) {
      next.whenData((posts) => _silentPreloadFeed(posts));
    });
    ref.listen<AsyncValue<List<CommunityPost>>>(communityReelsProvider, (_, next) {
      next.whenData((reels) => _silentPreloadReels(reels));
    });

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
            SizedBox(height: 2),
            Text(label,
                style: TextStyle(
                  color: active ? kOrange : kNavInactive,
                  fontSize: 10,
                  fontWeight: active ? FontWeight.w700 : FontWeight.w400,
                )),
            SizedBox(height: 2),
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
            decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
            child: Icon(Icons.add_rounded, color: Colors.white, size: 28),
          ),
        ),
      ),
    );
  }
}
