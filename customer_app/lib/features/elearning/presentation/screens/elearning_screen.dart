import 'package:cached_network_image/cached_network_image.dart';
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

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  void _applySearch() {
    ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(search: _searchCtrl.text.trim().isEmpty ? null : _searchCtrl.text.trim()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      body: CustomScrollView(
        slivers: [
          // ── App Bar ────────────────────────────────────────────────────────
          SliverAppBar(
            pinned: true,
            backgroundColor: Colors.white,
            elevation: 0,
            title: const Text(
              'eLearning',
              style: TextStyle(
                color: AppColors.secondary,
                fontWeight: FontWeight.w800,
                fontSize: 20,
              ),
            ),
            actions: [
              _AppBarAction(
                icon: Icons.cast_for_education_rounded,
                label: 'Teach',
                color: AppColors.secondary,
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
                                  ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(search: null));
                                },
                              )
                            : null,
                        filled: true,
                        fillColor: const Color(0xFFF5F6FA),
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
                          ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(categoryId: null));
                        },
                      ),
                      ...categories.map((cat) => _CategoryChip(
                        label: cat.name,
                        selected: _selectedCategory == cat.id,
                        onTap: () {
                          setState(() => _selectedCategory = cat.id);
                          ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(categoryId: cat.id));
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
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
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
                            ref.read(elearningFiltersProvider.notifier).update((f) => f.copyWith(sort: tab.$1));
                          },
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),

          // ── Course Grid ───────────────────────────────────────────────────
          Consumer(builder: (_, ref, __) {
            final coursesAsync = ref.watch(elearningCoursesProvider);
            return coursesAsync.when(
              loading: () => SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.72,
                  ),
                  delegate: SliverChildBuilderDelegate(
                    (_, __) => const _CourseCardSkeleton(),
                    childCount: 6,
                  ),
                ),
              ),
              error: (e, _) => SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(40),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                    const SizedBox(height: 12),
                    Text(e.toString(), textAlign: TextAlign.center, style: const TextStyle(color: Colors.grey)),
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
                        Text('No courses found', style: TextStyle(color: Colors.grey[500], fontSize: 16, fontWeight: FontWeight.w600)),
                      ]),
                    ),
                  );
                }
                return SliverPadding(
                  padding: const EdgeInsets.all(16),
                  sliver: SliverGrid(
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.72,
                    ),
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
  const _AppBarAction({required this.icon, required this.label, required this.color, required this.onTap});

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

// ── Course Card ───────────────────────────────────────────────────────────────
class _CourseCard extends StatelessWidget {
  final ELearningCourse course;
  const _CourseCard({required this.course});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/elearning/course/${course.slug}'),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Thumbnail
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: course.thumbnail != null
                  ? CachedNetworkImage(
                      imageUrl: course.thumbnail!,
                      height: 110,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => _thumbnailPlaceholder(),
                      errorWidget: (_, __, ___) => _thumbnailPlaceholder(),
                    )
                  : _thumbnailPlaceholder(),
            ),
            // Content
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    course.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: AppColors.secondary, height: 1.3),
                  ),
                  if (course.instructor != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      course.instructor!.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                    ),
                  ],
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(Icons.star_rounded, color: Colors.amber, size: 13),
                      const SizedBox(width: 2),
                      Text(course.rating.toStringAsFixed(1), style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                      const SizedBox(width: 4),
                      Text('(${course.totalStudents})', style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                    ],
                  ),
                  const SizedBox(height: 6),
                  course.isFree
                      ? Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(color: Colors.green[50], borderRadius: BorderRadius.circular(6)),
                          child: const Text('FREE', style: TextStyle(color: Colors.green, fontSize: 11, fontWeight: FontWeight.w800)),
                        )
                      : Row(
                          children: [
                            Text(
                              '\$${course.effectivePrice.toStringAsFixed(2)}',
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: AppColors.primary),
                            ),
                            if (course.discountPrice != null) ...[
                              const SizedBox(width: 4),
                              Text(
                                '\$${course.price.toStringAsFixed(2)}',
                                style: const TextStyle(
                                  fontSize: 10,
                                  color: Colors.grey,
                                  decoration: TextDecoration.lineThrough,
                                ),
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

  Widget _thumbnailPlaceholder() {
    return Container(
      height: 110,
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [AppColors.secondary, Color(0xFF3D2B8E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: const Icon(Icons.school_rounded, color: Colors.white54, size: 36),
    );
  }
}

// ── Skeleton card ─────────────────────────────────────────────────────────────
class _CourseCardSkeleton extends StatelessWidget {
  const _CourseCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(height: 110, decoration: BoxDecoration(color: Colors.grey[200], borderRadius: const BorderRadius.vertical(top: Radius.circular(14)))),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(height: 12, width: double.infinity, decoration: BoxDecoration(color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
              const SizedBox(height: 6),
              Container(height: 10, width: 80, decoration: BoxDecoration(color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
              const SizedBox(height: 10),
              Container(height: 14, width: 60, decoration: BoxDecoration(color: Colors.grey[200], borderRadius: BorderRadius.circular(6))),
            ]),
          ),
        ],
      ),
    );
  }
}
