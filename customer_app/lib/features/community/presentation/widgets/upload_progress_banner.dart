import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/background_upload_service.dart';
import '../screens/community_shell.dart';

class UploadProgressBanner extends ConsumerWidget {
  const UploadProgressBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final upload = ref.watch(backgroundUploadProvider);
    if (upload.status == UploadStatus.idle) return const SizedBox.shrink();

    final isError = upload.status == UploadStatus.error;
    final isSuccess = upload.status == UploadStatus.success;

    return Container(
      margin: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      padding: EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isError ? const Color(0xFFFEE2E2) : isSuccess ? const Color(0xFFD1FAE5) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Row(children: [
        if (!isSuccess && !isError)
          SizedBox(width: 20, height: 20, child: CircularProgressIndicator(
            value: upload.status == UploadStatus.uploading ? upload.progress : null,
            strokeWidth: 2.5, color: kOrange)),
        if (isSuccess)
          Icon(Icons.check_circle_rounded, color: Color(0xFF10B981), size: 22),
        if (isError)
          Icon(Icons.error_rounded, color: Color(0xFFEF4444), size: 22),
        SizedBox(width: 10),
        Expanded(child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              isSuccess ? 'Post published!' : isError ? 'Upload failed' : 'Posting...',
              style: TextStyle(
                fontWeight: FontWeight.w700, fontSize: 13,
                color: isError ? const Color(0xFFEF4444) : isSuccess ? const Color(0xFF10B981) : const Color(0xFF1F2937)),
            ),
            if (upload.message != null && !isSuccess)
              Text(upload.message!, style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
          ],
        )),
        if (!isSuccess && !isError && upload.status == UploadStatus.uploading)
          Text('${(upload.progress * 100).toInt()}%',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: kOrange)),
        if (isError)
          GestureDetector(
            onTap: () => ref.read(backgroundUploadProvider.notifier).dismiss(),
            child: Padding(
              padding: EdgeInsets.all(4),
              child: Icon(Icons.close_rounded, size: 18, color: context.colors.mutedText),
            ),
          ),
      ]),
    );
  }
}
