import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../providers/elearning_provider.dart';

class ELearningScreen extends ConsumerStatefulWidget {
  const ELearningScreen({super.key});

  @override
  ConsumerState<ELearningScreen> createState() => _ELearningScreenState();
}

class _ELearningScreenState extends ConsumerState<ELearningScreen> {
  final _searchCtrl = TextEditingController();
  String _selectedSort = 'newest';
  int? _selectedCategory;

  // Filter chips mapped to sort values + isFree flag
  String _activeFilter = 'all'; // all | popular | newest | free | rating

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  void _applySearch() {
    ref.read(elearningFiltersProvider.notifier).update(
        (f) => f.copyWith(search: _searchCtrl.text.trim().isEmpty ? null : _searchCtrl.text.trim()));
  }

  void _applyFilterChip(String filter) {
    setState(() => _activeFilter = filter);
    switch (filter) {
      case 'popular':
        ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(sort: 'popular', isFree: null));
        setState(() => _selectedSort = 'popular');
      case 'newest':
        ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(sort: 'newest', isFree: null));
        setState(() => _selectedSort = 'newest');
      case 'free':
        ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(sort: 'newest', isFree: true));
      case 'rating':
        ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(sort: 'rating', isFree: null));
        setState(() => _selectedSort = 'rating');
      default: // all
        ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(sort: 'newest', isFree: null));
        setState(() => _selectedSort = 'newest');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      
      body: CustomScrollView(
        slivers: [
          // ── App Bar ────────────────────────────────────────────────────────
          SliverAppBar(
            pinned: true,
            
            elevation: 0,
            title: const Text(
              'eLearning',
              style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w800, fontSize: 20),
            ),
            actions: [
              _AppBarAction(
                icon: Icons.cast_for_education_rounded,
                label: 'Teach',
                color: context.colors.navyText,
                onTap: () => context.push('/elearning/instructor'),
              ),
              _AppBarAction(
                icon: Icons.school_rounded,
                label: 'My Learning',
                color: AppColors.primary,
                onTap: () => context.push('/elearning/my-learning'),
              ),
              const SizedBox(width: 4),
            ],
          ),

          // ── Search Bar ────────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _searchCtrl,
                      onSubmitted: (_) => _applySearch(),
                      decoration: InputDecoration(
                        hintText: 'Search courses…',
                        hintStyle: TextStyle(color: Colors.grey[400], fontSize: 14),
                        prefixIcon: const Icon(Icons.search, color: AppColors.primary, size: 20),
                        suffixIcon: _searchCtrl.text.isNotEmpty
                            ? IconButton(
                                icon: const Icon(Icons.clear, size: 18),
                                onPressed: () {
                                  _searchCtrl.clear();
                                  ref.read(elearningFiltersProvider.notifier)
                                      .update((f) => f.copyWith(search: null));
                                },
                              )
                            : null,
                        filled: true,
                        fillColor: context.colors.cardBg,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: _applySearch,
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    ),
                    child: const Text('Search'),
                  ),
                ],
              ),
            ),
          ),

          // ── Filter Chips Row: All | Popular | Newest | Free | Top Rated ──
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final chip in [
                      ('all', 'All'),
                      ('popular', 'Popular'),
                      ('newest', 'Newest'),
                      ('free', 'Free'),
                      ('rating', 'Top Rated'),
                    ])
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: GestureDetector(
                          onTap: () => _applyFilterChip(chip.$1),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
                            decoration: BoxDecoration(
                              color: _activeFilter == chip.$1 ? AppColors.secondary : Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: _activeFilter == chip.$1
                                    ? AppColors.secondary
                                    : Colors.grey[300]!,
                              ),
                            ),
                            child: Text(
                              chip.$2,
                              style: TextStyle(
                                color: _activeFilter == chip.$1 ? Colors.white : AppColors.secondary,
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),

          // ── Category Chips ────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Consumer(builder: (_, ref, __) {
              final cats = ref.watch(elearningCategoriesProvider);
              return cats.when(
                loading: () => const SizedBox(height: 48),
                error: (_, __) => const SizedBox.shrink(),
                data: (categories) => SizedBox(
                  height: 48,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    children: [
                      _CategoryChip(
                        label: 'All',
                        selected: _selectedCategory == null,
                        onTap: () {
                          setState(() => _selectedCategory = null);
                          ref.read(elearningFiltersProvider.notifier)
                              .update((f) => f.copyWith(categoryId: null));
                        },
                      ),
                      ...categories.map((cat) => _CategoryChip(
                            label: cat.name,
                            selected: _selectedCategory == cat.id,
                            onTap: () {
                              setState(() => _selectedCategory = cat.id);
                              ref.read(elearningFiltersProvider.notifier)
                                  .update((f) => f.copyWith(categoryId: cat.id));
                            },
                          )),
                    ],
                  ),
                ),
              );
            }),
          ),

          // ── Sort Tabs ─────────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final tab in [
                      ('newest', 'Newest'),
                      ('popular', 'Popular'),
                      ('rating', 'Top Rated'),
                      ('price_asc', 'Price ↑'),
                    ])
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(tab.$2, style: const TextStyle(fontSize: 12)),
                          selected: _selectedSort == tab.$1,
                          selectedColor: AppColors.primary,
                          labelStyle: TextStyle(
                            color: _selectedSort == tab.$1 ? Colors.white : AppColors.secondary,
                            fontWeight: FontWeight.w600,
                          ),
                          onSelected: (_) {
                            setState(() => _selectedSort = tab.$1);
                            ref.read(elearningFiltersProvider.notifier)
                                .update((f) => f.copyWith(sort: tab.$1));
                          },
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),

          // ── "Popular Courses" heading + See All ───────────────────────────
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              child: Row(
                children: [
                  const Text(
                    'Popular Courses',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText),
                  ),
                  const Spacer(),
                  TextButton(
                    onPressed: () {
                      _applyFilterChip('popular');
                    },
                    child: const Text('See All',
                        style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600, fontSize: 13)),
                  ),
                ],
              ),
            ),
          ),

          // ── Course List ───────────────────────────────────────────────────
          Consumer(builder: (_, ref, __) {
            final coursesAsync = ref.watch(elearningCoursesProvider);
            return coursesAsync.when(
              loading: () => SliverList(
                delegate: SliverChildBuilderDelegate(
                  (_, __) => const _CourseCardSkeleton(),
                  childCount: 5,
                ),
              ),
              error: (e, _) => SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(40),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                    const SizedBox(height: 12),
                    Text(e.toString(),
                        textAlign: TextAlign.center, style: const TextStyle(color: Colors.grey)),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      onPressed: () => ref.invalidate(elearningCoursesProvider),
                      child: const Text('Retry'),
                    ),
                  ]),
                ),
              ),
              data: (result) {
                final courses = result['courses'] as List<ELearningCourse>;
                if (courses.isEmpty) {
                  return SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(40),
                      child: Column(children: [
                        Icon(Icons.school_rounded, size: 64, color: Colors.grey[300]),
                        const SizedBox(height: 16),
                        Text('No courses found',
                            style: TextStyle(
                                color: Colors.grey[500], fontSize: 16, fontWeight: FontWeight.w600)),
                      ]),
                    ),
                  );
                }
                return SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 0),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (_, i) => _CourseCard(course: courses[i]),
                      childCount: courses.length,
                    ),
                  ),
                );
              },
            );
          }),
          const SliverPadding(padding: EdgeInsets.only(bottom: 80)),
        ],
      ),
    );
  }
}

// ── AppBar action with icon + label ──────────────────────────────────────────
class _AppBarAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _AppBarAction(
      {required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: 2),
            Text(label, style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

// ── Category Chip ─────────────────────────────────────────────────────────────
class _CategoryChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _CategoryChip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: selected ? AppColors.primary : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: selected ? AppColors.primary : Colors.grey[300]!),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : AppColors.secondary,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ),
      ),
    );
  }
}

// ── Course Card — horizontal list layout ──────────────────────────────────────
class _CourseCard extends StatelessWidget {
  final ELearningCourse course;
  const _CourseCard({required this.course});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/elearning/course/${course.slug}'),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
                color: Colors.black.withValues(alpha: 0.05),
                blurRadius: 8,
                offset: const Offset(0, 2))
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Square thumbnail 80x80
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: course.thumbnail != null
                  ? CachedNetworkImage(
                      imageUrl: course.thumbnail!,
                      width: 80,
                      height: 80,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => _thumbPlaceholder(),
                      errorWidget: (_, __, ___) => _thumbPlaceholder(),
                    )
                  : _thumbPlaceholder(),
            ),
            const SizedBox(width: 12),
            // Right side content
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title
                  Text(
                    course.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: context.colors.navyText,
                        height: 1.3),
                  ),
                  const SizedBox(height: 6),
                  // Instructor row
                  if (course.instructor != null)
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 9,
                          backgroundImage: course.instructor!.avatar != null
                              ? NetworkImage(course.instructor!.avatar!)
                              : null,
                          backgroundcolor: context.colors.navyText,
                          child: course.instructor!.avatar == null
                              ? Text(course.instructor!.name[0],
                                  style: const TextStyle(color: Colors.white, fontSize: 8))
                              : null,
                        ),
                        const SizedBox(width: 5),
                        Expanded(
                          child: Text(
                            course.instructor!.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                          ),
                        ),
                      ],
                    ),
                  const SizedBox(height: 5),
                  // Star rating + review count
                  Row(
                    children: [
                      const Icon(Icons.star_rounded, color: Colors.amber, size: 13),
                      const SizedBox(width: 2),
                      Text(course.rating.toStringAsFixed(1),
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700)),
                      const SizedBox(width: 3),
                      Text('(${course.totalReviews})',
                          style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                    ],
                  ),
                  const SizedBox(height: 6),
                  // Price row
                  course.isFree
                      ? Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                              color: Colors.green[50], borderRadius: BorderRadius.circular(6)),
                          child: const Text('FREE',
                              style: TextStyle(
                                  color: Colors.green,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800)),
                        )
                      : Row(
                          children: [
                            Text(
                              '\$${course.effectivePrice.toStringAsFixed(2)}',
                              style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.primary),
                            ),
                            if (course.discountPrice != null) ...[
                              const SizedBox(width: 6),
                              Text(
                                '\$${course.price.toStringAsFixed(2)}',
                                style: const TextStyle(
                                    fontSize: 11,
                                    color: Colors.grey,
                                    decoration: TextDecoration.lineThrough),
                              ),
                            ],
                          ],
                        ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _thumbPlaceholder() {
    return Container(
      width: 80,
      height: 80,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [AppColors.secondary, Color(0xFF3D2B8E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: const Icon(Icons.school_rounded, color: Colors.white54, size: 28),
    );
  }
}

// ── Skeleton card ─────────────────────────────────────────────────────────────
class _CourseCardSkeleton extends StatelessWidget {
  const _CourseCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                  color: Colors.grey[200], borderRadius: BorderRadius.circular(12))),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(
                  height: 13,
                  width: double.infinity,
                  decoration: BoxDecoration(
                      color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
              const SizedBox(height: 6),
              Container(
                  height: 11,
                  width: 120,
                  decoration: BoxDecoration(
                      color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
              const SizedBox(height: 8),
              Container(
                  height: 11,
                  width: 80,
                  decoration: BoxDecoration(
                      color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
              const SizedBox(height: 6),
              Container(
                  height: 13,
                  width: 60,
                  decoration: BoxDecoration(
                      color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
            ]),
          ),
        ],
      ),
    );
  }
}
