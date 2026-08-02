import 'dart:math' as math;
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../providers/community_provider.dart';
import 'community_chat_screen.dart';
import 'community_shell.dart' show kOrange;

// ─── Providers ────────────────────────────────────────────────────────────────

final _filterProvider = StateProvider<_EMarryFilter>((_) => const _EMarryFilter());

class _EMarryFilter {
  final int minAge;
  final int maxAge;
  final String? lookingFor;
  const _EMarryFilter({this.minAge = 18, this.maxAge = 60, this.lookingFor});
}

final _discoverProvider = StateNotifierProvider.autoDispose<_DiscoverNotifier, _DiscoverState>((ref) {
  final f = ref.watch(_filterProvider);
  return _DiscoverNotifier(f);
});

class _DiscoverState {
  final List<Map<String, dynamic>> cards;
  final bool loading;
  final bool exhausted;
  final bool hasError;
  final Map<String, dynamic>? matchProfile;
  const _DiscoverState({this.cards = const [], this.loading = false, this.exhausted = false, this.hasError = false, this.matchProfile});
  _DiscoverState copyWith({List<Map<String, dynamic>>? cards, bool? loading, bool? exhausted, bool? hasError, Map<String, dynamic>? matchProfile}) =>
      _DiscoverState(cards: cards ?? this.cards, loading: loading ?? this.loading, exhausted: exhausted ?? this.exhausted, hasError: hasError ?? this.hasError, matchProfile: matchProfile ?? this.matchProfile);
}

class _DiscoverNotifier extends StateNotifier<_DiscoverState> {
  final _EMarryFilter filter;
  int _page = 1;
  _DiscoverNotifier(this.filter) : super(const _DiscoverState()) {
    _load();
  }

  Future<void> _load() async {
    if (state.loading || state.exhausted) return;
    state = state.copyWith(loading: true, hasError: false);
    try {
      final res = await ApiClient.instance.get('/emarry/profiles', queryParameters: {
        'page': _page,
        'min_age': filter.minAge,
        'max_age': filter.maxAge,
        if (filter.lookingFor != null) 'gender': filter.lookingFor,
      });
      final items = (res.data['data']['data'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map)).toList();
      _page++;
      state = state.copyWith(
        cards: [...state.cards, ...items],
        loading: false,
        exhausted: items.isEmpty,
      );
    } catch (_) {
      state = state.copyWith(loading: false, hasError: true);
    }
  }

  Future<bool> like(Map<String, dynamic> profile) async {
    final uid = _uid(profile);
    _removeTop();
    if (state.cards.length < 3) _load();
    if (uid == null) return false;
    try {
      final res = await ApiClient.instance.post('/emarry/interest/$uid');
      final isMatch = res.data['is_match'] == true;
      if (isMatch) state = state.copyWith(matchProfile: profile);
      return isMatch;
    } catch (_) { return false; }
  }

  Future<void> pass(Map<String, dynamic> profile) async {
    final uid = _uid(profile);
    _removeTop();
    if (state.cards.length < 3) _load();
    if (uid == null) return;
    try { await ApiClient.instance.post('/emarry/pass/$uid'); } catch (_) {}
  }

  void clearMatch() {
    state = state.copyWith(matchProfile: null);
  }

  void _removeTop() {
    if (state.cards.isEmpty) return;
    state = state.copyWith(cards: state.cards.sublist(1));
  }

  int? _uid(Map p) {
    final v = p['user_id'];
    return v is int ? v : int.tryParse(v?.toString() ?? '');
  }
}

final _matchesProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final res = await ApiClient.instance.get('/emarry/matches');
  return (res.data['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
});

final _receivedProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final res = await ApiClient.instance.get('/emarry/interests/received');
  return (res.data['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
});

final _sentProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final res = await ApiClient.instance.get('/emarry/interests/sent');
  return (res.data['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
});

final _myEmarryProfileProvider = FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final res = await ApiClient.instance.get('/emarry/profile/me');
  final d = res.data['data'];
  if (d == null) return null;
  return Map<String, dynamic>.from(d as Map);
});

// ─── Main Screen ──────────────────────────────────────────────────────────────

class EMarryScreen extends ConsumerStatefulWidget {
  const EMarryScreen({super.key});
  @override
  ConsumerState<EMarryScreen> createState() => _EMarryScreenState();
}

class _EMarryScreenState extends ConsumerState<EMarryScreen> with SingleTickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 4, vsync: this);
    _tab.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      _TopBar(tab: _tab),
      Expanded(child: TabBarView(
        controller: _tab,
        physics: const NeverScrollableScrollPhysics(),
        children: const [
          _DiscoverTab(),
          _MatchesTab(),
          _LikesTab(),
          _MyProfileTab(),
        ],
      )),
    ]);
  }
}

// ─── Top Bar ──────────────────────────────────────────────────────────────────

class _TopBar extends StatelessWidget {
  final TabController tab;
  const _TopBar({required this.tab});

  @override
  Widget build(BuildContext context) {
    final labels = ['Discover', 'Matches', 'Likes', 'Profile'];
    final icons  = [Icons.explore_rounded, Icons.favorite_rounded, Icons.favorite_border_rounded, Icons.person_rounded];
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB))),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Column(children: [
            Row(children: [
              RichText(text: const TextSpan(children: [
                TextSpan(text: 'e', style: TextStyle(color: kOrange, fontWeight: FontWeight.w900, fontSize: 22)),
                TextSpan(text: 'Marry', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.w900, fontSize: 22)),
              ])),
              const Spacer(),
              if (tab.index == 0)
                Consumer(builder: (ctx, ref, _) => IconButton(
                  icon: const Icon(Icons.tune_rounded, color: Color(0xFF475569)),
                  onPressed: () => _FilterSheet.show(ctx, ref),
                )),
            ]),
            const SizedBox(height: 8),
            Row(children: List.generate(4, (i) {
              final sel = tab.index == i;
              return Expanded(child: GestureDetector(
                onTap: () => tab.animateTo(i),
                child: Column(children: [
                  Icon(icons[i], color: sel ? kOrange : const Color(0xFF9CA3AF), size: 22),
                  const SizedBox(height: 4),
                  Text(labels[i], style: TextStyle(
                    fontSize: 11, fontWeight: sel ? FontWeight.w700 : FontWeight.w500,
                    color: sel ? kOrange : const Color(0xFF9CA3AF),
                  )),
                  const SizedBox(height: 6),
                  Container(height: 2, color: sel ? kOrange : Colors.transparent),
                ]),
              ));
            })),
          ]),
        ),
      ),
    );
  }
}

// ─── Discover Tab ─────────────────────────────────────────────────────────────

class _DiscoverTab extends ConsumerWidget {
  const _DiscoverTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(_discoverProvider);

    return Stack(children: [
      // Card stack
      if (state.loading && state.cards.isEmpty)
        const Center(child: CircularProgressIndicator(color: kOrange))
      else if (!state.loading && state.cards.isEmpty)
        _EmptyDiscover(onRefresh: () => ref.refresh(_discoverProvider))
      else
        _SwipeStack(profiles: state.cards),

      // Match celebration overlay
      if (state.matchProfile != null)
        _MatchCelebration(
          matchProfile: state.matchProfile!,
          onDismiss: () {
            ref.read(_discoverProvider.notifier).clearMatch();
            ref.invalidate(_matchesProvider);
          },
        ),
    ]);
  }
}

// ─── Swipe Stack ──────────────────────────────────────────────────────────────

class _SwipeStack extends ConsumerStatefulWidget {
  final List<Map<String, dynamic>> profiles;
  const _SwipeStack({required this.profiles});

  @override
  ConsumerState<_SwipeStack> createState() => _SwipeStackState();
}

class _SwipeStackState extends ConsumerState<_SwipeStack> {
  final GlobalKey<_SwipeCardState> _frontKey = GlobalKey();

  void _triggerLike()  => _frontKey.currentState?.animateLike();
  void _triggerPass()  => _frontKey.currentState?.animatePass();
  void _triggerInfo()  {
    if (widget.profiles.isEmpty) return;
    _ProfileDetailSheet.show(context, widget.profiles.first);
  }

  @override
  Widget build(BuildContext context) {
    final profiles = widget.profiles.take(3).toList();
    if (profiles.isEmpty) return const SizedBox.shrink();

    return Column(children: [
      Expanded(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Stack(alignment: Alignment.bottomCenter, children: [
            // Card 3 (back)
            if (profiles.length >= 3)
              Positioned.fill(child: Transform.scale(scale: 0.92,
                child: Transform.translate(offset: const Offset(0, 16),
                  child: _StaticCard(profile: profiles[2])))),
            // Card 2 (middle)
            if (profiles.length >= 2)
              Positioned.fill(child: Transform.scale(scale: 0.96,
                child: Transform.translate(offset: const Offset(0, 8),
                  child: _StaticCard(profile: profiles[1])))),
            // Card 1 (front — interactive)
            Positioned.fill(child: _SwipeCard(
              key: _frontKey,
              profile: profiles[0],
              onLike: () async {
                final notifier = ref.read(_discoverProvider.notifier);
                await notifier.like(profiles[0]);
              },
              onPass: () => ref.read(_discoverProvider.notifier).pass(profiles[0]),
            )),
          ]),
        ),
      ),
      // Action buttons
      Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
          _ActionButton(icon: Icons.close_rounded, color: const Color(0xFFEF4444), size: 56, onTap: _triggerPass),
          _ActionButton(icon: Icons.info_outline_rounded, color: const Color(0xFF64748B), size: 44, onTap: _triggerInfo),
          _ActionButton(icon: Icons.favorite_rounded, color: kOrange, size: 56, onTap: _triggerLike),
        ]),
      ),
    ]);
  }
}

// ─── Swipe Card ───────────────────────────────────────────────────────────────

class _SwipeCard extends StatefulWidget {
  final Map<String, dynamic> profile;
  final VoidCallback onLike;
  final VoidCallback onPass;
  const _SwipeCard({super.key, required this.profile, required this.onLike, required this.onPass});

  @override
  State<_SwipeCard> createState() => _SwipeCardState();
}

class _SwipeCardState extends State<_SwipeCard> with SingleTickerProviderStateMixin {
  Offset _offset = Offset.zero;
  bool _dragging = false;
  late final AnimationController _anim;
  late Animation<Offset> _flyAnim;
  bool _flying = false;
  int _photoIndex = 0;

  @override
  void initState() {
    super.initState();
    _anim = AnimationController(vsync: this, duration: const Duration(milliseconds: 300));
    _anim.addListener(() {
      if (_flying) setState(() => _offset = _flyAnim.value);
    });
    _anim.addStatusListener((s) {
      if (s == AnimationStatus.completed && _flying) {
        _flying = false;
        if (_offset.dx > 0) widget.onLike(); else widget.onPass();
      }
    });
  }

  @override
  void dispose() { _anim.dispose(); super.dispose(); }

  void animateLike() => _flyOff(const Offset(800, -100));
  void animatePass() => _flyOff(const Offset(-800, -100));

  void _flyOff(Offset target) {
    _flying = true;
    _flyAnim = Tween(begin: _offset, end: target).animate(CurvedAnimation(parent: _anim, curve: Curves.easeIn));
    _anim.forward(from: 0);
  }

  void _onPanStart(DragStartDetails _) => setState(() => _dragging = true);

  void _onPanUpdate(DragUpdateDetails d) {
    setState(() => _offset += d.delta);
  }

  void _onPanEnd(DragEndDetails d) {
    setState(() => _dragging = false);
    if (_offset.dx > 90) {
      animateLike();
    } else if (_offset.dx < -90) {
      animatePass();
    } else {
      _anim.reverse(from: 1);
      _flyAnim = Tween(begin: _offset, end: Offset.zero).animate(CurvedAnimation(parent: _anim, curve: Curves.elasticOut));
      _flying = true;
      _anim.forward(from: 0).then((_) { _flying = false; setState(() => _offset = Offset.zero); });
    }
  }

  @override
  Widget build(BuildContext context) {
    final angle = _offset.dx / 300 * 0.25;
    final likeOpacity = (_offset.dx / 80).clamp(0.0, 1.0);
    final nopeOpacity = (-_offset.dx / 80).clamp(0.0, 1.0);

    final photos = (widget.profile['photos'] as List? ?? []).map((e) => e.toString()).toList();
    final name    = (widget.profile['name'] ?? '').toString();
    final age     = widget.profile['age']?.toString() ?? '';
    final city    = (widget.profile['city'] ?? '').toString();
    final bio     = (widget.profile['bio'] ?? '').toString();
    final marital = (widget.profile['marital_status'] ?? '').toString();
    final edu     = (widget.profile['education'] ?? '').toString();

    return GestureDetector(
      onPanStart: _onPanStart,
      onPanUpdate: _onPanUpdate,
      onPanEnd: _onPanEnd,
      child: Transform.translate(
        offset: _offset,
        child: Transform.rotate(
          angle: angle,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: Stack(children: [
              // Photo
              Positioned.fill(child: photos.isNotEmpty
                  ? GestureDetector(
                      onTapUp: (d) {
                        final w = context.size?.width ?? 300;
                        setState(() => _photoIndex = d.localPosition.dx < w / 2
                            ? math.max(0, _photoIndex - 1)
                            : math.min(photos.length - 1, _photoIndex + 1));
                      },
                      child: NetImage(url: photos[_photoIndex.clamp(0, photos.length - 1)], fit: BoxFit.cover),
                    )
                  : Container(
                      decoration: BoxDecoration(gradient: LinearGradient(
                        colors: [kOrange.withValues(alpha: 0.3), kOrange.withValues(alpha: 0.6)],
                        begin: Alignment.topLeft, end: Alignment.bottomRight,
                      )),
                      child: const Center(child: Icon(Icons.person_rounded, size: 80, color: Colors.white70)),
                    )),

              // Photo indicator dots
              if (photos.length > 1)
                Positioned(top: 12, left: 0, right: 0, child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(photos.length, (i) => AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.symmetric(horizontal: 2),
                    width: i == _photoIndex ? 20 : 6,
                    height: 4,
                    decoration: BoxDecoration(
                      color: i == _photoIndex ? Colors.white : Colors.white54,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  )),
                )),

              // Bottom gradient overlay
              Positioned(left: 0, right: 0, bottom: 0, height: 280,
                child: DecoratedBox(decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter, end: Alignment.topCenter,
                    colors: [Colors.black.withValues(alpha: 0.85), Colors.transparent],
                  ),
                ))),

              // Info overlay
              Positioned(left: 20, right: 20, bottom: 20, child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Expanded(child: Text('$name, $age', style: const TextStyle(
                      color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900,
                      shadows: [Shadow(blurRadius: 8)],
                    ))),
                  ]),
                  if (city.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Row(children: [
                      const Icon(Icons.location_on_rounded, color: Colors.white70, size: 14),
                      const SizedBox(width: 4),
                      Text(city, style: const TextStyle(color: Colors.white70, fontSize: 14)),
                    ]),
                  ],
                  const SizedBox(height: 8),
                  Wrap(spacing: 6, runSpacing: 6, children: [
                    if (marital.isNotEmpty) _CardChip(marital),
                    if (edu.isNotEmpty) _CardChip(edu),
                  ]),
                  if (bio.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(bio, style: const TextStyle(color: Colors.white70, fontSize: 13),
                      maxLines: 2, overflow: TextOverflow.ellipsis),
                  ],
                ],
              )),

              // LIKE stamp
              if (likeOpacity > 0)
                Positioned(top: 40, left: 24, child: Opacity(opacity: likeOpacity,
                  child: Transform.rotate(angle: -0.3, child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      border: Border.all(color: const Color(0xFF22C55E), width: 3),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text('LIKE', style: TextStyle(color: Color(0xFF22C55E), fontSize: 28, fontWeight: FontWeight.w900)),
                  )))),

              // NOPE stamp
              if (nopeOpacity > 0)
                Positioned(top: 40, right: 24, child: Opacity(opacity: nopeOpacity,
                  child: Transform.rotate(angle: 0.3, child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      border: Border.all(color: const Color(0xFFEF4444), width: 3),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text('NOPE', style: TextStyle(color: Color(0xFFEF4444), fontSize: 28, fontWeight: FontWeight.w900)),
                  )))),
            ]),
          ),
        ),
      ),
    );
  }
}

// ─── Static Card (behind) ─────────────────────────────────────────────────────

class _StaticCard extends StatelessWidget {
  final Map<String, dynamic> profile;
  const _StaticCard({required this.profile});

  @override
  Widget build(BuildContext context) {
    final photos = (profile['photos'] as List? ?? []).map((e) => e.toString()).toList();
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: photos.isNotEmpty
          ? NetImage(url: photos.first, fit: BoxFit.cover)
          : Container(color: const Color(0xFFE5E7EB),
              child: const Center(child: Icon(Icons.person_rounded, size: 64, color: Colors.white54))),
    );
  }
}

// ─── Action Button ────────────────────────────────────────────────────────────

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final Color color;
  final double size;
  final VoidCallback onTap;
  const _ActionButton({required this.icon, required this.color, required this.size, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      width: size, height: size,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: [BoxShadow(color: color.withValues(alpha: 0.25), blurRadius: 16, offset: const Offset(0, 4))],
        border: Border.all(color: color.withValues(alpha: 0.2), width: 1.5),
      ),
      child: Icon(icon, color: color, size: size * 0.45),
    ),
  );
}

// ─── Card Chip ────────────────────────────────────────────────────────────────

class _CardChip extends StatelessWidget {
  final String label;
  const _CardChip(this.label);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.2),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.white30),
    ),
    child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
  );
}

// ─── Empty Discover ───────────────────────────────────────────────────────────

class _EmptyDiscover extends StatelessWidget {
  final VoidCallback onRefresh;
  const _EmptyDiscover({required this.onRefresh});
  @override
  Widget build(BuildContext context) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.explore_rounded, size: 80, color: Color(0xFFE5E7EB)),
    const SizedBox(height: 16),
    const Text("You've seen everyone!", style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
    const SizedBox(height: 8),
    const Text("Check back later for new profiles", style: TextStyle(color: Color(0xFF9CA3AF))),
    const SizedBox(height: 24),
    ElevatedButton(
      onPressed: onRefresh,
      style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12)),
      child: const Text('Refresh', style: TextStyle(fontWeight: FontWeight.w700)),
    ),
  ]));
}

// ─── Match Celebration ────────────────────────────────────────────────────────

class _MatchCelebration extends StatefulWidget {
  final Map<String, dynamic> matchProfile;
  final VoidCallback onDismiss;
  const _MatchCelebration({required this.matchProfile, required this.onDismiss});
  @override
  State<_MatchCelebration> createState() => _MatchCelebrationState();
}

class _MatchCelebrationState extends State<_MatchCelebration> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _scale = CurvedAnimation(parent: _ctrl, curve: Curves.elasticOut);
    _ctrl.forward();
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final photos = (widget.matchProfile['photos'] as List? ?? []).map((e) => e.toString()).toList();
    final name   = (widget.matchProfile['name'] ?? '').toString();
    final uid    = widget.matchProfile['user_id'];
    final userId = uid is int ? uid : int.tryParse(uid?.toString() ?? '');

    return Material(color: Colors.transparent,
      child: Container(
        color: Colors.black.withValues(alpha: 0.85),
        child: SafeArea(child: ScaleTransition(scale: _scale, child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text("💍 It's a Match!", style: TextStyle(
              color: kOrange, fontSize: 36, fontWeight: FontWeight.w900,
              shadows: [Shadow(color: kOrange, blurRadius: 20)],
            )),
            const SizedBox(height: 8),
            Text("You and $name liked each other", style: const TextStyle(color: Colors.white70, fontSize: 16)),
            const SizedBox(height: 40),
            // Overlapping avatars
            SizedBox(height: 140, child: Stack(alignment: Alignment.center, children: [
              Positioned(left: 60, child: _MatchAvatar(url: photos.isNotEmpty ? photos.first : null)),
              Positioned(right: 60, child: Container(width: 110, height: 110,
                decoration: BoxDecoration(shape: BoxShape.circle, color: kOrange.withValues(alpha: 0.3),
                  border: Border.all(color: kOrange, width: 3)),
                child: const Icon(Icons.person_rounded, size: 60, color: Colors.white54))),
              Container(width: 44, height: 44, decoration: const BoxDecoration(
                  color: kOrange, shape: BoxShape.circle),
                child: const Icon(Icons.favorite_rounded, color: Colors.white, size: 24)),
            ])),
            const SizedBox(height: 40),
            Padding(padding: const EdgeInsets.symmetric(horizontal: 40), child: Column(children: [
              if (userId != null)
                Consumer(builder: (ctx, ref, _) => SizedBox(width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () async {
                      try {
                        final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(userId);
                        if (ctx.mounted) {
                          widget.onDismiss();
                          Navigator.push(ctx, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
                        }
                      } catch (_) {}
                    },
                    style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      padding: const EdgeInsets.symmetric(vertical: 16)),
                    child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.chat_bubble_rounded, size: 18),
                      SizedBox(width: 8),
                      Text('Say Hello', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                    ]),
                  ))),
              const SizedBox(height: 12),
              SizedBox(width: double.infinity,
                child: OutlinedButton(
                  onPressed: widget.onDismiss,
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.white,
                    side: const BorderSide(color: Colors.white30),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    padding: const EdgeInsets.symmetric(vertical: 14)),
                  child: const Text('Keep Swiping', style: TextStyle(fontWeight: FontWeight.w600)),
                )),
            ])),
          ],
        ))),
      ));
  }
}

class _MatchAvatar extends StatelessWidget {
  final String? url;
  const _MatchAvatar({this.url});
  @override
  Widget build(BuildContext context) => Container(
    width: 110, height: 110,
    decoration: BoxDecoration(shape: BoxShape.circle,
      border: Border.all(color: Colors.white, width: 3)),
    child: ClipOval(child: url != null
        ? NetImage(url: url!, fit: BoxFit.cover)
        : Container(color: const Color(0xFF374151), child: const Icon(Icons.person_rounded, size: 60, color: Colors.white54))),
  );
}

// ─── Matches Tab ──────────────────────────────────────────────────────────────

class _MatchesTab extends ConsumerWidget {
  const _MatchesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_matchesProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => _ErrorState(onRetry: () => ref.invalidate(_matchesProvider)),
      data: (matches) {
        if (matches.isEmpty) return _EmptyState(icon: Icons.favorite_rounded,
            title: 'No matches yet', sub: 'Keep swiping to find your match');
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(_matchesProvider),
          child: GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.72),
            itemCount: matches.length,
            itemBuilder: (_, i) => _MatchCard(profile: matches[i]),
          ),
        );
      },
    );
  }
}

class _MatchCard extends ConsumerWidget {
  final Map<String, dynamic> profile;
  const _MatchCard({required this.profile});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final photos = (profile['photos'] as List? ?? []).map((e) => e.toString()).toList();
    final name   = (profile['name'] ?? '').toString();
    final age    = profile['age']?.toString() ?? '';
    final uid    = profile['user_id'];
    final userId = uid is int ? uid : int.tryParse(uid?.toString() ?? '');

    return GestureDetector(
      onTap: () async {
        if (userId == null) return;
        try {
          final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(userId);
          if (context.mounted) Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
        } catch (_) {}
      },
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Stack(fit: StackFit.expand, children: [
          photos.isNotEmpty
              ? NetImage(url: photos.first, fit: BoxFit.cover)
              : Container(color: const Color(0xFFE5E7EB),
                  child: const Icon(Icons.person_rounded, size: 48, color: Colors.white54)),
          // Gradient
          Positioned.fill(child: DecoratedBox(decoration: BoxDecoration(
            gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.center,
              colors: [Colors.black.withValues(alpha: 0.7), Colors.transparent])))),
          // Info
          Positioned(left: 10, right: 10, bottom: 10, child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('$name, $age', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.chat_bubble_rounded, color: Colors.white, size: 10),
                  SizedBox(width: 4),
                  Text('Message', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                ]),
              ),
            ],
          )),
          // Match badge
          Positioned(top: 8, right: 8, child: Container(
            padding: const EdgeInsets.all(4),
            decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
            child: const Icon(Icons.favorite_rounded, color: Colors.white, size: 12),
          )),
        ]),
      ),
    );
  }
}

// ─── Likes Tab ────────────────────────────────────────────────────────────────

class _LikesTab extends ConsumerStatefulWidget {
  const _LikesTab();
  @override
  ConsumerState<_LikesTab> createState() => _LikesTabState();
}

class _LikesTabState extends ConsumerState<_LikesTab> with SingleTickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() { super.initState(); _tab = TabController(length: 2, vsync: this); }
  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Container(color: Colors.white,
        child: TabBar(controller: _tab,
          labelColor: kOrange, unselectedLabelColor: const Color(0xFF9CA3AF),
          indicatorColor: kOrange, indicatorSize: TabBarIndicatorSize.tab,
          tabs: const [Tab(text: 'Received'), Tab(text: 'Sent')],
        )),
      Expanded(child: TabBarView(controller: _tab, children: [
        _ReceivedTab(),
        _SentTab(),
      ])),
    ]);
  }
}

class _ReceivedTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_receivedProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => _ErrorState(onRetry: () => ref.invalidate(_receivedProvider)),
      data: (items) {
        if (items.isEmpty) return _EmptyState(icon: Icons.favorite_border_rounded,
            title: 'No interests yet', sub: 'When someone likes you, they appear here');
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(_receivedProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _InterestCard(item: items[i], isReceived: true),
          ),
        );
      },
    );
  }
}

class _SentTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_sentProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => _ErrorState(onRetry: () => ref.invalidate(_sentProvider)),
      data: (items) {
        if (items.isEmpty) return _EmptyState(icon: Icons.send_rounded,
            title: 'No sent interests', sub: 'Swipe right to send interest to someone');
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (_, i) => _InterestCard(item: items[i], isReceived: false),
        );
      },
    );
  }
}

class _InterestCard extends ConsumerWidget {
  final Map<String, dynamic> item;
  final bool isReceived;
  const _InterestCard({required this.item, required this.isReceived});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final photos  = (item['photos'] as List? ?? []).map((e) => e.toString()).toList();
    final name    = (item['name'] ?? '').toString();
    final age     = item['age']?.toString() ?? '';
    final city    = (item['city'] ?? '').toString();
    final status  = (item['status'] ?? 'pending').toString();
    final uid     = item['user_id'];
    final userId  = uid is int ? uid : int.tryParse(uid?.toString() ?? '');

    Color statusColor; String statusLabel; IconData statusIcon;
    switch (status) {
      case 'accepted': statusColor = const Color(0xFF16A34A); statusLabel = 'Matched!'; statusIcon = Icons.favorite_rounded; break;
      case 'rejected': statusColor = const Color(0xFFEF4444); statusLabel = 'Declined'; statusIcon = Icons.close_rounded; break;
      default:         statusColor = const Color(0xFFF59E0B); statusLabel = 'Pending';  statusIcon = Icons.schedule_rounded;
    }

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, 2))],
      ),
      child: Row(children: [
        ClipRRect(
          borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
          child: SizedBox(width: 90, height: 110, child: photos.isNotEmpty
              ? NetImage(url: photos.first, fit: BoxFit.cover)
              : Container(color: const Color(0xFFE5E7EB),
                  child: const Icon(Icons.person_rounded, size: 40, color: Colors.white54))),
        ),
        Expanded(child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('$name, $age', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            if (city.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(city, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
            ],
            const SizedBox(height: 8),
            Row(children: [
              Icon(statusIcon, size: 14, color: statusColor),
              const SizedBox(width: 4),
              Text(statusLabel, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: statusColor)),
            ]),
            if (isReceived && status == 'pending') ...[
              const SizedBox(height: 8),
              Row(children: [
                _SmallBtn(label: 'Accept', color: kOrange, onTap: () async {
                  final senderId = item['user_id'];
                  final sId = senderId is int ? senderId : int.tryParse(senderId?.toString() ?? '');
                  if (sId == null) return;
                  try {
                    await ApiClient.instance.post('/emarry/interest/$sId/respond', data: {'action': 'accept'});
                    ref.invalidate(_receivedProvider);
                    ref.invalidate(_matchesProvider);
                  } catch (_) {}
                }),
                const SizedBox(width: 8),
                _SmallBtn(label: 'Decline', color: const Color(0xFFEF4444), outline: true, onTap: () async {
                  final senderId = item['user_id'];
                  final sId = senderId is int ? senderId : int.tryParse(senderId?.toString() ?? '');
                  if (sId == null) return;
                  try {
                    await ApiClient.instance.post('/emarry/interest/$sId/respond', data: {'action': 'reject'});
                    ref.invalidate(_receivedProvider);
                  } catch (_) {}
                }),
              ]),
            ],
            if (status == 'accepted' && userId != null) ...[
              const SizedBox(height: 8),
              _SmallBtn(label: 'Message', color: kOrange, icon: Icons.chat_bubble_rounded, onTap: () async {
                try {
                  final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(userId);
                  if (context.mounted) Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
                } catch (_) {}
              }),
            ],
          ]),
        )),
      ]),
    );
  }
}

class _SmallBtn extends StatelessWidget {
  final String label;
  final Color color;
  final bool outline;
  final IconData? icon;
  final VoidCallback onTap;
  const _SmallBtn({required this.label, required this.color, this.outline = false, this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: outline ? Colors.transparent : color,
        borderRadius: BorderRadius.circular(8),
        border: outline ? Border.all(color: color) : null,
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) ...[Icon(icon, color: outline ? color : Colors.white, size: 12), const SizedBox(width: 4)],
        Text(label, style: TextStyle(color: outline ? color : Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
      ]),
    ),
  );
}

// ─── My Profile Tab ───────────────────────────────────────────────────────────

class _MyProfileTab extends ConsumerWidget {
  const _MyProfileTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_myEmarryProfileProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => _EmptyState(icon: Icons.person_add_rounded,
          title: 'Create your profile', sub: 'Set up your eMarry profile to start matching'),
      data: (profile) => profile == null
          ? _EmptyState(
              icon: Icons.person_add_rounded,
              title: 'Create your profile',
              sub: 'Set up your eMarry profile to start matching',
              action: ElevatedButton(
                onPressed: () => _ProfileFormSheet.show(context, ref, null),
                style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12)),
                child: const Text('Create Profile', style: TextStyle(fontWeight: FontWeight.w700)),
              ))
          : _ProfileView(profile: profile, ref: ref),
    );
  }
}

class _ProfileView extends StatelessWidget {
  final Map<String, dynamic> profile;
  final WidgetRef ref;
  const _ProfileView({required this.profile, required this.ref});

  @override
  Widget build(BuildContext context) {
    final photos   = (profile['photos'] as List? ?? []).map((e) => e.toString()).toList();
    final name     = (profile['name'] ?? '').toString();
    final age      = profile['age']?.toString() ?? '';
    final city     = (profile['city'] ?? '').toString();
    final bio      = (profile['bio'] ?? '').toString();
    final status   = (profile['status'] ?? '').toString();
    final edu      = (profile['education'] ?? '').toString();
    final occ      = (profile['occupation'] ?? '').toString();
    final marital  = (profile['marital_status'] ?? '').toString();
    final kids     = profile['has_children'] == true || profile['has_children'] == 1;

    Color sBg; String sLabel;
    switch (status) {
      case 'approved': sBg = const Color(0xFF16A34A); sLabel = 'Active'; break;
      case 'pending':  sBg = const Color(0xFFF59E0B); sLabel = 'Under Review'; break;
      default:         sBg = const Color(0xFFEF4444); sLabel = 'Rejected';
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Photo + name card
        Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            color: Colors.white,
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 12, offset: const Offset(0, 4))],
          ),
          child: Column(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
              child: SizedBox(height: 280, width: double.infinity, child: photos.isNotEmpty
                  ? NetImage(url: photos.first, fit: BoxFit.cover)
                  : Container(color: const Color(0xFFF1F5F9),
                      child: const Icon(Icons.person_rounded, size: 80, color: Color(0xFFCBD5E1)))),
            ),
            Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('$name, $age', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900))),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: sBg.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
                  child: Text(sLabel, style: TextStyle(color: sBg, fontSize: 12, fontWeight: FontWeight.w700)),
                ),
              ]),
              if (city.isNotEmpty) ...[
                const SizedBox(height: 4),
                Row(children: [
                  const Icon(Icons.location_on_rounded, size: 14, color: Color(0xFF9CA3AF)),
                  const SizedBox(width: 4),
                  Text(city, style: const TextStyle(color: Color(0xFF9CA3AF))),
                ]),
              ],
            ])),
          ]),
        ),
        const SizedBox(height: 16),
        // Info cards
        _InfoSection(title: 'About Me', children: [
          if (bio.isNotEmpty) Text(bio, style: const TextStyle(color: Color(0xFF475569), height: 1.5)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            if (edu.isNotEmpty)     _Tag(edu, Icons.school_rounded),
            if (occ.isNotEmpty)     _Tag(occ, Icons.work_rounded),
            if (marital.isNotEmpty) _Tag(marital, Icons.favorite_border_rounded),
            _Tag(kids ? 'Has children' : 'No children', Icons.child_care_rounded),
          ]),
        ]),
        const SizedBox(height: 12),
        // Photos
        if (photos.length > 1)
          _InfoSection(title: 'Photos', children: [
            SizedBox(height: 100,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: photos.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (_, i) => ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: SizedBox(width: 80, height: 100, child: NetImage(url: photos[i], fit: BoxFit.cover)),
                ),
              )),
          ]),
        const SizedBox(height: 12),
        // Photo upload section
        _PhotoUploadSection(photos: photos, profileRef: ref),
        const SizedBox(height: 20),
        SizedBox(width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: () => _ProfileFormSheet.show(context, ref, profile),
            icon: const Icon(Icons.edit_rounded, size: 18),
            label: const Text('Edit Profile', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
            style: ElevatedButton.styleFrom(
              backgroundColor: kOrange, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          )),
      ]),
    );
  }
}

class _PhotoUploadSection extends StatefulWidget {
  final List<String> photos;
  final WidgetRef profileRef;
  const _PhotoUploadSection({required this.photos, required this.profileRef});
  @override
  State<_PhotoUploadSection> createState() => _PhotoUploadSectionState();
}

class _PhotoUploadSectionState extends State<_PhotoUploadSection> {
  bool _uploading = false;

  Future<void> _pickAndUpload() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85, maxWidth: 1200);
    if (picked == null || !mounted) return;
    setState(() => _uploading = true);
    try {
      final file = File(picked.path);
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(file.path, filename: 'photo.jpg'),
      });
      final res = await ApiClient.instance.post('/emarry/photo', data: formData);
      if (res.data['success'] == true) {
        final newUrl = res.data['url']?.toString() ?? '';
        if (newUrl.isNotEmpty) {
          final updatedPhotos = [...widget.photos, newUrl];
          await ApiClient.instance.post('/emarry/profile', data: {'photos': updatedPhotos});
          widget.profileRef.invalidate(_myEmarryProfileProvider);
        }
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Photo upload failed. Try again.'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _deletePhoto(int index) async {
    final updated = [...widget.photos]..removeAt(index);
    try {
      await ApiClient.instance.post('/emarry/profile', data: {'photos': updated});
      widget.profileRef.invalidate(_myEmarryProfileProvider);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final photos = widget.photos;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('My Photos', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        const SizedBox(height: 12),
        SizedBox(
          height: 110,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: [
              // Existing photos
              for (int i = 0; i < photos.length; i++)
                Stack(children: [
                  Container(
                    width: 90, height: 110, margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(borderRadius: BorderRadius.circular(12)),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: NetImage(url: photos[i], fit: BoxFit.cover),
                    ),
                  ),
                  Positioned(top: 4, right: 12,
                    child: GestureDetector(
                      onTap: () => _deletePhoto(i),
                      child: Container(
                        width: 22, height: 22,
                        decoration: BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                        child: const Icon(Icons.close, color: Colors.white, size: 14),
                      ),
                    )),
                ]),
              // Add photo button
              if (photos.length < 4)
                GestureDetector(
                  onTap: _uploading ? null : _pickAndUpload,
                  child: Container(
                    width: 90, height: 110,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: kOrange, width: 2, style: BorderStyle.solid),
                      color: kOrange.withValues(alpha: 0.05),
                    ),
                    child: _uploading
                        ? const Center(child: SizedBox(width: 24, height: 24,
                            child: CircularProgressIndicator(color: kOrange, strokeWidth: 2)))
                        : const Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                            Icon(Icons.add_photo_alternate_rounded, color: kOrange, size: 32),
                            SizedBox(height: 4),
                            Text('Add Photo', style: TextStyle(color: kOrange, fontSize: 11, fontWeight: FontWeight.w600)),
                          ]),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Text('${photos.length}/4 photos • Tap + to add, × to remove',
          style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
      ]),
    );
  }
}

class _InfoSection extends StatelessWidget {
  final String title;
  final List<Widget> children;
  const _InfoSection({required this.title, required this.children});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
      const SizedBox(height: 10),
      ...children,
    ]),
  );
}

class _Tag extends StatelessWidget {
  final String label;
  final IconData icon;
  const _Tag(this.label, this.icon);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: kOrange.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 13, color: kOrange),
      const SizedBox(width: 5),
      Text(label, style: const TextStyle(fontSize: 12, color: kOrange, fontWeight: FontWeight.w600)),
    ]),
  );
}

// ─── Profile Detail Sheet (from swipe card info button) ───────────────────────

class _ProfileDetailSheet extends ConsumerWidget {
  final Map<String, dynamic> profile;
  const _ProfileDetailSheet({required this.profile});

  static void show(BuildContext context, Map<String, dynamic> profile) {
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => _ProfileDetailSheet(profile: profile),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final photos   = (profile['photos'] as List? ?? []).map((e) => e.toString()).toList();
    final name     = (profile['name'] ?? '').toString();
    final age      = profile['age']?.toString() ?? '';
    final city     = (profile['city'] ?? '').toString();
    final bio      = (profile['bio'] ?? '').toString();
    final edu      = (profile['education'] ?? '').toString();
    final occ      = (profile['occupation'] ?? '').toString();
    final marital  = (profile['marital_status'] ?? '').toString();
    final hasKids  = profile['has_children'] == true || profile['has_children'] == 1;
    final uid      = profile['user_id'];
    final userId   = uid is int ? uid : int.tryParse(uid?.toString() ?? '');
    final isMatch  = profile['interest_status'] == 'accepted';

    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.88,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          // Drag handle
          Center(child: Padding(padding: const EdgeInsets.only(top: 12, bottom: 8),
            child: Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2))))),

          // Photos
          if (photos.isNotEmpty)
            SizedBox(height: 320, child: PageView.builder(
              itemCount: photos.length,
              itemBuilder: (_, i) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: ClipRRect(borderRadius: BorderRadius.circular(16),
                  child: NetImage(url: photos[i], fit: BoxFit.cover)),
              ),
            ))
          else
            Container(height: 200, margin: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(16)),
              child: const Icon(Icons.person_rounded, size: 80, color: Color(0xFFCBD5E1))),

          Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('$name, $age', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
            if (city.isNotEmpty) ...[
              const SizedBox(height: 4),
              Row(children: [const Icon(Icons.location_on_rounded, size: 15, color: Color(0xFF9CA3AF)),
                const SizedBox(width: 4),
                Text(city, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 14))]),
            ],
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              if (marital.isNotEmpty) _Tag(marital, Icons.favorite_border_rounded),
              if (edu.isNotEmpty) _Tag(edu, Icons.school_rounded),
              if (occ.isNotEmpty) _Tag(occ, Icons.work_rounded),
              _Tag(hasKids ? 'Has children' : 'No children', Icons.child_care_rounded),
            ]),
            if (bio.isNotEmpty) ...[
              const SizedBox(height: 16),
              const Text('About', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 6),
              Text(bio, style: const TextStyle(color: Color(0xFF475569), height: 1.6)),
            ],
            if (isMatch && userId != null) ...[
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFDCFCE7),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFF86EFAC)),
                ),
                child: Row(children: [
                  const Icon(Icons.favorite_rounded, color: Color(0xFF16A34A), size: 20),
                  const SizedBox(width: 8),
                  const Expanded(child: Text("You're matched! Start a conversation.",
                    style: TextStyle(color: Color(0xFF16A34A), fontWeight: FontWeight.w600))),
                  const SizedBox(width: 8),
                  GestureDetector(
                    onTap: () async {
                      try {
                        final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(userId);
                        if (context.mounted) {
                          Navigator.pop(context);
                          Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
                        }
                      } catch (_) {}
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(10)),
                      child: const Text('Message', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                    ),
                  ),
                ]),
              ),
            ],
          ])),
        ],
      ),
    );
  }
}

// ─── Profile Form Sheet ───────────────────────────────────────────────────────

class _ProfileFormSheet extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  const _ProfileFormSheet({this.existing});

  static void show(BuildContext context, WidgetRef ref, Map<String, dynamic>? existing) {
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => _ProfileFormSheet(existing: existing),
    );
  }

  @override
  ConsumerState<_ProfileFormSheet> createState() => _ProfileFormSheetState();
}

class _ProfileFormSheetState extends ConsumerState<_ProfileFormSheet> {
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String _gender = 'male', _lookingFor = 'female', _education = 'bachelor', _maritalStatus = 'single';
  int _age = 25;
  String _city = '', _occupation = '', _bio = '';
  bool _hasChildren = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    if (e != null) {
      _gender        = (e['gender'] ?? 'male').toString();
      _lookingFor    = (e['looking_for'] ?? 'female').toString();
      _age           = e['age'] is int ? e['age'] : int.tryParse(e['age']?.toString() ?? '') ?? 25;
      _city          = (e['city'] ?? '').toString();
      _education     = (e['education'] ?? 'bachelor').toString();
      _occupation    = (e['occupation'] ?? '').toString();
      _maritalStatus = (e['marital_status'] ?? 'single').toString();
      _hasChildren   = e['has_children'] == true || e['has_children'] == 1;
      _bio           = (e['bio'] ?? '').toString();
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();
    setState(() => _loading = true);
    try {
      await ApiClient.instance.post('/emarry/profile', data: {
        'gender': _gender, 'looking_for': _lookingFor, 'age': _age,
        'city': _city, 'education': _education, 'occupation': _occupation,
        'marital_status': _maritalStatus, 'has_children': _hasChildren, 'bio': _bio,
      });
      ref.invalidate(_myEmarryProfileProvider);
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Profile submitted for review ✓'),
          backgroundColor: kOrange,
        ));
      }
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.88,
        child: Form(key: _formKey, child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          children: [
            Center(child: Container(width: 40, height: 4,
              decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('My Profile', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
            const SizedBox(height: 20),

            // Gender
            _FormLabel('I am'),
            _ChoiceRow(
              options: const ['male', 'female'],
              labels: const ['Male', 'Female'],
              selected: _gender,
              onSelect: (v) => setState(() => _gender = v),
            ),
            const SizedBox(height: 16),

            // Looking for
            _FormLabel('Looking for'),
            _ChoiceRow(
              options: const ['male', 'female'],
              labels: const ['Male', 'Female'],
              selected: _lookingFor,
              onSelect: (v) => setState(() => _lookingFor = v),
            ),
            const SizedBox(height: 16),

            // Age
            _FormLabel('Age'),
            TextFormField(
              initialValue: _age.toString(),
              keyboardType: TextInputType.number,
              decoration: _inputDeco('Enter your age'),
              validator: (v) => (int.tryParse(v ?? '') ?? 0) < 18 ? 'Min age 18' : null,
              onSaved: (v) => _age = int.tryParse(v ?? '') ?? _age,
            ),
            const SizedBox(height: 16),

            // City
            _FormLabel('City'),
            TextFormField(
              initialValue: _city,
              decoration: _inputDeco('Your city'),
              onSaved: (v) => _city = v ?? '',
            ),
            const SizedBox(height: 16),

            // Education
            _FormLabel('Education'),
            _DropdownField(
              value: _education,
              items: const ['high_school', 'bachelor', 'master', 'phd', 'other'],
              labels: const ['High School', 'Bachelor', 'Master', 'PhD', 'Other'],
              onChanged: (v) => setState(() => _education = v!),
            ),
            const SizedBox(height: 16),

            // Occupation
            _FormLabel('Occupation'),
            TextFormField(
              initialValue: _occupation,
              decoration: _inputDeco('Your occupation'),
              onSaved: (v) => _occupation = v ?? '',
            ),
            const SizedBox(height: 16),

            // Marital Status
            _FormLabel('Marital Status'),
            _ChoiceRow(
              options: const ['single', 'divorced', 'widowed'],
              labels: const ['Single', 'Divorced', 'Widowed'],
              selected: _maritalStatus,
              onSelect: (v) => setState(() => _maritalStatus = v),
            ),
            const SizedBox(height: 16),

            // Has children
            Container(
              decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0))),
              child: SwitchListTile(
                title: const Text('Has children', style: TextStyle(fontWeight: FontWeight.w600)),
                value: _hasChildren,
                onChanged: (v) => setState(() => _hasChildren = v),
                activeColor: kOrange,
              ),
            ),
            const SizedBox(height: 16),

            // Bio
            _FormLabel('About Me'),
            TextFormField(
              initialValue: _bio,
              maxLines: 4, maxLength: 500,
              decoration: _inputDeco('Write a short bio...'),
              onSaved: (v) => _bio = v ?? '',
            ),
            const SizedBox(height: 24),

            SizedBox(width: double.infinity,
              child: ElevatedButton(
                onPressed: _loading ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: kOrange, foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  padding: const EdgeInsets.symmetric(vertical: 16)),
                child: _loading
                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Submit for Review', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              )),
          ],
        )),
      ),
    );
  }

  InputDecoration _inputDeco(String hint) => InputDecoration(
    hintText: hint, hintStyle: const TextStyle(color: Color(0xFF9CA3AF)),
    filled: true, fillColor: const Color(0xFFF8FAFC),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: kOrange, width: 1.5)),
  );
}

class _FormLabel extends StatelessWidget {
  final String label;
  const _FormLabel(this.label);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF374151))),
  );
}

class _ChoiceRow extends StatelessWidget {
  final List<String> options;
  final List<String> labels;
  final String selected;
  final void Function(String) onSelect;
  const _ChoiceRow({required this.options, required this.labels, required this.selected, required this.onSelect});

  @override
  Widget build(BuildContext context) => Row(children: List.generate(options.length, (i) {
    final sel = selected == options[i];
    return Expanded(child: GestureDetector(
      onTap: () => onSelect(options[i]),
      child: Container(
        margin: EdgeInsets.only(right: i < options.length - 1 ? 8 : 0),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: sel ? kOrange : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: sel ? kOrange : const Color(0xFFE2E8F0)),
        ),
        child: Center(child: Text(labels[i], style: TextStyle(
          fontWeight: FontWeight.w700, color: sel ? Colors.white : const Color(0xFF374151)))),
      ),
    ));
  }));
}

class _DropdownField extends StatelessWidget {
  final String value;
  final List<String> items;
  final List<String> labels;
  final void Function(String?) onChanged;
  const _DropdownField({required this.value, required this.items, required this.labels, required this.onChanged});

  @override
  Widget build(BuildContext context) => DropdownButtonFormField<String>(
    value: value,
    decoration: InputDecoration(
      filled: true, fillColor: const Color(0xFFF8FAFC),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: kOrange, width: 1.5)),
    ),
    items: List.generate(items.length, (i) => DropdownMenuItem(value: items[i], child: Text(labels[i]))),
    onChanged: onChanged,
  );
}

// ─── Filter Sheet ─────────────────────────────────────────────────────────────

class _FilterSheet extends ConsumerStatefulWidget {
  const _FilterSheet();

  static void show(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => const _FilterSheet(),
    );
  }

  @override
  ConsumerState<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<_FilterSheet> {
  late double _minAge, _maxAge;

  @override
  void initState() {
    super.initState();
    final f = ref.read(_filterProvider);
    _minAge = f.minAge.toDouble();
    _maxAge = f.maxAge.toDouble();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
    child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      Center(child: Container(width: 40, height: 4,
        decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
      const SizedBox(height: 16),
      const Text('Filters', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
      const SizedBox(height: 24),
      Row(children: [
        const Text('Age Range', style: TextStyle(fontWeight: FontWeight.w700)),
        const Spacer(),
        Text('${_minAge.round()} – ${_maxAge.round()}',
          style: const TextStyle(color: kOrange, fontWeight: FontWeight.w700)),
      ]),
      RangeSlider(
        values: RangeValues(_minAge, _maxAge),
        min: 18, max: 80,
        activeColor: kOrange,
        inactiveColor: const Color(0xFFE2E8F0),
        onChanged: (v) => setState(() { _minAge = v.start; _maxAge = v.end; }),
      ),
      const SizedBox(height: 24),
      SizedBox(width: double.infinity,
        child: ElevatedButton(
          onPressed: () {
            ref.read(_filterProvider.notifier).state = _EMarryFilter(
              minAge: _minAge.round(), maxAge: _maxAge.round());
            ref.invalidate(_discoverProvider);
            Navigator.pop(context);
          },
          style: ElevatedButton.styleFrom(
            backgroundColor: kOrange, foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            padding: const EdgeInsets.symmetric(vertical: 14)),
          child: const Text('Apply Filters', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        )),
    ]),
  );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String sub;
  final Widget? action;
  const _EmptyState({required this.icon, required this.title, required this.sub, this.action});

  @override
  Widget build(BuildContext context) => Center(child: Padding(
    padding: const EdgeInsets.all(32),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 72, color: const Color(0xFFE5E7EB)),
      const SizedBox(height: 16),
      Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
      const SizedBox(height: 8),
      Text(sub, style: const TextStyle(color: Color(0xFF9CA3AF)), textAlign: TextAlign.center),
      if (action != null) ...[const SizedBox(height: 24), action!],
    ]),
  ));
}

class _ErrorState extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorState({required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 56, color: Color(0xFFE5E7EB)),
    const SizedBox(height: 12),
    const Text('Something went wrong', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF374151))),
    const SizedBox(height: 12),
    TextButton(onPressed: onRetry, child: const Text('Retry', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700))),
  ]));
}
