import 'package:flutter/material.dart';
import '../api/api_client.dart';

/// Shows a bottom sheet explaining the user's active restriction.
/// Call via: RestrictionDialog.show(context, e);
class RestrictionDialog {
  static void show(BuildContext context, RestrictionException e) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => _RestrictionSheet(e: e),
    );
  }

  /// Use in catch blocks to handle both RestrictionException and generic errors.
  /// Returns true if it was a restriction (so callers can skip their own snackbar).
  static bool handle(BuildContext context, Object error) {
    final restriction = _extract(error);
    if (restriction != null) {
      show(context, restriction);
      return true;
    }
    return false;
  }

  static RestrictionException? _extract(Object error) {
    if (error is RestrictionException) return error;
    if (error is Exception && error.toString().contains('RestrictionException')) return null;
    // Unwrap from DioException
    try {
      final dio = error as dynamic;
      if (dio?.error is RestrictionException) return dio.error as RestrictionException;
    } catch (_) {}
    return null;
  }
}

class _RestrictionSheet extends StatelessWidget {
  final RestrictionException e;
  const _RestrictionSheet({required this.e});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? const Color(0xFF1C1C1E) : Colors.white;
    final sub = isDark ? const Color(0xFF8E8E93) : const Color(0xFF6B7280);

    final (icon, color, title) = _meta(e.restrictionType);

    String? expiry;
    if (e.expiresAt != null) {
      try {
        final dt = DateTime.parse(e.expiresAt!).toLocal();
        expiry = '${dt.day}/${dt.month}/${dt.year}';
      } catch (_) {}
    }

    return Container(
      margin: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 8),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: color.withOpacity(.3), borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          Container(
            width: 64, height: 64,
            decoration: BoxDecoration(color: color.withOpacity(.12), shape: BoxShape.circle),
            child: Icon(icon, color: color, size: 32),
          ),
          const SizedBox(height: 16),
          Text(title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17, color: color)),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: Text(
              e.message,
              textAlign: TextAlign.center,
              style: TextStyle(color: sub, fontSize: 14, height: 1.5),
            ),
          ),
          if (expiry != null) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.schedule_rounded, size: 14, color: sub),
                const SizedBox(width: 4),
                Text('Waxa dhammaanaysa: $expiry', style: TextStyle(color: sub, fontSize: 12)),
              ],
            ),
          ],
          const SizedBox(height: 20),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.pop(context),
                style: FilledButton.styleFrom(
                  backgroundColor: color,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: const Text('Xaaladayda eeg', style: TextStyle(fontWeight: FontWeight.w600)),
              ),
            ),
          ),
          SizedBox(height: MediaQuery.of(context).padding.bottom + 16),
        ],
      ),
    );
  }

  (IconData, Color, String) _meta(String type) => switch (type) {
    'comment_ban'  => (Icons.chat_bubble_outline_rounded, const Color(0xFFF59E0B), 'Comment ban'),
    'post_ban'     => (Icons.edit_off_rounded,            const Color(0xFFF59E0B), 'Post ban'),
    'message_ban'  => (Icons.mail_outline_rounded,         const Color(0xFFF59E0B), 'Message ban'),
    'live_ban'     => (Icons.videocam_off_rounded,         const Color(0xFFF59E0B), 'Live ban'),
    'shadow_reduce'=> (Icons.visibility_off_rounded,       const Color(0xFF6B7280), 'Shadow reduce'),
    'read_only'    => (Icons.lock_outline_rounded,         const Color(0xFFEF4444), 'Read-only mode'),
    'temp_suspend' => (Icons.block_rounded,                const Color(0xFFEF4444), 'Temporary suspension'),
    'perm_suspend' => (Icons.gavel_rounded,                const Color(0xFF7C3AED), 'Permanent suspension'),
    _              => (Icons.warning_amber_rounded,        const Color(0xFFEF4444), 'Restricted'),
  };
}
