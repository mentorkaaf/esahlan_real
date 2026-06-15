import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/screens/splash_screen.dart';
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
import '../../features/community/data/models/community_models.dart';

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

// Module screens
import '../../features/modules/efood/efood_screen.dart';
import '../../features/modules/eparcel/eparcel_screen.dart';
import '../../features/modules/erent/erent_screen.dart';
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

  final router = GoRouter(
    initialLocation: '/splash',
    refreshListenable: authNotifier,
    redirect: (context, state) {
      // Read (not watch) so we don't recreate the router on auth change.
      // GoRouter.refresh() triggers this callback instead.
      final authState = ref.read(authStateProvider);
      final isLoggedIn   = authState.valueOrNull != null;
      final isAuthRoute  = state.matchedLocation.startsWith('/auth');
      final isSplash     = state.matchedLocation == '/splash';
      final isOnboarding = state.matchedLocation == '/onboarding';

      if (isSplash || isOnboarding) return null;
      if (!isLoggedIn && !isAuthRoute) return '/auth/login';
      if (isLoggedIn  && isAuthRoute)  return '/home';
      return null;
    },
    routes: [
      // Splash & Onboarding
      GoRoute(path: '/splash',     builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/onboarding', builder: (_, __) => const OnboardingScreen()),

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
          GoRoute(path: '/community/notifications', builder: (_, __) => const CommunityNotificationsScreen()),
          GoRoute(path: '/community/explore', builder: (_, __) => const CommunityExploreScreen()),
          GoRoute(path: '/community/groups', builder: (_, __) => const GroupsScreen()),

          // Modules with bottom nav
          GoRoute(path: '/eparcel',    builder: (_, __) => const EParcelScreen()),
          GoRoute(path: '/erent',      builder: (_, __) => const ERentScreen()),
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

      // eFood — full-screen, no bottom nav
      GoRoute(path: '/efood', builder: (_, __) => const EFoodScreen()),

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

  return router;
});
