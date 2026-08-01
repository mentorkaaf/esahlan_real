import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'dart:convert';
import '../../../../core/api/api_client.dart';
import '../../../../core/services/realtime_client.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart' show NetImage;

// ─── Providers ────────────────────────────────────────────────────────────────

final _emarryProfilesProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final res = await ApiClient.instance.get('/emarry/profiles');
  // res.data = {'success': true, 'data': {'data': [...], 'my_profile': ...}}
  return Map<String, dynamic>.from(res.data['data'] as Map);
});

final _myEmarryProfileProvider = FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final res = await ApiClient.instance.get('/emarry/profile/me');
  final data = res.data['data'];
  return data != null ? Map<String, dynamic>.from(data as Map) : null;
});

final _receivedInterestsProvider = FutureProvider.autoDispose<List<dynamic>>((ref) async {
  final res = await ApiClient.instance.get('/emarry/interests/received');
  return (res.data['data'] as List?) ?? [];
});

// ─── Main Screen ──────────────────────────────────────────────────────────────

class EMarryScreen extends ConsumerStatefulWidget {
  const EMarryScreen({super.key});

  @override
  ConsumerState<EMarryScreen> createState() => _EMarryScreenState();
}

class _EMarryScreenState extends ConsumerState<EMarryScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;
  String? _userChannel;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
    _subscribeRealtime();
  }

  Future<void> _subscribeRealtime() async {
    final userJson = await LocalStorage.getString('user_data');
    if (userJson == null) return;
    try {
      final map = jsonDecode(userJson) as Map<String, dynamic>;
      final userId = map['id'];
      if (userId == null) return;
      _userChannel = 'private-user.$userId';
      RealtimeClient.instance.listen(_userChannel!, 'emarry.interest', _onInterest);
      RealtimeClient.instance.listen(_userChannel!, 'emarry.interest.accepted', _onInterestAccepted);
    } catch (_) {}
  }

  void _onInterest(dynamic _) {
    if (!mounted) return;
    ref.invalidate(_receivedInterestsProvider);
    // Switch to Interests tab with a badge-style flash
    if (_tab.index != 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('💍 New interest received! Check your Interests tab.'),
          backgroundColor: AppColors.primary,
          duration: Duration(seconds: 3),
        ),
      );
    }
  }

  void _onInterestAccepted(dynamic _) {
    if (!mounted) return;
    ref.invalidate(_emarryProfilesProvider);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('💍 Your interest was accepted!'),
        backgroundColor: Color(0xFF16A34A),
        duration: Duration(seconds: 3),
      ),
    );
  }

  @override
  void dispose() {
    // Only remove our eMarry listeners — don't unsubscribe the whole private-user channel
    // since chat and notifications also use it.
    if (_userChannel != null) {
      RealtimeClient.instance.removeListener(_userChannel!, 'emarry.interest', _onInterest);
      RealtimeClient.instance.removeListener(_userChannel!, 'emarry.interest.accepted', _onInterestAccepted);
    }
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Inner tab bar
        Container(
          color: Theme.of(context).appBarTheme.backgroundColor,
          child: TabBar(
            controller: _tab,
            indicatorColor: AppColors.primary,
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textGrey,
            labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
            tabs: const [
              Tab(text: 'Browse'),
              Tab(text: 'Interests'),
              Tab(text: 'My Profile'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _tab,
            children: [
              _BrowseTab(onGoProfile: () => _tab.animateTo(2)),
              _InterestsTab(),
              _MyProfileTab(),
            ],
          ),
        ),
      ],
    );
  }
}

// ─── Browse Tab ───────────────────────────────────────────────────────────────

class _BrowseTab extends ConsumerWidget {
  final VoidCallback onGoProfile;
  const _BrowseTab({required this.onGoProfile});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_emarryProfilesProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
      error: (e, _) => _EMarryEmpty(
        icon: Icons.favorite_border_rounded,
        title: 'No profiles yet',
        subtitle: 'Create your profile first to browse matches',
        actionLabel: 'Create Profile',
        onAction: onGoProfile,
      ),
      data: (data) {
        final profiles = (data['data'] as List?) ?? [];
        final myProfile = data['my_profile'];

        if (myProfile == null) {
          return _EMarryEmpty(
            icon: Icons.person_add_rounded,
            title: 'Set up your profile',
            subtitle: 'Create a profile to start browsing potential matches',
            actionLabel: 'Create Profile',
            onAction: onGoProfile,
          );
        }

        if (myProfile['status'] == 'pending') {
          return const _EMarryEmpty(
            icon: Icons.hourglass_top_rounded,
            title: 'Profile under review',
            subtitle: 'Your profile is being reviewed by our team. This usually takes less than 24 hours.',
          );
        }

        if (profiles.isEmpty) {
          return const _EMarryEmpty(
            icon: Icons.search_off_rounded,
            title: 'No matches found',
            subtitle: 'There are no profiles matching your preferences right now. Check back soon.',
          );
        }

        return RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () => ref.refresh(_emarryProfilesProvider.future),
          child: GridView.builder(
            padding: const EdgeInsets.all(12),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              childAspectRatio: 0.72,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
            ),
            itemCount: profiles.length,
            itemBuilder: (_, i) => _ProfileCard(profile: profiles[i]),
          ),
        );
      },
    );
  }
}

// ─── Profile Card ─────────────────────────────────────────────────────────────

class _ProfileCard extends StatelessWidget {
  final dynamic profile;
  const _ProfileCard({required this.profile});

  @override
  Widget build(BuildContext context) {
    final photos = (profile['photos'] as List?) ?? [];
    final photoUrl = photos.isNotEmpty ? photos.first as String? : null;
    final name  = profile['name'] as String? ?? '';
    final age   = profile['age'];
    final city  = profile['city'] as String? ?? '';
    final status = profile['interest_status'] as String?;

    return GestureDetector(
      onTap: () => _ProfileDetailSheet.show(context, profile),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          color: Theme.of(context).cardTheme.color,
          boxShadow: [
            BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8, offset: const Offset(0, 2)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Photo
            Expanded(
              child: ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                child: photoUrl != null
                    ? NetImage(url: photoUrl, fit: BoxFit.cover, width: double.infinity)
                    : Container(
                        color: AppColors.primary.withValues(alpha: 0.1),
                        child: const Icon(Icons.person_rounded, size: 64, color: AppColors.primary),
                      ),
              ),
            ),
            // Info
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('$name, $age',
                    style: TextStyle(
                      fontWeight: FontWeight.w800, fontSize: 14,
                      color: Theme.of(context).colorScheme.onSurface,
                    ),
                    maxLines: 1, overflow: TextOverflow.ellipsis,
                  ),
                  if (city.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(city,
                      style: const TextStyle(fontSize: 12, color: AppColors.textGrey),
                      maxLines: 1, overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  const SizedBox(height: 8),
                  _InterestChip(status: status, userId: profile['user_id'] as int),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InterestChip extends ConsumerWidget {
  final String? status;
  final int userId;
  const _InterestChip({required this.status, required this.userId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (status == 'accepted') {
      return _chip(context, Icons.favorite_rounded, 'Matched', const Color(0xFF16A34A));
    }
    if (status == 'pending') {
      return _chip(context, Icons.schedule_rounded, 'Sent', AppColors.textGrey);
    }
    if (status == 'rejected') {
      return _chip(context, Icons.close_rounded, 'Declined', AppColors.error);
    }

    return GestureDetector(
      onTap: () => _sendInterest(context, ref, userId),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: AppColors.primary,
          borderRadius: BorderRadius.circular(8),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.favorite_border_rounded, color: Colors.white, size: 14),
            SizedBox(width: 4),
            Text('Interest', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }

  Widget _chip(BuildContext context, IconData icon, String label, Color color) {
    return Row(
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 4),
        Text(label, style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w600)),
      ],
    );
  }

  Future<void> _sendInterest(BuildContext context, WidgetRef ref, int userId) async {
    try {
      await ApiClient.instance.post('/emarry/interest/$userId');
      ref.invalidate(_emarryProfilesProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('💍 Interest sent!'), backgroundColor: AppColors.primary),
        );
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
        );
      }
    }
  }
}

// ─── Profile Detail Sheet ─────────────────────────────────────────────────────

class _ProfileDetailSheet extends StatelessWidget {
  final dynamic profile;
  const _ProfileDetailSheet({required this.profile});

  static void show(BuildContext context, dynamic profile) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _ProfileDetailSheet(profile: profile),
    );
  }

  @override
  Widget build(BuildContext context) {
    final photos = (profile['photos'] as List?) ?? [];
    final name   = profile['name'] as String? ?? '';
    final age    = profile['age'];
    final city   = profile['city'] as String? ?? '';
    final bio    = profile['bio'] as String? ?? '';
    final edu    = profile['education'] as String? ?? '';
    final occ    = profile['occupation'] as String? ?? '';
    final marital= profile['marital_status'] as String? ?? '';
    final hasKids= profile['has_children'] as bool? ?? false;

    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      minChildSize: 0.5,
      builder: (_, ctrl) => Container(
        decoration: BoxDecoration(
          color: Theme.of(context).dialogTheme.backgroundColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: ListView(
          controller: ctrl,
          padding: const EdgeInsets.all(20),
          children: [
            // Handle
            Center(child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)),
            )),
            const SizedBox(height: 16),

            // Photos carousel
            if (photos.isNotEmpty)
              SizedBox(
                height: 260,
                child: PageView.builder(
                  itemCount: photos.length,
                  itemBuilder: (_, i) => ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: NetImage(url: photos[i] as String, fit: BoxFit.cover),
                  ),
                ),
              ),

            const SizedBox(height: 16),
            Text('$name, $age',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: context.colors.navyText)),
            if (city.isNotEmpty) ...[
              const SizedBox(height: 4),
              Row(children: [
                const Icon(Icons.location_on_rounded, size: 16, color: AppColors.textGrey),
                const SizedBox(width: 4),
                Text(city, style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
              ]),
            ],

            const SizedBox(height: 16),
            Wrap(spacing: 8, runSpacing: 8, children: [
              if (edu.isNotEmpty) _Tag(edu),
              if (occ.isNotEmpty) _Tag(occ),
              _Tag(marital),
              _Tag(hasKids ? 'Has children' : 'No children'),
            ]),

            if (bio.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('About', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: context.colors.navyText)),
              const SizedBox(height: 6),
              Text(bio, style: const TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.5)),
            ],

            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  final String label;
  const _Tag(this.label);
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
    decoration: BoxDecoration(
      color: AppColors.primary.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(label, style: const TextStyle(fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.w600)),
  );
}

// ─── Interests Tab ────────────────────────────────────────────────────────────

class _InterestsTab extends ConsumerWidget {
  const _InterestsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_receivedInterestsProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
      error: (_, __) => const Center(child: Text('Failed to load interests')),
      data: (list) {
        if (list.isEmpty) {
          return const _EMarryEmpty(
            icon: Icons.favorite_border_rounded,
            title: 'No interests yet',
            subtitle: 'When someone is interested in you, they will appear here.',
          );
        }
        return RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () => ref.refresh(_receivedInterestsProvider.future),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: list.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) => _InterestCard(item: list[i], ref: ref),
          ),
        );
      },
    );
  }
}

class _InterestCard extends StatelessWidget {
  final dynamic item;
  final WidgetRef ref;
  const _InterestCard({required this.item, required this.ref});

  @override
  Widget build(BuildContext context) {
    final name   = item['name'] as String? ?? '';
    final age    = item['age'];
    final city   = item['city'] as String? ?? '';
    final avatar = item['avatar'] as String?;
    final status = item['status'] as String? ?? 'pending';
    final msg    = item['message'] as String?;
    final senderId = item['user_id'] as int;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 6)],
      ),
      child: Row(
        children: [
          // Avatar
          CircleAvatar(
            radius: 28,
            backgroundColor: AppColors.primary.withValues(alpha: 0.1),
            backgroundImage: avatar != null ? NetworkImage(avatar) : null,
            child: avatar == null ? const Icon(Icons.person_rounded, color: AppColors.primary) : null,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$name, $age',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Theme.of(context).colorScheme.onSurface)),
                if (city.isNotEmpty)
                  Text(city, style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                if (msg != null && msg.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text('"$msg"',
                    style: const TextStyle(fontSize: 13, color: AppColors.textGrey, fontStyle: FontStyle.italic),
                    maxLines: 2, overflow: TextOverflow.ellipsis),
                ],
                if (status == 'pending') ...[
                  const SizedBox(height: 8),
                  Row(children: [
                    Expanded(
                      child: _ActionBtn(
                        label: 'Accept', color: const Color(0xFF16A34A),
                        onTap: () => _respond(context, senderId, 'accept'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _ActionBtn(
                        label: 'Decline', color: AppColors.error,
                        onTap: () => _respond(context, senderId, 'reject'),
                      ),
                    ),
                  ]),
                ] else ...[
                  const SizedBox(height: 6),
                  Row(children: [
                    Icon(
                      status == 'accepted' ? Icons.favorite_rounded : Icons.close_rounded,
                      size: 14,
                      color: status == 'accepted' ? const Color(0xFF16A34A) : AppColors.error,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      status == 'accepted' ? 'Accepted' : 'Declined',
                      style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w600,
                        color: status == 'accepted' ? const Color(0xFF16A34A) : AppColors.error,
                      ),
                    ),
                  ]),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _respond(BuildContext context, int senderId, String action) async {
    try {
      await ApiClient.instance.post('/emarry/interest/$senderId/respond', data: {'action': action});
      ref.invalidate(_receivedInterestsProvider);
    } catch (_) {}
  }
}

class _ActionBtn extends StatelessWidget {
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _ActionBtn({required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(label,
        textAlign: TextAlign.center,
        style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 13)),
    ),
  );
}

// ─── My Profile Tab ───────────────────────────────────────────────────────────

class _MyProfileTab extends ConsumerStatefulWidget {
  const _MyProfileTab();

  @override
  ConsumerState<_MyProfileTab> createState() => _MyProfileTabState();
}

class _MyProfileTabState extends ConsumerState<_MyProfileTab> {
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  bool _editMode = false;

  // Form fields
  String _gender = 'male';
  String _lookingFor = 'female';
  int _age = 25;
  String _city = '';
  String _education = 'bachelor';
  String _occupation = '';
  String _maritalStatus = 'single';
  bool _hasChildren = false;
  String _bio = '';

  void _loadFromProfile(Map<String, dynamic> p) {
    _gender       = p['gender'] ?? 'male';
    _lookingFor   = p['looking_for'] ?? 'female';
    _age          = p['age'] ?? 25;
    _city         = p['city'] ?? '';
    _education    = p['education'] ?? 'bachelor';
    _occupation   = p['occupation'] ?? '';
    _maritalStatus= p['marital_status'] ?? 'single';
    _hasChildren  = p['has_children'] ?? false;
    _bio          = p['bio'] ?? '';
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();
    setState(() => _loading = true);
    try {
      await ApiClient.instance.post('/emarry/profile', data: {
        'gender': _gender, 'looking_for': _lookingFor,
        'age': _age, 'city': _city, 'education': _education,
        'occupation': _occupation, 'marital_status': _maritalStatus,
        'has_children': _hasChildren, 'bio': _bio,
      });
      ref.invalidate(_myEmarryProfileProvider);
      ref.invalidate(_emarryProfilesProvider);
      if (mounted) setState(() { _editMode = false; _loading = false; });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile submitted for review ✓'), backgroundColor: AppColors.primary),
        );
      }
    } catch (e) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_myEmarryProfileProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
      error: (_, __) => const Center(child: Text('Failed to load profile')),
      data: (profile) {
        if (profile != null && !_editMode) {
          return _ProfileView(
            profile: profile,
            onEdit: () {
              _loadFromProfile(profile);
              setState(() => _editMode = true);
            },
          );
        }
        // Form
        if (profile != null && !_editMode) _loadFromProfile(profile);
        return _ProfileForm(
          formKey: _formKey,
          loading: _loading,
          gender: _gender,
          lookingFor: _lookingFor,
          age: _age,
          city: _city,
          education: _education,
          occupation: _occupation,
          maritalStatus: _maritalStatus,
          hasChildren: _hasChildren,
          bio: _bio,
          onGenderChanged: (v) => setState(() => _gender = v!),
          onLookingForChanged: (v) => setState(() => _lookingFor = v!),
          onAgeChanged: (v) => setState(() => _age = int.tryParse(v ?? '') ?? _age),
          onCityChanged: (v) => setState(() => _city = v ?? ''),
          onEducationChanged: (v) => setState(() => _education = v!),
          onOccupationChanged: (v) => setState(() => _occupation = v ?? ''),
          onMaritalChanged: (v) => setState(() => _maritalStatus = v!),
          onHasChildrenChanged: (v) => setState(() => _hasChildren = v ?? false),
          onBioChanged: (v) => setState(() => _bio = v ?? ''),
          onSave: _save,
        );
      },
    );
  }
}

// ── Profile View (read-only with status) ─────────────────────────────────────

class _ProfileView extends StatelessWidget {
  final Map<String, dynamic> profile;
  final VoidCallback onEdit;
  const _ProfileView({required this.profile, required this.onEdit});

  @override
  Widget build(BuildContext context) {
    final status = profile['status'] as String? ?? 'pending';
    final statusColor = status == 'approved'
        ? const Color(0xFF16A34A)
        : status == 'rejected'
            ? AppColors.error
            : AppColors.warning;
    final statusLabel = status == 'approved' ? '✓ Approved & Visible'
        : status == 'rejected' ? '✗ Rejected'
        : '⏳ Under Review';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Status banner
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
            decoration: BoxDecoration(
              color: statusColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: statusColor.withValues(alpha: 0.3)),
            ),
            child: Text(statusLabel,
              style: TextStyle(color: statusColor, fontWeight: FontWeight.w700, fontSize: 14)),
          ),
          const SizedBox(height: 20),

          _InfoRow('Gender', profile['gender'] ?? ''),
          _InfoRow('Looking for', profile['looking_for'] ?? ''),
          _InfoRow('Age', '${profile['age']}'),
          if ((profile['city'] ?? '').isNotEmpty) _InfoRow('City', profile['city']),
          if ((profile['education'] ?? '').isNotEmpty) _InfoRow('Education', profile['education']),
          if ((profile['occupation'] ?? '').isNotEmpty) _InfoRow('Occupation', profile['occupation']),
          _InfoRow('Marital status', profile['marital_status'] ?? ''),
          _InfoRow('Has children', (profile['has_children'] ?? false) ? 'Yes' : 'No'),
          if ((profile['bio'] ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('Bio', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: context.colors.navyText)),
            const SizedBox(height: 4),
            Text(profile['bio'], style: const TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.5)),
          ],

          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: onEdit,
              icon: const Icon(Icons.edit_rounded, size: 18),
              label: const Text('Edit Profile', style: TextStyle(fontWeight: FontWeight.w800)),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  const _InfoRow(this.label, this.value);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      children: [
        SizedBox(width: 120,
          child: Text(label, style: const TextStyle(fontSize: 13, color: AppColors.textGrey))),
        Expanded(
          child: Text(value,
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Theme.of(context).colorScheme.onSurface)),
        ),
      ],
    ),
  );
}

// ── Profile Form ─────────────────────────────────────────────────────────────

class _ProfileForm extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final bool loading;
  final String gender, lookingFor, city, education, occupation, maritalStatus, bio;
  final int age;
  final bool hasChildren;
  final void Function(String?) onGenderChanged, onLookingForChanged, onEducationChanged, onMaritalChanged;
  final void Function(String?) onAgeChanged, onCityChanged, onOccupationChanged, onBioChanged;
  final void Function(bool?) onHasChildrenChanged;
  final VoidCallback onSave;

  const _ProfileForm({
    required this.formKey, required this.loading,
    required this.gender, required this.lookingFor, required this.age,
    required this.city, required this.education, required this.occupation,
    required this.maritalStatus, required this.hasChildren, required this.bio,
    required this.onGenderChanged, required this.onLookingForChanged,
    required this.onAgeChanged, required this.onCityChanged,
    required this.onEducationChanged, required this.onOccupationChanged,
    required this.onMaritalChanged, required this.onHasChildrenChanged,
    required this.onBioChanged, required this.onSave,
  });

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text('Your Profile', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText)),
          const SizedBox(height: 4),
          const Text('Fill in your details. Your profile will be reviewed before going live.',
            style: TextStyle(fontSize: 13, color: AppColors.textGrey)),
          const SizedBox(height: 20),

          _DropField('I am', gender, ['male', 'female'], onGenderChanged),
          const SizedBox(height: 12),
          _DropField('Looking for', lookingFor, ['male', 'female'], onLookingForChanged),
          const SizedBox(height: 12),

          TextFormField(
            initialValue: '$age',
            keyboardType: TextInputType.number,
            decoration: _dec('Age'),
            validator: (v) {
              final n = int.tryParse(v ?? '');
              if (n == null || n < 18 || n > 80) return 'Age must be 18–80';
              return null;
            },
            onChanged: onAgeChanged,
          ),
          const SizedBox(height: 12),

          TextFormField(
            initialValue: city,
            decoration: _dec('City (optional)'),
            onChanged: onCityChanged,
          ),
          const SizedBox(height: 12),

          _DropField('Education', education,
            ['high_school', 'bachelor', 'master', 'phd', 'other'],
            onEducationChanged),
          const SizedBox(height: 12),

          TextFormField(
            initialValue: occupation,
            decoration: _dec('Occupation (optional)'),
            onChanged: onOccupationChanged,
          ),
          const SizedBox(height: 12),

          _DropField('Marital status', maritalStatus,
            ['single', 'divorced', 'widowed'], onMaritalChanged),
          const SizedBox(height: 4),

          CheckboxListTile(
            value: hasChildren,
            onChanged: onHasChildrenChanged,
            title: const Text('I have children'),
            activeColor: AppColors.primary,
            contentPadding: EdgeInsets.zero,
          ),
          const SizedBox(height: 8),

          TextFormField(
            initialValue: bio,
            maxLines: 4,
            maxLength: 500,
            decoration: _dec('About me (optional)'),
            onChanged: onBioChanged,
          ),
          const SizedBox(height: 20),

          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: loading ? null : onSave,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: loading
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Submit for Review', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
            ),
          ),
        ],
      ),
    );
  }

  InputDecoration _dec(String label) => InputDecoration(
    labelText: label,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
  );
}

class _DropField extends StatelessWidget {
  final String label, value;
  final List<String> options;
  final void Function(String?) onChanged;
  const _DropField(this.label, this.value, this.options, this.onChanged);

  @override
  Widget build(BuildContext context) => DropdownButtonFormField<String>(
    value: value,
    decoration: InputDecoration(
      labelText: label,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    ),
    items: options.map((o) => DropdownMenuItem(value: o, child: Text(_label(o)))).toList(),
    onChanged: onChanged,
  );

  String _label(String v) => switch (v) {
    'male'        => 'Male',
    'female'      => 'Female',
    'high_school' => 'High School',
    'bachelor'    => 'Bachelor',
    'master'      => 'Master',
    'phd'         => 'PhD',
    'other'       => 'Other',
    'single'      => 'Single',
    'divorced'    => 'Divorced',
    'widowed'     => 'Widowed',
    _             => v,
  };
}

// ─── Empty State ──────────────────────────────────────────────────────────────

class _EMarryEmpty extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;
  const _EMarryEmpty({required this.icon, required this.title, required this.subtitle, this.actionLabel, this.onAction});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 80, height: 80,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 40, color: AppColors.primary),
          ),
          const SizedBox(height: 16),
          Text(title, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: context.colors.navyText), textAlign: TextAlign.center),
          const SizedBox(height: 8),
          Text(subtitle, style: const TextStyle(fontSize: 14, color: AppColors.textGrey), textAlign: TextAlign.center),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: onAction,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: Text(actionLabel!, style: const TextStyle(fontWeight: FontWeight.w800)),
            ),
          ],
        ],
      ),
    ),
  );
}
