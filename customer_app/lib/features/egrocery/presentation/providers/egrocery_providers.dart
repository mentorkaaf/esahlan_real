import 'dart:async';
import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../data/models/egrocery_models.dart';
import '../../data/repositories/egrocery_repository.dart';

// ── Repository ────────────────────────────────────────────────────────────────

final egroceryRepoProvider = Provider<EGroceryRepository>((_) => EGroceryRepository());

// ── Home ──────────────────────────────────────────────────────────────────────

final egHomeProvider = FutureProvider<EGHomePayload>((ref) {
  return ref.read(egroceryRepoProvider).getHome();
});

// ── Categories ────────────────────────────────────────────────────────────────

final egCategoriesProvider = FutureProvider<List<EGCategory>>((ref) {
  return ref.read(egroceryRepoProvider).getCategories();
});

final egCategoryDetailProvider = FutureProvider.family<EGCategory, int>((ref, id) {
  return ref.read(egroceryRepoProvider).getCategory(id);
});

// ── Products listing ─────────────────────────────────────────────────────────

class EGProductFilter {
  final int? categoryId;
  final int? brandId;
  final double? minPrice;
  final double? maxPrice;
  final bool? discounted;
  final bool? inStock;
  final String? search;
  final String? sort;

  const EGProductFilter({
    this.categoryId,
    this.brandId,
    this.minPrice,
    this.maxPrice,
    this.discounted,
    this.inStock,
    this.search,
    this.sort,
  });

  EGProductFilter copyWith({
    int? categoryId,
    int? brandId,
    double? minPrice,
    double? maxPrice,
    bool? discounted,
    bool? inStock,
    String? search,
    String? sort,
    bool clearCategory = false,
  }) =>
      EGProductFilter(
        categoryId: clearCategory ? null : (categoryId ?? this.categoryId),
        brandId: brandId ?? this.brandId,
        minPrice: minPrice ?? this.minPrice,
        maxPrice: maxPrice ?? this.maxPrice,
        discounted: discounted ?? this.discounted,
        inStock: inStock ?? this.inStock,
        search: search ?? this.search,
        sort: sort ?? this.sort,
      );
}

class EGProductListState {
  final List<EGProduct> products;
  final bool isLoading;
  final bool isLoadingMore;
  final int page;
  final int lastPage;
  final String? error;
  final EGProductFilter filter;

  const EGProductListState({
    this.products = const [],
    this.isLoading = false,
    this.isLoadingMore = false,
    this.page = 1,
    this.lastPage = 1,
    this.error,
    this.filter = const EGProductFilter(),
  });

  bool get hasMore => page < lastPage;

  EGProductListState copyWith({
    List<EGProduct>? products,
    bool? isLoading,
    bool? isLoadingMore,
    int? page,
    int? lastPage,
    String? error,
    EGProductFilter? filter,
  }) =>
      EGProductListState(
        products: products ?? this.products,
        isLoading: isLoading ?? this.isLoading,
        isLoadingMore: isLoadingMore ?? this.isLoadingMore,
        page: page ?? this.page,
        lastPage: lastPage ?? this.lastPage,
        error: error,
        filter: filter ?? this.filter,
      );
}

class EGProductListNotifier extends AsyncNotifier<EGProductListState> {
  @override
  Future<EGProductListState> build() async {
    return const EGProductListState();
  }

  Future<void> load(EGProductFilter filter) async {
    state = AsyncData(state.valueOrNull?.copyWith(isLoading: true, filter: filter) ?? EGProductListState(isLoading: true, filter: filter));
    try {
      final result = await ref.read(egroceryRepoProvider).getProducts(
            categoryId: filter.categoryId,
            brandId: filter.brandId,
            minPrice: filter.minPrice,
            maxPrice: filter.maxPrice,
            discounted: filter.discounted,
            inStock: filter.inStock,
            search: filter.search,
            sort: filter.sort,
          );
      state = AsyncData(EGProductListState(
        products: result.products,
        page: 1,
        lastPage: result.lastPage,
        filter: filter,
      ));
    } catch (e) {
      state = AsyncData(state.valueOrNull?.copyWith(isLoading: false, error: e.toString()) ??
          EGProductListState(error: e.toString()));
    }
  }

  Future<void> loadMore() async {
    final current = state.valueOrNull;
    if (current == null || !current.hasMore || current.isLoadingMore) return;

    state = AsyncData(current.copyWith(isLoadingMore: true));
    try {
      final result = await ref.read(egroceryRepoProvider).getProducts(
            categoryId: current.filter.categoryId,
            search: current.filter.search,
            sort: current.filter.sort,
            page: current.page + 1,
          );
      state = AsyncData(current.copyWith(
        products: [...current.products, ...result.products],
        page: current.page + 1,
        lastPage: result.lastPage,
        isLoadingMore: false,
      ));
    } catch (e) {
      state = AsyncData(current.copyWith(isLoadingMore: false));
    }
  }
}

final egProductListProvider = AsyncNotifierProvider<EGProductListNotifier, EGProductListState>(EGProductListNotifier.new);

// ── Product detail ────────────────────────────────────────────────────────────

final egProductDetailProvider = FutureProvider.family<EGProduct, String>((ref, slug) {
  return ref.read(egroceryRepoProvider).getProduct(slug);
});

// ── Search ────────────────────────────────────────────────────────────────────

final egSuggestProvider = FutureProvider.family<List<dynamic>, String>((ref, q) {
  if (q.length < 2) return Future.value([]);
  return ref.read(egroceryRepoProvider).suggest(q);
});

// ── Favorites ─────────────────────────────────────────────────────────────────

final egFavoritesProvider = FutureProvider<List<EGProduct>>((ref) {
  return ref.read(egroceryRepoProvider).getFavorites();
});

// ── Orders ────────────────────────────────────────────────────────────────────

final egOrdersProvider = FutureProvider<List<EGOrder>>((ref) async {
  final result = await ref.read(egroceryRepoProvider).getOrders();
  return result.orders;
});

final egOrderDetailProvider = FutureProvider.family<EGOrder, int>((ref, id) {
  return ref.read(egroceryRepoProvider).getOrder(id);
});

// ══════════════════════════════════════════════════════════════════════════════
// CART STATE — persisted via SharedPreferences
// ══════════════════════════════════════════════════════════════════════════════

const _kCartKey = 'eg_cart_lines';

class EGCartNotifier extends Notifier<List<EGCartLine>> {
  @override
  List<EGCartLine> build() {
    _load();
    return [];
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kCartKey);
    if (raw != null) {
      try {
        final list = (jsonDecode(raw) as List).map((e) => EGCartLine(
              variantId: e['variant_id'],
              qty: (e['qty'] as num).toDouble(),
              productName: e['product_name'] ?? '',
              variantLabel: e['variant_label'] ?? '',
              unitPrice: (e['unit_price'] as num?)?.toDouble() ?? 0,
            )).toList();
        state = list;
      } catch (_) {}
    }
  }

  Future<void> _persist() async {
    final prefs = await SharedPreferences.getInstance();
    final encoded = jsonEncode(state.map((l) => {
          'variant_id': l.variantId,
          'qty': l.qty,
          'product_name': l.productName,
          'variant_label': l.variantLabel,
          'unit_price': l.unitPrice,
        }).toList());
    await prefs.setString(_kCartKey, encoded);
  }

  void addOrIncrement(EGVariant variant, EGProduct product, {double qty = 1}) {
    final idx = state.indexWhere((l) => l.variantId == variant.id);
    if (idx >= 0) {
      final updated = List<EGCartLine>.from(state);
      updated[idx].qty += qty;
      state = updated;
    } else {
      state = [
        ...state,
        EGCartLine(
          variantId: variant.id,
          qty: qty,
          productName: product.name,
          variantLabel: variant.label,
          image: product.image,
          unitPrice: variant.effectivePrice,
          lineTotal: variant.effectivePrice * qty,
        ),
      ];
    }
    _persist();
  }

  void setQty(int variantId, double qty) {
    if (qty <= 0) {
      remove(variantId);
      return;
    }
    state = state.map((l) => l.variantId == variantId ? (l..qty = qty) : l).toList();
    _persist();
  }

  void remove(int variantId) {
    state = state.where((l) => l.variantId != variantId).toList();
    _persist();
  }

  void applyValidation(EGCartValidateResult result) {
    // Merge server-corrected lines back into cart
    final serverMap = {for (final l in result.lines) l.variantId: l};
    state = state.map((l) {
      final s = serverMap[l.variantId];
      if (s == null) return l;
      return EGCartLine(
        variantId: l.variantId,
        qty: s.qty,
        productName: s.productName.isNotEmpty ? s.productName : l.productName,
        variantLabel: s.variantLabel.isNotEmpty ? s.variantLabel : l.variantLabel,
        image: s.image ?? l.image,
        unitPrice: s.unitPrice,
        lineTotal: s.lineTotal,
        pricing: s.pricing,
      );
    }).where((l) => serverMap.containsKey(l.variantId)).toList();
    _persist();
  }

  void clear() {
    state = [];
    _persist();
  }

  double get itemCount => state.fold(0, (s, l) => s + l.qty);
  int get lineCount => state.length;
}

final egCartProvider = NotifierProvider<EGCartNotifier, List<EGCartLine>>(EGCartNotifier.new);
