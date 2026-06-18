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

class VendorsScreen extends ConsumerWidget {
  final String moduleSlug;
  const VendorsScreen({super.key, required this.moduleSlug});

  String get _moduleTitle {
    const names = {
      'efood': 'eFood', 'eshop': 'eShop', 'eticket': 'eTicket',
      'ehealth': 'eHealth', 'edata': 'eData', 'eparcel': 'eParcel',
      'erent': 'eRent', 'emoving': 'eMoving', 'ewholesale': 'eWholesale',
      'egrocery': 'eGrocery', 'eexchange': 'eExchange', 'elaundry': 'eLaundry',
    };
    return names[moduleSlug] ?? moduleSlug;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final vendorsAsync = ref.watch(vendorsByModuleProvider(moduleSlug));

    return Scaffold(
      
      appBar: AppBar(
        title: Text(_moduleTitle),
        
        foregroundColor: AppColors.textDark,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(icon: const Icon(Icons.search_rounded), onPressed: () {}),
          IconButton(icon: const Icon(Icons.filter_list_rounded), onPressed: () {}),
        ],
      ),
      body: vendorsAsync.when(
        loading: () => ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: 5,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (_, __) => _ShimmerCard(),
        ),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.wifi_off_rounded, size: 48, color: AppColors.textLight),
              const SizedBox(height: 12),
              Text(AppErrorHandler.message(e), style: const TextStyle(color: AppColors.textGrey)),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.refresh(vendorsByModuleProvider(moduleSlug)),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
        data: (vendors) {
          if (vendors.isEmpty) {
            return const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('🏪', style: TextStyle(fontSize: 56)),
                  SizedBox(height: 16),
                  Text('No vendors available yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.textDark)),
                  SizedBox(height: 6),
                  Text('Check back soon!', style: TextStyle(color: AppColors.textGrey)),
                ],
              ),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: vendors.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _VendorListCard(vendor: vendors[i]),
          );
        },
      ),
    );
  }
}

class _VendorListCard extends StatelessWidget {
  final VendorModel vendor;
  const _VendorListCard({required this.vendor});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/vendor/${vendor.id}'),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 8)],
        ),
        child: Column(
          children: [
            // Cover image
            Container(
              height: 140,
              decoration: BoxDecoration(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                color: AppColors.surface,
              ),
              clipBehavior: Clip.antiAlias,
              child: NetImage(
                url: vendor.coverUrl,
                fit: BoxFit.cover,
                width: double.infinity,
                errorWidget: _Placeholder(name: vendor.name),
              ),
            ),
            // Info
            Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  // Logo
                  Container(
                    width: 50, height: 50,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      color: AppColors.surface,
                      border: Border.all(color: AppColors.divider),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: NetImage(
                      url: vendor.logoUrl,
                      fit: BoxFit.cover,
                      errorWidget: const Icon(Icons.store, color: AppColors.textLight),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(vendor.name,
                                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: AppColors.textDark)),
                            ),
                            if (!vendor.isOpen)
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(6)),
                                child: const Text('Closed', style: TextStyle(color: Colors.red, fontSize: 11, fontWeight: FontWeight.w600)),
                              ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            if (vendor.rating != null) ...[
                              const Icon(Icons.star_rounded, color: Colors.amber, size: 14),
                              const SizedBox(width: 2),
                              Text(vendor.rating!.toStringAsFixed(1),
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textDark)),
                              Text(' (${vendor.reviewsCount ?? 0})',
                                style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
                              const SizedBox(width: 10),
                            ],
                            const Icon(Icons.access_time_rounded, size: 13, color: AppColors.textGrey),
                            const SizedBox(width: 2),
                            Text('${vendor.deliveryTime ?? 30} min',
                              style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                            const SizedBox(width: 10),
                            Icon(Icons.delivery_dining_rounded, size: 14,
                              color: vendor.deliveryFee == 0 ? Colors.green : AppColors.textGrey),
                            const SizedBox(width: 2),
                            Text(
                              vendor.deliveryFee == 0 ? 'Free' : '\$${vendor.deliveryFee.toStringAsFixed(0)}',
                              style: TextStyle(
                                fontSize: 12,
                                color: vendor.deliveryFee == 0 ? Colors.green : AppColors.textGrey,
                                fontWeight: vendor.deliveryFee == 0 ? FontWeight.w600 : FontWeight.w400,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Placeholder extends StatelessWidget {
  final String name;
  const _Placeholder({required this.name});

  @override
  Widget build(BuildContext context) => Container(
    color: AppColors.primary.withOpacity(0.12),
    child: Center(
      child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'V',
        style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w800, color: AppColors.primary)),
    ),
  );
}

class _ShimmerCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade200,
      highlightColor: Colors.grey.shade100,
      child: Container(
        height: 230,
        decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16)),
      ),
    );
  }
}
