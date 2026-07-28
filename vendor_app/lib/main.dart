import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/services/auth_service.dart';
import 'core/services/fcm_service.dart';
import 'core/theme/vc.dart';
import 'features/auth/login_screen.dart';
import 'features/shell/main_shell.dart';

final _navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
  ));

  // Set navigator key before init so notification taps can route
  VendorFcmService.navigatorKey = _navigatorKey;

  try { await VendorFcmService.init(); } catch (_) {}

  runApp(const ProviderScope(child: VendorApp()));
}

class VendorApp extends ConsumerWidget {
  const VendorApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeModeProvider);
    return MaterialApp(
      title: 'eSahlan Vendor',
      debugShowCheckedModeBanner: false,
      navigatorKey: _navigatorKey,
      theme: VC.light(),
      darkTheme: VC.dark(),
      themeMode: themeMode,
      home: const _AuthGate(),
      routes: {
        '/orders': (_) => const MainShell(initialIndex: 1),
        '/wallet': (_) => const MainShell(initialIndex: 4),
      },
    );
  }
}

class _AuthGate extends StatefulWidget {
  const _AuthGate();
  @override
  State<_AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<_AuthGate> {
  bool _checked = false;
  bool _loggedIn = false;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    final ok = await AuthService.instance.isLoggedIn();
    if (mounted) setState(() { _loggedIn = ok; _checked = true; });
  }

  @override
  Widget build(BuildContext context) {
    if (!_checked) {
      return Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        body: Center(
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Container(width: 72, height: 72,
              decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(20)),
              child: const Icon(Icons.storefront_rounded, color: VC.orange, size: 40)),
            const SizedBox(height: 16),
            Text('eSahlan Vendor', style: TextStyle(
              color: Theme.of(context).colorScheme.onSurface,
              fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
          ]),
        ),
      );
    }
    return _loggedIn ? const MainShell() : const LoginScreen();
  }
}
