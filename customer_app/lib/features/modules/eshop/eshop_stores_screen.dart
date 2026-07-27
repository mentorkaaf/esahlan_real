import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'eshop_providers.dart';

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;
Map<String, dynamic> _asMap(dynamic v) {
  if (v is Map<String, dynamic>) return v;
  if (v is Map) return Map<String, dynamic>.from(v);
  return {};
}
List<Map<String, dynamic>> _asList(dynamic v) {
  if (v is! List) return [];
  return v.map(_asMap).toList();
}

class EShopStoresScreen extends ConsumerStatefulWidget {
  const EShopStoresScreen({super.key});
  @override
  ConsumerState<EShopStoresScreen> createState() => _State();
}

class _State extends ConsumerState<EShopStoresScreen> {
  final _searchCtrl = TextEditingController();
  String _search = '';
  bool _featuredOnly = false;

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final storesAsync = ref.watch(eshopStoresProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('All Stores'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        foregroundColor: Theme.of(context).colorScheme.onSurface,
        elevation: 0,
      ),
      body: Column(
        children: [
          _SearchBar(
            ctrl: _searchCtrl,
            onChanged: (v) => setState(() => _search = v),
            featuredOnly: _featuredOnly,
            onFeaturedToggle: () => setState(() => _featuredOnly = !_featuredOnly),
          ),
          Expanded(
            child: storesAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(child: Text(e.toString())),
              data: (stores) {
                var filtered = stores;
                if (_search.isNotEmpty) {
                  final q = _search.toLowerCase();
                  filtered = stores
                      .where((s) => (s['name']?.toString() ?? '').toLowerCase().contains(q))
                      .toList();
                }
                if (_featuredOnly) {
                  filtered = filtered
                      .where((s) => s['is_featured'] == true || s['is_featured'] == 1)
                      .toList();
                }
                if (filtered.isEmpty) {
                  return const Center(
                    child: Text('No stores found', style: TextStyle(color: AppColors.textGrey)),
                  );
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: filtered.length,
                  itemBuilder: (_, i) => _StoreCard(store: filtered[i]),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchBar extends StatelessWidget {
  final TextEditingController ctrl;
  final ValueChanged<String> onChanged;
  final bool featuredOnly;
  final VoidCallback onFeaturedToggle;
  const _SearchBar({required this.ctrl, required this.onChanged, required this.featuredOnly, required this.onFeaturedToggle});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: ctrl,
              onChanged: onChanged,
              decoration: InputDecoration(
                hintText: 'Search stores…',
                prefixIcon: const Icon(Icons.search, size: 20),
                contentPadding: const EdgeInsets.symmetric(vertical: 10),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                filled: true,
                fillColor: Colors.grey[100],
              ),
            ),
          ),
          const SizedBox(width: 8),
          GestureDetector(
            onTap: onFeaturedToggle,
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: featuredOnly ? const Color(0xFFFF8A00) : Colors.grey[100],
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(children: [
                Icon(Icons.star, size: 15, color: featuredOnly ? Colors.white : Colors.grey),
                const SizedBox(width: 4),
                Text('Featured',
                    style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w600,
                        color: featuredOnly ? Colors.white : Colors.grey[700])),
              ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreCard extends StatelessWidget {
  final Map<String, dynamic> store;
  const _StoreCard({required this.store});

  @override
  Widget build(BuildContext context) {
    final id = int.tryParse(store['id']?.toString() ?? '0') ?? 0;
    final name = store['name']?.toString() ?? '';
    final description = store['description']?.toString() ?? '';
    final rating = _toD(store['rating']);
    final reviewCount = int.tryParse(store['review_count']?.toString() ?? '0') ?? 0;
    final isOpen = store['is_open'] == true || store['is_open'] == 1;
    final isFeatured = store['is_featured'] == true || store['is_featured'] == 1;
    final deliveryTime = store['delivery_time']?.toString();
    final deliveryFee = _toD(store['delivery_fee']);

    return GestureDetector(
      onTap: () => context.push('/eshop/stores/$id'),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Cover
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: Stack(
                children: [
                  SizedBox(
                    height: 100,
                    width: double.infinity,
                    child: store['cover_image'] != null
                        ? NetImage(url: store['cover_image']?.toString(), fit: BoxFit.cover)
                        : Container(
                            decoration: const BoxDecoration(
                              gradient: LinearGradient(
                                colors: [Color(0xFFFF8A00), Color(0xFFFF4E00)],
                              ),
                            ),
                            child: const Icon(Icons.store, size: 36, color: Colors.white30),
                          ),
                  ),
                  if (isFeatured)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFF8A00),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Row(mainAxisSize: MainAxisSize.min, children: [
                          Icon(Icons.star, size: 10, color: Colors.white),
                          SizedBox(width: 3),
                          Text('Featured', style: TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.w700)),
                        ]),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Logo
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.white, width: 2),
                      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 6)],
                      color: Colors.white,
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: store['logo'] != null
                          ? NetImage(url: store['logo']?.toString(), fit: BoxFit.cover)
                          : const Icon(Icons.store, size: 24, color: Colors.grey),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(children: [
                          Expanded(
                            child: Text(name,
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis),
                          ),
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: isOpen ? Colors.green : Colors.grey,
                            ),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            isOpen ? 'Open' : 'Closed',
                            style: TextStyle(fontSize: 11, color: isOpen ? Colors.green : Colors.grey),
                          ),
                        ]),
                        if (description.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text(description,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                          ),
                        const SizedBox(height: 6),
                        Row(children: [
                          const Icon(Icons.star, size: 12, color: Color(0xFFFFC107)),
                          const SizedBox(width: 2),
                          Text('${rating.toStringAsFixed(1)} ($reviewCount)',
                              style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
                          if (deliveryTime != null) ...[
                            const SizedBox(width: 10),
                            const Icon(Icons.access_time, size: 12, color: AppColors.textGrey),
                            const SizedBox(width: 2),
                            Text(deliveryTime,
                                style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
                          ],
                          const SizedBox(width: 10),
                          const Icon(Icons.delivery_dining, size: 12, color: AppColors.textGrey),
                          const SizedBox(width: 2),
                          Text(
                            deliveryFee == 0 ? 'Free' : '\$${deliveryFee.toStringAsFixed(2)}',
                            style: const TextStyle(fontSize: 11, color: AppColors.textGrey),
                          ),
                        ]),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.textGrey, size: 20),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
