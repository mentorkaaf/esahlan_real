import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:go_router/go_router.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../../core/theme/app_theme.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final _ctrl = PageController();
  int _page = 0;

  final _pages = const [
    _OnboardPage(
      emoji: '🍔',
      title: 'Order Food, Groceries & More',
      subtitle: 'From restaurants, supermarkets and local shops — delivered to your door in minutes.',
      color: Color(0xFFFF5722),
    ),
    _OnboardPage(
      emoji: '🏍️',
      title: 'Fast & Reliable Delivery',
      subtitle: 'Track your order live on the map. Our riders bring it straight to you.',
      color: Color(0xFF2196F3),
    ),
    _OnboardPage(
      emoji: '💳',
      title: 'Pay with EVC Plus or Wallet',
      subtitle: 'Multiple payment options including Waafi Pay, cash on delivery, and your eSahlan wallet.',
      color: Color(0xFF4CAF50),
    ),
  ];

  void _next() {
    if (_page < _pages.length - 1) {
      _ctrl.nextPage(duration: const Duration(milliseconds: 300), curve: Curves.ease);
    } else {
      _done();
    }
  }

  Future<void> _done() async {
    await LocalStorage.saveBool('onboarding_done', true);
    if (mounted) context.go('/auth/login');
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.topRight,
              child: TextButton(
                onPressed: _done,
                child: const Text('Skip', style: TextStyle(color: AppColors.textGrey)),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _ctrl,
                onPageChanged: (i) => setState(() => _page = i),
                itemCount: _pages.length,
                itemBuilder: (_, i) => _pages[i],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: Column(
                children: [
                  SmoothPageIndicator(
                    controller: _ctrl,
                    count: _pages.length,
                    effect: ExpandingDotsEffect(
                      dotColor: AppColors.divider,
                      activeDotColor: AppColors.primary,
                      dotHeight: 8,
                      dotWidth: 8,
                    ),
                  ),
                  const SizedBox(height: 32),
                  ElevatedButton(
                    onPressed: _next,
                    child: Text(_page == _pages.length - 1 ? 'Get Started' : 'Continue'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OnboardPage extends StatelessWidget {
  final String emoji;
  final String title;
  final String subtitle;
  final Color color;

  const _OnboardPage({required this.emoji, required this.title, required this.subtitle, required this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 160, height: 160,
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Center(child: Text(emoji, style: const TextStyle(fontSize: 72))),
          ),
          const SizedBox(height: 48),
          Text(title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: AppColors.textDark, height: 1.3),
          ),
          const SizedBox(height: 16),
          Text(subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 15, color: AppColors.textGrey, height: 1.6),
          ),
        ],
      ),
    );
  }
}
