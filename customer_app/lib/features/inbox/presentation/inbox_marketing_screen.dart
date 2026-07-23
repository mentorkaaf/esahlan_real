import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../data/inbox_models.dart';
import '../data/inbox_repository.dart';

class InboxMarketingScreen extends StatefulWidget {
  const InboxMarketingScreen({super.key, required this.broadcast, required this.repo});
  final MarketingBroadcast broadcast;
  final InboxRepository repo;

  @override
  State<InboxMarketingScreen> createState() => _InboxMarketingScreenState();
}

class _InboxMarketingScreenState extends State<InboxMarketingScreen> {
  @override
  void initState() {
    super.initState();
    // Mark as read
    widget.repo.markBroadcastRead(widget.broadcast.uuid).catchError((_) {});
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final b = widget.broadcast;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: isDark ? const Color(0xFF1A1A2E) : Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: isDark ? Colors.white : const Color(0xFF07003B)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text('eSahlan', style: TextStyle(
          color: isDark ? Colors.white : const Color(0xFF07003B),
          fontWeight: FontWeight.w700,
        )),
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Hero image
            if (b.imageUrl != null)
              Image.network(b.imageUrl!, width: double.infinity, height: 220, fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const SizedBox.shrink()),

            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Module badge
                  if (b.module != null) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFF6C63FF).withAlpha(20),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(b.module!.toUpperCase(),
                          style: const TextStyle(color: Color(0xFF6C63FF), fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 1)),
                    ),
                    const SizedBox(height: 12),
                  ],

                  // Title
                  Text(b.title, style: TextStyle(
                    fontSize: 22, fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : const Color(0xFF07003B),
                    height: 1.3,
                  )),
                  const SizedBox(height: 8),

                  // Time
                  if (b.sentAt != null)
                    Text(_formatDate(b.sentAt!),
                        style: TextStyle(color: Colors.grey[500], fontSize: 12)),
                  const SizedBox(height: 16),

                  // Body
                  Text(b.body, style: TextStyle(
                    fontSize: 15, height: 1.6,
                    color: isDark ? Colors.white.withAlpha(200) : const Color(0xFF333355),
                  )),

                  const SizedBox(height: 32),

                  // CTA button
                  if (b.ctaLabel != null && b.ctaRoute != null) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: () => _handleCta(context),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF6C63FF),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          elevation: 0,
                        ),
                        child: Text(b.ctaLabel!,
                            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Center(
                      child: TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Maybe later', style: TextStyle(color: Colors.grey)),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _handleCta(BuildContext context) async {
    try {
      final route = await widget.repo.trackCtaClick(widget.broadcast.uuid);
      if (!mounted) return;
      if (route != null && route.isNotEmpty) {
        Navigator.pop(context);
        context.go(route);
      } else {
        Navigator.pop(context);
      }
    } catch (_) {
      if (mounted) Navigator.pop(context);
    }
  }

  String _formatDate(DateTime dt) {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return '${months[dt.month - 1]} ${dt.day}, ${dt.year}';
  }
}
