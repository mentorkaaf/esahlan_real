import 'package:flutter/material.dart';
import '../../../core/api/module_api_service.dart';
import 'request_detail_screen.dart';

/// Fetches a single house request by ID (from FCM deep link) and
/// opens RequestDetailScreen.
class ERentRequestDeepLinkScreen extends StatefulWidget {
  final int requestId;
  final String? initialTab;

  const ERentRequestDeepLinkScreen({
    super.key,
    required this.requestId,
    this.initialTab,
  });

  @override
  State<ERentRequestDeepLinkScreen> createState() => _ERentRequestDeepLinkScreenState();
}

class _ERentRequestDeepLinkScreenState extends State<ERentRequestDeepLinkScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) return;
    try {
      final svc = ModuleApiService.create();
      final res = await svc.getHouseRequest(widget.requestId);
      final data = res['data'];
      if (data == null) throw Exception('Not found');

      final request = Map<String, dynamic>.from(data as Map);
      if (!mounted) return;

      await RequestDetailScreen.push(context, request, initialTab: widget.initialTab);
      if (mounted) Navigator.of(context).pop();
    } catch (_) {
      if (mounted) Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
