import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/module_api_service.dart';

class AppNotification {
  final String id;
  final String type;
  final Map<String, dynamic> data;
  final bool read;
  final String createdAt;

  const AppNotification({
    required this.id,
    required this.type,
    required this.data,
    required this.read,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> j) => AppNotification(
        id: j['id'] as String,
        type: j['type'] as String? ?? '',
        data: (j['data'] as Map?)?.cast<String, dynamic>() ?? {},
        read: j['read'] as bool? ?? false,
        createdAt: j['created_at'] as String? ?? '',
      );

  String get title => data['title'] as String? ?? 'Notification';
  String get body => data['body'] as String? ?? '';
}

// ── Provider ──────────────────────────────────────────────────────────────────

final notificationsProvider = AsyncNotifierProvider<NotificationsNotifier, List<AppNotification>>(
  NotificationsNotifier.new,
);

final unreadNotificationCountProvider = FutureProvider.autoDispose<int>((ref) async {
  final svc = ModuleApiService.create();
  final res = await svc.getUnreadNotificationCount();
  return (res?['data']?['count'] as int?) ?? 0;
});

class NotificationsNotifier extends AsyncNotifier<List<AppNotification>> {
  @override
  Future<List<AppNotification>> build() => _fetch();

  Future<List<AppNotification>> _fetch() async {
    final svc = ModuleApiService.create();
    final res = await svc.getNotifications();
    final items = res?['data'] as List? ?? [];
    return items
        .whereType<Map>()
        .map((e) => AppNotification.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<void> markRead(String id) async {
    final svc = ModuleApiService.create();
    await svc.markNotificationRead(id: id);
    final current = state.valueOrNull ?? [];
    state = AsyncData(current.map((n) => n.id == id
        ? AppNotification(id: n.id, type: n.type, data: n.data, read: true, createdAt: n.createdAt)
        : n).toList());
  }

  Future<void> markAllRead() async {
    final svc = ModuleApiService.create();
    await svc.markNotificationRead(all: true);
    final current = state.valueOrNull ?? [];
    state = AsyncData(current.map((n) => AppNotification(
        id: n.id, type: n.type, data: n.data, read: true, createdAt: n.createdAt)).toList());
  }
}
