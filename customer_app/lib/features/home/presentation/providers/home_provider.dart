import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/home_models.dart';
import '../../data/repositories/home_repository.dart';

final homeRepositoryProvider = Provider<HomeRepository>((ref) => HomeRepository());

final modulesProvider = FutureProvider<List<ModuleModel>>((ref) {
  return ref.read(homeRepositoryProvider).getModules();
});

final homeDataProvider = FutureProvider<Map<String, dynamic>>((ref) {
  return ref.read(homeRepositoryProvider).getHomeData();
});

final bannersProvider = FutureProvider<List<BannerModel>>((ref) async {
  final data = await ref.watch(homeDataProvider.future);
  final list = data['banners'] as List? ?? [];
  return list.map((e) => BannerModel.fromJson(e)).toList();
});

/// Public banner provider — uses GET /api/banners (no auth needed).
/// Used by home screen hero slider so it works even before full home data loads.
final homeBannersProvider = FutureProvider.autoDispose<List<BannerModel>>((ref) {
  return ref.read(homeRepositoryProvider).getHomeBanners();
});

final featuredVendorsProvider = FutureProvider<List<VendorModel>>((ref) async {
  final data = await ref.watch(homeDataProvider.future);
  final list = data['featured_vendors'] as List? ?? [];
  return list.map((e) => VendorModel.fromJson(e)).toList();
});

final popularProductsProvider = FutureProvider<List<ProductModel>>((ref) async {
  final data = await ref.watch(homeDataProvider.future);
  final list = data['popular_products'] as List? ?? [];
  return list.map((e) => ProductModel.fromJson(e)).toList();
});

// Vendors by module
final vendorsByModuleProvider = FutureProvider.family<List<VendorModel>, String>((ref, slug) {
  return ref.read(homeRepositoryProvider).getVendorsByModule(slug);
});

// Single vendor
final vendorProvider = FutureProvider.family<VendorModel, int>((ref, id) {
  return ref.read(homeRepositoryProvider).getVendor(id);
});

// Vendor products
final vendorProductsProvider = FutureProvider.family<List<ProductModel>, int>((ref, vendorId) {
  return ref.read(homeRepositoryProvider).getVendorProducts(vendorId);
});
