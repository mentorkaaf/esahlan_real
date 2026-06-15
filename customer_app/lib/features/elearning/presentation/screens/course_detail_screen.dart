import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../../data/services/elearning_api_service.dart';
import '../providers/elearning_provider.dart';

class CourseDetailScreen extends ConsumerStatefulWidget {
  final String slug;
  const CourseDetailScreen({super.key, required this.slug});

  @override
  ConsumerState<CourseDetailScreen> createState() => _CourseDetailScreenState();
}

class _CourseDetailScreenState extends ConsumerState<CourseDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  bool _inWishlist = false;
  bool _purchasing = false;
  final _svc = ELearningApiService.create();

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  Future<void> _toggleWishlist(int courseId) async {
    try {
      final result = await _svc.toggleWishlist(courseId);
      setState(() => _inWishlist = result);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result ? 'Added to wishlist' : 'Removed from wishlist'), duration: const Duration(seconds: 2)),
        );
      }
    } catch (_) {}
  }

  Future<void> _purchase(int courseId, bool isFree, String slug) async {
    setState(() => _purchasing = true);
    try {
      if (isFree) {
        await _svc.enrollFree(courseId);
      } else {
        // Get price + wallet balance first
        final info = await _svc.initiatePurchase(courseId);
        final price = (info['data']?['price'] as num?)?.toDouble() ?? 0;
        final walletBalance = (info['data']?['wallet_balance'] as num?)?.toDouble() ?? 0;

        // Show payment method selection
        final method = await showModalBottomSheet<String>(
          context: context,
          backgroundColor: Colors.transparent,
          builder: (sheetCtx) => _PaymentSheet(
            price: price,
            walletBalance: walletBalance,
          ),
        );

        if (method == null) {
          setState(() => _purchasing = false);
          return;
        }

        if (method == 'wallet') {
          await _svc.verifyPurchase(courseId, paymentMethod: 'wallet');
        } else if (method.startsWith('waafi:')) {
          // method = 'waafi:PHONE:REFERENCE'
          final parts = method.split(':');
          await _svc.verifyPurchase(courseId,
              paymentMethod: 'waafi', paymentReference: parts.length > 2 ? parts[2] : '');
        }
      }
      ref.invalidate(courseDetailProvider(slug));
      ref.invalidate(myLearningProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Enrolled successfully! 🎉'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceAll('Exception: ', '')), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _purchasing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(courseDetailProvider(widget.slug));

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(e.toString(), textAlign: TextAlign.center),
            const SizedBox(height: 12),
            ElevatedButton(onPressed: () => ref.invalidate(courseDetailProvider(widget.slug)), child: const Text('Retry')),
          ]),
        ),
        data: (data) {
          final course = ELearningCourse.fromJson(data);
          final sections = (data['sections'] as List<dynamic>? ?? [])
              .map((s) => ELearningSection.fromJson(s as Map<String, dynamic>))
              .toList();
          final reviews = (data['reviews'] as List<dynamic>? ?? [])
              .map((r) => ELearningReview.fromJson(r as Map<String, dynamic>))
              .toList();

          return CustomScrollView(
            slivers: [
              // ── Hero ──────────────────────────────────────────────────────
              SliverAppBar(
                expandedHeight: 220,
                pinned: true,
                backgroundColor: AppColors.secondary,
                leading: IconButton(
                  icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white),
                  onPressed: () => context.pop(),
                ),
                actions: [
                  IconButton(
                    icon: Icon(
                      _inWishlist ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                      color: _inWishlist ? Colors.redAccent : Colors.white,
                    ),
                    onPressed: () => _toggleWishlist(course.id),
                  ),
                ],
                flexibleSpace: FlexibleSpaceBar(
                  background: Stack(
                    fit: StackFit.expand,
                    children: [
                      course.thumbnail != null
                          ? CachedNetworkImage(
                              imageUrl: course.thumbnail!,
                              fit: BoxFit.cover,
                              placeholder: (_, __) => _heroPlaceholder(),
                              errorWidget: (_, __, ___) => _heroPlaceholder(),
                            )
                          : _heroPlaceholder(),
                      Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Colors.transparent, AppColors.secondary.withValues(alpha: 0.9)],
                          ),
                        ),
                      ),
                      if (course.trailerVideo != null)
                        Center(
                          child: Container(
                            width: 56, height: 56,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.2),
                              shape: BoxShape.circle,
                              border: Border.all(color: Colors.white, width: 2),
                            ),
                            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 32),
                          ),
                        ),
                    ],
                  ),
                ),
              ),

              // ── Course header ──────────────────────────────────────────────
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (course.category != null)
                        Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(course.category!.name, style: const TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.w600)),
                        ),
                      Text(course.title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.secondary)),
                      if (course.subtitle != null) ...[
                        const SizedBox(height: 6),
                        Text(course.subtitle!, style: TextStyle(fontSize: 14, color: Colors.grey[600])),
                      ],
                      const SizedBox(height: 12),
                      // Instructor
                      if (course.instructor != null)
                        Row(children: [
                          CircleAvatar(
                            radius: 16,
                            backgroundImage: course.instructor!.avatar != null ? NetworkImage(course.instructor!.avatar!) : null,
                            backgroundColor: AppColors.secondary,
                            child: course.instructor!.avatar == null
                                ? Text(course.instructor!.name[0], style: const TextStyle(color: Colors.white, fontSize: 12))
                                : null,
                          ),
                          const SizedBox(width: 8),
                          Text(course.instructor!.name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.secondary)),
                        ]),
                      const SizedBox(height: 12),
                      // Meta row
                      Wrap(
                        spacing: 16,
                        runSpacing: 6,
                        children: [
                          _MetaBadge(icon: Icons.star_rounded, text: '${course.rating.toStringAsFixed(1)} (${course.totalReviews})', color: Colors.amber),
                          _MetaBadge(icon: Icons.people_rounded, text: '${course.totalStudents} students', color: AppColors.primary),
                          _MetaBadge(icon: Icons.access_time_rounded, text: '${course.durationHours.toStringAsFixed(1)}h', color: Colors.teal),
                          _MetaBadge(icon: Icons.bar_chart_rounded, text: course.level[0].toUpperCase() + course.level.substring(1), color: AppColors.secondary),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              // ── Price + Enroll ─────────────────────────────────────────────
              SliverToBoxAdapter(
                child: Container(
                  margin: const EdgeInsets.all(16),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)],
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (course.isFree)
                              const Text('FREE', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Colors.green))
                            else ...[
                              Text(
                                '\$${course.effectivePrice.toStringAsFixed(2)}',
                                style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppColors.secondary),
                              ),
                              if (course.discountPrice != null)
                                Text(
                                  '\$${course.price.toStringAsFixed(2)}',
                                  style: const TextStyle(fontSize: 14, color: Colors.grey, decoration: TextDecoration.lineThrough),
                                ),
                            ],
                          ],
                        ),
                      ),
                      SizedBox(
                        height: 48,
                        child: FilledButton(
                          onPressed: _purchasing ? null : () {
                            if (course.isEnrolled) {
                              context.push('/elearning/my-learning');
                            } else {
                              _purchase(course.id, course.isFree, course.slug);
                            }
                          },
                          style: FilledButton.styleFrom(
                            backgroundColor: course.isEnrolled ? Colors.green : AppColors.primary,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            padding: const EdgeInsets.symmetric(horizontal: 24),
                          ),
                          child: _purchasing
                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                              : Text(
                                  course.isEnrolled ? 'Continue Learning' : (course.isFree ? 'Enroll Free' : 'Buy Now'),
                                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                                ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // ── Tabs ───────────────────────────────────────────────────────
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  child: TabBar(
                    controller: _tabCtrl,
                    labelColor: AppColors.primary,
                    unselectedLabelColor: Colors.grey,
                    indicatorColor: AppColors.primary,
                    labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                    tabs: const [
                      Tab(text: 'Overview'),
                      Tab(text: 'Curriculum'),
                      Tab(text: 'Reviews'),
                    ],
                  ),
                ),
              ),

              // ── Tab Views (static, no need for nested scroll) ──────────────
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 600,
                  child: TabBarView(
                    controller: _tabCtrl,
                    children: [
                      _OverviewTab(course: course),
                      _CurriculumTab(sections: sections, isEnrolled: course.isEnrolled),
                      _ReviewsTab(reviews: reviews, rating: course.rating),
                    ],
                  ),
                ),
              ),
              const SliverPadding(padding: EdgeInsets.only(bottom: 80)),
            ],
          );
        },
      ),
    );
  }

  Widget _heroPlaceholder() => Container(
    color: AppColors.secondary,
    child: const Icon(Icons.school_rounded, size: 80, color: Colors.white30),
  );
}

// ── Overview Tab ──────────────────────────────────────────────────────────────
class _OverviewTab extends StatelessWidget {
  final ELearningCourse course;
  const _OverviewTab({required this.course});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (course.description != null) ...[
            const Text('About this course', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
            const SizedBox(height: 8),
            Text(course.description!, style: TextStyle(fontSize: 14, color: Colors.grey[700], height: 1.6)),
            const SizedBox(height: 20),
          ],
          if (course.learningOutcomes.isNotEmpty) ...[
            const Text('What you\'ll learn', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
            const SizedBox(height: 10),
            ...course.learningOutcomes.map((o) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.check_circle_rounded, color: Colors.green, size: 18),
                const SizedBox(width: 8),
                Expanded(child: Text(o, style: const TextStyle(fontSize: 13))),
              ]),
            )),
            const SizedBox(height: 20),
          ],
          if (course.requirements.isNotEmpty) ...[
            const Text('Requirements', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
            const SizedBox(height: 10),
            ...course.requirements.map((r) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.circle, color: AppColors.primary, size: 8),
                const SizedBox(width: 10),
                Expanded(child: Text(r, style: const TextStyle(fontSize: 13))),
              ]),
            )),
          ],
        ],
      ),
    );
  }
}

// ── Curriculum Tab ────────────────────────────────────────────────────────────
class _CurriculumTab extends StatefulWidget {
  final List<ELearningSection> sections;
  final bool isEnrolled;
  const _CurriculumTab({required this.sections, required this.isEnrolled});

  @override
  State<_CurriculumTab> createState() => _CurriculumTabState();
}

class _CurriculumTabState extends State<_CurriculumTab> {
  final Set<int> _expanded = {0};

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: widget.sections.length,
      itemBuilder: (_, i) {
        final section = widget.sections[i];
        final isOpen = _expanded.contains(i);
        return Container(
          margin: const EdgeInsets.only(bottom: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            children: [
              ListTile(
                onTap: () => setState(() { if (isOpen) _expanded.remove(i); else _expanded.add(i); }),
                leading: Icon(isOpen ? Icons.folder_open_rounded : Icons.folder_rounded, color: AppColors.primary),
                title: Text(section.title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.secondary)),
                subtitle: Text('${section.lessons.length} lessons', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                trailing: Icon(isOpen ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded),
              ),
              if (isOpen)
                ...section.lessons.map((lesson) {
                  final canOpen = widget.isEnrolled || lesson.isFreePreview;
                  return GestureDetector(
                    onTap: canOpen
                        ? () => context.push('/elearning/lesson/${lesson.id}')
                        : null,
                    child: Container(
                      color: Colors.transparent,
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                      child: Row(
                        children: [
                          Icon(_lessonIcon(lesson.type), size: 16,
                              color: canOpen ? _lessonColor(lesson.type) : Colors.grey[400]),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(lesson.title,
                                style: TextStyle(fontSize: 13,
                                    color: canOpen ? Colors.black87 : Colors.grey[500])),
                          ),
                          if (lesson.formattedDuration.isNotEmpty)
                            Text(lesson.formattedDuration,
                                style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                          const SizedBox(width: 6),
                          if (lesson.isLocked && !widget.isEnrolled)
                            Icon(Icons.lock_rounded, size: 14, color: Colors.grey[400])
                          else if (lesson.isFreePreview && !widget.isEnrolled)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                  color: Colors.teal[50], borderRadius: BorderRadius.circular(4)),
                              child: Text('Preview',
                                  style: TextStyle(fontSize: 10,
                                      color: Colors.teal[700], fontWeight: FontWeight.w600)),
                            )
                          else if (widget.isEnrolled)
                            Icon(Icons.play_circle_outline_rounded, size: 16, color: AppColors.primary),
                        ],
                      ),
                    ),
                  );
                }),
            ],
          ),
        );
      },
    );
  }

  IconData _lessonIcon(String type) => switch (type) {
    'video'      => Icons.play_circle_rounded,
    'pdf'        => Icons.picture_as_pdf_rounded,
    'quiz'       => Icons.quiz_rounded,
    'assignment' => Icons.assignment_rounded,
    'live'       => Icons.videocam_rounded,
    _            => Icons.description_rounded,
  };

  Color _lessonColor(String type) => switch (type) {
    'video'      => Colors.blue,
    'pdf'        => Colors.red,
    'quiz'       => Colors.purple,
    'assignment' => Colors.orange,
    'live'       => Colors.green,
    _            => Colors.grey,
  };
}

// ── Reviews Tab ───────────────────────────────────────────────────────────────
class _ReviewsTab extends StatelessWidget {
  final List<ELearningReview> reviews;
  final double rating;
  const _ReviewsTab({required this.reviews, required this.rating});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Rating summary
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
          child: Row(
            children: [
              Column(children: [
                Text(rating.toStringAsFixed(1), style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, color: AppColors.secondary)),
                Row(children: List.generate(5, (i) => Icon(
                  i < rating.round() ? Icons.star_rounded : Icons.star_outline_rounded,
                  color: Colors.amber, size: 18,
                ))),
                const SizedBox(height: 4),
                Text('${reviews.length} reviews', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
              ]),
            ],
          ),
        ),
        const SizedBox(height: 12),
        ...reviews.map((r) => Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              CircleAvatar(
                radius: 16, backgroundColor: AppColors.secondary,
                child: Text((r.user['name'] as String? ?? '?')[0], style: const TextStyle(color: Colors.white, fontSize: 12)),
              ),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(r.user['name'] as String? ?? 'Student', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                Text(r.createdAt, style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ])),
              Row(children: List.generate(5, (i) => Icon(
                i < r.rating ? Icons.star_rounded : Icons.star_outline_rounded,
                color: Colors.amber, size: 14,
              ))),
            ]),
            if (r.comment != null) ...[
              const SizedBox(height: 8),
              Text(r.comment!, style: TextStyle(fontSize: 13, color: Colors.grey[700], height: 1.5)),
            ],
          ]),
        )),
      ],
    );
  }
}

class _MetaBadge extends StatelessWidget {
  final IconData icon;
  final String text;
  final Color color;
  const _MetaBadge({required this.icon, required this.text, required this.color});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 14, color: color),
      const SizedBox(width: 4),
      Text(text, style: TextStyle(fontSize: 12, color: Colors.grey[600], fontWeight: FontWeight.w500)),
    ]);
  }
}

// ── Payment method selection sheet ────────────────────────────────────────────
class _PaymentSheet extends ConsumerStatefulWidget {
  final double price;
  final double walletBalance;
  const _PaymentSheet({required this.price, required this.walletBalance});

  @override
  ConsumerState<_PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends ConsumerState<_PaymentSheet> {
  final _phoneCtrl = TextEditingController();
  bool _loadingWaafi = false;

  @override
  void dispose() {
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _payWaafi() async {
    final phone = _phoneCtrl.text.trim();
    if (phone.length < 9) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Geli lambarka telefoonka saxda ah')));
      return;
    }
    setState(() => _loadingWaafi = true);
    try {
      final svc = ELearningApiService.create();
      final result = await svc.initiateWaafiPayment(
          amount: widget.price, phone: phone);
      if (!mounted) return;
      Navigator.pop(context, 'waafi:$phone:${result['reference']}');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(e.toString().replaceAll('Exception: ', '')),
                backgroundColor: Colors.red));
      }
    } finally {
      if (mounted) setState(() => _loadingWaafi = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasEnough = widget.walletBalance >= widget.price;
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Center(child: Container(width: 40, height: 4,
            decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
        const SizedBox(height: 16),
        Text('Buy Course — \$${widget.price.toStringAsFixed(2)}',
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.secondary)),
        const SizedBox(height: 4),
        Text('Dooro hab lacag bixinta', style: TextStyle(color: Colors.grey[500], fontSize: 13)),
        const SizedBox(height: 20),

        // Wallet option
        GestureDetector(
          onTap: hasEnough ? () => Navigator.pop(context, 'wallet') : null,
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: hasEnough ? AppColors.primary.withValues(alpha: 0.07) : Colors.grey[50],
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: hasEnough ? AppColors.primary : Colors.grey[300]!),
            ),
            child: Row(children: [
              Container(
                width: 44, height: 44,
                decoration: BoxDecoration(
                  color: hasEnough ? AppColors.primary : Colors.grey[200],
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(Icons.account_balance_wallet_rounded,
                    color: hasEnough ? Colors.white : Colors.grey[400], size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('eSahlan Wallet', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                Text('Balance: \$${widget.walletBalance.toStringAsFixed(2)}',
                    style: TextStyle(color: hasEnough ? Colors.grey[600] : Colors.red[400], fontSize: 13)),
                if (!hasEnough)
                  Text('Kharashku waa ka badan yahay balance-kaaga',
                      style: TextStyle(color: Colors.red[400], fontSize: 11)),
              ])),
              if (hasEnough)
                const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.primary),
            ]),
          ),
        ),
        const SizedBox(height: 12),

        // Waafi Pay option
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.purple.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.purple.withValues(alpha: 0.3)),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(
                width: 44, height: 44,
                decoration: BoxDecoration(color: Colors.purple, borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.phone_android_rounded, color: Colors.white, size: 22),
              ),
              const SizedBox(width: 14),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Waafi Pay', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                Text('Geli lambarka Waafi-ga', style: TextStyle(color: Colors.grey, fontSize: 13)),
              ])),
            ]),
            const SizedBox(height: 12),
            TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                hintText: 'e.g. 252615xxxxxx',
                prefixIcon: const Icon(Icons.phone_rounded, size: 18),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10),
                    borderSide: BorderSide(color: Colors.grey[300]!)),
                contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
              ),
            ),
            const SizedBox(height: 10),
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: Colors.purple,
                minimumSize: const Size.fromHeight(44),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: _loadingWaafi ? null : _payWaafi,
              child: _loadingWaafi
                  ? const SizedBox(width: 20, height: 20,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Pay with Waafi', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ]),
        ),
        const SizedBox(height: 8),
        Center(
          child: TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel', style: TextStyle(color: Colors.grey)),
          ),
        ),
      ]),
    );
  }
}
