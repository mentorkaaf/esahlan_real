import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shimmer/shimmer.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../providers/home_provider.dart';
import '../../data/models/home_models.dart';

class VendorDetailScreen extends ConsumerWidget {
  final int vendorId;
  const VendorDetailScreen({super.key, required this.vendorId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final vendorAsync   = ref.watch(vendorProvider(vendorId));
    final productsAsync = ref.watch(vendorProductsProvider(vendorId));

    return Scaffold(
      
      body: vendorAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(child: Text(AppErrorHandler.message(e))),
        data: (vendor) => CustomScrollView(
          slivers: [
            // Cover + back button
            SliverAppBar(
              expandedHeight: 220,
              pinned: true,
              backgroundColor: context.colors.navyText,
              leading: GestureDetector(
                onTap: () => context.pop(),
                child: Container(
                  margin: const EdgeInsets.all(8),
                  decoration: BoxDecoration(color: Colors.black.withOpacity(0.4), shape: BoxShape.circle),
                  child: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
                ),
              ),
              actions: [
                GestureDetector(
                  onTap: () {},
                  child: Container(
                    margin: const EdgeInsets.all(8),
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(color: Colors.black.withOpacity(0.4), shape: BoxShape.circle),
                    child: const Icon(Icons.favorite_border_rounded, color: Colors.white, size: 20),
                  ),
                ),
              ],
              flexibleSpace: FlexibleSpaceBar(
                background: NetImage(
                  url: vendor.coverUrl,
                  fit: BoxFit.cover,
                  errorWidget: Container(color: AppColors.primary.withOpacity(0.2)),
                ),
              ),
            ),

            SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Vendor info card
                  Container(
                    color: Colors.white,
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Logo
                        Container(
                          width: 64, height: 64,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: AppColors.divider, width: 1.5),
                            color: AppColors.surface,
                          ),
                          clipBehavior: Clip.antiAlias,
                          child: NetImage(
                            url: vendor.logoUrl,
                            fit: BoxFit.cover,
                            errorWidget: const Icon(Icons.store, color: AppColors.textLight, size: 28),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(vendor.name,
                                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textDark)),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: vendor.isOpen ? Colors.green.shade50 : Colors.red.shade50,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      vendor.isOpen ? 'Open' : 'Closed',
                                      style: TextStyle(
                                        color: vendor.isOpen ? Colors.green : Colors.red,
                                        fontSize: 12, fontWeight: FontWeight.w700),
                                    ),
                                  ),
                                ],
                              ),
                              if (vendor.description != null) ...[
                                const SizedBox(height: 4),
                                Text(vendor.description!, maxLines: 2, overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 13, color: AppColors.textGrey)),
                              ],
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  _InfoChip(icon: Icons.star_rounded, label: vendor.rating?.toStringAsFixed(1) ?? 'N/A', color: Colors.amber),
                                  const SizedBox(width: 8),
                                  _InfoChip(icon: Icons.access_time_rounded, label: '${vendor.deliveryTime ?? 30} min', color: AppColors.primary),
                                  const SizedBox(width: 8),
                                  _InfoChip(
                                    icon: Icons.delivery_dining_rounded,
                                    label: vendor.deliveryFee == 0 ? 'Free' : '\$${vendor.deliveryFee.toStringAsFixed(0)}',
                                    color: Colors.green,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 8),

                  // Products section
                  const Padding(
                    padding: EdgeInsets.fromLTRB(16, 8, 16, 12),
                    child: Text('Menu', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.textDark)),
                  ),

                  productsAsync.when(
                    loading: () => Shimmer.fromColors(
                      baseColor: Colors.grey.shade200,
                      highlightColor: Colors.grey.shade100,
                      child: Column(
                        children: List.generate(4, (_) => Container(
                          height: 90, margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(12)),
                        )),
                      ),
                    ),
                    error: (_, __) => const Padding(
                      padding: EdgeInsets.all(32),
                      child: Center(child: Text('Failed to load menu', style: TextStyle(color: AppColors.textGrey))),
                    ),
                    data: (products) {
                      if (products.isEmpty) {
                        return const Padding(
                          padding: EdgeInsets.all(48),
                          child: Center(child: Text('No items available', style: TextStyle(color: AppColors.textGrey))),
                        );
                      }
                      return Column(
                        children: products.map((p) => _ProductRow(product: p)).toList(),
                      );
                    },
                  ),
                  const SizedBox(height: 100),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;

  const _InfoChip({required this.icon, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 3),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
        ],
      ),
    );
  }
}

class _ProductRow extends StatelessWidget {
  final ProductModel product;
  const _ProductRow({required this.product});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)],
      ),
      child: Row(
        children: [
          // Image
          Container(
            width: 72, height: 72,
            decoration: BoxDecoration(borderRadius: BorderRadius.circular(10), color: AppColors.surface),
            clipBehavior: Clip.antiAlias,
            child: NetImage(
                url: product.imageUrl,
                fit: BoxFit.cover,
                errorWidget: const Icon(Icons.fastfood_outlined, color: AppColors.textLight, size: 28),
              ),
          ),
          const SizedBox(width: 12),
          // Info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(product.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.textDark)),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text('\$${product.effectivePrice.toStringAsFixed(0)}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.primary)),
                    if (product.hasDiscount) ...[
                      const SizedBox(width: 6),
                      Text('\$${product.price.toStringAsFixed(0)}',
                        style: const TextStyle(fontSize: 12, color: AppColors.textLight,
                          decoration: TextDecoration.lineThrough)),
                    ],
                  ],
                ),
              ],
            ),
          ),
          // Add button
          GestureDetector(
            onTap: () {},
            child: Container(
              width: 34, height: 34,
              decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.add_rounded, color: Colors.white, size: 20),
            ),
          ),
        ],
      ),
    );
  }
}
