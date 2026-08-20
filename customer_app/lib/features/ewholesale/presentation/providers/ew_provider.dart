import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/ew_models.dart';
import '../../data/repositories/ewholesale_repository.dart';

// ── Repository singleton ───────────────────────────────────────────────────────

final ewRepoProvider = Provider((_) => EwholesaleRepository());

// ── Home ──────────────────────────────────────────────────────────────────────

final ewHomeProvider = FutureProvider<EwHomePayload>((ref) {
  return ref.watch(ewRepoProvider).getHome();
});

// ── Categories ────────────────────────────────────────────────────────────────

final ewCategoriesProvider = FutureProvider<List<EwCategory>>((ref) {
  return ref.watch(ewRepoProvider).getCategories();
});

final ewCategoryProvider = FutureProvider.family<EwCategory, int>((ref, id) {
  return ref.watch(ewRepoProvider).getCategory(id);
});

// ── Products listing ─────────────────────────────────────────────────────────

class EwProductFilter {
  final int? categoryId;
  final String? sort;
  final double? maxMoq;
  final double? minPrice;
  final double? maxPrice;
  final bool verifiedOnly;
  final bool goldOnly;
  final String? search;
  final int page;

  const EwProductFilter({
    this.categoryId, this.sort, this.maxMoq, this.minPrice, this.maxPrice,
    this.verifiedOnly = false, this.goldOnly = false, this.search, this.page = 1,
  });

  EwProductFilter copyWith({int? categoryId, String? sort, double? maxMoq,
    double? minPrice, double? maxPrice, bool? verifiedOnly, bool? goldOnly,
    String? search, int? page}) => EwProductFilter(
    categoryId:   categoryId   ?? this.categoryId,
    sort:         sort         ?? this.sort,
    maxMoq:       maxMoq       ?? this.maxMoq,
    minPrice:     minPrice     ?? this.minPrice,
    maxPrice:     maxPrice     ?? this.maxPrice,
    verifiedOnly: verifiedOnly ?? this.verifiedOnly,
    goldOnly:     goldOnly     ?? this.goldOnly,
    search:       search       ?? this.search,
    page:         page         ?? this.page,
  );
}

class EwProductListState {
  final List<EwProduct> products;
  final bool isLoading;
  final bool isLoadingMore;
  final bool hasMore;
  final String? error;
  final EwProductFilter filter;

  const EwProductListState({
    this.products = const [], this.isLoading = false,
    this.isLoadingMore = false, this.hasMore = true,
    this.error, required this.filter,
  });

  EwProductListState copyWith({List<EwProduct>? products, bool? isLoading,
    bool? isLoadingMore, bool? hasMore, String? error, EwProductFilter? filter}) =>
    EwProductListState(
      products:      products      ?? this.products,
      isLoading:     isLoading     ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      hasMore:       hasMore       ?? this.hasMore,
      error:         error,
      filter:        filter        ?? this.filter,
    );
}

class EwProductListNotifier extends AutoDisposeAsyncNotifier<EwProductListState> {
  @override
  Future<EwProductListState> build() async {
    final filter = const EwProductFilter();
    return _load(filter, reset: true);
  }

  Future<EwProductListState> _load(EwProductFilter filter, {bool reset = false}) async {
    final repo = ref.read(ewRepoProvider);
    final result = await repo.getProducts(
      categoryId:   filter.categoryId,
      minPrice:     filter.minPrice,
      maxPrice:     filter.maxPrice,
      maxMoq:       filter.maxMoq,
      verifiedOnly: filter.verifiedOnly,
      goldOnly:     filter.goldOnly,
      search:       filter.search,
      sort:         filter.sort,
      page:         filter.page,
    );
    final existing = (reset || state.value == null) ? <EwProduct>[] : state.value!.products;
    return EwProductListState(
      products:  [...existing, ...result.products],
      hasMore:   filter.page < result.lastPage,
      filter:    filter,
    );
  }

  Future<void> applyFilter(EwProductFilter filter) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => _load(filter.copyWith(page: 1), reset: true));
  }

  Future<void> loadMore() async {
    final cur = state.valueOrNull;
    if (cur == null || !cur.hasMore || cur.isLoadingMore) return;
    state = AsyncValue.data(cur.copyWith(isLoadingMore: true));
    final next = await AsyncValue.guard(() => _load(cur.filter.copyWith(page: cur.filter.page + 1)));
    state = next;
  }
}

final ewProductListProvider = AutoDisposeAsyncNotifierProvider<EwProductListNotifier, EwProductListState>(
  EwProductListNotifier.new,
);

// ── Product detail ────────────────────────────────────────────────────────────

final ewProductDetailProvider = FutureProvider.family<EwProduct, String>((ref, slug) {
  return ref.watch(ewRepoProvider).getProduct(slug);
});

// ── Supplier storefront ───────────────────────────────────────────────────────

final ewSupplierProvider = FutureProvider.family<EwSupplierCard, int>((ref, id) {
  return ref.watch(ewRepoProvider).getSupplier(id);
});

// ── Cart (client-side state) ─────────────────────────────────────────────────

class EwCartNotifier extends Notifier<List<EwCartLine>> {
  @override
  List<EwCartLine> build() => [];

  void add(EwCartLine line) {
    final idx = state.indexWhere((l) =>
      l.productId == line.productId && l.variantId == line.variantId);
    if (idx >= 0) {
      final updated = [...state];
      updated[idx] = updated[idx].copyWith(qty: updated[idx].qty + line.qty);
      state = updated;
    } else {
      state = [...state, line];
    }
  }

  void update(int productId, int? variantId, double qty) {
    state = state.map((l) {
      if (l.productId == productId && l.variantId == variantId) {
        return l.copyWith(qty: qty);
      }
      return l;
    }).toList();
  }

  void remove(int productId, int? variantId) {
    state = state.where((l) =>
      !(l.productId == productId && l.variantId == variantId)).toList();
  }

  void clear() => state = [];

  void replaceAll(List<EwCartLine> lines) => state = lines;

  int get count => state.fold(0, (s, l) => s + 1);
}

final ewCartProvider = NotifierProvider<EwCartNotifier, List<EwCartLine>>(EwCartNotifier.new);

// ── Cart validate result (debounced server call) ──────────────────────────────

final ewCartValidateProvider = FutureProvider.autoDispose<EwCartValidateResult?>((ref) async {
  final lines = ref.watch(ewCartProvider);
  if (lines.isEmpty) return null;
  return ref.watch(ewRepoProvider).cartValidate(lines);
});

// ── Quotes ────────────────────────────────────────────────────────────────────

final ewQuotesProvider = FutureProvider<List<EwQuote>>((ref) {
  return ref.watch(ewRepoProvider).getQuotes();
});

final ewQuoteDetailProvider = FutureProvider.family<EwQuote, int>((ref, id) {
  return ref.watch(ewRepoProvider).getQuote(id);
});

// ── RFQs ─────────────────────────────────────────────────────────────────────

final ewRfqsProvider = FutureProvider<List<EwRfq>>((ref) {
  return ref.watch(ewRepoProvider).getMyRfqs();
});

// ── Orders ────────────────────────────────────────────────────────────────────

final ewOrdersProvider = FutureProvider.family<List<EwOrder>, String?>((ref, status) {
  return ref.watch(ewRepoProvider).getOrders(status: status);
});

final ewOrderDetailProvider = FutureProvider.family<EwOrder, int>((ref, id) {
  return ref.watch(ewRepoProvider).getOrder(id);
});

// ── Buyer profile ─────────────────────────────────────────────────────────────

final ewBuyerProfileProvider = FutureProvider<EwBuyerProfile>((ref) {
  return ref.watch(ewRepoProvider).getBuyerProfile();
});

// ── Saved Lists ───────────────────────────────────────────────────────────────

final ewSavedListsProvider = FutureProvider<List<EwSavedList>>((ref) {
  return ref.watch(ewRepoProvider).getSavedLists();
});

// ── Search suggestions ────────────────────────────────────────────────────────

final ewSearchSuggestProvider = FutureProvider.family<List<String>, String>((ref, q) {
  if (q.length < 2) return Future.value([]);
  return ref.watch(ewRepoProvider).searchSuggest(q);
});
