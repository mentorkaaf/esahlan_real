import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../providers/elearning_provider.dart';

class ELearningCertificateScreen extends ConsumerWidget {
  final int certId;
  const ELearningCertificateScreen({super.key, required this.certId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final certsAsync = ref.watch(myCertificatesProvider);

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        title: Text('Certificate', style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: context.colors.navyText),
          onPressed: () => context.pop(),
        ),
      ),
      body: certsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (certs) {
          final cert = certs.where((c) => c.id == certId).firstOrNull;
          if (cert == null) {
            return Center(
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Icon(Icons.workspace_premium_rounded, size: 64, color: Colors.grey),
                const SizedBox(height: 12),
                const Text('Certificate not found'),
                const SizedBox(height: 12),
                ElevatedButton(onPressed: () => context.pop(), child: const Text('Go Back')),
              ]),
            );
          }
          return _CertificateView(cert: cert);
        },
      ),
    );
  }
}

class _CertificateView extends StatelessWidget {
  final ELearningCertificate cert;
  const _CertificateView({required this.cert});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        children: [
          // ── Certificate Design ────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.all(24),
            child: Container(
              width: double.infinity,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF07003B), Color(0xFF1a0570), Color(0xFF07003B)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(color: context.colors.navyText.withValues(alpha: 0.4), blurRadius: 20, offset: const Offset(0, 8)),
                ],
              ),
              child: Stack(
                children: [
                  // Gold border decoration
                  Positioned.fill(
                    child: Container(
                      margin: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: Colors.amber.withValues(alpha: 0.4), width: 1.5),
                      ),
                    ),
                  ),
                  // Corner decorations
                  Positioned(top: 16, left: 16, child: Icon(Icons.star_rounded, color: Colors.amber.withValues(alpha: 0.4), size: 28)),
                  Positioned(top: 16, right: 16, child: Icon(Icons.star_rounded, color: Colors.amber.withValues(alpha: 0.4), size: 28)),
                  Positioned(bottom: 16, left: 16, child: Icon(Icons.star_rounded, color: Colors.amber.withValues(alpha: 0.4), size: 28)),
                  Positioned(bottom: 16, right: 16, child: Icon(Icons.star_rounded, color: Colors.amber.withValues(alpha: 0.4), size: 28)),

                  Padding(
                    padding: const EdgeInsets.all(32),
                    child: Column(
                      children: [
                        const SizedBox(height: 8),
                        // Brand
                        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Icon(Icons.school_rounded, color: AppColors.primary, size: 20),
                          const SizedBox(width: 6),
                          RichText(text: const TextSpan(children: [
                            TextSpan(text: 'e-', style: TextStyle(color: AppColors.primary, fontSize: 18, fontWeight: FontWeight.w900)),
                            TextSpan(text: 'Sahlan', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
                          ])),
                        ]),
                        const SizedBox(height: 20),
                        const Text(
                          'Certificate of Completion',
                          style: TextStyle(color: Colors.amber, fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: 1),
                        ),
                        const SizedBox(height: 24),
                        const Text('This is to certify that', style: TextStyle(color: Colors.white60, fontSize: 12)),
                        const SizedBox(height: 8),
                        const Text(
                          'Student Name',
                          style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 16),
                        const Text('has successfully completed the course', style: TextStyle(color: Colors.white60, fontSize: 12)),
                        const SizedBox(height: 8),
                        Text(
                          cert.course.title,
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700, height: 1.3),
                        ),
                        const SizedBox(height: 24),
                        // Divider
                        Row(children: [
                          Expanded(child: Divider(color: Colors.amber.withValues(alpha: 0.3))),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            child: Icon(Icons.workspace_premium_rounded, color: Colors.amber, size: 24),
                          ),
                          Expanded(child: Divider(color: Colors.amber.withValues(alpha: 0.3))),
                        ]),
                        const SizedBox(height: 20),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('Issue Date', style: TextStyle(color: Colors.white38, fontSize: 10)),
                            Text(cert.issuedAt, style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
                          ]),
                          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                            Text('Certificate No.', style: TextStyle(color: Colors.white38, fontSize: 10)),
                            Text(cert.certificateNumber, style: const TextStyle(color: Colors.amber, fontSize: 11, fontWeight: FontWeight.w700, fontFamily: 'monospace')),
                          ]),
                        ]),
                        const SizedBox(height: 8),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          // ── Actions ───────────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () {
                      // Share functionality
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Share functionality coming soon')),
                      );
                    },
                    icon: const Icon(Icons.share_rounded),
                    label: const Text('Share'),
                    style: OutlinedButton.styleFrom(
                      foregroundcolor: context.colors.navyText,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                if (cert.pdfPath != null)
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () {
                        // Open PDF URL
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Opening: ${cert.pdfPath}')),
                        );
                      },
                      icon: const Icon(Icons.download_rounded),
                      label: const Text('Download'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}
