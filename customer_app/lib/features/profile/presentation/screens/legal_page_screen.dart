import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../../../core/constants/app_constants.dart';

class LegalPageScreen extends StatefulWidget {
  final String slug;   // 'privacy-policy' | 'terms' | 'about'
  final String title;

  const LegalPageScreen({super.key, required this.slug, required this.title});

  @override
  State<LegalPageScreen> createState() => _LegalPageScreenState();
}

class _LegalPageScreenState extends State<LegalPageScreen> {
  late final WebViewController _controller;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    final url = '${AppConstants.baseUrl.replaceFirst('/api/v1', '')}/${widget.slug}?lang=en';
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onPageFinished: (_) => setState(() => _loading = false),
      ))
      ..loadRequest(Uri.parse(url));
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0D0D1A) : const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: Text(widget.title,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        backgroundColor: const Color(0xFF07003B),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Stack(children: [
        WebViewWidget(controller: _controller),
        if (_loading)
          const Center(child: CircularProgressIndicator(color: Color(0xFFF5A623))),
      ]),
    );
  }
}
