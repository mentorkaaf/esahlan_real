import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ── Country list ──────────────────────────────────────────────────────────────
// To add more countries: append a _Country entry here. isLocal=true → Somalia
// local app. isLocal=false → global store. 'OTHER' is the catch-all.
const _kCountries = [
  _Country(code: 'SO',    name: 'Somalia',              flag: '🇸🇴', isLocal: true),
  _Country(code: 'US',    name: 'United States',         flag: '🇺🇸', isLocal: false),
  _Country(code: 'GB',    name: 'United Kingdom',        flag: '🇬🇧', isLocal: false),
  _Country(code: 'OTHER', name: 'Other / International', flag: '🌍', isLocal: false),
];

// ── Storage key — permanent (survives app restarts, cleared on re-install) ────
const _kSelectedCountry = 'user_selected_country';

/// Returns the previously saved country code, or null on first launch.
Future<String?> getSavedCountrySelection() async {
  final prefs = await SharedPreferences.getInstance();
  return prefs.getString(_kSelectedCountry);
}

/// Saves the user's country choice. Pass null to clear (triggers selector again).
Future<void> saveCountrySelection(String? code) async {
  final prefs = await SharedPreferences.getInstance();
  if (code == null) {
    await prefs.remove(_kSelectedCountry);
  } else {
    await prefs.setString(_kSelectedCountry, code);
  }
}

// ── Screen ────────────────────────────────────────────────────────────────────

class CountrySelectionScreen extends StatefulWidget {
  const CountrySelectionScreen({super.key});

  @override
  State<CountrySelectionScreen> createState() => _CountrySelectionScreenState();
}

class _CountrySelectionScreenState extends State<CountrySelectionScreen>
    with SingleTickerProviderStateMixin {
  _Country? _selected;
  bool _loading = false;

  late final AnimationController _fadeCtrl;
  late final Animation<double> _fade;

  @override
  void initState() {
    super.initState();
    _fadeCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 450));
    _fade = CurvedAnimation(parent: _fadeCtrl, curve: Curves.easeOut);
    _fadeCtrl.forward();
  }

  @override
  void dispose() {
    _fadeCtrl.dispose();
    super.dispose();
  }

  Future<void> _confirm() async {
    if (_selected == null || _loading) return;
    setState(() => _loading = true);

    await saveCountrySelection(_selected!.code);
    if (!mounted) return;

    if (_selected!.isLocal) {
      context.go('/auth/login');   // Somalia → local login
    } else {
      context.go('/global');       // International → global store
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF07003B),
      body: FadeTransition(
        opacity: _fade,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 28),

                // ── Header ───────────────────────────────────────────────────
                const Text(
                  '👋 Welcome to eSahlan',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.3,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Select your country to get started',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.55),
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 40),

                // ── Country cards ─────────────────────────────────────────────
                Expanded(
                  child: ListView(
                    physics: const BouncingScrollPhysics(),
                    children: _kCountries
                        .map((c) => _CountryCard(
                              country: c,
                              selected: _selected == c,
                              onTap: () => setState(() => _selected = c),
                            ))
                        .toList(),
                  ),
                ),

                const SizedBox(height: 12),

                // ── Continue button ───────────────────────────────────────────
                AnimatedOpacity(
                  duration: const Duration(milliseconds: 200),
                  opacity: _selected != null ? 1.0 : 0.35,
                  child: SizedBox(
                    width: double.infinity,
                    height: 56,
                    child: ElevatedButton(
                      onPressed: _selected != null ? _confirm : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFFF6B35),
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: const Color(0xFFFF6B35),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                        elevation: 0,
                      ),
                      child: _loading
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2.5,
                              ),
                            )
                          : const Text(
                              'Continue',
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.3,
                              ),
                            ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ── Card widget ───────────────────────────────────────────────────────────────

class _CountryCard extends StatelessWidget {
  final _Country country;
  final bool selected;
  final VoidCallback onTap;

  const _CountryCard({
    required this.country,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        decoration: BoxDecoration(
          color: selected
              ? const Color(0xFFFF6B35).withValues(alpha: 0.12)
              : Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: selected
                ? const Color(0xFFFF6B35)
                : Colors.white.withValues(alpha: 0.1),
            width: selected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Text(country.flag, style: const TextStyle(fontSize: 34)),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    country.name,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    country.isLocal
                        ? 'Local services · Somali'
                        : 'Global store · English',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.45),
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: 22,
              height: 22,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: selected ? const Color(0xFFFF6B35) : Colors.transparent,
                border: Border.all(
                  color: selected
                      ? const Color(0xFFFF6B35)
                      : Colors.white.withValues(alpha: 0.3),
                  width: 2,
                ),
              ),
              child: selected
                  ? const Icon(Icons.check, color: Colors.white, size: 14)
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}

// ── Model ─────────────────────────────────────────────────────────────────────

class _Country {
  final String code;
  final String name;
  final String flag;
  final bool isLocal;

  const _Country({
    required this.code,
    required this.name,
    required this.flag,
    required this.isLocal,
  });
}
