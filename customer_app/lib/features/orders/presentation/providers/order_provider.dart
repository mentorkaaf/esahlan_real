import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/order_model.dart';
import '../../data/repositories/order_repository.dart';

final orderRepositoryProvider = Provider<OrderRepository>((ref) => OrderRepository());

final ordersProvider = FutureProvider.family<List<OrderModel>, String?>((ref, status) {
  return ref.read(orderRepositoryProvider).getOrders(status: status);
});

final orderDetailProvider = FutureProvider.family<OrderModel, int>((ref, id) {
  return ref.read(orderRepositoryProvider).getOrder(id);
});

final orderAnalyticsProvider = FutureProvider<Map<String, dynamic>>((ref) {
  return ref.read(orderRepositoryProvider).getAnalytics();
});
