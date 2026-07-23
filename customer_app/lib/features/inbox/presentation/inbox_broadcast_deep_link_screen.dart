import 'package:flutter/material.dart';
import '../data/inbox_models.dart';
import '../data/inbox_repository.dart';
import 'inbox_marketing_screen.dart';

/// Shown when user taps a marketing FCM notification.
/// Fetches the broadcast by UUID then immediately replaces itself with InboxMarketingScreen.
class InboxBroadcastDeepLinkScreen extends StatefulWidget {
  const InboxBroadcastDeepLinkScreen({
    super.key,
    required this.uuid,
    required this.repo,
  });
  final String uuid;
  final InboxRepository repo;

  @override
  State<InboxBroadcastDeepLinkScreen> createState() => _State();
}

class _State extends State<InboxBroadcastDeepLinkScreen> {
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final broadcast = await widget.repo.getBroadcastByUuid(widget.uuid);
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => InboxMarketingScreen(broadcast: broadcast, repo: widget.repo),
        ),
      );
    } catch (_) {
      if (mounted) Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
