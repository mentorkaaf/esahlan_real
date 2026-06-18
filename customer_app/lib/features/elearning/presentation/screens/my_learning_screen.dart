import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../providers/elearning_provider.dart';

class MyLearningScreen extends ConsumerWidget {
  const MyLearningScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final enrollmentsAsync = ref.watch(myLearningProvider);
    final certsAsync = ref.watch(myCertificatesProvider);

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        title: Text('My Learning', style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: context.colors.navyText),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: AppColors.primary),
            onPressed: () {
              ref.invalidate(myLearningProvider);
              ref.invalidate(myCertificatesProvider);
            },
          ),
        ],
      ),
      body: enrollmentsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          Text(e.toString(), textAlign: TextAlign.center),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: () => ref.invalidate(myLearningProvider), child: const Text('Retry')),
        ])),
        data: (enrollments) {
          final inProgress  = enrollments.where((e) => e.status == 'active').toList();
          final completed   = enrollments.where((e) => e.status == 'completed').toList();

          return RefreshIndicator(
            color: AppColors.primary,
            onRefresh: () async {
              ref.invalidate(myLearningProvider);
              ref.invalidate(myCertificatesProvider);
            },
            child: CustomScrollView(
              slivers: [
                // ── Stats header ────────────────────────────────────────────
                SliverToBoxAdapter(
                  child: Container(
                    margin: const EdgeInsets.all(16),
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [AppColors.secondary, Color(0xFF3D2B8E)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _StatItem(count: enrollments.length, label: 'Enrolled', icon: Icons.school_rounded),
                        _StatItem(count: completed.length, label: 'Completed', icon: Icons.check_circle_rounded),
                        certsAsync.when(
                          data: (certs) => _StatItem(count: certs.length, label: 'Certificates', icon: Icons.workspace_premium_rounded),
                          loading: () => _StatItem(count: 0, label: 'Certificates', icon: Icons.workspace_premium_rounded),
                          error: (_, __) => _StatItem(count: 0, label: 'Certificates', icon: Icons.workspace_premium_rounded),
                        ),
                      ],
                    ),
                  ),
                ),

                // ── In Progress ─────────────────────────────────────────────
                if (inProgress.isNotEmpty) ...[
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                      child: Text('In Progress', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                    ),
                  ),
                  SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (_, i) => _EnrollmentCard(enrollment: inProgress[i]),
                      childCount: inProgress.length,
                    ),
                  ),
                ],

                // ── Completed ───────────────────────────────────────────────
                if (completed.isNotEmpty) ...[
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                      child: Text('Completed', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                    ),
                  ),
                  SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (_, i) => _EnrollmentCard(enrollment: completed[i], isCompleted: true),
                      childCount: completed.length,
                    ),
                  ),
                ],

                // ── Certificates ────────────────────────────────────────────
                certsAsync.when(
                  data: (certs) {
                    if (certs.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());
                    return SliverList(
                      delegate: SliverChildListDelegate([
                        Padding(
                          padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                          child: Text('Certificates', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                        ),
                        ...certs.map((c) => _CertificateCard(cert: c)),
                      ]),
                    );
                  },
                  loading: () => const SliverToBoxAdapter(child: SizedBox.shrink()),
                  error: (_, __) => const SliverToBoxAdapter(child: SizedBox.shrink()),
                ),

                // ── Empty state ─────────────────────────────────────────────
                if (enrollments.isEmpty)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(40),
                      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(Icons.school_rounded, size: 80, color: Colors.grey[300]),
                        const SizedBox(height: 16),
                        Text('No courses yet', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.grey[500])),
                        const SizedBox(height: 8),
                        Text('Browse courses and start learning today', style: TextStyle(color: Colors.grey[400])),
                        const SizedBox(height: 20),
                        FilledButton(
                          onPressed: () => context.pop(),
                          style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
                          child: const Text('Browse Courses'),
                        ),
                      ]),
                    ),
                  ),

                const SliverPadding(padding: EdgeInsets.only(bottom: 80)),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _StatItem extends StatelessWidget {
  final int count;
  final String label;
  final IconData icon;
  const _StatItem({required this.count, required this.label, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Icon(icon, color: Colors.white70, size: 24),
      const SizedBox(height: 6),
      Text('$count', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900)),
      Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12)),
    ]);
  }
}

class _EnrollmentCard extends StatelessWidget {
  final ELearningEnrollment enrollment;
  final bool isCompleted;
  const _EnrollmentCard({required this.enrollment, this.isCompleted = false});

  @override
  Widget build(BuildContext context) {
    final course = enrollment.course;
    return GestureDetector(
      onTap: () => context.push('/elearning/course/${course.slug}'),
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
        ),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: course.thumbnail != null
                  ? Image.network(course.thumbnail!, width: 72, height: 72, fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _placeholder(context))
                  : _placeholder(context),
            ),
            SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(course.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText)),
                  if (course.instructor != null)
                    Text(course.instructor!.name, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                  const SizedBox(height: 8),
                  if (!isCompleted) ...[
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text('${enrollment.progressPercent}%', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
                      Text('${enrollment.completedLessons}/${enrollment.totalLessons} lessons', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                    ]),
                    const SizedBox(height: 4),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: enrollment.progressPercent / 100,
                        backgroundColor: Colors.grey[200],
                        valueColor: const AlwaysStoppedAnimation<Color>(AppColors.primary),
                        minHeight: 6,
                      ),
                    ),
                  ] else
                    Row(children: const [
                      Icon(Icons.check_circle_rounded, color: Colors.green, size: 16),
                      SizedBox(width: 4),
                      Text('Completed', style: TextStyle(fontSize: 12, color: Colors.green, fontWeight: FontWeight.w600)),
                    ]),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _placeholder(BuildContext context) => Container(
    width: 72, height: 72,
    decoration: BoxDecoration(color: context.colors.navyText, borderRadius: BorderRadius.circular(10)),
    child: const Icon(Icons.school_rounded, color: Colors.white54, size: 28),
  );
}

class _CertificateCard extends StatelessWidget {
  final ELearningCertificate cert;
  const _CertificateCard({required this.cert});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/elearning/certificate/${cert.id}'),
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFFFF8E1), Color(0xFFFFF3CD)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.amber[200]!),
        ),
        child: Row(
          children: [
            const Icon(Icons.workspace_premium_rounded, size: 40, color: Colors.amber),
            SizedBox(width: 14),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(cert.course.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText)),
                const SizedBox(height: 4),
                Text(cert.certificateNumber, style: TextStyle(fontSize: 11, color: Colors.grey[600], fontFamily: 'monospace')),
                Text('Issued: ${cert.issuedAt}', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ]),
            ),
            const Icon(Icons.chevron_right_rounded, color: Colors.amber),
          ],
        ),
      ),
    );
  }
}
