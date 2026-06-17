import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../models/ad_model.dart';
import '../services/ad_service.dart';
import '../../../core/widgets/network_image_widget.dart';

/// Show a popup ad. Returns true if the user clicked the CTA button.
Future<bool> showPopupAd(
  BuildContext context,
  AdModel ad, {
  required AdService adService,
}) async {
  if (ad.isFullScreen) {
    return await _showFullScreen(context, ad, adService) ?? false;
  } else {
    return await _showModal(context, ad, adService) ?? false;
  }
}

// ── Modal (center dialog) ─────────────────────────────────────────────────────

Future<bool?> _showModal(BuildContext context, AdModel ad, AdService adService) {
  return showGeneralDialog<bool>(
    context: context,
    barrierDismissible: false,
    barrierColor: Colors.black.withOpacity(0.6),
    transitionDuration: const Duration(milliseconds: 280),
    transitionBuilder: (_, anim, __, child) {
      return ScaleTransition(
        scale: CurvedAnimation(parent: anim, curve: Curves.easeOutBack),
        child: FadeTransition(opacity: anim, child: child),
      );
    },
    pageBuilder: (ctx, _, __) => _PopupAdSheet(ad: ad, adService: adService, fullScreen: false),
  );
}

// ── Full-screen ───────────────────────────────────────────────────────────────

Future<bool?> _showFullScreen(BuildContext context, AdModel ad, AdService adService) {
  return showGeneralDialog<bool>(
    context: context,
    barrierDismissible: false,
    barrierColor: Colors.black,
    transitionDuration: const Duration(milliseconds: 350),
    transitionBuilder: (_, anim, __, child) {
      return SlideTransition(
        position: Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
            .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
        child: child,
      );
    },
    pageBuilder: (ctx, _, __) => _PopupAdSheet(ad: ad, adService: adService, fullScreen: true),
  );
}

// ─── Shared sheet widget ──────────────────────────────────────────────────────

class _PopupAdSheet extends StatefulWidget {
  final AdModel ad;
  final AdService adService;
  final bool fullScreen;

  const _PopupAdSheet({
    required this.ad,
    required this.adService,
    required this.fullScreen,
  });

  @override
  State<_PopupAdSheet> createState() => _PopupAdSheetState();
}

class _PopupAdSheetState extends State<_PopupAdSheet> {
  bool _dontShowToday = false;

  AdModel get ad => widget.ad;

  void _close({bool clicked = false}) {
    if (_dontShowToday) {
      widget.adService.markDontShowToday(ad.id);
    }
    Navigator.of(context).pop(clicked);
  }

  void _onCtaPressed() {
    widget.adService.trackClick(ad.id);
    _handleAction();
    _close(clicked: true);
  }

  void _handleAction() {
    final val = ad.actionValue ?? '';
    switch (ad.actionType) {
      case 'module':
        if (val.isNotEmpty) context.push('/$val');
        break;
      case 'vendor':
        final id = int.tryParse(val);
        if (id != null) context.push('/vendor/$id');
        break;
      case 'product':
        final id = int.tryParse(val);
        if (id != null) context.push('/eshop/products/$id');
        break;
      case 'url':
        // External URL — just close; could open with url_launcher if available
        break;
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return widget.fullScreen ? _buildFullScreen() : _buildModal();
  }

  // ── Full-screen layout ──────────────────────────────────────────────────────

  Widget _buildFullScreen() {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(children: [
        // Background image
        if (ad.imageUrl != null)
          Positioned.fill(
            child: NetImage(
              url: ad.imageUrl!,
              fit: BoxFit.cover,
            ),
          ),
        // Gradient overlay (bottom)
        Positioned.fill(
          child: Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: [0.4, 1.0],
                colors: [Colors.transparent, Colors.black87],
              ),
            ),
          ),
        ),
        // Close button (top-right)
        Positioned(
          top: MediaQuery.of(context).padding.top + 12,
          right: 16,
          child: _CloseButton(onTap: _close),
        ),
        // Content (bottom)
        Positioned(
          left: 0, right: 0, bottom: 0,
          child: _buildBottomContent(dark: true),
        ),
      ]),
    );
  }

  // ── Modal layout ───────────────────────────────────────────────────────────

  Widget _buildModal() {
    final size = MediaQuery.of(context).size;
    return Center(
      child: Material(
        color: Colors.transparent,
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 24),
          constraints: BoxConstraints(maxWidth: 420, maxHeight: size.height * 0.85),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 40, offset: const Offset(0, 12))],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Image
                if (ad.imageUrl != null)
                  AspectRatio(
                    aspectRatio: 16 / 9,
                    child: Stack(children: [
                      NetImage(url: ad.imageUrl!, fit: BoxFit.cover,
                          width: double.infinity, height: double.infinity),
                      Positioned(
                        top: 10, right: 10,
                        child: _CloseButton(onTap: _close),
                      ),
                    ]),
                  )
                else
                  Container(
                    height: 8,
                    color: ad.buttonColor,
                    child: Align(
                      alignment: Alignment.topRight,
                      child: Padding(
                        padding: const EdgeInsets.all(4),
                        child: _CloseButton(onTap: _close, small: true),
                      ),
                    ),
                  ),

                if (ad.imageUrl == null)
                  Align(
                    alignment: Alignment.topRight,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(0, 8, 12, 0),
                      child: _CloseButton(onTap: _close),
                    ),
                  ),

                // Content
                Flexible(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
                    child: _buildTextContent(dark: false),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBottomContent({required bool dark}) {
    return Padding(
      padding: EdgeInsets.fromLTRB(24, 0, 24, MediaQuery.of(context).padding.bottom + 32),
      child: _buildTextContent(dark: dark),
    );
  }

  Widget _buildTextContent({required bool dark}) {
    final titleColor = dark ? Colors.white : const Color(0xFF1F2937);
    final bodyColor  = dark ? Colors.white70 : const Color(0xFF6B7280);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        // Title
        Text(
          ad.title,
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w900,
            color: titleColor,
            height: 1.2,
            letterSpacing: -0.5,
          ),
        ),

        // Description
        if (ad.description != null && ad.description!.isNotEmpty) ...[
          const SizedBox(height: 10),
          Text(
            ad.description!,
            style: TextStyle(fontSize: 14, color: bodyColor, height: 1.55),
          ),
        ],

        const SizedBox(height: 20),

        // CTA button
        if (ad.actionType != 'none')
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed: _onCtaPressed,
              style: ElevatedButton.styleFrom(
                backgroundColor: ad.buttonColor,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: Text(
                ad.buttonText,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
              ),
            ),
          ),

        // "Don't show today" toggle
        if (ad.allowDontShowToday) ...[
          const SizedBox(height: 14),
          GestureDetector(
            onTap: () => setState(() => _dontShowToday = !_dontShowToday),
            child: Row(children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: 18, height: 18,
                decoration: BoxDecoration(
                  color: _dontShowToday ? ad.buttonColor : Colors.transparent,
                  border: Border.all(
                    color: _dontShowToday ? ad.buttonColor : (dark ? Colors.white38 : const Color(0xFFD1D5DB)),
                    width: 1.5,
                  ),
                  borderRadius: BorderRadius.circular(5),
                ),
                child: _dontShowToday
                    ? const Icon(Icons.check, size: 13, color: Colors.white)
                    : null,
              ),
              const SizedBox(width: 8),
              Text(
                "Don't show today",
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: dark ? Colors.white60 : const Color(0xFF9CA3AF),
                ),
              ),
            ]),
          ),
        ],
      ],
    );
  }
}

// ── Close button ──────────────────────────────────────────────────────────────

class _CloseButton extends StatelessWidget {
  final VoidCallback onTap;
  final bool small;

  const _CloseButton({required this.onTap, this.small = false});

  @override
  Widget build(BuildContext context) {
    final size = small ? 28.0 : 34.0;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: size, height: size,
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.45),
          shape: BoxShape.circle,
        ),
        child: Icon(Icons.close_rounded, color: Colors.white, size: size * 0.55),
      ),
    );
  }
}
