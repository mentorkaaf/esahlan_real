import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../api/api_client.dart' show bannedNotifier;

// Global Store screens
import '../../features/global/presentation/screens/global_home_screen.dart';
import '../../features/global/presentation/screens/global_auth_screen.dart';
import '../../features/global/presentation/screens/global_products_screen.dart';
import '../../features/global/presentation/screens/global_product_detail_screen.dart';
import '../../features/global/presentation/screens/global_cart_screen.dart';
import '../../features/global/presentation/screens/global_checkout_screen.dart';
import '../../features/global/presentation/screens/global_orders_screen.dart';

import '../../features/auth/presentation/screens/splash_screen.dart';
import '../../features/auth/presentation/screens/country_selection_screen.dart';
import '../../features/auth/presentation/screens/onboarding_screen.dart';
import '../../features/auth/presentation/screens/login_screen.dart';
import '../../features/auth/presentation/screens/register_screen.dart';
import '../../features/auth/presentation/screens/otp_screen.dart';
import '../../features/auth/presentation/providers/auth_provider.dart';
import '../../features/home/presentation/screens/main_shell.dart';
import '../../features/home/presentation/screens/home_screen.dart';
import '../../features/home/presentation/screens/vendors_screen.dart';
import '../../features/home/presentation/screens/vendor_detail_screen.dart';
import '../../features/cart/presentation/screens/cart_screen.dart';
import '../../features/orders/presentation/screens/orders_screen.dart';
import '../../features/orders/presentation/screens/order_detail_screen.dart';
import '../../features/orders/presentation/screens/order_tracking_screen.dart';
import '../../features/wallet/presentation/screens/wallet_screen.dart';
import '../../features/profile/presentation/screens/profile_screen.dart';

// Community screens
import '../../features/community/presentation/screens/community_shell.dart';
import '../../features/community/presentation/screens/community_screen.dart';
import '../../features/community/presentation/screens/community_profile_screen.dart';
import '../../features/community/presentation/screens/community_chat_list_screen.dart';
import '../../features/community/presentation/screens/community_chat_screen.dart';
import '../../features/community/presentation/screens/community_notifications_screen.dart';
import '../../features/community/presentation/screens/community_explore_screen.dart';
import '../../features/community/presentation/screens/groups_screen.dart';
import '../../features/community/presentation/screens/post_detail_screen.dart';
import '../../features/community/data/models/community_models.dart';
import '../../features/community/presentation/screens/follow_list_screen.dart';
import '../../features/community/presentation/screens/highlight_viewer_screen.dart';

// Calls screens
import '../../features/calls/presentation/screens/incoming_call_screen.dart';
import '../../features/calls/presentation/screens/active_call_screen.dart';
import '../../features/calls/data/models/call_models.dart';

// Live screens
import '../../features/live/presentation/screens/live_rooms_screen.dart';
import '../../features/live/presentation/screens/go_live_screen.dart';
import '../../features/live/presentation/screens/live_host_screen.dart';
import '../../features/live/presentation/screens/live_viewer_screen.dart';
import '../../features/live/presentation/screens/past_lives_screen.dart';
import '../../features/live/data/models/live_models.dart';
import '../../features/live/data/repositories/live_repository.dart';

// eLearning screens
import '../../features/elearning/presentation/screens/elearning_screen.dart';
import '../../features/elearning/presentation/screens/course_detail_screen.dart';
import '../../features/elearning/presentation/screens/my_learning_screen.dart';
import '../../features/elearning/presentation/screens/lesson_player_screen.dart';
import '../../features/elearning/presentation/screens/elearning_certificate_screen.dart';
import '../../features/elearning/presentation/screens/quiz_screen.dart';
import '../../features/elearning/presentation/screens/instructor_hub_screen.dart';
import '../../features/elearning/presentation/screens/instructor_apply_screen.dart';
import '../../features/elearning/presentation/screens/create_course_screen.dart';
import '../../features/elearning/presentation/screens/course_builder_screen.dart';

// Inbox screens
import '../../features/inbox/presentation/inbox_screen.dart';
import '../../features/inbox/presentation/inbox_broadcast_deep_link_screen.dart';
import '../../features/inbox/data/inbox_repository.dart';

// Module screens
import '../../features/modules/efood/efood_screen.dart';
import '../../features/modules/eparcel/eparcel_screen.dart';
import '../../features/modules/erent/erent_screen.dart';
import '../../features/modules/erent/request_detail_screen.dart';
import '../../features/modules/erent/erent_request_deep_link_screen.dart';
import '../../features/modules/emoving/emoving_screen.dart';
import '../../features/modules/edata/edata_screen.dart';
import '../../features/modules/eexchange/eexchange_screen.dart';
import '../../features/modules/eticket/eticket_screen.dart';
import '../../features/modules/egrocery/egrocery_screen.dart';
import '../../features/modules/ewholesale/ewholesale_screen.dart';
import '../../features/modules/elaundry/elaundry_screen.dart';
import '../../features/modules/ehealth/ehealth_screen.dart';
import '../../features/modules/eshop/eshop_screen.dart';
import '../../features/modules/eshop/product_list_screen.dart';
import '../../features/modules/eshop/product_detail_screen.dart';
import '../../features/modules/eshop/eshop_cart_screen.dart';
import '../../features/modules/eshop/eshop_checkout_screen.dart';
import '../../features/modules/eshop/eshop_stores_screen.dart';
import '../../features/modules/eshop/eshop_store_detail_screen.dart';
import '../../features/modules/eshop/eshop_popular_screen.dart';

// ── Auth change notifier ─────────────────────────────────────────────────────
// GoRouter listens to this so it re-evaluates redirect without recreating itself.
final authChangeNotifierProvider = Provider<_AuthChangeNotifier>((ref) {
  return _AuthChangeNotifier();
});

class _AuthChangeNotifier extends ChangeNotifier {
  void notify() => notifyListeners();
}

// ── Router ───────────────────────────────────────────────────────────────────
final routerProvider = Provider<GoRouter>((ref) {
  final authNotifier = ref.read(authChangeNotifierProvider);

  // On web: notify GoRouter whenever authStateProvider resolves so that
  // the redirect re-evaluates after a page refresh (avoids always-go-home).
  ref.listen(authStateProvider, (_, __) {
    try { authNotifier.notify(); } catch (_) {}
  });

  // Detect if running on global.esahlan.com → skip Somalia auth flow
  bool _isGlobalDomain() {
    if (!kIsWeb) return false;
    try {
      // ignore: undefined_prefixed_name
      final hostname = Uri.base.host;
      return hostname == 'global.esahlan.com' || hostname.startsWith('global.');
    } catch (_) {
      return false;
    }
  }

  final isGlobalDomain = _isGlobalDomain();

  final router = GoRouter(
    initialLocation: isGlobalDomain ? '/global' : '/splash',
    refreshListenable: authNotifier,
    redirect: (context, state) {
      // On global.esahlan.com — no Somalia auth required; /global routes are always accessible
      if (isGlobalDomain) {
        final loc = state.matchedLocation;
        if (!loc.startsWith('/global')) return '/global';
        return null;
      }

      // Read (not watch) so we don't recreate the router on auth change.
      // GoRouter.refresh() triggers this callback instead.
      final authState = ref.read(authStateProvider);
      final isLoggedIn   = authState.valueOrNull != null;
      final isLoading    = authState.isLoading;
      final isAuthRoute  = state.matchedLocation.startsWith('/auth');
      final isSplash     = state.matchedLocation == '/splash';
      final isOnboarding = state.matchedLocation == '/onboarding';

      final isCountrySelect = state.matchedLocation == '/country-select';
      if (isSplash || isOnboarding || isCountrySelect) return null;
      // /global routes are always accessible — no Somalia login required
      if (state.matchedLocation.startsWith('/global')) return null;
      // While auth is still loading, stay on the current route.
      // The ref.listen above will notify GoRouter once it resolves.
      if (isLoading) return null;
      if (!isLoggedIn && !isAuthRoute) return '/auth/login';
      if (isLoggedIn  && isAuthRoute)  return '/home';
      return null;
    },
    routes: [
      // Splash, Country Selection & Onboarding
      GoRoute(path: '/splash',         builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/country-select', builder: (_, __) => const CountrySelectionScreen()),
      GoRoute(path: '/onboarding',     builder: (_, __) => const OnboardingScreen()),

      // Auth routes
      GoRoute(path: '/auth/login',    builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/auth/register', builder: (_, __) => const RegisterScreen()),
      GoRoute(
        path: '/auth/otp',
        builder: (_, state) {
          final extra = state.extra as Map<String, String>?;
          return OtpScreen(
            phone:   extra?['phone']   ?? '',
            purpose: extra?['purpose'] ?? 'login',
          );
        },
      ),

      // Main Shell (Bottom Nav) — all modules except eFood
      ShellRoute(
        builder: (context, state, child) => MainShell(child: child),
        routes: [
          // Core tabs
          GoRoute(path: '/home',      builder: (_, __) => const HomeScreen()),
          GoRoute(path: '/orders',    builder: (_, __) => const OrdersScreen()),
          GoRoute(path: '/wallet',    builder: (_, __) => const WalletScreen()),
          GoRoute(path: '/community', builder: (_, __) => const CommunityShell()),
          GoRoute(path: '/chat',      builder: (_, __) => const InboxScreen()),
          GoRoute(
            path: '/inbox/broadcast/:uuid',
            builder: (_, state) => InboxBroadcastDeepLinkScreen(
              uuid: state.pathParameters['uuid']!,
              repo: InboxRepository.create(),
            ),
          ),
          GoRoute(path: '/profile',   builder: (_, __) => const ProfileScreen()),

          // Community sub-routes (inside shell so back nav works)
          GoRoute(
            path: '/community/profile/:userId',
            builder: (_, state) => CommunityProfileScreen(
              userId: int.parse(state.pathParameters['userId']!),
            ),
          ),
          GoRoute(path: '/community/chats',  builder: (_, __) => const CommunityChatListScreen()),
          GoRoute(
            path: '/community/chats/:chatId',
            builder: (_, state) {
              final chat = state.extra as CommunityChat;
              return CommunityChatScreen(chat: chat);
            },
          ),
          GoRoute(
            path: '/community/post/:postId',
            builder: (_, state) => PostDetailScreen(postId: int.parse(state.pathParameters['postId']!)),
          ),
          GoRoute(path: '/community/notifications', builder: (_, __) => const CommunityNotificationsScreen()),
          GoRoute(path: '/community/explore', builder: (_, __) => const CommunityExploreScreen()),
          GoRoute(path: '/community/groups', builder: (_, __) => const GroupsScreen()),
          GoRoute(
            path: '/community/follow-list',
            builder: (_, state) {
              final extra = state.extra as Map<String, dynamic>;
              return FollowListScreen(
                userId: extra['userId'] as int,
                type: extra['type'] as String,
              );
            },
          ),
          GoRoute(
            path: '/community/highlight-viewer',
            builder: (_, state) {
              final extra = state.extra as Map<String, dynamic>;
              return HighlightViewerScreen(
                highlight: extra['highlight'] as CommunityHighlight,
                initialIndex: (extra['initialIndex'] as int?) ?? 0,
              );
            },
          ),

          // Modules with bottom nav
          GoRoute(path: '/eparcel',    builder: (_, __) => const EParcelScreen()),
          GoRoute(path: '/erent',      builder: (_, __) => const ERentScreen()),
          GoRoute(
            path: '/erent/request/:id',
            builder: (_, state) => ERentRequestDeepLinkScreen(
              requestId: int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
              initialTab: state.uri.queryParameters['tab'],
            ),
          ),
          GoRoute(path: '/emoving',    builder: (_, __) => const EMovingScreen()),
          GoRoute(path: '/edata',      builder: (_, __) => const EDataScreen()),
          GoRoute(path: '/eexchange',  builder: (_, __) => const EExchangeScreen()),
          GoRoute(path: '/eticket',    builder: (_, __) => const ETicketScreen()),
          GoRoute(path: '/egrocery',   builder: (_, __) => const EGroceryScreen()),
          GoRoute(path: '/ewholesale', builder: (_, __) => const EWholesaleScreen()),
          GoRoute(path: '/elaundry',   builder: (_, __) => const ELaundryScreen()),
          GoRoute(path: '/ehealth',    builder: (_, __) => const EHealthScreen()),
          GoRoute(path: '/eshop',      builder: (_, __) => const EShopScreen()),
          GoRoute(
            path: '/eshop/products',
            builder: (_, state) {
              final p = state.uri.queryParameters;
              return ProductListScreen(
                categoryId:   p['category_id'] != null ? int.tryParse(p['category_id']!) : null,
                categoryName: p['category_name'],
                featured:     p['featured'] == '1',
              );
            },
          ),
          GoRoute(
            path: '/eshop/products/:id',
            builder: (_, state) => ProductDetailScreen(
                productId: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/eshop/cart',     builder: (_, __) => const EShopCartScreen()),
          GoRoute(path: '/eshop/checkout', builder: (_, __) => const EShopCheckoutScreen()),
          GoRoute(path: '/eshop/stores',   builder: (_, __) => const EShopStoresScreen()),
          GoRoute(
            path: '/eshop/stores/:id',
            builder: (_, state) => EShopStoreDetailScreen(
                storeId: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/eshop/popular',  builder: (_, __) => const EShopPopularScreen()),

          // eFood — moved inside shell so desktop sidebar stays visible
          GoRoute(path: '/efood', builder: (_, __) => const EFoodScreen()),

          // eLearning routes (inside shell for bottom nav)
          GoRoute(path: '/elearning', builder: (_, __) => const ELearningScreen()),
          GoRoute(
            path: '/elearning/course/:slug',
            builder: (_, state) => CourseDetailScreen(slug: state.pathParameters['slug']!),
          ),
          GoRoute(path: '/elearning/my-learning', builder: (_, __) => const MyLearningScreen()),
          GoRoute(
            path: '/elearning/lesson/:id',
            builder: (_, state) => LessonPlayerScreen(lessonId: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(
            path: '/elearning/certificate/:id',
            builder: (_, state) => ELearningCertificateScreen(certId: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(
            path: '/elearning/quiz/:id',
            builder: (_, state) => QuizScreen(quizId: int.parse(state.pathParameters['id']!)),
          ),
          // Instructor area
          GoRoute(path: '/elearning/instructor', builder: (_, __) => const InstructorHubScreen()),
          GoRoute(path: '/elearning/instructor/apply', builder: (_, __) => const InstructorApplyScreen()),
          GoRoute(path: '/elearning/instructor/create-course', builder: (_, __) => const CreateCourseScreen()),
          GoRoute(
            path: '/elearning/instructor/course-builder/:id',
            builder: (_, state) => CourseBuilderScreen(
              courseId: int.parse(state.pathParameters['id']!),
              courseTitle: state.extra as String?,
            ),
          ),
        ],
      ),

      // Calls — full-screen overlays, no bottom nav
      GoRoute(
        path: '/calls/incoming',
        builder: (_, state) => IncomingCallScreen(
          payload: state.extra as Map<String, dynamic>,
        ),
      ),
      GoRoute(
        path: '/calls/active',
        builder: (_, state) => ActiveCallScreen(
          session: state.extra as CallSession,
        ),
      ),

      // Live — full-screen, no bottom nav
      GoRoute(path: '/live', builder: (_, __) => const LiveRoomsScreen()),
      GoRoute(path: '/live/go', builder: (_, __) => const GoLiveScreen()),
      GoRoute(
        path: '/live/host',
        builder: (_, state) => LiveHostScreen(
          session: state.extra as LiveSession,
        ),
      ),
      GoRoute(
        path: '/live/view',
        builder: (_, state) {
          // Support both extra (direct) and query param (from notification deep link)
          if (state.extra is LiveRoom) {
            return LiveViewerScreen(room: state.extra as LiveRoom);
          }
          // Deep link: /live/view?room_id=123
          final roomId = int.tryParse(state.uri.queryParameters['room_id'] ?? '');
          return _LiveViewerLoader(roomId: roomId);
        },
      ),
      GoRoute(path: '/live/past', builder: (_, __) => const PastLivesScreen()),

      // Detail routes
      GoRoute(
        path: '/vendors/:module',
        builder: (_, state) =>
            VendorsScreen(moduleSlug: state.pathParameters['module']!),
      ),
      GoRoute(
        path: '/vendor/:id',
        builder: (_, state) =>
            VendorDetailScreen(vendorId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/cart', builder: (_, __) => const CartScreen()),
      GoRoute(
        path: '/orders/:id',
        builder: (_, state) =>
            OrderDetailScreen(orderId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/orders/:id/tracking',
        builder: (_, state) =>
            OrderTrackingScreen(orderId: int.parse(state.pathParameters['id']!)),
      ),

      // ── Global eCommerce Store ──────────────────────────────────────────────
      GoRoute(path: '/global', builder: (_, __) => const GlobalHomeScreen()),
      GoRoute(
        path: '/global/auth',
        builder: (_, state) => GlobalAuthScreen(
            isLogin: state.uri.queryParameters['mode'] != 'register'),
      ),
      GoRoute(
        path: '/global/products',
        builder: (_, state) => GlobalProductsScreen(
          categoryId: int.tryParse(
              state.uri.queryParameters['category_id'] ?? ''),
          query: state.uri.queryParameters['q'],
        ),
      ),
      GoRoute(
        path: '/global/product/:id',
        builder: (_, state) => GlobalProductDetailScreen(
            productId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/global/cart', builder: (_, __) => const GlobalCartScreen()),
      GoRoute(
          path: '/global/checkout',
          builder: (_, __) => const GlobalCheckoutScreen()),
      GoRoute(
          path: '/global/orders',
          builder: (_, __) => const GlobalOrdersScreen()),
      GoRoute(
        path: '/global/order/:id',
        builder: (_, state) => GlobalOrderDetailScreen(
            orderId: int.parse(state.pathParameters['id']!)),
      ),
    ],
    errorBuilder: (context, state) => Scaffold(
      body: Center(child: Text('Page not found: ${state.error}')),
    ),
  );

  // When authStateProvider changes, refresh the router redirect
  // WITHOUT recreating the GoRouter instance.
  ref.listen<AsyncValue>(authStateProvider, (_, __) {
    authNotifier.notify();
  });

  // When the server returns 403 account_banned, wipe auth state + redirect.
  bannedNotifier.addListener(() {
    final msg = bannedNotifier.value;
    if (msg == null) return;
    ref.invalidate(authStateProvider);
    authNotifier.notify();
    bannedNotifier.value = null; // reset so it doesn't re-fire
  });

  return router;
});

// Loads a LiveRoom by ID then opens LiveViewerScreen — used for notification deep links
class _LiveViewerLoader extends StatefulWidget {
  final int? roomId;
  const _LiveViewerLoader({this.roomId});

  @override
  State<_LiveViewerLoader> createState() => _LiveViewerLoaderState();
}

class _LiveViewerLoaderState extends State<_LiveViewerLoader> {
  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    if (widget.roomId == null) {
      if (mounted) Navigator.of(context).pop();
      return;
    }
    try {
      final session = await LiveRepository().joinRoom(widget.roomId!);
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => LiveViewerScreen(room: session.room)),
        );
      }
    } catch (_) {
      if (mounted) Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: Colors.black,
      body: Center(child: CircularProgressIndicator(color: Colors.orange)),
    );
  }
}
