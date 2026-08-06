import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/global_models.dart';
import '../../data/repositories/global_repository.dart';

// ── Repository singleton ─────────────────────────────────────────────────────

final globalRepoProvider = Provider<GlobalRepository>((ref) {
  return GlobalRepository.instance;
});

// ── Auth ─────────────────────────────────────────────────────────────────────

final globalAuthProvider =
    AsyncNotifierProvider<GlobalAuthNotifier, GlobalUser?>(
        GlobalAuthNotifier.new);

class GlobalAuthNotifier extends AsyncNotifier<GlobalUser?> {
  @override
  Future<GlobalUser?> build() async {
    final repo = ref.read(globalRepoProvider);
    final t = await repo.token;
    if (t == null) return null;
    try {
      return await repo.getMe();
    } catch (_) {
      return null;
    }
  }

  GlobalRepository get _repo => ref.read(globalRepoProvider);

  Future<bool> login(String email, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final data = await _repo.login(email, password);
      await _repo.saveToken(data['token']);
      // Refresh cart
      ref.invalidate(globalCartProvider);
      return GlobalUser.fromJson(data['user']);
    });
    return state.hasValue && state.value != null;
  }

  Future<bool> register(Map<String, dynamic> data) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final res = await _repo.register(data);
      await _repo.saveToken(res['token']);
      ref.invalidate(globalCartProvider);
      return GlobalUser.fromJson(res['user']);
    });
    return state.hasValue && state.value != null;
  }

  Future<void> logout() async {
    try {
      await _repo.logout();
    } catch (_) {}
    await _repo.clearToken();
    ref.invalidate(globalCartProvider);
    state = const AsyncData(null);
  }

  /// Refresh user data from server (e.g. after address update)
  Future<void> fetchMe() async {
    try {
      final user = await _repo.getMe();
      state = AsyncData(user);
    } catch (_) {}
  }
}

// ── Products ─────────────────────────────────────────────────────────────────

final globalCategoriesProvider =
    FutureProvider<List<GlobalCategory>>((ref) async {
  return ref.read(globalRepoProvider).getCategories();
});

final globalFeaturedProvider =
    FutureProvider<List<GlobalProduct>>((ref) async {
  return ref.read(globalRepoProvider).getFeatured();
});

final globalFlashDealsProvider =
    FutureProvider<List<GlobalProduct>>((ref) async {
  return ref.read(globalRepoProvider).getFlashDeals();
});

// Products list with filters
class GlobalProductsParams {
  final String? q;
  final int? categoryId;
  final String? sort;

  const GlobalProductsParams({this.q, this.categoryId, this.sort});

  @override
  bool operator ==(Object other) =>
      other is GlobalProductsParams &&
      other.q == q &&
      other.categoryId == categoryId &&
      other.sort == sort;

  @override
  int get hashCode => Object.hash(q, categoryId, sort);
}

final globalProductsProvider = AsyncNotifierProvider.family<
    GlobalProductsNotifier, List<GlobalProduct>, GlobalProductsParams>(
    GlobalProductsNotifier.new);

class GlobalProductsNotifier extends FamilyAsyncNotifier<List<GlobalProduct>,
    GlobalProductsParams> {
  int _page = 1;
  bool hasMore = true;
  List<GlobalProduct> _items = [];

  @override
  Future<List<GlobalProduct>> build(GlobalProductsParams arg) async {
    _page = 1;
    hasMore = true;
    _items = [];
    return _fetch(arg);
  }

  Future<List<GlobalProduct>> _fetch(GlobalProductsParams arg) async {
    final res = await ref.read(globalRepoProvider).getProducts(
          q: arg.q,
          categoryId: arg.categoryId,
          sort: arg.sort,
          page: _page,
        );
    final data = res['products'];
    final List rawList =
        data is Map ? (data['data'] as List? ?? []) : (data as List? ?? []);
    final products = rawList.map((p) => GlobalProduct.fromJson(p)).toList();
    final pagination = res['pagination'] as Map<String, dynamic>? ?? {};
    hasMore = _page < (pagination['last_page'] as int? ?? 1);
    _items = [..._items, ...products];
    return List.unmodifiable(_items);
  }

  Future<void> loadMore() async {
    if (!hasMore || state.isLoading) return;
    _page++;
    state = await AsyncValue.guard(() => _fetch(arg));
  }
}

final globalProductDetailProvider =
    FutureProvider.family<GlobalProduct, int>((ref, id) async {
  return ref.read(globalRepoProvider).getProduct(id);
});

// ── Cart ─────────────────────────────────────────────────────────────────────

final globalCartProvider =
    AsyncNotifierProvider<GlobalCartNotifier, GlobalCart>(
        GlobalCartNotifier.new);

class GlobalCartNotifier extends AsyncNotifier<GlobalCart> {
  @override
  Future<GlobalCart> build() async {
    final repo = ref.read(globalRepoProvider);
    final t = await repo.token;
    if (t == null) return const GlobalCart();
    try {
      return await repo.getCart();
    } catch (_) {
      return const GlobalCart();
    }
  }

  Future<void> add(int productId, int qty, {String? variant}) async {
    state = await AsyncValue.guard(() => ref
        .read(globalRepoProvider)
        .addToCart(productId, qty, variant: variant));
  }

  Future<void> updateItem(int itemId, int qty) async {
    state = await AsyncValue.guard(
        () => ref.read(globalRepoProvider).updateCartItem(itemId, qty));
  }

  Future<void> remove(int itemId) async {
    state = await AsyncValue.guard(
        () => ref.read(globalRepoProvider).removeCartItem(itemId));
  }

  Future<void> clear() async {
    await ref.read(globalRepoProvider).clearCart();
    state = const AsyncData(GlobalCart());
  }
}

// ── Orders ────────────────────────────────────────────────────────────────────

final globalOrdersProvider =
    AsyncNotifierProvider<GlobalOrdersNotifier, List<GlobalOrder>>(
        GlobalOrdersNotifier.new);

class GlobalOrdersNotifier extends AsyncNotifier<List<GlobalOrder>> {
  int _page = 1;
  bool hasMore = true;
  List<GlobalOrder> _orders = [];

  @override
  Future<List<GlobalOrder>> build() async {
    _page = 1;
    hasMore = true;
    _orders = [];
    return _fetch();
  }

  Future<List<GlobalOrder>> _fetch() async {
    final res =
        await ref.read(globalRepoProvider).getOrders(page: _page);
    final data = res['orders'];
    final List rawList =
        data is Map ? (data['data'] as List? ?? []) : (data as List? ?? []);
    final orders = rawList.map((o) => GlobalOrder.fromJson(o)).toList();
    final pagination = res['pagination'] as Map<String, dynamic>? ?? {};
    hasMore = _page < (pagination['last_page'] as int? ?? 1);
    _orders = [..._orders, ...orders];
    return List.unmodifiable(_orders);
  }

  Future<void> loadMore() async {
    if (!hasMore || state.isLoading) return;
    _page++;
    state = await AsyncValue.guard(_fetch);
  }
}

final globalOrderDetailProvider =
    FutureProvider.family<GlobalOrder, int>((ref, id) async {
  return ref.read(globalRepoProvider).getOrder(id);
});

// ── Reviews ───────────────────────────────────────────────────────────────────

final globalReviewsProvider = FutureProvider.family<
    ({List<GlobalReview> reviews, GlobalReviewStats stats}), int>(
  (ref, productId) async {
    final res = await ref.read(globalRepoProvider).getReviews(productId);
    final reviews = (res['reviews'] as List? ?? [])
        .map((r) => GlobalReview.fromJson(r))
        .toList();
    final stats = GlobalReviewStats.fromJson(
        res['stats'] as Map<String, dynamic>? ?? {});
    return (reviews: reviews, stats: stats);
  },
);
