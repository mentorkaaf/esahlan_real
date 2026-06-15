import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../providers/elearning_provider.dart';

/// Entry point for the instructor area. Decides what to show based on the
/// user's instructor application status.
class InstructorHubScreen extends ConsumerStatefulWidget {
  const InstructorHubScreen({super.key});

  @override
  ConsumerState<InstructorHubScreen> createState() => _InstructorHubScreenState();
}

class _InstructorHubScreenState extends ConsumerState<InstructorHubScreen> {
  @override
  void initState() {
    super.initState();
    // Always re-fetch fresh status when opening the hub so that an approval/
    // rejection that happened on the admin side is reflected immediately.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.invalidate(instructorStatusProvider);
    });
  }

  Future<void> _refresh() async {
    ref.invalidate(instructorStatusProvider);
    await ref.read(instructorStatusProvider.future);
  }

  @override
  Widget build(BuildContext context) {
    final statusAsync = ref.watch(instructorStatusProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('Instructor', style: TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: AppColors.primary),
            onPressed: _refresh,
            tooltip: 'Refresh status',
          ),
        ],
      ),
      body: statusAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => _ErrorState(onRetry: () => ref.invalidate(instructorStatusProvider)),
        data: (status) {
          switch (status.applicationStatus) {
            case 'approved':
              return const _ApprovedDashboard();
            case 'pending':
              return _PendingState(onRefresh: _refresh);
            case 'rejected':
              return _RejectedState(onReapply: () => context.push('/elearning/instructor/apply'));
            default:
              return _NotAppliedState(onApply: () => context.push('/elearning/instructor/apply'));
          }
        },
      ),
    );
  }
}

// ── Not applied: CTA ────────────────────────────────────────────────────────────
class _NotAppliedState extends StatelessWidget {
  final VoidCallback onApply;
  const _NotAppliedState({required this.onApply});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 20),
        Center(
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: const Icon(Icons.cast_for_education_rounded, size: 64, color: AppColors.primary),
          ),
        ),
        const SizedBox(height: 24),
        const Text('Become an Instructor',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AppColors.secondary)),
        const SizedBox(height: 10),
        Text('Teach what you love. Create courses, build your audience, and earn from every enrollment.',
            textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600], fontSize: 14, height: 1.5)),
        const SizedBox(height: 28),
        ..._benefits,
        const SizedBox(height: 28),
        FilledButton(
          style: FilledButton.styleFrom(
            backgroundColor: AppColors.primary,
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
          onPressed: onApply,
          child: const Text('Apply Now', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        ),
      ],
    );
  }

  List<Widget> get _benefits => const [
        _Benefit(icon: Icons.video_library_rounded, title: 'Build courses', sub: 'Sections, video lessons, quizzes & more'),
        _Benefit(icon: Icons.groups_rounded, title: 'Reach students', sub: 'Get discovered across the eSahlan app'),
        _Benefit(icon: Icons.payments_rounded, title: 'Earn money', sub: 'Get paid for every enrollment'),
      ];
}

class _Benefit extends StatelessWidget {
  final IconData icon;
  final String title, sub;
  const _Benefit({required this.icon, required this.title, required this.sub});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: AppColors.primary, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.secondary)),
              const SizedBox(height: 2),
              Text(sub, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
            ]),
          ),
        ],
      ),
    );
  }
}

// ── Pending ─────────────────────────────────────────────────────────────────────
class _PendingState extends StatelessWidget {
  final Future<void> Function() onRefresh;
  const _PendingState({required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: onRefresh,
      child: ListView(
        children: [
          SizedBox(height: MediaQuery.of(context).size.height * 0.18),
          Center(
            child: Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(color: Colors.orange.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.hourglass_top_rounded, size: 60, color: Colors.orange),
            ),
          ),
          const SizedBox(height: 24),
          const Text('Application Under Review',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 32),
            child: Text('Your instructor application is being reviewed. Admin usually responds within 24 hours.',
                textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600], height: 1.5)),
          ),
          const SizedBox(height: 24),
          Center(
            child: OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.primary,
                side: const BorderSide(color: AppColors.primary),
              ),
              onPressed: onRefresh,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Check Status'),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Rejected ──────────────────────────────────────────────────────────────────
class _RejectedState extends StatelessWidget {
  final VoidCallback onReapply;
  const _RejectedState({required this.onReapply});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(color: Colors.red.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: const Icon(Icons.cancel_rounded, size: 60, color: Colors.red),
          ),
          const SizedBox(height: 24),
          const Text('Application Not Approved',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 10),
          Text('Your previous application was not approved. You can update your details and apply again.',
              textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600], height: 1.5)),
          const SizedBox(height: 24),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
            onPressed: onReapply,
            child: const Text('Apply Again'),
          ),
        ]),
      ),
    );
  }
}

// ── Approved: dashboard ─────────────────────────────────────────────────────────
class _ApprovedDashboard extends ConsumerWidget {
  const _ApprovedDashboard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashAsync = ref.watch(instructorDashboardProvider);
    final coursesAsync = ref.watch(instructorCoursesProvider);

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () async {
        ref.invalidate(instructorDashboardProvider);
        ref.invalidate(instructorCoursesProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Stats
          dashAsync.when(
            loading: () => const SizedBox(height: 120, child: Center(child: CircularProgressIndicator(color: AppColors.primary))),
            error: (e, _) => const SizedBox.shrink(),
            data: (d) => Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [AppColors.secondary, Color(0xFF3D2B8E)]),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Column(children: [
                Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
                  _Stat(value: '${d.totalCourses}', label: 'Courses', icon: Icons.menu_book_rounded),
                  _Stat(value: '${d.totalStudents}', label: 'Students', icon: Icons.groups_rounded),
                  _Stat(value: d.rating.toStringAsFixed(1), label: 'Rating', icon: Icons.star_rounded),
                ]),
                const Divider(color: Colors.white24, height: 28),
                Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
                  _Stat(value: '\$${d.totalEarnings.toStringAsFixed(0)}', label: 'Earned', icon: Icons.account_balance_wallet_rounded),
                  _Stat(value: '\$${d.pendingEarnings.toStringAsFixed(0)}', label: 'Pending', icon: Icons.schedule_rounded),
                ]),
              ]),
            ),
          ),
          const SizedBox(height: 20),

          // Create course CTA
          FilledButton.icon(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.primary,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            onPressed: () async {
              await context.push('/elearning/instructor/create-course');
              ref.invalidate(instructorCoursesProvider);
            },
            icon: const Icon(Icons.add_rounded),
            label: const Text('Create New Course', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
          ),
          const SizedBox(height: 24),

          const Text('My Courses', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 12),

          coursesAsync.when(
            loading: () => const Padding(padding: EdgeInsets.all(20), child: Center(child: CircularProgressIndicator(color: AppColors.primary))),
            error: (e, _) => Text('Failed to load courses', style: TextStyle(color: Colors.grey[500])),
            data: (courses) {
              if (courses.isEmpty) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 30),
                  child: Column(children: [
                    Icon(Icons.menu_book_rounded, size: 60, color: Colors.grey[300]),
                    const SizedBox(height: 12),
                    Text('No courses yet. Create your first one!', style: TextStyle(color: Colors.grey[500])),
                  ]),
                );
              }
              return Column(children: courses.map((c) => _InstructorCourseCard(course: c)).toList());
            },
          ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String value, label;
  final IconData icon;
  const _Stat({required this.value, required this.label, required this.icon});

  @override
  Widget build(BuildContext context) => Column(children: [
        Icon(icon, color: Colors.white70, size: 22),
        const SizedBox(height: 6),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
        Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
      ]);
}

class _InstructorCourseCard extends ConsumerWidget {
  final InstructorCourse course;
  const _InstructorCourseCard({required this.course});

  Color _statusColor(String s) {
    switch (s) {
      case 'published': return Colors.green;
      case 'pending':   return Colors.orange;
      case 'rejected':  return Colors.red;
      case 'archived':  return Colors.grey;
      default:          return Colors.blueGrey; // draft
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () async {
        await context.push('/elearning/instructor/course-builder/${course.id}', extra: course.title);
        ref.invalidate(instructorCoursesProvider);
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)],
        ),
        child: Row(children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: course.thumbnail != null
                ? Image.network(course.thumbnail!, width: 64, height: 64, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _thumb())
                : _thumb(),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(course.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.secondary)),
              const SizedBox(height: 6),
              Row(children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(color: _statusColor(course.status).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
                  child: Text(course.status.toUpperCase(),
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: _statusColor(course.status))),
                ),
                const SizedBox(width: 10),
                Icon(Icons.menu_book, size: 13, color: Colors.grey[400]),
                const SizedBox(width: 3),
                Text('${course.totalLessons}', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                const SizedBox(width: 10),
                Icon(Icons.groups, size: 13, color: Colors.grey[400]),
                const SizedBox(width: 3),
                Text('${course.totalStudents}', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
              ]),
            ]),
          ),
          const Icon(Icons.chevron_right_rounded, color: Colors.grey),
        ]),
      ),
    );
  }

  Widget _thumb() => Container(
        width: 64, height: 64,
        decoration: BoxDecoration(color: AppColors.secondary, borderRadius: BorderRadius.circular(10)),
        child: const Icon(Icons.menu_book_rounded, color: Colors.white54, size: 26),
      );
}

class _ErrorState extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorState({required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          const Text('Could not load instructor info'),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: onRetry, child: const Text('Retry')),
        ]),
      );
}
