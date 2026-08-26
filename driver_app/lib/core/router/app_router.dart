import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../features/auth/presentation/providers/auth_provider.dart';
import '../../features/auth/presentation/screens/login_screen.dart';
import '../../features/auth/presentation/screens/register_screen.dart';
import '../../features/auth/presentation/screens/pending_screen.dart';
import '../../features/dashboard/presentation/screens/dashboard_screen.dart';
import '../../features/orders/presentation/screens/orders_screen.dart';
import '../../features/orders/presentation/screens/incoming_order_screen.dart';
import '../../features/earnings/presentation/screens/earnings_screen.dart';
import '../../features/wallet/presentation/screens/wallet_screen.dart';
import '../../features/profile/presentation/screens/profile_screen.dart';
import '../../shell/main_shell.dart';

class _AuthNotifier extends ChangeNotifier {
  void notify() => notifyListeners();
}

final _authNotifierProvider = Provider((_) => _AuthNotifier());

final routerProvider = Provider<GoRouter>((ref) {
  final notifier = ref.read(_authNotifierProvider);

  final router = GoRouter(
    initialLocation: '/login',
    refreshListenable: notifier,
    redirect: (context, state) {
      final auth = ref.read(authStateProvider).valueOrNull;
      final loggedIn = auth?.loggedIn ?? false;
      final approved = auth?.approved ?? false;
      final loc = state.matchedLocation;
      final isAuth = loc == '/login' || loc == '/register';

      if (!loggedIn && !isAuth) return '/login';
      if (loggedIn && !approved && loc != '/pending') return '/pending';
      if (loggedIn && approved && (isAuth || loc == '/pending')) return '/dashboard';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, __) => const RegisterScreen()),
      GoRoute(path: '/pending', builder: (_, __) => const PendingScreen()),
      // Full-screen incoming order — no shell, shown over everything
      GoRoute(
        path: '/incoming-order',
        builder: (context, state) {
          final order = state.extra as Map<String, dynamic>? ?? {};
          return IncomingOrderScreen(order: order);
        },
      ),
      ShellRoute(
        builder: (_, __, child) => MainShell(child: child),
        routes: [
          GoRoute(path: '/dashboard', builder: (_, __) => const DashboardScreen()),
          GoRoute(path: '/orders', builder: (_, __) => const OrdersScreen()),
          GoRoute(path: '/earnings', builder: (_, __) => const EarningsScreen()),
          GoRoute(path: '/wallet', builder: (_, __) => const WalletScreen()),
          GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
        ],
      ),
    ],
  );

  ref.listen(authStateProvider, (_, __) => notifier.notify());
  return router;
});
